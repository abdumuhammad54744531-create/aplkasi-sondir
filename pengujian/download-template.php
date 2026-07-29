<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_login();
require APP_ROOT.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$book=new Spreadsheet();
$sheet=$book->getActiveSheet();
$sheet->setTitle('Data Sondir');
$sheet->fromArray([
    ['TEMPLATE IMPOR DATA SONDIR - SNI 2827:2008'],
    ['Isi kolom Kedalaman, Cw, dan Tw. Jenis tanah serta konsistensi/kepadatan dihitung otomatis.'],
    [],
    ['No','Kedalaman','Cw','Tw'],
],null,'A1');
for($i=1;$i<=20;$i++)$sheet->fromArray([$i,$i*.2,'',''],null,'A'.($i+4));
$sheet->mergeCells('A1:D1')->mergeCells('A2:D2');
$sheet->getStyle('A1:D1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B3B63');
$sheet->getStyle('A4:D4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A4:D4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1769AA');
$sheet->getStyle('A1:D4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->freezePane('A5');
foreach(['A'=>8,'B'=>14,'C'=>14,'D'=>14] as $col=>$width)$sheet->getColumnDimension($col)->setWidth($width);
$sheet->getStyle('B5:D24')->getNumberFormat()->setFormatCode('0.00');
$sheet->setAutoFilter('A4:D24');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Template-Impor-Sondir-SNI-2827-2008.xlsx"');
header('Cache-Control: max-age=0');
(new Xlsx($book))->save('php://output');
exit;
