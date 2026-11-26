<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

use InvalidArgumentException;

final readonly class SaveFfaAction
{
    public function __construct(private FfaActionGateway $gateway) {}

    public function execute(array $post): string
    {
        $id = (int) ($post['ffa_match_id'] ?? 0);
        $command = ['kind' => (string) ($post['ffa_kind'] ?? '')];
        foreach (['deck_id', 'delta'] as $key) {
            if (isset($post['ffa_' . $key])) {
                $value = filter_var($post['ffa_' . $key], FILTER_VALIDATE_INT);
                if ($value === false) {
                    throw new InvalidArgumentException('Valor de accion invalido.');
                }
                $command[$key] = $value;
            }
        }
        if (isset($post['ffa_reason'])) {
            $command['reason'] = (string) $post['ffa_reason'];
        }
        $this->gateway->command(
            $id,
            (int) ($post['ffa_revision'] ?? -1),
            (string) ($post['ffa_request'] ?? ''),
            $command,
        );
        return 'evento_configurado_' . $this->gateway->competition($id);
    }
}
