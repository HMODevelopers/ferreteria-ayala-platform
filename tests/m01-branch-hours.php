<?php
declare(strict_types=1);

use App\Core\Persistence\MigrationDiscovery;
use App\Core\Persistence\PersistenceException;
use App\Domain\Branches\Branch;
use App\Domain\Branches\BranchHoursInterval;
use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\BranchScheduleService;
use App\Domain\Branches\BranchSpecialHours;

function localInstant(string $value): DateTimeImmutable
{
    return new DateTimeImmutable($value, new DateTimeZone('America/Hermosillo'));
}

$service = new BranchScheduleService();
$inactiveBranch = Branch::create('HOURS', 'Hours test branch');
$activeBranch = $inactiveBranch->withActive(true);

$week = [];
for ($weekday = 1; $weekday <= 7; $weekday++) {
    $week[] = BranchHoursInterval::create($weekday, '08:00', '18:00');
}
$everyDay = BranchSchedule::create($week);
foreach (['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25'] as $date) {
    check($service->isScheduledOpenAt($everyDay, localInstant($date . ' 12:00')), 'ISO weekday schedule opens on ' . $date);
}
check(!$service->isScheduledOpenAt(BranchSchedule::create(), localInstant('2026-10-19 12:00')), 'A branch with no hours is scheduled closed');
check(!$service->isScheduledOpenAt(BranchSchedule::create([
    BranchHoursInterval::create(2, '08:00', '18:00'),
]), localInstant('2026-10-19 12:00')), 'A weekday without records is closed');

$split = BranchSchedule::create([
    BranchHoursInterval::create(1, '08:00', '13:00', sortOrder: 1),
    BranchHoursInterval::create(1, '15:00', '19:00', sortOrder: 2),
]);
check($service->isScheduledOpenAt($split, localInstant('2026-10-19 08:00')), 'Opening boundary is included');
check(!$service->isScheduledOpenAt($split, localInstant('2026-10-19 13:00')), 'Closing boundary is excluded');
check(!$service->isScheduledOpenAt($split, localInstant('2026-10-19 14:00')), 'Gap between split intervals remains closed');
check($service->isScheduledOpenAt($split, localInstant('2026-10-19 15:00')), 'Second split interval opens independently');
check(!$service->isScheduledOpenAt($split, localInstant('2026-10-19 19:00')), 'Final closing boundary is excluded');

$consecutive = BranchSchedule::create([
    BranchHoursInterval::create(1, '08:00', '13:00', sortOrder: 1),
    BranchHoursInterval::create(1, '13:00', '18:00', sortOrder: 2),
]);
check($service->isScheduledOpenAt($consecutive, localInstant('2026-10-19 13:00')), 'Consecutive semi-open intervals are valid');

foreach ([
    static fn() => BranchHoursInterval::create(0, '08:00', '18:00'),
    static fn() => BranchHoursInterval::create(8, '08:00', '18:00'),
    static fn() => BranchHoursInterval::create(1, '8:00', '18:00'),
    static fn() => BranchHoursInterval::create(1, '24:00', '02:00', true),
    static fn() => BranchHoursInterval::create(1, '08:00', '08:00'),
    static fn() => BranchHoursInterval::create(1, '18:00', '08:00'),
    static fn() => BranchHoursInterval::create(1, '08:00', '18:00', true),
    static fn() => BranchHoursInterval::create(1, '08:00', '18:00', sortOrder: 0),
] as $invalidInterval) {
    expectException($invalidInterval, 'Invalid regular interval is rejected', InvalidArgumentException::class);
}

foreach ([
    [
        BranchHoursInterval::create(1, '08:00', '13:00', sortOrder: 1),
        BranchHoursInterval::create(1, '08:00', '13:00', sortOrder: 2),
    ],
    [
        BranchHoursInterval::create(1, '08:00', '14:00', sortOrder: 1),
        BranchHoursInterval::create(1, '13:00', '18:00', sortOrder: 2),
    ],
    [
        BranchHoursInterval::create(1, '08:00', '13:00', sortOrder: 1),
        BranchHoursInterval::create(1, '15:00', '18:00', sortOrder: 1),
    ],
    [
        BranchHoursInterval::create(5, '20:00', '02:00', true),
        BranchHoursInterval::create(6, '01:00', '04:00'),
    ],
    [
        BranchHoursInterval::create(7, '22:00', '02:00', true),
        BranchHoursInterval::create(1, '01:00', '03:00'),
    ],
] as $conflictingIntervals) {
    expectException(
        static fn() => BranchSchedule::create($conflictingIntervals),
        'Duplicate, overlapping, or cross-day regular intervals are rejected',
        InvalidArgumentException::class,
    );
}

