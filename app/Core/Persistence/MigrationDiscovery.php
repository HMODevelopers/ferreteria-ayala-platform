<?php
declare(strict_types=1);

namespace App\Core\Persistence;

use Throwable;

final class MigrationDiscovery
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @return array<string, Migration> */
    public function all(): array
    {
        if (!is_dir($this->directory) || !is_readable($this->directory)) {
            throw new PersistenceException('Migration directory is unavailable.');
        }

        $files = glob($this->directory . '/*.php');
        if ($files === false) {
            throw new PersistenceException('Unable to discover migration files.');
        }
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            if (preg_match('/^\d{14}_[a-z][a-z0-9_]{0,79}$/', $id) !== 1) {
                throw new PersistenceException('A migration filename is invalid.');
            }

            try {
                $migration = (static fn(string $__file): mixed => require $__file)($file);
            } catch (Throwable) {
                throw new PersistenceException('A migration file could not be loaded safely.');
            }

            if (!$migration instanceof Migration || $migration->id !== $id) {
                throw new PersistenceException('A migration definition is invalid or does not match its filename.');
            }
            if (isset($migrations[$migration->id])) {
                throw new PersistenceException('A duplicate migration identifier was found.');
            }
            $migrations[$migration->id] = $migration;
        }

        return $migrations;
    }
}
