<?php
declare(strict_types=1);

use App\Application\Exceptions\EntityNotFound;
use App\Application\Exceptions\OperationConflict;
use App\Application\Read\Page;
use App\Application\Read\RegularHoursRecord;
use App\Application\Read\SpecialHoursRecord;
use App\Application\Repositories\BranchRepository;
use App\Application\Repositories\BranchScheduleRepository;
use App\Application\Repositories\BranchServiceRepository;
use App\Application\Repositories\ModuleRepository;
use App\Application\Repositories\ServiceRepository;
use App\Application\Services\BranchManagementService;
use App\Application\Services\BranchScheduleManagementService;
use App\Application\Services\BranchServiceManagementService;
use App\Application\Services\ModuleAvailabilityService;
use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;
use App\Core\Persistence\TransactionManager;
use App\Domain\Branches\Branch;
use App\Domain\Branches\BranchHoursInterval;
use App\Domain\Branches\BranchSpecialHours;
use App\Domain\Modules\Module;
use App\Domain\Services\BranchServiceConfiguration;
use App\Domain\Services\Service;
use App\Infrastructure\Persistence\PdoBranchRepository;
use App\Infrastructure\Persistence\PdoBranchScheduleRepository;
use App\Infrastructure\Persistence\PdoBranchServiceRepository;
use App\Infrastructure\Persistence\PdoModuleRepository;
use App\Infrastructure\Persistence\PdoServiceRepository;

final class M014Statement extends PDOStatement
{
    public array $bound = [];
    public ?array $executedWith = null;

    public function __construct(
        private readonly M014Pdo $pdo,
        private readonly string $sql,
        private readonly array $fixture,
    ) {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bound[(string) $param] = ['value' => $value, 'type' => $type];
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->executedWith = $params;
        $this->pdo->executions[] = ['sql' => $this->sql, 'params' => $params, 'bound' => $this->bound];
        if (($this->fixture['exception'] ?? null) instanceof Throwable) {
            throw $this->fixture['exception'];
        }
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->fixture['rows'][0] ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->fixture['rows'] ?? [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->fixture['rows'][0] ?? null;
        return is_array($row) ? (array_values($row)[$column] ?? false) : ($row ?? false);
    }

    public function rowCount(): int
    {
        return $this->fixture['rowCount'] ?? 1;
    }
}

final class M014Pdo extends PDO
{
    public array $fixtures = [];
    public array $prepared = [];
    public array $executions = [];
    public string $insertId = '1';
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->prepared[] = $query;
        return new M014Statement($this, $query, array_shift($this->fixtures) ?? []);
    }
    public function lastInsertId(?string $name = null): string|false { return $this->insertId; }
}

function m014Connection(M014Pdo $pdo, int &$connections): Connection
{
    return new Connection(
        DatabaseConfig::fromArray(persistenceConfig()),
        static function () use ($pdo, &$connections): PDO { $connections++; return $pdo; },
        static fn(): array => ['mysql'],
    );
}

$pdo = new M014Pdo();
$connections = 0;
$connection = m014Connection($pdo, $connections);
$branchRepository = new PdoBranchRepository($connection);
$moduleRepository = new PdoModuleRepository($connection);
$serviceRepository = new PdoServiceRepository($connection);
$branchServiceRepository = new PdoBranchServiceRepository($connection);
$scheduleRepository = new PdoBranchScheduleRepository($connection);
check($connections === 0, 'M01.4 repository construction keeps PDO lazy');

$pdo->fixtures[] = ['rows' => [[
    'id' => '7', 'code' => 'CENTRO', 'name' => 'Centro', 'address' => null, 'phone' => null,
    'email' => null, 'whatsapp' => null, 'latitude' => '29.123456', 'longitude' => '-110.123456',
    'is_active' => '1',
]]];
$storedBranch = $branchRepository->findById(7);
check($connections === 1 && $storedBranch?->id === 7 && $storedBranch->active, 'Branch repository lazily hydrates stored domain state');
check(str_contains($pdo->prepared[0], 'WHERE id = :id') && $pdo->executions[0]['params'] === ['id' => 7], 'Branch lookup uses a prepared identifier parameter');

