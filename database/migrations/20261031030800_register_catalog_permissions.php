<?php
declare(strict_types=1);

use App\Application\Security\PermissionCatalog;
use App\Core\Persistence\Migration;
use App\Core\Persistence\PersistenceException;

$catalogCodes = static fn(): array => array_values(array_map(static fn(array $item): string => $item[0], array_filter(PermissionCatalog::ITEMS, static fn(array $item): bool => str_starts_with($item[0], 'catalog.') || str_starts_with($item[0], 'categories.') || str_starts_with($item[0], 'brands.') || str_starts_with($item[0], 'units.') || str_starts_with($item[0], 'attributes.') || str_starts_with($item[0], 'products.'))));
$reversible = static function (PDO $pdo) use ($catalogCodes): void {
    $codes = $catalogCodes();
    $quoted = implode(',', array_fill(0, count($codes), '?'));
    $statement = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id INNER JOIN roles r ON r.id = rp.role_id WHERE p.code IN ($quoted) AND r.code <> 'GLOBAL_ADMIN'");
    $statement->execute($codes);
    if ((int) $statement->fetchColumn() !== 0) throw new PersistenceException('Rollback refused because catalog permissions are assigned to configurable roles.');
};
return new Migration('20261031030800_register_catalog_permissions', 'Register M03 catalog permissions', static function (PDO $pdo): void {
    foreach (PermissionCatalog::ITEMS as [$code, $resource, $action, $scope, $description]) {
        if (!(str_starts_with($code, 'catalog.') || str_starts_with($code, 'categories.') || str_starts_with($code, 'brands.') || str_starts_with($code, 'units.') || str_starts_with($code, 'attributes.') || str_starts_with($code, 'products.'))) continue;
        $insert = $pdo->prepare('INSERT INTO permissions (code, resource, action, scope, description) VALUES (:code,:resource,:action,:scope,:description) ON DUPLICATE KEY UPDATE description = VALUES(description)');
        $insert->execute(compact('code', 'resource', 'action', 'scope', 'description'));
    }
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'GLOBAL_ADMIN' AND (p.code LIKE 'catalog.%' OR p.code LIKE 'categories.%' OR p.code LIKE 'brands.%' OR p.code LIKE 'units.%' OR p.code LIKE 'attributes.%' OR p.code LIKE 'products.%')");
}, static function (PDO $pdo) use ($catalogCodes, $reversible): void {
    $reversible($pdo);
    $codes = $catalogCodes();
    $quoted = implode(',', array_fill(0, count($codes), '?'));
    $deleteLinks = $pdo->prepare("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id INNER JOIN roles r ON r.id = rp.role_id WHERE r.code = 'GLOBAL_ADMIN' AND p.code IN ($quoted)");
    $deleteLinks->execute($codes);
    $delete = $pdo->prepare("DELETE FROM permissions WHERE code IN ($quoted)");
    $delete->execute($codes);
}, $reversible);
