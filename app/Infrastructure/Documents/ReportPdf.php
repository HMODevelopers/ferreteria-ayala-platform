<?php
declare(strict_types=1);
namespace App\Infrastructure\Documents;
/** Compact landscape PDF report writer using PDF standard fonts. */
final class ReportPdf
{
    private array $pages=[];private string $stream='';private int $y=70;
    public function render(string$title,string$scope,string$basis,array$headers,array$rows):string{$this->page($title,$scope);$this->line($basis,9);$this->line(implode(' | ',$headers),8,true);foreach($rows as$row){foreach($this->wrap(implode(' | ',array_map(fn($v)=>(string)($v??''),$row)),145)as$line){if($this->y>545){$this->finish();$this->page($title,$scope);$this->line(implode(' | ',$headers),8,true);}$this->line($line,8);}}$this->finish();return$this->document();}
    private function page(string$title,string$scope):void{$this->stream="1 .42 0 rg 30 555 782 5 re f 0 0 0 rg\n";$this->text('FERRETERIA AYALA',30,575,16,true);$this->text($title,30,552,12,true);$this->text('Generado: '.(new \DateTimeImmutable('now',new \DateTimeZone('America/Hermosillo')))->format('Y-m-d H:i').' | '.$scope,30,535,8);$this->y=55;}
    private function line(string$text,int$size=8,bool$bold=false):void{$this->text($text,30,595-$this->y,$size,$bold);$this->y+=13;}
    private function text(string$text,int$left,int$bottom,int$size,bool$bold=false):void{$value=iconv('UTF-8','Windows-1252//TRANSLIT',$text);$value=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$value===false?'':$value);$this->stream.='BT /'.($bold?'F2':'F1').' '.$size.' Tf 1 0 0 1 '.$left.' '.$bottom.' Tm ('.$value.") Tj ET\n";}
    private function wrap(string$text,int$length):array{return preg_split('/\n/',wordwrap($text,$length,"\n",true))?:[''];}
    private function finish():void{$this->text('Pagina '.(count($this->pages)+1),760,20,8);$this->pages[]=$this->stream;$this->stream='';}
    private function document():string{$objects=[];$kids=[];$next=5;foreach($this->pages as$content){$page=$next++;$stream=$next++;$kids[]="$page 0 R";$objects[$page]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $stream 0 R >>";$objects[$stream]='<< /Length '.strlen($content).">>\nstream\n".$content."endstream";}$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($this->pages).' >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';$objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offset=[0];foreach($objects as$id=>$object){$offset[$id]=strlen($pdf);$pdf.="$id 0 obj\n$object\nendobj\n";}$xref=strlen($pdf);$max=max(array_keys($objects));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ',$offset[$i])."\n";return$pdf."trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";}
}
