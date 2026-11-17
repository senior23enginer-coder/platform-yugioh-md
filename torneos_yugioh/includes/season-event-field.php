<?php
$eventSeasonCurrent = !empty($dbStatus['connected']) && isset($pdo) ? ygo_current_season_id($pdo) : 0;
$eventSeasonName = '';
foreach (($appData['seasons'] ?? []) as $eventSeason) {
    if ((int) $eventSeason['id'] === $eventSeasonCurrent) {
        $eventSeasonName = (string) $eventSeason['name'];
        break;
    }
}
?>
<label class="<?php echo $seasonFieldMode === 'config' ? 'span-2' : 'span-all'; ?>">Temporada
    <input type="hidden" name="competition_season_id" value="<?php echo $eventSeasonCurrent; ?>" <?php echo $seasonFieldMode === 'config' ? 'data-competition-season' : 'data-gestion-competition-season'; ?> data-event-season-select>
    <input class="form-control" type="text" value="<?php echo e($eventSeasonName); ?>" data-event-season-name readonly>
</label>
