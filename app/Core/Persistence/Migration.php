<?php
declare(strict_types=1);

namespace App\Core\Persistence;

use Closure;
use PDO;

final class Migration
{
    private readonly Closure $up;
    private readonly ?Closure $down;

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        callable $up,
        ?callable $down,
    ) {
        if (preg_match('/^\d{14}_[a-z][a-z0-9_]{0,79}$/', $id) !== 1) {
            throw new PersistenceException('Migration identifier is invalid.');
        }
        if ($name === '' || strlen($name) > 190 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new PersistenceException('Migration name is invalid.');
        }

        $this->up = Closure::fromCallable($up);
        $this->down = $down === null ? null : Closure::fromCallable($down);
    }

    public function up(PDO $pdo): void
    {
        ($this->up)($pdo);
    }

    public function isReversible(): bool
    {
        return $this->down instanceof Closure;
    }

    public function down(PDO $pdo): void
    {
        if (!$this->down instanceof Closure) {
            throw new PersistenceException('Migration is not reversible.');
        }
        ($this->down)($pdo);
    }
}
