<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Application\Reporting\{ReportExportPolicy,ReportFilters};
use App\Core\{AppKernel,Request,Response};
final class ReportsController extends AdminController
{
    private function input(Request$r):array{return['report'=>$r->query('report'),'from'=>$r->query('from'),'to'=>$r->query('to'),'branch_id'=>$r->query('branch_id'),'q'=>$r->query('q'),'status'=>$r->query('status'),'origin'=>$r->query('origin')];}
    public function index(Request$r):Response
    {
        $user=$this->requireUser();if($user instanceof Response)return$user;$allowed=AppKernel::access()->accessibleBranchIdsForPermission($user->id??0,'reports.view');if($allowed===[])return$this->requirePermission($user,'reports.view')??Response::redirect('/admin');
        try{$filters=ReportFilters::from($this->input($r));if($filters['branch']!==null&&is_array($allowed)&&!in_array($filters['branch'],$allowed,true))return$this->requirePermission($user,'reports.view',$filters['branch'])??Response::redirect('/admin');$data=AppKernel::reports()->report($filters,$allowed,25,max(0,(int)$r->query('offset','0')));$error=null;}catch(\InvalidArgumentException$e){$filters=ReportFilters::from([]);$data=AppKernel::reports()->report($filters,$allowed);$error=$e->getMessage();}
        return$this->adminView('admin/reports/index',['title'=>'Reportes','activeNav'=>'reports','user'=>$user,'filters'=>$filters,'report'=>$data,'branches'=>AppKernel::customers()->branches($allowed),'error'=>$error,'canExport'=>AppKernel::access()->accessibleBranchIdsForPermission($user->id??0,'reports.export')!==[]]);
    }
    public function export(Request$r):Response
    {
        $user=$this->requireUser();if($user instanceof Response)return$user;$allowed=AppKernel::access()->accessibleBranchIdsForPermission($user->id??0,'reports.export');if($allowed===[])return$this->requirePermission($user,'reports.export')??Response::redirect('/admin');
        try{$filters=ReportFilters::from($this->input($r));if($filters['branch']!==null&&is_array($allowed)&&!in_array($filters['branch'],$allowed,true))return$this->requirePermission($user,'reports.export',$filters['branch'])??Response::redirect('/admin');$data=AppKernel::reports()->report($filters,$allowed,ReportExportPolicy::MAX_ROWS,0);ReportExportPolicy::assertFits($data['page']);$rows=[];foreach($data['page']->items as$row)$rows[]=array_map(fn($key)=>$row[$key]??'', $data['keys']);$format=$r->query('format','xlsx');$stamp=(new \DateTimeImmutable('now',new \DateTimeZone('America/Hermosillo')))->format('Ymd-His');$scope='Periodo '.$filters['from'].' a '.$filters['to'].($filters['branch']?' · sucursal #'.$filters['branch']:' · alcance autorizado');if($format==='pdf'){$body=(new \App\Infrastructure\Documents\ReportPdf())->render('REPORTE DE '.strtoupper($data['title']),$scope,$data['date_basis'],$data['columns'],$rows);$type='application/pdf';$ext='pdf';}else{$body=(new \App\Infrastructure\Documents\XlsxWriter())->render($data['columns'],$rows,$data['title']);$type='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';$ext='xlsx';}AppKernel::audit()->record($user->id,'report.export','report',$filters['type'],$filters['branch'],'success',context:['format'=>$ext,'rows'=>count($rows)]);return new Response($body,200,['Content-Type'=>$type,'Content-Disposition'=>'attachment; filename="reporte-'.$filters['type'].'-'.$stamp.'.'.$ext.'"','Cache-Control'=>'no-store, private']);}catch(\InvalidArgumentException$e){return$this->adminView('admin/error',['title'=>'No se pudo exportar','message'=>$e->getMessage(),'user'=>$user],422);}
    }
}