$pdo->fixtures[] = ['rows' => [
    ['id' => '1', 'code' => 'A', 'name' => 'A', 'address' => null, 'phone' => null, 'email' => null, 'whatsapp' => null, 'latitude' => null, 'longitude' => null, 'is_active' => '1'],
    ['id' => '2', 'code' => 'B', 'name' => 'B', 'address' => null, 'phone' => null, 'email' => null, 'whatsapp' => null, 'latitude' => null, 'longitude' => null, 'is_active' => '1'],
    ['id' => '3', 'code' => 'C', 'name' => 'C', 'address' => null, 'phone' => null, 'email' => null, 'whatsapp' => null, 'latitude' => null, 'longitude' => null, 'is_active' => '1'],
]];
$branchPage = $branchRepository->activePage(2, 4);
check(count($branchPage->items) === 2 && $branchPage->hasMore && $branchPage->offset === 4, 'Branch pagination uses limit-plus-one without leaking the probe row');
$pageExecution = $pdo->executions[array_key_last($pdo->executions)];
check($pageExecution['bound']['limit'] === ['value' => 3, 'type' => PDO::PARAM_INT], 'Repository binds page limits as integers');
check(str_contains($pageExecution['sql'], 'ORDER BY name ASC, id ASC'), 'Branch page order has a stable identifier tiebreaker');
foreach ([[0, 0], [101, 0], [10, -1]] as [$limit, $offset]) {
    expectException(fn() => $branchRepository->activePage($limit, $offset), 'Unsafe repository page boundary rejected', InvalidArgumentException::class);
}

$pdo->insertId = '12';
$pdo->fixtures[] = [];
$createdBranch = $branchRepository->create(Branch::create('NORTE', 'Norte'));
check($createdBranch->id === 12 && !$createdBranch->active, 'Branch insert returns an inactive persisted entity');
$branchInsert = $pdo->executions[array_key_last($pdo->executions)];
check(str_contains($branchInsert['sql'], 'INSERT INTO branches') && str_contains($branchInsert['sql'], 'is_active'), 'Branch insert explicitly persists the inactive default');
check($branchInsert['params']['code'] === 'NORTE', 'Branch insert uses normalized domain data');

$duplicate = new PDOException('private duplicate details');
$duplicate->errorInfo = ['23000', 1062, 'private duplicate details'];
$pdo->fixtures[] = ['exception' => $duplicate];
try {
    $branchRepository->create(Branch::create('NORTE', 'Duplicate'));
    check(false, 'Duplicate branch expected');
} catch (OperationConflict $error) {
    check(!str_contains($error->getMessage(), 'private'), 'Repository duplicate errors are controlled and sanitized');
}

$pdo->fixtures[] = ['rows' => [[
    'id' => '2', 'code' => 'M01', 'name' => 'Sucursales', 'description' => null,
    'is_globally_enabled' => '1', 'requires_branch_enablement' => '1',
]]];
$storedModule = $moduleRepository->findByCode('m01');
check($storedModule?->id === 2 && $storedModule->globallyEnabled, 'Module repository normalizes code and hydrates activation flags');
check($pdo->executions[array_key_last($pdo->executions)]['params'] === ['value' => 'M01'], 'Module code lookup is parameterized');

$pdo->fixtures[] = ['rows' => [[
    'id' => '4', 'code' => 'CORTE', 'name' => 'Corte', 'description' => 'Descripción', 'is_active' => '1',
]]];
$storedService = $serviceRepository->findById(4);
check($storedService?->id === 4 && $storedService->active, 'Service repository hydrates persisted services');

$pdo->fixtures[] = ['rows' => [[
    'branch_id' => '7', 'service_id' => '4', 'is_enabled' => '1', 'display_order' => '3',
]]];
$storedConfiguration = $branchServiceRepository->find(7, 4);
check($storedConfiguration?->enabled && $storedConfiguration->displayOrder === 3, 'Branch-service repository hydrates relationship configuration');

$pdo->fixtures[] = ['rows' => [
    ['id' => '4', 'code' => 'CORTE', 'name' => 'Corte', 'description' => null, 'is_active' => '1'],
    ['id' => '9', 'code' => 'ENVIO', 'name' => 'Envío', 'description' => null, 'is_active' => '1'],
]];
$effective = $branchServiceRepository->effectiveServices(7);
$effectiveSql = $pdo->prepared[array_key_last($pdo->prepared)];
check(array_column($effective->items, 'id') === [4, 9], 'Effective service query preserves repository order');
foreach (['b.is_active = 1', 's.is_active = 1', 'bs.is_enabled = 1', 'ORDER BY bs.display_order ASC, s.id ASC'] as $fragment) {
    check(str_contains($effectiveSql, $fragment), 'Effective service SQL contains rule: ' . $fragment);
}
check(!str_contains($effectiveSql, 'modules'), 'Commercial service queries remain independent from technical modules');

