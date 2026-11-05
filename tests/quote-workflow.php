<?php
use App\Domain\Quotes\QuoteWorkflowRules as W;
$input=['origin'=>'email','branch_id'=>'1','full_name'=>'Persona de prueba','email'=>'persona@example.test','phone'=>'','notes'=>''];
check(W::intake($input,['Material solicitado'],['2'])['origin']==='email','Workflow supports email intake');
foreach(['whatsapp','phone','branch'] as $origin)check(W::intake(array_replace($input,['origin'=>$origin,'phone'=>'+52 555 000 1234']),['Material'],['1'])['origin']===$origin,'Workflow captures channel '.$origin);
foreach([fn()=>W::intake(array_replace($input,['origin'=>'web']),['M'],['1']),fn()=>W::intake(array_replace($input,['email'=>'','phone'=>'']),['M'],['1']),fn()=>W::intake($input,[],[]),fn()=>W::intake($input,['M'],['0']),fn()=>W::intake($input,['M'],['-2']),fn()=>W::intake($input,['M'],['1','2']),fn()=>W::channel('email','wrong'),fn()=>W::channel('sms','123')] as $reject){$ok=false;try{$reject();}catch(InvalidArgumentException){$ok=true;}check($ok,'Workflow rejects incomplete or unsupported input');}
check(W::channel('whatsapp','+52 555 000 1234')[0]==='whatsapp','Workflow validates actual contact channel');
check(W::STAGES['received']==='Recibida'&&W::STAGES['waiting_customer']==='Pendiente de respuesta','Workflow distinguishes receipt from verification and delivery');

require_once __DIR__.'/support/quote-workflow.php';
$html=App\Core\View::partial('admin/quotes/inbox',workflowFixtureData('populated'));check(!str_contains($html,'M08')&&!str_contains($html,'M11'),'Unified inbox has user-facing copy');foreach(['origin','stage','assigned_user_id','branch_id'] as $field)check(str_contains($html,'name="'.$field.'"'),'Inbox supports filter '.$field);check(substr_count($html,'class="quote-inbox-row"')===5,'All channels appear in same inbox');
$html=App\Core\View::partial('admin/quotes/intake',workflowFixtureData('error'));check(str_contains($html,'Nombre conservado')&&str_contains($html,'Material conservado'),'Intake preserves values after errors');check(str_contains($html,'data-quote-workflow-form'),'Intake uses shared modal submission');check(!str_contains($html,'<option value="web"'),'Staff cannot impersonate public-web origin');
$html=App\Core\View::partial('admin/quotes/inbox',workflowFixtureData('restricted'));check(!str_contains($html,'/admin/quotes/intake'),'Intake action respects capability');

check(W::composition([['variant_id'=>1,'quantity'=>'1'],['variant_id'=>1,'quantity'=>'1.000000']])===W::composition([['variant_id'=>1,'quantity'=>'2']]),'Composition comparison normalizes and aggregates quantities');

check(W::intake(array_replace($input,['origin'=>'branch','email'=>'','phone'=>'']),['Material'],['1'])['origin']==='branch','Presential intake does not require invented contact information');
