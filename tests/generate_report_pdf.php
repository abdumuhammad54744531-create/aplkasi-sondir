<?php
declare(strict_types=1);

require dirname(__DIR__).'/config/bootstrap.php';
require APP_ROOT.'/vendor/autoload.php';
require APP_ROOT.'/laporan/_report.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$projectId=max(1,(int)($argv[1]??1));
$target=$argv[2]??(APP_ROOT.'/output/pdf/Laporan-Sondir-Model-Mix-Desain-Beton.pdf');
$report=build_project_report_html($pdo,$projectId);
if(!$report)throw new RuntimeException('Proyek tidak ditemukan.');

$options=new Options();
$options->set('isRemoteEnabled',false);
$options->set('isHtml5ParserEnabled',true);
$options->set('defaultFont','DejaVu Sans');
$render=function(array $document)use($options):Dompdf{$pdf=new Dompdf($options);$pdf->loadHtml($document['html'],'UTF-8');$pdf->setPaper('A4','portrait');$pdf->render();return $pdf;};
$firstPass=$render($report);
$report=build_project_report_html($pdo,$projectId,report_pdf_destination_pages($firstPass));
$dompdf=$render($report);
$canvas=$dompdf->getCanvas();
$font=$dompdf->getFontMetrics()->getFont('DejaVu Sans','normal');
$canvas->page_text(330,814,'Halaman {PAGE_NUM} dari {PAGE_COUNT}',$font,7,[.30,.38,.46]);
if(!is_dir(dirname($target)))mkdir(dirname($target),0775,true);
file_put_contents($target,$dompdf->output());
echo $target.PHP_EOL;
