<?php
declare(strict_types=1);

namespace App\Core\Persistence;

final class IntegrationDatabaseEnvironment
{
    /** @return array{host: string, port: string, database: string, username: string, password: string, charset: string} */
    public static function forSuite(string $suite): array
    {
        $suffix = match ($suite) {
            'm00' => 'M00',
            'm01' => 'M01',
            'm02' => 'M02',
            'm03' => 'M03',
            'm04' => 'M04',
            'm05' => 'M05',
            default => throw new PersistenceException('Integration database suite is invalid.'),
        };
        $names = in_array($suite, ['m03','m04','m05'], true)
            ? [
                'host' => 'TEST_DB_HOST_' . $suffix,
                'port' => 'TEST_DB_PORT_' . $suffix,
                'database' => 'TEST_DB_DATABASE_' . $suffix,
                'username' => 'TEST_DB_USERNAME_' . $suffix,
                'password' => 'TEST_DB_PASSWORD_' . $suffix,
                'charset' => 'TEST_DB_CHARSET_' . $suffix,
            ]
            : [
                'host' => 'TEST_DB_HOST',
                'port' => 'TEST_DB_PORT',
                'database' => 'TEST_DB_DATABASE',
                'username' => 'TEST_DB_USERNAME_' . $suffix,
                'password' => 'TEST_DB_PASSWORD_' . $suffix,
                'charset' => 'TEST_DB_CHARSET',
            ];
        $values = [];
        foreach ($names as $key => $name) {
            $value = getenv($name);
            if ($value === false || ($value === '' && ($key !== 'password' || in_array($suite, ['m03','m04','m05'], true)))) {
                throw new PersistenceException('All suite-specific TEST_DB_* variables must be configured.');
            }
            $values[$key] = $value;
        }
        return $values;
    }
}
