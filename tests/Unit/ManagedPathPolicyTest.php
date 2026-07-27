<?php

use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;

beforeEach(function () {
    $this->managedPathDirectory = sys_get_temp_dir().'/evolayer-managed-path-'.bin2hex(random_bytes(4));
    mkdir($this->managedPathDirectory, 0755, true);
    $this->managedPathPolicy = new ManagedPathPolicy;
});

afterEach(function () {
    foreach (scandir($this->managedPathDirectory) ?: [] as $entry) {
        if (in_array($entry, ['.', '..'], true)) {
            continue;
        }

        $path = $this->managedPathDirectory.'/'.$entry;
        is_dir($path) && ! is_link($path) ? rmdir($path) : unlink($path);
    }

    rmdir($this->managedPathDirectory);
});

test('managed mutation rejects directories and dangling links', function () {
    $directory = $this->managedPathDirectory.'/directory';
    $link = $this->managedPathDirectory.'/dangling-link';
    mkdir($directory);
    symlink($this->managedPathDirectory.'/missing', $link);

    expect(fn () => $this->managedPathPolicy->assertMutationTarget($directory))
        ->toThrow(ManagedPathException::class, 'regular, non-linked file')
        ->and(fn () => $this->managedPathPolicy->assertMutationTarget($link))
        ->toThrow(ManagedPathException::class, 'regular, non-linked file');
});

test('managed mutation rejects FIFO nodes when the platform exposes them', function () {
    if (! function_exists('posix_mkfifo')) {
        $this->markTestSkipped('posix_mkfifo is unavailable.');
    }

    $fifo = $this->managedPathDirectory.'/fifo';
    expect(posix_mkfifo($fifo, 0600))->toBeTrue()
        ->and(fn () => $this->managedPathPolicy->assertMutationTarget($fifo))
        ->toThrow(ManagedPathException::class, 'regular, non-linked file');
});

test('managed mutation rejects Unix socket nodes when supported', function () {
    $socketPath = $this->managedPathDirectory.'/socket';
    $socket = @stream_socket_server('unix://'.$socketPath, $errorCode, $errorMessage);

    if ($socket === false) {
        $this->markTestSkipped("Unix sockets are unavailable: {$errorCode} {$errorMessage}");
    }

    try {
        expect(fn () => $this->managedPathPolicy->assertMutationTarget($socketPath))
            ->toThrow(ManagedPathException::class, 'regular, non-linked file');
    } finally {
        fclose($socket);
    }
});

test('managed mutation rejects lexical aliases before filesystem inspection', function (string $path) {
    expect(fn () => $this->managedPathPolicy->assertMutationTarget($path))
        ->toThrow(ManagedPathException::class);
})->with([
    'relative' => 'relative/file.txt',
    'parent traversal' => '/tmp/../file.txt',
    'dot alias' => '/tmp/./file.txt',
    'double separator' => '/tmp//file.txt',
    'backslash' => '/tmp/nested\\file.txt',
]);
