<?php
declare(strict_types=1);

namespace App\Core\Persistence;

use Closure;
use Throwable;

final class MigrationCommand
{
    private readonly Closure $runnerFactory;

    public function __construct(callable $runnerFactory, private readonly string $environment)
    {
        $this->runnerFactory = Closure::fromCallable($runnerFactory);
    }

    public function run(array $arguments, string $sapi, callable $output, callable $errors): int
    {
        if ($sapi !== 'cli') {
            $errors("Migration commands are available only from PHP CLI.\n");
            return 2;
        }

        $command = $arguments[0] ?? null;
        $options = array_slice($arguments, 1);
        if (!in_array($command, ['status', 'migrate', 'rollback'], true)) {
            $errors("Usage: php bin/migrations.php status|migrate|rollback [--force]\n");
            return 2;
        }
        if (array_diff($options, ['--force']) !== [] || count($options) !== count(array_unique($options))) {
            $errors("Invalid command options.\n");
            return 2;
        }
        if ($command === 'status' && $options !== []) {
            $errors("The status command does not accept options.\n");
            return 2;
        }

        $safeEnvironment = in_array($this->environment, ['development', 'testing'], true);
        if ($command !== 'status' && !$safeEnvironment && !in_array('--force', $options, true)) {
            $errors("Schema changes outside development/testing require --force.\n");
            return 2;
        }

        try {
            $runner = ($this->runnerFactory)();
            if (!$runner instanceof MigrationRunner) {
                throw new PersistenceException('Migration runner configuration is invalid.');
            }
            return match ($command) {
                'status' => $this->showStatus($runner, $output),
                'migrate' => $this->showMigrationResult($runner->migrate(), $output),
                'rollback' => $this->showResult('Reverted', $runner->rollback(), $output),
            };
        } catch (PersistenceException $error) {
            $errors('ERROR: ' . $error->getMessage() . "\n");
            return 1;
        } catch (Throwable) {
            $errors("ERROR: Unexpected persistence failure. Review private server logs.\n");
            return 1;
        }
    }

    private function showStatus(MigrationRunner $runner, callable $output): int
    {
        $rows = $runner->status();
        if ($rows === []) {
            $output("No migration files found.\n");
            return 0;
        }
        foreach ($rows as $row) {
            $details = $row['batch'] === null ? '' : ' batch=' . $row['batch'] . ' applied_at=' . $row['applied_at'];
            $output(sprintf("%-8s %s %s%s\n", $row['state'], $row['id'], $row['name'], $details));
        }
        return 0;
    }

    private function showResult(string $verb, array $identifiers, callable $output): int
    {
        if ($identifiers === []) {
            $output("No migration changes were necessary.\n");
            return 0;
        }
        foreach ($identifiers as $identifier) {
            $output($verb . ': ' . $identifier . "\n");
        }
        return 0;
    }

    private function showMigrationResult(array $identifiers, callable $output): int
    {
        if ($identifiers === []) {
            $output("No migration files were applied. Migration storage is ready.\n");
            return 0;
        }

        return $this->showResult('Applied', $identifiers, $output);
    }
}
