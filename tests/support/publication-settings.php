<?php
declare(strict_types=1);
require_once __DIR__.'/admin-navigation.php';
function publicationFixtureData(string $case):array
{
    $data=['csrf'=>'publication-fixture-only','siteEnabled'=>false,'intakeEnabled'=>false,'privacyPublished'=>false,'canViewContent'=>true,'canManageLegal'=>true,'canCreateBranch'=>true,'canViewBranches'=>true,'branches'=>[]];
    if($case==='branches')$data=array_replace($data,['siteEnabled'=>true,'intakeEnabled'=>true,'privacyPublished'=>true,'branches'=>[
        ['id'=>21,'name'=>'Sucursal de prueba Norte','intake_enabled'=>1],['id'=>22,'name'=>'Sucursal de prueba Sur','intake_enabled'=>0],
    ]]);
    if($case==='restricted')$data=array_replace($data,['intakeEnabled'=>true,'canViewContent'=>false,'canManageLegal'=>false,'canCreateBranch'=>false,'canViewBranches'=>false]);
    return $data;
}
function publicationFixtureHtml(string $case):string
{
    $data=navigationFixtureData('all','cms_settings');$data['content']=App\Core\View::partial('admin/cms/settings',publicationFixtureData($case));
    return App\Core\View::partial('layouts/admin',$data);
}