$overnight = BranchSchedule::create([
    BranchHoursInterval::create(5, '20:00', '02:00', true),
]);
check($service->isScheduledOpenAt($overnight, localInstant('2026-10-23 20:00')), 'Overnight interval includes its opening');
check($service->isScheduledOpenAt($overnight, localInstant('2026-10-24 01:59')), 'Overnight interval remains open on the next date');
check(!$service->isScheduledOpenAt($overnight, localInstant('2026-10-24 02:00')), 'Overnight interval excludes its closing');

$closedException = BranchSchedule::create(
    [BranchHoursInterval::create(1, '08:00', '18:00')],
    [BranchSpecialHours::closed('2026-10-19', 'Cierre confirmado')],
);
check(!$service->isScheduledOpenAt($closedException, localInstant('2026-10-19 10:00')), 'Closed exception replaces regular hours');

$reducedException = BranchSchedule::create(
    [BranchHoursInterval::create(1, '08:00', '18:00')],
    [BranchSpecialHours::open('2026-10-19', '10:00', '14:00')],
);
check(!$service->isScheduledOpenAt($reducedException, localInstant('2026-10-19 09:00')), 'Reduced exception suppresses earlier regular hours');
check($service->isScheduledOpenAt($reducedException, localInstant('2026-10-19 10:00')), 'Reduced exception opens at its explicit boundary');
check(!$service->isScheduledOpenAt($reducedException, localInstant('2026-10-19 14:00')), 'Reduced exception closes at its explicit boundary');

$extendedException = BranchSchedule::create(
    [BranchHoursInterval::create(1, '09:00', '17:00')],
    [BranchSpecialHours::open('2026-10-19', '07:00', '20:00')],
);
check($service->isScheduledOpenAt($extendedException, localInstant('2026-10-19 07:30')), 'Extended exception can open before regular hours');
check($service->isScheduledOpenAt($extendedException, localInstant('2026-10-19 19:30')), 'Extended exception can close after regular hours');

$splitException = BranchSchedule::create([], [
    BranchSpecialHours::open('2026-10-19', '08:00', '12:00', sortOrder: 1),
    BranchSpecialHours::open('2026-10-19', '14:00', '18:00', sortOrder: 2),
]);
check($service->isScheduledOpenAt($splitException, localInstant('2026-10-19 09:00')), 'Special date supports multiple intervals');
check(!$service->isScheduledOpenAt($splitException, localInstant('2026-10-19 13:00')), 'Special date preserves gaps between intervals');

foreach ([
    static fn() => BranchSpecialHours::closed('2026-02-30'),
    static fn() => BranchSpecialHours::closed('19-10-2026'),
    static fn() => BranchSpecialHours::open('2026-10-19', '', '18:00'),
    static fn() => BranchSpecialHours::open('2026-10-19', '18:00', '08:00'),
] as $invalidSpecial) {
    expectException($invalidSpecial, 'Invalid or incomplete special hours are rejected', InvalidArgumentException::class);
}

foreach ([
    [
        BranchSpecialHours::closed('2026-10-19'),
        BranchSpecialHours::open('2026-10-19', '08:00', '12:00'),
    ],
    [
        BranchSpecialHours::open('2026-10-19', '08:00', '14:00', sortOrder: 1),
        BranchSpecialHours::open('2026-10-19', '13:00', '18:00', sortOrder: 2),
    ],
    [
        BranchSpecialHours::open('2026-10-19', '08:00', '12:00', sortOrder: 1),
        BranchSpecialHours::open('2026-10-19', '14:00', '18:00', sortOrder: 1),
    ],
] as $invalidSpecialSet) {
    expectException(
        static fn() => BranchSchedule::create([], $invalidSpecialSet),
        'Contradictory or overlapping special hours are rejected',
        InvalidArgumentException::class,
    );
}

$overnightWithClosure = BranchSchedule::create(
    [BranchHoursInterval::create(7, '20:00', '02:00', true)],
    [BranchSpecialHours::closed('2026-10-19')],
);
check(!$service->isScheduledOpenAt($overnightWithClosure, localInstant('2026-10-19 01:00')), 'Closed exception suppresses regular overnight carry from previous date');

$overnightWithOpenException = BranchSchedule::create(
    [BranchHoursInterval::create(7, '20:00', '02:00', true)],
    [BranchSpecialHours::open('2026-10-19', '10:00', '12:00')],
);
check(!$service->isScheduledOpenAt($overnightWithOpenException, localInstant('2026-10-19 01:00')), 'Open exception also owns the whole date and suppresses regular carry');
check($service->isScheduledOpenAt($overnightWithOpenException, localInstant('2026-10-19 10:30')), 'Open exception uses only its explicit intervals');

