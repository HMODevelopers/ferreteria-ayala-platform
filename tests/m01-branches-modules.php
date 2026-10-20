<?php
declare(strict_types=1);

use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\PersistenceException;
use App\Domain\Branches\Branch;
use App\Domain\Modules\Module;
use App\Domain\Modules\ModuleAvailability;

final class M01SchemaStatement extends PDOStatement
{
    public function __construct(private readonly int $count)
    {
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->count;
    }
}

final class M01SchemaPdo extends PDO
{
    /** @var list<string> */
    public array $executed = [];

    /** @param array<string, int> $tableCounts */
    public function __construct(public array $tableCounts = [])
    {
    }

    public function exec(string $statement): int|false
    {
        $this->executed[] = $statement;
        return 0;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (preg_match('/^SELECT COUNT\(\*\) FROM ([a-z_]+)$/D', $query, $matches) !== 1) {
            throw new RuntimeException('Unexpected isolated schema query.');
        }

        return new M01SchemaStatement($this->tableCounts[$matches[1]] ?? 0);
    }
}

$branch = Branch::create(
    ' centro-01 ',
    ' Centro ',
    address: 'Calle pendiente de confirmar',
    email: 'contacto@example.test',
    latitude: ' 30.123456 ',
    longitude: ' -110.123456 ',
);
check($branch->code === 'CENTRO-01', 'Branch codes are trimmed and normalized to uppercase');
check($branch->name === 'Centro', 'Branch names are trimmed');
check(!$branch->active, 'New branches are inactive by default');
check($branch->latitude === '30.123456' && $branch->longitude === '-110.123456', 'Valid decimal coordinates are preserved');
check($branch->withActive(true)->active && !$branch->active, 'Branch activation is an explicit immutable state change');
check(!$branch->withActive(true)->withActive(false)->active, 'Branch deactivation is logical and reversible');

foreach ([
    static fn() => Branch::create('', 'Valid'),
    static fn() => Branch::create('INVALID CODE', 'Valid'),
    static fn() => Branch::create('VALID', '   '),
    static fn() => Branch::create('VALID', 'Name', email: 'not-an-email'),
    static fn() => Branch::create('VALID', 'Name', latitude: '20.0'),
    static fn() => Branch::create('VALID', 'Name', longitude: '-110.0'),
    static fn() => Branch::create('VALID', 'Name', latitude: '90.000001', longitude: '0'),
    static fn() => Branch::create('VALID', 'Name', latitude: '0', longitude: '-180.000001'),
    static fn() => Branch::create('VALID', 'Name', latitude: '1.1234567', longitude: '2'),
    static fn() => Branch::create('VALID', "Invalid\nName"),
] as $invalidBranch) {
    expectException($invalidBranch, 'Invalid branch data is rejected', InvalidArgumentException::class);
}

$branchAtMinimum = Branch::create('MIN', 'Minimum', latitude: '-90', longitude: '-180');
$branchAtMaximum = Branch::create('MAX', 'Maximum', latitude: '90', longitude: '180');
check($branchAtMinimum->latitude === '-90' && $branchAtMaximum->longitude === '180', 'Coordinate boundary values are accepted');
check(Branch::create('OPTIONAL', 'Optional')->address === null, 'Unconfirmed branch fields remain null');

$branchModule = Module::create('m01', 'Sucursales y módulos', requiresBranchEnablement: true);
$globalModule = Module::create('m00', 'Persistencia', 'Infraestructura técnica', requiresBranchEnablement: false);
check($branchModule->code === 'M01', 'Module codes are normalized and compatible with the documented catalog');
check(!$branchModule->globallyEnabled, 'New modules are globally disabled by default');
check($branchModule->requiresBranchEnablement, 'Branch-scoped modules retain their activation requirement');
check($globalModule->description === 'Infraestructura técnica', 'Optional module descriptions are retained');

foreach ([
    static fn() => Module::create('', 'Valid'),
    static fn() => Module::create('M1', 'Valid'),
    static fn() => Module::create('M001', 'Valid'),
    static fn() => Module::create('M01', ''),
    static fn() => Module::create('M01', "Invalid\nName"),
] as $invalidModule) {
    expectException($invalidModule, 'Invalid module data is rejected', InvalidArgumentException::class);
}

