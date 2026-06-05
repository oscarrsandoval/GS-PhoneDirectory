<?php
/**
 * Tiny JSON "database" helpers with file locking.
 *
 * Each store is a JSON array of associative records. Reads return [] when the
 * file is missing so the app works on a fresh install. Writes are atomic
 * (write to a temp file, then rename) and guarded with an exclusive lock so two
 * concurrent requests cannot corrupt the file.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Read a JSON array from disk. Returns [] if the file does not exist yet.
 *
 * @return array<int,array<string,mixed>>
 */
function read_json(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Unable to open data file: ' . $path);
    }

    try {
        flock($handle, LOCK_SH);
        $contents = stream_get_contents($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $data = json_decode($contents, true);
    if (!is_array($data)) {
        throw new RuntimeException('Corrupt JSON in data file: ' . $path);
    }

    return $data;
}

/**
 * Write a JSON array to disk atomically.
 *
 * @param array<int,array<string,mixed>> $data
 */
function write_json(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create data directory: ' . $dir);
    }

    $json = json_encode(
        array_values($data),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        throw new RuntimeException('Failed to encode JSON for: ' . $path);
    }

    // Write to a unique temp file in the same directory, then rename so readers
    // never see a half-written file.
    $tmp = tempnam($dir, 'tmp');
    if ($tmp === false) {
        throw new RuntimeException('Unable to create temp file in: ' . $dir);
    }

    $handle = fopen($tmp, 'wb');
    if ($handle === false) {
        @unlink($tmp);
        throw new RuntimeException('Unable to open temp file for writing.');
    }

    try {
        flock($handle, LOCK_EX);
        fwrite($handle, $json);
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to move temp file into place: ' . $path);
    }

    @chmod($path, 0664);
}

/**
 * Next auto-increment id for a list of records keyed by "id".
 *
 * @param array<int,array<string,mixed>> $records
 */
function next_id(array $records): int
{
    $max = 0;
    foreach ($records as $record) {
        $id = (int)($record['id'] ?? 0);
        if ($id > $max) {
            $max = $id;
        }
    }

    return $max + 1;
}

/**
 * Atomically read-modify-write a JSON store.
 *
 * Holds an exclusive lock across the WHOLE load → modify → save cycle using a
 * sidecar ".lock" file, so concurrent saves serialise instead of clobbering
 * each other. This is what prevents lost updates (and duplicate ids) when more
 * than one user edits at the same time.
 *
 * The lock is on a separate ".lock" file rather than the data file itself, so
 * it survives the atomic temp-file + rename in write_json(). Plain readers
 * (read_json) never block — they still get a complete file thanks to that
 * atomic rename.
 *
 * The mutator receives the current decoded array and must return the array to
 * persist. It runs while the lock is held, so any uniqueness / existence /
 * next-id logic inside it sees a consistent, exclusive view.
 *
 * @param callable(array<int,array<string,mixed>>):array<int,array<string,mixed>> $mutator
 * @return array<int,array<string,mixed>> The data that was written.
 */
function update_json(string $path, callable $mutator): array
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create data directory: ' . $dir);
    }

    $lock = fopen($path . '.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Unable to open lock file for: ' . $path);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire lock for: ' . $path);
        }

        $data = read_json($path);
        $new = $mutator($data);
        if (!is_array($new)) {
            throw new RuntimeException('update_json mutator must return an array.');
        }
        write_json($path, $new);

        return $new;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
