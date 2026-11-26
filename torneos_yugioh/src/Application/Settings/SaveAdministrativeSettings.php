<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Settings;

use StationX\Yugioh\Domain\Settings\AdministrativeInput;

final readonly class SaveAdministrativeSettings
{
    public function __construct(private AdministrativeStore $store) {}

    public function settings(array $post): string
    {
        foreach (AdministrativeInput::settings($post) as $key => $value) {
            $this->store->saveSetting($key, $value);
        }
        $this->store->audit('settings', $this->store->currentSeasonId(), 'save_settings', $post);
        $this->store->refreshStatistics();
        return 'configuracion_guardada';
    }

    public function labels(array $post): string
    {
        $seasonId = $this->store->currentSeasonId();
        $this->store->saveLabels($seasonId, AdministrativeInput::labels($post));
        $this->store->audit('ui_labels', $seasonId, 'save_ui_labels', [
            'rows' => AdministrativeInput::labelCount($post),
        ]);
        return 'textos_guardados';
    }

    public function catalog(array $post): string
    {
        $seasonId = $this->store->currentSeasonId();
        $this->store->saveCatalog($seasonId, AdministrativeInput::catalog($post));
        $row = AdministrativeInput::newCatalog($post);
        if ($row[0] !== '' && $row[1] !== '' && $row[2] !== '') {
            $this->store->addCatalog($seasonId, $row);
        }
        $this->store->audit('catalog_items', $seasonId, 'save_catalog_items', [
            'updated' => count((array) ($post['catalog_id'] ?? [])),
            'new' => $row[0] . ':' . $row[1],
        ]);
        return 'opciones_guardadas';
    }
}
