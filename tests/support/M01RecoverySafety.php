<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Persistence\PersistenceException;

final class M01RecoverySafety
{
    public const HOST = '192.168.1.12';
    public const PORT = '3306';
    public const DATABASE = 'ferreteria_ayala_test';
    public const AUTHORIZATION = 'RECOVER:' . self::DATABASE;
    public const CONFIRMATION = 'DELETE SYNTHETIC M01 BATCH 1 FROM ' . self::DATABASE;
    public const SESSION_CONFIRMATION = 'NO PRIOR M01 SESSIONS ON ' . self::DATABASE;

    public const MIGRATIONS = [
        '20261019010000_create_branches' => 'Create branches table',
        '20261019010100_create_modules' => 'Create modules table',
        '20261019010200_create_branch_modules' => 'Create branch modules table',
        '20261019010300_create_branch_hours' => 'Create branch hours table',
        '20261019010400_create_branch_special_hours' => 'Create branch special hours table',
        '20261019010500_create_services' => 'Create services table',
        '20261019010600_create_branch_services' => 'Create branch services table',
    ];

    public const OBJECTS = [
        'BASE TABLE:branch_hours',
        'BASE TABLE:branch_modules',
        'BASE TABLE:branch_services',
        'BASE TABLE:branch_special_hours',
        'BASE TABLE:branches',
        'BASE TABLE:modules',
        'BASE TABLE:schema_migrations',
        'BASE TABLE:services',
    ];

    public const ROW_COUNTS = [
        'branches' => 3,
        'modules' => 2,
        'branch_modules' => 2,
        'branch_hours' => 4,
        'branch_special_hours' => 1,
        'services' => 2,
        'branch_services' => 2,
    ];

    public const COLUMNS = [
        'schema_migrations' => ['id', 'name', 'batch', 'applied_at'],
        'branches' => ['id', 'code', 'name', 'address', 'phone', 'email', 'whatsapp', 'latitude', 'longitude', 'is_active', 'created_at', 'updated_at'],
        'modules' => ['id', 'code', 'name', 'description', 'is_globally_enabled', 'requires_branch_enablement', 'created_at', 'updated_at'],
        'branch_modules' => ['branch_id', 'module_id', 'is_enabled', 'created_at', 'updated_at'],
        'branch_hours' => ['id', 'branch_id', 'iso_weekday', 'opens_at', 'closes_at', 'crosses_midnight', 'interval_order', 'created_at', 'updated_at'],
        'branch_special_hours' => ['id', 'branch_id', 'local_date', 'is_closed', 'opens_at', 'closes_at', 'crosses_midnight', 'interval_order', 'note', 'created_at', 'updated_at'],
        'services' => ['id', 'code', 'name', 'description', 'is_active', 'created_at', 'updated_at'],
        'branch_services' => ['branch_id', 'service_id', 'is_enabled', 'display_order', 'created_at', 'updated_at'],
    ];

