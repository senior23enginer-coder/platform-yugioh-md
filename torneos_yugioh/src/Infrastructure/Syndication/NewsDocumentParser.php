<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Syndication;

use DOMDocument;
use DOMXPath;
use SimpleXMLElement;
use RuntimeException;
use StationX\Yugioh\Application\News\NewsDocuments;

/** Native provider document parsers; libxml is non-networked and its error state is restored. */
final class NewsDocumentParser implements NewsDocuments
{
    public function __construct(private readonly \StationX\Yugioh\Domain\News\NewsUrls $urls) {}

    public function dom(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            $dom->loadHTML(
                '<?xml encoding="UTF-8">' . $html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
            return [$dom, new DOMXPath($dom)];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function parseOrg(string $xml): array
    {
        $old = libxml_use_internal_errors(true);
        try {
            $root = simplexml_load_string(
                $xml,
                SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_NOCDATA,
            );
            if (!$root || !isset($root->channel->item)) {
                throw new RuntimeException('El RSS de la fuente cambió.');
            }
            $rows = [];
            foreach ($root->channel->item as $item) {
                $description = (string) $item->description;
                preg_match('~<img[^>]+src=[\'"]([^\'"]+)~i', $description, $m);
                $rows[] = [
                    'url' => (string) $item->link,
                    'title' => (string) $item->title,
                    'excerpt' => $description,
                    'date' => (string) $item->pubDate,
                    'image' => $m[1] ?? '',
                    'category' => implode(
                        ' ',
                        array_map('strval', iterator_to_array($item->category, false)),
                    ),
                ];
            }
            return $rows;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }

    public function orgImages(string $html): array
    {
        [$dom, $xp] = $this->dom($html);
        $images = [];
        foreach ($xp->query('//article') as $article) {
            $a = $xp->query('.//a[@href]', $article)->item(0);
            $img = $xp->query('.//img[contains(@src,"cdn.ygorganization.com")]', $article)->item(0);
            if (!$a || !$img) {
                continue;
            }
            $path = $a->getAttribute('href');
            if (str_starts_with($path, './')) {
                $path = '/' . substr($path, 2);
            }
            $url = $this->urls->normalize($path, 'https://ygorganization.com/');
            if ($url) {
                $images[rtrim($url, '/')] = $img->getAttribute('src');
            }
        }
        return $images;
    }

    public function parseMeta(string $json): array
    {
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !array_is_list($data)) {
            throw new RuntimeException('El catálogo público de la fuente cambió.');
        }
        $rows = [];
        foreach ($data as $r) {
            if (!is_array($r) || !empty($r['hidden'])) {
                continue;
            }
            $path = (string) ($r['url'] ?? '');
            if (!str_starts_with($path, '/articles/')) {
                $path = '/articles/' . ltrim($path, '/');
            }
            $rows[] = [
                'url' => $path,
                'title' => $r['title'] ?? '',
                'excerpt' => $r['description'] ?? '',
                'date' => $r['date'] ?? '',
                'image' => $r['image'] ?? '',
                'category' =>
                    ($r['category'] ?? '') .
                    ' ' .
                    (!empty($r['ocg']) ? 'OCG' : '') .
                    ' ' .
                    (!empty($r['genesys']) ? 'Genesys' : ''),
            ];
        }
        return $rows;
    }

    public function parseListing(string $source, string $html): array
    {
        [$dom, $xp] = $this->dom($html);
        $master = $source === 'masterduelmeta';
        $rows = [];
        foreach (
            $xp->query('//a[contains(@href,"' . ($master ? '/articles/' : '/article/') . '")]')
            as $a
        ) {
            $title = $master
                ? $xp
                    ->query('.//*[contains(concat(" ",normalize-space(@class)," ")," title ")]', $a)
                    ->item(0)?->textContent
                : $a->textContent;
            if (!$title) {
                continue;
            }
            $excerpt = '';
            if (!$master) {
                $excerpt =
                    $xp->query('.//*[contains(@class,"card-excerpt")]', $a->parentNode)->item(0)
                        ?->textContent ?? '';
            }
            $image = $xp->query('.//img', $a)->item(0);
            $img = $image?->getAttribute('src') ?? '';
            if (!$master && !$img) {
                $parent = $a->parentNode;
                for ($i = 0; $i < 3 && $parent; $i++, $parent = $parent->parentNode) {
                    $found = $xp
                        ->query('.//img[contains(@src,"/images/articles/")]', $parent)
                        ->item(0);
                    if ($found) {
                        $img = $found->getAttribute('src');
                        break;
                    }
                }
            }
            if (!$img && $image) {
                $set = $image->getAttribute('srcset');
                if (preg_match('~https://wsrv\.nl/\?url=([^& ]+)~', $set, $m)) {
                    $img = urldecode($m[1]);
                }
            }
            $text = $master
                ? $xp->query('.//*[contains(@class,"sub-text")]', $a)->item(0)?->textContent ?? ''
                : '';
            $date = '';
            if (
                preg_match(
                    '/(?:Published|Updated)\s+([A-Z][a-z]+ \d{1,2})(?:st|nd|rd|th)?, (\d{4})/',
                    $text,
                    $m,
                )
            ) {
                $date = $m[1] . ', ' . $m[2];
            }
            $row = [
                'url' => $a->getAttribute('href'),
                'title' => $title,
                'excerpt' => $excerpt,
                'image' => $img,
                'date' => $date,
                'category' => $a->textContent,
            ];
            $rows[$row['url']] = $row;
        }
        return array_slice(array_values($rows), 0, 16);
    }

    public function articleMetadata(string $html): array
    {
        [$dom, $xp] = $this->dom($html);
        $meta = [];
        foreach ($xp->query('//meta[@content]') as $node) {
            $meta[
                $node->getAttribute('property') ?: $node->getAttribute('name')
            ] = $node->getAttribute('content');
        }
        $date = $meta['article:published_time'] ?? '';
        if (!$date) {
            foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
                $r = json_decode($script->textContent, true);
                if (is_array($r) && isset($r['datePublished'])) {
                    $date = (string) $r['datePublished'];
                    break;
                }
            }
        }
        return [
            'excerpt' => $meta['og:description'] ?? ($meta['description'] ?? ''),
            'image' => $meta['og:image'] ?? '',
            'date' => $date,
        ];
    }
}
