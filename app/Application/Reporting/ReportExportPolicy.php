<?php
declare(strict_types=1);

namespace App\Application\Reporting;

use App\Application\Read\Page;
use InvalidArgumentException;

final class ReportExportPolicy
{
    public const MAX_ROWS = 10000;

    public static function assertFits(Page $page): void
    {
        if ($page->hasMore || count($page->items) > self::MAX_ROWS) {
            throw new InvalidArgumentException('La exportación supera 10,000 filas; reduce el periodo o aplica más filtros.');
        }
    }
}
