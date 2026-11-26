<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

final readonly class SynchronizeOfflineForm
{
    private const ALLOWED = [
        'save_deck',
        'save_deck_analysis_profile',
        'save_matchup_analysis',
        'save_team',
        'save_competition',
        'configure_competition',
        'save_match_schedule',
        'save_division',
        'save_settings',
    ];
    private const ID_FIELDS = [
        'save_deck' => 'deck_id',
        'save_deck_analysis_profile' => 'deck_id',
        'save_matchup_analysis' => 'analysis_id',
        'save_team' => 'team_id',
        'save_competition' => 'competition_id',
        'configure_competition' => 'competition_id',
        'save_match_schedule' => 'schedule_match_id',
        'save_division' => 'division_id',
    ];

    public function __construct(
        private OfflineFormStore $store,
        private OfflineFormExecutor $executor,
    ) {}

    public function execute(array $post): string
    {
        $requestId =
            preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($post['offline_request_id'] ?? '')) ?:
            '';
        $payload = json_decode((string) ($post['offline_payload'] ?? ''), true);
        if (
            $requestId === '' ||
            !is_array($payload) ||
            !in_array((string) ($payload['action'] ?? ''), self::ALLOWED, true)
        ) {
            return 'offline_invalido';
        }
        $existing = $this->store->result($requestId);
        if ($existing !== null) {
            return $existing;
        }
        $action = (string) $payload['action'];
        $baseline = trim((string) ($payload['offline_entity_updated_at'] ?? ''));
        if ($baseline !== '' && isset(self::ID_FIELDS[$action])) {
            $id = (int) ($payload[self::ID_FIELDS[$action]] ?? 0);
            if ($id > 0) {
                $updated = $this->store->updatedAt($action, $id);
                if ($updated !== '' && $updated !== $baseline) {
                    return 'offline_conflicto';
                }
            }
        }
        unset($payload['offline_entity_updated_at']);
        $result = $this->executor->execute($payload);
        $this->store->record(
            $requestId,
            hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE) ?: ''),
            $result,
        );
        return $result;
    }
}
