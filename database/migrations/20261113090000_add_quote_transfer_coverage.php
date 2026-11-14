<?php
declare(strict_types=1);

use App\Core\Persistence\{Migration,PersistenceException};

$empty=static function(PDO $pdo):void {
    foreach(['transfer_coverage_events','transfer_quote_coverage_items','transfer_quote_snapshots'] as $table) {
        if((int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()!==0) {
            throw new PersistenceException('Rollback refused: quote transfer coverage history exists.');
        }
    }
};

return new Migration(
    '20261113090000_add_quote_transfer_coverage',
    'Add immutable quote snapshots and item-level transfer coverage',
    static function(PDO $pdo):void {
        $pdo->exec(<<<'SQL'
ALTER TABLE transfer_items
 ADD source_released_quantity DECIMAL(18,6) NOT NULL DEFAULT 0 AFTER approved_quantity,
 ADD CONSTRAINT transfer_source_released_check CHECK(source_released_quantity>=0 AND source_released_quantity<=approved_quantity-dispatched_quantity);

CREATE TABLE transfer_quote_snapshots (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 transfer_id BIGINT UNSIGNED NOT NULL,
 quote_version_id BIGINT UNSIGNED NOT NULL,
 content_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 snapshot_json LONGTEXT NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY transfer_quote_snapshot_transfer_unique(transfer_id),
 FOREIGN KEY(transfer_id) REFERENCES transfers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(quote_version_id) REFERENCES quote_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(JSON_VALID(snapshot_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_quote_coverage_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 snapshot_id BIGINT UNSIGNED NOT NULL,
 transfer_item_id BIGINT UNSIGNED NOT NULL,
 variant_id BIGINT UNSIGNED NOT NULL,
 unit_id BIGINT UNSIGNED NOT NULL,
 quoted_quantity DECIMAL(18,6) NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
 invalidated_at DATETIME(6) NULL,
 invalidated_by BIGINT UNSIGNED NULL,
 invalidation_reason VARCHAR(500) NULL,
 UNIQUE KEY transfer_quote_coverage_item_unique(transfer_item_id),
 INDEX transfer_quote_coverage_snapshot_idx(snapshot_id,status,id),
 FOREIGN KEY(snapshot_id) REFERENCES transfer_quote_snapshots(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(transfer_item_id) REFERENCES transfer_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(invalidated_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(quoted_quantity>0),
 CHECK(status IN('active','invalidated')),
 CHECK((status='active' AND invalidated_at IS NULL AND invalidated_by IS NULL AND invalidation_reason IS NULL) OR (status='invalidated' AND invalidated_at IS NOT NULL AND invalidated_by IS NOT NULL AND invalidation_reason IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_coverage_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 transfer_id BIGINT UNSIGNED NOT NULL,
 coverage_item_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 quantity DECIMAL(18,6) NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(transfer_id) REFERENCES transfers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(coverage_item_id) REFERENCES transfer_quote_coverage_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 INDEX transfer_coverage_event_history_idx(transfer_id,id),
 CHECK(action IN('linked','invalidated')),
 CHECK(quantity>=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $transfers=$pdo->query('SELECT id,quote_version_id,created_by FROM transfers WHERE quote_version_id IS NOT NULL ORDER BY id')->fetchAll();
        $snapshotInsert=$pdo->prepare('INSERT INTO transfer_quote_snapshots(transfer_id,quote_version_id,content_hash,snapshot_json,created_by) VALUES(?,?,?,?,?)');
        $coverageInsert=$pdo->prepare('INSERT INTO transfer_quote_coverage_items(snapshot_id,transfer_item_id,variant_id,unit_id,quoted_quantity) VALUES(?,?,?,?,?)');
        $eventInsert=$pdo->prepare("INSERT INTO transfer_coverage_events(transfer_id,coverage_item_id,action,quantity,actor_user_id,reason) VALUES(?,?,'linked',?,?,?)");
        $versionQuery=$pdo->prepare('SELECT * FROM quote_versions WHERE id=?');
        $quoteItemsQuery=$pdo->prepare('SELECT * FROM quote_items WHERE quote_version_id=? ORDER BY position,id');
        $transferItemsQuery=$pdo->prepare('SELECT * FROM transfer_items WHERE transfer_id=? ORDER BY variant_id,id');
        foreach($transfers as $transfer) {
            $versionQuery->execute([$transfer['quote_version_id']]);
            $version=$versionQuery->fetch();
            $quoteItemsQuery->execute([$transfer['quote_version_id']]);
            $quoteItems=$quoteItemsQuery->fetchAll();
            if(!is_array($version))throw new PersistenceException('Linked transfer has no quote version.');
            $hash=\App\Application\Services\QuoteManagementService::hash($version,$quoteItems);
            $snapshot=json_encode(['version'=>$version,'items'=>$quoteItems],JSON_THROW_ON_ERROR);
            $snapshotInsert->execute([$transfer['id'],$transfer['quote_version_id'],$hash,$snapshot,$transfer['created_by']]);
            $snapshotId=(int)$pdo->lastInsertId();
            $quoted=[];foreach($quoteItems as $item)$quoted[(int)$item['variant_id']]=$item;
            $transferItemsQuery->execute([$transfer['id']]);
            foreach($transferItemsQuery->fetchAll() as $item) {
                $quantity=(string)($quoted[(int)$item['variant_id']]['quantity']??$item['requested_quantity']);
                $coverageInsert->execute([$snapshotId,$item['id'],$item['variant_id'],$item['unit_id'],$quantity]);
                $coverageId=(int)$pdo->lastInsertId();
                $eventInsert->execute([$transfer['id'],$coverageId,$item['requested_quantity'],$transfer['created_by'],'Cobertura histórica vinculada al activar AY-04.']);
            }
        }
    },
    static function(PDO $pdo)use($empty):void {
        $empty($pdo);
        $pdo->exec('DROP TABLE transfer_coverage_events');
        $pdo->exec('DROP TABLE transfer_quote_coverage_items');
        $pdo->exec('DROP TABLE transfer_quote_snapshots');
        $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $pdo->exec('ALTER TABLE transfer_items '.(str_contains($version,'MariaDB')?'DROP CONSTRAINT':'DROP CHECK').' transfer_source_released_check, DROP COLUMN source_released_quantity');
    },
    $empty
);
