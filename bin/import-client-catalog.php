<?php
declare(strict_types=1);

use App\Core\Persistence\Connection;
use App\Core\Persistence\DatabaseConfig;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/cli.php';
require dirname(__DIR__) . '/bootstrap/app.php';

const CLIENT_CATALOG_ROWS = 31028;
const CLIENT_CATALOG_ACTIVE = 10135;
const CLIENT_CATALOG_CONFIRMATION = 'IMPORTAR_31028_PRODUCTOS';
const CLIENT_CATALOG_TABLES = [
    'categories', 'brands', 'units', 'products', 'product_variants',
    'product_barcodes', 'attribute_definitions', 'attribute_options',
    'category_attributes', 'variant_attribute_values', 'unit_conversions',
];

/** @return array<string, string|bool> */
function options(array $arguments): array
{
    $out = [];
    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--')) {
            throw new InvalidArgumentException('Argumento inválido: ' . $argument);
        }
        $parts = explode('=', substr($argument, 2), 2);
        $out[$parts[0]] = $parts[1] ?? true;
    }
    return $out;
}

function optionString(array $options, string $name, string $default = ''): string
{
    $value = $options[$name] ?? $default;
    if (!is_string($value) || $value === '') {
        throw new InvalidArgumentException('Falta --' . $name . '=valor.');
    }
    return $value;
}

function foldText(string $value): string
{
    return strtolower(strtr($value, [
        'Á' => 'á', 'É' => 'é', 'Í' => 'í', 'Ó' => 'ó', 'Ú' => 'ú', 'Ü' => 'ü', 'Ñ' => 'ñ',
    ]));
}

/** @return list<array<string, string>> */
function csvRecords(string $path, array $required): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo abrir: ' . $path);
    }
    $headers = fgetcsv($handle, 0, ',', '"', '\\');
    if ($headers === false) {
        fclose($handle);
        throw new RuntimeException('CSV sin encabezados: ' . $path);
    }
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? $headers[0];
    }
    $missing = array_values(array_diff($required, $headers));
    if ($missing !== []) {
        fclose($handle);
        throw new RuntimeException('Faltan columnas en ' . basename($path) . ': ' . implode(', ', $missing));
    }
    $records = [];
    while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if (count($values) !== count($headers)) {
            fclose($handle);
            throw new RuntimeException('Fila con ancho inválido en ' . basename($path));
        }
        $record = array_combine($headers, array_map(static fn(mixed $value): string => (string) $value, $values));
        if (!is_array($record)) {
            fclose($handle);
            throw new RuntimeException('No se pudo interpretar ' . basename($path));
        }
        $records[] = $record;
    }
    fclose($handle);
    return $records;
}

