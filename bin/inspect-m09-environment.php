<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';
$p=App\Core\AppKernel::connection()->pdo();$identity=$p->query('SELECT DATABASE() db,VERSION() engine,@@hostname server')->fetch();
if(getenv('APP_ENV')!=='development'||getenv('DB_HOST')!=='mysql-local')throw new RuntimeException('Unexpected environment.');
$account=(string)$p->query('SELECT CURRENT_USER()')->fetchColumn();$identity['identity_matches_configured_user']=strtok($account,'@')===getenv('DB_USERNAME');if(!$identity['identity_matches_configured_user'])throw new RuntimeException('Unexpected effective identity.');$identity['environment']=getenv('APP_ENV');$identity['history']=(int)$p->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();$identity['test_database']=getenv('TEST_DB_DATABASE_M08');
foreach(['branches','product_variants','customers','quotes','inventory_balances','inventory_movements']as $t)$identity[$t]=(int)$p->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();echo json_encode($identity,JSON_PRETTY_PRINT),PHP_EOL;
