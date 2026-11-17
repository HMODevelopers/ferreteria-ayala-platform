<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(2);
$cliEnvironment = 'testing';
require dirname(__DIR__).'/bootstrap/cli.php';
require dirname(__DIR__).'/bootstrap/app.php';
require __DIR__.'/m10-support.php';

use App\Application\Exceptions\OperationConflict;
use App\Application\Services\{CatalogImportService, InventoryManagementService};
use App\Infrastructure\Documents\{XlsxReader, XlsxWriter};
use App\Infrastructure\Persistence\{PdoAuditRepository, PdoInventoryRepository};

function ayCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    $GLOBALS['ay_checks'][] = $message;
}

function ayReject(callable $operation, string $message, string $class = Throwable::class): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        ayCheck($error instanceof $class, $message.' (unexpected '.get_class($error).')');
        return;
    }
    throw new RuntimeException($message);
}

function ayRows(CatalogImportService $service, string $method, string $path): array
{
    $reflection = new ReflectionMethod($service, $method);
    $reflection->setAccessible(true);
    return $reflection->invoke($service, $path);
}

function ayAnalyze(CatalogImportService $service, array $rows): array
{
    $reflection = new ReflectionMethod($service, 'analyze');
    $reflection->setAccessible(true);
    return $reflection->invoke($service, $rows);
}

function ayPreview(PDO $pdo, string $directory, int $actor, string $source, array $rows, array $analysis): string
{
    $token = bin2hex(random_bytes(24));
    $hash = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    $record = ['token'=>$token,'actor'=>$actor,'expires'=>time()+1800,'status'=>'preview','rows'=>$rows,'hash'=>$hash];
    file_put_contents($directory.'/'.$token.'.json', json_encode($record, JSON_THROW_ON_ERROR), LOCK_EX);
    chmod($directory.'/'.$token.'.json', 0600);
    $statement = $pdo->prepare("INSERT INTO catalog_import_runs(token,actor_user_id,source_name,content_hash,status,total_rows,accepted_rows,rejected_rows) VALUES(?,?,?,?,'preview',?,?,?)");
    $statement->execute([$token,$actor,$source,$hash,$analysis['total'],$analysis['accepted'],$analysis['rejected']]);
    $run = (int) $pdo->lastInsertId();
    $error = $pdo->prepare('INSERT INTO catalog_import_row_errors(import_run_id,source_row_number,error_message) VALUES(?,?,?)');
    foreach ($analysis['rows'] as $row) foreach ($row['errors'] as $message) $error->execute([$run,$row['line'],$message]);
    return $token;
}

$fixture = new M10Fixture('20261116100000_complete_catalog_imports', 78);
$stage = 'preflight';
$exit = 1;
$directory = sys_get_temp_dir().'/ayala-ay08-ay09-'.bin2hex(random_bytes(8));

