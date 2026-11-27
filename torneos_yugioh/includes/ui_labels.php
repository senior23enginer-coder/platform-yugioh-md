<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

function ygo_ui_event_name(string $name): string {
    return \StationX\Yugioh\Application\Presentation\UiLabelProjection::eventName($name);
}
function ygo_ui_labels_catalog(array $divisions,array $competitions): array {
    return \StationX\Yugioh\Application\Presentation\UiLabelProjection::catalog($divisions,$competitions);
}
function ygo_ui_match_label(array $match,string $code,mixed $round,array $labels): string {
    return \StationX\Yugioh\Application\Presentation\UiLabelProjection::matchLabel($match,$code,$round,$labels);
}

function ygo_ui_labels_script(array $labels): string {
    return '<script>window.StationXPresentationCatalog='.json_encode($labels,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';</script><script src="./assets/ui-labels.js?v=20261026-labels-101" defer></script>';
}
