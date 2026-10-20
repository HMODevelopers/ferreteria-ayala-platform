<?php
declare(strict_types=1);

namespace App\Application\Repositories;

use App\Application\Read\Page;
use App\Domain\Services\Service;

interface ServiceRepository
{
    public function findById(int $id): ?Service;
    public function findByCode(string $code): ?Service;
    public function activePage(int $limit = 25, int $offset = 0): Page;
    public function create(Service $service): Service;
    public function updateDetails(Service $service): void;
    public function setActive(int $id, bool $active): void;
}