try {
    $fixture->start();
    $pdo = $fixture->pdo;
    $migrationIds = $pdo->query('SELECT id FROM schema_migrations ORDER BY id DESC LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    ayCheck($migrationIds === ['20261116100000_complete_catalog_imports','20261116090000_complete_inventory_tracking'], 'Migrations 77 and 78 applied in order.');
    $columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='catalog_import_row_errors'")->fetchAll(PDO::FETCH_COLUMN);
    ayCheck(in_array('source_row_number', $columns, true) && !in_array('row_number', $columns, true), 'Migration 78 uses a non-reserved source row identifier.');
    $data = $fixture->fixtures();

    $stage = 'AY-08 serial creation and transitions';
    $pdo->prepare('UPDATE products SET tracks_serials=1 WHERE id=?')->execute([$data['product']]);
    $repository = new PdoInventoryRepository($fixture->cn);
    $inventory = new InventoryManagementService($repository, $fixture->cn);
    $audit = new PdoAuditRepository($fixture->cn);
    $serial = 'AY08-SERIAL-'.bin2hex(random_bytes(6));
    $incoming = ['branch_id'=>$data['branch'],'variant_id'=>$data['variant'],'unit_id'=>$data['unit'],'quantity'=>'1','type'=>'adjustment_in','reason'=>'Alta de serie AY-08','operation_key'=>bin2hex(random_bytes(20)),'serial_numbers'=>[$serial]];
    $movement = $inventory->record($incoming, $data['actor'], static function(array $movement) use ($audit, $data): void {
        $audit->record($data['actor'], 'inventory.serial.create', 'inventory_movement', (int)$movement['id'], $data['branch'], 'success');
    });
    $stored = $repository->findSerial($serial);
    ayCheck($stored !== null && $stored['status'] === 'available', 'A new serial is inserted directly as available.');
    ayCheck((int)$pdo->query('SELECT COUNT(*) FROM inventory_tracking_movements WHERE movement_id='.(int)$movement['id'])->fetchColumn() === 1, 'The new serial is linked to its inventory movement.');
    ayCheck((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='inventory.serial.create'")->fetchColumn() === 1, 'Serial creation audit is committed once.');
    $replayed = $inventory->record($incoming, $data['actor'], static function(): void { throw new RuntimeException('Replay must not audit twice.'); });
    ayCheck((int)$replayed['id'] === (int)$movement['id'] && (int)$pdo->query('SELECT COUNT(*) FROM inventory_serials WHERE serial_number='.$pdo->quote($serial))->fetchColumn() === 1, 'Serial creation replay is idempotent and does not duplicate data.');

    $outgoing = ['branch_id'=>$data['branch'],'variant_id'=>$data['variant'],'unit_id'=>$data['unit'],'quantity'=>'1','type'=>'adjustment_out','reason'=>'Salida de serie AY-08','operation_key'=>bin2hex(random_bytes(20)),'serial_numbers'=>[$serial]];
    $inventory->record($outgoing, $data['actor']);
    ayCheck($repository->findSerial($serial)['status'] === 'issued', 'An available serial transitions to issued on output.');
    $return = $incoming;
    $return['reason'] = 'Reingreso válido de serie AY-08';
    $return['operation_key'] = bin2hex(random_bytes(20));
    $inventory->record($return, $data['actor']);
    ayCheck($repository->findSerial($serial)['status'] === 'available', 'An issued serial transitions back to available on a valid input.');

    $duplicate = $return;
    $duplicate['operation_key'] = bin2hex(random_bytes(20));
    ayReject(static fn() => $inventory->record($duplicate, $data['actor']), 'An already available serial cannot be silently re-entered.', OperationConflict::class);
    $missing = $outgoing;
    $missing['operation_key'] = bin2hex(random_bytes(20));
    $missing['serial_numbers'] = ['AY08-MISSING-'.bin2hex(random_bytes(6))];
    ayReject(static fn() => $inventory->record($missing, $data['actor']), 'An unknown serial cannot be silently issued.', OperationConflict::class);
    ayReject(static fn() => $repository->updateSerialStatus((int)$stored['id'], 'available'), 'A redundant status update remains rejected.', RuntimeException::class);
    ayReject(static fn() => $repository->updateSerialStatus(PHP_INT_MAX, 'issued'), 'A status update for a missing serial remains rejected.', RuntimeException::class);

    $stage = 'AY-09 CSV and XLSX catalog imports';
    mkdir($directory, 0700, true);
    $imports = new CatalogImportService($fixture->cn, $directory);
    $csv = $directory.'/valid.csv';
    file_put_contents($csv, $imports->template()
        ."AY09_NEW,Producto CSV AY-09,Alta CSV,M10_CAT,,AY09-CSV,Variante CSV,M10_UNIT\r\n"
        ."M10_PROD,Producto actualizado AY-09,Actualización por SKU,M10_CAT,,M10-SKU-A,Variante actualizada AY-09,M10_UNIT\r\n");
    $csvRows = ayRows($imports, 'csvRows', $csv);
    $csvAnalysis = ayAnalyze($imports, $csvRows);
    ayCheck($csvAnalysis['accepted'] === 2 && $csvAnalysis['rejected'] === 0, 'Valid CSV rows are accepted for create and SKU update.');
    $csvToken = ayPreview($pdo, $directory, $data['actor'], 'valid.csv', $csvRows, $csvAnalysis);
    $csvResult = $imports->confirm($csvToken, $data['actor']);
    ayCheck($csvResult === ['created'=>1,'created_variants'=>1,'updated'=>1,'replayed'=>false], 'CSV confirmation creates and updates the expected rows.');
    ayCheck($pdo->query("SELECT name FROM product_variants WHERE sku='M10-SKU-A'")->fetchColumn() === 'Variante actualizada AY-09', 'Existing catalog data is updated by SKU.');
    ayCheck($imports->confirm($csvToken, $data['actor']) === ['created'=>1,'created_variants'=>1,'updated'=>1,'replayed'=>true], 'CSV confirmation replay is durable and idempotent.');

    $xlsx = $directory.'/valid.xlsx';
    file_put_contents($xlsx, (new XlsxWriter())->render(CatalogImportService::HEADERS, [['AY09_NEW','Producto CSV AY-09','Alta CSV','M10_CAT','','AY09-XLSX','Variante XLSX','M10_UNIT']], 'Importación'));
    $xlsxRows = ayRows($imports, 'xlsxRows', $xlsx);
    ayCheck((new XlsxReader())->rows($xlsx)[1][5] === 'AY09-XLSX', 'The generated XLSX is read through the bounded worksheet reader.');
    $xlsxAnalysis = ayAnalyze($imports, $xlsxRows);
    $xlsxToken = ayPreview($pdo, $directory, $data['actor'], 'valid.xlsx', $xlsxRows, $xlsxAnalysis);
    ayCheck($imports->confirm($xlsxToken, $data['actor']) === ['created'=>0,'created_variants'=>1,'updated'=>0,'replayed'=>false], 'XLSX confirmation adds a variant to an existing imported product.');

    $invalid = $directory.'/invalid.csv';
    file_put_contents($invalid, $imports->template()."AY09_BAD,Producto inválido,,NO_EXISTE,,AY09-BAD,Variante inválida,M10_UNIT\r\n");
    $invalidRows = ayRows($imports, 'csvRows', $invalid);
    $invalidAnalysis = ayAnalyze($imports, $invalidRows);
    $invalidToken = ayPreview($pdo, $directory, $data['actor'], 'invalid.csv', $invalidRows, $invalidAnalysis);
    ayCheck($invalidAnalysis['rejected'] === 1 && (int)$pdo->query("SELECT source_row_number FROM catalog_import_row_errors e JOIN catalog_import_runs r ON r.id=e.import_run_id WHERE r.token=".$pdo->quote($invalidToken))->fetchColumn() === 2, 'Rejected CSV errors retain their source row number.');
    ayReject(static fn() => $imports->confirm($invalidToken, $data['actor']), 'A rejected import cannot be confirmed.', InvalidArgumentException::class);
    ayCheck((int)$pdo->query("SELECT COUNT(*) FROM products WHERE code='AY09_BAD'")->fetchColumn() === 0, 'Rejected import rows do not mutate the catalog.');

    $rollbackRows = [['line'=>2,'data'=>['product_code'=>'AY09_ROLLBACK','product_name'=>'Producto rollback','description'=>'Rollback AY-09','category_code'=>'M10_CAT','brand_code'=>'','sku'=>'AY09-ROLLBACK','variant_name'=>'Variante rollback','unit_code'=>'M10_UNIT'],'errors'=>[]]];
    $rollbackAnalysis = ayAnalyze($imports, $rollbackRows);
    $rollbackToken = ayPreview($pdo, $directory, $data['actor'], 'rollback.xlsx', $rollbackRows, $rollbackAnalysis);
    ayReject(static fn() => $imports->confirm($rollbackToken, $data['actor'], static function(array $result) use ($audit, $data, $rollbackToken): void {
        $audit->record($data['actor'], 'catalog.import.rollback', 'catalog_import', $rollbackToken, $data['branch'], 'success', context:['changed_fields'=>['created'=>$result['created']]]);
        throw new RuntimeException('Controlled AY-09 audit failure.');
    }), 'Catalog changes and audit roll back atomically when confirmation fails.', RuntimeException::class);
    ayCheck((int)$pdo->query("SELECT COUNT(*) FROM products WHERE code='AY09_ROLLBACK'")->fetchColumn() === 0
        && $pdo->query('SELECT status FROM catalog_import_runs WHERE token='.$pdo->quote($rollbackToken))->fetchColumn() === 'preview'
        && (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='catalog.import.rollback'")->fetchColumn() === 0, 'Failed confirmation leaves no catalog or audit side effects.');

    $stage = 'isolated cleanup';
    $fixture->cleanup();
    ayCheck(m10inventory($pdo, $fixture->v['database']) === [], 'The isolated database contains zero objects after cleanup.');
    foreach (glob($directory.'/*') ?: [] as $file) if (is_file($file)) unlink($file);
    rmdir($directory);
    echo 'PASS: '.count($GLOBALS['ay_checks'])." focused SQL checks for migrations 77-78, AY-08 and AY-09 with isolated cleanup.\n";
    $exit = 0;
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR AY-08/AY-09 ['.$stage.'] '.get_class($error).': '.($error instanceof PDOException ? 'SQLSTATE '.$error->getCode().' driver '.($error->errorInfo[1] ?? 0) : $error->getMessage()).' line '.$error->getLine()."\nState preserved for inspection.\n");
} finally {
    try { $fixture->unlock(); } catch (Throwable) { $exit = 1; }
    if ($exit === 0 && is_dir($directory)) {
        foreach (glob($directory.'/*') ?: [] as $file) if (is_file($file)) unlink($file);
        @rmdir($directory);
    }
}
exit($exit);
