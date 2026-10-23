<?php
declare(strict_types=1);

use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\PersistenceException;
use App\Domain\Branches\Branch;
use App\Domain\Services\BranchServiceConfiguration;
use App\Domain\Services\Service;
use App\Domain\Services\ServiceAvailability;

$service = Service::create(' corte-especial ', ' Servicio técnico ', ' Descripción técnica ');
check($service->code === 'CORTE-ESPECIAL', 'Service codes are trimmed and normalized to uppercase');
check($service->name === 'Servicio técnico', 'Service names are required and trimmed');
check($service->description === 'Descripción técnica', 'Optional service descriptions are retained and trimmed');
check(!$service->active, 'New services are inactive by default');
check($service->withActive(true)->active && !$service->active, 'Service activation is an immutable logical state change');
check(!$service->withActive(true)->withActive(false)->active, 'Service deactivation preserves the original service data');
check(Service::create('OPTIONAL', 'Optional')->description === null, 'A missing service description remains null');
check(Service::create('BLANK', 'Blank', '   ')->description === null, 'A blank service description is normalized to null');
check(Service::create(str_repeat('A', 50), str_repeat('N', 150))->code === str_repeat('A', 50), 'Service code and name maximum lengths are accepted');
check(strlen(Service::create('UTF8', str_repeat('Á', 150))->name) > 150, 'Service name limits count characters rather than bytes');
check(Service::create('DESCRIPTION', 'Description', str_repeat('D', 5000))->description !== null, 'Service description maximum length is accepted');

foreach ([
    static fn() => Service::create('', 'Valid'),
    static fn() => Service::create('INVALID CODE', 'Valid'),
    static fn() => Service::create('-INVALID', 'Valid'),
    static fn() => Service::create('INVALID-', 'Valid'),
    static fn() => Service::create(str_repeat('A', 51), 'Valid'),
    static fn() => Service::create('VALID', ''),
    static fn() => Service::create('VALID', str_repeat('N', 151)),
    static fn() => Service::create('VALID', "Invalid\nName"),
    static fn() => Service::create('VALID', 'Valid', str_repeat('D', 5001)),
    static fn() => Service::create('VALID', 'Valid', "Invalid\0description"),
] as $invalidService) {
    expectException($invalidService, 'Invalid service data is rejected', InvalidArgumentException::class);
}

$configuration = BranchServiceConfiguration::create(10, 20, 7);
check($configuration->branchId === 10 && $configuration->serviceId === 20, 'Branch-service configuration retains valid identifiers');
check(!$configuration->enabled, 'New branch-service associations are disabled by default');
check($configuration->displayOrder === 7, 'Branch-service presentation order is retained');
check($configuration->withEnabled(true)->enabled && !$configuration->enabled, 'Association enablement is an immutable logical state change');
check(!$configuration->withEnabled(true)->withEnabled(false)->enabled, 'Association deactivation preserves its relation');
check(BranchServiceConfiguration::create(1, 1)->displayOrder === 0, 'Association display order has a safe default');
check(
    $configuration->withEnabled(true)->withDisplayOrder(9)->enabled,
    'Changing presentation order preserves association enablement',
);

foreach ([
    static fn() => BranchServiceConfiguration::create(0, 1),
    static fn() => BranchServiceConfiguration::create(1, 0),
    static fn() => BranchServiceConfiguration::create(-1, 1),
    static fn() => BranchServiceConfiguration::create(1, -1),
    static fn() => BranchServiceConfiguration::create(1, 1, -1),
    static fn() => BranchServiceConfiguration::create(1, 1, 4294967296),
] as $invalidConfiguration) {
    expectException(
        $invalidConfiguration,
        'Invalid branch-service configuration data is rejected',
        InvalidArgumentException::class,
    );
}

$unordered = [
    BranchServiceConfiguration::create(1, 30, 2),
    BranchServiceConfiguration::create(1, 20, 1),
    BranchServiceConfiguration::create(1, 10, 1),
];
usort($unordered, [BranchServiceConfiguration::class, 'compareForDisplay']);
check(array_column($unordered, 'serviceId') === [10, 20, 30], 'Equal display orders use service identifiers as a deterministic tiebreaker');

$inactiveBranch = Branch::create('SERVICES', 'Service test branch');
$activeBranch = $inactiveBranch->withActive(true);
$inactiveService = Service::create('TEST-SERVICE', 'Test service');
$activeService = $inactiveService->withActive(true);
$disabledAssociation = BranchServiceConfiguration::create(1, 1);
$enabledAssociation = $disabledAssociation->withEnabled(true);

