<?php
declare(strict_types=1);
namespace App\Infrastructure\Documents;
/** A4 PDF writer using standard PDF fonts and Windows-1252 for Spanish. */
final class QuotePdf
{
    private array $pages=[];
    private string $stream='';
    private int $y=0;
    private string $reference='';
    private function literal(string $text):string
    {
        $value=iconv('UTF-8','Windows-1252//TRANSLIT',$text);
        if($value===false)throw new \RuntimeException('No se pudo codificar el documento.');
        return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$value);
    }
    private function text(string $text,int $x,int $top,int $size=10,bool $bold=false):void
    {
        $font=$bold?'F2':'F1';
        $this->stream.='BT /'.$font.' '.$size.' Tf 1 0 0 1 '.$x.' '.(842-$top).' Tm ('.$this->literal($text).") Tj ET\n";
    }
    private function rect(int $x,int $top,int $width,int $height,string $color):void
    {
        $this->stream.=$color.' rg '.$x.' '.(842-$top-$height).' '.$width.' '.$height." re f 0 0 0 rg\n";
    }
    private function page():void
    {
        if($this->stream!=='')$this->finishPage();
        $this->stream='';$this->rect(36,30,523,5,'1 .42 0');
        $this->text('FERRETERÍA AYALA',36,58,17,true);
        $this->text('COTIZACIÓN COMERCIAL',36,78,10,true);
        $this->text($this->reference,36,96,9);$this->y=124;
    }
    private function finishPage():void
    {
        $this->text('Ferretería Ayala | MXN | Documento comercial, sin efectos fiscales',36,805,8);
        $this->text('Página '.(count($this->pages)+1),504,805,8);
        $this->pages[]=$this->stream;$this->stream='';
    }
    private function ensure(int $height):void {if($this->y+$height>780)$this->page();}
    private function lines(string $text,int $width):array
    {
        // Conservative integer glyph widths keep even unbroken wide text inside the column.
        $measure=static function(string $value):int {
            $units=0;
            foreach(preg_split('//u',$value,-1,PREG_SPLIT_NO_EMPTY)?:[]as $char){
                $units+=match(true){$char===' '=>300,in_array($char,['W','M','@'],true)=>1000,$char==='m'=>900,$char==='w'=>800,(bool)preg_match('/^[A-Z]$/D',$char)=>900,(bool)preg_match('/^[a-z0-9]$/D',$char)=>600,default=>1000};
            }
            return $units;
        };
        $budget=$width*500;$out=[];
        foreach(explode("\n",str_replace("\r",'',$text))as $paragraph){
            $line='';
            foreach(preg_split('/\s+/u',$paragraph,-1,PREG_SPLIT_NO_EMPTY)?:[]as $word){
                $parts=[];$part='';
                foreach(preg_split('//u',$word,-1,PREG_SPLIT_NO_EMPTY)?:[]as $char){
                    if($part!==''&&$measure($part.$char)>$budget){$parts[]=$part;$part='';}
                    $part.=$char;
                }
                if($part!=='')$parts[]=$part;
                foreach($parts as $part){
                    if($line!==''&&$measure($line.' '.$part)>$budget){$out[]=$line;$line='';}
                    $line=trim($line.' '.$part);
                }
            }
            $out[]=$line;
        }
        return $out;
    }
    private function paragraph(string $text,bool $bold=false):void
    {
        foreach($this->lines($text,90)as $line){$this->ensure(15);$this->text($line,36,$this->y,10,$bold);$this->y+=15;}
        $this->y+=7;
    }
    private function tableHeader():void
    {
        $this->ensure(32);$this->rect(36,$this->y-13,523,25,'.95 .95 .95');
        foreach([[42,'Material / SKU'],[278,'Cantidad'],[358,'Precio MXN'],[463,'Total MXN']]as[$x,$label])$this->text($label,$x,$this->y,9,true);
        $this->y+=26;
    }
    public function render(array $quote,array $version,array $items):string
    {
        $this->pages=[];$this->stream='';$this->reference=$quote['folio'].' | Versión '.$version['version_number'];$this->page();
        $branch=json_decode($version['branch_snapshot'],true,512,JSON_THROW_ON_ERROR);
        $customer=json_decode($version['customer_snapshot'],true,512,JSON_THROW_ON_ERROR);
        $this->paragraph($branch['name'],true);
        foreach(['address','phone','email']as $key)if(!empty($branch[$key]))$this->paragraph($branch[$key]);
        $issued=(new \DateTimeImmutable($version['issued_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('America/Hermosillo'))->format('d/m/Y H:i');
        $this->paragraph('Emisión: '.$issued.' | Vigencia: '.$version['valid_until'].' (America/Hermosillo)');
        $this->paragraph('Vendedor: '.$version['seller_snapshot']);
        $this->paragraph('CLIENTE: '.$customer['legal_name'],true);
        foreach(['tax_identifier','contact_name','phone','email','address_line1','city','state_name','postal_code']as $key)if(!empty($customer[$key]))$this->paragraph($customer[$key]);
        $this->tableHeader();
        foreach($items as $item){
            $description=$this->lines($item['description'],38);
            $metadata=$this->lines($item['sku'].' | '.$item['unit_name'],95);
            $tax=$this->lines('Desc. '.$item['discount_rate'].'% ($'.$item['discount_amount'].') | Impuesto '.$item['tax_rate'].'% ($'.$item['tax_amount'].')'.($item['tax_included']?' incluido':' excluido'),95);
            $height=(count($description)+count($metadata)+count($tax))*13+16;
            if($this->y+$height>780){$this->page();$this->tableHeader();}
            foreach($description as $i=>$line)$this->text($line,42,$this->y+$i*13,9,true);
            $this->text($item['quantity'],278,$this->y,strlen($item['quantity'])>14?7:9);
            $this->text('$'.$item['unit_price'],358,$this->y,9);
            $this->text('$'.$item['total'],463,$this->y,strlen($item['total'])>15?8:9,true);
            $this->y+=count($description)*13;
            foreach($metadata as $line){$this->text($line,42,$this->y,8);$this->y+=13;}
            foreach($tax as $line){$this->text($line,42,$this->y,8);$this->y+=13;}
            $this->y+=16;
        }
        $this->ensure(150);$this->y+=8;
        foreach(['subtotal'=>'Subtotal','discount_amount'=>'Descuentos','tax_amount'=>'Impuestos','delivery_amount'=>'Entrega acordada','total'=>'TOTAL MXN']as $field=>$label){
            $bold=$field==='total';if($bold)$this->rect(330,$this->y-14,229,28,'1 .94 .89');
            $this->text($label,340,$this->y,11,$bold);
            $this->text('$'.$version[$field],460,$this->y,strlen($version[$field])>15?9:11,$bold);
            $this->y+=25;
        }
        $this->paragraph('CONDICIONES COMERCIALES',true);$this->paragraph($version['terms']);
        $this->paragraph('ENTREGA ACORDADA',true);$this->paragraph($version['delivery_terms']);
        if($version['notes']){$this->paragraph('OBSERVACIONES',true);$this->paragraph($version['notes']);}
        $this->paragraph('Crear o emitir una cotización no aparta mercancía. La aceptación confirmada valida disponibilidad y genera el apartado. No representa una venta, un pago ni un comprobante fiscal.');
        $this->finishPage();return $this->encode();
    }
    private function encode():string
    {
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids=[];
        foreach($this->pages as $content){
            $page=count($objects)+1;$stream=$page+1;$kids[]=$page.' 0 R';
            $objects[$page]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$stream.' 0 R >>';
            $objects[$stream]='<< /Length '.strlen($content).">>\nstream\n".$content.'endstream';
        }
        $objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++)$pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";
        return $pdf.'trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF\n";
    }
}
