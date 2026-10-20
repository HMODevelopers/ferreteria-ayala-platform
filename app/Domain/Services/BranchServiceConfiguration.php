<?php
declare(strict_types=1);

namespace App\Domain\Services;

use InvalidArgumentException;

final class BranchServiceConfiguration
{
    private function __construct(
        public readonly int $branchId,
        public readonly int $serviceId,
        public readonly bool $enabled,
        public readonly int $displayOrder,
    ) {
    }

    public static function create(int $branchId, int $serviceId, int $displayOrder = 0): self
    {
        if ($branchId < 1) {
            throw new InvalidArgumentException('Branch identifier is invalid.');
        }
        if ($serviceId < 1) {
            throw new InvalidArgumentException('Service identifier is invalid.');
        }
        if ($displayOrder < 0 || $displayOrder > 4294967295) {
            throw new InvalidArgumentException('Service display order is invalid.');
        }

        return new self($branchId, $serviceId, false, $displayOrder);
    }

    public function withEnabled(bool $enabled): self
    {
        return new self($this->branchId, $this->serviceId, $enabled, $this->displayOrder);
    }

    public function withDisplayOrder(int $displayOrder): self
    {
        return self::create($this->branchId, $this->serviceId, $displayOrder)
            ->withEnabled($this->enabled);
    }

    public static function compareForDisplay(self $left, self $right): int
    {
        return [$left->displayOrder, $left->serviceId, $left->branchId]
            <=> [$right->displayOrder, $right->serviceId, $right->branchId];
    }
}
