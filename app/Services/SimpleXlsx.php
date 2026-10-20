<?php
namespace App\Services;
/** Minimal Office Open XML sheet writer. All cells are inline strings (never formulas). */
class SimpleXlsx {
 private static function xml(string $s):string {return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',$s)??'',ENT_XML1|ENT_QUOTES,'UTF-8');}
 private static function col(int $n):string {$out='';do{$out=chr(65+$n%26).$out;$n=intdiv($n,26)-1;}while($n>=0);return $out;}
 public static function write(array $headers,array $rows,string $path):void {
  if(!class_exists(\ZipArchive::class))throw new \RuntimeException('PHP ext-zip chưa bật; dùng CSV hoặc bật extension zip.');
  $zip=new \ZipArchive();if($zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Không tạo được XLSX.');
  $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
  $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="DATA" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
  $stream=fopen('php://temp','w+');fwrite($stream,'<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
  foreach(array_merge([$headers],$rows) as $ri=>$cells){fwrite($stream,'<row r="'.($ri+1).'">');foreach($cells as $ci=>$value){$cell=self::col($ci).($ri+1);$str=self::xml((string)($value??''));fwrite($stream,'<c r="'.$cell.'" t="inlineStr"><is><t xml:space="preserve">'.$str.'</t></is></c>');}fwrite($stream,'</row>');}fwrite($stream,'</sheetData></worksheet>');rewind($stream);$zip->addFromString('xl/worksheets/sheet1.xml',stream_get_contents($stream));fclose($stream);$zip->close();
 }
}
