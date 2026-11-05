<?php
require_once __DIR__.'/support/cms-design.php';
foreach(App\Domain\Cms\CmsRules::TYPES as $type=>$label){
 $html=App\Core\View::partial('admin/cms/index',cmsDesignData('empty',$type));
 check(!str_contains($html,'<form'),'CMS listing has no inline forms: '.$type);
 check(!str_contains($html,'M11')&&!str_contains($html,'CMS PÚBLICO'),'CMS copy omits internal identifiers: '.$type);
 check(str_contains($html,'data-admin-form-link')&&str_contains($html,'editor=1'),'CMS creation uses shared modal: '.$type);
}
$html=App\Core\View::partial('admin/cms/editor',cmsDesignData('error'));
check(str_contains($html,'Título conservado')&&str_contains($html,'role="alert"'),'Editor preserves values on validation error');
foreach(['_token','id','lock_version','operation_key','slug','title','branch_id','media_id','display_order','status','publish_at','expires_at','body','seo_title','seo_description'] as $name)check(str_contains($html,'name="'.$name.'"'),'Editor preserves existing field '.$name);
$html=App\Core\View::partial('admin/cms/index',cmsDesignData('restricted'));check(!str_contains($html,'editor=1')&&!str_contains($html,'/admin/media'),'CMS listing hides unauthorized create and media actions');
$html=App\Core\View::partial('admin/cms/editor',cmsDesignData('readonly'));check(!str_contains($html,'<form')&&str_contains($html,'Versiones anteriores'),'Read-only details have history without save controls');
$html=App\Core\View::partial('admin/cms/editor',cmsDesignData('draftonly'));check(!str_contains($html,'value="published"'),'Editor without publishing permission only offers draft');
$data=cmsDesignData('edit');$data['values']['title']='<script>alert(1)</script>';$data['revisions'][0]['snapshot_json']=json_encode(['title'=>'<img src=x onerror=alert(1)>','body'=>'<script>unsafe</script>']);$html=App\Core\View::partial('admin/cms/editor',$data);check(!str_contains($html,'<script>')&&!str_contains($html,'<img src=x'),'Editor and readable history escape dynamic text');

foreach(App\Domain\Cms\CmsRules::TYPES as $type=>$label){
 $html=App\Core\View::partial('admin/cms/editor',cmsDesignData('create',$type));
 check(str_contains($html,'data-cms-content-type="'.$type.'"'),'Editor identifies its specific content type: '.$type);
 foreach(App\Domain\Cms\CmsRules::FIELDS[$type] as $key=>$fieldLabel)check(str_contains($html,'name="fields['.$key.']"'),'Specific editor includes its field '.$type.'.'.$key);
 foreach(App\Domain\Cms\CmsRules::FIELDS as $other=>$otherFields)foreach($otherFields as $key=>$fieldLabel)if(!isset(App\Domain\Cms\CmsRules::FIELDS[$type][$key]))check(!str_contains($html,'name="fields['.$key.']"'),'Editor omits irrelevant field '.$type.'.'.$key);
 check(strpos($html,'cms-editor-primary')<strpos($html,'cms-editor-publication'),'Primary content precedes publication: '.$type);
}
$legal=['values'=>[],'error'=>null,'csrf'=>'legal-design-fixture-only','versions'=>[],'offset'=>0];
$html=App\Core\View::partial('admin/cms/legal',$legal);
check(str_contains($html,'legal-workspace')&&str_contains($html,'legal-empty'),'Legal documents have a dedicated workspace and empty state');
check(!str_contains($html,'M11'),'Legal documents omit internal module identifiers');
$legal['versions']=[['id'=>1,'code'=>'privacy','version_number'=>1,'title'=>'<script>unsafe</script>','body'=>'<img src=x onerror=alert(1)>','content_hash'=>'fixture-hash','created_at'=>'2026-11-04 12:00:00','published_at'=>null]];
$html=App\Core\View::partial('admin/cms/legal',$legal);
check(!str_contains($html,'<script>')&&!str_contains($html,'<img src=x'),'Legal version history escapes dynamic text');
