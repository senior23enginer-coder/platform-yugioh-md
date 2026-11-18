<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

use Throwable;
use RuntimeException;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\News\NewsContent;

/** Owns one due-source synchronization; provider failures preserve previously stored publications. */
final readonly class SynchronizeNews
{
    public function __construct(
        private NewsSyncLock $lock,
        private NewsSourceState $sources,
        private CollectNewsItems $collector,
        private NewsItemLookup $lookup,
        private NewsItemWriter $writer,
        private TransactionBoundary $transaction,
        private NewsFailureMessage $failures,
    ) {}

    public function execute(bool $retryFailed = false): array
    {
        if (!$this->lock->acquire()) {
            return ['busy' => true, 'checked' => false];
        }
        try {
            $source = $this->sources->nextDue($retryFailed);
            if (!$source) {
                return ['checked' => false, 'complete' => true];
            }
            $key = $source['source_key'];
            $this->sources->attempted($key);
            try {
                $items = $this->collector->execute($key);
                if (!$items) {
                    throw new RuntimeException(
                        'La fuente no entregó publicaciones reconocibles. Se conserva el historial anterior.',
                    );
                }
                return $this->transaction->run(function () use ($items, $key): array {
                    $imported = 0;
                    foreach ($items as $item) {
                        $hash = hash('sha256', $item['url']);
                        $old = $this->lookup->find($hash);
                        $item = NewsContent::merge($item, $old);
                        $content = NewsContent::hash($item);
                        if (!$old) {
                            $this->writer->insert($key, $hash, $item, $content);
                            $imported++;
                        } elseif ($old['content_hash'] !== $content) {
                            $this->writer->update((int) $old['id'], $item, $content);
                        }
                    }
                    $this->sources->succeeded($key, $imported);
                    return ['checked' => true, 'source' => $key, 'imported' => $imported];
                });
            } catch (Throwable $error) {
                $message = $this->failures->for($error);
                $this->sources->failed($key, mb_substr($message, 0, 300));
                return ['checked' => true, 'source' => $key, 'error' => $message];
            }
        } finally {
            $this->lock->release();
        }
    }
}
