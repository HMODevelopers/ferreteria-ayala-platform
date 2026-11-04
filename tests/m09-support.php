<?php
declare(strict_types=1);
use App\Core\Persistence\{Connection,DatabaseConfig,IntegrationDatabaseEnvironment,IntegrationTestSafety,MigrationDiscovery,Migrator,MySqlMigrationStore,PersistenceException};
use App\Application\Services\{InitialAdministratorService,QuoteManagementService,QuoteReservationService,PricingManagementService};
use App\Infrastructure\Persistence\{PdoQuoteRepository,PdoCustomerRepository,PdoPricingRepository,PdoInventoryRepository,PdoAuditRepository};
function m09check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function m09inventory(PDO $p,string $db):array {$out=[];foreach([['TABLES','TABLE_NAME','TABLE_SCHEMA'],['TRIGGERS','TRIGGER_NAME','TRIGGER_SCHEMA'],['EVENTS','EVENT_NAME','EVENT_SCHEMA'],['ROUTINES','ROUTINE_NAME','ROUTINE_SCHEMA']]as[$t,$n,$s]){$q=$p->prepare("SELECT $n FROM information_schema.$t WHERE $s=?");$q->execute([$db]);foreach($q->fetchAll(PDO::FETCH_COLUMN)as $name)$out[]=$t.':'.$name;}sort($out);return $out;}
function m09connection():Connection {
    $v=IntegrationDatabaseEnvironment::forSuite('m09');IntegrationTestSafety::assertConfiguration($v['database'],$v['username']);
    if(!hash_equals($v['database'],(string)getenv('M09_TEST_AUTHORIZATION')))throw new PersistenceException('M09 test authorization mismatch.');
    if($v['database']===(string)getenv('DB_DATABASE')||$v['username']===(string)getenv('DB_USERNAME'))throw new PersistenceException('M09 cannot use development database or identity.');
    return new Connection(DatabaseConfig::fromArray(['connection'=>'mysql','host'=>$v['host'],'port'=>$v['port'],'database'=>$v['database'],'username'=>$v['username'],'password'=>$v['password'],'charset'=>$v['charset'],'connect_timeout'=>5,'migration_lock_timeout'=>10]));
}
function m09service(Connection $cn):QuoteManagementService {$repo=new PdoQuoteRepository($cn);$price=new PdoPricingRepository($cn);return new QuoteManagementService($repo,new PdoCustomerRepository($cn),$price,new PricingManagementService($price,$cn),new QuoteReservationService(new PdoInventoryRepository($cn),$repo),$cn);}
final class M09Fixture
{
    public Connection $cn;public PDO $pdo;public MySqlMigrationStore $store;public Migrator $migrator;public array $objects=[];public bool $locked=false;public bool $owned=false;public string $lock;public array $v;
    public function start():void {
        $this->v=IntegrationDatabaseEnvironment::forSuite('m09');$this->cn=m09connection();$this->pdo=$this->cn->pdo();$p=$this->pdo;$this->store=new MySqlMigrationStore($p);$this->lock='ayala_m09_isolated_integration';
        m09check($this->store->acquireLock($this->lock,10),'M09 integration already running.');$this->locked=true;
        $identity=$p->query('SELECT DATABASE() db,CURRENT_USER() account,VERSION() version')->fetch();IntegrationTestSafety::assertConnectionIdentity($this->v['database'],$this->v['username'],$identity['db'],$identity['account']);
        m09check((bool)preg_match('/^(?:(?:5\.5\.5-)?10\.11\.|8\.4\.)/',$identity['version']),'M09 requires MariaDB 10.11 or the authorized local MySQL 8.4.');
        $g="CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))";$s=$p->prepare('SELECT PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE='.$g.' AND TABLE_SCHEMA=?');$s->execute([$this->v['database']]);$schema=[];$grant=false;foreach($s as $r){$schema[]=$r['PRIVILEGE_TYPE'];$grant=$grant||$r['IS_GRANTABLE']==='YES';}$global=[];foreach($p->query('SELECT PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE='.$g)as $r){$global[]=$r['PRIVILEGE_TYPE'];$grant=$grant||$r['IS_GRANTABLE']==='YES';}if(str_starts_with($identity['version'],'8.4.')){m09check(in_array('REFERENCES',$schema,true),'MySQL 8.4 requires REFERENCES on the isolated schema.');$schema=array_values(array_diff($schema,['REFERENCES']));}IntegrationTestSafety::assertM03IsolationPrivileges($schema,$global,$grant);
        IntegrationTestSafety::assertDatabaseIsEmpty(m09inventory($p,$this->v['database']));$this->store->initialize();m09check($this->store->wasInitializedByCurrentInstance(),'Unproven schema storage ownership.');$this->owned=true;
        $this->migrator=new Migrator(new MigrationDiscovery(dirname(__DIR__).'/database/migrations','20261103090300_register_transfer_permissions'),$this->store,$p,'ayala_m09_migrations',10);m09check(count($this->migrator->migrate())===57,'Expected 53 previous and four M09 migrations.');$this->objects=m09inventory($p,$this->v['database']);
    }
    public function fixtures():array {
        $p=$this->pdo;$email='m09-'.bin2hex(random_bytes(6)).'@example.test';$password='M09-'.bin2hex(random_bytes(14)).'aA7';$actor=(new InitialAdministratorService($this->cn))->create($email,'Vendedor de integración',$password);
        $p->exec("INSERT INTO branches(code,name,address,is_active) VALUES('M09_A','Sucursal M09 A','Dirección aislada de prueba',1),('M09_B','Sucursal M09 B',NULL,1)");$branch=(int)$p->lastInsertId();$other=$branch+1;
        $p->exec("INSERT INTO units(code,name,symbol,decimal_places,is_active) VALUES('M09_UNIT','Unidad de prueba','u',3,1)");$unit=(int)$p->lastInsertId();
        $p->exec("INSERT INTO categories(code,name,is_active) VALUES('M09_CAT','Categoría de prueba',1)");$category=(int)$p->lastInsertId();
        $s=$p->prepare("INSERT INTO products(code,name,category_id,is_active) VALUES('M09_PROD','Material de integración',?,1)");$s->execute([$category]);$product=(int)$p->lastInsertId();
        $s=$p->prepare("INSERT INTO product_variants(product_id,sku,name,unit_id,is_active) VALUES(?,'M09-SKU-A','Variante A',?,1),(?,'M09-SKU-B','Variante B',?,1),(?,'M09-SKU-C','Sin precio',?,1)");$s->execute([$product,$unit,$product,$unit,$product,$unit]);$variant=(int)$p->lastInsertId();$second=$variant+1;$missing=$variant+2;
        $p->exec("INSERT INTO tax_categories(code,name,is_active) VALUES('M09_TAX','Impuesto de prueba aislada',1)");$tax=(int)$p->lastInsertId();$p->prepare("INSERT INTO tax_rates(tax_category_id,rate,valid_from,is_active) VALUES(?,16,'2020-01-01',1)")->execute([$tax]);
        $p->exec("INSERT INTO price_lists(code,name,is_public,is_active) VALUES('M09_PUBLIC','Lista pública de prueba',1,1),('M09_LIST','Lista comercial de prueba',0,1)");$list=(int)$p->lastInsertId();$commercial=$list+1;$p->prepare("INSERT INTO price_list_rules(price_list_id,discount_rate,valid_from,is_active) VALUES(?,10,'2020-01-01',1)")->execute([$commercial]);
        foreach([[$variant,'100',0],[$second,'116',1]]as[$v,$amount,$included])$p->prepare("INSERT INTO branch_variant_prices(branch_id,variant_id,price_list_id,tax_category_id,amount,tax_included,valid_from,is_active,created_by,updated_by) VALUES(?,?,?,?,?,?,'2020-01-01',1,?,?)")->execute([$branch,$v,$list,$tax,$amount,$included,$actor,$actor]);
        $p->prepare('INSERT INTO discount_policies(branch_id,seller_limit_rate,is_active) VALUES(?,5,1)')->execute([$branch]);
        $p->prepare("INSERT INTO customers(branch_id,price_list_id,code,customer_type,legal_name,is_active,created_by,updated_by) VALUES(?,?,'M09-CUSTOMER','business','Cliente histórico de integración',1,?,?)")->execute([$branch,$commercial,$actor,$actor]);$customer=(int)$p->lastInsertId();
        foreach([$variant,$second]as $v)$p->prepare('INSERT INTO inventory_balances(branch_id,variant_id,physical_quantity,reserved_quantity,available_quantity,initialized_at) VALUES(?,?,100,0,100,UTC_TIMESTAMP(6))')->execute([$branch,$v]);
        return compact('email','password','actor','branch','other','unit','category','product','variant','second','missing','tax','list','commercial','customer');
    }
    public function cleanup():void {
        m09check($this->owned&&$this->objects===m09inventory($this->pdo,$this->v['database']),'M09 object inventory changed; preserve for inspection.');
        m09check((int)$this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn()===57,'M09 history changed; preserve for inspection.');
        $tables=['transfer_event_items','transfer_events','transfer_items','transfers','inventory_reservation_items','inventory_reservations','quote_approvals','quote_assignments','quote_status_history','quote_items','quote_versions','quote_contacts','quotes','customer_addresses','customer_contacts','customers','goods_receipt_items','goods_receipts','supplier_invoice_items','supplier_invoices','supplier_contacts','suppliers','stock_count_items','stock_counts','stock_thresholds','inventory_movements','inventory_balances','inventory_serials','inventory_lots','price_history','branch_variant_prices','discount_policies','price_list_rules','tax_rates','product_barcodes','variant_attribute_values','unit_conversions','product_variants','products','category_attributes','attribute_options','attribute_definitions','brands','units','categories','price_lists','tax_categories','audit_logs','user_sessions','user_roles','user_branches','role_permissions','users','roles','permissions','branch_services','services','branch_special_hours','branch_hours','branch_modules','modules','branches'];
        foreach($tables as $table)$this->pdo->exec('DELETE FROM '.$table);
        m09check(count($this->migrator->rollback())===57,'M09 rollback incomplete.');$this->pdo->exec('DROP TABLE schema_migrations');IntegrationTestSafety::assertDatabaseIsEmpty(m09inventory($this->pdo,$this->v['database']));
    }
    public function unlock():void {if($this->locked)m09check($this->store->releaseLock($this->lock),'M09 lock release not confirmed.');$this->locked=false;}
}
function m09input(array $f):array {return ['branch_id'=>(string)$f['branch'],'customer_id'=>(string)$f['customer'],'price_list_id'=>(string)$f['commercial'],'valid_until'=>date('Y-m-d',strtotime('+5 days')),'terms'=>'Condiciones acordadas para la integración aislada.','delivery_terms'=>'Recolección confirmada en sucursal.','delivery_amount'=>'0.00','notes'=>'','operation_key'=>bin2hex(random_bytes(20))];}

function m09proof(string $suite):void {
    $root=dirname(__DIR__);$files=[];
    foreach(['app','bin','bootstrap','config','database','resources','tests']as $dir){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS));foreach($it as $f)if($f->isFile()&&in_array($f->getExtension(),['php','js','css','mjs'],true)){$relative=str_replace('\\','/',substr($f->getPathname(),strlen($root)+1));$files[$relative]=hash_file('sha256',$f->getPathname());}}
    ksort($files);file_put_contents($root.'/tests/artifacts/m09-'.$suite.'-pass.json',json_encode(['passed_at'=>time(),'files'=>$files],JSON_THROW_ON_ERROR));
}

function m09transferService(Connection $cn):App\Application\Services\TransferManagementService {$repo=new App\Infrastructure\Persistence\PdoTransferRepository($cn);$stock=new PdoInventoryRepository($cn);return new App\Application\Services\TransferManagementService($repo,$stock,new App\Application\Services\InventoryManagementService($stock,$cn),$cn);}