    public const REQUIRED_CONSTRAINTS = [
        'branches:branches_code_unique:UNIQUE',
        'branches:branches_code_format_check:CHECK',
        'branches:branches_coordinates_pair_check:CHECK',
        'branches:branches_latitude_range_check:CHECK',
        'branches:branches_longitude_range_check:CHECK',
        'branches:branches_name_not_blank_check:CHECK',
        'branches:branches_active_boolean_check:CHECK',
        'branch_modules:branch_modules_branch_fk:FOREIGN KEY',
        'branch_modules:branch_modules_module_fk:FOREIGN KEY',
        'branch_modules:branch_modules_enabled_boolean_check:CHECK',
        'branch_hours:branch_hours_branch_day_order_unique:UNIQUE',
        'branch_hours:branch_hours_interval_unique:UNIQUE',
        'branch_hours:branch_hours_duration_check:CHECK',
        'branch_hours:branch_hours_weekday_check:CHECK',
        'branch_hours:branch_hours_crosses_boolean_check:CHECK',
        'branch_hours:branch_hours_order_check:CHECK',
        'branch_hours:branch_hours_clock_check:CHECK',
        'branch_hours:branch_hours_branch_fk:FOREIGN KEY',
        'branch_special_hours:branch_special_day_order_unique:UNIQUE',
        'branch_special_hours:branch_special_interval_unique:UNIQUE',
        'branch_special_hours:branch_special_representation_check:CHECK',
        'branch_special_hours:branch_special_closed_boolean_check:CHECK',
        'branch_special_hours:branch_special_cross_boolean_check:CHECK',
        'branch_special_hours:branch_special_branch_fk:FOREIGN KEY',
        'modules:modules_code_unique:UNIQUE',
        'modules:modules_code_format_check:CHECK',
        'modules:modules_name_not_blank_check:CHECK',
        'modules:modules_global_boolean_check:CHECK',
        'modules:modules_branch_requirement_boolean_check:CHECK',
        'services:services_code_unique:UNIQUE',
        'services:services_code_format_check:CHECK',
        'services:services_name_not_blank_check:CHECK',
        'services:services_description_not_blank_check:CHECK',
        'services:services_active_boolean_check:CHECK',
        'branch_services:branch_services_branch_fk:FOREIGN KEY',
        'branch_services:branch_services_service_fk:FOREIGN KEY',
        'branch_services:branch_services_enabled_boolean_check:CHECK',
    ];

    public const REQUIRED_INDEXES = [
        'branches:branches_active_name_idx',
        'branches:branches_code_unique',
        'modules:modules_availability_idx',
        'modules:modules_code_unique',
        'branch_modules:branch_modules_module_enabled_branch_idx',
        'branch_hours:branch_hours_lookup_idx',
        'branch_hours:branch_hours_branch_day_order_unique',
        'branch_hours:branch_hours_interval_unique',
        'branch_special_hours:branch_special_lookup_idx',
        'branch_special_hours:branch_special_day_order_unique',
        'branch_special_hours:branch_special_interval_unique',
        'services:services_active_name_idx',
        'services:services_code_unique',
        'branch_services:branch_services_display_idx',
        'branch_services:branch_services_service_enabled_branch_idx',
        'schema_migrations:schema_migrations_batch_id',
    ];

    public const DELETE_ORDER = [
        'branch_special_hours',
        'branch_hours',
        'branch_services',
        'branch_modules',
        'services',
        'modules',
        'branches',
    ];

    public static function assertInvocation(
        string $mode,
        string $environment,
        string $host,
        string $port,
        string $database,
        string $username,
        ?string $authorization,
        ?string $confirmation,
        ?string $sessionConfirmation = null,
    ): void {
        if (!in_array($mode, ['inspect', 'recover'], true)) {
            throw new PersistenceException('Recovery mode must be inspect or recover.');
        }
        if ($environment !== 'testing') {
            throw new PersistenceException('M01 recovery requires APP_ENV=testing.');
        }
        if (!hash_equals(self::HOST, $host) || !hash_equals(self::PORT, $port)) {
            throw new PersistenceException('M01 recovery is restricted to the exact authorized server endpoint.');
        }
        if (!hash_equals(self::DATABASE, $database)) {
            throw new PersistenceException('M01 recovery is restricted to the exact dedicated test database.');
        }
        if ($username === '' || strcasecmp(trim($username), 'root') === 0) {
            throw new PersistenceException('M01 recovery refuses an administrative or missing database account.');
        }
        if ($mode === 'recover' && (
            !is_string($authorization)
            || !hash_equals(self::AUTHORIZATION, $authorization)
            || !is_string($confirmation)
            || !hash_equals(self::CONFIRMATION, $confirmation)
            || !is_string($sessionConfirmation)
            || !hash_equals(self::SESSION_CONFIRMATION, $sessionConfirmation)
        )) {
            throw new PersistenceException('Destructive recovery remains disabled without all exact confirmations.');
        }
    }

    /** @param list<string> $objects */
    public static function assertInventory(array $objects): void
    {
        sort($objects, SORT_STRING);
        $expected = self::OBJECTS;
        sort($expected, SORT_STRING);
        if ($objects !== $expected) {
            throw new PersistenceException('Recovery refused because the database object inventory is not the exact failed M01 inventory.');
        }
    }

