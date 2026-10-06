<?php

/**
 * Puts a "Directory access is forbidden" page into every folder that has no
 * index file of its own, so a web server with directory listing switched on
 * serves that instead of the contents of the folder.
 *
 * Only the project root is meant to be reachable over HTTP at all — and only
 * so that its own `index.html` can send the browser on to `public/`, which is
 * where the application lives. Everything else up here is support: the code in
 * `app/` and `system/`, the dependencies in `vendor/`, the logs and sessions in
 * `writable/`. CodeIgniter ships a guard in the folders it creates; this writes
 * the ones nobody shipped.
 *
 * Run it after `composer install` or `composer update`, which replace the whole
 * of `vendor/` and take its guards with them:
 *
 *     php scripts/guard-directories.php
 *
 * It never touches an existing index file, so running it twice does nothing the
 * first run did not already do.
 *
 * This is a second line and not the first one. A folder nobody can list can
 * still be read file by file by anybody who guesses a name, so the thing that
 * actually settles it is pointing the web server at `public/` as its document
 * root — or, failing that, `Options -Indexes` plus a deny rule in an
 * `.htaccess` at this level. The guards are what holds while that is not done.
 */

$root = dirname(__DIR__);

// The root carries the redirect to the app and writes itself; `public/` is the
// one folder that *is* served, and its own .htaccess already says -Indexes.
$skip = ['.git', 'public'];

const GUARD = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>403 Forbidden</title>
</head>
<body>

<p>Directory access is forbidden.</p>

</body>
</html>

HTML;

/** Every folder under the root, less the ones above. */
$folders = static function (string $root, array $skip): iterable {
    $tree = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($root, $skip): bool {
                if (! $file->isDir()) {
                    return false;
                }

                $relative = substr($file->getPathname(), strlen($root) + 1);

                foreach ($skip as $top) {
                    if ($relative === $top || str_starts_with($relative, $top . DIRECTORY_SEPARATOR)) {
                        return false;
                    }
                }

                return true;
            }
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    // The root itself as well: without an index file it lists like any other.
    yield $root;

    foreach ($tree as $folder) {
        yield $folder->getPathname();
    }
};

$written = 0;

foreach ($folders($root, $skip) as $folder) {
    foreach (['index.html', 'index.htm', 'index.php'] as $index) {
        if (is_file($folder . DIRECTORY_SEPARATOR . $index)) {
            continue 2;
        }
    }

    file_put_contents($folder . DIRECTORY_SEPARATOR . 'index.html', GUARD);
    $written++;
}

echo $written === 0
    ? "Every folder already has an index file." . PHP_EOL
    : sprintf('Wrote %d index.html %s.%s', $written, $written === 1 ? 'guard' : 'guards', PHP_EOL);