$specialOvernight = BranchSchedule::create([], [
    BranchSpecialHours::open('2026-10-19', '20:00', '02:00', true),
]);
check($service->isScheduledOpenAt($specialOvernight, localInstant('2026-10-20 01:00')), 'Special overnight interval carries into a date without an exception');
$specialOvernightSuppressed = BranchSchedule::create([], [
    BranchSpecialHours::open('2026-10-19', '20:00', '02:00', true),
    BranchSpecialHours::closed('2026-10-20'),
]);
check(!$service->isScheduledOpenAt($specialOvernightSuppressed, localInstant('2026-10-20 01:00')), 'Next-date closure suppresses special overnight carry');
expectException(
    static fn() => BranchSchedule::create(
        [BranchHoursInterval::create(2, '01:00', '04:00')],
        [BranchSpecialHours::open('2026-10-19', '20:00', '02:00', true)],
    ),
    'Special overnight carry cannot overlap next-date regular hours',
    InvalidArgumentException::class,
);

$activeStatus = $service->statusAt($activeBranch, $split, localInstant('2026-10-19 09:00'));
check($activeStatus->scheduledOpen, 'Active branch status retains scheduled-open state');
check($activeStatus->operationallyEnabled, 'Active branch status reports operational enablement');
check($activeStatus->publiclyAvailable(), 'Active and scheduled-open branch is publicly available');
$inactiveStatus = $service->statusAt($inactiveBranch, $split, localInstant('2026-10-19 09:00'));
check($inactiveStatus->scheduledOpen, 'Inactive branch can still be scheduled open');
check(!$inactiveStatus->operationallyEnabled, 'Inactive branch is operationally disabled');
check(!$inactiveStatus->publiclyAvailable(), 'Inactive branch is never publicly available');

$utcInstant = new DateTimeImmutable('2026-10-19 15:00:00', new DateTimeZone('UTC'));
check($service->isScheduledOpenAt($split, $utcInstant), 'Evaluation converts instants to America/Hermosillo');

$allMigrations = (new MigrationDiscovery(dirname(__DIR__) . '/database/migrations'))->all();
$expectedHoursMigrations = [
    '20261019010300_create_branch_hours',
    '20261019010400_create_branch_special_hours',
];
$hoursMigrations = array_intersect_key($allMigrations, array_flip($expectedHoursMigrations));
check(array_keys($hoursMigrations) === $expectedHoursMigrations, 'M01.2 migrations are discovered in dependency order');
check(count(array_unique(array_keys($hoursMigrations))) === 2, 'M01.2 migration identifiers are unique');

$schemaPdo = new M01SchemaPdo();
foreach ($hoursMigrations as $migration) {
    $migration->up($schemaPdo);
}
check(count($schemaPdo->executed) === 2, 'Each M01.2 isolated migration defines one table');
[$hoursSql, $specialSql] = $schemaPdo->executed;
foreach (['CREATE TABLE branch_hours', 'iso_weekday', 'TIME NOT NULL', 'crosses_midnight', 'branch_hours_branch_day_order_unique', 'branch_hours_interval_unique', 'branch_hours_clock_check', 'branch_hours_duration_check', 'branch_hours_branch_fk', 'REFERENCES branches (id)', 'ON DELETE RESTRICT', 'branch_hours_lookup_idx', 'ENGINE=InnoDB', 'utf8mb4'] as $fragment) {
    check(str_contains($hoursSql, $fragment), 'Regular-hours schema contains required definition: ' . $fragment);
}
foreach (['CREATE TABLE branch_special_hours', 'local_date DATE NOT NULL', 'is_closed', 'TIME NULL', 'interval_order', 'note VARCHAR(500)', 'branch_special_day_order_unique', 'branch_special_interval_unique', 'branch_special_representation_check', 'branch_special_branch_fk', 'REFERENCES branches (id)', 'ON DELETE RESTRICT', 'branch_special_lookup_idx', 'ENGINE=InnoDB', 'utf8mb4'] as $fragment) {
    check(str_contains($specialSql, $fragment), 'Special-hours schema contains required definition: ' . $fragment);
}

foreach ($hoursMigrations as $migration) {
    $table = $migration->id === '20261019010300_create_branch_hours'
        ? 'branch_hours'
        : 'branch_special_hours';
    check($migration->isReversible(), 'M01.2 migration declares a guarded rollback');
    $nonEmptyPdo = new M01SchemaPdo([$table => 1]);
    expectException(
        static fn() => $migration->down($nonEmptyPdo),
        'M01.2 rollback rejects data-bearing table: ' . $table,
        PersistenceException::class,
    );
    check($nonEmptyPdo->executed === [], 'Rejected M01.2 rollback preserves table: ' . $table);
    $emptyPdo = new M01SchemaPdo([$table => 0]);
    $migration->down($emptyPdo);
    check($emptyPdo->executed === ['DROP TABLE ' . $table], 'Empty M01.2 table rollback is explicit: ' . $table);
}
