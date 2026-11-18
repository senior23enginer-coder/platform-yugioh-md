<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Configuration\News;

use StationX\Yugioh\Application\News\NewsProviders;

/** Existing fixed provider/display configuration, without network or database access. */
final class NativeNewsProviders implements NewsProviders
{
    public function all(): array
    {
        return [
            'ygorganization' => [
                'name' => 'YGOrganization',
                'url' => 'https://ygorganization.com/',
                'transport' => 'RSS',
                'color' => '#ffb45c',
                'short' => 'ORG',
            ],
            'ygoprodeck' => [
                'name' => 'YGOPRODeck',
                'url' => 'https://ygoprodeck.com/',
                'transport' => 'Artículos públicos',
                'color' => '#55d4ed',
                'short' => 'YGO',
            ],
            'masterduelmeta' => [
                'name' => 'Master Duel Meta',
                'url' => 'https://www.masterduelmeta.com/',
                'transport' => 'Artículos públicos',
                'color' => '#b49aff',
                'short' => 'MD',
            ],
            'yugiohmeta' => [
                'name' => 'Yu-Gi-Oh! Meta',
                'url' => 'https://www.yugiohmeta.com/',
                'transport' => 'Catálogo público del sitio',
                'color' => '#78e4b2',
                'short' => 'META',
            ],
        ];
    }

    public function imageHosts(): array
    {
        return [
            'ygorganization.com',
            'cdn.ygorganization.com',
            'ygoprodeck.com',
            'images.ygoprodeck.com',
            's3.duellinksmeta.com',
            'www.masterduelmeta.com',
            'www.yugiohmeta.com',
        ];
    }
}