$pdo->fixtures[] = ['rows' => [[
    'id' => '21', 'branch_id' => '7', 'iso_weekday' => '1', 'opens_at' => '08:00', 'closes_at' => '18:00',
    'crosses_midnight' => '0', 'interval_order' => '1',
]]];
$regularRecords = $scheduleRepository->regularHours(7);
check($regularRecords[0]->id === 21 && $regularRecords[0]->interval->opensAt === '08:00', 'Schedule repository converts SQL regular hours to domain intervals');
check(str_contains($pdo->prepared[array_key_last($pdo->prepared)], 'ORDER BY iso_weekday ASC, interval_order ASC, id ASC'), 'Regular-hours reads use deterministic order');

$pdo->fixtures[] = ['rows' => [[
    'id' => '21', 'branch_id' => '7', 'iso_weekday' => '1', 'opens_at' => '08:00', 'closes_at' => '18:00',
    'crosses_midnight' => '0', 'interval_order' => '1',
]]];
check(count($scheduleRepository->regularHoursForDay(7, 1)) === 1, 'Schedule repository supports a parameterized daily interval read');
expectException(fn() => $scheduleRepository->regularHoursForDay(7, 8), 'Daily interval reads reject invalid ISO weekdays', InvalidArgumentException::class);

$pdo->fixtures[] = ['rows' => [[
    'id' => '31', 'branch_id' => '7', 'local_date' => '2026-12-24', 'is_closed' => '1',
    'opens_at' => null, 'closes_at' => null, 'crosses_midnight' => '0', 'interval_order' => '0', 'note' => 'Cierre',
]]];
$specialRecords = $scheduleRepository->specialHoursBetween(7, '2026-12-01', '2026-12-31');
check($specialRecords[0]->interval->closed && $specialRecords[0]->interval->note === 'Cierre', 'Schedule repository converts closed exceptions to domain objects');
$specialRead = $pdo->executions[array_key_last($pdo->executions)];
check($specialRead['params']['from_date'] === '2026-12-01' && str_contains($specialRead['sql'], 'BETWEEN'), 'Special-hours reads use a bounded parameterized range');
expectException(
    fn() => $scheduleRepository->specialHoursBetween(7, '2026-01-01', '2028-01-02'),
    'Unbounded special-hours ranges are rejected',
    InvalidArgumentException::class,
);

$pdo->fixtures[] = ['rows' => []];
check($scheduleRepository->specialHoursForValidation(7) === [], 'Schedule write validation supports an empty exception set');
$validationRead = $pdo->executions[array_key_last($pdo->executions)];
check(
    $validationRead['bound']['limit'] === ['value' => 10001, 'type' => PDO::PARAM_INT],
    'Schedule write validation uses an explicit safety cap',
);

$pdo->insertId = '44';
$pdo->fixtures[] = [];
check($scheduleRepository->insertRegular(7, BranchHoursInterval::create(5, '20:00', '02:00', true)) === 44, 'Regular-hours inserts return a confirmed identifier');
$hoursInsert = $pdo->executions[array_key_last($pdo->executions)];
check($hoursInsert['params']['crosses'] === 1 && $hoursInsert['params']['branch_id'] === 7, 'Regular-hours insert binds explicit overnight and branch values');

final class M014Transactions implements TransactionManager
{
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public array $events = [];
    public function transaction(callable $operation): mixed
    {
        $this->begins++;
        $this->events[] = 'begin';
        try {
            $result = $operation(null);
            $this->commits++;
            $this->events[] = 'commit';
            return $result;
        } catch (Throwable $error) {
            $this->rollbacks++;
            $this->events[] = 'rollback';
            throw $error;
        }
    }
}

