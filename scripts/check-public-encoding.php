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

$badPatterns = [
    '/\x{00C3}./u' => 'mojibake-utf8',
    '/\x{00C2}./u' => 'mojibake-cp1252',
    '/\x{FFFD}/u' => 'replacement-char',
];

$files = [];
foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
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

$errors = [];
foreach ($files as $file) {
    $content = (string) file_get_contents($file);

    foreach ($badPatterns as $pattern => $label) {
        if (preg_match($pattern, $content) === 1) {
            $errors[] = $label . ' => ' . $file;
        }
    }

    $textWithoutUrls = preg_replace('~https?://\S+|/[^\s\'\"]*\?[^\s\'\"]*~u', '', $content) ?? $content;
    if (preg_match('/\p{L}\?\p{L}/u', $textWithoutUrls) === 1) {
        $errors[] = 'question-inside-word => ' . $file;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Encoding guard failed:\n" . implode("\n", array_unique($errors)) . "\n");
    exit(1);
}

echo "Encoding guard OK (files=" . count($files) . ")\n";
