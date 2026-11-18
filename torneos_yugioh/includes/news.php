<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use StationX\Yugioh\Infrastructure\Composition\NewsModule;
use StationX\Yugioh\Infrastructure\Configuration\News\NativeNewsProviders;
use StationX\Yugioh\Infrastructure\Persistence\News\NewsSchema;
use StationX\Yugioh\Infrastructure\Time\NativePublicationDates;
use StationX\Yugioh\Domain\News\NewsText;
use StationX\Yugioh\Domain\News\NewsClassifier;

/** Compatibility facade. Source identity, HTTP transport, parsing and persistence are separate. */
function ygo_news_sources(): array
{
    return (new NativeNewsProviders())->all();
}

function ygo_news_schema(PDO $pdo): void
{
    NewsSchema::ensure($pdo);
}

function ygo_news_text(mixed $value, int $max = 350): string
{
    return NewsText::plain($value, $max);
}

function ygo_news_url(string $value, string $base, bool $image = false): string
{
    return NewsModule::urlPolicy()->normalize($value, $base, $image);
}

function ygo_news_fetch(string $url, int $max = 3500000): string
{
    return NewsModule::fetcher()->fetch($url, $max);
}

function ygo_news_dom(string $html): array
{
    return NewsModule::parser()->dom($html);
}

function ygo_news_date(string $value): array
{
    return (new NativePublicationDates())->parse($value);
}

function ygo_news_classify(string $source, string $text, string $category = ''): array
{
    return NewsClassifier::classify($source, $text, $category);
}

function ygo_news_normalize(string $source, array $row): ?array
{
    return NewsModule::normalizer()->execute($source, $row);
}

function ygo_news_parse_org(string $xml): array
{
    return NewsModule::parser()->parseOrg($xml);
}

function ygo_news_org_images(string $html): array
{
    return NewsModule::parser()->orgImages($html);
}

function ygo_news_parse_meta(string $json): array
{
    return NewsModule::parser()->parseMeta($json);
}

function ygo_news_parse_listing(string $source, string $html): array
{
    return NewsModule::parser()->parseListing($source, $html);
}

function ygo_news_article_metadata(string $html): array
{
    return NewsModule::parser()->articleMetadata($html);
}

function ygo_news_discover(string $source): array
{
    return NewsModule::discovery()->execute($source);
}

function ygo_news_sync(PDO $pdo, bool $retryFailed = false): array
{
    return NewsModule::create($pdo)->sync->execute($retryFailed);
}
