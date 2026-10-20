<?php
declare(strict_types=1);

namespace App\Domain\Modules;

use App\Domain\Branches\Branch;

final class ModuleAvailability
{
    public static function isAvailable(
        ?Module $module,
        ?Branch $branch = null,
        ?bool $branchModuleEnabled = null,
    ): bool {
        if ($module === null || !$module->globallyEnabled) {
            return false;
        }
        if ($branch !== null && !$branch->active) {
            return false;
        }
        if (!$module->requiresBranchEnablement) {
            return true;
        }

        return $branch !== null && $branchModuleEnabled === true;
    }
}
