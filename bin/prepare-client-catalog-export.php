<?php
declare(strict_types=1);

/**
 * Prepares a private, database-neutral catalog export from the reviewed client CSVs.
 * It never connects to MySQL and refuses to overwrite an existing export directory.
 */

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Este comando solo puede ejecutarse por CLI.');
}

$root = dirname(__DIR__);
$sourceDirectory = $argv[1] ?? '/home/carlos-lafarga/Documentos/FERRETERIA AYALA';
$outputDirectory = $argv[2] ?? $root . '/storage/backups/catalog-export-20261118';
$satCatalog = $argv[3] ?? '/tmp/sat-cfdi-catalog/catCFDI.xsd';

$catalogPath = rtrim($sourceDirectory, '/') . '/catalogo_normalizado.csv';
$lineMapPath = rtrim($sourceDirectory, '/') . '/mapeo_lineas.csv';
$unitMapPath = rtrim($sourceDirectory, '/') . '/mapeo_unidades.csv';
$reviewWorkbookPath = rtrim($sourceDirectory, '/') . '/Articulos_normalizados_para_revision.xlsx';
$originalWorkbookPath = rtrim($sourceDirectory, '/') . '/Artículos.xlsx';

foreach ([$catalogPath, $lineMapPath, $unitMapPath, $reviewWorkbookPath, $originalWorkbookPath, $satCatalog] as $required) {
    if (!is_file($required) || !is_readable($required)) {
        throw new RuntimeException('No se puede leer el archivo requerido: ' . $required);
    }
}

if (file_exists($outputDirectory)) {
    throw new RuntimeException('El directorio de salida ya existe; no se sobrescribirá: ' . $outputDirectory);
}
if (!mkdir($outputDirectory, 0700, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('No se pudo crear el directorio privado de exportación.');
}

/** @return array{0: resource, 1: array<string, int>} */
function openCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo abrir: ' . $path);
    }
    $headers = fgetcsv($handle, 0, ',', '"', '\\');
    if ($headers === false) {
        fclose($handle);
        throw new RuntimeException('El CSV no contiene encabezados: ' . $path);
    }
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? $headers[0];
    }
    $indexes = [];
    foreach ($headers as $index => $header) {
        $indexes[(string) $header] = $index;
    }
    return [$handle, $indexes];
}

/** @param resource $handle @param array<string, int> $indexes @return array<string, string>|null */
function readCsvRow($handle, array $indexes): ?array
{
    $values = fgetcsv($handle, 0, ',', '"', '\\');
    if ($values === false) {
        return null;
    }
    $row = [];
    foreach ($indexes as $header => $index) {
        $row[$header] = isset($values[$index]) ? (string) $values[$index] : '';
    }
    return $row;
}

/** @param list<string> $required @param array<string, int> $indexes */
function requireColumns(array $required, array $indexes, string $path): void
{
    $missing = array_values(array_diff($required, array_keys($indexes)));
    if ($missing !== []) {
        throw new RuntimeException('Faltan columnas en ' . $path . ': ' . implode(', ', $missing));
    }
}

/** @return resource */
function createCsv(string $path, array $headers)
{
    $handle = fopen($path, 'xb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo crear: ' . $path);
    }
    chmod($path, 0600);
    if (fputcsv($handle, $headers, ',', '"', '\\', "\r\n") === false) {
        fclose($handle);
        throw new RuntimeException('No se pudo escribir el encabezado: ' . $path);
    }
    return $handle;
}

/** @param resource $handle */
function putCsv($handle, array $values): void
{
    if (fputcsv($handle, $values, ',', '"', '\\', "\r\n") === false) {
        throw new RuntimeException('No se pudo escribir una fila CSV.');
    }
}

function closeFile($handle): void
{
    if (!fflush($handle)) {
        throw new RuntimeException('No se pudo vaciar un archivo generado.');
    }
    fclose($handle);
}

function validProductCode(string $value): bool
{
    return preg_match('/^[A-Z0-9](?:[A-Z0-9._-]{0,78}[A-Z0-9])?$/D', $value) === 1;
}

function validSku(string $value): bool
{
    return preg_match('/^[A-Z0-9](?:[A-Z0-9._\/-]{0,78}[A-Z0-9])?$/D', $value) === 1;
}

function textLength(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? strlen($value) : $count;
}

