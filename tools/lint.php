<?php
declare(strict_types=1);

$basePath = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
);

$failures = [];

foreach ($iterator as $fileInfo) {
    if (! $fileInfo instanceof SplFileInfo) {
        continue;
    }

    $path = $fileInfo->getPathname();

    if ($fileInfo->getExtension() !== 'php') {
        continue;
    }

    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $contents = file_get_contents($path);

    if (! is_string($contents)) {
        $failures[$path][] = 'Could not read file contents.';
        continue;
    }

    if (! preg_match('/^<\?php\s+declare\(strict_types=1\);/s', $contents)) {
        $failures[$path][] = 'Missing declare(strict_types=1); at the top of the file.';
    }

    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        $failures[$path][] = implode(PHP_EOL, $output);
    }
}

if ($failures === []) {
    fwrite(STDOUT, "PHP lint passed.\n");
    exit(0);
}

foreach ($failures as $path => $messages) {
    fwrite(STDERR, $path . PHP_EOL);

    foreach ($messages as $message) {
        fwrite(STDERR, '- ' . $message . PHP_EOL);
    }
}

exit(1);
