<?php
declare(strict_types=1);

namespace App\Application\Read;

use InvalidArgumentException;

final class Page
{
    /** @param list<object> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $limit,
        public readonly int $offset,
        public readonly bool $hasMore,
    ) {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Page boundaries are invalid.');
        }
    }
}
