<?php
declare(strict_types=1);
namespace App\Infrastructure\Documents;
/** Dependency-free, bounded OOXML workbook writer. All cells are inline text. */
final class XlsxWriter
{
    public function render(array $headers,array $rows,string $title='Reporte'):string
    {
        if(count($rows)>10000)throw new \InvalidArgumentException('La exportación admite hasta 10,000 registros.');
        $sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $all=array_merge([$headers],$rows);
        foreach($all as $ri=>$row){$sheet.='<row r="'.($ri+1).'">';foreach(array_values($row)as$ci=>$value){$sheet.='<c r="'.$this->column($ci).($ri+1).'" t="inlineStr" s="'.($ri===0?'1':'0').'"><is><t xml:space="preserve">'.$this->cell((string)($value??'')).'</t></is></c>';}$sheet.='</row>';}
        $sheet.='</sheetData><autoFilter ref="A1:'.$this->column(max(0,count($headers)-1)).max(1,count($all)).'"/></worksheet>';
        $files=[
            '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->cell(substr($title,0,31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml'=>'<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF97316"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0" fillId="0" borderId="0"/><xf fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml'=>'<?xml version="1.0" encoding="UTF-8"?>'.$sheet,
        ];
        return $this->zip($files);
    }
    private function cell(string $value):string{$value=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$value)??'';if(preg_match('/^[=+\-@]/',$value))$value="'".$value;return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');}
    private function column(int $number):string{$value='';do{$value=chr(65+($number%26)).$value;$number=intdiv($number,26)-1;}while($number>=0);return$value;}
    private function zip(array $files):string
    {
        $data='';$central='';$offset=0;$count=0;
        foreach($files as$name=>$body){$crc=crc32($body);$length=strlen($body);$local="PK\x03\x04".pack('vvvvvVVVvv',20,0,0,0,0,$crc,$length,$length,strlen($name),0).$name.$body;$data.=$local;$central.="PK\x01\x02".pack('vvvvvvVVVvvvvvVV',20,20,0,0,0,0,$crc,$length,$length,strlen($name),0,0,0,0,0,$offset).$name;$offset+=strlen($local);$count++;}
        return$data.$central."PK\x05\x06".pack('vvvvVVv',0,0,$count,$count,strlen($central),strlen($data),0);
    }
}