    /** @param list<array{id: string, name: string, batch: int}> $records */
    public static function assertMigrationHistory(array $records): void
    {
        if (count($records) !== count(self::MIGRATIONS)) {
            throw new PersistenceException('Recovery refused because the migration history size is unexpected.');
        }
        $seen = [];
        foreach ($records as $record) {
            $id = $record['id'] ?? null;
            $name = $record['name'] ?? null;
            $batch = $record['batch'] ?? null;
            if (!is_string($id) || !is_string($name) || !is_int($batch) || $batch !== 1) {
                throw new PersistenceException('Recovery refused because migration history is malformed or not batch 1.');
            }
            if (!isset(self::MIGRATIONS[$id]) || !hash_equals(self::MIGRATIONS[$id], $name) || isset($seen[$id])) {
                throw new PersistenceException('Recovery refused because migration history does not exactly identify M01.');
            }
            $seen[$id] = true;
        }
        if (array_keys(self::MIGRATIONS) !== array_keys($seen)) {
            throw new PersistenceException('Recovery refused because migration history order or membership changed.');
        }
    }

    /**
     * @param array<string, list<string>> $columns
     * @param list<string> $constraints
     * @param list<string> $indexes
     * @param array<string, array{engine: string, collation: string}> $tables
     * @param array<string, string> $checkClauses
     */
    public static function assertSchema(
        array $columns,
        array $constraints,
        array $indexes,
        array $tables,
        array $checkClauses,
    ): void
    {
        if (array_keys($columns) !== array_keys(self::COLUMNS)) {
            throw new PersistenceException('Recovery refused because the table structure inventory changed.');
        }
        foreach (self::COLUMNS as $table => $expected) {
            if (($columns[$table] ?? null) !== $expected) {
                throw new PersistenceException('Recovery refused because an M01 column signature changed.');
            }
            $metadata = $tables[$table] ?? null;
            if (!is_array($metadata) || ($metadata['engine'] ?? '') !== 'InnoDB') {
                throw new PersistenceException('Recovery refused because an M01 table engine changed.');
            }
            $collation = (string) ($metadata['collation'] ?? '');
            if ($table !== 'schema_migrations' && $collation !== 'utf8mb4_unicode_ci') {
                throw new PersistenceException('Recovery refused because an M01 table collation changed.');
            }
            if ($table === 'schema_migrations' && !str_starts_with($collation, 'utf8mb4_')) {
                throw new PersistenceException('Recovery refused because migration storage charset changed.');
            }
        }
        foreach ([self::REQUIRED_CONSTRAINTS, self::REQUIRED_INDEXES] as $position => $required) {
            $actual = $position === 0 ? $constraints : $indexes;
            sort($actual, SORT_STRING);
            sort($required, SORT_STRING);
            if ($actual !== $required) {
                throw new PersistenceException('Recovery refused because the exact M01 schema signature changed.');
            }
        }
        foreach (['branches_code_format_check', 'modules_code_format_check', 'services_code_format_check'] as $name) {
            $clause = strtolower(preg_replace('/\s+/', '', $checkClauses[$name] ?? '') ?? '');
            $uppercaseFunction = str_contains($clause, 'upper(') || str_contains($clause, 'ucase(');
            $binaryCast = str_contains($clause, 'asbinary') || str_contains($clause, 'charsetbinary');
            if (!str_contains($clause, 'cast(') || !$uppercaseFunction || !$binaryCast) {
                throw new PersistenceException('Recovery refused because a case-sensitive code constraint changed.');
            }
        }
    }

    /** @param list<array{id: int, user: string, database: ?string, command: string}> $sessions */
    public static function assertNoCompetingSessions(array $sessions, int $currentConnectionId): void
    {
        if ($currentConnectionId < 1) {
            throw new PersistenceException('Recovery connection identity could not be established.');
        }
        foreach ($sessions as $session) {
            if (($session['id'] ?? 0) !== $currentConnectionId) {
                throw new PersistenceException('Recovery refused because another visible session is using the dedicated database.');
            }
        }
    }

