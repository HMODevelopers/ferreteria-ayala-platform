<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$codes=['cms.configure','cms.view','cms.edit','cms.publish','cms.legal','cms.media','cms.terms','media.products','media.invoices','media.evidence.view','media.evidence.create','quote_requests.view','quote_requests.assign'];
$guard=static function(PDO $p)use($codes):void{
 $q=implode(',',array_fill(0,count($codes),'?'));
 $s=$p->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id JOIN roles r ON r.id=rp.role_id WHERE p.code IN ($q) AND r.code NOT IN('GLOBAL_ADMIN','PAGE_ADMIN')");$s->execute($codes);
 if((int)$s->fetchColumn()>0)throw new PersistenceException('M11 rollback refused: delegated permissions.');
};
return new Migration('20261103110500_register_cms_permissions','M11 CMS media and public intake permissions',static function(PDO $p)use($codes):void{
 foreach(App\Application\Security\PermissionCatalog::ITEMS as [$code,$resource,$action,$scope,$description])if(in_array($code,$codes,true)){
  $s=$p->prepare('INSERT INTO permissions(code,resource,action,scope,description) VALUES(?,?,?,?,?)');$s->execute([$code,$resource,$action,$scope,$description]);
  $s=$p->prepare("INSERT INTO role_permissions(role_id,permission_id) SELECT id,? FROM roles WHERE code='GLOBAL_ADMIN'");$s->execute([(int)$p->lastInsertId()]);
 }
},static function(PDO $p)use($codes,$guard):void{$guard($p);$q=implode(',',array_fill(0,count($codes),'?'));$s=$p->prepare("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.code IN ($q)");$s->execute($codes);$s=$p->prepare("DELETE FROM permissions WHERE code IN ($q)");$s->execute($codes);},$guard);