$activeBranch = $branch->withActive(true);
$enabledBranchModule = $branchModule->withGlobalEnabled(true);
$enabledGlobalModule = $globalModule->withGlobalEnabled(true);
check(!ModuleAvailability::isAvailable(null, $activeBranch, true), 'A missing module is unavailable');
check(!ModuleAvailability::isAvailable($branchModule, $activeBranch, true), 'A globally disabled module is unavailable');
check(!ModuleAvailability::isAvailable($enabledBranchModule, $branch, true), 'An inactive branch cannot use a module');
check(!ModuleAvailability::isAvailable($enabledBranchModule, $activeBranch), 'A missing branch-module relation is disabled');
check(!ModuleAvailability::isAvailable($enabledBranchModule, $activeBranch, false), 'A disabled branch-module relation is unavailable');
check(ModuleAvailability::isAvailable($enabledBranchModule, $activeBranch, true), 'An enabled branch-scoped module is available to an active branch');
check(ModuleAvailability::isAvailable($enabledGlobalModule), 'An enabled technical global module does not require a branch');
check(ModuleAvailability::isAvailable($enabledGlobalModule, $activeBranch), 'An enabled global module is available in active branch context');
check(!ModuleAvailability::isAvailable($enabledGlobalModule, $branch), 'Inactive branch context blocks even a global module');

$migrationDirectory = dirname(__DIR__) . '/database/migrations';
$allMigrations = (new MigrationDiscovery($migrationDirectory))->all();
$expectedMigrationIds = [
    '20261019010000_create_branches',
    '20261019010100_create_modules',
    '20261019010200_create_branch_modules',
];
$migrations = array_intersect_key($allMigrations, array_flip($expectedMigrationIds));
check(array_keys($migrations) === $expectedMigrationIds, 'M01.1 migrations are discovered in dependency order without M00 conflicts');
check(count(array_unique(array_keys($migrations))) === 3, 'M01.1 migration identifiers are unique');

$schemaPdo = new M01SchemaPdo();
foreach ($migrations as $migration) {
    $migration->up($schemaPdo);
}
check(count($schemaPdo->executed) === 3, 'Each isolated migration defines one table');

[$branchesSql, $modulesSql, $branchModulesSql] = $schemaPdo->executed;
foreach (['CREATE TABLE branches', 'BIGINT UNSIGNED', 'DEFAULT 0', 'DECIMAL(9,6)', 'DECIMAL(10,6)', 'branches_code_unique', 'branches_coordinates_pair_check', 'branches_active_name_idx', 'ENGINE=InnoDB', 'utf8mb4'] as $fragment) {
    check(str_contains($branchesSql, $fragment), 'Branches schema contains required definition: ' . $fragment);
}
foreach (['CREATE TABLE modules', 'modules_code_unique', 'is_globally_enabled', 'requires_branch_enablement', 'DEFAULT 0', 'DEFAULT 1', 'modules_availability_idx', 'ENGINE=InnoDB', 'utf8mb4'] as $fragment) {
    check(str_contains($modulesSql, $fragment), 'Modules schema contains required definition: ' . $fragment);
}
foreach (['CREATE TABLE branch_modules', 'PRIMARY KEY (branch_id, module_id)', 'branch_modules_branch_fk', 'branch_modules_module_fk', 'ON DELETE RESTRICT', 'branch_modules_module_enabled_branch_idx', 'DEFAULT 0', 'ENGINE=InnoDB', 'utf8mb4'] as $fragment) {
    check(str_contains($branchModulesSql, $fragment), 'Branch modules schema contains required definition: ' . $fragment);
}
check(
    strpos($branchModulesSql, 'REFERENCES branches') < strpos($branchModulesSql, 'REFERENCES modules'),
    'Branch-module foreign key definitions are deterministic',
);

foreach ($migrations as $migration) {
    check($migration->isReversible(), 'M01.1 migration declares a guarded rollback');
    $table = match ($migration->id) {
        '20261019010000_create_branches' => 'branches',
        '20261019010100_create_modules' => 'modules',
        default => 'branch_modules',
    };
    $nonEmptyPdo = new M01SchemaPdo([$table => 1]);
    expectException(
        static fn() => $migration->down($nonEmptyPdo),
        'Rollback rejects data-bearing table: ' . $table,
        PersistenceException::class,
    );
    check($nonEmptyPdo->executed === [], 'Rejected rollback does not drop data-bearing table: ' . $table);

    $emptyPdo = new M01SchemaPdo([$table => 0]);
    $migration->down($emptyPdo);
    check($emptyPdo->executed === ['DROP TABLE ' . $table], 'Empty table rollback is explicit: ' . $table);
}