check(!ServiceAvailability::isAvailable(null, $activeBranch, $enabledAssociation), 'A missing service is unavailable');
check(!ServiceAvailability::isAvailable($activeService, null, $enabledAssociation), 'A missing branch makes a service unavailable');
check(!ServiceAvailability::isAvailable($activeService, $activeBranch, null), 'A missing branch-service association is unavailable');
check(!ServiceAvailability::isAvailable($inactiveService, $activeBranch, $enabledAssociation), 'An inactive service is unavailable');
check(!ServiceAvailability::isAvailable($activeService, $inactiveBranch, $enabledAssociation), 'An inactive branch cannot publish a service');
check(!ServiceAvailability::isAvailable($activeService, $activeBranch, $disabledAssociation), 'A disabled branch-service association is unavailable');
check(ServiceAvailability::isAvailable($activeService, $activeBranch, $enabledAssociation), 'Active service and branch with an enabled association are available');

$allMigrations = (new MigrationDiscovery(dirname(__DIR__) . '/database/migrations'))->all();
$expectedServiceMigrations = [
    '20261019010500_create_services',
    '20261019010600_create_branch_services',
];
$serviceMigrations = array_intersect_key($allMigrations, array_flip($expectedServiceMigrations));
check(array_keys($serviceMigrations) === $expectedServiceMigrations, 'M01.3 migrations are discovered in dependency order');
check(count(array_unique(array_keys($serviceMigrations))) === 2, 'M01.3 migration identifiers are unique');

$schemaPdo = new M01SchemaPdo();
foreach ($serviceMigrations as $migration) {
    $migration->up($schemaPdo);
}
check(count($schemaPdo->executed) === 2, 'Each M01.3 isolated migration defines one table');
[$servicesSql, $branchServicesSql] = $schemaPdo->executed;
foreach ([
    'CREATE TABLE services',
    'BIGINT UNSIGNED',
    'code VARCHAR(50) CHARACTER SET ascii COLLATE ascii_general_ci',
    'description TEXT NULL',
    'DEFAULT 0',
    'services_code_unique',
    'CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY)',
    'services_code_format_check',
    'services_name_not_blank_check',
    'services_description_not_blank_check',
    'services_active_boolean_check',
    'services_active_name_idx',
    'ENGINE=InnoDB',
    'utf8mb4',
] as $fragment) {
    check(str_contains($servicesSql, $fragment), 'Services schema contains required definition: ' . $fragment);
}
foreach ([
    'CREATE TABLE branch_services',
    'PRIMARY KEY (branch_id, service_id)',
    'is_enabled TINYINT(1) NOT NULL DEFAULT 0',
    'display_order INT UNSIGNED NOT NULL DEFAULT 0',
    'branch_services_enabled_boolean_check',
    'branch_services_branch_fk',
    'REFERENCES branches (id)',
    'branch_services_service_fk',
    'REFERENCES services (id)',
    'ON DELETE RESTRICT',
    'branch_services_display_idx',
    'branch_services_service_enabled_branch_idx',
    'ENGINE=InnoDB',
    'utf8mb4',
] as $fragment) {
    check(str_contains($branchServicesSql, $fragment), 'Branch services schema contains required definition: ' . $fragment);
}
check(
    !str_contains($branchServicesSql, 'UNIQUE (branch_id, display_order)'),
    'Branch-service display order is not unnecessarily unique',
);
check(
    strpos($branchServicesSql, 'REFERENCES branches') < strpos($branchServicesSql, 'REFERENCES services'),
    'Branch-service foreign key definitions are deterministic',
);

foreach ($serviceMigrations as $migration) {
    $table = $migration->id === '20261019010500_create_services' ? 'services' : 'branch_services';
    check($migration->isReversible(), 'M01.3 migration declares a guarded rollback');
    $nonEmptyPdo = new M01SchemaPdo([$table => 1]);
    expectException(
        static fn() => $migration->assertCanRollback($nonEmptyPdo),
        'M01.3 rollback preflight rejects data-bearing table: ' . $table,
        PersistenceException::class,
    );
    expectException(
        static fn() => $migration->down($nonEmptyPdo),
        'M01.3 rollback rejects data-bearing table: ' . $table,
        PersistenceException::class,
    );
    check($nonEmptyPdo->executed === [], 'Rejected M01.3 rollback preserves table: ' . $table);
    $emptyPdo = new M01SchemaPdo([$table => 0]);
    $migration->assertCanRollback($emptyPdo);
    $migration->down($emptyPdo);
    check($emptyPdo->executed === ['DROP TABLE ' . $table], 'Empty M01.3 table rollback is explicit: ' . $table);
}
