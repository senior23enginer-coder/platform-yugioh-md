<?php
declare(strict_types=1);

/** Enumerate native functions and their dependencies without loading the application or database. */
$root = dirname(__DIR__) . '/torneos_yugioh';
$files = [];
foreach (['includes', 'src/Infrastructure'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory)) as $entry) {
        if ($entry->isFile() && $entry->getExtension() === 'php') $files[] = $entry->getPathname();
    }
}
$functions = [];
foreach ($files as $file) {
    $source = file_get_contents($file);
    $tokens = token_get_all($source);
    $positions = [];
    $offset = 0;
    foreach ($tokens as $i => $token) {
        $positions[$i] = $offset;
        $offset += strlen(is_array($token) ? $token[1] : $token);
    }
    for ($i = 0, $size = count($tokens); $i < $size; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $next = $i + 1;
        while (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) $next++;
        if ($tokens[$next] === '&') $next++;
        if (!is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) continue;
        $name = $tokens[$next][1];
        // Class methods are outside the native compatibility inventory.
        if (!str_starts_with($name, 'ygo_') && !in_array($name, ['buildFixtures', 'build_round_robin_pairings', 'evaluate_duel_server', 'ensure_ygo_tables', 'seed_ygo_database'], true)) continue;
        $open = $next;
        while ($tokens[$open] !== '{' && $tokens[$open] !== ';') $open++;
        if ($tokens[$open] === ';') continue;
        $depth = 1;
        for ($end = $open + 1; $depth && $end < $size; $end++) {
            $token = $tokens[$end];
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $depth++;
            if ($token === '}') $depth--;
        }
        $end--;
        $signature = substr($source, $positions[$i], $positions[$open] - $positions[$i]);
        $body = substr($source, $positions[$open], $positions[$end] - $positions[$open] + 1);
        $functions[$name] = [
            'file' => str_replace('\\', '/', substr($file, strlen($root) + 1)),
            'start' => $positions[$i], 'end' => $positions[$end] + 1,
            'signature' => $signature, 'body' => $body, 'calls' => [], 'reasons' => [],
        ];
        $i = $end;
    }
}
$pureBuiltins = explode(' ', 'abs acos array_change_key_case array_chunk array_column array_combine array_diff array_diff_key array_fill array_fill_keys array_filter array_flip array_intersect array_intersect_key array_is_list array_key_exists array_key_first array_key_last array_keys array_map array_merge array_multisort array_pad array_pop array_push array_reduce array_replace array_reverse array_search array_shift array_slice array_splice array_sum array_unique array_unshift array_values arsort asort base64_decode base64_encode bin2hex boolval ceil chr count crc32 ctype_digit end explode filter_var floatval floor fmod hash hash_equals hexdec htmlspecialchars http_build_query iconv implode in_array intdiv intval is_array is_bool is_float is_int is_integer is_null is_numeric is_finite is_scalar is_string isset json_decode json_encode key krsort ksort lcfirst log ltrim max mb_strlen mb_strtolower mb_substr min natcasesort next number_format ord pow preg_match preg_match_all preg_quote preg_replace preg_split range reset round rsort rtrim serialize sha1 sort sprintf sqrt str_contains str_ends_with str_getcsv str_pad str_replace str_starts_with strcasecmp strcmp strlen strpos strrpos strtolower strtoupper strtr strval substr trim uasort uksort ucfirst ucwords unserialize urldecode urlencode usort version_compare');
foreach ($functions as $name => &$function) {
    $tokens = array_values(array_filter(token_get_all('<?php ' . $function['signature'] . $function['body']), static fn($token) => !(is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))));
    foreach ($tokens as $i => $token) {
        if (!is_array($token)) continue;
        [$kind, $text] = $token;
        if (in_array($kind, [T_GLOBAL, T_DIR, T_FILE, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_EVAL, T_EXIT, T_ECHO, T_PRINT, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) $function['reasons'][] = token_name($kind);
        if ($kind === T_VARIABLE && str_starts_with($text, '$_')) $function['reasons'][] = 'superglobal';
        if ($kind === T_STATIC && is_array($tokens[$i + 1] ?? null) && $tokens[$i + 1][0] === T_VARIABLE) $function['reasons'][] = 'static state';
        if ($kind === T_STRING && ($text === 'PDO' || preg_match('/^YGO_[A-Z_]+$/', $text))) $function['reasons'][] = 'external type/constant';
        if ($kind === T_NEW) {
            $class = $tokens[$i + 1][1] ?? '';
            if (!in_array($class, ['InvalidArgumentException', 'RuntimeException', 'DomainException', 'LogicException', 'LengthException'], true)) $function['reasons'][] = 'object creation: ' . $class;
        }
        if (in_array($kind, [T_STRING, T_NAME_FULLY_QUALIFIED], true) && ($tokens[$i + 1] ?? null) === '(') {
            $previous = $tokens[$i - 1] ?? null;
            if (is_array($previous) && in_array($previous[0], [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) continue;
            $function['calls'][] = ltrim($text, '\\');
        }
        if ($kind === T_CONSTANT_ENCAPSED_STRING) {
            $callback = substr($text, 1, -1);
            if (isset($functions[$callback])) $function['calls'][] = $callback;
        }
    }
    $function['calls'] = array_values(array_unique($function['calls']));
    foreach ($function['calls'] as $call) if (!isset($functions[$call]) && !in_array($call, $pureBuiltins, true)) $function['reasons'][] = 'external call: ' . $call;
    $function['reasons'] = array_values(array_unique($function['reasons']));
}
unset($function);
do {
    $changed = false;
    foreach ($functions as $name => &$function) {
        if ($function['reasons']) continue;
        foreach ($function['calls'] as $call) if (isset($functions[$call]) && $functions[$call]['reasons']) {
            $function['reasons'][] = 'mixed dependency: ' . $call;
            $changed = true;
            break;
        }
    }
    unset($function);
} while ($changed);
if (in_array('--summary', $argv, true)) {
    foreach ($functions as &$function) unset($function['signature'], $function['body'], $function['start'], $function['end']);
    unset($function);
}
echo json_encode($functions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
