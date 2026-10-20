<?php
declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Branches\Branch;

final class ServiceAvailability
{
    public static function isAvailable(
        ?Service $service,
        ?Branch $branch,
        ?BranchServiceConfiguration $configuration,
    ): bool {
        return $service !== null
            && $service->active
            && $branch !== null
            && $branch->active
            && $configuration !== null
            && $configuration->enabled;
    }
}
