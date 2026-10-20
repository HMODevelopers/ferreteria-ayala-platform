<?php
declare(strict_types=1);

namespace App\Core\Persistence;

interface TransactionManager
{
    public function transaction(callable $operation): mixed;
}