/** @return array{manifest: array<string, mixed>, categories: list<array<string, string>>, brands: list<array<string, string>>, units: list<array<string, string>>, products: list<array<string, string>>, variants: list<array<string, string>>} */
function loadPackage(string $directory): array
{
    $directory = rtrim($directory, '/');
    if (!is_dir($directory)) {
        throw new RuntimeException('No existe el paquete: ' . $directory);
    }
    $manifestPath = $directory . '/manifest.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || !isset($manifest['generated_files'], $manifest['stats'])) {
        throw new RuntimeException('El manifiesto del paquete no es válido.');
    }
    foreach ($manifest['generated_files'] as $name => $metadata) {
        $path = $directory . '/' . $name;
        if (!is_file($path)
            || !isset($metadata['bytes'], $metadata['sha256'])
            || filesize($path) !== (int) $metadata['bytes']
            || !hash_equals((string) $metadata['sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('Falló la integridad del archivo: ' . $name);
        }
    }

    $categories = csvRecords($directory . '/categories.csv', ['code', 'name', 'description', 'parent_code', 'is_active']);
    $brands = csvRecords($directory . '/brands.csv', ['code', 'name', 'description', 'is_active']);
    $units = csvRecords($directory . '/units.csv', ['code', 'name', 'symbol', 'decimal_places', 'is_active']);
    $products = csvRecords($directory . '/products.csv', [
        'code', 'name', 'description', 'category_code', 'brand_code',
        'tracks_lots', 'tracks_expiration', 'tracks_serials', 'is_active',
    ]);
    $variants = csvRecords($directory . '/product_variants.csv', ['product_code', 'unit_code', 'sku', 'name', 'is_active']);

    if (count($products) !== CLIENT_CATALOG_ROWS || count($variants) !== CLIENT_CATALOG_ROWS) {
        throw new RuntimeException('El paquete no contiene las 31,028 filas esperadas.');
    }
    $activeProducts = count(array_filter($products, static fn(array $row): bool => $row['is_active'] === '1'));
    if ($activeProducts !== CLIENT_CATALOG_ACTIVE) {
        throw new RuntimeException('El paquete no conserva los 10,135 productos activos esperados.');
    }

    $categoryCodes = array_fill_keys(array_column($categories, 'code'), true);
    $brandCodes = array_fill_keys(array_column($brands, 'code'), true);
    $unitCodes = array_fill_keys(array_column($units, 'code'), true);
    $productCodes = [];
    foreach ($products as $product) {
        $key = strtolower($product['code']);
        if (isset($productCodes[$key])) {
            throw new RuntimeException('Código duplicado en el paquete: ' . $product['code']);
        }
        if (!isset($categoryCodes[$product['category_code']])) {
            throw new RuntimeException('Categoría ausente en el paquete: ' . $product['category_code']);
        }
        if ($product['brand_code'] !== '' && !isset($brandCodes[$product['brand_code']])) {
            throw new RuntimeException('Marca ausente en el paquete: ' . $product['brand_code']);
        }
        $productCodes[$key] = true;
    }
    $skus = [];
    foreach ($variants as $variant) {
        $key = strtolower($variant['sku']);
        if (isset($skus[$key])) {
            throw new RuntimeException('SKU duplicado en el paquete: ' . $variant['sku']);
        }
        if (!isset($productCodes[strtolower($variant['product_code'])])) {
            throw new RuntimeException('Producto ausente para SKU: ' . $variant['sku']);
        }
        if (!isset($unitCodes[$variant['unit_code']])) {
            throw new RuntimeException('Unidad ausente para SKU: ' . $variant['sku']);
        }
        $skus[$key] = true;
    }

    return compact('manifest', 'categories', 'brands', 'units', 'products', 'variants');
}

/** @return array<string, list<array<string, mixed>>> */
function databaseSnapshot(PDO $pdo): array
{
    $orderBy = [
        'category_attributes' => 'category_id,attribute_id',
        'variant_attribute_values' => 'variant_id,attribute_id',
    ];
    $snapshot = [];
    foreach (CLIENT_CATALOG_TABLES as $table) {
        $snapshot[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . ($orderBy[$table] ?? 'id'))->fetchAll();
    }
    return $snapshot;
}

/** @return array<string, int> */
function databaseCounts(PDO $pdo): array
{
    $counts = [];
    foreach (CLIENT_CATALOG_TABLES as $table) {
        $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    return $counts;
}

/** @return array<string, mixed> */
function createBackup(PDO $pdo, string $database, string $directory): array
{
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo crear el directorio privado de respaldos.');
    }
    $timestamp = date('Ymd-His');
    $snapshotPath = rtrim($directory, '/') . '/catalog-pre-import-' . $timestamp . '.json';
    $manifestPath = $snapshotPath . '.manifest.json';
    if (file_exists($snapshotPath) || file_exists($manifestPath)) {
        throw new RuntimeException('Ya existe un respaldo con el mismo identificador temporal.');
    }

    $tables = databaseSnapshot($pdo);
    $ddl = [];
    foreach (CLIENT_CATALOG_TABLES as $table) {
        $row = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch();
        $ddl[$table] = is_array($row) ? (string) array_values($row)[1] : '';
    }
    $payload = [
        'database' => $database,
        'created_at' => date(DATE_ATOM),
        'server_version' => (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        'tables' => $tables,
        'ddl' => $ddl,
    ];
    $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    $handle = fopen($snapshotPath, 'xb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo crear el respaldo.');
    }
    chmod($snapshotPath, 0600);
    if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
        fclose($handle);
        throw new RuntimeException('El respaldo quedó incompleto.');
    }
    fclose($handle);
    if (json_decode((string) file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR) !== $payload) {
        throw new RuntimeException('El respaldo no pudo verificarse por decodificación.');
    }
    $manifest = [
        'database' => $database,
        'snapshot_file' => basename($snapshotPath),
        'bytes' => filesize($snapshotPath),
        'sha256' => hash_file('sha256', $snapshotPath),
        'counts' => array_map('count', $tables),
    ];
    $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    if (file_put_contents($manifestPath, $manifestJson, LOCK_EX) !== strlen($manifestJson)) {
        throw new RuntimeException('No se pudo crear el manifiesto del respaldo.');
    }
    chmod($manifestPath, 0600);
    return ['snapshot' => $snapshotPath, 'manifest' => $manifestPath, 'sha256' => $manifest['sha256'], 'counts' => $manifest['counts']];
}

