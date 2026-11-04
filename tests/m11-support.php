<?php
declare(strict_types=1);
require_once __DIR__.'/m10-support.php';
use App\Core\Persistence\{MigrationDiscovery,Migrator};
use App\Infrastructure\Persistence\PdoCmsRepository;
function m11check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);$GLOBALS['m11_checks'][]=$message;}
function m11reject(callable $fn,string $message):void{try{$fn();}catch(InvalidArgumentException|App\Application\Exceptions\ApplicationException){m11check(true,$message);return;}throw new RuntimeException($message);}
function m11sourceHashes():array{
 $root=dirname(__DIR__);$files=[];foreach(['app','config','database','resources','tests','scripts'] as $dir)foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile()&&in_array($f->getExtension(),['php','js','mjs','css'],true)&&!str_contains(str_replace('\\','/',$f->getPathname()),'/tests/artifacts/')){$rel=str_replace('\\','/',substr($f->getPathname(),strlen($root)+1));$files[$rel]=hash_file('sha256',$f->getPathname());}foreach(['public/assets/css/app.css','public/assets/js/app.js','docker/Dockerfile','docker/uploads.ini','package.json'] as $rel)$files[$rel]=hash_file('sha256',$root.'/'.$rel);ksort($files);
 return $files;
}
function m11proof(string $suite):void{
 $files=m11sourceHashes();m11check(($GLOBALS['m11_start_hashes']??null)===$files,'Executed source hashes unchanged during suite');
 file_put_contents(__DIR__.'/artifacts/m11-'.$suite.'-pass.json',json_encode(['passed_at'=>time(),'files'=>$files,'checks'=>$GLOBALS['m11_checks']??[]],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
}
final class M11Fixture
{
 public M10Fixture $base;public App\Core\Persistence\Connection $cn;public PDO $pdo;public Migrator $migrator;public array $objects;public string $mediaDir;public array $files=[];
 public function start():void{
  $GLOBALS['m11_start_hashes']=m11sourceHashes();
  $this->base=new M10Fixture();$this->base->start();$this->cn=$this->base->cn;$this->pdo=$this->base->pdo;
  $this->migrator=new Migrator(new MigrationDiscovery(dirname(__DIR__).'/database/migrations'),$this->base->store,$this->pdo,'ayala_m11_migrations',10);
  m11check(count($this->migrator->migrate())===7,'Seven additive M11 migrations applied after exact 61-history baseline');
  $this->objects=m10inventory($this->pdo,$this->base->v['database']);m11check(count($this->objects)===93,'93 expected tables and no hidden objects');
  file_put_contents(__DIR__.'/artifacts/m11-owned-schema.json',json_encode(['objects'=>$this->objects,'history'=>$this->pdo->query('SELECT id FROM schema_migrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)],JSON_THROW_ON_ERROR));
  $this->mediaDir=__DIR__.'/artifacts/m11-media-'.bin2hex(random_bytes(8));mkdir($this->mediaDir,0700);
 }
 public function fixtures():array{
  $f=$this->base->fixtures();$p=$this->pdo;
  $p->prepare('UPDATE branches SET latitude=29.0,longitude=-110.0 WHERE id=?')->execute([$f['branch']]);
  $p->exec("INSERT INTO modules(code,name,is_globally_enabled,requires_branch_enablement) VALUES('M11','CMS de prueba aislada',1,0),('M08','Solicitudes de prueba aislada',1,1)");
  $p->prepare("INSERT INTO branch_modules(branch_id,module_id,is_enabled) SELECT ?,id,1 FROM modules WHERE code='M08'")->execute([$f['branch']]);
  $f['page_email']='m11-page-'.bin2hex(random_bytes(6)).'@example.test';$f['page_password']='M11-'.bin2hex(random_bytes(12)).'aA7';
  $p->prepare('INSERT INTO users(email,display_name,password_hash,is_active) VALUES(?,?,?,1)')->execute([$f['page_email'],'Editor de prueba M11',password_hash($f['page_password'],PASSWORD_DEFAULT)]);$f['page_actor']=(int)$p->lastInsertId();
  $p->prepare("INSERT INTO user_roles(user_id,role_id,branch_id) SELECT ?,id,NULL FROM roles WHERE code='PAGE_ADMIN'")->execute([$f['page_actor']]);
  $f['source']=$this->mediaDir.'/source.jpg';$im=imagecreatetruecolor(1600,800);imagefill($im,0,0,imagecolorallocate($im,245,130,32));imagejpeg($im,$f['source'],90);imagedestroy($im);
  $owned=$f;foreach(array_keys($owned) as $k)if(str_contains($k,'password'))unset($owned[$k]);file_put_contents(__DIR__.'/artifacts/m11-owned-fixture.json',json_encode($owned,JSON_THROW_ON_ERROR));
  return $f;
 }
 public function cleanup():void{
  m11check(m10inventory($this->pdo,$this->base->v['database'])===$this->objects,'M11 object inventory remains owned');m11check((int)$this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn()===68,'M11 history remains exact before cleanup');
  foreach(['legal_acceptances','public_quote_request_items','public_quote_requests','public_request_limits','delivery_evidence','supplier_invoice_files','product_images','cms_operations','page_revisions','quote_term_templates','legal_document_versions','legal_documents','navigation_items','site_settings','pages','reviews','events','promotions','banners','media_variants','media'] as $t)$this->pdo->exec('DELETE FROM '.$t);
  $this->pdo->exec("DELETE ur FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE r.code<>'GLOBAL_ADMIN'");
  $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code NOT IN('GLOBAL_ADMIN','PAGE_ADMIN')");
  $this->pdo->exec("DELETE FROM roles WHERE code NOT IN('GLOBAL_ADMIN','PAGE_ADMIN')");
  m11check(count($this->migrator->rollback())===7,'Seven M11 migrations reverse only after fixtures removed');$this->base->cleanup();
  if(is_dir($this->mediaDir.'/private')){foreach(glob($this->mediaDir.'/private/*')?:[] as $file){if(basename($file)!=='request-hmac.key'||!is_file($file))throw new RuntimeException('Unknown private test file; preserve directory');unlink($file);}if(!rmdir($this->mediaDir.'/private'))throw new RuntimeException('Private test directory not empty');}
  foreach(glob($this->mediaDir.'/*')?:[] as $file){if(!is_file($file)||!preg_match('/^(?:source\.jpg|evil\.jpg|[a-f0-9]{48}\.webp)$/D',basename($file)))throw new RuntimeException('Unknown test file or directory; preserve it');unlink($file);}if(!rmdir($this->mediaDir))throw new RuntimeException('Test image directory not empty');m11check(true,'Isolated schema and owned image files empty after PASS');
 }
 public function unlock():void{if(isset($this->base))$this->base->unlock();}
}
function m11service(M11Fixture $f):App\Application\Services\CmsManagementService{return new App\Application\Services\CmsManagementService(new PdoCmsRepository($f->cn),$f->cn);}
function m11media(M11Fixture $f):App\Application\Services\MediaManagementService{return new App\Application\Services\MediaManagementService(new PdoCmsRepository($f->cn),$f->cn,new App\Infrastructure\Media\ImageStorage($f->mediaDir));}
