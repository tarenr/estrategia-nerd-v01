<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require __DIR__ . '/preflight-encoding.php';
$passed = 0;
$failed = 0;
$cases = [
    ['Âmbar, Ângelo, BOTÃO, NÃO, MAÇÃ', false],
    ['áàâãéêíóôõúçÁÀÂÃÉÊÍÓÔÕÚÇ', false],
    ['Estratégia Nerd — ação, café, © 2026, preço R$ 10', false],
    ['Texto ASCII sem acentos', false],
    ['', false],
    ["a\u{00C3}\u{00A7}\u{00C3}\u{00A3}o", true],
    ["caf\u{00C3}\u{00A9}", true],
    ["\u{00C3}\u{2030}", true],
    ["\u{00C2}\u{00A0}", true],
    ["\u{00C2}\u{00BA}", true],
    ["\u{00E2}\u{20AC}\u{2122}", true],
    ["\u{00F0}\u{0178}\u{02DC}\u{20AC}", true],
    ["substitui\u{FFFD}o", true],
    ["UTF-8 inv\xC3\x28lido", true],
];
foreach ($cases as $index => [$text, $expected]) {
    $ok = (preflightEncodingIssues($text) !== []) === $expected;
    $ok ? $passed++ : $failed++;
    echo ($ok ? 'PASS ' : 'FAIL ') . 'caso ' . ($index + 1) . "\n";
}
echo "Encoding: {$passed} OK, {$failed} falhas.\n";
exit($failed === 0 ? 0 : 1);
