<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Domain\News\NewsUrls;
use StationX\Yugioh\Application\News\NewsProviders;
use StationX\Yugioh\Application\News\NewsFetcher;
use StationX\Yugioh\Application\News\NewsDocuments;
use StationX\Yugioh\Application\News\PublicationDates;
use StationX\Yugioh\Application\News\NewsImageCache;
use StationX\Yugioh\Application\News\NewsServices;
use StationX\Yugioh\Application\News\NormalizeNewsItem;
use StationX\Yugioh\Application\News\DiscoverNews;
use StationX\Yugioh\Application\News\CollectNewsItems;
use StationX\Yugioh\Application\News\SynchronizeNews;
use StationX\Yugioh\Application\News\ListNews;
use StationX\Yugioh\Application\News\UpdateNewsReading;
use StationX\Yugioh\Application\News\GetNewsImage;
use StationX\Yugioh\Infrastructure\Configuration\News\NativeNewsProviders;
use StationX\Yugioh\Infrastructure\Network\News\CurlNewsFetcher;
use StationX\Yugioh\Infrastructure\Network\News\NativeNewsFailureMessage;
use StationX\Yugioh\Infrastructure\Syndication\NewsDocumentParser;
use StationX\Yugioh\Infrastructure\Time\NativePublicationDates;
use StationX\Yugioh\Infrastructure\Persistence\News\PdoNewsItems;
use StationX\Yugioh\Infrastructure\Persistence\News\PdoNewsSourceState;
use StationX\Yugioh\Infrastructure\Persistence\News\PdoNewsSyncLock;
use StationX\Yugioh\Infrastructure\Persistence\News\PdoNewsReadStore;
use StationX\Yugioh\Infrastructure\Persistence\News\PdoNewsReaderActions;
use StationX\Yugioh\Infrastructure\Persistence\News\PdoNewsImageUrls;
use StationX\Yugioh\Infrastructure\Cache\News\FilesystemNewsImageCache;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

/** No I/O or schema initialization during composition. Every external dependency is replaceable. */
final class NewsModule
{
    public static function urlPolicy(?NewsProviders $providers = null): NewsUrls
    {
        $native = new NativeNewsProviders();
        $providers ??= $native;
        return new NewsUrls(
            array_unique(
                array_map(
                    static fn($source) => parse_url($source['url'], PHP_URL_HOST),
                    $providers->all(),
                ),
            ),
            $native->imageHosts(),
        );
    }
    public static function fetcher(): NewsFetcher
    {
        return new CurlNewsFetcher(self::urlPolicy());
    }
    public static function parser(): NewsDocumentParser
    {
        return new NewsDocumentParser(self::urlPolicy());
    }
    public static function normalizer(): NormalizeNewsItem
    {
        return new NormalizeNewsItem(
            new NativePublicationDates(),
            new NativeNewsProviders(),
            self::urlPolicy(),
        );
    }
    public static function discovery(): DiscoverNews
    {
        return new DiscoverNews(self::fetcher(), self::parser(), new NativeNewsProviders());
    }

    public static function create(
        PDO $pdo,
        ?NewsFetcher $fetcher = null,
        ?NewsDocuments $documents = null,
        ?NewsImageCache $images = null,
        ?NewsProviders $providers = null,
        ?PublicationDates $dates = null,
    ): NewsServices {
        $providers ??= new NativeNewsProviders();
        $policy = self::urlPolicy($providers);
        $fetcher ??= new CurlNewsFetcher($policy);
        $documents ??= new NewsDocumentParser($policy);
        $dates ??= new NativePublicationDates();
        $items = new PdoNewsItems($pdo);
        $collector = new CollectNewsItems(
            new DiscoverNews($fetcher, $documents, $providers),
            new NormalizeNewsItem($dates, $providers, $policy),
            $items,
            $fetcher,
            $documents,
        );
        $images ??= new FilesystemNewsImageCache(
            dirname(__DIR__, 3) . '/assets/cache/news',
            $fetcher,
        );
        return new NewsServices(
            new ListNews(new PdoNewsReadStore($pdo), $providers),
            new SynchronizeNews(
                new PdoNewsSyncLock($pdo),
                new PdoNewsSourceState($pdo),
                $collector,
                $items,
                $items,
                new PdoTransactionBoundary($pdo, false),
                new NativeNewsFailureMessage(),
            ),
            new UpdateNewsReading(new PdoNewsReaderActions($pdo)),
            new GetNewsImage(new PdoNewsImageUrls($pdo), $images, $policy),
        );
    }
}
