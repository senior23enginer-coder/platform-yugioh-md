<?php
declare(strict_types=1);

/** Token-level characterization of extracted algorithms, independent of indentation and quote style. */
function architectureBodyTokens(string $source, array $originalNames = []): array
{
    $tokens = array_values(array_filter(token_get_all('<?php ' . $source), static fn($t) => !(is_array($t) && in_array($t[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))));
    $start = array_search('{', $tokens, true);
    if ($start === false) throw new RuntimeException('Missing function body');
    $tokens = array_slice($tokens, $start);
    $normalized = [];
    foreach ($tokens as $i => $token) {
        if ($token === ',' && in_array($tokens[$i + 1] ?? null, [')', ']'], true)) continue;
        if (is_array($token)) {
            [$kind, $value] = $token;
            if (in_array($kind, [T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)
                && is_array($tokens[$i + 1] ?? null) && $tokens[$i + 1][0] === T_DOUBLE_COLON
                && is_array($tokens[$i + 2] ?? null)) {
                $reference = ltrim($value, '\\') . '::' . $tokens[$i + 2][1];
                if (isset($originalNames[$reference])) {
                    $normalized[] = [T_STRING, $originalNames[$reference]];
                    continue;
                }
            }
            if ($kind === T_DOUBLE_COLON && is_array($tokens[$i - 1] ?? null) && is_array($tokens[$i + 1] ?? null)
                && isset($originalNames[ltrim($tokens[$i - 1][1], '\\') . '::' . $tokens[$i + 1][1]])) continue;
            if ($kind === T_STRING && is_array($tokens[$i - 1] ?? null) && $tokens[$i - 1][0] === T_DOUBLE_COLON && is_array($tokens[$i - 2] ?? null)
                && isset($originalNames[ltrim($tokens[$i - 2][1], '\\') . '::' . $value])) continue;
            if ($kind === T_CONSTANT_ENCAPSED_STRING) {
                // The tokenizer has already established this is one PHP string literal, not executable input.
                $value = eval('return ' . $value . ';');
                $value = $originalNames[ltrim($value, '\\')] ?? $value;
            }
            $normalized[] = [$kind, $value];
        } else $normalized[] = $token;
    }
    return $normalized;
}

function architectureBodyHash(string $source, array $originalNames = []): string
{
    return hash('sha256', json_encode(architectureBodyTokens($source, $originalNames), JSON_THROW_ON_ERROR));
}

/** Rewrite only native calls and exact callable literals; labels and unrelated strings remain intact. */
function architectureRewriteBody(string $body, array $references): string
{
    $tokens = token_get_all('<?php ' . $body);
    $significant = [];
    foreach ($tokens as $i => $token) if (!(is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) $significant[] = $i;
    $position = array_flip($significant);
    $out = '';
    foreach ($tokens as $i => $token) {
        if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
        $text = is_array($token) ? $token[1] : $token;
        if (is_array($token) && $token[0] === T_STRING && isset($references[$text])) {
            $next = $tokens[$significant[($position[$i] ?? -1) + 1] ?? -1] ?? null;
            if ($next === '(') $text = '\\' . $references[$text];
        }
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $value = eval('return ' . $text . ';');
            if (isset($references[$value])) $text = var_export($references[$value], true);
        }
        $out .= $text;
    }
    return $out;
}


/** Restore native calls only, so the same formatter can compare original and migrated syntax. */
function architectureRestoreBody(string $body, array $originalNames): string
{
    $tokens = token_get_all('<?php ' . $body);
    $out = '';
    foreach ($tokens as $i => $token) {
        if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
        if (is_array($token) && in_array($token[0], [T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)) {
            $reference = ltrim($token[1], '\\') . '::' . ($tokens[$i + 2][1] ?? '');
            if (isset($originalNames[$reference]) && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_DOUBLE_COLON) {
                $out .= $originalNames[$reference];
                continue;
            }
        }
        if (is_array($token) && $token[0] === T_DOUBLE_COLON && is_array($tokens[$i - 1] ?? null) && is_array($tokens[$i + 1] ?? null)
            && isset($originalNames[ltrim($tokens[$i - 1][1], '\\') . '::' . $tokens[$i + 1][1]])) continue;
        if (is_array($token) && $token[0] === T_STRING && is_array($tokens[$i - 1] ?? null) && $tokens[$i - 1][0] === T_DOUBLE_COLON && is_array($tokens[$i - 2] ?? null)
            && isset($originalNames[ltrim($tokens[$i - 2][1], '\\') . '::' . $token[1]])) continue;
        $text = is_array($token) ? $token[1] : $token;
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $value = eval('return ' . $text . ';');
            if (isset($originalNames[ltrim($value, '\\')])) $text = var_export($originalNames[ltrim($value, '\\')], true);
        }
        $out .= $text;
    }
    return $out;
}


/**
 * Historical branch fingerprints were produced with PHP token constants from
 * the baseline runtime. Permit cross-version verification ONLY when both full
 * evidence files retain their exact Git blob identities. Every migrated action
 * is still compared to the original source by compare-domain-contracts.cjs.
 * This does not certify or suppress a change to either original snapshot.
 */
function architectureFormCommandPinnedEvidence(string $root): bool
{
    $file = file_get_contents($root . '/tests/fixtures/form-command-originals.json');
    $manifest = file_get_contents($root . '/context/form-command-extractions.json');
    if (!is_string($file) || !is_string($manifest)) return false;
    $blob = static fn (string $bytes): string => sha1('blob ' . strlen($bytes) . "\0" . $bytes);
    return hash_equals('add933deb0de98204fcc73a2e14e1b54719851e7', $blob($file)) &&
        hash_equals('389f7f8fe587aef7ec8a9f08793164b0b02a4c49', $blob($manifest));
}
