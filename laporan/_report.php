<?php
declare(strict_types=1);

function report_number(float $value, int $decimals=2): string
{
    $formatted=number_format($value,$decimals,',','.');
    return $decimals>0?rtrim(rtrim($formatted,'0'),','):$formatted;
}

function report_settings_ensure(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pengaturan_laporan (
            id TINYINT PRIMARY KEY DEFAULT 1,kop_nama VARCHAR(200) NOT NULL,kop_subjudul VARCHAR(200),kop_alamat TEXT,
            judul_laporan VARCHAR(200) NOT NULL,font_family VARCHAR(50) DEFAULT 'DejaVu Sans',font_size DECIMAL(4,1) DEFAULT 9.2,
            warna_utama VARCHAR(7) DEFAULT '#173B61',warna_aksen VARCHAR(7) DEFAULT '#F4B400',gaya_kop VARCHAR(30) DEFAULT 'formal',
            logo_path VARCHAR(255),footer_text VARCHAR(255),updated_by INT NULL,updated_at DATETIME NULL,
            FOREIGN KEY(updated_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function report_settings(PDO $pdo, array $lab=[]): array
{
    report_settings_ensure($pdo);
    $settings=$pdo->query('SELECT * FROM pengaturan_laporan WHERE id=1')->fetch()?:[];
    return array_merge([
        'kop_nama'=>$lab['nama_laboratorium']??'Laboratorium Mekanika Tanah',
        'kop_subjudul'=>$lab['nama_instansi']??'Universitas Muhammadiyah Buton',
        'kop_alamat'=>$lab['alamat']??'',
        'judul_laporan'=>'LAPORAN PENYELIDIKAN TANAH',
        'font_family'=>'DejaVu Sans',
        'font_size'=>9.2,
        'warna_utama'=>'#173B61',
        'warna_aksen'=>'#F4B400',
        'gaya_kop'=>'formal',
        'logo_path'=>$lab['logo']??null,
        'footer_text'=>$lab['footer_laporan']??'',
    ],$settings);
}

function report_hex_color(string $color, string $fallback): string
{
    return preg_match('/^#[0-9A-Fa-f]{6}$/',$color)?strtoupper($color):$fallback;
}

function report_hsl_to_rgb(float $h, float $s, float $l): array
{
    $h=fmod($h,360)/360;$s/=100;$l/=100;
    if($s===0.0)$r=$g=$b=$l;
    else{
        $q=$l<.5?$l*(1+$s):$l+$s-$l*$s;
        $p=2*$l-$q;
        $hue=function(float $t)use($p,$q):float{
            if($t<0)$t+=1;if($t>1)$t-=1;
            if($t<1/6)return $p+($q-$p)*6*$t;
            if($t<1/2)return $q;
            if($t<2/3)return $p+($q-$p)*(2/3-$t)*6;
            return $p;
        };
        $r=$hue($h+1/3);$g=$hue($h);$b=$hue($h-1/3);
    }
    return [(int)round($r*255),(int)round($g*255),(int)round($b*255)];
}

function report_soil_visual(string $soil, string $strength): array
{
    $soilTypes=['Pasir sangat padat / kerikil','Pasir - pasir berlanau','Lanau - pasir berlanau','Lempung berlanau','Lempung','Lempung organik / sangat lunak'];
    $levels=['Sangat Lunak'=>0,'Sangat Lepas'=>0,'Lunak'=>1,'Lepas'=>1,'Teguh / Sedang'=>2,'Agak Padat'=>2,'Kaku'=>3,'Padat'=>3,'Sangat Kaku'=>4,'Sangat Padat'=>4,'Keras'=>5];
    $soilIndex=array_search($soil,$soilTypes,true);
    $soilIndex=$soilIndex===false?0:$soilIndex;
    $level=$levels[$strength]??0;
    $hue=fmod($soilIndex*57+$level*83+25,360);
    [$r,$g,$b]=report_hsl_to_rgb($hue,88,72);
    [$lr,$lg,$lb]=report_hsl_to_rgb($hue,88,30);
    if(!extension_loaded('gd'))return ['background'=>"rgb($r,$g,$b)",'image'=>''];

    $image=imagecreatetruecolor(14,14);
    $base=imagecolorallocate($image,$r,$g,$b);
    $line=imagecolorallocatealpha($image,$lr,$lg,$lb,35);
    imagefill($image,0,0,$base);
    imagesetthickness($image,1);
    if($level===0){imageline($image,0,3,14,3,$line);imageline($image,0,10,14,10,$line);}
    elseif($level===1){imageline($image,3,0,3,14,$line);imageline($image,10,0,10,14,$line);}
    elseif($level===2){imageline($image,-4,14,10,0,$line);imageline($image,4,18,18,4,$line);}
    elseif($level===3){imageline($image,-4,0,10,14,$line);imageline($image,4,-4,18,10,$line);}
    elseif($level===4){imageline($image,-4,14,10,0,$line);imageline($image,4,18,18,4,$line);imageline($image,-4,0,10,14,$line);imageline($image,4,-4,18,10,$line);}
    else{imagefilledellipse($image,3,3,3,3,$line);imagefilledellipse($image,10,10,3,3,$line);}
    ob_start();imagepng($image);$png=(string)ob_get_clean();
    return ['background'=>"rgb($r,$g,$b)",'image'=>'data:image/png;base64,'.base64_encode($png)];
}

function report_soil_cells(array $rows): array
{
    $cells=[];$count=count($rows);
    for($start=0;$start<$count;){
        $soil=(string)($rows[$start]['jenis_tanah']??'');
        $strength=(string)($rows[$start]['keterangan']??'');
        $label=trim(sondir_soil_display_name($soil).' '.$strength);
        $end=$start;
        while($end+1<$count){
            $next=trim(sondir_soil_display_name((string)($rows[$end+1]['jenis_tanah']??'')).' '.(string)($rows[$end+1]['keterangan']??''));
            if($next!==$label)break;
            $end++;
        }
        $visual=report_soil_visual($soil,$strength);
        $middle=(int)floor(($start+$end)/2);
        for($i=$start;$i<=$end;$i++)$cells[$i]=['label'=>$i===$middle?$label:'','start'=>$i===$start,'end'=>$i===$end,'background'=>$visual['background'],'image'=>$visual['image']];
        $start=$end+1;
    }
    return $cells;
}

function report_roman_month(int $month): string
{
    return [1=>'I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][$month]??'I';
}

function report_image_data_uri(string $file): ?string
{
    if(!is_file($file))return null;
    $mime=mime_content_type($file)?:'image/jpeg';
    if(!str_starts_with($mime,'image/'))return null;
    return 'data:'.$mime.';base64,'.base64_encode((string)file_get_contents($file));
}

function report_qr_data_uri(string $content): string
{
    // PNG lebih konsisten dirender Dompdf pada seluruh versi PHP/Laragon.
    $png=(new \BaconQrCode\Writer(
        new \BaconQrCode\Renderer\GDLibRenderer(260,3,'png')
    ))->writeString($content);
    return 'data:image/png;base64,'.base64_encode($png);
}

function report_legal_token(array $project, string $reportNumber): string
{
    return strtoupper(substr(hash('sha256',implode('|',[
        (string)$project['id'],(string)$project['kode_proyek'],(string)$project['nama_proyek'],
        $reportNumber,(string)$project['created_at'],
    ])),0,32));
}

function report_verification_url(string $token, string $type='report'): string
{
    $scheme=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http';
    $host=$_SERVER['HTTP_HOST']??'localhost';
    return $scheme.'://'.$host.url('verifikasi.php').'?kode='.rawurlencode($token).'&jenis='.rawurlencode($type);
}

function report_water_row_index(array $rows, mixed $waterLevel): ?int
{
    if($waterLevel===null||$waterLevel===''||(float)$waterLevel<=0||!$rows)return null;
    $best=0;$distance=INF;
    foreach($rows as $index=>$row){
        $current=abs((float)$row['kedalaman']-(float)$waterLevel);
        if($current<$distance){$best=$index;$distance=$current;}
    }
    return $best;
}

function report_documentation_path(string $name): ?string
{
    $name=ltrim(str_replace(['../','..\\'], '', $name),'/\\');
    foreach([APP_ROOT.'/uploads/'.$name,APP_ROOT.'/storage/'.$name,APP_ROOT.'/'.$name] as $path){
        if(is_file($path))return $path;
    }
    return null;
}

function report_status(array $points): string
{
    if(!$points)return 'draft';
    $statuses=array_column($points,'status');
    if(in_array('perlu_revisi',$statuses,true))return 'perlu_revisi';
    if(in_array('menunggu_pemeriksaan',$statuses,true))return 'menunggu_pemeriksaan';
    if(count(array_filter($statuses,fn($status)=>$status==='diterbitkan'))===count($statuses))return 'diterbitkan';
    if(count(array_filter($statuses,fn($status)=>in_array($status,['disetujui','diterbitkan'],true)))===count($statuses))return 'disetujui';
    if(in_array('sedang_diuji',$statuses,true))return 'sedang_diuji';
    return 'draft';
}

function report_project_data(PDO $pdo, int $projectId): ?array
{
    $q=$pdo->prepare(
        "SELECT p.*,k.nama_klien,k.nama_perusahaan,k.nama_kontak,k.alamat alamat_klien,
                op.nama_lengkap operator_proyek,pm.nama_lengkap pemeriksa_proyek
         FROM proyek p
         JOIN klien k ON k.id=p.klien_id
         LEFT JOIN users op ON op.id=p.operator_id
         LEFT JOIN users pm ON pm.id=p.pemeriksa_id
         WHERE p.id=?"
    );
    $q->execute([$projectId]);
    $project=$q->fetch();
    if(!$project)return null;

    $q=$pdo->prepare(
        "SELECT t.*,a.kode_alat,a.nama_alat,a.jenis_alat,a.merek,a.model,a.nomor_seri,
                a.kapasitas_maksimum,a.satuan_kapasitas,a.diameter_piston,a.diameter_konus,
                a.diameter_selimut,a.panjang_selimut_geser,a.luas_piston,a.luas_konus,a.luas_selimut,
                a.nomor_sertifikat,a.tanggal_kalibrasi,a.tanggal_kedaluwarsa,a.lembaga_kalibrasi,
                op.nama_lengkap operator,pm.nama_lengkap pemeriksa
         FROM titik_sondir t
         JOIN alat_sondir a ON a.id=t.alat_id
         JOIN users op ON op.id=t.operator_id
         LEFT JOIN users pm ON pm.id=t.pemeriksa_id
         WHERE t.proyek_id=?
         ORDER BY t.nomor_urut,t.id"
    );
    $q->execute([$projectId]);
    $points=$q->fetchAll();

    $resultQuery=$pdo->prepare(
        'SELECT * FROM hasil_sondir WHERE titik_sondir_id=? ORDER BY kedalaman,nomor'
    );
    $docQuery=$pdo->prepare(
        'SELECT * FROM dokumentasi_sondir WHERE titik_sondir_id=? ORDER BY urutan,id'
    );
    foreach($points as &$point){
        $resultQuery->execute([(int)$point['id']]);
        $point['rows']=$resultQuery->fetchAll();
        $docQuery->execute([(int)$point['id']]);
        $point['documentation']=$docQuery->fetchAll();
    }
    unset($point);

    $lab=$pdo->query('SELECT * FROM laboratorium ORDER BY id LIMIT 1')->fetch()?:[];
    $settings=report_settings($pdo,$lab);
    return compact('project','points','lab','settings');
}

function report_point_stats(array $point): array
{
    $rows=$point['rows'];
    if(!$rows)return ['depth'=>0.0,'qc_max'=>0.0,'tf_max'=>0.0,'hard_depth'=>null,'soil'=>'Belum ada data'];
    $depth=max(array_map(fn($row)=>(float)$row['kedalaman'],$rows));
    $qcMax=max(array_map(fn($row)=>(float)$row['qc'],$rows));
    $tfMax=max(array_map(fn($row)=>(float)$row['jhp'],$rows));
    $hardDepth=null;
    foreach($rows as $row){
        if((float)$row['qc']>=150){
            $hardDepth=(float)$row['kedalaman'];
            break;
        }
    }
    $last=end($rows);
    $soil=trim(sondir_soil_display_name((string)($last['jenis_tanah']??'')).' '.(string)($last['keterangan']??''));
    return compact('depth','qcMax','tfMax','hardDepth','soil');
}

function report_soil_intervals(array $rows): array
{
    $segments=[];
    foreach($rows as $row){
        $label=trim(sondir_soil_display_name((string)($row['jenis_tanah']??'')).' '.(string)($row['keterangan']??''));
        if($label==='')$label='Belum terklasifikasi';
        $depth=(float)$row['kedalaman'];
        $last=count($segments)-1;
        if($last>=0&&$segments[$last]['label']===$label){
            $segments[$last]['to']=$depth;
            $segments[$last]['qc_max']=max($segments[$last]['qc_max'],(float)$row['qc']);
        }else{
            $segments[]=['from'=>$depth,'to'=>$depth,'label'=>$label,'qc_min'=>(float)$row['qc'],'qc_max'=>(float)$row['qc']];
        }
    }
    return $segments;
}

function report_nice_max(float $value): float
{
    if($value<=0)return 1;
    $power=10**floor(log10($value));
    $fraction=$value/$power;
    return (match(true){$fraction<=1=>1,$fraction<=2=>2,$fraction<=5=>5,default=>10})*$power;
}

function report_chart_uri(array $rows): string
{
    $width=920;$height=500;$top=62;$bottom=55;$left=72;$panel=360;$gap=80;
    $plotHeight=$height-$top-$bottom;
    $maxDepth=max(1.0,ceil(max(array_map(fn($r)=>(float)$r['kedalaman'],$rows))));
    $maxQc=report_nice_max(max(array_map(fn($r)=>(float)$r['qc'],$rows)));
    $maxTf=report_nice_max(max(array_map(fn($r)=>(float)$r['jhp'],$rows)));
    $maxFr=report_nice_max(max(array_map(fn($r)=>(float)$r['friction_ratio'],$rows)));
    $qcPoints=[];$tfPoints=[];$frPoints=[];
    foreach($rows as $row){
        $y=$top+((float)$row['kedalaman']/$maxDepth)*$plotHeight;
        $qcPoints[]=($left+(float)$row['qc']/$maxQc*$panel).','.round($y,2);
        $tfPoints[]=($left+(float)$row['jhp']/$maxTf*$panel).','.round($y,2);
        $frPoints[]=($left+$panel+$gap+(float)$row['friction_ratio']/$maxFr*$panel).','.round($y,2);
    }
    $grid='';$labels='';
    for($i=0;$i<=5;$i++){
        $y=$top+$plotHeight*$i/5;
        $depth=$maxDepth*$i/5;
        $grid.='<line x1="'.$left.'" y1="'.$y.'" x2="'.($left+$panel).'" y2="'.$y.'"/>';
        $grid.='<line x1="'.($left+$panel+$gap).'" y1="'.$y.'" x2="'.($left+$panel*2+$gap).'" y2="'.$y.'"/>';
        $labels.='<text x="'.($left-10).'" y="'.($y+4).'" text-anchor="end">'.report_number($depth,1).'</text>';
        $labels.='<text x="'.($left+$panel+$gap-10).'" y="'.($y+4).'" text-anchor="end">'.report_number($depth,1).'</text>';
        foreach([[$left,$maxTf,'#d97706',$top-12],[$left,$maxQc,'#1769aa',$height-20],[$left+$panel+$gap,$maxFr,'#7c3aed',$top-12]] as [$origin,$maximum,$color,$textY]){
            $x=$origin+$panel*$i/5;
            $grid.='<line x1="'.$x.'" y1="'.$top.'" x2="'.$x.'" y2="'.($top+$plotHeight).'"/>';
            $labels.='<text x="'.$x.'" y="'.$textY.'" text-anchor="middle" fill="'.$color.'">'.report_number($maximum*$i/5,1).'</text>';
        }
    }
    $svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">
      <rect width="100%" height="100%" fill="#fff"/><style>text{font:12px DejaVu Sans,sans-serif;fill:#334155}.grid line{stroke:#d9e2ec;stroke-width:1}.axis{stroke:#64748b;stroke-width:1.4}.title{font:bold 15px DejaVu Sans,sans-serif;fill:#173b61}.line{fill:none;stroke-width:3;stroke-linejoin:round;stroke-linecap:round}</style>
      <g class="grid">'.$grid.'</g>'.$labels.'
      <line class="axis" x1="'.$left.'" y1="'.$top.'" x2="'.$left.'" y2="'.($top+$plotHeight).'"/><line class="axis" x1="'.($left+$panel+$gap).'" y1="'.$top.'" x2="'.($left+$panel+$gap).'" y2="'.($top+$plotHeight).'"/>
      <polyline class="line" stroke="#1769aa" points="'.implode(' ',$qcPoints).'"/><polyline class="line" stroke="#d97706" points="'.implode(' ',$tfPoints).'"/><polyline class="line" stroke="#7c3aed" points="'.implode(' ',$frPoints).'"/>
      <text class="title" x="'.($left+$panel/2).'" y="20" text-anchor="middle">Tf (kg/cm) — sumbu atas</text>
      <text class="title" x="'.($left+$panel/2).'" y="'.($height-2).'" text-anchor="middle">qc (kg/cm²) — sumbu bawah</text>
      <text class="title" x="'.($left+$panel+$gap+$panel/2).'" y="20" text-anchor="middle">FR (%)</text>
      <text class="title" transform="rotate(-90 16 '.($top+$plotHeight/2).')" x="16" y="'.($top+$plotHeight/2).'" text-anchor="middle">Kedalaman (m)</text>
    </svg>';
    return 'data:image/svg+xml;base64,'.base64_encode($svg);
}

function report_location_uri(array $points): string
{
    $valid=array_values(array_filter($points,fn($p)=>$p['latitude']!==null&&$p['longitude']!==null));
    $w=920;$h=420;$pad=80;
    if(!$valid){
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'"><rect width="100%" height="100%" fill="#eef4f8"/><text x="460" y="210" text-anchor="middle" font-family="DejaVu Sans" font-size="22" fill="#64748b">Koordinat titik sondir belum tersedia</text></svg>';
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
    $lats=array_map(fn($p)=>(float)$p['latitude'],$valid);
    $lngs=array_map(fn($p)=>(float)$p['longitude'],$valid);
    $minLat=min($lats);$maxLat=max($lats);$minLng=min($lngs);$maxLng=max($lngs);
    $latRange=max(.0002,$maxLat-$minLat);$lngRange=max(.0002,$maxLng-$minLng);
    $grid='';
    for($i=0;$i<=8;$i++){
        $x=$pad+($w-2*$pad)*$i/8;$y=$pad+($h-2*$pad)*$i/8;
        $grid.='<line x1="'.$x.'" y1="'.$pad.'" x2="'.$x.'" y2="'.($h-$pad).'"/><line x1="'.$pad.'" y1="'.$y.'" x2="'.($w-$pad).'" y2="'.$y.'"/>';
    }
    $markers='';
    foreach($valid as $point){
        $x=$pad+(((float)$point['longitude']-$minLng+$lngRange*.08)/($lngRange*1.16))*($w-2*$pad);
        $y=$pad+(1-(((float)$point['latitude']-$minLat+$latRange*.08)/($latRange*1.16)))*($h-2*$pad);
        $label='S'.(int)$point['nomor_urut'];
        $markers.='<circle cx="'.$x.'" cy="'.$y.'" r="17" fill="#ef4444" stroke="#fff" stroke-width="5"/><circle cx="'.$x.'" cy="'.$y.'" r="6" fill="#7f1d1d"/><rect x="'.($x+20).'" y="'.($y-21).'" width="105" height="42" rx="8" fill="#fff" stroke="#93a8bb"/><text x="'.($x+30).'" y="'.($y-4).'" font-weight="bold">'.$label.'</text><text x="'.($x+30).'" y="'.($y+13).'" font-size="11">'.number_format((float)$point['latitude'],6,'.','').', '.number_format((float)$point['longitude'],6,'.','').'</text>';
    }
    $svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'"><rect width="100%" height="100%" fill="#e7f0e8"/><path d="M0 85 C220 40 330 150 520 95 S760 40 920 110 V175 C720 130 610 220 420 165 S160 115 0 180Z" fill="#c7ddc7"/><path d="M-20 310 C220 240 380 355 570 285 S810 210 950 270" fill="none" stroke="#fff" stroke-width="26"/><path d="M-20 310 C220 240 380 355 570 285 S810 210 950 270" fill="none" stroke="#aab7c4" stroke-width="3"/><g stroke="#cbd8ce" stroke-width="1">'.$grid.'</g><g font-family="DejaVu Sans,sans-serif" fill="#17324d">'.$markers.'</g><path d="M860 75 L875 25 L890 75 L875 62Z" fill="#173b61"/><text x="875" y="18" text-anchor="middle" font-family="DejaVu Sans" font-weight="bold" fill="#173b61">U</text><rect x="20" y="374" width="510" height="30" rx="6" fill="#fff" opacity=".9"/><text x="35" y="394" font-family="DejaVu Sans" font-size="13" fill="#475569">Diagram sebaran berdasarkan koordinat tersimpan; posisi marker mewakili seluruh titik proyek.</text></svg>';
    return 'data:image/svg+xml;base64,'.base64_encode($svg);
}

function report_pile_capacity(float $qc, float $tf, float $size, bool $circular): float
{
    $dimension=$size*100;
    $area=$circular?M_PI*$dimension**2/4:$dimension**2;
    $perimeter=$circular?M_PI*$dimension:4*$dimension;
    return (($qc*$area/3)+($tf*$perimeter/5))/100;
}

function report_average_qc(array $rows, float $depth): float
{
    $values=[];
    foreach($rows as $row){
        if((float)$row['kedalaman']>=max(0,$depth-.5)&&(float)$row['kedalaman']<=$depth+1)$values[]=(float)$row['qc'];
    }
    return $values?array_sum($values)/count($values):0.0;
}

function report_meyerhof(float $qc, float $width): float
{
    $qa=$width<=1.2?$qc/30:($qc/50)*(($width+.3)/$width)**2;
    return $qa*98.0665*$width*$width;
}

function report_schmertmann(float $qc, string $soil, float $depth, float $width): ?float
{
    if($depth/$width>1.5)return null;
    $cohesive=str_starts_with($soil,'Lempung')||str_starts_with($soil,'Lanau');
    $qu=$cohesive?5+.34*$qc:48-.009*max(0,300-$qc)**1.5;
    return max(0,$qu)/3*98.0665*$width*$width;
}

function build_project_report_html(PDO $pdo, int $projectId): ?array
{
    $data=report_project_data($pdo,$projectId);
    if(!$data)return null;
    ['project'=>$project,'points'=>$points,'lab'=>$lab,'settings'=>$settings]=$data;
    $pointCount=count($points);
    $allRows=array_sum(array_map(fn($p)=>count($p['rows']),$points));
    $testDates=array_values(array_filter(array_column($points,'tanggal_pengujian')));
    $reportDate=$testDates?max($testDates):date('Y-m-d');
    $year=(int)date('Y',strtotime($reportDate));
    $reportNo='SND/'.str_pad((string)$projectId,3,'0',STR_PAD_LEFT).'/LAB-UM.BTN/'.report_roman_month((int)date('n',strtotime($reportDate))).'/'.$year;
    $labName=$settings['kop_nama'];
    $institution=$settings['kop_subjudul'];
    $primary=report_hex_color((string)$settings['warna_utama'],'#173B61');
    $accent=report_hex_color((string)$settings['warna_aksen'],'#F4B400');
    $font=in_array($settings['font_family'],['DejaVu Sans','DejaVu Serif','DejaVu Sans Mono'],true)?$settings['font_family']:'DejaVu Sans';
    $fontSize=max(7.5,min(12,(float)$settings['font_size']));
    $logoPath=$settings['logo_path']?report_documentation_path((string)$settings['logo_path']):null;
    $logoUri=$logoPath?report_image_data_uri($logoPath):null;
    $headerClass='kop-'.(in_array($settings['gaya_kop'],['formal','minimal','balok'],true)?$settings['gaya_kop']:'formal');
    $head=trim((string)($lab['kepala_laboratorium']??''));
    if($head===''||mb_strtolower($head)==='kepala laboratorium')$head='MUHAMMAD ABDU, S.T., M.T';
    $operator=$project['operator_proyek']??($points[0]['operator']??'-');
    $examiner=$project['pemeriksa_proyek']??($points[0]['pemeriksa']??$head);
    $address=$project['alamat_lokasi']?:implode(', ',array_filter([$project['desa'],$project['kecamatan'],$project['kabupaten'],$project['provinsi']]));
    $client=$project['nama_perusahaan']?:$project['nama_klien'];
    $status=report_status($points);
    $stats=array_map(fn($point)=>report_point_stats($point),$points);
    $deepest=$stats?max(array_column($stats,'depth')):0;
    $qcMax=$stats?max(array_column($stats,'qcMax')):0;
    $legalToken=report_legal_token($project,$reportNo);
    $legalQr=report_qr_data_uri(report_verification_url($legalToken,'report'));
    $signerQr=report_qr_data_uri(report_verification_url($legalToken,'signer'));

    ob_start();
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
@page{margin:25mm 15mm 18mm 15mm}
*{box-sizing:border-box}body{font-family:'<?=e($font)?>',sans-serif;color:#1f3347;font-size:<?=$fontSize?>pt;line-height:1.48;margin:0;width:100%}
.running-header{position:fixed;top:-20mm;left:0;right:0;height:16mm;border-bottom:2px solid <?=$primary?>;color:<?=$primary?>;width:100%}
.running-header .kop-text{position:absolute;left:<?=$logoUri?'15mm':'0'?>;top:1mm;right:48mm;line-height:1.15}.running-header strong{display:block;font-size:9.5pt}.running-header small{display:block;font-size:6.8pt;color:#526577;margin-top:1mm}.running-header .project-name{position:absolute;right:0;top:2mm;width:45mm;text-align:right;font-size:7pt;color:#64748b}
.running-header .kop-logo{position:absolute;left:0;top:0;width:12mm;height:12mm;object-fit:contain}.running-header.kop-balok{background:<?=$primary?>;color:#fff;padding:1.5mm 2mm;border:0}.running-header.kop-balok small,.running-header.kop-balok .project-name{color:#e8f1f7}.running-header.kop-minimal{border-bottom-width:1px}
.running-footer{position:fixed;bottom:-13mm;left:0;right:0;border-top:1px solid #a9bac8;padding-top:3mm;font-size:7.5pt;color:#526577}
.running-footer .right{position:absolute;right:0;top:3mm}.page{page-break-before:always;width:100%;clear:both}.cover{page-break-before:auto;height:225mm;position:relative;padding:12mm 8mm;background:#fff;width:100%;clear:both}
.cover-band{height:16mm;background:<?=$primary?>;margin:-12mm -8mm 20mm}.cover-accent{width:38mm;height:5mm;background:<?=$accent?>;margin-bottom:8mm}
.cover h1{font-size:27pt;line-height:1.12;color:<?=$primary?>;margin:0 0 3mm}.cover h2{font-size:15pt;margin:0;color:<?=$primary?>}.cover-project{margin-top:18mm;padding:8mm;border-left:5px solid <?=$accent?>;background:#eef5fb}
.cover-project strong{display:block;font-size:17pt;color:<?=$primary?>}.cover-meta{margin-top:13mm;width:100%;border-collapse:collapse}.cover-meta td{padding:2.5mm 2mm;border-bottom:1px solid #dce5ec}
.cover-bottom{position:absolute;left:8mm;right:8mm;bottom:12mm;background:<?=$primary?>;color:#fff;padding:7mm}.cover-bottom b{font-size:12pt}.cover-bottom span{position:absolute;right:7mm;top:7mm;font-size:20pt;font-weight:bold}
h1.chapter{font-size:17pt;text-align:center;color:<?=$primary?>;margin:0 0 8mm;text-transform:uppercase}h2{font-size:13pt;color:<?=$primary?>;margin:7mm 0 3mm}h3{font-size:11pt;color:<?=$primary?>;margin:5mm 0 2mm}
p{text-align:justify;margin:0 0 3mm}.lead{font-size:10.5pt}.center{text-align:center}.muted{color:#64748b}.small{font-size:8pt}
.section-title{border-bottom:2px solid <?=$primary?>;padding-bottom:2mm;margin-bottom:6mm}.toc{width:100%;border-collapse:collapse}.toc td{padding:2.2mm;border-bottom:1px dotted #9cafbf}.toc .bab{font-weight:bold;color:<?=$primary?>}
.info{width:100%;border-collapse:collapse;margin:3mm 0 5mm}.info td{padding:2mm 2.5mm;border:1px solid #ccd8e2;vertical-align:top}.info td:first-child,.info td:nth-child(3){background:#eef4f8;font-weight:bold;width:18%}
.summary-grid{width:100%;border-collapse:separate;border-spacing:3mm}.summary-grid td{width:25%;padding:4mm;background:#eef5fb;border-top:3px solid #1769aa;text-align:center}.summary-grid b{display:block;font-size:16pt;color:#173b61}
.map{width:100%;height:auto;border:1px solid #bacbd8}.figure-caption{text-align:center;font-size:8pt;margin:2mm 0 5mm;color:#526577}
.data-table,.foundation-table,.soil-table{width:100%;border-collapse:collapse;page-break-inside:auto}.data-table thead,.foundation-table thead{display:table-header-group}.data-table tr,.foundation-table tr{page-break-inside:avoid}.data-table th,.data-table td{border:1px solid #8ea5b6;padding:1.2mm .8mm;text-align:center;font-size:6.4pt}.data-table th{background:#dceaf5;color:<?=$primary?>}.data-table .soil{text-align:left;font-size:5.8pt}.data-table .soil-layer-pdf{border-top:0;border-bottom:0;font-weight:700;text-align:center;color:#111827;text-shadow:0 1px 0 #fff;padding:0 1mm}.data-table .soil-layer-start{border-top:1.5px solid #526577!important}.data-table .soil-layer-end{border-bottom:1.5px solid #526577!important}
.unit{display:block;font-size:5pt;font-weight:normal;color:#526577}.chart{width:100%;height:auto}.point-head{padding:4mm;background:<?=$primary?>;color:#fff;margin-bottom:4mm;position:relative}.point-head b{font-size:13pt}.point-head span{position:absolute;right:4mm;top:4mm}
.soil-table th,.soil-table td{border:1px solid #a9bbc8;padding:2mm}.soil-table th{background:#e8f1f7}.soil-swatch{border-left:6px solid #f4b400}
.formula{padding:3mm 4mm;margin:3mm 0;background:#f5f8fb;border-left:4px solid <?=$primary?>;font-family:'<?=e($font)?>'}.foundation-table th,.foundation-table td{border:1px solid #879dac;text-align:center;padding:.8mm .5mm;font-size:5.2pt}.foundation-table th{background:#dfeaf3}.foundation-table .group{background:#c9dded;font-size:6.2pt}.foundation-note{font-size:7pt;margin-top:2mm;color:#526577}
.approval{width:100%;border-collapse:collapse;margin-top:14mm}.approval td{width:50%;text-align:center;padding:3mm}.signature-space{height:25mm}.signer-qr-wrap{height:27mm;text-align:center;padding-top:2mm}.signer-qr{width:23mm;height:23mm;display:inline-block}.signer-name{display:block;clear:both;text-align:center;margin-top:1mm}.legal-box{margin-top:8mm;border:1px solid #9db1c1;padding:4mm;min-height:38mm}.legal-box img{width:30mm;height:30mm;float:left;margin-right:5mm}.legal-box b{color:<?=$primary?>}.legal-code{font-family:'DejaVu Sans Mono';font-size:7pt;letter-spacing:.5px}.photo-grid{width:100%;border-collapse:separate;border-spacing:3mm}.photo-grid td{width:50%;border:1px solid #cbd6df;padding:2mm;vertical-align:top}.photo-grid img{width:100%;max-height:86mm;object-fit:contain}.empty-photo{height:70mm;background:#eef3f6;text-align:center;padding-top:30mm;color:#718294}
.groundwater-pdf{position:relative;border-top:3px double #087ea4!important}.groundwater-pdf:after{content:'Muka air tanah';position:absolute;right:1mm;top:-3.3mm;background:#087ea4;color:#fff;padding:.4mm 1mm;font-size:4.8pt;font-weight:bold}
.badge{display:inline-block;padding:1.5mm 3mm;border-radius:8mm;background:#e3edf5;color:#173b61;font-size:8pt}.warning{padding:3mm;background:#fff5cc;border:1px solid #e8c64b}
ul,ol{margin:2mm 0 3mm 6mm;padding-left:5mm}li{margin-bottom:1.5mm}
</style>
</head>
<body>
<div class="running-header <?=e($headerClass)?>"><?php if($logoUri):?><img class="kop-logo" src="<?=$logoUri?>" alt="Logo"><?php endif;?><div class="kop-text"><strong><?=e($settings['kop_nama'])?></strong><small><?=e(trim($settings['kop_subjudul'].' · '.$settings['kop_alamat'],' ·'))?></small></div><div class="project-name"><?=e($project['nama_proyek'])?></div></div>
<div class="running-footer"><b><?=e($settings['footer_text']?:$labName.' · '.$institution)?></b><span class="right"><?=e($reportNo)?></span></div>

<section class="cover">
  <div class="cover-band"></div><div class="cover-accent"></div>
  <h1><?=e($settings['judul_laporan'])?></h1><h2>UJI SONDIR / CONE PENETRATION TEST</h2>
  <div class="cover-project"><span>PEKERJAAN</span><strong><?=e($project['nama_proyek'])?></strong><div><?=e($address?:'Alamat lokasi belum diisi')?></div></div>
  <table class="cover-meta">
    <tr><td>Nomor laporan</td><td><b><?=e($reportNo)?></b></td></tr>
    <tr><td>Pemohon</td><td><?=e($client)?></td></tr>
    <tr><td>Jumlah titik sondir</td><td><b><?=$pointCount?> titik</b></td></tr>
    <tr><td>Tanggal pengujian</td><td><?=e(tanggal_id($reportDate))?></td></tr>
    <tr><td>Status data</td><td><?=e(ucwords(str_replace('_',' ',$status)))?></td></tr>
  </table>
  <div class="cover-bottom"><b><?=e($labName)?><br><?=e($institution)?></b><span><?=$year?></span></div>
</section>

<section class="page">
  <h1 class="chapter section-title">Kata Pengantar</h1>
  <p class="lead">Puji syukur kepada Tuhan Yang Maha Esa atas terlaksananya penyelidikan tanah dengan metode uji sondir untuk pekerjaan <b><?=e($project['nama_proyek'])?></b>.</p>
  <p>Pengujian dilakukan pada <?=$pointCount?> titik yang mewakili area proyek. Laporan ini menyatukan identitas proyek, lokasi seluruh titik, metode pelaksanaan, data pengujian, grafik, interpretasi lapisan tanah, analisis daya dukung pondasi, kesimpulan, rekomendasi, pengesahan, dan dokumentasi dalam satu dokumen proyek.</p>
  <p>Terima kasih disampaikan kepada <?=e($client)?> selaku pemohon, tim pelaksana lapangan, pemeriksa, serta seluruh pihak yang mendukung pelaksanaan pengujian. Hasil dalam laporan ini berlaku untuk lokasi dan kondisi tanah pada saat pengujian.</p>
  <table class="approval"><tr><td></td><td><?=e(($lab['kabupaten']??'Baubau').', '.tanggal_id(date('Y-m-d')))?><br>Hormat kami,<br><?=e($labName)?><div class="signature-space"></div><b><?=e($head)?></b><br>Kepala Laboratorium</td></tr></table>
</section>

<section class="page">
  <h1 class="chapter section-title">Ringkasan</h1>
  <p>Penyelidikan tanah untuk <b><?=e($project['nama_proyek'])?></b> dilaksanakan di <?=e($address?:'-')?> menggunakan metode sondir sesuai prinsip SNI 2827:2008. Sebanyak <b><?=$pointCount?> titik</b> diuji dengan total <b><?=$allRows?> data kedalaman</b>.</p>
  <table class="summary-grid"><tr><td><b><?=$pointCount?></b>Titik sondir</td><td><b><?=report_number($deepest,2)?> m</b>Kedalaman maksimum</td><td><b><?=report_number($qcMax,0)?></b>qc maksimum (kg/cm²)</td><td><b><?=$allRows?></b>Baris data</td></tr></table>
  <h2>Ringkasan setiap titik</h2>
  <table class="soil-table"><thead><tr><th>Titik</th><th>Koordinat</th><th>Kedalaman</th><th>qc maks.</th><th>Lapisan akhir</th><th>Tanah keras</th></tr></thead><tbody>
  <?php foreach($points as $i=>$point):$stat=$stats[$i];?><tr><td><b>S<?=e($point['nomor_urut'])?></b><br><?=e($point['nama_titik']?:$point['kode_titik'])?></td><td><?=e(($point['latitude']??'-').', '.($point['longitude']??'-'))?></td><td><?=report_number($stat['depth'],2)?> m</td><td><?=report_number($stat['qcMax'],0)?> kg/cm²</td><td><?=e($stat['soil'])?></td><td><?=$stat['hardDepth']===null?'Belum teridentifikasi':report_number($stat['hardDepth'],2).' m'?></td></tr><?php endforeach;?>
  </tbody></table>
  <div class="warning"><b>Catatan teknis:</b> pemilihan jenis, dimensi, dan kedalaman pondasi wajib diverifikasi oleh perencana struktur/geoteknik berdasarkan beban bangunan, penurunan yang diizinkan, kondisi muka air tanah, dan ketentuan proyek.</div>
</section>

<section class="page">
  <h1 class="chapter section-title">Daftar Isi</h1>
  <table class="toc">
    <tr><td>Kata Pengantar</td></tr><tr><td>Ringkasan</td></tr><tr><td>Daftar Isi</td></tr>
    <tr><td class="bab">BAB I — Pendahuluan</td></tr><tr><td>1.1 Latar Belakang</td></tr><tr><td>1.2 Tujuan Pengujian</td></tr><tr><td>1.3 Manfaat</td></tr>
    <tr><td class="bab">BAB II — Dasar Teori</td></tr><tr><td>2.1 Pengujian Sondir</td></tr><tr><td>2.2 Perhitungan Daya Dukung</td></tr>
    <tr><td class="bab">BAB III — Metodologi Pengujian</td></tr><tr><td>3.1 Lokasi dan Sebaran Titik</td></tr><tr><td>3.2 Peralatan</td></tr><tr><td>3.3 Prosedur</td></tr>
    <tr><td class="bab">BAB IV — Hasil Penyelidikan dan Analisis</td></tr>
    <?php foreach($points as $point):?><tr><td>4.<?=e($point['nomor_urut'])?> Data, Grafik, dan Analisis Sondir S<?=e($point['nomor_urut'])?></td></tr><?php endforeach;?>
    <tr><td class="bab">BAB V — Penutup</td></tr><tr><td>Halaman Pengesahan</td></tr><tr><td>Lampiran Dokumentasi</td></tr>
  </table>
</section>

<section class="page">
  <h1 class="chapter">BAB I<br>Pendahuluan</h1>
  <h2>1.1 Latar Belakang</h2>
  <p>Perencanaan pondasi memerlukan informasi kondisi tanah yang memadai agar beban struktur dapat diteruskan dengan aman. Variasi lapisan tanah yang tidak teridentifikasi dapat menimbulkan penurunan berlebih, ketidakstabilan, atau kegagalan pondasi. Oleh karena itu, investigasi geoteknik diperlukan sebelum penetapan sistem pondasi.</p>
  <p>Uji sondir memberikan profil tahanan konus dan hambatan lekat secara kontinu terhadap kedalaman. Data tersebut digunakan untuk memperkirakan stratifikasi, kekuatan relatif tanah, serta korelasi awal daya dukung pondasi.</p>
  <h2>1.2 Tujuan Pengujian</h2>
  <ol><li>Mengetahui perubahan tahanan tanah terhadap kedalaman pada seluruh titik proyek.</li><li>Memperkirakan jenis dan konsistensi/kepadatan lapisan tanah.</li><li>Menyediakan data untuk analisis awal daya dukung pondasi dangkal dan pondasi tiang.</li><li>Memberikan rekomendasi teknis awal bagi perencana.</li></ol>
  <h2>1.3 Manfaat</h2>
  <p>Hasil penyelidikan menjadi masukan bagi pemilik, perencana, dan pelaksana dalam memilih alternatif pondasi, menentukan kedalaman rencana, serta mengidentifikasi kebutuhan penyelidikan lanjutan.</p>
</section>

<section class="page">
  <h1 class="chapter">BAB II<br>Dasar Teori</h1>
  <h2>2.1 Pengujian Sondir</h2>
  <p>Alat sondir merupakan penetrometer statis. Ujung konus ditekan ke dalam tanah pada kecepatan terkendali dan pembacaan dilakukan pada interval kedalaman tertentu. Pada bikonus, pembacaan konus (<i>Cw</i>) dan pembacaan total (<i>Tw</i>) digunakan untuk menghitung hambatan lekat, tahanan konus <i>qc</i>, hambatan lekat setempat <i>fs</i>, total friksi <i>Tf</i>, serta rasio friksi <i>FR</i>.</p>
  <div class="formula"><b>Kw = Tw − Cw</b><br><b>fs = Kw × (Api/As)</b><br><b>Tf = Σ(fs × interval)</b><br><b>FR = fs/qc × 100%</b></div>
  <h2>2.2 Daya Dukung Pondasi</h2>
  <h3>Pondasi tiang — korelasi Meyerhof</h3>
  <div class="formula">Q<sub>izin</sub> = (qc × A<sub>b</sub> / 3 + Tf × K / 5) / 100 &nbsp; [kN]</div>
  <p>qc adalah tahanan ujung konus, Tf adalah total friksi, A<sub>b</sub> luas ujung tiang, dan K keliling penampang.</p>
  <h3>Pondasi dangkal — Meyerhof (1956)</h3>
  <div class="formula">B ≤ 1,20 m: q<sub>a</sub> = q̄c / 30<br>B &gt; 1,20 m: q<sub>a</sub> = q̄c/50 × ((B + 0,30)/B)²</div>
  <h3>Pondasi dangkal — Schmertmann (1978)</h3>
  <div class="formula">Lempung/lanau: q<sub>u</sub> = 5 + 0,34qc<br>Pasir: q<sub>u</sub> = 48 − 0,009(300 − qc)<sup>1,5</sup><br>q<sub>a</sub> = q<sub>u</sub>/3, dengan syarat Df/B ≤ 1,5</div>
</section>

<section class="page">
  <h1 class="chapter">BAB III<br>Metodologi Pengujian</h1>
  <h2>3.1 Lokasi dan Sebaran Titik</h2>
  <p>Pengujian dilaksanakan di <?=e($address?:'-')?>. Seluruh titik pada proyek ditampilkan pada diagram koordinat berikut.</p>
  <img class="map" src="<?=report_location_uri($points)?>" alt="Peta sebaran titik sondir"><div class="figure-caption">Gambar 3.1 Sebaran <?=$pointCount?> titik sondir dalam satu proyek</div>
  <table class="soil-table"><tr><th>Titik</th><th>Nama titik</th><th>Latitude</th><th>Longitude</th><th>Alamat/deskripsi</th></tr><?php foreach($points as $point):?><tr><td>S<?=e($point['nomor_urut'])?></td><td><?=e($point['nama_titik']?:'-')?></td><td><?=e($point['latitude']??'-')?></td><td><?=e($point['longitude']??'-')?></td><td><?=e($point['alamat_lokasi']?:$point['deskripsi_posisi'])?></td></tr><?php endforeach;?></table>
  <h2>3.2 Peralatan</h2>
  <?php $tools=[];foreach($points as $point)$tools[(int)$point['alat_id']]=$point;foreach($tools as $tool):?>
  <table class="info"><tr><td>Kode / alat</td><td><?=e($tool['kode_alat'].' — '.$tool['nama_alat'])?></td><td>Merek / model</td><td><?=e(trim($tool['merek'].' '.$tool['model']))?></td></tr><tr><td>Kapasitas</td><td><?=e(report_number((float)$tool['kapasitas_maksimum'],2).' '.$tool['satuan_kapasitas'])?></td><td>Nomor seri</td><td><?=e($tool['nomor_seri']?:'-')?></td></tr><tr><td>Dimensi</td><td colspan="3">Piston <?=report_number((float)$tool['diameter_piston'],3)?> cm; konus <?=report_number((float)$tool['diameter_konus'],3)?> cm; selimut <?=report_number((float)$tool['diameter_selimut'],3)?> cm; panjang selimut <?=report_number((float)$tool['panjang_selimut_geser'],3)?> cm.</td></tr><tr><td>Kalibrasi</td><td><?=e($tool['nomor_sertifikat']?:'-')?></td><td>Berlaku sampai</td><td><?=e(tanggal_id($tool['tanggal_kedaluwarsa']))?></td></tr></table>
  <?php endforeach;?>
  <h2>3.3 Prosedur Pengujian</h2>
  <ol><li>Menentukan dan menyiapkan titik uji, kemudian memasang alat secara tegak.</li><li>Menekan konus pada kecepatan terkendali dan melakukan pembacaan Cw serta Tw pada interval tersimpan.</li><li>Menghitung qc, fs, Tf, dan FR serta memeriksa kewajaran data.</li><li>Menghentikan pengujian pada kedalaman rencana, kapasitas alat, atau lapisan sangat keras.</li><li>Menginterpretasikan profil tanah dan korelasi daya dukung.</li></ol>
</section>

<section class="page"><h1 class="chapter">BAB IV<br>Hasil Penyelidikan Tanah dan Analisis</h1><p>Hasil disajikan per titik dalam satu laporan proyek. Sumbu vertikal pada grafik menunjukkan kedalaman dan bertambah ke arah bawah; grafik gabungan menampilkan Tf pada sumbu horizontal atas dan qc pada sumbu horizontal bawah.</p></section>

<?php foreach($points as $point):$stat=report_point_stats($point);$rows=$point['rows'];$soilCells=$rows?report_soil_cells($rows):[];$waterRow=report_water_row_index($rows,$point['muka_air_tanah']);$rowChunks=$rows?array_chunk($rows,15):[[]];?>
<?php foreach($rowChunks as $chunkIndex=>$rowChunk):?>
<section class="page">
  <div class="point-head"><b>4.<?=e($point['nomor_urut'])?> DATA SONDIR S<?=e($point['nomor_urut'])?></b><span><?=e($point['kode_titik'])?> · Bagian <?=$chunkIndex+1?>/<?=count($rowChunks)?></span></div>
  <?php if($chunkIndex===0):?>
  <table class="info"><tr><td>Nama titik</td><td><?=e($point['nama_titik']?:'Sondir '.$point['nomor_urut'])?></td><td>Tanggal</td><td><?=e(tanggal_id($point['tanggal_pengujian']))?></td></tr><tr><td>Koordinat</td><td><?=e(($point['latitude']??'-').', '.($point['longitude']??'-'))?></td><td>Operator</td><td><?=e($point['operator'])?></td></tr><tr><td>Alat</td><td><?=e($point['kode_alat'].' — '.$point['nama_alat'])?></td><td>Kedalaman</td><td><?=report_number($stat['depth'],2)?> m</td></tr><tr><td>Muka air</td><td><?=e($point['muka_air_tanah']!==null?report_number((float)$point['muka_air_tanah'],2).' m':'Tidak teramati')?></td><td>Cuaca</td><td><?=e($point['kondisi_cuaca']?:'-')?></td></tr></table>
  <?php else:?><p class="muted small">Lanjutan data S<?=e($point['nomor_urut'])?> · kedalaman <?=report_number((float)$rowChunk[0]['kedalaman'],2)?>–<?=report_number((float)end($rowChunk)['kedalaman'],2)?> m.</p><?php endif;?>
  <?php if(!$rows):?><div class="warning">Data pengujian untuk titik ini belum diisi.</div>
  <?php else:?>
  <table class="data-table"><thead><tr><th>No</th><th>Z<span class="unit">m</span></th><th>Cw<span class="unit">kg/cm²</span></th><th>Tw<span class="unit">kg/cm²</span></th><th>Kw<span class="unit">kg/cm²</span></th><th>qc<span class="unit">kg/cm²</span></th><th>fs<span class="unit">kg/cm²</span></th><th>fs·20<span class="unit">kg/cm</span></th><th>Tf<span class="unit">kg/cm</span></th><th>FR<span class="unit">%</span></th><th>Perkiraan jenis tanah</th></tr></thead><tbody>
  <?php foreach($rowChunk as $localIndex=>$row):$globalIndex=$chunkIndex*15+$localIndex;$soilCell=$soilCells[$globalIndex];?><tr><td><?=e($row['nomor'])?></td><td><?=report_number((float)$row['kedalaman'],3)?></td><td><?=report_number((float)$row['bacaan_konus'],0)?></td><td><?=report_number((float)$row['bacaan_total'],0)?></td><td><?=report_number((float)$row['hambatan_total'],0)?></td><td><?=report_number((float)$row['qc'],0)?></td><td><?=report_number((float)$row['fs'],3)?></td><td><?=report_number((float)$row['fs']*20,2)?></td><td><?=report_number((float)$row['jhp'],2)?></td><td><?=report_number((float)$row['friction_ratio'],2)?></td><td class="soil-layer-pdf <?=$soilCell['start']?'soil-layer-start':''?> <?=$soilCell['end']?'soil-layer-end':''?> <?=$waterRow===$globalIndex?'groundwater-pdf':''?>" style="background-color:<?=$soilCell['background']?>;<?=$soilCell['image']?'background-image:url('.$soilCell['image'].');':''?>"><?=e($soilCell['label'])?></td></tr><?php endforeach;?>
  </tbody></table>
  <?php endif;?>
</section>
<?php endforeach;?>
<?php if($rows):?>
<section class="page">
  <div class="point-head"><b>GRAFIK DAN PROFIL S<?=e($point['nomor_urut'])?></b><span><?=e($point['kode_titik'])?></span></div>
  <img class="chart" src="<?=report_chart_uri($rows)?>" alt="Grafik qc, Tf, dan FR"><div class="figure-caption">Grafik qc dan Tf serta FR terhadap kedalaman — S<?=e($point['nomor_urut'])?></div>
  <h2>Interpretasi lapisan</h2><table class="soil-table"><tr><th>Kedalaman</th><th>Rentang qc</th><th>Perkiraan jenis dan konsistensi/kepadatan</th></tr><?php foreach(report_soil_intervals($rows) as $segment):?><tr><td><?=report_number($segment['from'],2)?>–<?=report_number($segment['to'],2)?> m</td><td><?=report_number($segment['qc_min'],0)?>–<?=report_number($segment['qc_max'],0)?> kg/cm²</td><td class="soil-swatch"><?=e($segment['label'])?></td></tr><?php endforeach;?></table>
</section>

<section class="page">
  <div class="point-head"><b>DAYA DUKUNG PONDASI TIANG — S<?=e($point['nomor_urut'])?></b><span>Meyerhof</span></div>
  <div class="formula">Q<sub>izin</sub> = (qc × A<sub>b</sub>/3 + Tf × K/5) / 100 [kN]</div>
  <table class="foundation-table"><thead><tr><th rowspan="3">Df<span class="unit">m</span></th><th rowspan="3">qc<span class="unit">kg/cm²</span></th><th rowspan="3">Tf<span class="unit">kg/cm</span></th><th colspan="8" class="group">Daya dukung izin satu tiang (kN)</th></tr><tr><th colspan="4">Mini pile kotak (m)</th><th colspan="4">Strauss pile (m)</th></tr><tr><?php foreach([.2,.25,.3,.35,.2,.25,.3,.35] as $size):?><th><?=report_number($size,2)?></th><?php endforeach;?></tr></thead><tbody>
  <?php foreach($rows as $row):?><tr><td><?=report_number((float)$row['kedalaman'],2)?></td><td><?=report_number((float)$row['qc'],0)?></td><td><?=report_number((float)$row['jhp'],2)?></td><?php foreach([.2,.25,.3,.35] as $size):?><td><?=report_number(report_pile_capacity((float)$row['qc'],(float)$row['jhp'],$size,false),2)?></td><?php endforeach;?><?php foreach([.2,.25,.3,.35] as $size):?><td><?=report_number(report_pile_capacity((float)$row['qc'],(float)$row['jhp'],$size,true),2)?></td><?php endforeach;?></tr><?php endforeach;?>
  </tbody></table><div class="foundation-note">Mini pile menggunakan penampang kotak; Strauss pile menggunakan penampang lingkaran. Faktor keamanan ujung = 3 dan selimut = 5.</div>
</section>

<?php $widths=[.5,.6,.7,.8,.9,1,1.1,1.2,1.3,1.4,1.5,1.6,1.7,1.8,1.9,2];?>
<section class="page">
  <div class="point-head"><b>PONDASI DANGKAL — S<?=e($point['nomor_urut'])?></b><span>Meyerhof (1956)</span></div>
  <div class="formula">B ≤ 1,20 m: qa = q̄c/30. B &gt; 1,20 m: qa = q̄c/50 × ((B + 0,30)/B)².</div>
  <table class="foundation-table"><thead><tr><th rowspan="2">Df<span class="unit">m</span></th><th rowspan="2">q̄c<span class="unit">kg/cm²</span></th><th colspan="<?=count($widths)?>" class="group">P izin (kN) untuk lebar pondasi B = L (m)</th></tr><tr><?php foreach($widths as $width):?><th><?=report_number($width,1)?></th><?php endforeach;?></tr></thead><tbody>
  <?php foreach($rows as $row):$average=report_average_qc($rows,(float)$row['kedalaman']);?><tr><td><?=report_number((float)$row['kedalaman'],2)?></td><td><?=report_number($average,1)?></td><?php foreach($widths as $width):?><td><?=report_number(report_meyerhof($average,$width),1)?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table>
</section>

<section class="page">
  <div class="point-head"><b>PONDASI DANGKAL — S<?=e($point['nomor_urut'])?></b><span>Schmertmann (1978)</span></div>
  <div class="formula">Lempung/lanau: qu = 5 + 0,34qc. Pasir: qu = 48 − 0,009(300 − qc)<sup>1,5</sup>. qa = qu/3; Df/B ≤ 1,5.</div>
  <table class="foundation-table"><thead><tr><th rowspan="2">Df<span class="unit">m</span></th><th rowspan="2">qc<span class="unit">kg/cm²</span></th><th rowspan="2">Jenis</th><th colspan="<?=count($widths)?>" class="group">P izin (kN) untuk lebar pondasi B = L (m)</th></tr><tr><?php foreach($widths as $width):?><th><?=report_number($width,1)?></th><?php endforeach;?></tr></thead><tbody>
  <?php foreach($rows as $row):?><tr><td><?=report_number((float)$row['kedalaman'],2)?></td><td><?=report_number((float)$row['qc'],0)?></td><td><?=e(str_starts_with((string)$row['jenis_tanah'],'Lempung')||str_starts_with((string)$row['jenis_tanah'],'Lanau')?'Kohesif':'Pasir')?></td><?php foreach($widths as $width):$capacity=report_schmertmann((float)$row['qc'],(string)$row['jenis_tanah'],(float)$row['kedalaman'],$width);?><td><?=$capacity===null?'—':report_number($capacity,1)?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table>
</section>
<?php endif;endforeach;?>

<section class="page">
  <h1 class="chapter">BAB V<br>Penutup</h1><h2>5.1 Kesimpulan</h2>
  <p>Penyelidikan tanah pada proyek <?=e($project['nama_proyek'])?> telah dilaksanakan pada <?=$pointCount?> titik. Rangkuman kondisi setiap titik adalah sebagai berikut:</p>
  <table class="soil-table"><tr><th>Titik</th><th>Kedalaman uji</th><th>qc maksimum</th><th>Indikasi tanah keras</th><th>Lapisan akhir</th><th>Muka air</th></tr><?php foreach($points as $i=>$point):$stat=$stats[$i];?><tr><td>S<?=e($point['nomor_urut'])?></td><td><?=report_number($stat['depth'],2)?> m</td><td><?=report_number($stat['qcMax'],0)?> kg/cm²</td><td><?=$stat['hardDepth']===null?'Belum teridentifikasi':report_number($stat['hardDepth'],2).' m'?></td><td><?=e($stat['soil'])?></td><td><?=$point['muka_air_tanah']===null?'Tidak teramati':report_number((float)$point['muka_air_tanah'],2).' m'?></td></tr><?php endforeach;?></table>
  <h2>5.2 Rekomendasi</h2>
  <ol><li>Gunakan tabel daya dukung sebagai korelasi awal; dimensi akhir pondasi ditentukan dari reaksi struktur dan pemeriksaan penurunan.</li><li>Untuk pondasi dangkal, pilih lapisan yang seragam dan memenuhi syarat kedalaman/lebar metode yang digunakan.</li><li>Untuk beban besar atau lapisan atas yang lemah/tidak seragam, evaluasi pondasi tiang pada kedalaman yang memiliki qc dan Tf memadai.</li><li>Lakukan verifikasi geoteknik lanjutan bila terdapat perbedaan elevasi, urugan, muka air, atau kondisi lapangan yang tidak terwakili oleh titik sondir.</li></ol>
  <div class="warning"><b>Batasan:</b> interpretasi jenis tanah dari sondir merupakan korelasi empiris. Identifikasi visual dan parameter desain sebaiknya dikonfirmasi dengan boring, sampling, dan pengujian laboratorium bila tingkat risiko proyek memerlukannya.</div>
</section>

<section class="page">
  <h1 class="chapter section-title">Halaman Pengesahan</h1>
  <h2 class="center">LAPORAN HASIL UJI SONDIR<br>(CONE PENETRATION TEST)</h2>
  <p class="center">Laporan ini memuat seluruh <?=$pointCount?> titik sondir pada satu proyek dan telah disusun oleh <?=e($labName)?>, <?=e($institution)?>.</p>
  <table class="info"><tr><td>Nomor laporan</td><td><?=e($reportNo)?></td><td>Jumlah titik</td><td><?=$pointCount?> titik</td></tr><tr><td>Proyek</td><td><?=e($project['nama_proyek'])?></td><td>Tanggal uji</td><td><?=e(tanggal_id($reportDate))?></td></tr><tr><td>Lokasi</td><td colspan="3"><?=e($address?:'-')?></td></tr></table>
  <table class="approval"><tr><td>Pelaksana Geoteknik<div class="signature-space"></div><b><?=e($operator?:'-')?></b></td><td><?=e(($lab['kabupaten']??'Baubau').', '.tanggal_id(date('Y-m-d')))?><br>Kepala Laboratorium / Pemeriksa<div class="signer-qr-wrap"><img class="signer-qr" src="<?=$signerQr?>" alt="QR penanda tangan Kepala Laboratorium"></div><div class="signer-name"><b><?=e($head)?></b><br><?=e($examiner&&$examiner!==$head?'Pemeriksa: '.$examiner:'')?></div></td></tr></table>
  <div class="legal-box"><img src="<?=$legalQr?>" alt="QR legalitas laporan"><b>LEGALITAS LAPORAN ELEKTRONIK</b><p>Pindai kode ini untuk memeriksa nomor laporan, proyek, status data, dan identitas penerbit. QR legalitas ini berbeda dari QR penanda tangan Kepala Laboratorium.</p><div class="legal-code">KODE: <?=e($legalToken)?></div></div>
</section>

<section class="page">
  <h1 class="chapter section-title">Lampiran Dokumentasi</h1>
  <?php $documents=[];foreach($points as $point)foreach($point['documentation'] as $doc)$documents[]=[$point,$doc];?>
  <?php if(!$documents):?><div class="empty-photo">Belum ada dokumentasi foto yang tersimpan untuk proyek ini.</div><p class="center muted">Lampiran akan terisi otomatis setelah foto ditambahkan pada titik sondir.</p>
  <?php else:?><table class="photo-grid"><?php foreach(array_chunk($documents,2) as $pair):?><tr><?php foreach($pair as [$point,$doc]):$path=report_documentation_path((string)$doc['nama_file']);?><td><?php if($path&&($uri=report_image_data_uri($path))):?><img src="<?=$uri?>" alt="<?=e($doc['judul'])?>"><?php else:?><div class="empty-photo">Berkas foto tidak ditemukan</div><?php endif;?><b>S<?=e($point['nomor_urut'])?> — <?=e($doc['judul']?:$doc['jenis_foto'])?></b><br><span class="small"><?=e($doc['keterangan']?:'Dokumentasi pengujian lapangan')?></span></td><?php endforeach;?><?php if(count($pair)===1):?><td></td><?php endif;?></tr><?php endforeach;?></table><?php endif;?>
</section>
</body></html>
    <?php
    return ['html'=>(string)ob_get_clean(),'filename'=>'Laporan-Proyek-'.$project['kode_proyek'].'.pdf','number'=>$reportNo,'project'=>$project,'points'=>$points];
}
