<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

use Throwable;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Decks\Packages\PackageFolder;
use StationX\Yugioh\Domain\Decks\Packages\PackagePreviewPolicy;

final readonly class DeckPackageWorkflow
{
    public function __construct(
        private DeckPackageInventory $inventory,
        private DeckPackageSources $sources,
        private DeckPackageArchive $archive,
        private DeckPackageTicketStore $tickets,
        private DeckPackageStaging $staging,
        private PrepareDeckPackage $prepare,
        private ExportDeckPackage $export,
        private PublishDeckPackage $publish,
        private Clock $clock,
    ) {}

    public function open(int $id): array
    {
        $inventory = $this->inventory->inventory($id);
        [$entry, $current] = $this->sources->current($id);
        return ['id' => $id, 'inventory' => $inventory, 'entry' => $entry, 'current' => $current];
    }

    public function status(array $context): array
    {
        $current = $context['current'];
        return [
            'has_data' => (bool) $current,
            'cards' => count($current['cards'] ?? []),
            'routes' => count($current['routes'] ?? []),
            'updated_at' => is_string($current['folder_delivery']['imported_at'] ?? null)
                ? $current['folder_delivery']['imported_at']
                : null,
        ];
    }

    public function export(array $context): array
    {
        return $this->export->execute(
            $context['inventory'],
            $context['current'],
            $this->sources->sources($context['id'], $context['entry']),
            $this->inventory->saved($context['id']),
        );
    }

    public function preview(array $context, string $upload): array
    {
        $id = $context['id'];
        $inventory = $context['inventory'];
        $files = PackageFolder::normalizeFiles($this->archive->read($upload));
        $sources = $this->sources->sources($id, $context['entry']);
        $record = $this->prepare->execute($files, $inventory, $context['current'], $sources);
        $structured = PackagePreviewPolicy::structured($files);
        $temp = $this->staging->stage($upload);
        try {
            $old = $this->tickets->pending();
            if ($old) {
                $this->staging->discard($old['path']);
            }
            $ticket = $this->staging->ticket();
            $saved = $this->inventory->saved($id);
            $this->tickets->save(
                PackagePreviewPolicy::pending(
                    $id,
                    $temp,
                    $ticket,
                    $this->clock->now()->getTimestamp(),
                    $context['entry'],
                    $inventory,
                    $saved,
                ),
            );
        } catch (Throwable $error) {
            $this->staging->discard($temp);
            throw $error;
        }
        return PackagePreviewPolicy::response(
            $ticket,
            $inventory,
            $context['current'],
            $record,
            $saved,
            $structured,
        );
    }

    public function commit(array $context, string $ticket): array
    {
        $pending = $this->tickets->pending();
        $id = $context['id'];
        $inventory = $context['inventory'];
        PackagePreviewPolicy::assertTicket(
            $pending,
            $id,
            $ticket,
            $this->clock->now()->getTimestamp(),
        );
        // Preserve the original short circuit: do not read revisions for a changed inventory.
        if ($pending['inventory'] !== $inventory['inventory_sha256']) {
            PackagePreviewPolicy::assertInventory($pending, $inventory, []);
        }
        PackagePreviewPolicy::assertInventory($pending, $inventory, $this->inventory->saved($id));
        $files = PackageFolder::normalizeFiles($this->archive->read($pending['path']));
        $record = $this->prepare->execute(
            $files,
            $inventory,
            $context['current'],
            $this->sources->sources($id, $context['entry']),
        );
        $entry = $this->publish->execute($id, $record, $files, $pending['registry']);
        $this->staging->discard($pending['path']);
        $this->tickets->clear();
        return ['entry' => $entry, 'deck' => $inventory['deck']['name']];
    }
}
