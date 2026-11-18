<?php
declare(strict_types=1);

/**
 * media: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_store_event_logo_upload(): string
{
    $file = $_FILES['competition_logo'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return '';
    }
    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        return '';
    }
    $dir = YGO_APP_ROOT . '/assets/uploads/events';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'event-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return '';
    }
    return './assets/uploads/events/' . $name;
}

function ygo_media_src(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^(?:https?:)?//#i', $url) || str_starts_with($url, 'data:')) {
        return $url;
    }
    $url = str_replace('\\', '/', $url);
    $url = preg_replace('#^/?(?:domains/[^/]+/public_html/)?yugioh/#i', './', $url) ?? $url;
    $url = preg_replace('#^/?public_html/yugioh/#i', './', $url) ?? $url;
    if (str_starts_with($url, '/yugioh/')) {
        return '.' . substr($url, strlen('/yugioh'));
    }
    if (str_starts_with($url, 'assets/')) {
        return './' . $url;
    }
    if (str_starts_with($url, '/assets/')) {
        return '.' . $url;
    }
    return $url;
}

function ygo_store_team_logo_upload(int $teamId): string
{
    $file = $_FILES['team_logo'] ?? null;
    if ($teamId <= 0 || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return '';
    }
    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        return '';
    }
    $folder = 'team-' . $teamId;
    $dir = YGO_APP_ROOT . '/assets/uploads/teams/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'team-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return '';
    }
    return './assets/uploads/teams/' . $folder . '/' . $name;
}

function ygo_store_deck_logo_upload(int $deckId): string
{
    $file = $_FILES['deck_logo'] ?? null;
    if ($deckId <= 0 || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return '';
    }
    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        return '';
    }
    $folder = 'deck-' . $deckId;
    $dir = YGO_APP_ROOT . '/assets/uploads/decks/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'deck-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return '';
    }
    return './assets/uploads/decks/' . $folder . '/' . $name;
}

function ygo_store_division_logo_upload(int $divisionId): string
{
    $file = $_FILES['division_logo'] ?? null;
    if ($divisionId <= 0 || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return '';
    }
    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        return '';
    }
    $folder = 'division-' . $divisionId;
    $dir = YGO_APP_ROOT . '/assets/uploads/divisions/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'division-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return '';
    }
    return './assets/uploads/divisions/' . $folder . '/' . $name;
}

function ygo_event_logo_gallery(): array
{
    static $gallery = null;
    if ($gallery !== null) {
        return $gallery;
    }
    $typeDirs = [
        'qualifier' => ['clasificatorio'],
        'tournament' => ['torneo'],
        'league' => ['ligas'],
        'cup' => ['copa'],
        'world' => ['division1'],
        'final' => ['division2'],
        'special' => ['division3'],
        'friendly' => ['division4'],
    ];
    $base = YGO_APP_ROOT . '/assets/imagenes eventos';
    $gallery = [];
    foreach ($typeDirs as $type => $dirs) {
        $items = [];
        foreach ($dirs as $dir) {
            $path = $base . '/' . $dir;
            $files = [];
            if (is_dir($path)) {
                foreach (scandir($path) ?: [] as $file) {
                    if (preg_match('/\.(png|jpe?g|webp|gif)$/i', $file)) {
                        $files[] = $file;
                    }
                }
            }
            sort($files, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($files as $file) {
                $items[] = [
                    'url' => './assets/imagenes eventos/' . $dir . '/' . basename((string) $file),
                    'label' => 'Logo ' . (count($items) + 1),
                ];
            }
        }
        $gallery[$type] = $items;
    }
    return $gallery;
}

function ygo_valid_event_gallery_logo(string $url, string $type): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $gallery = ygo_event_logo_gallery();
    $allowed = $gallery[$type] ?? [];
    foreach ($allowed as $item) {
        if (($item['url'] ?? '') === $url) {
            return $url;
        }
    }
    return '';
}

function ygo_record_logo_history(PDO $pdo, string $entityType, int $entityId, string $logoUrl, string $source = 'upload'): void
{
    $logoUrl = trim($logoUrl);
    if ($entityId <= 0 || $logoUrl === '' || !in_array($entityType, ['deck', 'team', 'competition'], true)) {
        return;
    }
    $pdo->prepare('INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source) VALUES (?, ?, ?, ?)')
        ->execute([$entityType, $entityId, $logoUrl, substr($source, 0, 30)]);
}