final class M014Branches implements BranchRepository
{
    public array $items = [];
    public array $events = [];
    public function findById(int $id): ?Branch { return $this->items[$id] ?? null; }
    public function findByCode(string $code): ?Branch { foreach ($this->items as $item) if ($item->code === $code) return $item; return null; }
    public function activePage(int $limit = 25, int $offset = 0): Page { return new Page([], $limit, $offset, false); }
    public function create(Branch $branch): Branch { $stored = Branch::rehydrate(count($this->items) + 1, $branch->code, $branch->name, $branch->address, $branch->phone, $branch->email, $branch->whatsapp, $branch->latitude, $branch->longitude, false); $this->items[$stored->id] = $stored; return $stored; }
    public function updateDetails(Branch $branch): void { $this->items[$branch->id] = $branch; }
    public function setActive(int $id, bool $active): void { $this->items[$id] = $this->items[$id]->withActive($active); }
    public function lockById(int $id): ?Branch { $this->events[] = 'lock:' . $id; return $this->items[$id] ?? null; }
}

final class M014Modules implements ModuleRepository
{
    public array $items = [];
    public array $relations = [];
    public function findById(int $id): ?Module { return $this->items[$id] ?? null; }
    public function findByCode(string $code): ?Module { return null; }
    public function page(int $limit = 25, int $offset = 0): Page { return new Page([], $limit, $offset, false); }
    public function create(Module $module): Module { $id = count($this->items) + 1; return $this->items[$id] = Module::rehydrate($id, $module->code, $module->name, $module->description, false, $module->requiresBranchEnablement); }
    public function updateDetails(Module $module): void { $this->items[$module->id] = $module; }
    public function setGloballyEnabled(int $id, bool $enabled): void { $this->items[$id] = $this->items[$id]->withGlobalEnabled($enabled); }
    public function branchEnablement(int $branchId, int $moduleId): ?bool { return $this->relations[$branchId . ':' . $moduleId] ?? null; }
    public function associateWithBranch(int $branchId, int $moduleId): void { $this->relations[$branchId . ':' . $moduleId] = false; }
    public function setBranchEnabled(int $branchId, int $moduleId, bool $enabled): void { $this->relations[$branchId . ':' . $moduleId] = $enabled; }
}

final class M014Services implements ServiceRepository
{
    public array $items = [];
    public function findById(int $id): ?Service { return $this->items[$id] ?? null; }
    public function findByCode(string $code): ?Service { return null; }
    public function activePage(int $limit = 25, int $offset = 0): Page { return new Page([], $limit, $offset, false); }
    public function create(Service $service): Service { $id = count($this->items) + 1; return $this->items[$id] = Service::rehydrate($id, $service->code, $service->name, $service->description, false); }
    public function updateDetails(Service $service): void { $this->items[$service->id] = $service; }
    public function setActive(int $id, bool $active): void { $this->items[$id] = $this->items[$id]->withActive($active); }
}

final class M014BranchServices implements BranchServiceRepository
{
    public array $items = [];
    public function find(int $branchId, int $serviceId): ?BranchServiceConfiguration { return $this->items[$branchId . ':' . $serviceId] ?? null; }
    public function forBranch(int $branchId, bool $enabledOnly = false, int $limit = 25, int $offset = 0): Page { $items = array_values(array_filter($this->items, fn($item) => $item->branchId === $branchId && (!$enabledOnly || $item->enabled))); return new Page(array_slice($items, $offset, $limit), $limit, $offset, count($items) > $offset + $limit); }
    public function associate(BranchServiceConfiguration $configuration): void { $this->items[$configuration->branchId . ':' . $configuration->serviceId] = $configuration; }
    public function setEnabled(int $branchId, int $serviceId, bool $enabled): void { $key = $branchId . ':' . $serviceId; $this->items[$key] = $this->items[$key]->withEnabled($enabled); }
    public function setDisplayOrder(int $branchId, int $serviceId, int $displayOrder): void { $key = $branchId . ':' . $serviceId; $this->items[$key] = $this->items[$key]->withDisplayOrder($displayOrder); }
    public function effectiveServices(int $branchId, int $limit = 25, int $offset = 0): Page { return new Page([], $limit, $offset, false); }
}

