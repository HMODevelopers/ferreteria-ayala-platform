<?php
declare(strict_types=1);

namespace App\Core\Persistence;

final class IntegrationTestSafety
{
    public static function assertConfiguration(string $database, string $username): void
    {
        if (!str_ends_with($database, '_test')) {
            throw new PersistenceException('Integration tests require a dedicated database ending in _test.');
        }
        if (strcasecmp(trim($username), 'root') === 0) {
            throw new PersistenceException('Integration tests refuse administrative database users.');
        }
    }

    public static function assertConnectionIdentity(
        string $expectedDatabase,
        string $configuredUsername,
        mixed $selectedDatabase,
        mixed $effectiveAccount,
    ): void {
        if (!is_string($selectedDatabase) || !hash_equals($expectedDatabase, $selectedDatabase)) {
            throw new PersistenceException('The integration connection selected an unexpected database.');
        }
        if (!is_string($effectiveAccount) || $effectiveAccount === '') {
            throw new PersistenceException('The effective database account could not be verified.');
        }

        $separator = strrpos($effectiveAccount, '@');
        $effectiveUsername = $separator === false ? $effectiveAccount : substr($effectiveAccount, 0, $separator);
        if (
            strcasecmp($effectiveUsername, 'root') === 0
            || !hash_equals($configuredUsername, $effectiveUsername)
        ) {
            throw new PersistenceException('The effective database account is not the configured test account.');
        }
    }

    /** @param list<string> $objects */
    public static function assertDatabaseIsEmpty(array $objects): void
    {
        foreach ($objects as $object) {
            if (!is_string($object) || $object === '') {
                throw new PersistenceException('The test database inventory could not be verified safely.');
            }
        }
        if ($objects !== []) {
            throw new PersistenceException('Integration test refused: the dedicated test database is not empty.');
        }
    }

    /**
     * @param list<string> $schemaPrivileges
     * @param list<string> $globalPrivileges
     */
    public static function assertIsolationPrivileges(
        array $schemaPrivileges,
        array $globalPrivileges,
        bool $hasGrantOption,
    ): void {
        $required = ['CREATE', 'DELETE', 'DROP', 'EVENT', 'EXECUTE', 'INSERT', 'SELECT', 'TRIGGER'];
        $schemaPrivileges = array_values(array_unique($schemaPrivileges));
        $globalPrivileges = array_values(array_unique($globalPrivileges));
        sort($schemaPrivileges, SORT_STRING);
        sort($globalPrivileges, SORT_STRING);

        if ($schemaPrivileges !== $required || array_diff($globalPrivileges, ['USAGE']) !== [] || $hasGrantOption) {
            throw new PersistenceException(
                'The test account does not have the exact limited privileges required to verify database isolation.'
            );
        }
    }

    /**
     * @param list<string> $objects
     * @param list<array{id: string, name: string}> $records
     */
    public static function assertCleanupIsSafe(
        bool $storageOwned,
        array $objects,
        array $records,
        string $expectedId,
        string $expectedName,
    ): void {
        if (!$storageOwned) {
            throw new PersistenceException('Integration cleanup refused because storage ownership is unproven.');
        }

        sort($objects, SORT_STRING);
        if ($objects !== ['BASE TABLE:schema_migrations']) {
            throw new PersistenceException('Integration cleanup refused because the database inventory changed.');
        }
        if (count($records) > 1) {
            throw new PersistenceException('Integration cleanup refused because migration storage contains foreign records.');
        }
        if ($records !== []) {
            $record = $records[0];
            if (
                !isset($record['id'], $record['name'])
                || !hash_equals($expectedId, $record['id'])
                || !hash_equals($expectedName, $record['name'])
            ) {
                throw new PersistenceException('Integration cleanup refused because migration storage contains a foreign record.');
            }
        }
    }
}
