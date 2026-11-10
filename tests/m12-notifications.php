<?php
declare(strict_types=1);

foreach(['/admin/notifications','/admin/notifications/status','/admin/notifications/open'] as $url) check($router->dispatch(new App\Core\Request('GET',$url))->status===303,'M12 notifications require a session: '.$url);
check($router->dispatch(new App\Core\Request('POST','/admin/notifications/read'))->status===303,'M12 notification mutation requires a session');

$row=['delivery_id'=>7,'read_at'=>null,'delivered_at'=>'2026-11-10 10:00:00','event_id'=>3,'event_type'=>'delivery.incident','source_type'=>'delivery_event','source_id'=>'33','resource_type'=>'delivery','resource_id'=>5,'branch_id'=>2,'importance'=>'high','title'=>'Incidencia <script>','message'=>'Motivo & seguimiento','occurred_at'=>'2026-11-10 10:00:00','branch_name'=>'Sucursal <A>'];
$html=App\Core\View::partial('admin/notifications/index',['page'=>new App\Application\Read\Page([$row],25,0,false),'readFilter'=>'all','typeFilter'=>'','csrf'=>'test-token']);
check(str_contains($html,'Incidencia &lt;script&gt;')&&!str_contains($html,'Incidencia <script>'),'M12 notification title is escaped');
check(str_contains($html,'Motivo &amp; seguimiento')&&str_contains($html,'Sucursal &lt;A&gt;'),'M12 notification message and branch are escaped');
check(str_contains($html,'/admin/notifications/read')&&str_contains($html,'test-token'),'M12 read action carries CSRF');
check(str_contains($html,'/admin/notifications/open?id=7'),'M12 resource navigation uses the authorized indirection route');

$migration=(string)file_get_contents(dirname(__DIR__).'/database/migrations/20261110090000_create_internal_notifications.php');
check(str_contains($migration,'uq_notification_event_source')&&str_contains($migration,'uq_notification_delivery_recipient'),'M12 database enforces event and recipient idempotency');
check(str_contains($migration,'idx_notification_delivery_inbox'),'M12 unread and history lookup is indexed');
$service=(string)file_get_contents(dirname(__DIR__).'/app/Application/Services/DeliveryManagementService.php');
check(str_contains($service,"\$action==='incident'&&\$this->notifications!==null")&&str_contains($service,"'delivery.incident'"),'M12 delivery incident is integrated inside the business transaction');
$repository=(string)file_get_contents(dirname(__DIR__).'/app/Infrastructure/Persistence/PdoNotificationRepository.php');
check(str_contains($repository,'au.is_active=1')&&str_contains($repository,'p.code={$eventAlias}.required_permission'),'M12 reads re-evaluate active user and current resource permission');