function cleanLegacyName(string $value): string
{
    // Conservative repairs for encoding artifacts observed in this exact source.
    $value = strtr($value, [
        "CI\u{00C3}\u{2039}N" => 'CIÓN',
        "NI\u{00C2}\u{00AD}OS" => 'NIÑOS',
        "\u{00C3}\u{0081}" => 'Á',
        "\u{00C3}\u{008D}" => 'Í',
        "\u{00C3}\u{0090}" => 'Ñ',
        "\u{00C3}\u{2018}" => 'Ñ',
        "\u{00C3}\u{0161}" => 'Ú',
        "\u{00C3}\u{201C}" => 'Ó',
        "\u{00C3}\u{2030}" => 'É',
        "\u{00C3}\u{20AC}" => 'À',
        "\u{00C3}\u{2019}" => 'Ò',
        "\u{00C2}\u{00A5}" => 'Ñ',
        "A\u{0081}" => 'Á',
        "Á\u{0081}" => 'Á',
        "I\u{008D}" => 'Í',
        "Í\u{008D}" => 'Í',
    ]);
    $value = preg_replace('/[\x{0080}-\x{009F}]/u', '', $value) ?? $value;
    return str_replace('Â', '', $value);
}

$satXml = file_get_contents($satCatalog);
if ($satXml === false) {
    throw new RuntimeException('No se pudo leer el catálogo SAT.');
}

// Source value => [SAT/internal code, name, symbol, decimal places, resolution].
$unitResolutions = [
    'PZA' => ['H87', 'Pieza', 'pza', 0, 'SAT'],
    'Pieza' => ['H87', 'Pieza', 'pza', 0, 'SAT'],
    'Set' => ['SET', 'Conjunto', 'set', 0, 'SAT'],
    'Juego' => ['SET', 'Conjunto', 'set', 0, 'SAT'],
    'Bolsa' => ['XBG', 'Bolsa', 'bolsa', 0, 'SAT'],
    'Par' => ['PR', 'Par', 'par', 0, 'SAT'],
    'PAR' => ['PR', 'Par', 'par', 0, 'SAT'],
    'Metro' => ['MTR', 'Metro', 'm', 3, 'SAT'],
    'MTS' => ['MTR', 'Metro', 'm', 3, 'SAT'],
    'CJA' => ['XBX', 'Caja', 'caja', 0, 'SAT'],
    'Caja' => ['XBX', 'Caja', 'caja', 0, 'SAT'],
    'Rollo' => ['XRO', 'Rollo', 'rollo', 0, 'SAT'],
    'kg' => ['KGM', 'Kilogramo', 'kg', 3, 'SAT'],
    'KGS' => ['KGM', 'Kilogramo', 'kg', 3, 'SAT'],
    'Litro' => ['LTR', 'Litro', 'L', 3, 'SAT'],
    'LTS' => ['LTR', 'Litro', 'L', 3, 'SAT'],
    'PQT' => ['XPK', 'Paquete', 'pqt', 0, 'SAT'],
    'Galón' => ['GLL', 'Galón estadounidense', 'gal', 3, 'SAT_ASSUMPTION_US_GALLON'],
    'GAL' => ['GLL', 'Galón estadounidense', 'gal', 3, 'SAT_ASSUMPTION_US_GALLON'],
    'MT3' => ['MTQ', 'Metro cúbico', 'm³', 3, 'SAT'],
    'Metro cuadrado' => ['MTK', 'Metro cuadrado', 'm²', 3, 'SAT'],
    'PIE' => ['FOT', 'Pie', 'ft', 3, 'SAT'],
    'Pie cúbico' => ['FTQ', 'Pie cúbico', 'ft³', 3, 'SAT'],
    'Pulgada' => ['INH', 'Pulgada', 'in', 3, 'SAT'],
    '' => ['H87', 'Pieza', 'pza', 0, 'DEFAULT_FOR_MISSING_SOURCE_UNIT'],
];

foreach ($unitResolutions as [$code]) {
    if (!str_contains($satXml, '<xs:enumeration value="' . $code . '"')) {
        throw new RuntimeException('La clave de unidad no existe en el XSD oficial SAT consultado: ' . $code);
    }
}

[$lineHandle, $lineIndexes] = openCsv($lineMapPath);
requireColumns([
    'linea_original', 'category_code_propuesto', 'category_name_propuesta',
    'brand_code_propuesto', 'brand_name_propuesta', 'tipo_detectado',
], $lineIndexes, $lineMapPath);

