<?php
declare(strict_types=1);

// Public season page context derived from request and loaded seasons.
    $publicSeasonId = max(0, (int) ($_GET['public_season'] ?? 0));
    $publicSeasonRow = null;
    foreach (($appData['seasons'] ?? []) as $seasonRow) {
        if ((int) ($seasonRow['id'] ?? 0) === $publicSeasonId) {
            $publicSeasonRow = $seasonRow;
            break;
        }
    }
    $publicSeasonName = $publicSeasonRow
        ? (string) (($publicSeasonRow['name'] ?? '') ?: ('Temporada ' . $publicSeasonId))
        : ('Temporada ' . $publicSeasonId);
