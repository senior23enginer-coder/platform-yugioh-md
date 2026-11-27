<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

/** Structural invariants for new recipes; historical fixtures are never migrated here. */
function ygo_event_contract_errors(array $c,?array $preset=null): array {
    return \StationX\Yugioh\Domain\Competitions\EventContractPolicy::errors(
        $c, $preset, static fn($code) => function_exists('ygo_template_base_code') ? ygo_template_base_code($code) : $code,
    );
}