$lineMap = [];
$categories = [
    'CAT_POR_CLASIFICAR' => ['CAT_POR_CLASIFICAR', 'Por clasificar', 'Categoría operativa para registros cuya línea de origen no determina una categoría comercial.', '', 1],
];
$brands = [];
while (($row = readCsvRow($lineHandle, $lineIndexes)) !== null) {
    $line = $row['linea_original'];
    if (array_key_exists($line, $lineMap)) {
        throw new RuntimeException('Línea duplicada en el mapeo: ' . $line);
    }
    $categoryCode = trim($row['category_code_propuesto']);
    $categoryName = trim($row['category_name_propuesta']);
    $brandCode = trim($row['brand_code_propuesto']);
    $brandName = trim($row['brand_name_propuesta']);
    if ($categoryCode !== '') {
        $categories[$categoryCode] = [$categoryCode, $categoryName, '', '', 1];
    }
    if ($brandCode !== '') {
        $brands[$brandCode] = [$brandCode, $brandName, '', 1];
    }
    $lineMap[$line] = [
        'category_code' => $categoryCode !== '' ? $categoryCode : 'CAT_POR_CLASIFICAR',
        'brand_code' => $brandCode,
        'type' => $row['tipo_detectado'],
        'used_fallback' => $categoryCode === '',
    ];
}
fclose($lineHandle);

ksort($categories, SORT_STRING);
ksort($brands, SORT_STRING);

$categoryFile = createCsv($outputDirectory . '/categories.csv', ['code', 'name', 'description', 'parent_code', 'is_active']);
foreach ($categories as $category) {
    putCsv($categoryFile, $category);
}
closeFile($categoryFile);

$brandFile = createCsv($outputDirectory . '/brands.csv', ['code', 'name', 'description', 'is_active']);
foreach ($brands as $brand) {
    putCsv($brandFile, $brand);
}
closeFile($brandFile);

$uniqueUnits = [];
foreach ($unitResolutions as $resolution) {
    $uniqueUnits[$resolution[0]] = [$resolution[0], $resolution[1], $resolution[2], $resolution[3], 1];
}
ksort($uniqueUnits, SORT_STRING);
$unitFile = createCsv($outputDirectory . '/units.csv', ['code', 'name', 'symbol', 'decimal_places', 'is_active']);
foreach ($uniqueUnits as $unit) {
    putCsv($unitFile, $unit);
}
closeFile($unitFile);

[$catalogHandle, $catalogIndexes] = openCsv($catalogPath);
requireColumns([
    'numero_fila_original', 'clave_original', 'product_code_propuesto', 'product_name_original',
    'product_name_propuesto', 'linea_original', 'sku_propuesto', 'variant_name_propuesto',
    'unidad_original', 'ultima_compra_original', 'ultima_compra_normalizada', 'is_active',
    'clave_normalizada_candidata', 'sku_candidato', 'observaciones', 'observaciones_nombre',
], $catalogIndexes, $catalogPath);

$productsFile = createCsv($outputDirectory . '/products.csv', [
    'code', 'name', 'description', 'category_code', 'brand_code',
    'tracks_lots', 'tracks_expiration', 'tracks_serials', 'is_active',
]);
$variantsFile = createCsv($outputDirectory . '/product_variants.csv', [
    'product_code', 'unit_code', 'sku', 'name', 'is_active',
]);
$mappingFile = createCsv($outputDirectory . '/catalog_source_mappings.csv', [
    'source_row', 'source_code', 'product_code', 'sku', 'source_name', 'imported_name',
    'source_line', 'category_code', 'brand_code', 'source_unit', 'unit_code',
    'last_purchase_raw', 'last_purchase_date', 'is_active', 'resolution_notes',
]);
$followupFile = createCsv($outputDirectory . '/manual_followup.csv', [
    'source_row', 'source_code', 'product_code', 'issue', 'resolution_applied',
]);
$nameChangesFile = createCsv($outputDirectory . '/name_changes.csv', [
    'source_row', 'source_code', 'name_before_encoding_repair', 'name_after_encoding_repair', 'method',
]);

$seenProducts = [];
$seenSkus = [];
$stats = [
    'source_rows' => 0,
    'products' => 0,
    'variants' => 0,
    'active_products' => 0,
    'inactive_products' => 0,
    'fallback_category_products' => 0,
    'normalized_product_codes' => 0,
    'normalized_skus' => 0,
    'missing_source_keys_resolved' => 0,
    'missing_source_units_defaulted' => 0,
    'collision_keys_resolved' => 0,
    'names_with_pending_encoding_review' => 0,
    'names_repaired_conservatively' => 0,
];

