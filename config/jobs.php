<?php
declare(strict_types=1);

$integer = static function (string $name, int $default, int $minimum, int $maximum): int {
    $value = getenv($name);
    if ($value === false || $value === '') return $default;
    if (preg_match('/^\d+$/D', $value) !== 1) throw new InvalidArgumentException($name . ' must be an integer.');
    $number = (int) $value;
    if ($number < $minimum || $number > $maximum) throw new InvalidArgumentException($name . ' is outside the allowed range.');
    return $number;
};

return [
    'retry_base_seconds' => $integer('JOB_RETRY_BASE_SECONDS', 30, 1, 3600),
    'retry_max_seconds' => $integer('JOB_RETRY_MAX_SECONDS', 3600, 1, 86400),
    'stale_after_seconds' => $integer('JOB_STALE_AFTER_SECONDS', 900, 60, 86400),
    'batch_limit' => $integer('JOB_BATCH_LIMIT', 25, 1, 200),
];
