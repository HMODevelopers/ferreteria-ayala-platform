<?php
declare(strict_types=1);

use App\Core\Persistence\IntegrationTestSafety;
use App\Core\Persistence\PersistenceException;
use Tests\Support\M01RecoverySafety;

require_once __DIR__ . '/support/M01RecoverySafety.php';

function m01RecoveryFixtureRows(): array
{
    return [
        'branches' => [
            ['id' => 11, 'code' => 'IT-AAAABBBB', 'name' => 'Default-state branch', 'address' => null, 'phone' => null, 'email' => null, 'whatsapp' => null, 'latitude' => null, 'longitude' => null, 'is_active' => 0],
            ['id' => 12, 'code' => 'IT-CCCCDDDD', 'name' => 'Integration branch updated', 'address' => null, 'phone' => null, 'email' => null, 'whatsapp' => null, 'latitude' => null, 'longitude' => null, 'is_active' => 1],
            ['id' => 13, 'code' => 'IT-EEEEFFFF', 'name' => 'Integration branch two', 'address' => null, 'phone' => null, 'email' => null, 'whatsapp' => null, 'latitude' => null, 'longitude' => null, 'is_active' => 0],
        ],
        'modules' => [
            ['id' => 21, 'code' => 'M92', 'name' => 'Default-state module', 'description' => null, 'is_globally_enabled' => 0, 'requires_branch_enablement' => 1],
            ['id' => 22, 'code' => 'M91', 'name' => 'Integration module', 'description' => null, 'is_globally_enabled' => 1, 'requires_branch_enablement' => 1],
        ],
        'branch_modules' => [
            ['branch_id' => 11, 'module_id' => 21, 'is_enabled' => 0],
            ['branch_id' => 12, 'module_id' => 22, 'is_enabled' => 1],
        ],
        'branch_hours' => [
            ['id' => 31, 'branch_id' => 12, 'iso_weekday' => 1, 'opens_at' => '08:30:00', 'closes_at' => '12:00:00', 'crosses_midnight' => 0, 'interval_order' => 1],
            ['id' => 32, 'branch_id' => 12, 'iso_weekday' => 1, 'opens_at' => '13:00:00', 'closes_at' => '18:00:00', 'crosses_midnight' => 0, 'interval_order' => 2],
            ['id' => 33, 'branch_id' => 12, 'iso_weekday' => 5, 'opens_at' => '20:00:00', 'closes_at' => '02:00:00', 'crosses_midnight' => 1, 'interval_order' => 1],
            ['id' => 34, 'branch_id' => 13, 'iso_weekday' => 3, 'opens_at' => '09:00:00', 'closes_at' => '12:00:00', 'crosses_midnight' => 0, 'interval_order' => 1],
        ],
        'branch_special_hours' => [[
            'id' => 41, 'branch_id' => 12, 'local_date' => '2026-12-25', 'is_closed' => 1,
            'opens_at' => null, 'closes_at' => null, 'crosses_midnight' => 0, 'interval_order' => 0,
            'note' => 'Updated synthetic closure',
        ]],
        'services' => [
            ['id' => 51, 'code' => 'IT-1111AAAA', 'name' => 'Default-state service', 'description' => null, 'is_active' => 0],
            ['id' => 52, 'code' => 'IT-2222BBBB', 'name' => 'Integration service', 'description' => null, 'is_active' => 0],
        ],
        'branch_services' => [
            ['branch_id' => 11, 'service_id' => 51, 'is_enabled' => 0, 'display_order' => 0],
            ['branch_id' => 12, 'service_id' => 52, 'is_enabled' => 1, 'display_order' => 10],
        ],
    ];
}

M01RecoverySafety::assertInvocation(
    'inspect', 'testing', M01RecoverySafety::HOST, M01RecoverySafety::PORT,
    M01RecoverySafety::DATABASE, 'test_user', null, null,
);
foreach ([
    ['unknown', 'testing', M01RecoverySafety::HOST, M01RecoverySafety::PORT, M01RecoverySafety::DATABASE, 'test_user', null, null],
    ['inspect', 'production', M01RecoverySafety::HOST, M01RecoverySafety::PORT, M01RecoverySafety::DATABASE, 'test_user', null, null],
    ['inspect', 'testing', '127.0.0.1', M01RecoverySafety::PORT, M01RecoverySafety::DATABASE, 'test_user', null, null],
    ['inspect', 'testing', M01RecoverySafety::HOST, M01RecoverySafety::PORT, 'another_test', 'test_user', null, null],
    ['inspect', 'testing', M01RecoverySafety::HOST, M01RecoverySafety::PORT, M01RecoverySafety::DATABASE, 'ROOT', null, null],
    ['recover', 'testing', M01RecoverySafety::HOST, M01RecoverySafety::PORT, M01RecoverySafety::DATABASE, 'test_user', null, null],
    ['recover', 'testing', M01RecoverySafety::HOST, M01RecoverySafety::PORT, M01RecoverySafety::DATABASE, 'test_user', M01RecoverySafety::AUTHORIZATION, 'wrong'],
] as $arguments) {
    expectException(
        static fn() => M01RecoverySafety::assertInvocation(...$arguments),
        'M01 recovery rejects unsafe invocation or missing destructive confirmation',
        PersistenceException::class,
    );
}
M01RecoverySafety::assertInvocation(
    'recover', 'testing', M01RecoverySafety::HOST, M01RecoverySafety::PORT,
    M01RecoverySafety::DATABASE, 'test_user', M01RecoverySafety::AUTHORIZATION,
    M01RecoverySafety::CONFIRMATION, M01RecoverySafety::SESSION_CONFIRMATION,
);
check(true, 'M01 destructive recovery requires two exact opt-in confirmations');