final class M014Schedules implements BranchScheduleRepository
{
    public array $regular = [];
    public array $special = [];
    public array $events = [];
    public int $writes = 0;
    public function regularHours(int $branchId): array { $this->events[] = 'read-regular'; return array_values($this->regular); }
    public function regularHoursForDay(int $branchId, int $isoWeekday): array { return array_values(array_filter($this->regular, fn($r) => $r->interval->isoWeekday === $isoWeekday)); }
    public function specialHoursBetween(int $branchId, string $fromDate, string $toDate): array { return array_values(array_filter($this->special, fn($r) => $r->interval->localDate >= $fromDate && $r->interval->localDate <= $toDate)); }
    public function specialHoursForValidation(int $branchId): array { $this->events[] = 'read-special'; return array_values($this->special); }
    public function insertRegular(int $branchId, BranchHoursInterval $interval): int { $id = count($this->regular) + 1; $this->regular[$id] = new RegularHoursRecord($id, $branchId, $interval); $this->events[] = 'write-regular'; $this->writes++; return $id; }
    public function updateRegular(int $id, int $branchId, BranchHoursInterval $interval): bool { if (!isset($this->regular[$id])) return false; $this->regular[$id] = new RegularHoursRecord($id, $branchId, $interval); $this->writes++; return true; }
    public function deleteRegular(int $id, int $branchId): bool { if (!isset($this->regular[$id])) return false; unset($this->regular[$id]); $this->writes++; return true; }
    public function insertSpecial(int $branchId, BranchSpecialHours $interval): int { $id = count($this->special) + 1; $this->special[$id] = new SpecialHoursRecord($id, $branchId, $interval); $this->writes++; return $id; }
    public function updateSpecial(int $id, int $branchId, BranchSpecialHours $interval): bool { if (!isset($this->special[$id])) return false; $this->special[$id] = new SpecialHoursRecord($id, $branchId, $interval); $this->writes++; return true; }
    public function deleteSpecial(int $id, int $branchId): bool { if (!isset($this->special[$id])) return false; unset($this->special[$id]); $this->writes++; return true; }
}

$memoryBranches = new M014Branches();
$memoryBranches->items[1] = Branch::rehydrate(1, 'CENTRO', 'Centro', null, null, null, null, null, null, true);
$transactions = new M014Transactions();
$branchManagement = new BranchManagementService($memoryBranches, $transactions);
$changedBranch = $branchManagement->update(1, Branch::create('CENTRO', 'Centro actualizado'));
check($changedBranch->id === 1 && $changedBranch->name === 'Centro actualizado', 'Branch application service updates mutable fields while retaining identity');
expectException(
    fn() => $branchManagement->update(1, Branch::create('OTRA', 'Otra')),
    'Branch application service rejects immutable code changes',
    OperationConflict::class,
);
check($transactions->rollbacks === 1, 'Application transaction rolls back a rejected branch update');
expectException(fn() => $branchManagement->setActive(999, true), 'Missing branch is distinguished from invalid state', EntityNotFound::class);

$memoryModules = new M014Modules();
$memoryModules->items[1] = Module::rehydrate(1, 'M01', 'Sucursales', null, true, true);
$memoryModules->items[2] = Module::rehydrate(2, 'M00', 'Persistencia', null, true, false);
$moduleApplication = new ModuleAvailabilityService($memoryModules, $memoryBranches, $transactions);
check(!$moduleApplication->isAvailable(1, 1), 'Missing required branch-module association is unavailable');
$moduleApplication->associate(1, 1);
check(!$moduleApplication->isAvailable(1, 1), 'New branch-module associations remain disabled');
$moduleApplication->setBranchEnabled(1, 1, true);
check($moduleApplication->isAvailable(1, 1), 'Enabled branch-scoped module is available for an active branch');
$moduleApplication->setGloballyEnabled(1, false);
check(!$moduleApplication->isAvailable(1, 1), 'Global module deactivation prevails over branch enablement');
check($moduleApplication->isAvailable(2), 'Strictly global enabled module needs no invented branch relation');

$memoryServices = new M014Services();
$memoryServices->items[1] = Service::rehydrate(1, 'CORTE', 'Corte', null, true);
$memoryBranchServices = new M014BranchServices();
$serviceApplication = new BranchServiceManagementService($memoryServices, $memoryBranchServices, $memoryBranches, $transactions);
check(!$serviceApplication->isAvailable(1, 1), 'Commercial service without branch association is unavailable');
$serviceApplication->associate(1, 1, 3);
check(!$serviceApplication->isAvailable(1, 1), 'New commercial service association is disabled');
$serviceApplication->configure(1, 1, true, 5);
check($serviceApplication->isAvailable(1, 1), 'Commercial service availability combines active service, branch, and association');
check($memoryBranchServices->find(1, 1)?->displayOrder === 5, 'Commercial service configuration updates deterministic display order');
$serviceApplication->setServiceActive(1, false);
check(!$serviceApplication->isAvailable(1, 1), 'Inactive commercial service remains unavailable without deleting its association');
check($memoryBranchServices->find(1, 1) !== null, 'Commercial service deactivation preserves relationship data');

