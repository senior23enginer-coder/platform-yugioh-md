<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use StationX\Yugioh\Domain\Decks\Packages\CalculatorContract;
use StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy;

final class DeckPackageReaderHtml
{
    public static function render(string $name, string $raw): string
    {
        $raw = CalculatorContract::withoutContract($raw);
        $e = static fn($v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '';
        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^(#{1,6})\s+(.*)$/u', $line, $m)) {
                $n = min(6, strlen($m[1]) + 1);
                $html .= '<h' . $n . '>' . $e(MarkdownStudy::plain($m[2])) . '</h' . $n . '>';
            } else {
                $html .= '<div class="line">' . $e($line) . '</div>';
            }
        }
        return '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; style-src &#39;unsafe-inline&#39;"><title>' .
            $e($name) .
            '</title><style>body{max-width:1000px;margin:24px auto;padding:16px;background:#081827;color:#e2efff;font:17px/1.6 system-ui}.line{white-space:pre-wrap;overflow-wrap:anywhere}h2,h3{color:#60dded}</style><h1>' .
            $e($name) .
            '</h1>' .
            $html .
            '</html>';
    }
}
