<?php

/**
 * Applies the laravel/ai structured-streaming patch to the package's own
 * vendor copy. Wired into composer's post-install-cmd / post-update-cmd so
 * the package's own dev test suite can rely on the patched behaviour.
 *
 * Production consumers (host projects, starter templates) handle the patch
 * themselves — either via their own composer-patches declaration or by
 * shipping the patched vendor file in the starter template.
 *
 * Marker check makes this idempotent.
 */

$file = __DIR__.'/../vendor/laravel/ai/src/Providers/Concerns/StreamsText.php';
$patch = __DIR__.'/../patches/laravel-ai-structured-streaming.patch';

if (! is_file($file)) {
    echo "  - laravel/ai not installed; skipping patch\n";
    exit(0);
}

if (str_contains((string) file_get_contents($file), 'JsonSchemaTypeFactory')) {
    echo "  - laravel/ai patch already applied\n";
    exit(0);
}

$dir = dirname($file, 4); // vendor/laravel/ai
$cmd = sprintf('cd %s && patch -p1 --forward --silent < %s 2>&1', escapeshellarg($dir), escapeshellarg($patch));
$out = [];
$rc = 0;
exec($cmd, $out, $rc);

if ($rc !== 0) {
    echo "  - apply-patches FAILED:\n    ".implode("\n    ", $out)."\n";
    exit($rc);
}

echo "  - laravel/ai patch applied\n";