/** @return array<string, mixed> */
function verifyBackup(PDO $pdo, string $database, string $manifestPath): array
{
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || ($manifest['database'] ?? '') !== $database) {
        throw new RuntimeException('El manifiesto del respaldo no corresponde a esta base.');
    }
    $snapshotPath = dirname($manifestPath) . '/' . basename((string) ($manifest['snapshot_file'] ?? ''));
    if (!is_file($snapshotPath)
        || filesize($snapshotPath) !== (int) ($manifest['bytes'] ?? -1)
        || !hash_equals((string) ($manifest['sha256'] ?? ''), hash_file('sha256', $snapshotPath))) {
        throw new RuntimeException('Falló la integridad del respaldo previo.');
    }
    $payload = json_decode((string) file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || ($payload['database'] ?? '') !== $database || !isset($payload['tables'])) {
        throw new RuntimeException('El contenido del respaldo previo no es válido.');
    }
    if (databaseSnapshot($pdo) !== $payload['tables']) {
        throw new RuntimeException('El catálogo cambió después del respaldo; se requiere un respaldo nuevo.');
    }
    return $manifest;
}

/** @return array<string, mixed> */
function analyzeConflicts(PDO $pdo, array $package, int $actorId): array
{
    $actor = $pdo->prepare("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=? AND u.is_active=1 AND r.name='Administrador global' LIMIT 1");
    $actor->execute([$actorId]);
    if ($actor->fetchColumn() === false) {
        throw new RuntimeException('El actor no es un Administrador global activo.');
    }

    $conflicts = [];
    foreach ([['categories', $package['categories']], ['brands', $package['brands']], ['units', $package['units']]] as [$table, $rows]) {
        $existing = $pdo->query('SELECT code,name FROM ' . $table)->fetchAll();
        $byCode = [];
        $byName = [];
        foreach ($existing as $row) {
            $byCode[strtolower((string) $row['code'])] = $row;
            $byName[foldText((string) $row['name'])] = $row;
        }
        foreach ($rows as $row) {
            $codeKey = strtolower($row['code']);
            $nameKey = foldText($row['name']);
            if (isset($byCode[$codeKey]) && $byCode[$codeKey]['name'] !== $row['name']) {
                $conflicts[] = $table . ': código existente con otro nombre: ' . $row['code'];
            }
            if (isset($byName[$nameKey]) && strcasecmp((string) $byName[$nameKey]['code'], $row['code']) !== 0) {
                $conflicts[] = $table . ': nombre existente con otro código: ' . $row['name'];
            }
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() !== 0) {
        $conflicts[] = 'products no está vacía.';
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM product_variants')->fetchColumn() !== 0) {
        $conflicts[] = 'product_variants no está vacía.';
    }

    return [
        'database_counts' => databaseCounts($pdo),
        'package' => [
            'categories' => count($package['categories']),
            'brands' => count($package['brands']),
            'units' => count($package['units']),
            'products' => count($package['products']),
            'variants' => count($package['variants']),
            'active_products' => CLIENT_CATALOG_ACTIVE,
        ],
        'conflicts' => $conflicts,
        'ready' => $conflicts === [],
    ];
}

function insertCatalogs(PDO $pdo, array $package): array
{
    $categoryIds = [];
    foreach ($pdo->query('SELECT id,code FROM categories')->fetchAll() as $row) {
        $categoryIds[(string) $row['code']] = (int) $row['id'];
    }
    $insertCategory = $pdo->prepare('INSERT INTO categories(parent_id,code,name,description,is_active) VALUES(NULL,?,?,?,?)');
    foreach ($package['categories'] as $row) {
        if (isset($categoryIds[$row['code']])) {
            continue;
        }
        $insertCategory->execute([$row['code'], $row['name'], $row['description'] === '' ? null : $row['description'], (int) $row['is_active']]);
        $categoryIds[$row['code']] = (int) $pdo->lastInsertId();
    }

    $brandIds = [];
    foreach ($pdo->query('SELECT id,code FROM brands')->fetchAll() as $row) {
        $brandIds[(string) $row['code']] = (int) $row['id'];
    }
    $insertBrand = $pdo->prepare('INSERT INTO brands(code,name,description,is_active) VALUES(?,?,?,?)');
    foreach ($package['brands'] as $row) {
        if (isset($brandIds[$row['code']])) {
            continue;
        }
        $insertBrand->execute([$row['code'], $row['name'], $row['description'] === '' ? null : $row['description'], (int) $row['is_active']]);
        $brandIds[$row['code']] = (int) $pdo->lastInsertId();
    }

    $unitIds = [];
    foreach ($pdo->query('SELECT id,code FROM units')->fetchAll() as $row) {
        $unitIds[(string) $row['code']] = (int) $row['id'];
    }
    $insertUnit = $pdo->prepare('INSERT INTO units(code,name,symbol,decimal_places,is_active) VALUES(?,?,?,?,?)');
    foreach ($package['units'] as $row) {
        if (isset($unitIds[$row['code']])) {
            continue;
        }
        $insertUnit->execute([$row['code'], $row['name'], $row['symbol'], (int) $row['decimal_places'], (int) $row['is_active']]);
        $unitIds[$row['code']] = (int) $pdo->lastInsertId();
    }
    return [$categoryIds, $brandIds, $unitIds];
}

function insertProducts(PDO $pdo, array $products, array $categoryIds, array $brandIds): array
{
    foreach (array_chunk($products, 250) as $chunk) {
        $placeholders = [];
        $params = [];
        foreach ($chunk as $row) {
            $placeholders[] = '(?,?,?,?,?,?,?,?,?)';
            array_push(
                $params,
                $row['code'], $row['name'], $row['description'] === '' ? null : $row['description'],
                $categoryIds[$row['category_code']], $row['brand_code'] === '' ? null : $brandIds[$row['brand_code']],
                (int) $row['tracks_lots'], (int) $row['tracks_expiration'], (int) $row['tracks_serials'], (int) $row['is_active'],
            );
        }
        $statement = $pdo->prepare('INSERT INTO products(code,name,description,category_id,brand_id,tracks_lots,tracks_expiration,tracks_serials,is_active) VALUES' . implode(',', $placeholders));
        $statement->execute($params);
    }
    $ids = [];
    foreach ($pdo->query('SELECT id,code FROM products')->fetchAll() as $row) {
        $ids[(string) $row['code']] = (int) $row['id'];
    }
    if (count($ids) !== count($products)) {
        throw new RuntimeException('No se recuperaron todos los productos insertados.');
    }
    return $ids;
}

function insertVariants(PDO $pdo, array $variants, array $productIds, array $unitIds): void
{
    foreach (array_chunk($variants, 250) as $chunk) {
        $placeholders = [];
        $params = [];
        foreach ($chunk as $row) {
            $placeholders[] = '(?,?,?,?,?)';
            array_push($params, $productIds[$row['product_code']], $unitIds[$row['unit_code']], $row['sku'], $row['name'], (int) $row['is_active']);
        }
        $statement = $pdo->prepare('INSERT INTO product_variants(product_id,unit_id,sku,name,is_active) VALUES' . implode(',', $placeholders));
        $statement->execute($params);
    }
}

function applyImport(PDO $pdo, array $package, int $actorId, string $packageDirectory): array
{
    $lockName = 'ferreteria_ayala_client_catalog_import';
    $lock = $pdo->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('No se pudo obtener el bloqueo exclusivo de importación.');
    }
    try {
        if (!$pdo->beginTransaction()) {
            throw new RuntimeException('No se pudo iniciar la transacción.');
        }
        try {
            $analysis = analyzeConflicts($pdo, $package, $actorId);
            if (!$analysis['ready']) {
                throw new RuntimeException('La base cambió y la importación ya no es segura: ' . implode(' ', $analysis['conflicts']));
            }
            [$categoryIds, $brandIds, $unitIds] = insertCatalogs($pdo, $package);
            $productIds = insertProducts($pdo, $package['products'], $categoryIds, $brandIds);
            insertVariants($pdo, $package['variants'], $productIds, $unitIds);

            $token = bin2hex(random_bytes(24));
            $contentHash = hash_file('sha256', rtrim($packageDirectory, '/') . '/manifest.json');
            $run = $pdo->prepare("INSERT INTO catalog_import_runs(token,actor_user_id,source_name,content_hash,status,total_rows,accepted_rows,rejected_rows,created_products,created_variants,updated_products,confirmed_at) VALUES(?,?,?,?,'confirmed',?,?,0,?,?,0,CURRENT_TIMESTAMP(6))");
            $run->execute([$token, $actorId, 'catalog-export-20261118-final.zip', $contentHash, CLIENT_CATALOG_ROWS, CLIENT_CATALOG_ROWS, CLIENT_CATALOG_ROWS, CLIENT_CATALOG_ROWS]);
            $runId = (int) $pdo->lastInsertId();

            $context = json_encode([
                'reason' => 'Importación inicial del catálogo entregado por cliente.',
                'changed_fields' => ['categories', 'brands', 'units', 'products', 'product_variants'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $audit = $pdo->prepare("INSERT INTO audit_logs(actor_user_id,action,resource,resource_id,outcome,context_json) VALUES(?,'catalog.client_import','catalog_import',?,'success',?)");
            $audit->execute([$actorId, (string) $runId, $context]);

            if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() !== CLIENT_CATALOG_ROWS
                || (int) $pdo->query('SELECT COUNT(*) FROM product_variants')->fetchColumn() !== CLIENT_CATALOG_ROWS
                || (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active=1')->fetchColumn() !== CLIENT_CATALOG_ACTIVE
                || (int) $pdo->query('SELECT COUNT(*) FROM product_variants WHERE is_active=1')->fetchColumn() !== CLIENT_CATALOG_ACTIVE) {
                throw new RuntimeException('La verificación interna de conteos falló.');
            }
            if (!$pdo->commit()) {
                throw new RuntimeException('No se pudo confirmar la transacción.');
            }
            return ['run_id' => $runId, 'token' => $token, 'content_hash' => $contentHash];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}

/** @return array<string, mixed> */
function verifyImport(PDO $pdo, array $package): array
{
    $catalogChecks = [];
    foreach ([['categories', $package['categories']], ['brands', $package['brands']], ['units', $package['units']]] as [$table, $expectedRows]) {
        $actualRows = $pdo->query('SELECT * FROM ' . $table)->fetchAll();
        $actualByCode = [];
        foreach ($actualRows as $row) {
            $actualByCode[(string) $row['code']] = $row;
        }
        foreach ($expectedRows as $expected) {
            $actual = $actualByCode[$expected['code']] ?? null;
            if (!is_array($actual)
                || (string) $actual['name'] !== $expected['name']
                || (int) $actual['is_active'] !== (int) $expected['is_active']) {
                throw new RuntimeException('La verificación falló para ' . $table . ': ' . $expected['code']);
            }
        }
        $catalogChecks[$table . '_package_rows'] = count($expectedRows);
        $catalogChecks[$table . '_database_rows'] = count($actualRows);
    }

    $actualProducts = [];
    $productSql = "SELECT p.code,p.name,COALESCE(p.description,'') description,c.code category_code,COALESCE(b.code,'') brand_code,p.tracks_lots,p.tracks_expiration,p.tracks_serials,p.is_active FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN brands b ON b.id=p.brand_id";
    foreach ($pdo->query($productSql)->fetchAll() as $row) {
        $actualProducts[(string) $row['code']] = $row;
    }
    if (count($actualProducts) !== count($package['products'])) {
        throw new RuntimeException('La cantidad persistida de productos no coincide con el paquete.');
    }
    foreach ($package['products'] as $expected) {
        $actual = $actualProducts[$expected['code']] ?? null;
        if (!is_array($actual)) {
            throw new RuntimeException('Producto persistido ausente: ' . $expected['code']);
        }
        foreach (['name', 'description', 'category_code', 'brand_code'] as $field) {
            if ((string) $actual[$field] !== $expected[$field]) {
                throw new RuntimeException('Diferencia persistida en ' . $expected['code'] . ': ' . $field);
            }
        }
        foreach (['tracks_lots', 'tracks_expiration', 'tracks_serials', 'is_active'] as $field) {
            if ((int) $actual[$field] !== (int) $expected[$field]) {
                throw new RuntimeException('Diferencia persistida en ' . $expected['code'] . ': ' . $field);
            }
        }
    }

    $actualVariants = [];
    $variantSql = 'SELECT p.code product_code,u.code unit_code,pv.sku,pv.name,pv.is_active FROM product_variants pv JOIN products p ON p.id=pv.product_id JOIN units u ON u.id=pv.unit_id';
    foreach ($pdo->query($variantSql)->fetchAll() as $row) {
        $actualVariants[(string) $row['sku']] = $row;
    }
    if (count($actualVariants) !== count($package['variants'])) {
        throw new RuntimeException('La cantidad persistida de variantes no coincide con el paquete.');
    }
    foreach ($package['variants'] as $expected) {
        $actual = $actualVariants[$expected['sku']] ?? null;
        if (!is_array($actual)) {
            throw new RuntimeException('SKU persistido ausente: ' . $expected['sku']);
        }
        foreach (['product_code', 'unit_code', 'sku', 'name'] as $field) {
            if ((string) $actual[$field] !== $expected[$field]) {
                throw new RuntimeException('Diferencia persistida en SKU ' . $expected['sku'] . ': ' . $field);
            }
        }
        if ((int) $actual['is_active'] !== (int) $expected['is_active']) {
            throw new RuntimeException('Diferencia de estado persistido en SKU ' . $expected['sku']);
        }
    }

    $run = $pdo->query('SELECT id,status,total_rows,accepted_rows,rejected_rows,created_products,created_variants,updated_products,content_hash FROM catalog_import_runs ORDER BY id DESC LIMIT 1')->fetch();
    if (!is_array($run)
        || $run['status'] !== 'confirmed'
        || (int) $run['total_rows'] !== CLIENT_CATALOG_ROWS
        || (int) $run['accepted_rows'] !== CLIENT_CATALOG_ROWS
        || (int) $run['created_products'] !== CLIENT_CATALOG_ROWS
        || (int) $run['created_variants'] !== CLIENT_CATALOG_ROWS) {
        throw new RuntimeException('El historial durable de importación no coincide.');
    }
    $auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='catalog.client_import' AND resource='catalog_import' AND outcome='success'")->fetchColumn();
    if ($auditCount < 1) {
        throw new RuntimeException('No existe el evento de auditoría de importación.');
    }

    return $catalogChecks + [
        'products' => count($actualProducts),
        'variants' => count($actualVariants),
        'active_products' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active=1')->fetchColumn(),
        'inactive_products' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active=0')->fetchColumn(),
        'fallback_category_products' => (int) $pdo->query("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE c.code='CAT_POR_CLASIFICAR'")->fetchColumn(),
        'orphan_products' => (int) $pdo->query('SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE c.id IS NULL')->fetchColumn(),
        'orphan_variants' => (int) $pdo->query('SELECT COUNT(*) FROM product_variants pv LEFT JOIN products p ON p.id=pv.product_id LEFT JOIN units u ON u.id=pv.unit_id WHERE p.id IS NULL OR u.id IS NULL')->fetchColumn(),
        'import_run_id' => (int) $run['id'],
        'import_content_hash' => (string) $run['content_hash'],
        'audit_events' => $auditCount,
        'status' => 'verified',
    ];
}

try {
    $command = $argv[1] ?? '';
    if (!in_array($command, ['inspect', 'backup', 'dry-run', 'apply', 'verify'], true)) {
        throw new InvalidArgumentException('Uso: php bin/import-client-catalog.php inspect|backup|dry-run|apply|verify --package=... [--actor=1 --backup=... --authorization=... --confirm=...]');
    }
    $options = options(array_slice($argv, 2));
    $packageDirectory = optionString($options, 'package');
    $package = loadPackage($packageDirectory);
    $config = DatabaseConfig::fromArray(require dirname(__DIR__) . '/config/database.php');
    $connection = new Connection($config);
    $pdo = $connection->pdo();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $actorId = (int) ($options['actor'] ?? 1);

    if ($command === 'verify') {
        echo json_encode(['mode' => 'verify', 'database' => $database] + verifyImport($pdo, $package), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }

    if ($command === 'inspect' || $command === 'dry-run') {
        $result = analyzeConflicts($pdo, $package, $actorId);
        $result['mode'] = $command;
        $result['database'] = $database;
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
        exit($result['ready'] ? 0 : 1);
    }
    if ($command === 'backup') {
        $backup = createBackup($pdo, $database, dirname(__DIR__) . '/storage/backups');
        echo json_encode(['mode' => 'backup', 'database' => $database] + $backup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
        exit(0);
    }

    if (optionString($options, 'authorization') !== $database) {
        throw new RuntimeException('La autorización no coincide con la base conectada.');
    }
    if (optionString($options, 'confirm') !== CLIENT_CATALOG_CONFIRMATION) {
        throw new RuntimeException('Falta la confirmación exacta de importación.');
    }
    $backupManifest = optionString($options, 'backup');
    verifyBackup($pdo, $database, $backupManifest);
    $analysis = analyzeConflicts($pdo, $package, $actorId);
    if (!$analysis['ready']) {
        throw new RuntimeException('La simulación previa contiene conflictos: ' . implode(' ', $analysis['conflicts']));
    }
    $result = applyImport($pdo, $package, $actorId, $packageDirectory);
    echo json_encode([
        'mode' => 'apply',
        'database' => $database,
        'status' => 'confirmed',
        'products' => CLIENT_CATALOG_ROWS,
        'variants' => CLIENT_CATALOG_ROWS,
        'active' => CLIENT_CATALOG_ACTIVE,
        'inactive' => CLIENT_CATALOG_ROWS - CLIENT_CATALOG_ACTIVE,
    ] + $result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