M01RecoverySafety::assertInventory(M01RecoverySafety::OBJECTS);
expectException(
    static fn() => M01RecoverySafety::assertInventory([...M01RecoverySafety::OBJECTS, 'VIEW:foreign']),
    'M01 recovery rejects an extra object',
    PersistenceException::class,
);

$history = [];
foreach (M01RecoverySafety::MIGRATIONS as $id => $name) {
    $history[] = ['id' => $id, 'name' => $name, 'batch' => 1];
}
M01RecoverySafety::assertMigrationHistory($history);
$foreignHistory = $history;
$foreignHistory[0]['name'] = 'Foreign migration';
expectException(
    static fn() => M01RecoverySafety::assertMigrationHistory($foreignHistory),
    'M01 recovery rejects migration history not owned by the exact batch',
    PersistenceException::class,
);

$tableMetadata = [];
foreach (M01RecoverySafety::COLUMNS as $table => $columns) {
    $tableMetadata[$table] = [
        'engine' => 'InnoDB',
        'collation' => $table === 'schema_migrations' ? 'utf8mb4_general_ci' : 'utf8mb4_unicode_ci',
    ];
}
M01RecoverySafety::assertSchema(
    M01RecoverySafety::COLUMNS,
    M01RecoverySafety::REQUIRED_CONSTRAINTS,
    M01RecoverySafety::REQUIRED_INDEXES,
    $tableMetadata,
    [
        'branches_code_format_check' => 'CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY)',
        'modules_code_format_check' => 'CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY)',
        'services_code_format_check' => 'CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY)',
    ],
);
M01RecoverySafety::assertSchema(
    M01RecoverySafety::COLUMNS,
    M01RecoverySafety::REQUIRED_CONSTRAINTS,
    M01RecoverySafety::REQUIRED_INDEXES,
    $tableMetadata,
    [
        'branches_code_format_check' => 'cast(`code` as char charset binary) = cast(ucase(`code`) as char charset binary)',
        'modules_code_format_check' => 'cast(`code` as char charset binary) = cast(ucase(`code`) as char charset binary)',
        'services_code_format_check' => 'cast(`code` as char charset binary) = cast(ucase(`code`) as char charset binary)',
    ],
);
check(true, 'M01 recovery recognizes MariaDB normalized binary uppercase CHECK clauses');
$changedColumns = M01RecoverySafety::COLUMNS;
$changedColumns['branches'][] = 'foreign_column';
expectException(
    static fn() => M01RecoverySafety::assertSchema(
        $changedColumns, M01RecoverySafety::REQUIRED_CONSTRAINTS,
        M01RecoverySafety::REQUIRED_INDEXES, $tableMetadata,
        [
            'branches_code_format_check' => 'CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY)',
            'modules_code_format_check' => 'CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY)',
            'services_code_format_check' => 'CAST(code AS BINARY) = CAST(UPPER(code) AS BINARY)',
        ],
    ),
    'M01 recovery rejects changed table structure',
    PersistenceException::class,
);

M01RecoverySafety::assertNoCompetingSessions([
    ['id' => 100, 'user' => 'test_user', 'database' => M01RecoverySafety::DATABASE, 'command' => 'Query'],
], 100);
expectException(
    static fn() => M01RecoverySafety::assertNoCompetingSessions([
        ['id' => 100, 'user' => 'test_user', 'database' => M01RecoverySafety::DATABASE, 'command' => 'Query'],
        ['id' => 101, 'user' => 'test_user', 'database' => M01RecoverySafety::DATABASE, 'command' => 'Sleep'],
    ], 100),
    'M01 recovery rejects a visible prior session',
    PersistenceException::class,
);
M01RecoverySafety::assertLocksAreFree(['integration' => null, 'migration' => null]);
expectException(
    static fn() => M01RecoverySafety::assertLocksAreFree(['integration' => 101]),
    'M01 recovery rejects an incompatible advisory lock owner',
    PersistenceException::class,
);

