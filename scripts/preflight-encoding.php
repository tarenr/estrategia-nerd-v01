<?php
declare(strict_types=1);

/** @return list<string> */
function preflightEncodingIssues(string $content): array
{
    if (preg_match('//u', $content) !== 1) {
        return ['invalid-utf8'];
    }
    // Bytes de continuação UTF-8 interpretados como Latin-1/Windows-1252.
    // Letras legítimas após Ã/Â (BOTÃO, Âmbar, Ângelo) não são evidência de corrupção.
    $continuation = '[\x{0080}-\x{00BF}\x{0152}\x{0153}\x{0160}\x{0161}\x{0178}\x{017D}\x{017E}\x{0192}\x{02C6}\x{02DC}\x{2013}-\x{201E}\x{2020}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2122}]';
    $patterns = [
        '/\x{00C3}' . $continuation . '/u' => 'mojibake-utf8',
        '/\x{00C2}' . $continuation . '/u' => 'mojibake-cp1252',
        '/\x{00E2}\x{20AC}' . $continuation . '/u' => 'mojibake-punctuation',
        '/\x{00F0}\x{0178}' . $continuation . '/u' => 'mojibake-emoji',
        '/\x{FFFD}/u' => 'replacement-char',
    ];
    $issues = [];
    foreach ($patterns as $pattern => $label) {
        if (preg_match($pattern, $content) === 1) {
            $issues[] = $label;
        }
    }
    return $issues;
}
