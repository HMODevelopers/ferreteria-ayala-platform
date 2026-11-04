<?php
declare(strict_types=1);
use App\Core\Persistence\{Migration,PersistenceException};
$guard=static function(PDO $p):void{
 $id=$p->query("SELECT id FROM roles WHERE code='PAGE_ADMIN'")->fetchColumn();if(!$id)return;
 $s=$p->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id=?');$s->execute([$id]);if((int)$s->fetchColumn())throw new PersistenceException('M11 rollback refused: page role assigned to users.');
 $s=$p->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.code NOT IN('cms.view','cms.edit','cms.publish','cms.legal','cms.media')");$s->execute([$id]);if((int)$s->fetchColumn())throw new PersistenceException('M11 rollback refused: page role customized.');
};
return new Migration('20261103110600_create_page_admin_role','M11 dedicated page administrator without user assignments',static function(PDO $p):void{
 if($p->query("SELECT id FROM roles WHERE code='PAGE_ADMIN'")->fetchColumn())throw new PersistenceException('PAGE_ADMIN already exists; no role overwritten.');
 $p->exec("INSERT INTO roles(code,name,description,is_system) VALUES('PAGE_ADMIN','Administrador de la página','Contenido público, medios públicos y documentos legales. Sin acceso comercial ni usuarios.',0)");$id=(int)$p->lastInsertId();
 $s=$p->prepare("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE code IN('cms.view','cms.edit','cms.publish','cms.legal','cms.media')");$s->execute([$id]);
},static function(PDO $p)use($guard):void{$guard($p);$id=$p->query("SELECT id FROM roles WHERE code='PAGE_ADMIN'")->fetchColumn();if($id){$s=$p->prepare('DELETE FROM role_permissions WHERE role_id=?');$s->execute([$id]);$s=$p->prepare('DELETE FROM roles WHERE id=?');$s->execute([$id]);}},$guard);