$fixtureRows = m01RecoveryFixtureRows();
$ownership = M01RecoverySafety::identifySyntheticRows($fixtureRows);
check(array_keys($ownership) === M01RecoverySafety::DELETE_ORDER, 'M01 fixtures produce an FK-safe exact deletion plan');
$foreignRows = $fixtureRows;
$foreignRows['services'][1]['name'] = 'Foreign service';
expectException(
    static fn() => M01RecoverySafety::identifySyntheticRows($foreignRows),
    'M01 recovery rejects equal counts containing a foreign row',
    PersistenceException::class,
);
$extraRows = $fixtureRows;
$extraRows['branches'][] = $extraRows['branches'][0];
expectException(
    static fn() => M01RecoverySafety::identifySyntheticRows($extraRows),
    'M01 recovery rejects any extra row before deletion',
    PersistenceException::class,
);

$deletionLog = [];
M01RecoverySafety::deleteSyntheticRows(
    $ownership,
    static function (callable $operation): mixed { return $operation(); },
    static function (string $table, array $key) use (&$deletionLog): int {
        $deletionLog[] = $table;
        return 1;
    },
    static fn(string $table): int => 0,
);
$expectedDeletionLog = [];
foreach (M01RecoverySafety::DELETE_ORDER as $table) {
    $expectedDeletionLog = [...$expectedDeletionLog, ...array_fill(0, M01RecoverySafety::ROW_COUNTS[$table], $table)];
}
check($deletionLog === $expectedDeletionLog, 'M01 recovery deletes only owned rows in FK-safe order');

$attempts = 0;
$rolledBack = false;
try {
    M01RecoverySafety::deleteSyntheticRows(
        $ownership,
        static function (callable $operation) use (&$rolledBack): mixed {
            try {
                return $operation();
            } catch (Throwable $error) {
                $rolledBack = true;
                throw $error;
            }
        },
        static function () use (&$attempts): int {
            $attempts++;
            return $attempts === 3 ? 0 : 1;
        },
        static fn(): int => 0,
    );
    check(false, 'M01 partial DML failure expected');
} catch (PersistenceException) {
    check($rolledBack && $attempts === 3, 'M01 recovery stops and rolls back on the first changed row');
}

$backupDirectory = sys_get_temp_dir() . '/ayala-m01-recovery-' . bin2hex(random_bytes(5));
if (!mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('Unable to create recovery proof fixture.');
}
$dumpPath = $backupDirectory . '/m01.sql';
$manifestPath = $backupDirectory . '/m01.json';
file_put_contents($dumpPath, "-- synthetic verified backup fixture\n");
$hash = hash_file('sha256', $dumpPath);
$manifest = [
    'database' => M01RecoverySafety::DATABASE,
    'tool' => 'mariadb-dump',
    'dump_completed' => true,
    'restore_tested' => true,
    'verification_method' => 'independent-restore',
    'dump_sha256' => $hash,
    'dump_size' => filesize($dumpPath),
    'objects' => M01RecoverySafety::OBJECTS,
    'migration_ids' => array_keys(M01RecoverySafety::MIGRATIONS),
    'row_counts' => M01RecoverySafety::ROW_COUNTS,
    'created_at' => date(DATE_ATOM),
];
file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
try {
    M01RecoverySafety::assertBackupProof(dirname(__DIR__), $dumpPath, $manifestPath, $hash);
    $manifest['restore_tested'] = false;
    file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
    expectException(
        static fn() => M01RecoverySafety::assertBackupProof(dirname(__DIR__), $dumpPath, $manifestPath, $hash),
        'M01 recovery rejects a backup without independent restore verification',
        PersistenceException::class,
    );
    expectException(
        static fn() => M01RecoverySafety::assertBackupProof(dirname(__DIR__), $dumpPath, $manifestPath, str_repeat('0', 64)),
        'M01 recovery rejects a mismatched backup hash',
        PersistenceException::class,
    );
} finally {
    unlink($manifestPath);
    unlink($dumpPath);
    rmdir($backupDirectory);
}

$recoveryDiagnostic = IntegrationTestSafety::formatM01RecoveryFailure(
    'transactional synthetic row deletion',
    new PDOException('SQLSTATE secret DELETE FROM branches password=secret'),
);
check(
    str_contains($recoveryDiagnostic, 'transactional synthetic row deletion [database-driver]'),
    'M01 recovery diagnostics identify the safe stage and failure type',
);
check(
    !str_contains($recoveryDiagnostic, 'DELETE') && !str_contains($recoveryDiagnostic, 'password'),
    'M01 recovery diagnostics omit driver messages, SQL and secrets',
);