while (($row = readCsvRow($catalogHandle, $catalogIndexes)) !== null) {
    $stats['source_rows']++;
    $sourceRow = (int) $row['numero_fila_original'];
    $sourceCode = $row['clave_original'];
    $trimmedSourceCode = trim($sourceCode);
    $productCode = trim($row['product_code_propuesto']);
    $sku = trim($row['sku_propuesto']);
    $resolutionNotes = [];

    if ($sourceRow === 8644 && strtolower($trimmedSourceCode) === 'prueba') {
        $productCode = 'LEGACY-8644-PRUEBA';
        $sku = 'LEGACY-8644-PRUEBA';
        $stats['collision_keys_resolved']++;
        $resolutionNotes[] = 'Colisión PRUEBA resuelta con identificador legacy estable; registro permanece inactivo.';
        putCsv($followupFile, [$sourceRow, $sourceCode, $productCode, 'COLISION_PRUEBA', 'LEGACY-8644-PRUEBA']);
    } elseif ($sourceRow === 21214 && $trimmedSourceCode === 'PRUEBA') {
        $productCode = 'PRUEBA';
        $sku = 'PRUEBA';
        $stats['collision_keys_resolved']++;
        $resolutionNotes[] = 'Se conserva PRUEBA para el registro activo; colisión inactiva separada.';
        putCsv($followupFile, [$sourceRow, $sourceCode, $productCode, 'COLISION_PRUEBA', 'PRUEBA']);
    } elseif ($sourceCode === '') {
        $productCode = 'LEGACY-' . $sourceRow;
        $sku = $productCode;
        $stats['missing_source_keys_resolved']++;
        $resolutionNotes[] = 'Clave ausente resuelta con identificador legacy estable basado en la fila original.';
        putCsv($followupFile, [$sourceRow, $sourceCode, $productCode, 'CLAVE_ORIGINAL_VACIA', $productCode]);
    }

    if (!validProductCode($productCode)) {
        throw new RuntimeException('Código de producto final inválido en fila ' . $sourceRow . ': ' . $productCode);
    }
    if (!validSku($sku)) {
        throw new RuntimeException('SKU final inválido en fila ' . $sourceRow . ': ' . $sku);
    }
    if (isset($seenProducts[strtolower($productCode)])) {
        throw new RuntimeException('Código de producto final duplicado: ' . $productCode);
    }
    if (isset($seenSkus[strtolower($sku)])) {
        throw new RuntimeException('SKU final duplicado: ' . $sku);
    }
    $seenProducts[strtolower($productCode)] = true;
    $seenSkus[strtolower($sku)] = true;

    $line = $row['linea_original'];
    $lineResolution = $lineMap[$line] ?? throw new RuntimeException('Línea sin mapeo en fila ' . $sourceRow . ': ' . $line);
    if ($lineResolution['used_fallback']) {
        $stats['fallback_category_products']++;
        $resolutionNotes[] = 'Categoría operativa Por clasificar; Línea original conservada.';
    }

    $sourceUnit = trim($row['unidad_original']);
    $unit = $unitResolutions[$sourceUnit] ?? throw new RuntimeException('Unidad sin resolución: ' . $sourceUnit);
    if ($sourceUnit === '') {
        $stats['missing_source_units_defaulted']++;
        $resolutionNotes[] = 'Unidad original vacía; se asignó Pieza/H87 como valor operativo y queda en seguimiento.';
        putCsv($followupFile, [$sourceRow, $sourceCode, $productCode, 'UNIDAD_ORIGINAL_VACIA', 'H87']);
    }
    if ($unit[4] === 'SAT_ASSUMPTION_US_GALLON') {
        $resolutionNotes[] = 'Galón interpretado como galón estadounidense GLL; confirmar equivalencia comercial.';
    }

    $nameBeforeEncodingRepair = trim($row['product_name_propuesto']);
    $name = cleanLegacyName($nameBeforeEncodingRepair);
    if ($name !== $nameBeforeEncodingRepair) {
        $stats['names_repaired_conservatively']++;
        $resolutionNotes[] = 'Se repararon artefactos de codificación conocidos; original conservado en la trazabilidad.';
        putCsv($nameChangesFile, [$sourceRow, $sourceCode, $nameBeforeEncodingRepair, $name, 'REPARACION_CONSERVADORA_MOJIBAKE']);
    }
    if ($name === '' || textLength($name) > 200) {
        throw new RuntimeException('Nombre final inválido en fila ' . $sourceRow);
    }
    $variantName = trim($row['variant_name_propuesto']);
    if ($variantName === '') {
        $variantName = 'Presentación ' . $unit[1];
    }
    if (textLength($variantName) > 150) {
        throw new RuntimeException('Nombre de variante inválido en fila ' . $sourceRow);
    }

    $active = $row['is_active'] === '1' ? 1 : 0;
    $stats[$active === 1 ? 'active_products' : 'inactive_products']++;
    if ($trimmedSourceCode !== $productCode) {
        $stats['normalized_product_codes']++;
        $resolutionNotes[] = 'Código interno normalizado; clave original conservada.';
    }
    if ($trimmedSourceCode !== $sku) {
        $stats['normalized_skus']++;
    }
    if (str_contains($row['observaciones_nombre'], 'dañados')) {
        $stats['names_with_pending_encoding_review']++;
        $resolutionNotes[] = 'Nombre preservado con posible codificación legacy pendiente de revisión visual.';
    }

    putCsv($productsFile, [
        $productCode, $name, '', $lineResolution['category_code'], $lineResolution['brand_code'],
        0, 0, 0, $active,
    ]);
    putCsv($variantsFile, [$productCode, $unit[0], $sku, $variantName, $active]);
    putCsv($mappingFile, [
        $sourceRow, $sourceCode, $productCode, $sku, $row['product_name_original'], $name,
        $line, $lineResolution['category_code'], $lineResolution['brand_code'], $row['unidad_original'], $unit[0],
        $row['ultima_compra_original'], $row['ultima_compra_normalizada'], $active, implode(' ', $resolutionNotes),
    ]);
    $stats['products']++;
    $stats['variants']++;
}
fclose($catalogHandle);
closeFile($productsFile);
closeFile($variantsFile);
closeFile($mappingFile);
closeFile($followupFile);
closeFile($nameChangesFile);

