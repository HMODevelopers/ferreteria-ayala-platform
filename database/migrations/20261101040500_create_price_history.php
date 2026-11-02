<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$empty=static function(PDO $pdo):void{if((int)$pdo->query('SELECT COUNT(*) FROM price_history')->fetchColumn()!==0)throw new PersistenceException('Rollback refused because price_history contains immutable history.');};
return new Migration('20261101040500_create_price_history','Create immutable price history',static function(PDO $pdo):void{$pdo->exec(<<<'SQL'
CREATE TABLE price_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, price_id BIGINT UNSIGNED NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, previous_json LONGTEXT NULL, current_json LONGTEXT NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(id),
 CONSTRAINT price_history_price_fk FOREIGN KEY(price_id) REFERENCES branch_variant_prices(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT price_history_actor_fk FOREIGN KEY(actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT price_history_action_check CHECK(action IN('created','updated','activated','deactivated')),
 CONSTRAINT price_history_previous_json_check CHECK(previous_json IS NULL OR JSON_VALID(previous_json)),
 CONSTRAINT price_history_current_json_check CHECK(JSON_VALID(current_json)), INDEX price_history_price_created_idx(price_id,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);},static function(PDO $pdo)use($empty):void{$empty($pdo);$pdo->exec('DROP TABLE price_history');},$empty);
