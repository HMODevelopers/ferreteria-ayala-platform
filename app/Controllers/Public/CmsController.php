<?php
declare(strict_types=1);
namespace App\Controllers\Public;
use App\Core\{AppKernel,Controller,Request,Response};
use App\Data\CmsHomeData;
use App\Domain\Cms\CmsRules;
final class CmsController extends Controller
{
    public static function enabled():bool
    {
        try{return AppKernel::cms()->installed()&&AppKernel::cms()->one("SELECT id FROM modules WHERE code='M11' AND is_globally_enabled=1")!==null;}catch(\App\Core\Persistence\PersistenceException|\PDOException){return false;}
    }
    private function missing():Response {return new Response('Contenido no disponible.',404,['Cache-Control'=>'no-store']);}
    private function render(string $template,array $data,int $status=200):Response
    {
        $base=rtrim((string)getenv('APP_URL'),'/');
        if(filter_var($base,FILTER_VALIDATE_URL)&&in_array(parse_url($base,PHP_URL_SCHEME),['http','https'],true))$data['canonical']=$base.($data['canonicalPath']??'/');
        $data['navigation']=AppKernel::cms()->publicContent('navigation_items',$data['branchId']??null);
        return new Response(\App\Core\View::render($template,$data,'layouts/cms'),$status,['Cache-Control'=>'no-store']);
    }
    public function home(Request $r,array $quoteValues=[],?string $quoteError=null,?string $quoteFolio=null,int $status=200):Response
    {
        if(!self::enabled())return $this->missing();$branch=$r->integer('branch_id')?:null;$search=substr(trim($r->query('q')),0,100);$category=substr(trim($r->query('category')),0,80);$offset=max(0,(int)$r->query('offset','0'));$data=CmsHomeData::from(AppKernel::cms(),$branch,$search,$category,$offset);
        $data['quoteRequest']=$this->quoteData($r,$quoteValues,$quoteError,$quoteFolio);
        return new Response(\App\Core\View::render('public/home',$data,'layouts/public'),$status,['Cache-Control'=>'no-store']);
    }
    public function page(Request $r):Response
    {
        if(!self::enabled())return $this->missing();$branch=$r->integer('branch_id')?:null;$slug=CmsRules::text($r->query('slug'),120,true);$page=AppKernel::cms()->publicContent('pages',$branch,$slug)[0]??null;if(!$page)return $this->missing();
        return $this->render('public/cms/page',['title'=>$page['seo_title']?:$page['title'],'description'=>$page['seo_description'],'canonicalPath'=>'/pagina?slug='.rawurlencode($slug).($branch?'&branch_id='.$branch:''),'page'=>$page,'branchId'=>$branch]);
    }
    public function legal(Request $r):Response
    {
        if(!self::enabled())return $this->missing();$code=$r->query('document','privacy');if(!in_array($code,['privacy','quote_conditions'],true))return $this->missing();$doc=AppKernel::cms()->legal($code,$r->integer('version')?:null);if(!$doc)return $this->missing();
        return $this->render('public/cms/legal',['title'=>$doc['title'],'canonicalPath'=>'/legal?document='.$code.'&version='.$doc['id'],'document'=>$doc]);
    }
    public function image(Request $r):Response
    {
        if(!self::enabled())return $this->missing();$token=$r->query('token');if(!preg_match('/^[a-f0-9]{48}$/D',$token))return $this->missing();$m=AppKernel::cms()->publicMedia($token);if(!$m)return $this->missing();
        $width=$r->integer('width');if($width){$v=AppKernel::cms()->one('SELECT storage_key,byte_size,sha256 FROM media_variants WHERE media_id=? AND width=?',[$m['id'],$width]);if(!$v)return $this->missing();$m=array_replace($m,$v);}
        try{$bytes=AppKernel::imageStorage()->read($m);return new Response($bytes,200,['Content-Type'=>'image/webp','Content-Length'=>(string)strlen($bytes),'Cache-Control'=>'no-store','Content-Disposition'=>'inline; filename="imagen.webp"']);}catch(\Throwable){return $this->missing();}
    }
    private function quoteData(Request $r,array $values=[],?string $error=null,?string $folio=null):array
    {
        $legal=AppKernel::cms()->legal('privacy');$branch=isset($values['branch_id'])&&ctype_digit((string)$values['branch_id'])?(int)$values['branch_id']:($r->integer('branch_id')?:null);$q=CmsRules::text($r->query('q'),120);$branches=array_values(array_filter(AppKernel::cms()->branches(),static fn($b)=>AppKernel::publicRequests()->enabled((int)$b['id'])));
        if($branch!==null&&!array_filter($branches,static fn($b)=>(int)$b['id']===$branch))$branch=null;
        $variants=AppKernel::cms()->query("SELECT v.id,v.sku,v.name,p.name product_name,u.name unit_name,u.symbol,c.id category_id,c.name category_name FROM product_variants v JOIN products p ON p.id=v.product_id JOIN categories c ON c.id=p.category_id JOIN units u ON u.id=v.unit_id WHERE v.is_active=1 AND p.is_active=1 AND c.is_active=1 AND u.is_active=1 ORDER BY c.name,p.name,v.name,v.id LIMIT 250")->fetchAll();
        $csrf=AppKernel::session()->csrfToken();$_SESSION['public_quote_forms']??=[];$candidate=(string)($values['operation_key']??$r->query('operation_key'));
        $key=preg_match('/^[a-f0-9]{48}$/D',$candidate)&&isset($_SESSION['public_quote_forms'][$candidate])?$candidate:bin2hex(random_bytes(24));
        if(!isset($_SESSION['public_quote_forms'][$key]))$_SESSION['public_quote_forms'][$key]=time();if(count($_SESSION['public_quote_forms'])>20)array_shift($_SESSION['public_quote_forms']);
        $lines=$values['lines']??[];if(!$lines&&$q!==''){foreach($variants as $variant)if(hash_equals(strtolower((string)$variant['sku']),strtolower($q))){$lines=[['variant_id'=>(string)$variant['id'],'description'=>$variant['product_name'].' · '.$variant['name'],'quantity'=>'1','unit'=>$variant['unit_name'],'observations'=>'']];break;}}
        return ['branches'=>$branches,'branchId'=>$branch,'variants'=>$variants,'legal'=>$legal,'csrf'=>$csrf,'operationKey'=>$key,'values'=>$values,'lines'=>$lines,'error'=>$error,'folio'=>$folio,'available'=>$legal!==null&&$branches!==[]];
    }
    public function requestForm(Request $r,array $values=[],?string $error=null,?string $folio=null):Response
    {
        if(!self::enabled())return $this->missing();
        return $this->render('public/cms/request',['title'=>'Solicitar cotización','noIndex'=>true,'canonicalPath'=>'/solicitar-cotizacion','quoteRequest'=>$this->quoteData($r,$values,$error,$folio)],$error?422:200);
    }
    public function receive(Request $r):Response
    {
        if(!self::enabled())return $this->missing();$key=$r->input('operation_key');$in=[];foreach(['branch_id','legal_version_id','full_name','company','phone','email','contact_preference','comment','consent','website','operation_key','modal'] as $k)$in[$k]=$r->input($k);
        $descriptions=$r->values('description');$quantities=$r->values('quantity');$units=$r->values('unit');$observations=$r->values('observations');$variants=$r->values('variant_id');$lines=[];$count=max(count($descriptions),count($quantities),count($units),count($observations),count($variants));for($i=0;$i<$count;$i++)$lines[]=['variant_id'=>$variants[$i]??'','description'=>$descriptions[$i]??'','quantity'=>$quantities[$i]??'','unit'=>$units[$i]??'','observations'=>$observations[$i]??''];
        try{
            if(!AppKernel::session()->verifyCsrf($r->input('_token')))return new Response('El formulario caducó. Actualiza la página.',419,['Cache-Control'=>'no-store']);
            $created=$_SESSION['public_quote_forms'][$key]??0;if(!is_int($created)||time()-$created<2||time()-$created>7200)throw new \InvalidArgumentException('Actualiza el formulario y revisa tus datos antes de enviarlo.');
            $fingerprint=\App\Infrastructure\Media\RequestFingerprint::forIp($r->ip(),AppKernel::cmsPrivateDirectory());
            $result=AppKernel::publicRequests()->receive($in,$lines,$fingerprint);$values=['operation_key'=>$key];return $in['modal']==='1'?$this->home($r,$values,null,$result['folio']):$this->requestForm($r,$values,null,$result['folio']);
        }catch(\InvalidArgumentException|\App\Application\Exceptions\ApplicationException $e){$error=$e->getMessage();}catch(\Throwable){$error='No pudimos registrar la solicitud. Conserva tus datos e intenta nuevamente.';}
        $in['lines']=$lines;return $in['modal']==='1'?$this->home($r,$in,$error,null,422):$this->requestForm($r,$in,$error);
    }

    public function sitemap(Request $r):Response
    {
        $base=rtrim((string)getenv('APP_URL'),'/');
        if(!self::enabled()||!filter_var(getenv('APP_INDEXING')?:'false',FILTER_VALIDATE_BOOL)||!filter_var($base,FILTER_VALIDATE_URL)||!in_array(parse_url($base,PHP_URL_SCHEME),['http','https'],true))return $this->missing();
        $rows=AppKernel::cms()->query('SELECT c.slug,c.branch_id,c.updated_at FROM pages c WHERE '.AppKernel::cms()->visibleSql().' AND (c.branch_id IS NULL OR EXISTS(SELECT 1 FROM branches b WHERE b.id=c.branch_id AND b.is_active=1)) ORDER BY c.id LIMIT 10000')->fetchAll();
        $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>'.e($base.'/').'</loc></url>';
        foreach($rows as $row)$xml.='<url><loc>'.e($base.'/pagina?slug='.rawurlencode($row['slug']).($row['branch_id']!==null?'&branch_id='.$row['branch_id']:'')).'</loc></url>';
        return new Response($xml.'</urlset>',200,['Content-Type'=>'application/xml; charset=UTF-8','Cache-Control'=>'no-store']);
    }
}
