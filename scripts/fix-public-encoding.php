<?php
declare(strict_types=1);

$roots = [
    __DIR__ . '/../app/Views',
    __DIR__ . '/../app/Services',
    __DIR__ . '/../app/Controllers',
    __DIR__ . '/../config',
    __DIR__ . '/../public/assets/js',
];

$extensions = ['php', 'html', 'txt', 'md', 'xml', 'js', 'css'];

$mojibakeMap = [
    "\u{00C3}\u{00A1}" => "\u{00E1}",
    "\u{00C3}\u{00A0}" => "\u{00E0}",
    "\u{00C3}\u{00A2}" => "\u{00E2}",
    "\u{00C3}\u{00A3}" => "\u{00E3}",
    "\u{00C3}\u{00A9}" => "\u{00E9}",
    "\u{00C3}\u{00AA}" => "\u{00EA}",
    "\u{00C3}\u{00AD}" => "\u{00ED}",
    "\u{00C3}\u{00B3}" => "\u{00F3}",
    "\u{00C3}\u{00B4}" => "\u{00F4}",
    "\u{00C3}\u{00B5}" => "\u{00F5}",
    "\u{00C3}\u{00BA}" => "\u{00FA}",
    "\u{00C3}\u{00A7}" => "\u{00E7}",
    "\u{00C3}\u{0081}" => "\u{00C1}",
    "\u{00C3}\u{0089}" => "\u{00C9}",
    "\u{00C3}\u{008D}" => "\u{00CD}",
    "\u{00C3}\u{0093}" => "\u{00D3}",
    "\u{00C3}\u{0094}" => "\u{00D4}",
    "\u{00C3}\u{0095}" => "\u{00D5}",
    "\u{00C3}\u{009A}" => "\u{00DA}",
    "\u{00C3}\u{0087}" => "\u{00C7}",
    "\u{00C2}\u{00BA}" => "\u{00BA}",
    "\u{00C2}\u{00AA}" => "\u{00AA}",
    "\u{00C2}\u{00A0}" => " ",
    "\u{00E2}\u{0080}\u{0093}" => "-",
    "\u{00E2}\u{0080}\u{0094}" => "-",
    "\u{00E2}\u{0080}\u{0099}" => "'",
    "\u{00E2}\u{0080}\u{009C}" => '"',
    "\u{00E2}\u{0080}\u{009D}" => '"',
];

$wordMap = [
    'Estrat?gia' => "Estrat\u{00E9}gia",
    'estrat?gia' => "estrat\u{00E9}gia",
    're?ne' => "re\u{00FA}ne",
    'pr?ticas' => "pr\u{00E1}ticas",
    'pr?tica' => "pr\u{00E1}tica",
    'pr?ticos' => "pr\u{00E1}ticos",
    'pr?ximo' => "pr\u{00F3}ximo",
    'ru?do' => "ru\u{00ED}do",
    'perif?ricos' => "perif\u{00E9}ricos",
    'conte?do' => "conte\u{00FA}do",
    'n?o' => "n\u{00E3}o",
    'voc?' => "voc\u{00EA}",
    'op??es' => "op\u{00E7}\u{00F5}es",
    'recomenda??o' => "recomenda\u{00E7}\u{00E3}o",
    'fa?a' => "fa\u{00E7}a",
    'An?lises' => "An\u{00E1}lises",
    'seguran?a' => "seguran\u{00E7}a",
    'd?vidas' => "d\u{00FA}vidas",
    'sele??es' => "sele\u{00E7}\u{00F5}es",
    'utilidade ?til' => "utilidade \u{00FA}til",
    '?ndice' => "\u{00CD}ndice",
    'visualiza??es' => "visualiza\u{00E7}\u{00F5}es",
    'coment?rios' => "coment\u{00E1}rios",
    'Informa??es' => "Informa\u{00E7}\u{00F5}es",
    'conte?dos' => "conte\u{00FA}dos",
    'mat?ria' => "mat\u{00E9}ria",
    'Pol?tica' => "Pol\u{00ED}tica",
    'bot?es' => "bot\u{00F5}es",
    'at?' => "at\u{00E9}",
    'p?gina' => "p\u{00E1}gina",
    'configura??es' => "configura\u{00E7}\u{00F5}es",
    'p?blicas' => "p\u{00FA}blicas",
    'Exporta??o' => "Exporta\u{00E7}\u{00E3}o",
    'Verifica??o' => "Verifica\u{00E7}\u{00E3}o",
    'produ??o' => "produ\u{00E7}\u{00E3}o",
    'Publica??o' => "Publica\u{00E7}\u{00E3}o",
    'Aplica??o' => "Aplica\u{00E7}\u{00E3}o",
    'solicita??o' => "solicita\u{00E7}\u{00E3}o",
    '?ltimo' => "\u{00FA}ltimo",
    '?teis' => "\u{00FA}teis",
    'conte?do' => "conte\u{00FA}do",
];

$changed = [];

$iterFiles = static function (array $roots, array $extensions): array {
    $files = [];
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $ext = strtolower((string) $file->getExtension());
            if (!in_array($ext, $extensions, true)) {
                continue;
            }
            $files[] = $file->getPathname();
        }
    }
    return $files;
};

$files = $iterFiles($roots, $extensions);

foreach ($files as $file) {
    $content = (string) file_get_contents($file);
    $original = $content;

    foreach ($mojibakeMap as $bad => $good) {
        $content = str_replace($bad, $good, $content);
    }

    foreach ($wordMap as $bad => $good) {
        $content = str_replace($bad, $good, $content);
    }

    $content = str_replace("\u{00AD}", '', $content);

    if ($content !== $original) {
        file_put_contents($file, $content);
        $changed[] = $file;
    }
}

echo 'files_changed=' . count($changed) . PHP_EOL;
foreach ($changed as $file) {
    echo $file . PHP_EOL;
}
