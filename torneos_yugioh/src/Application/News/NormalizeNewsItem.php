<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

/** Normalizes provider identity/text/labels and delegates native date parsing through its port. */
final readonly class NormalizeNewsItem
{
    public function __construct(
        private PublicationDates $dates,
        private NewsProviders $providers,
        private \StationX\Yugioh\Domain\News\NewsUrls $urls,
    ) {}

    public function execute(string $source, array $row): ?array
    {
        $base = $this->providers->all()[$source]['url'];
        $url = $this->urls->normalize((string) ($row['url'] ?? ''), $base);
        $title = \StationX\Yugioh\Domain\News\NewsText::plain($row['title'] ?? '');
        if (
            !$url ||
            parse_url($url, PHP_URL_HOST) !== parse_url($base, PHP_URL_HOST) ||
            mb_strlen($title) < 4
        ) {
            return null;
        }
        if (
            $source === 'yugiohmeta' &&
            str_starts_with((string) ($row['image'] ?? ''), '/mdm_img/')
        ) {
            $row['image'] = 'https://s3.duellinksmeta.com' . $row['image'];
        }
        $url = rtrim(explode('?', $url)[0], '/');
        [$date, $precision] = $this->dates->parse((string) ($row['date'] ?? ''));
        [$format, $topic] = \StationX\Yugioh\Domain\News\NewsClassifier::classify(
            $source,
            $title . ' ' . ($row['excerpt'] ?? ''),
            (string) ($row['category'] ?? ''),
        );
        return [
            'source_key' => $source,
            'url' => $url,
            'title' => $title,
            'excerpt' => \StationX\Yugioh\Domain\News\NewsText::plain($row['excerpt'] ?? '', 320),
            'image_url' =>
                $this->urls->normalize((string) ($row['image'] ?? ''), $base, true) ?: null,
            'published_at' => $date,
            'date_precision' => $precision,
            'format_code' => $format,
            'topic' => $topic,
        ];
    }
}
