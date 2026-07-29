<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
require_login();
require APP_ROOT.'/vendor/autoload.php';
require __DIR__.'/_report.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$projectId=max(0,(int)($_GET['proyek_id']??0));
if(!$projectId&&!empty($_GET['id'])){
    $q=$pdo->prepare('SELECT proyek_id FROM titik_sondir WHERE id=?');
    $q->execute([(int)$_GET['id']]);
    $projectId=(int)$q->fetchColumn();
}

$report=build_project_report_html($pdo,$projectId);
if(!$report){
    http_response_code(404);
    exit('Proyek tidak ditemukan.');
}

$options=new Options();
$options->set('isRemoteEnabled',false);
$options->set('isHtml5ParserEnabled',true);
$options->set('defaultFont','DejaVu Sans');
$dompdf=new Dompdf($options);
$dompdf->loadHtml($report['html'],'UTF-8');
$dompdf->setPaper('A4','portrait');
$dompdf->render();
$canvas=$dompdf->getCanvas();
$font=$dompdf->getFontMetrics()->getFont('DejaVu Sans','normal');
$canvas->page_text(515,814,'Halaman {PAGE_NUM} dari {PAGE_COUNT}',$font,7,[.30,.38,.46]);
$dompdf->stream($report['filename'],['Attachment'=>false]);