$memorySchedules = new M014Schedules();
$scheduleApplication = new BranchScheduleManagementService($memorySchedules, $memoryBranches, $transactions);
$firstIntervalId = $scheduleApplication->addRegular(1, BranchHoursInterval::create(1, '08:00', '13:00'));
check($firstIntervalId === 1 && $memorySchedules->writes === 1, 'Schedule coordinator persists a validated regular interval');
check($memoryBranches->events[array_key_last($memoryBranches->events)] === 'lock:1', 'Every schedule mutation locks the owning branch row first');
check($memorySchedules->events === ['read-regular', 'read-special', 'write-regular'], 'Schedule mutation reloads protected state before writing');
$writesBeforeConflict = $memorySchedules->writes;
expectException(
    fn() => $scheduleApplication->addRegular(1, BranchHoursInterval::create(1, '12:00', '16:00', sortOrder: 2)),
    'Schedule coordinator rejects an overlapping interval under the branch lock',
    InvalidArgumentException::class,
);
check($memorySchedules->writes === $writesBeforeConflict, 'Invalid concurrent-style schedule candidate performs no DML');
check($transactions->rollbacks >= 3, 'Schedule validation failure triggers transaction rollback');

$overnightId = $scheduleApplication->addRegular(1, BranchHoursInterval::create(5, '20:00', '02:00', true));
check($overnightId === 2, 'Schedule coordinator accepts a non-overlapping overnight interval');
$closureId = $scheduleApplication->addSpecial(1, BranchSpecialHours::closed('2026-12-24', 'Cierre confirmado'));
check($closureId === 1, 'Schedule coordinator persists a canonical closed exception');
expectException(
    fn() => $scheduleApplication->addSpecial(1, BranchSpecialHours::open('2026-12-24', '10:00', '12:00')),
    'Schedule coordinator rejects mixing a closure with open exceptions',
    InvalidArgumentException::class,
);
$status = $scheduleApplication->statusAt(1, new DateTimeImmutable('2026-10-19 09:00', new DateTimeZone('America/Hermosillo')));
check($status->scheduledOpen && $status->operationallyEnabled && $status->publiclyAvailable(), 'Schedule read contract keeps scheduled, operational, and public availability distinct');
$memoryBranches->items[1] = $memoryBranches->items[1]->withActive(false);
$inactiveStatus = $scheduleApplication->statusAt(1, new DateTimeImmutable('2026-10-19 09:00', new DateTimeZone('America/Hermosillo')));
check($inactiveStatus->scheduledOpen && !$inactiveStatus->publiclyAvailable(), 'Operational deactivation does not erase scheduled-open state');

$sourceFiles = [
    dirname(__DIR__) . '/app/Infrastructure/Persistence/PdoBranchRepository.php',
    dirname(__DIR__) . '/app/Infrastructure/Persistence/PdoModuleRepository.php',
    dirname(__DIR__) . '/app/Infrastructure/Persistence/PdoServiceRepository.php',
    dirname(__DIR__) . '/app/Infrastructure/Persistence/PdoBranchServiceRepository.php',
    dirname(__DIR__) . '/app/Infrastructure/Persistence/PdoBranchScheduleRepository.php',
];
foreach ($sourceFiles as $sourceFile) {
    $source = (string) file_get_contents($sourceFile);
    check(!str_contains($source, 'query('), 'M01.4 repository avoids direct query execution: ' . basename($sourceFile));
    check(str_contains($source, 'prepare('), 'M01.4 repository uses prepared statements: ' . basename($sourceFile));
}

$routes = (string) file_get_contents(dirname(__DIR__) . '/config/routes.php');
check(substr_count($routes, '$router->') === 1 && str_contains($routes, "get('/'"), 'M01.4 adds no public or administrative write routes');
