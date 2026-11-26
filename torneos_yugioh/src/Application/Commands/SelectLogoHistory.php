<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

final readonly class SelectLogoHistory
{
    public function __construct(private LogoHistoryStore $store) {}

    public function execute(array $post): string
    {
        $entity = trim((string) ($post['logo_entity_type'] ?? ''));
        $id = (int) ($post['logo_entity_id'] ?? 0);
        $url = trim((string) ($post['logo_history_url'] ?? ''));
        if ($id > 0 && $url !== '' && in_array($entity, ['deck', 'team', 'competition'], true) && $this->store->contains($entity, $id, $url)) {
            $this->store->select($entity, $id, $url);
            $this->store->record($entity, $id, $url);
        }
        return 'logo_' . $entity . '_' . $id;
    }
}
