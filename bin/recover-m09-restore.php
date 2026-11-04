<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--complete-and-clean')exit(2);require dirname(__DIR__).'/bootstrap/cli.php';require dirname(__DIR__).'/bootstrap/app.php';
if(getenv('APP_ENV')!=='development'||getenv('DB_HOST')!=='mysql-local')throw new RuntimeException('Unexpected development environment');$root=dirname(__DIR__);$files=glob($root.'/storage/backups/m09-pre-migration-*.json');sort($files);$file=end($files);$snapshot=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);if($snapshot['database']!==getenv('DB_DATABASE'))throw new RuntimeException('Backup identity mismatch');$restoreResume=true;require $root.'/tests/m09-restore-backup.php';echo "PASS: owned backup restoration completed, all rows verified and isolated database empty.\n";
