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
            default => throw new PersistenceException('Integration database suite is invalid.'),
        };
        $names = $suite === 'm03'
            ? [
                'host' => 'TEST_DB_HOST_M03',
                'port' => 'TEST_DB_PORT_M03',
                'database' => 'TEST_DB_DATABASE_M03',
                'username' => 'TEST_DB_USERNAME_M03',
                'password' => 'TEST_DB_PASSWORD_M03',
                'charset' => 'TEST_DB_CHARSET_M03',
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
            if ($value === false || ($value === '' && ($key !== 'password' || $suite === 'm03'))) {
                throw new PersistenceException('All suite-specific TEST_DB_* variables must be configured.');
            }
            $values[$key] = $value;
        }
        return $values;
    }
}
