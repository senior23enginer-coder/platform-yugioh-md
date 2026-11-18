<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';

/** Selection operates on verified, closed source standings. It never manufactures substitutes. */
function ygo_qualification_candidates(array $source, array $row): array
{
    return \StationX\Yugioh\Domain\Competitions\QualificationPolicy::candidates($source, $row);
}

function ygo_qualification_mode(string $rule): ?string
{
    return \StationX\Yugioh\Domain\Competitions\QualificationPolicy::mode($rule);
}