    /** @param array<string, int|null> $owners */
    public static function assertLocksAreFree(array $owners): void
    {
        foreach ($owners as $owner) {
            if ($owner !== null) {
                throw new PersistenceException('Recovery refused because an incompatible advisory lock is still owned.');
            }
        }
    }

    /**
     * @param array<string, list<array<string, mixed>>> $rows
     * @return array<string, list<array<string, int>>>
     */
    public static function identifySyntheticRows(array $rows): array
    {
        foreach (self::ROW_COUNTS as $table => $count) {
            if (!isset($rows[$table]) || count($rows[$table]) !== $count) {
                throw new PersistenceException('Recovery refused because M01 row counts do not match the failed run.');
            }
        }

        $branches = self::keyBy($rows['branches'], 'name');
        $defaultBranch = self::exact($branches, 'Default-state branch', [
            'address' => null, 'phone' => null, 'email' => null, 'whatsapp' => null,
            'latitude' => null, 'longitude' => null, 'is_active' => 0,
        ]);
        $integrationBranch = self::exact($branches, 'Integration branch updated', [
            'address' => null, 'phone' => null, 'email' => null, 'whatsapp' => null,
            'latitude' => null, 'longitude' => null, 'is_active' => 1,
        ]);
        $secondBranch = self::exact($branches, 'Integration branch two', [
            'address' => null, 'phone' => null, 'email' => null, 'whatsapp' => null,
            'latitude' => null, 'longitude' => null, 'is_active' => 0,
        ]);
        foreach ([$defaultBranch, $integrationBranch, $secondBranch] as $branch) {
            self::assertPositiveId($branch);
            if (preg_match('/^IT-[A-F0-9]{8}$/D', (string) ($branch['code'] ?? '')) !== 1) {
                throw new PersistenceException('Recovery refused because a branch is not an identifiable integration fixture.');
            }
        }
        self::assertUniqueValues([$defaultBranch, $integrationBranch, $secondBranch], 'id');
        self::assertUniqueValues([$defaultBranch, $integrationBranch, $secondBranch], 'code');

        $modules = self::keyBy($rows['modules'], 'code');
        $defaultModule = self::exact($modules, 'M92', [
            'name' => 'Default-state module', 'description' => null,
            'is_globally_enabled' => 0, 'requires_branch_enablement' => 1,
        ]);
        $integrationModule = self::exact($modules, 'M91', [
            'name' => 'Integration module', 'description' => null,
            'is_globally_enabled' => 1, 'requires_branch_enablement' => 1,
        ]);
        self::assertPositiveId($defaultModule);
        self::assertPositiveId($integrationModule);

        $services = self::keyBy($rows['services'], 'name');
        $defaultService = self::exact($services, 'Default-state service', ['description' => null, 'is_active' => 0]);
        $integrationService = self::exact($services, 'Integration service', ['description' => null, 'is_active' => 0]);
        foreach ([$defaultService, $integrationService] as $service) {
            self::assertPositiveId($service);
            if (preg_match('/^IT-[A-F0-9]{8}$/D', (string) ($service['code'] ?? '')) !== 1) {
                throw new PersistenceException('Recovery refused because a service is not an identifiable integration fixture.');
            }
        }
        self::assertUniqueValues([$defaultService, $integrationService], 'id');
        self::assertUniqueValues([$defaultService, $integrationService], 'code');

        $expectedRelations = [
            'branch_modules' => [
                [(int) $defaultBranch['id'], (int) $defaultModule['id'], 0],
                [(int) $integrationBranch['id'], (int) $integrationModule['id'], 1],
            ],
            'branch_services' => [
                [(int) $defaultBranch['id'], (int) $defaultService['id'], 0, 0],
                [(int) $integrationBranch['id'], (int) $integrationService['id'], 1, 10],
            ],
        ];
        self::assertTuples($rows['branch_modules'], ['branch_id', 'module_id', 'is_enabled'], $expectedRelations['branch_modules']);
        self::assertTuples($rows['branch_services'], ['branch_id', 'service_id', 'is_enabled', 'display_order'], $expectedRelations['branch_services']);

        self::assertTuples($rows['branch_hours'], [
            'branch_id', 'iso_weekday', 'opens_at', 'closes_at', 'crosses_midnight', 'interval_order',
        ], [
            [(int) $integrationBranch['id'], 1, '08:30:00', '12:00:00', 0, 1],
            [(int) $integrationBranch['id'], 1, '13:00:00', '18:00:00', 0, 2],
            [(int) $integrationBranch['id'], 5, '20:00:00', '02:00:00', 1, 1],
            [(int) $secondBranch['id'], 3, '09:00:00', '12:00:00', 0, 1],
        ]);
        foreach ($rows['branch_hours'] as $row) {
            self::assertPositiveId($row);
        }

        $special = $rows['branch_special_hours'][0];
        self::assertPositiveId($special);
        self::assertRow($special, [
            'branch_id' => (int) $integrationBranch['id'], 'local_date' => '2026-12-25',
            'is_closed' => 1, 'opens_at' => null, 'closes_at' => null,
            'crosses_midnight' => 0, 'interval_order' => 0, 'note' => 'Updated synthetic closure',
        ]);

        return [
            'branch_special_hours' => [['id' => (int) $special['id']]],
            'branch_hours' => array_map(static fn(array $row): array => ['id' => (int) $row['id']], $rows['branch_hours']),
            'branch_services' => array_map(static fn(array $row): array => [
                'branch_id' => (int) $row['branch_id'], 'service_id' => (int) $row['service_id'],
            ], $rows['branch_services']),
            'branch_modules' => array_map(static fn(array $row): array => [
                'branch_id' => (int) $row['branch_id'], 'module_id' => (int) $row['module_id'],
            ], $rows['branch_modules']),
            'services' => [['id' => (int) $defaultService['id']], ['id' => (int) $integrationService['id']]],
            'modules' => [['id' => (int) $defaultModule['id']], ['id' => (int) $integrationModule['id']]],
            'branches' => [['id' => (int) $defaultBranch['id']], ['id' => (int) $integrationBranch['id']], ['id' => (int) $secondBranch['id']]],
        ];
    }

