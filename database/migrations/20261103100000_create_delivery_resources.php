<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$tables=['vehicles','drivers','delivery_runs'];
$empty=static function(PDO $p)use($tables):void{foreach($tables as $t)if((int)$p->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==0)throw new PersistenceException('Rollback refused: M10 history exists.');};
return new Migration('20261103100000_create_delivery_resources','M10 create delivery resources',static function(PDO $p):void{$p->exec(<<<'SQL'

CREATE TABLE vehicles (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,branch_id BIGINT UNSIGNED NOT NULL,code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,is_available TINYINT UNSIGNED NOT NULL DEFAULT 1,lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT,CHECK(is_active IN(0,1)),CHECK(is_available IN(0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE drivers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,branch_id BIGINT UNSIGNED NOT NULL,code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,is_available TINYINT UNSIGNED NOT NULL DEFAULT 1,lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT,CHECK(is_active IN(0,1)),CHECK(is_available IN(0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE delivery_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,branch_id BIGINT UNSIGNED NOT NULL,vehicle_id BIGINT UNSIGNED NOT NULL,driver_id BIGINT UNSIGNED NOT NULL,scheduled_date DATE NOT NULL,name VARCHAR(120) NOT NULL,created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(driver_id) REFERENCES drivers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,INDEX delivery_run_schedule(branch_id,scheduled_date,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $p)use($tables,$empty):void{$empty($p);foreach(array_reverse($tables)as $t)$p->exec('DROP TABLE '.$t);},$empty);
