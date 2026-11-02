<?php

/*
 * Applies a core update (docs/self-hosting-build-guide.md §5.3).
 *
 * Plain PHP on purpose — no Laravel, no autoloader — because it replaces
 * the very code Laravel runs from. CoreUpdater copies it out of app/ and runs
 * it in its own process:
 *
 *   php apply-core.php <base> <new core folder> <backup folder> <result.json> <php binary>
 *
 *  1. maintenance mode (old code)
 *  2. move each core path to the backup, the new one into place. public/
 *     itself stays (web servers serve from it); only what the package ships
 *     inside it is swapped, so public/storage (uploads) is never touched
 *  3. "artisan core:after-update" in the new code (migrations; it rolls
 *     back its own migrations if one fails)
 *  4. on any failure: put every old path back
 *  5. leave maintenance mode, write the result
 *
 * Never touched: .env, storage/, Modules/.
 */

const CORE_PATHS = [
    'app', 'bootstrap/app.php', 'bootstrap/providers.php', 'config', 'database', 'public',
    'resources', 'routes', 'vendor', 'artisan', 'composer.json', 'composer.lock', '.htaccess',
];

[, $base, $source, $backup, $resultFile, $php] = array_pad($argv, 6, null);

if (! $base || ! $source || ! $backup || ! $resultFile || ! $php) {
    fwrite(STDERR, "usage: apply-core.php <base> <source> <backup> <result> <php>\n");
    exit(2);
}

$moved = [];        // paths swapped, in order ("public/<entry>" inside public/)

$artisan = function (array $args) use ($php, $base): array {
    $process = proc_open(array_merge([$php, $base.'/artisan'], $args, ['--no-interaction']), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $base);

    if (! is_resource($process)) {
        return [1, 'Could not start php.'];
    }

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), trim($output)];
};

$remove = function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }

    if (is_dir($path)) {
        foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
            $remove($path.'/'.$entry);
        }

        @rmdir($path);
    }
};

$move = function (string $from, string $to): void {
    if (! is_dir(dirname($to))) {
        mkdir(dirname($to), 0755, true);
    }

    if (! @rename($from, $to)) {
        throw new RuntimeException("Could not move {$from} to {$to}.");
    }
};

$clearCaches = function () use ($base): void {
    foreach ((array) glob($base.'/bootstrap/cache/*.php') as $file) {
        @unlink($file);
    }
};

$result = ['status' => 'success', 'error' => null, 'log' => []];

$artisan(['down', '--retry=60']);

try {
    foreach (CORE_PATHS as $path) {
        if (! file_exists($source.'/'.$path)) {
            continue;
        }

        // The web root keeps its folder (a server may be holding on to it);
        // only the entries the package ships are swapped.
        $swap = $path === 'public' && is_dir($base.'/public')
            ? array_map(fn ($entry) => 'public/'.$entry, array_values(array_diff((array) scandir($source.'/public'), ['.', '..'])))
            : [$path];

        foreach ($swap as $item) {
            if (file_exists($base.'/'.$item) || is_link($base.'/'.$item)) {
                $move($base.'/'.$item, $backup.'/'.$item);
            }

            $move($source.'/'.$item, $base.'/'.$item);
            $moved[] = $item;
        }
    }

    $clearCaches();

    [$code, $output] = $artisan(['core:after-update']);
    $result['log'][] = $output;

    if ($code !== 0) {
        throw new RuntimeException($output !== '' ? $output : 'core:after-update failed.');
    }
} catch (Throwable $e) {
    $result['status'] = 'rolled_back';
    $result['error'] = $e->getMessage();

    try {
        foreach (array_reverse($moved) as $path) {
            $remove($base.'/'.$path);

            if (file_exists($backup.'/'.$path) || is_link($backup.'/'.$path)) {
                $move($backup.'/'.$path, $base.'/'.$path);
            }
        }

        $clearCaches();
    } catch (Throwable $restore) {
        $result['status'] = 'failed';
        $result['error'] .= ' — RESTORE FAILED: '.$restore->getMessage().'. The previous code is in '.$backup;
    }
}

$artisan(['up']);

file_put_contents($resultFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
exit($result['status'] === 'success' ? 0 : 1);