if ($stats['source_rows'] !== 31028 || $stats['products'] !== $stats['source_rows'] || $stats['variants'] !== $stats['source_rows']) {
    throw new RuntimeException('El conteo final no coincide con las 31,028 filas esperadas.');
}
if ($stats['active_products'] !== 10135 || $stats['inactive_products'] !== 20893) {
    throw new RuntimeException('El conteo de actividad cambió respecto del archivo auditado.');
}

$generated = [];
foreach (glob($outputDirectory . '/*.csv') ?: [] as $path) {
    $generated[basename($path)] = [
        'bytes' => filesize($path),
        'sha256' => hash_file('sha256', $path),
    ];
}
ksort($generated, SORT_STRING);

$manifest = [
    'created_at' => date(DATE_ATOM),
    'purpose' => 'Exportación privada y trazable previa a importación; no implica escritura en MySQL.',
    'source_files' => [
        basename($originalWorkbookPath) => hash_file('sha256', $originalWorkbookPath),
        basename($reviewWorkbookPath) => hash_file('sha256', $reviewWorkbookPath),
        basename($catalogPath) => hash_file('sha256', $catalogPath),
        basename($lineMapPath) => hash_file('sha256', $lineMapPath),
        basename($unitMapPath) => hash_file('sha256', $unitMapPath),
    ],
    'sat_catalog' => [
        'source' => 'SAT, Catálogo de datos CFDI 4.0 (catCFDI.xsd), enlace oficial consultado el 2026-11-18',
        'sha256' => hash_file('sha256', $satCatalog),
        'validated_unit_codes' => array_values(array_keys($uniqueUnits)),
    ],
    'decisions' => [
        'Última compra con fecha => producto y variante activos; vacío => inactivos.',
        'Línea con categoría propuesta => categoría plana conservada; de otro modo => CAT_POR_CLASIFICAR.',
        'Marca solo se asigna cuando el mapeo revisado aporta una marca explícita.',
        'Las cinco unidades vacías usan H87/Pieza como valor operativo y permanecen en manual_followup.csv.',
        'Galón se interpreta como GLL/galón estadounidense y permanece señalado para confirmación comercial.',
        'Los nombres conservan el original y solo reparan patrones de mojibake comprobables; cada cambio consta en name_changes.csv.',
        'No se generan precios, existencias, compras, facturas, impuestos ni movimientos.',
    ],
    'stats' => $stats + [
        'categories' => count($categories),
        'brands' => count($brands),
        'units' => count($uniqueUnits),
    ],
    'generated_files' => $generated,
];
$manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
$manifestPath = $outputDirectory . '/manifest.json';
if (file_put_contents($manifestPath, $manifestJson, LOCK_EX) !== strlen($manifestJson)) {
    throw new RuntimeException('No se pudo escribir el manifiesto.');
}
chmod($manifestPath, 0600);

echo json_encode([
    'status' => 'ok',
    'output_directory' => $outputDirectory,
    'manifest_sha256' => hash_file('sha256', $manifestPath),
    'stats' => $manifest['stats'],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