    /**
     * @param array<string, list<array<string, int>>> $ownership
     * @param callable(callable): mixed $transaction
     * @param callable(string, array<string, int>): int $delete
     * @param callable(string): int $remaining
     */
    public static function deleteSyntheticRows(
        array $ownership,
        callable $transaction,
        callable $delete,
        callable $remaining,
    ): void {
        $transaction(static function () use ($ownership, $delete, $remaining): void {
            foreach (self::DELETE_ORDER as $table) {
                if (!isset($ownership[$table]) || count($ownership[$table]) !== self::ROW_COUNTS[$table]) {
                    throw new PersistenceException('Recovery deletion plan does not prove exact row ownership.');
                }
                foreach ($ownership[$table] as $key) {
                    if ($delete($table, $key) !== 1) {
                        throw new PersistenceException('Recovery deletion stopped because an owned row changed or disappeared.');
                    }
                }
            }
            foreach (self::DELETE_ORDER as $table) {
                if ($remaining($table) !== 0) {
                    throw new PersistenceException('Recovery deletion stopped because a commercial table is not empty.');
                }
            }
        });
    }

    public static function assertBackupProof(
        string $projectRoot,
        string $dumpPath,
        string $manifestPath,
        string $expectedSha256,
        ?int $now = null,
    ): void {
        foreach ([$dumpPath, $manifestPath] as $path) {
            if ($path === '' || !str_starts_with($path, '/') || is_link($path) || !is_file($path) || !is_readable($path)) {
                throw new PersistenceException('Recovery requires readable regular backup artifacts at absolute paths.');
            }
            $real = realpath($path);
            $root = realpath($projectRoot);
            if (!is_string($real) || !is_string($root) || str_starts_with($real . '/', rtrim($root, '/') . '/')) {
                throw new PersistenceException('Recovery backup artifacts must remain outside the project tree.');
            }
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedSha256) !== 1) {
            throw new PersistenceException('Recovery backup SHA-256 confirmation is invalid.');
        }
        $size = filesize($dumpPath);
        $actualHash = hash_file('sha256', $dumpPath);
        if (!is_int($size) || $size < 1 || !is_string($actualHash) || !hash_equals($expectedSha256, $actualHash)) {
            throw new PersistenceException('Recovery backup artifact is empty or its SHA-256 does not match.');
        }
        $manifestContents = file_get_contents($manifestPath);
        $manifest = is_string($manifestContents) ? json_decode($manifestContents, true) : null;
        if (!is_array($manifest)) {
            throw new PersistenceException('Recovery backup verification manifest is unreadable.');
        }
        $createdAt = isset($manifest['created_at']) && is_string($manifest['created_at'])
            ? strtotime($manifest['created_at'])
            : false;
        $now ??= time();
        if (
            ($manifest['database'] ?? null) !== self::DATABASE
            || ($manifest['tool'] ?? null) !== 'mariadb-dump'
            || ($manifest['dump_completed'] ?? null) !== true
            || ($manifest['restore_tested'] ?? null) !== true
            || ($manifest['verification_method'] ?? null) !== 'independent-restore'
            || ($manifest['dump_sha256'] ?? null) !== $expectedSha256
            || ($manifest['dump_size'] ?? null) !== $size
            || ($manifest['objects'] ?? null) !== self::OBJECTS
            || ($manifest['migration_ids'] ?? null) !== array_keys(self::MIGRATIONS)
            || ($manifest['row_counts'] ?? null) !== self::ROW_COUNTS
            || !is_int($createdAt)
            || $createdAt > $now + 300
            || $createdAt < $now - 86400
        ) {
            throw new PersistenceException('Recovery backup proof is incomplete, stale, or does not match the failed M01 state.');
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private static function keyBy(array $rows, string $key): array
    {
        $result = [];
        foreach ($rows as $row) {
            $value = $row[$key] ?? null;
            if (!is_string($value) || isset($result[$value])) {
                throw new PersistenceException('Recovery refused because fixture identity is ambiguous.');
            }
            $result[$value] = $row;
        }
        return $result;
    }

    private static function exact(array $rows, string $key, array $expected): array
    {
        $row = $rows[$key] ?? null;
        if (!is_array($row)) {
            throw new PersistenceException('Recovery refused because an expected integration fixture is missing.');
        }
        self::assertRow($row, $expected);
        return $row;
    }

    private static function assertRow(array $row, array $expected): void
    {
        foreach ($expected as $key => $value) {
            $actual = $row[$key] ?? null;
            if ($value === null ? $actual !== null : (string) $actual !== (string) $value) {
                throw new PersistenceException('Recovery refused because an integration fixture value changed.');
            }
        }
    }

    private static function assertPositiveId(array $row): void
    {
        if (!isset($row['id']) || filter_var($row['id'], FILTER_VALIDATE_INT) === false || (int) $row['id'] < 1) {
            throw new PersistenceException('Recovery refused because a fixture identifier is invalid.');
        }
    }

    private static function assertUniqueValues(array $rows, string $key): void
    {
        $values = array_map(static fn(array $row): string => (string) ($row[$key] ?? ''), $rows);
        if (count($values) !== count(array_unique($values, SORT_STRING))) {
            throw new PersistenceException('Recovery refused because fixture identifiers are not unique.');
        }
    }

    /** @param list<array<string, mixed>> $rows @param list<string> $keys @param list<list<int|string>> $expected */
    private static function assertTuples(array $rows, array $keys, array $expected): void
    {
        $actual = array_map(static function (array $row) use ($keys): array {
            return array_map(static function (string $key) use ($row): int|string {
                $value = $row[$key] ?? null;
                return is_numeric($value) && !str_contains((string) $value, ':') ? (int) $value : (string) $value;
            }, $keys);
        }, $rows);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new PersistenceException('Recovery refused because fixture relationships or values changed.');
        }
    }
}
