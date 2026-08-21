<?php
declare(strict_types=1);

function report_number(float $value, int $decimals=2): string
{
    $formatted=number_format($value,$decimals,',','.');
    return $decimals>0?rtrim(rtrim($formatted,'0'),','):$formatted;
}

function report_pdf_destination_pages(\Dompdf\Dompdf $dompdf): array
{
    $canvas=$dompdf->getCanvas();
    if(!method_exists($canvas,'get_cpdf'))return [];
    $pdf=$canvas->get_cpdf();$pageIds=[];
    foreach($pdf->objects as $object){
        if(($object['t']??'')==='pages'&&count($object['info']['pages']??[])>count($pageIds))$pageIds=$object['info']['pages'];
    }
    $pageNumbers=[];foreach($pageIds as $index=>$id)$pageNumbers[(int)$id]=$index+1;
    $destinations=[];
    foreach($pdf->destinations as $name=>$destinationId){
        $pageId=(int)($pdf->objects[$destinationId]['info']['page']??0);
        if(isset($pageNumbers[$pageId]))$destinations[(string)$name]=$pageNumbers[$pageId];
    }
    return $destinations;
}

function report_toc_number(array $pages, string $anchor): string
{
    return isset($pages[$anchor])?(string)$pages[$anchor]:'-';
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
    $columns=[
        'font_body_family'=>"VARCHAR(50) DEFAULT 'DejaVu Sans' AFTER font_size",
        'font_heading_family'=>"VARCHAR(50) DEFAULT 'DejaVu Sans' AFTER font_body_family",
        'font_table_family'=>"VARCHAR(50) DEFAULT 'DejaVu Sans' AFTER font_heading_family",
        'font_cover_family'=>"VARCHAR(50) DEFAULT 'DejaVu Serif' AFTER font_table_family",
        'font_body_size'=>"DECIMAL(4,1) DEFAULT 9.2 AFTER font_cover_family",
        'font_heading_size'=>"DECIMAL(4,1) DEFAULT 13.0 AFTER font_body_size",
        'font_table_size'=>"DECIMAL(4,1) DEFAULT 6.4 AFTER font_heading_size",
        'font_cover_size'=>"DECIMAL(4,1) DEFAULT 27.0 AFTER font_table_size",
        'line_height'=>"DECIMAL(3,2) DEFAULT 1.48 AFTER font_cover_size",
        'margin_top'=>"DECIMAL(4,1) DEFAULT 25.0 AFTER line_height",
        'margin_right'=>"DECIMAL(4,1) DEFAULT 15.0 AFTER margin_top",
        'margin_bottom'=>"DECIMAL(4,1) DEFAULT 18.0 AFTER margin_right",
        'margin_left'=>"DECIMAL(4,1) DEFAULT 15.0 AFTER margin_bottom",
        'map_type'=>"VARCHAR(20) DEFAULT 'satellite' AFTER margin_left",
        'show_map'=>"TINYINT(1) DEFAULT 1 AFTER map_type",
        'show_sbt_chart'=>"TINYINT(1) DEFAULT 1 AFTER show_map",
        'sbt_show_connection_line'=>"TINYINT(1) DEFAULT 1 AFTER show_sbt_chart",
        'sbt_line_style'=>"VARCHAR(12) DEFAULT 'solid' AFTER sbt_show_connection_line",
        'show_equipment'=>"TINYINT(1) DEFAULT 1 AFTER sbt_line_style",
        'show_foundation'=>"TINYINT(1) DEFAULT 1 AFTER show_equipment",
        'show_documentation'=>"TINYINT(1) DEFAULT 1 AFTER show_foundation",
        'font_subheading_size'=>"DECIMAL(4,1) DEFAULT 11.0 AFTER font_heading_size",
        'font_caption_size'=>"DECIMAL(4,1) DEFAULT 8.0 AFTER font_table_size",
        'header_lines_enabled'=>"TINYINT(1) DEFAULT 1 AFTER show_documentation",
        'header_lines'=>"LONGTEXT NULL AFTER header_lines_enabled",
        'header_double_line'=>"TINYINT(1) DEFAULT 1 AFTER header_lines",
        'header_line_1_width'=>"DECIMAL(4,2) DEFAULT 0.80 AFTER header_double_line",
        'header_line_2_width'=>"DECIMAL(4,2) DEFAULT 0.30 AFTER header_line_1_width",
        'header_line_gap'=>"DECIMAL(4,2) DEFAULT 0.80 AFTER header_line_2_width",
        'header_to_line_gap'=>"DECIMAL(4,2) DEFAULT 2.00 AFTER header_line_gap",
        'line_to_content_gap'=>"DECIMAL(4,2) DEFAULT 4.00 AFTER header_to_line_gap",
        'logo_left_path'=>"VARCHAR(255) NULL AFTER logo_path",
        'logo_right_path'=>"VARCHAR(255) NULL AFTER logo_left_path",
        'logo_left_position'=>"VARCHAR(12) DEFAULT 'left' AFTER logo_right_path",
        'logo_right_position'=>"VARCHAR(12) DEFAULT 'right' AFTER logo_left_position",
        'logo_left_width'=>"DECIMAL(4,1) DEFAULT 18.0 AFTER logo_right_position",
        'logo_left_height'=>"DECIMAL(4,1) NULL AFTER logo_left_width",
        'logo_left_x'=>"DECIMAL(4,1) DEFAULT 0 AFTER logo_left_height",
        'logo_left_y'=>"DECIMAL(4,1) DEFAULT 0 AFTER logo_left_x",
        'logo_right_width'=>"DECIMAL(4,1) DEFAULT 18.0 AFTER logo_left_y",
        'logo_right_height'=>"DECIMAL(4,1) NULL AFTER logo_right_width",
        'logo_right_x'=>"DECIMAL(4,1) DEFAULT 0 AFTER logo_right_height",
        'logo_right_y'=>"DECIMAL(4,1) DEFAULT 0 AFTER logo_right_x",
        'examiner_address'=>"TEXT NULL AFTER logo_right_y",
        'examiner_city'=>"VARCHAR(100) NULL AFTER examiner_address",
        'examiner_province'=>"VARCHAR(100) NULL AFTER examiner_city",
        'examiner_postal_code'=>"VARCHAR(20) NULL AFTER examiner_province",
        'examiner_phone'=>"VARCHAR(60) NULL AFTER examiner_postal_code",
        'examiner_email'=>"VARCHAR(120) NULL AFTER examiner_phone",
        'examiner_website'=>"VARCHAR(160) NULL AFTER examiner_email",
        'signer_name'=>"VARCHAR(160) NULL AFTER examiner_website",
        'signer_position'=>"VARCHAR(160) NULL AFTER signer_name",
        'signer_identity'=>"VARCHAR(100) NULL AFTER signer_position",
        'signature_path'=>"VARCHAR(255) NULL AFTER signer_identity",
        'stamp_path'=>"VARCHAR(255) NULL AFTER signature_path",
        'preface_template'=>"LONGTEXT NULL AFTER stamp_path",
    ];
    foreach($columns as $name=>$definition){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute(['pengaturan_laporan',$name]);
        if(!$statement->fetch())$pdo->exec("ALTER TABLE pengaturan_laporan ADD COLUMN `$name` $definition");
    }
    $pdo->exec("UPDATE pengaturan_laporan SET font_body_family=COALESCE(NULLIF(font_body_family,''),font_family),font_body_size=COALESCE(font_body_size,font_size) WHERE id=1");
}

function report_settings(PDO $pdo, array $lab=[]): array
{
    report_settings_ensure($pdo);
    $settings=$pdo->query('SELECT * FROM pengaturan_laporan WHERE id=1')->fetch()?:[];
    $resolved=array_merge([
        'kop_nama'=>$lab['nama_laboratorium']??'Laboratorium Mekanika Tanah',
        'kop_subjudul'=>$lab['nama_instansi']??'Universitas Muhammadiyah Buton',
        'kop_alamat'=>$lab['alamat']??'',
        'judul_laporan'=>'LAPORAN PENYELIDIKAN TANAH',
        'font_family'=>'DejaVu Sans',
        'font_size'=>9.2,
        'font_body_family'=>'DejaVu Sans',
        'font_heading_family'=>'DejaVu Sans',
        'font_table_family'=>'DejaVu Sans',
        'font_cover_family'=>'DejaVu Serif',
        'font_body_size'=>9.2,
        'font_heading_size'=>13.0,
        'font_subheading_size'=>11.0,
        'font_table_size'=>6.4,
        'font_caption_size'=>8.0,
        'font_cover_size'=>27.0,
        'line_height'=>1.48,
        'margin_top'=>25.0,
        'margin_right'=>15.0,
        'margin_bottom'=>18.0,
        'margin_left'=>15.0,
        'map_type'=>'satellite',
        'show_map'=>1,
        'show_sbt_chart'=>1,
        'sbt_show_connection_line'=>1,
        'sbt_line_style'=>'solid',
        'show_equipment'=>1,
        'show_foundation'=>1,
        'show_documentation'=>1,
        'header_lines_enabled'=>1,
        'header_lines'=>null,
        'header_double_line'=>1,
        'header_line_1_width'=>0.8,
        'header_line_2_width'=>0.3,
        'header_line_gap'=>0.8,
        'header_to_line_gap'=>2.0,
        'line_to_content_gap'=>4.0,
        'warna_utama'=>'#173B61',
        'warna_aksen'=>'#F4B400',
        'gaya_kop'=>'formal',
        'logo_path'=>$lab['logo']??null,
        'logo_left_path'=>$lab['logo']??null,
        'logo_right_path'=>null,
        'logo_left_width'=>18.0,'logo_left_height'=>null,'logo_left_x'=>0.0,'logo_left_y'=>0.0,'logo_left_position'=>'left',
        'logo_right_width'=>18.0,'logo_right_height'=>null,'logo_right_x'=>0.0,'logo_right_y'=>0.0,'logo_right_position'=>'right',
        'examiner_address'=>$lab['alamat']??'',
        'examiner_city'=>$lab['kabupaten']??'',
        'examiner_province'=>$lab['provinsi']??'',
        'examiner_postal_code'=>'','examiner_phone'=>$lab['telepon']??'','examiner_email'=>$lab['email']??'','examiner_website'=>$lab['website']??'',
        'signer_name'=>$lab['kepala_laboratorium']??'',
        'signer_position'=>'Kepala Laboratorium','signer_identity'=>'','signature_path'=>null,'stamp_path'=>null,
        'preface_template'=>'Puji syukur kepada Tuhan Yang Maha Esa atas terlaksananya penyelidikan tanah dengan metode uji sondir untuk pekerjaan [PROYEK]. Pengujian dilakukan pada lokasi [LOKASI] atas permohonan [PEMOHON]. Laporan ini disusun oleh [LABORATORIUM] dan memuat lokasi, metode, data pengujian, grafik, zonasi Robertson SBT 12 zona, interpretasi lapisan tanah, serta analisis daya dukung pondasi.',
        'footer_text'=>$lab['footer_laporan']??'',
    ],$settings);
    if(trim((string)($resolved['preface_template']??''))==='')$resolved['preface_template']='Puji syukur kepada Tuhan Yang Maha Esa atas terlaksananya penyelidikan tanah dengan metode uji sondir untuk pekerjaan [PROYEK]. Pengujian dilakukan pada lokasi [LOKASI] atas permohonan [PEMOHON]. Laporan ini disusun oleh [LABORATORIUM] dan memuat lokasi, metode, data pengujian, grafik, zonasi Robertson SBT 12 zona, interpretasi lapisan tanah, serta analisis daya dukung pondasi.';
    if(trim((string)($resolved['logo_left_path']??''))==='')$resolved['logo_left_path']=$resolved['logo_path']??($lab['logo']??null);
    if(trim((string)($resolved['signer_name']??''))==='')$resolved['signer_name']=$lab['kepala_laboratorium']??'';
    if(trim((string)($resolved['signer_position']??''))==='')$resolved['signer_position']='Kepala Laboratorium';
    if(trim((string)($resolved['examiner_city']??''))==='')$resolved['examiner_city']=$lab['kabupaten']??'';
    return $resolved;
}

function report_font_family(string $font): string
{
    return match($font){
        'Times New Roman','Georgia','Garamond','Times'=>'Times',
        'Arial','Calibri','Tahoma','Trebuchet MS','Verdana','Helvetica'=>'Helvetica',
        'Courier','DejaVu Sans','DejaVu Serif','DejaVu Sans Mono'=>$font,
        default=>'DejaVu Sans',
    };
}

/**
 * Keep mathematical glyphs independent from the configurable report font.
 * Dompdf does not perform reliable glyph fallback for its Times/Helvetica
 * core fonts, so unsupported symbols would otherwise be rendered as "?".
 * Only pass developer-authored formula HTML to this helper.
 */
function report_formula_html(string $trustedHtml): string
{
    return strtr($trustedHtml,[
        'q̄'=>'<span class="math-symbol">q&#772;</span>',
        'Σ'=>'<span class="math-symbol">&#931;</span>',
        '−'=>'<span class="math-symbol">&#8722;</span>',
        '×'=>'<span class="math-symbol">&#215;</span>',
        '≤'=>'<span class="math-symbol">&#8804;</span>',
        '≥'=>'<span class="math-symbol">&#8805;</span>',
        '±'=>'<span class="math-symbol">&#177;</span>',
        '√'=>'<span class="math-symbol">&#8730;</span>',
        '≈'=>'<span class="math-symbol">&#8776;</span>',
        '≠'=>'<span class="math-symbol">&#8800;</span>',
        '²'=>'<span class="math-symbol">&#178;</span>',
        '³'=>'<span class="math-symbol">&#179;</span>',
    ]);
}

function report_header_lines(array $settings, array $lab=[]): array
{
    $decoded=json_decode((string)($settings['header_lines']??''),true);
    if(!is_array($decoded)||!$decoded){
        $decoded=[
            ['text'=>$settings['kop_nama']??($lab['nama_laboratorium']??''),'font'=>'Times New Roman','size'=>16,'bold'=>1,'italic'=>0,'uppercase'=>1,'align'=>'center','margin_top'=>0,'margin_bottom'=>0,'line_height'=>1],
            ['text'=>$settings['kop_subjudul']??($lab['nama_instansi']??''),'font'=>'Times New Roman','size'=>12,'bold'=>1,'italic'=>0,'uppercase'=>1,'align'=>'center','margin_top'=>0,'margin_bottom'=>0,'line_height'=>1],
            ['text'=>$settings['kop_alamat']??($lab['alamat']??''),'font'=>'Times New Roman','size'=>9,'bold'=>0,'italic'=>0,'uppercase'=>0,'align'=>'center','margin_top'=>0,'margin_bottom'=>0,'line_height'=>1.05],
            ['text'=>'','font'=>'Times New Roman','size'=>8,'bold'=>0,'italic'=>0,'uppercase'=>0,'align'=>'center','margin_top'=>0,'margin_bottom'=>0,'line_height'=>1],
            ['text'=>'','font'=>'Times New Roman','size'=>8,'bold'=>0,'italic'=>0,'uppercase'=>0,'align'=>'center','margin_top'=>0,'margin_bottom'=>0,'line_height'=>1],
        ];
    }
    return array_slice(array_pad($decoded,5,[]),0,5);
}

function report_template_text(string $template, array $tokens): string
{
    return strtr($template,$tokens);
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
    if(!$rows)return ['depth'=>0.0,'qcMax'=>0.0,'tfMax'=>0.0,'hardDepth'=>null,'soil'=>'Belum ada data'];
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

function report_http_get(string $url): ?string
{
    if(function_exists('curl_init')){
        $curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'Sondir-Laporan/1.0']);
        $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        return is_string($body)&&$status>=200&&$status<300?$body:null;
    }
    $context=stream_context_create(['http'=>['timeout'=>20,'header'=>"User-Agent: Sondir-Laporan/1.0\r\n"]]);
    $body=@file_get_contents($url,false,$context);
    return is_string($body)?$body:null;
}

function report_coordinate_fallback_uri(array $valid, string $message='Citra satelit tidak tersedia; diagram koordinat digunakan sebagai cadangan.'): string
{
    $w=1200;$h=650;$pad=90;
    if(!$valid){
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'"><rect width="100%" height="100%" fill="#eef4f8"/><text x="600" y="325" text-anchor="middle" font-family="DejaVu Sans" font-size="25" fill="#64748b">Koordinat titik sondir belum tersedia</text></svg>';
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
    $lats=array_map(fn($p)=>(float)$p['latitude'],$valid);$lngs=array_map(fn($p)=>(float)$p['longitude'],$valid);
    $minLat=min($lats);$maxLat=max($lats);$minLng=min($lngs);$maxLng=max($lngs);
    $latRange=max(.0002,$maxLat-$minLat);$lngRange=max(.0002,$maxLng-$minLng);$grid='';$markers='';
    for($i=0;$i<=8;$i++){$x=$pad+($w-2*$pad)*$i/8;$y=$pad+($h-2*$pad)*$i/8;$grid.='<line x1="'.$x.'" y1="'.$pad.'" x2="'.$x.'" y2="'.($h-$pad).'"/><line x1="'.$pad.'" y1="'.$y.'" x2="'.($w-$pad).'" y2="'.$y.'"/>';}
    foreach($valid as $point){
        $x=$pad+(((float)$point['longitude']-$minLng+$lngRange*.12)/($lngRange*1.24))*($w-2*$pad);$y=$pad+(1-(((float)$point['latitude']-$minLat+$latRange*.12)/($latRange*1.24)))*($h-2*$pad);$label='S'.(int)$point['nomor_urut'];
        $markers.='<circle cx="'.$x.'" cy="'.$y.'" r="24" fill="#087ea4" stroke="#fff" stroke-width="6"/><text x="'.$x.'" y="'.($y+7).'" text-anchor="middle" fill="#fff" font-weight="bold" font-size="19">'.$label.'</text>';
    }
    $svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'"><rect width="100%" height="100%" fill="#e7eef4"/><g stroke="#c2d0dc">'.$grid.'</g><g font-family="DejaVu Sans">'.$markers.'</g><path d="M1110 92 L1130 25 L1150 92 L1130 72Z" fill="#173b61"/><text x="1130" y="20" text-anchor="middle" font-family="DejaVu Sans" font-weight="bold" fill="#173b61">U</text><rect x="22" y="595" width="760" height="35" rx="6" fill="#fff" opacity=".92"/><text x="38" y="618" font-family="DejaVu Sans" font-size="15" fill="#475569">'.htmlspecialchars($message,ENT_QUOTES|ENT_XML1,'UTF-8').'</text></svg>';
    return 'data:image/svg+xml;base64,'.base64_encode($svg);
}

function report_location_uri(array $points, string $mapType='satellite'): string
{
    $valid=array_values(array_filter($points,fn($p)=>$p['latitude']!==null&&$p['longitude']!==null&&is_numeric($p['latitude'])&&is_numeric($p['longitude'])));
    if(!$valid||$mapType!=='satellite'||!extension_loaded('gd'))return report_coordinate_fallback_uri($valid,$mapType==='satellite'?'Citra satelit tidak tersedia; diagram koordinat digunakan sebagai cadangan.':'Diagram koordinat berdasarkan posisi titik tersimpan.');
    $lats=array_map(fn($p)=>(float)$p['latitude'],$valid);$lngs=array_map(fn($p)=>(float)$p['longitude'],$valid);
    $minLat=min($lats);$maxLat=max($lats);$minLng=min($lngs);$maxLng=max($lngs);
    $latRange=max(.00045,$maxLat-$minLat);$lngRange=max(.00065,$maxLng-$minLng);
    $centerLat=($minLat+$maxLat)/2;$centerLng=($minLng+$maxLng)/2;
    $minLat=$centerLat-$latRange*.72;$maxLat=$centerLat+$latRange*.72;$minLng=$centerLng-$lngRange*.72;$maxLng=$centerLng+$lngRange*.72;
    $w=1000;$h=600;$cacheDir=APP_ROOT.'/storage/report-map-cache';if(!is_dir($cacheDir))@mkdir($cacheDir,0775,true);
    $cacheFile=$cacheDir.'/map-'.sha1(json_encode([$minLng,$minLat,$maxLng,$maxLat,$w,$h,array_column($valid,'nomor_urut')])).'.png';
    if(is_file($cacheFile)&&filesize($cacheFile)>10000)return 'data:image/png;base64,'.base64_encode((string)file_get_contents($cacheFile));
    $params=http_build_query(['bbox'=>implode(',',[$minLng,$minLat,$maxLng,$maxLat]),'bboxSR'=>4326,'size'=>$w.','.$h,'format'=>'jpg','transparent'=>'false','f'=>'pjson'],'','&',PHP_QUERY_RFC3986);
    $json=report_http_get('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/export?'.$params);$metadata=$json?json_decode($json,true):null;
    if(!is_array($metadata)||empty($metadata['href'])||empty($metadata['extent']))return report_coordinate_fallback_uri($valid);
    $raw=report_http_get((string)$metadata['href']);$image=$raw?@imagecreatefromstring($raw):false;if(!$image)return report_coordinate_fallback_uri($valid);
    $extent=$metadata['extent'];$xmin=(float)$extent['xmin'];$xmax=(float)$extent['xmax'];$ymin=(float)$extent['ymin'];$ymax=(float)$extent['ymax'];
    $blue=imagecolorallocate($image,8,126,164);$white=imagecolorallocate($image,255,255,255);$navy=imagecolorallocate($image,23,59,97);$shadow=imagecolorallocatealpha($image,0,0,0,65);$creditBg=imagecolorallocatealpha($image,0,0,0,45);
    foreach($valid as $point){
        $lon=(float)$point['longitude'];$lat=max(-85.05112878,min(85.05112878,(float)$point['latitude']));$mx=$lon*20037508.34/180;$my=log(tan((90+$lat)*M_PI/360))/(M_PI/180);$my=$my*20037508.34/180;
        $x=(int)round(($mx-$xmin)/($xmax-$xmin)*$w);$y=(int)round((1-($my-$ymin)/($ymax-$ymin))*$h);$label='S'.(int)$point['nomor_urut'];
        imagefilledellipse($image,$x+3,$y+4,60,60,$shadow);imagefilledpolygon($image,[$x-15,$y+22,$x+15,$y+22,$x,$y+49],$shadow);
        imagefilledpolygon($image,[$x-18,$y+18,$x+18,$y+18,$x,$y+48],$white);imagefilledellipse($image,$x,$y,66,66,$white);
        imagefilledpolygon($image,[$x-13,$y+17,$x+13,$y+17,$x,$y+41],$blue);imagefilledellipse($image,$x,$y,54,54,$blue);
        $font=5;$textWidth=imagefontwidth($font)*strlen($label);imagestring($image,$font,$x-(int)($textWidth/2),(int)round($y-imagefontheight($font)/2),$label,$white);
    }
    imagefilledpolygon($image,[$w-75,32,$w-96,105,$w-75,88,$w-54,105],$white);imagefilledpolygon($image,[$w-75,38,$w-91,94,$w-75,80,$w-59,94],$navy);imagestring($image,5,$w-80,12,'U',$white);
    imagefilledrectangle($image,0,$h-28,$w,$h,$creditBg);imagestring($image,3,12,$h-21,'Sumber citra: Esri World Imagery | Marker berdasarkan koordinat proyek',$white);
    ob_start();imagepng($image);$png=(string)ob_get_clean();if($png!=='')@file_put_contents($cacheFile,$png);
    return 'data:image/png;base64,'.base64_encode($png);
}

function report_sbt_chart_uri(array $rows, bool $showConnectionLine=true, string $lineStyle='solid'): string
{
    $config=sondir_soil_chart_config();$w=1000;$h=650;$left=78;$top=45;$plotW=650;$plotH=525;$frMax=(float)($config['display_fr_max']??10);
    $px=fn(float $fr):float=>$left+max(0,min($frMax,$fr))/$frMax*$plotW;
    $py=fn(float $qc):float=>$top+(2-log10(max(.1,min(100,$qc))))/3*$plotH;
    $polygons='';$labels='';$legend='';
    foreach(($config['zones']??[]) as $index=>$zone){
        $points=[];foreach($zone['polygon'] as $pair)$points[]=round($px((float)$pair[0]),2).','.round($py((float)$pair[1]),2);
        $color=report_hex_color((string)$zone['color'],'#CBD5E1');$polygons.='<polygon points="'.implode(' ',$points).'" fill="'.$color.'" fill-opacity=".72" stroke="#fff" stroke-width="1.4"/>';
        $labels.='<text class="zone-no" x="'.$px((float)$zone['label'][0]).'" y="'.($py((float)$zone['label'][1])+5).'" text-anchor="middle">'.(int)$zone['zone'].'</text>';
        $legend.='<rect x="765" y="'.(46+$index*42).'" width="18" height="18" rx="3" fill="'.$color.'"/><text x="791" y="'.(59+$index*42).'" class="legend"><tspan font-weight="bold">'.(int)$zone['zone'].'.</tspan> '.htmlspecialchars((string)$zone['name_id'],ENT_QUOTES|ENT_XML1,'UTF-8').'</text>';
    }
    $grid='';$axis='';
    for($i=0;$i<=10;$i++){$x=$px((float)$i);$grid.='<line x1="'.$x.'" y1="'.$top.'" x2="'.$x.'" y2="'.($top+$plotH).'"/>';$axis.='<text x="'.$x.'" y="'.($top+$plotH+22).'" text-anchor="middle">'.$i.'</text>';}
    foreach([.1,.2,.5,1,2,5,10,20,50,100] as $value){$y=$py($value);$grid.='<line x1="'.$left.'" y1="'.$y.'" x2="'.($left+$plotW).'" y2="'.$y.'"/>';$axis.='<text x="'.($left-10).'" y="'.($y+4).'" text-anchor="end">'.$value.'</text>';}
    $data=[];foreach($rows as $row){$qc=(float)($row['qc_mpa']??((float)($row['qc']??0)*.0980665));$fr=(float)($row['friction_ratio']??0);if($qc>=.1&&$qc<=100&&$fr>=0&&$fr<=$frMax)$data[]=['x'=>$px($fr),'y'=>$py($qc),'depth'=>(float)($row['kedalaman']??0)];}
    $dash=$lineStyle==='dashed'?' stroke-dasharray="9 6"':'';
    $polyline=$showConnectionLine&&$data?'<polyline points="'.implode(' ',array_map(fn($p)=>round($p['x'],2).','.round($p['y'],2),$data)).'" fill="none" stroke="#111827" stroke-width="2" stroke-opacity=".62"'.$dash.'/>':'';
    $dots='';$depthLabels='';$depthLeaders='';$occupiedLabels=[];
    foreach($data as $index=>$point){
        $dots.='<circle cx="'.$point['x'].'" cy="'.$point['y'].'" r="3.6" fill="#dc2626" stroke="#fff" stroke-width="1"/>';
        $depthText=report_number($point['depth'],2).' m';$labelWidth=max(25,mb_strlen($depthText)*5.8);$labelHeight=11;
        $labelX=$point['x'];$labelY=$point['y']-10;$found=false;
        foreach([10,18,28,40,54,70,88,108,130] as $radius){
            $angles=$index%2===0?[-55,-125,55,125,-90,90,0,180]:[-125,-55,125,55,-90,90,180,0];
            foreach($angles as $angle){
                $candidateX=$point['x']+cos(deg2rad($angle))*$radius;$candidateY=$point['y']+sin(deg2rad($angle))*$radius;
                $rect=[$candidateX-$labelWidth/2-2,$candidateY-$labelHeight+1,$candidateX+$labelWidth/2+2,$candidateY+3];
                if($rect[0]<$left+2||$rect[2]>$left+$plotW-2||$rect[1]<$top+2||$rect[3]>$top+$plotH-2)continue;
                $overlap=false;foreach($occupiedLabels as $used){if($rect[0]<$used[2]&&$rect[2]>$used[0]&&$rect[1]<$used[3]&&$rect[3]>$used[1]){$overlap=true;break;}}
                if(!$overlap){$labelX=$candidateX;$labelY=$candidateY;$occupiedLabels[]=$rect;$found=true;break 2;}
            }
        }
        if(!$found){$labelX=max($left+$labelWidth/2+3,min($left+$plotW-$labelWidth/2-3,$labelX));$labelY=max($top+$labelHeight+2,min($top+$plotH-4,$labelY));}
        if(hypot($labelX-$point['x'],$labelY-$point['y'])>20)$depthLeaders.='<line x1="'.$point['x'].'" y1="'.$point['y'].'" x2="'.$labelX.'" y2="'.($labelY-3).'" class="depth-leader"/>';
        $depth=htmlspecialchars($depthText,ENT_QUOTES|ENT_XML1,'UTF-8');
        $depthLabels.='<text x="'.$labelX.'" y="'.$labelY.'" text-anchor="middle" class="depth-label">'.$depth.'</text>';
    }
    $lineLegend=$showConnectionLine?'<line x1="765" y1="607" x2="793" y2="607" stroke="#111827" stroke-width="2"'.$dash.'/><text x="801" y="611" class="legend">Garis '.($lineStyle==='dashed'?'putus-putus':'penuh').'</text>':'';
    $svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'"><rect width="100%" height="100%" fill="#fff"/><style>text{font:12px DejaVu Sans,sans-serif;fill:#334155}.grid line{stroke:#cbd5e1;stroke-width:.8}.zone-no{font:bold 18px DejaVu Sans;fill:#102a43;paint-order:stroke;stroke:#fff;stroke-width:3}.depth-leader{stroke:#991b1b;stroke-width:.65;stroke-opacity:.5}.depth-label{font:bold 9.5px DejaVu Sans;fill:#7f1d1d;paint-order:stroke;stroke:#fff;stroke-width:2.6;stroke-linejoin:round}.legend{font:10.5px DejaVu Sans}.title{font:bold 18px DejaVu Sans;fill:#173b61}.axis-title{font:bold 13px DejaVu Sans;fill:#173b61}</style><text x="400" y="23" text-anchor="middle" class="title">Diagram Robertson SBT - 12 Zona</text><g class="grid">'.$grid.'</g>'.$polygons.$labels.$polyline.$depthLeaders.$dots.$depthLabels.$axis.'<rect x="'.$left.'" y="'.$top.'" width="'.$plotW.'" height="'.$plotH.'" fill="none" stroke="#475569" stroke-width="1.5"/><text x="'.($left+$plotW/2).'" y="620" text-anchor="middle" class="axis-title">Friction Ratio, FR (%)</text><text transform="rotate(-90 18 307)" x="18" y="307" text-anchor="middle" class="axis-title">Tahanan konus qc (MPa) - skala log</text><text x="765" y="25" class="title">Legenda zona</text>'.$legend.'<circle cx="768" cy="582" r="4" fill="#dc2626"/><text x="780" y="586" class="legend">Data + kedalaman (m)</text>'.$lineLegend.'</svg>';
    return 'data:image/svg+xml;base64,'.base64_encode($svg);
}

function report_sondir_equipment_uri(): string
{
    $svg=<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="430" viewBox="0 0 1000 430">
<rect width="1000" height="430" rx="16" fill="#f8fafc"/>
<style>.t{font:18px DejaVu Sans;fill:#173b61}.s{font:14px DejaVu Sans;fill:#334155}.h{font:bold 21px DejaVu Sans;fill:#173b61}.part{fill:#dbeafe;stroke:#1769aa;stroke-width:3}.metal{fill:#e2e8f0;stroke:#475569;stroke-width:3}.soil{fill:#d6b48a;stroke:#966b3d;stroke-width:2}.guide{stroke:#64748b;stroke-width:2;stroke-dasharray:6 5}.call{stroke:#1769aa;stroke-width:2;fill:none}</style>
<text x="240" y="30" text-anchor="middle" class="h">Skema perangkat sondir hidraulik</text>
<line x1="25" y1="350" x2="490" y2="350" class="guide"/><text x="35" y="375" class="s">Permukaan tanah</text>
<rect x="145" y="285" width="190" height="28" rx="4" class="metal"/><text x="240" y="304" text-anchor="middle" class="s">Balok reaksi</text>
<path d="M170 313v42m140-42v42" stroke="#475569" stroke-width="8"/><path d="M155 355h30l-15 35zM295 355h30l-15 35z" class="metal"/>
<rect x="195" y="165" width="90" height="120" rx="8" class="part"/><text x="240" y="220" text-anchor="middle" class="s">Dongkrak</text><text x="240" y="240" text-anchor="middle" class="s">hidraulik</text>
<circle cx="90" cy="130" r="50" fill="#fff" stroke="#1769aa" stroke-width="5"/><path d="M90 130l25-22" stroke="#dc2626" stroke-width="4"/><text x="90" y="138" text-anchor="middle" class="s">Manometer</text><path d="M138 145h57" class="call"/>
<rect x="229" y="70" width="22" height="95" class="metal"/><text x="275" y="100" class="s">Batang tekan</text><path d="M255 105h15" class="call"/>
<path d="M240 313v90" stroke="#475569" stroke-width="10"/><path d="M220 403h40l-20 22z" fill="#f59e0b" stroke="#92400e" stroke-width="3"/>
<text x="300" y="410" class="s">Konus / bikonus</text><path d="M267 405h28" class="call"/>
<text x="535" y="30" class="h">Detail bikonus standar</text>
<rect x="620" y="72" width="72" height="205" rx="7" class="metal"/><rect x="620" y="174" width="72" height="103" fill="#bfdbfe" stroke="#1769aa" stroke-width="3"/>
<path d="M620 277h72l-36 92z" fill="#f59e0b" stroke="#92400e" stroke-width="3"/><line x1="656" y1="52" x2="656" y2="365" class="guide"/>
<text x="730" y="115" class="t">Batang dalam</text><path d="M700 110h25" class="call"/>
<text x="730" y="210" class="t">Selimut geser</text><text x="730" y="232" class="s">Luas 150 +/- 3 cm2</text><path d="M700 218h25" class="call"/>
<text x="730" y="312" class="t">Ujung konus</text><text x="730" y="334" class="s">Sudut 60 +/- 5 derajat</text><text x="730" y="355" class="s">Diameter 35,7 +/- 0,4 mm</text><path d="M700 305h25" class="call"/>
<rect x="535" y="382" width="430" height="34" rx="7" fill="#e0f2fe"/><text x="750" y="404" text-anchor="middle" class="s">Penetrasi 10-20 mm/s; pembacaan pada interval 20 cm</text>
</svg>
SVG;
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

function build_project_report_html(PDO $pdo, int $projectId, array $tocPages=[]): ?array
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
    $fontBody=report_font_family((string)$settings['font_body_family']);
    $fontHeading=report_font_family((string)$settings['font_heading_family']);
    $fontTable=report_font_family((string)$settings['font_table_family']);
    $fontCover=report_font_family((string)$settings['font_cover_family']);
    $fontSize=max(8,min(16,(float)$settings['font_body_size']));$headingSize=max(10,min(24,(float)$settings['font_heading_size']));$subheadingSize=max(9,min(20,(float)$settings['font_subheading_size']));$tableSize=max(5,min(14,(float)$settings['font_table_size']));$captionSize=max(6,min(14,(float)$settings['font_caption_size']));$coverSize=max(20,min(38,(float)$settings['font_cover_size']));$lineHeight=max(1,min(2,(float)$settings['line_height']));
    $marginTop=max(20,min(60,(float)$settings['margin_top']));$marginRight=max(5,min(40,(float)$settings['margin_right']));$marginBottom=max(5,min(40,(float)$settings['margin_bottom']));$marginLeft=max(5,min(40,(float)$settings['margin_left']));
    $showMap=(bool)$settings['show_map'];$showSbt=(bool)$settings['show_sbt_chart'];$showEquipment=(bool)$settings['show_equipment'];$showFoundation=(bool)$settings['show_foundation'];$showDocumentation=(bool)$settings['show_documentation'];
    $hasDocumentation=(bool)array_filter($points,fn($point)=>!empty($point['documentation']));$includeDocumentation=$showDocumentation&&$hasDocumentation;
    $sbtShowConnectionLine=(bool)$settings['sbt_show_connection_line'];$sbtLineStyle=in_array($settings['sbt_line_style'],['solid','dashed'],true)?$settings['sbt_line_style']:'solid';
    $logoLeftPath=($settings['logo_left_path']?:$settings['logo_path'])?report_documentation_path((string)($settings['logo_left_path']?:$settings['logo_path'])):null;
    $logoRightPath=$settings['logo_right_path']?report_documentation_path((string)$settings['logo_right_path']):null;
    $logoLeftUri=$logoLeftPath?report_image_data_uri($logoLeftPath):null;$logoRightUri=$logoRightPath?report_image_data_uri($logoRightPath):null;
    $headerClass='kop-'.(in_array($settings['gaya_kop'],['formal','minimal','balok'],true)?$settings['gaya_kop']:'formal');
    $headerLines=report_header_lines($settings,$lab);
    $head=trim((string)($settings['signer_name']?:($lab['kepala_laboratorium']??'')));
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
    $signaturePath=$settings['signature_path']?report_documentation_path((string)$settings['signature_path']):null;$signatureUri=$signaturePath?report_image_data_uri($signaturePath):null;
    $stampPath=$settings['stamp_path']?report_documentation_path((string)$settings['stamp_path']):null;$stampUri=$stampPath?report_image_data_uri($stampPath):null;
    $preface=report_template_text((string)$settings['preface_template'],['[PROYEK]'=>$project['nama_proyek'],'[LOKASI]'=>$address?:'-','[PEMOHON]'=>$client?:'-','[LABORATORIUM]'=>$labName]);

    ob_start();
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
@page{margin:<?=$marginTop?>mm <?=$marginRight?>mm <?=$marginBottom?>mm <?=$marginLeft?>mm}
*{box-sizing:border-box}body{font-family:'<?=e($fontBody)?>',sans-serif;color:#1f3347;font-size:<?=$fontSize?>pt;line-height:<?=$lineHeight?>;margin:0;width:100%}
.running-header{position:fixed;top:-<?=max(20,$marginTop-2)?>mm;left:0;right:0;height:<?=max(18,$marginTop-(float)$settings['line_to_content_gap'])?>mm;color:#111;width:100%;text-align:center}
.running-header .kop-text{position:absolute;left:24mm;right:24mm;top:0}.running-header .kop-line{display:block;white-space:nowrap;overflow:visible;position:relative}.running-header .kop-logo{position:absolute;object-fit:contain;z-index:2}.running-header .kop-logo.left{left:<?=max(-80,min(80,(float)$settings['logo_left_x']))?>mm;top:<?=max(-20,min(50,(float)$settings['logo_left_y']))?>mm;width:<?=max(8,min(50,(float)$settings['logo_left_width']))?>mm;<?=($settings['logo_left_height']!==null&&$settings['logo_left_height']!=='')?'height:'.max(5,min(50,(float)$settings['logo_left_height'])).'mm;':''?>}.running-header .kop-logo.right{right:<?=max(-80,min(80,(float)$settings['logo_right_x']))?>mm;top:<?=max(-20,min(50,(float)$settings['logo_right_y']))?>mm;width:<?=max(8,min(50,(float)$settings['logo_right_width']))?>mm;<?=($settings['logo_right_height']!==null&&$settings['logo_right_height']!=='')?'height:'.max(5,min(50,(float)$settings['logo_right_height'])).'mm;':''?>}
.header-rules{position:absolute;left:0;right:0;bottom:0;border-top:<?=max(.1,min(3,(float)$settings['header_line_1_width']))?>mm solid <?=$primary?>;height:<?=!empty($settings['header_double_line'])?max(.2,min(5,(float)$settings['header_line_gap'])):0?>mm}.header-rules:after{content:'';position:absolute;left:0;right:0;bottom:0;border-top:<?=!empty($settings['header_double_line'])?max(.1,min(3,(float)$settings['header_line_2_width'])):0?>mm solid <?=$primary?>}.running-header.kop-minimal .header-rules:after,.running-header.kop-balok .header-rules:after{display:none}.running-header.kop-balok{background:<?=$primary?>;color:#fff;padding:1mm 2mm}.running-header.kop-balok .header-rules{border-color:<?=$accent?>}
.running-footer{position:fixed;bottom:-13mm;left:0;right:0;border-top:1px solid #a9bac8;padding-top:3mm;font-size:7.5pt;color:#526577}
.running-footer .right{position:absolute;right:0;top:3mm}.page{page-break-before:always;width:100%;clear:both}.report-flow{width:100%;clear:both;margin-top:6mm}.keep-together{page-break-inside:avoid}.cover{page-break-before:auto;height:200mm;position:relative;padding:6mm 8mm;background:#fff;width:100%;clear:both}
.cover-letterhead{position:relative;min-height:24mm;margin:-3mm 0 4mm;text-align:center;color:#111}.cover-letterhead .kop-text{margin:0 25mm}.cover-letterhead .kop-logo{position:absolute;top:0;object-fit:contain}.cover-letterhead .left{left:0;width:<?=max(8,min(50,(float)$settings['logo_left_width']))?>mm}.cover-letterhead .right{right:0;width:<?=max(8,min(50,(float)$settings['logo_right_width']))?>mm}.cover-letterhead .header-rules{bottom:-1mm}
.page-letterhead{position:relative;min-height:24mm;margin:-23mm 0 8mm;text-align:center;color:#111}.page-letterhead .kop-text{margin:0 25mm}.page-letterhead .kop-logo{position:absolute;top:0;object-fit:contain}.page-letterhead .left{left:0;width:<?=max(8,min(50,(float)$settings['logo_left_width']))?>mm}.page-letterhead .right{right:0;width:<?=max(8,min(50,(float)$settings['logo_right_width']))?>mm}.page-letterhead .header-rules{bottom:-1mm}
.cover-band{height:3mm;background:<?=$primary?>;margin:0 0 7mm}.cover-accent{width:38mm;height:2mm;background:<?=$accent?>;margin:0 auto 4mm}
.cover h1{font-family:'<?=e($fontCover)?>';font-size:<?=$coverSize?>pt;line-height:1.08;color:#111;margin:0 0 2mm;text-align:center}.cover h2{font-family:'<?=e($fontHeading)?>';font-size:15pt;margin:0;color:#111;text-align:center}.cover-project{margin:8mm auto 0;padding:5mm;border:2px solid <?=$primary?>;background:#fff;text-align:center;width:92%}
.cover-project strong{display:block;font-size:17pt;color:<?=$primary?>}.cover-meta{margin-top:5mm;width:100%;border-collapse:collapse}.cover-meta td{padding:1.2mm 2mm;border-bottom:1px solid #dce5ec}
.cover-bottom{position:relative;margin-top:3mm;background:<?=$primary?>;color:#fff;padding:5mm}.cover-bottom b{font-size:12pt}.cover-bottom span{position:absolute;right:5mm;top:5mm;font-size:20pt;font-weight:bold}
h1.chapter{font-family:'<?=e($fontHeading)?>';font-size:<?=($headingSize+4)?>pt;text-align:center;color:<?=$primary?>;margin:0 0 8mm;text-transform:uppercase}h2{font-family:'<?=e($fontHeading)?>';font-size:<?=$headingSize?>pt;color:<?=$primary?>;margin:7mm 0 3mm}h3{font-family:'<?=e($fontHeading)?>';font-size:<?=$subheadingSize?>pt;color:<?=$primary?>;margin:5mm 0 2mm}
p{text-align:justify;margin:0 0 3mm}.lead{font-size:10.5pt}.center{text-align:center}.muted{color:#64748b}.small{font-size:8pt}
.section-title{border:0!important;padding-bottom:0;margin-bottom:6mm}.toc{width:100%;border-collapse:collapse;font-size:<?=max(7,min(9.5,$fontSize-1))?>pt;line-height:1.08}.toc td{padding:.7mm 2mm;border-bottom:1px dotted #9cafbf}.toc td:last-child{width:16mm;text-align:right;font-weight:bold}.toc .bab{font-weight:bold;color:<?=$primary?>}.toc a{color:inherit;text-decoration:none}
.info{width:100%;border-collapse:collapse;margin:3mm 0 5mm}.info td{padding:2mm 2.5mm;border:1px solid #ccd8e2;vertical-align:top}.info td:first-child,.info td:nth-child(3){background:#eef4f8;font-weight:bold;width:18%}
.summary-grid{width:100%;border-collapse:separate;border-spacing:3mm}.summary-grid td{width:25%;padding:4mm;background:#eef5fb;border-top:3px solid #1769aa;text-align:center}.summary-grid b{display:block;font-size:16pt;color:#173b61}
.map{width:100%;height:auto;border:1px solid #bacbd8}.figure-caption{text-align:center;font-size:<?=$captionSize?>pt;margin:2mm 0 5mm;color:#526577}
.data-table,.foundation-table,.soil-table{font-family:'<?=e($fontTable)?>';width:100%;border-collapse:collapse;page-break-inside:auto}.data-table{table-layout:fixed}.data-table thead,.foundation-table thead{display:table-header-group}.data-table tr,.foundation-table tr{page-break-inside:avoid}.data-table th,.data-table td{border:1px solid #8ea5b6;padding:1.05mm .45mm;text-align:center;font-size:<?=$tableSize?>pt;overflow-wrap:anywhere}.data-table th{background:#dceaf5;color:<?=$primary?>}.data-table th.soil-width,.data-table td.soil-layer-pdf{width:20%}.data-table .soil{text-align:left;font-size:<?=max(5,$tableSize-.6)?>pt}.data-table .soil-layer-pdf{border-top:0;border-bottom:0;font-weight:700;text-align:center;color:#111827;text-shadow:0 1px 0 #fff;padding:0 .5mm}.data-table .soil-layer-start{border-top:1.5px solid #526577!important}.data-table .soil-layer-end{border-bottom:1.5px solid #526577!important}
.unit{display:block;font-size:5pt;font-weight:normal;color:#526577}.chart{width:100%;height:auto}.point-head{padding:4mm;background:<?=$primary?>;color:#fff;margin-bottom:4mm;position:relative}.point-head b{font-size:13pt}.point-head span{position:absolute;right:4mm;top:4mm}
.soil-table th,.soil-table td{border:1px solid #a9bbc8;padding:2mm}.soil-table th{background:#e8f1f7}.soil-swatch{border-left:6px solid #f4b400}
.formula{padding:3mm 4mm;margin:3mm 0;background:#f5f8fb;border-left:4px solid <?=$primary?>;font-family:'<?=e($fontBody)?>';page-break-inside:avoid}.math-symbol{font-family:'DejaVu Sans'!important;font-style:normal}.foundation-table th,.foundation-table td{border:1px solid #879dac;text-align:center;padding:.8mm .5mm;font-size:<?=max(5,$tableSize-1.2)?>pt}.foundation-table th{background:#dfeaf3}.foundation-table .group{background:#c9dded;font-size:<?=max(5.4,$tableSize-.2)?>pt}.foundation-note{font-size:7pt;margin-top:2mm;color:#526577}.foundation-range{margin-top:3mm;page-break-inside:auto}.foundation-range-title{font-weight:bold;color:<?=$primary?>;font-size:8pt;margin:2mm 0 1mm}.equipment-diagram{width:100%;height:auto;margin:2mm 0}.standard-note{font-size:7.5pt;color:#526577;margin-top:1mm}.point-head{page-break-after:avoid}
.approval{width:100%;border-collapse:collapse;margin-top:14mm}.approval td{width:50%;text-align:center;padding:3mm}.signature-space{height:25mm;position:relative}.signature-image{max-width:35mm;max-height:24mm;position:absolute;left:50%;top:0;transform:translateX(-50%);z-index:2}.stamp-image{max-width:28mm;max-height:28mm;position:absolute;left:55%;top:-2mm;opacity:.72}.signer-qr-wrap{height:27mm;text-align:center;padding-top:2mm}.signer-qr{width:23mm;height:23mm;display:inline-block}.signer-name{display:block;clear:both;text-align:center;margin-top:1mm}.legal-box{margin-top:8mm;border:1px solid #9db1c1;padding:4mm;min-height:38mm}.legal-box img{width:30mm;height:30mm;float:left;margin-right:5mm}.legal-box b{color:<?=$primary?>}.legal-code{font-family:'DejaVu Sans Mono';font-size:7pt;letter-spacing:.5px}.photo-grid{width:100%;border-collapse:separate;border-spacing:3mm}.photo-grid td{width:50%;border:1px solid #cbd6df;padding:2mm;vertical-align:top}.photo-grid img{width:100%;max-height:86mm;object-fit:contain}.empty-photo{height:70mm;background:#eef3f6;text-align:center;padding-top:30mm;color:#718294}
.groundwater-pdf{position:relative;border-top:3px double #087ea4!important}.groundwater-pdf:after{content:'Muka air tanah';position:absolute;right:1mm;top:-3.3mm;background:#087ea4;color:#fff;padding:.4mm 1mm;font-size:4.8pt;font-weight:bold}
.badge{display:inline-block;padding:1.5mm 3mm;border-radius:8mm;background:#e3edf5;color:#173b61;font-size:8pt}.warning{padding:3mm;background:#fff5cc;border:1px solid #e8c64b}
ul,ol{margin:2mm 0 3mm 6mm;padding-left:5mm}li{margin-bottom:1.5mm}
</style>
</head>
<body>
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

<section class="page" id="kata-pengantar">
  <h1 class="chapter section-title">Kata Pengantar</h1>
  <p class="lead"><?=nl2br(e($preface))?></p>
  <p>Pengujian dilakukan pada <?=$pointCount?> titik yang mewakili area proyek. Seluruh bagian dihimpun dalam satu dokumen agar dapat diperiksa dan digunakan sebagai lampiran laporan teknis.</p>
  <p>Terima kasih disampaikan kepada <?=e($client)?> selaku pemohon, tim pelaksana lapangan, pemeriksa, serta seluruh pihak yang mendukung pelaksanaan pengujian. Hasil dalam laporan ini berlaku untuk lokasi dan kondisi tanah pada saat pengujian.</p>
  <table class="approval"><tr><td></td><td><?=e(($settings['examiner_city']?:($lab['kabupaten']??'Baubau')).', '.tanggal_id(date('Y-m-d')))?><br>Hormat kami,<br><?=e($labName)?><div class="signature-space"><?php if($stampUri):?><img class="stamp-image" src="<?=$stampUri?>" alt="Stempel"><?php endif;?><?php if($signatureUri):?><img class="signature-image" src="<?=$signatureUri?>" alt="Tanda tangan"><?php endif;?></div><b><?=e($head)?></b><br><?=e($settings['signer_position']?:'Kepala Laboratorium')?><?php if($settings['signer_identity']):?><br><?=e($settings['signer_identity'])?><?php endif;?></td></tr></table>
</section>
<?php if(!empty($settings['header_lines_enabled'])):?><div class="running-header <?=e($headerClass)?>"><?php if($logoLeftUri):?><img class="kop-logo left" src="<?=$logoLeftUri?>" alt="Logo kiri"><?php endif;?><?php if($logoRightUri):?><img class="kop-logo right" src="<?=$logoRightUri?>" alt="Logo kanan"><?php endif;?><div class="kop-text"><?php foreach($headerLines as $line):$text=(string)($line['text']??'');if($text==='')continue;$lineFont=report_font_family((string)($line['font']??'Times New Roman'));?><span class="kop-line" style="font-family:'<?=e($lineFont)?>';font-size:<?=max(7,min(32,(float)($line['size']??9)))?>pt;font-weight:<?=!empty($line['bold'])?'bold':'normal'?>;font-style:<?=!empty($line['italic'])?'italic':'normal'?>;text-transform:<?=!empty($line['uppercase'])?'uppercase':'none'?>;text-align:<?=in_array($line['align']??'center',['left','center','right'],true)?e($line['align']):'center'?>;line-height:<?=max(.8,min(2,(float)($line['line_height']??1)))?>;margin-top:<?=max(-15,min(30,(float)($line['margin_top']??0)))?>mm;margin-bottom:<?=max(0,min(15,(float)($line['margin_bottom']??0)))?>mm"><?=e($text)?></span><?php endforeach;?></div><div class="header-rules"></div></div><?php endif;?>
<div class="running-footer"><b><?=e($settings['footer_text']?:$labName.' · '.$institution)?></b><span class="right"><?=e($reportNo)?></span></div>

<section class="page" id="ringkasan">
  <h1 class="chapter section-title">Ringkasan</h1>
  <p>Penyelidikan tanah untuk <b><?=e($project['nama_proyek'])?></b> dilaksanakan di <?=e($address?:'-')?> menggunakan metode sondir sesuai prinsip SNI 2827:2008. Sebanyak <b><?=$pointCount?> titik</b> diuji dengan total <b><?=$allRows?> data kedalaman</b>.</p>
  <table class="summary-grid"><tr><td><b><?=$pointCount?></b>Titik sondir</td><td><b><?=report_number($deepest,2)?> m</b>Kedalaman maksimum</td><td><b><?=report_number($qcMax,0)?></b>qc maksimum (kg/cm²)</td><td><b><?=$allRows?></b>Baris data</td></tr></table>
  <h2>Ringkasan setiap titik</h2>
  <table class="soil-table"><thead><tr><th>Titik</th><th>Koordinat</th><th>Kedalaman</th><th>qc maks.</th><th>Lapisan akhir</th><th>Tanah keras</th></tr></thead><tbody>
  <?php foreach($points as $i=>$point):$stat=$stats[$i];?><tr><td><b>S<?=e($point['nomor_urut'])?></b><br><?=e($point['nama_titik']?:$point['kode_titik'])?></td><td><?=e(($point['latitude']??'-').', '.($point['longitude']??'-'))?></td><td><?=report_number($stat['depth'],2)?> m</td><td><?=report_number($stat['qcMax'],0)?> kg/cm²</td><td><?=e($stat['soil'])?></td><td><?=$stat['hardDepth']===null?'Belum teridentifikasi':report_number($stat['hardDepth'],2).' m'?></td></tr><?php endforeach;?>
  </tbody></table>
  <div class="warning"><b>Catatan teknis:</b> pemilihan jenis, dimensi, dan kedalaman pondasi wajib diverifikasi oleh perencana struktur/geoteknik berdasarkan beban bangunan, penurunan yang diizinkan, kondisi muka air tanah, dan ketentuan proyek.</div>
</section>

<section class="page" id="daftar-isi">
  <h1 class="chapter section-title">Daftar Isi</h1>
  <table class="toc">
    <?php foreach([
      ['Kata Pengantar','kata-pengantar',false],['Ringkasan','ringkasan',false],['Daftar Isi','daftar-isi',false],
      ['BAB I - Pendahuluan','bab-1',true],['1.1 Latar Belakang','bab-1-1',false],['1.2 Tujuan Pengujian','bab-1-2',false],['1.3 Manfaat','bab-1-3',false],
      ['BAB II - Dasar Teori','bab-2',true],['2.1 Pengujian Sondir dan Perangkat','bab-2-1',false],['2.2 Perhitungan Daya Dukung','bab-2-2',false],['2.3 Klasifikasi Robertson SBT 12 Zona','bab-2-3',false],
      ['BAB III - Metodologi Pengujian','bab-3',true],['3.1 Lokasi dan Sebaran Titik','bab-3-1',false],
    ] as [$label,$anchor,$bold]):?><tr><td class="<?=$bold?'bab':''?>"><a href="#<?=$anchor?>"><?=e($label)?></a></td><td><?=report_toc_number($tocPages,$anchor)?></td></tr><?php endforeach;?>
    <?php if($showEquipment):?><tr><td><a href="#bab-3-2">3.2 Peralatan</a></td><td><?=report_toc_number($tocPages,'bab-3-2')?></td></tr><?php endif;?>
    <tr><td><a href="#bab-3-3">3.3 Prosedur</a></td><td><?=report_toc_number($tocPages,'bab-3-3')?></td></tr>
    <tr><td class="bab"><a href="#bab-4">BAB IV - Hasil Penyelidikan dan Analisis</a></td><td><?=report_toc_number($tocPages,'bab-4')?></td></tr>
    <?php foreach($points as $point):$anchor='point-'.(int)$point['nomor_urut'];?><tr><td><a href="#<?=$anchor?>">4.<?=e($point['nomor_urut'])?> Data, Grafik,<?= $showSbt?' Diagram SBT,':''?> dan Analisis Sondir S<?=e($point['nomor_urut'])?></a></td><td><?=report_toc_number($tocPages,$anchor)?></td></tr><?php endforeach;?>
    <tr><td class="bab"><a href="#bab-5">BAB V - Penutup</a></td><td><?=report_toc_number($tocPages,'bab-5')?></td></tr><tr><td><a href="#pengesahan">Halaman Pengesahan</a></td><td><?=report_toc_number($tocPages,'pengesahan')?></td></tr><?php if($includeDocumentation):?><tr><td><a href="#lampiran">Lampiran Dokumentasi</a></td><td><?=report_toc_number($tocPages,'lampiran')?></td></tr><?php endif;?>
  </table>
</section>

<section class="page" id="bab-1">
  <h1 class="chapter">BAB I<br>Pendahuluan</h1>
  <h2 id="bab-1-1">1.1 Latar Belakang</h2>
  <p>Perencanaan pondasi memerlukan informasi kondisi tanah yang memadai agar beban struktur dapat diteruskan dengan aman. Variasi lapisan tanah yang tidak teridentifikasi dapat menimbulkan penurunan berlebih, ketidakstabilan, atau kegagalan pondasi. Oleh karena itu, investigasi geoteknik diperlukan sebelum penetapan sistem pondasi.</p>
  <p>Uji sondir memberikan profil tahanan konus dan hambatan lekat secara kontinu terhadap kedalaman. Data tersebut digunakan untuk memperkirakan stratifikasi, kekuatan relatif tanah, serta korelasi awal daya dukung pondasi.</p>
  <h2 id="bab-1-2">1.2 Tujuan Pengujian</h2>
  <ol><li>Mengetahui perubahan tahanan tanah terhadap kedalaman pada seluruh titik proyek.</li><li>Memperkirakan jenis dan konsistensi/kepadatan lapisan tanah.</li><li>Menyediakan data untuk analisis awal daya dukung pondasi dangkal dan pondasi tiang.</li><li>Memberikan rekomendasi teknis awal bagi perencana.</li></ol>
  <h2 id="bab-1-3">1.3 Manfaat</h2>
  <p>Hasil penyelidikan menjadi masukan bagi pemilik, perencana, dan pelaksana dalam memilih alternatif pondasi, menentukan kedalaman rencana, serta mengidentifikasi kebutuhan penyelidikan lanjutan.</p>
</section>

<section class="page" id="bab-2">
  <h1 class="chapter">BAB II<br>Dasar Teori</h1>
  <h2 id="bab-2-1">2.1 Pengujian Sondir dan Perangkat</h2>
  <p>Sondir atau <i>Cone Penetration Test</i> merupakan pengujian penetrasi statis untuk memperoleh perubahan perlawanan tanah terhadap kedalaman. Berdasarkan SNI 2827:2008, konus tunggal atau ganda ditekan secara mekanik atau hidraulik. Sistem reaksi menahan gaya dorong, batang meneruskan gaya ke ujung, dan manometer atau indikator beban mencatat perlawanan selama penetrasi.</p>
  <img class="equipment-diagram" src="<?=report_sondir_equipment_uri()?>" alt="Skema alat sondir hidraulik dan bikonus berdasarkan SNI 2827:2008">
  <div class="figure-caption">Gambar 2.1 Skema perangkat sondir dan detail bikonus. Digambar ulang berdasarkan ketentuan umum SNI 2827:2008.</div>
  <p>Perangkat utama terdiri atas mesin atau rangka penekan, dongkrak hidraulik, balok reaksi dan angkur, batang tekan serta batang dalam, manometer, konus, dan selimut geser. Posisi alat harus tegak agar gaya penetrasi bekerja aksial. Pada bikonus, pembacaan konus (<i>Cw</i>) dan pembacaan total (<i>Tw</i>) digunakan untuk menghitung hambatan lekat, tahanan konus <i>qc</i>, hambatan lekat setempat <i>fs</i>, total friksi <i>Tf</i>, serta rasio friksi <i>FR</i>.</p>
  <p class="standard-note"><b>Acuan:</b> SNI 2827:2008, Cara Uji Penetrasi Lapangan dengan Alat Sondir. Parameter pada skema mencakup sudut konus 60 +/- 5 derajat, diameter 35,7 +/- 0,4 mm, luas selimut 150 +/- 3 cm2, laju penetrasi 10-20 mm/s, dan pembacaan berkala 20 cm.</p>
  <div class="formula"><?=report_formula_html('<b>Kw = Tw − Cw</b><br><b>fs = Kw × (Api/As)</b><br><b>Tf = Σ(fs × interval)</b><br><b>FR = fs/qc × 100%</b>')?></div>
  <h2 id="bab-2-2">2.2 Daya Dukung Pondasi</h2>
  <h3>Pondasi tiang — korelasi Meyerhof</h3>
  <div class="formula"><?=report_formula_html('Q<sub>izin</sub> = (qc × A<sub>b</sub> / 3 + Tf × K / 5) / 100 &nbsp; [kN]')?></div>
  <p>qc adalah tahanan ujung konus, Tf adalah total friksi, A<sub>b</sub> luas ujung tiang, dan K keliling penampang.</p>
  <h3>Pondasi dangkal — Meyerhof (1956)</h3>
  <div class="formula"><?=report_formula_html('B ≤ 1,20 m: q<sub>a</sub> = q̄c / 30<br>B &gt; 1,20 m: q<sub>a</sub> = q̄c/50 × ((B + 0,30)/B)²')?></div>
  <h3>Pondasi dangkal — Schmertmann (1978)</h3>
  <div class="formula"><?=report_formula_html('Lempung/lanau: q<sub>u</sub> = 5 + 0,34qc<br>Pasir: q<sub>u</sub> = 48 − 0,009(300 − qc)<sup>1,5</sup><br>q<sub>a</sub> = q<sub>u</sub>/3, dengan syarat Df/B ≤ 1,5')?></div>
  <h2 id="bab-2-3">2.3 Klasifikasi Robertson SBT 12 Zona</h2>
  <p>Jenis perilaku tanah (<i>Soil Behavior Type</i>/SBT) ditentukan pada diagram Robertson non-normalisasi menggunakan tahanan konus qc dalam MPa dan rasio friksi FR. Diagram terdiri dari 12 zona, mulai tanah halus sensitif, tanah organik, lempung, lanau, pasir, hingga tanah berbutir sangat kaku. Hasil SBT adalah korelasi perilaku mekanis dan bukan pengganti identifikasi tekstur dari contoh tanah.</p>
  <div class="warning"><b>Acuan:</b> pelaksanaan uji mengacu pada prinsip SNI 2827:2008. Interpretasi SBT menggunakan diagram Robertson 12 zona dan harus dibaca bersama data lapangan, geologi setempat, serta pengujian pendukung.</div>
</section>

<section class="page" id="bab-3">
  <h1 class="chapter">BAB III<br>Metodologi Pengujian</h1>
  <h2 id="bab-3-1">3.1 Lokasi dan Sebaran Titik</h2>
  <p>Pengujian dilaksanakan di <?=e($address?:'-')?>. Seluruh titik pada proyek ditampilkan berdasarkan koordinat tersimpan.</p>
  <?php if($showMap):?><img class="map" src="<?=report_location_uri($points,(string)$settings['map_type'])?>" alt="Peta sebaran titik sondir"><div class="figure-caption">Gambar 3.1 Peta <?=$settings['map_type']==='satellite'?'citra satelit':'koordinat'?> dan sebaran <?=$pointCount?> titik sondir. Sumber citra: Esri World Imagery.</div><?php endif;?>
  <table class="soil-table"><tr><th>Titik</th><th>Nama titik</th><th>Latitude</th><th>Longitude</th><th>Alamat/deskripsi</th></tr><?php foreach($points as $point):?><tr><td>S<?=e($point['nomor_urut'])?></td><td><?=e($point['nama_titik']?:'-')?></td><td><?=e($point['latitude']??'-')?></td><td><?=e($point['longitude']??'-')?></td><td><?=e($point['alamat_lokasi']?:$point['deskripsi_posisi'])?></td></tr><?php endforeach;?></table>
  <?php if($showEquipment):?><div class="keep-together"><h2 id="bab-3-2">3.2 Peralatan</h2>
  <?php $tools=[];foreach($points as $point)$tools[(int)$point['alat_id']]=$point;foreach($tools as $tool):?>
  <table class="info"><tr><td>Kode / alat</td><td><?=e($tool['kode_alat'].' — '.$tool['nama_alat'])?></td><td>Merek / model</td><td><?=e(trim($tool['merek'].' '.$tool['model']))?></td></tr><tr><td>Kapasitas</td><td><?=e(report_number((float)$tool['kapasitas_maksimum'],2).' '.$tool['satuan_kapasitas'])?></td><td>Nomor seri</td><td><?=e($tool['nomor_seri']?:'-')?></td></tr><tr><td>Dimensi</td><td colspan="3">Piston <?=report_number((float)$tool['diameter_piston'],3)?> cm; konus <?=report_number((float)$tool['diameter_konus'],3)?> cm; selimut <?=report_number((float)$tool['diameter_selimut'],3)?> cm; panjang selimut <?=report_number((float)$tool['panjang_selimut_geser'],3)?> cm.</td></tr><tr><td>Kalibrasi</td><td><?=e($tool['nomor_sertifikat']?:'-')?></td><td>Berlaku sampai</td><td><?=e(tanggal_id($tool['tanggal_kedaluwarsa']))?></td></tr></table>
  <?php endforeach;?></div><?php endif;?>
  <div class="keep-together"><h2 id="bab-3-3">3.3 Prosedur Pengujian</h2>
  <ol><li>Menentukan dan menyiapkan titik uji, kemudian memasang alat secara tegak.</li><li>Menekan konus pada kecepatan terkendali dan melakukan pembacaan Cw serta Tw pada interval tersimpan.</li><li>Menghitung qc, fs, Tf, dan FR serta memeriksa kewajaran data.</li><li>Menghentikan pengujian pada kedalaman rencana, kapasitas alat, atau lapisan sangat keras.</li><li>Menginterpretasikan profil tanah dan korelasi daya dukung.</li></ol>
  </div>
</section>

<?php $firstPoint=true;foreach($points as $point):$stat=report_point_stats($point);$rows=$point['rows'];$soilCells=$rows?report_soil_cells($rows):[];$waterRow=report_water_row_index($rows,$point['muka_air_tanah']);?>
<section class="<?=$firstPoint?'page':'report-flow'?>" <?=$firstPoint?'id="bab-4"':''?>>
  <?php if($firstPoint):?><h1 class="chapter">BAB IV<br>Hasil Penyelidikan Tanah dan Analisis</h1><p>Hasil disajikan per titik dalam satu laporan proyek. Sumbu vertikal pada grafik menunjukkan kedalaman dan bertambah ke arah bawah; grafik gabungan menampilkan Tf pada sumbu horizontal atas dan qc pada sumbu horizontal bawah.</p><?php endif;?>
  <div class="point-head" id="point-<?=(int)$point['nomor_urut']?>"><b>4.<?=e($point['nomor_urut'])?> DATA SONDIR S<?=e($point['nomor_urut'])?></b><span><?=e($point['kode_titik'])?></span></div>
  <table class="info"><tr><td>Nama titik</td><td><?=e($point['nama_titik']?:'Sondir '.$point['nomor_urut'])?></td><td>Tanggal</td><td><?=e(tanggal_id($point['tanggal_pengujian']))?></td></tr><tr><td>Koordinat</td><td><?=e(($point['latitude']??'-').', '.($point['longitude']??'-'))?></td><td>Operator</td><td><?=e($point['operator'])?></td></tr><tr><td>Alat</td><td><?=e($point['kode_alat'].' — '.$point['nama_alat'])?></td><td>Kedalaman</td><td><?=report_number($stat['depth'],2)?> m</td></tr><tr><td>Muka air</td><td><?=e($point['muka_air_tanah']!==null?report_number((float)$point['muka_air_tanah'],2).' m':'Tidak teramati')?></td><td>Cuaca</td><td><?=e($point['kondisi_cuaca']?:'-')?></td></tr></table>
  <?php if(!$rows):?><div class="warning">Data pengujian untuk titik ini belum diisi.</div>
  <?php else:?>
  <table class="data-table"><thead><tr><th>No</th><th>Z<span class="unit">m</span></th><th>Cw<span class="unit">kg/cm²</span></th><th>Tw<span class="unit">kg/cm²</span></th><th>Kw<span class="unit">kg/cm²</span></th><th>qc<span class="unit">kg/cm²</span></th><th>fs<span class="unit">kg/cm²</span></th><th>fs·20<span class="unit">kg/cm</span></th><th>Tf<span class="unit">kg/cm</span></th><th>FR<span class="unit">%</span></th><th>Zona<span class="unit">SBT</span></th><th class="soil-width">Perkiraan jenis tanah</th></tr></thead><tbody>
  <?php foreach($rows as $globalIndex=>$row):$soilCell=$soilCells[$globalIndex];?><tr><td><?=e($row['nomor'])?></td><td><?=report_number((float)$row['kedalaman'],3)?></td><td><?=report_number((float)$row['bacaan_konus'],0)?></td><td><?=report_number((float)$row['bacaan_total'],0)?></td><td><?=report_number((float)$row['hambatan_total'],0)?></td><td><?=report_number((float)$row['qc'],0)?></td><td><?=report_number((float)$row['fs'],3)?></td><td><?=report_number((float)$row['fs']*20,2)?></td><td><?=report_number((float)$row['jhp'],2)?></td><td><?=report_number((float)$row['friction_ratio'],2)?></td><td><?=$row['zona_sbt']===null?'-':'Z'.e($row['zona_sbt']).(!empty($row['batas_zona'])?'*':'')?></td><td class="soil-layer-pdf <?=$soilCell['start']?'soil-layer-start':''?> <?=$soilCell['end']?'soil-layer-end':''?> <?=$waterRow===$globalIndex?'groundwater-pdf':''?>" style="background-color:<?=$soilCell['background']?>;<?=$soilCell['image']?'background-image:url('.$soilCell['image'].');':''?>"><?=e($soilCell['label'])?></td></tr><?php endforeach;?>
  </tbody></table>
  <p class="muted small">Zona SBT mengikuti diagram Robertson 12 zona. Tanda * menunjukkan titik pada batas zona; tanda - menunjukkan data di luar rentang diagram.</p>
  <?php endif;?>
</section>
<?php if($rows):?>
<section class="report-flow keep-together">
  <div class="point-head"><b>GRAFIK DAN PROFIL S<?=e($point['nomor_urut'])?></b><span><?=e($point['kode_titik'])?></span></div>
  <img class="chart" src="<?=report_chart_uri($rows)?>" alt="Grafik qc, Tf, dan FR"><div class="figure-caption">Grafik qc dan Tf serta FR terhadap kedalaman — S<?=e($point['nomor_urut'])?></div>
  <h2>Interpretasi lapisan</h2><table class="soil-table"><tr><th>Kedalaman</th><th>Rentang qc</th><th>Perkiraan jenis dan konsistensi/kepadatan</th></tr><?php foreach(report_soil_intervals($rows) as $segment):?><tr><td><?=report_number($segment['from'],2)?>–<?=report_number($segment['to'],2)?> m</td><td><?=report_number($segment['qc_min'],0)?>–<?=report_number($segment['qc_max'],0)?> kg/cm²</td><td class="soil-swatch"><?=e($segment['label'])?></td></tr><?php endforeach;?></table>
</section>

<?php if($showSbt):?>
<section class="report-flow keep-together">
  <div class="point-head"><b>DIAGRAM ROBERTSON SBT 12 ZONA — S<?=e($point['nomor_urut'])?></b><span>qc (MPa) dan FR (%)</span></div>
  <img class="chart" src="<?=report_sbt_chart_uri($rows,$sbtShowConnectionLine,$sbtLineStyle)?>" alt="Diagram Robertson SBT 12 zona dengan label kedalaman"><div class="figure-caption">Posisi dan kedalaman data S<?=e($point['nomor_urut'])?> pada Diagram Robertson SBT 12 Zona<?= $sbtShowConnectionLine?' — garis '.($sbtLineStyle==='dashed'?'putus-putus':'penuh'):''?></div>
  <?php $zoneCounts=[];foreach($rows as $row){$zone=(int)($row['zona_sbt']??0);if($zone>0)$zoneCounts[$zone]=($zoneCounts[$zone]??0)+1;}ksort($zoneCounts);?>
  <table class="soil-table"><thead><tr><th>Zona</th><th>Jumlah data</th><th>Jenis perilaku tanah</th></tr></thead><tbody><?php foreach($zoneCounts as $zone=>$count):$zoneConfig=array_values(array_filter(sondir_soil_chart_config()['zones']??[],fn($item)=>(int)$item['zone']===$zone));?><tr><td>Z<?=$zone?></td><td><?=$count?></td><td><?=e($zoneConfig[0]['name_id']??'-')?></td></tr><?php endforeach;?><?php if(!$zoneCounts):?><tr><td colspan="3">Tidak ada data yang berada dalam rentang diagram.</td></tr><?php endif;?></tbody></table>
  <p class="muted small">Catatan: titik dengan qc di bawah 0,1 MPa atau di luar rentang FR 0-10% tidak diplot. Zona SBT merupakan interpretasi perilaku tanah, bukan klasifikasi tekstur langsung.</p>
</section>
<?php endif;?>

<?php if($showFoundation):?>
<section class="report-flow">
  <div class="point-head"><b>DAYA DUKUNG PONDASI TIANG — S<?=e($point['nomor_urut'])?></b><span>Meyerhof</span></div>
  <div class="formula"><?=report_formula_html('Q<sub>izin</sub> = (qc × A<sub>b</sub>/3 + Tf × K/5) / 100 [kN]')?></div>
  <table class="foundation-table"><thead><tr><th rowspan="3">Df<span class="unit">m</span></th><th rowspan="3">qc<span class="unit">kg/cm²</span></th><th rowspan="3">Tf<span class="unit">kg/cm</span></th><th colspan="8" class="group">Daya dukung izin satu tiang (kN)</th></tr><tr><th colspan="4">Mini pile kotak (m)</th><th colspan="4">Strauss pile (m)</th></tr><tr><?php foreach([.2,.25,.3,.35,.2,.25,.3,.35] as $size):?><th><?=report_number($size,2)?></th><?php endforeach;?></tr></thead><tbody>
  <?php foreach($rows as $row):?><tr><td><?=report_number((float)$row['kedalaman'],2)?></td><td><?=report_number((float)$row['qc'],0)?></td><td><?=report_number((float)$row['jhp'],2)?></td><?php foreach([.2,.25,.3,.35] as $size):?><td><?=report_number(report_pile_capacity((float)$row['qc'],(float)$row['jhp'],$size,false),2)?></td><?php endforeach;?><?php foreach([.2,.25,.3,.35] as $size):?><td><?=report_number(report_pile_capacity((float)$row['qc'],(float)$row['jhp'],$size,true),2)?></td><?php endforeach;?></tr><?php endforeach;?>
  </tbody></table><div class="foundation-note">Mini pile menggunakan penampang kotak; Strauss pile menggunakan penampang lingkaran. Faktor keamanan ujung = 3 dan selimut = 5.</div>
</section>

<?php $widths=[.5,.6,.7,.8,.9,1,1.1,1.2,1.3,1.4,1.5,1.6,1.7,1.8,1.9,2];$widthGroups=array_chunk($widths,8);?>
<section class="report-flow">
  <div class="point-head"><b>PONDASI DANGKAL — S<?=e($point['nomor_urut'])?></b><span>Meyerhof (1956)</span></div>
  <div class="formula"><?=report_formula_html('B ≤ 1,20 m: qa = q̄c/30. B &gt; 1,20 m: qa = q̄c/50 × ((B + 0,30)/B)².')?></div>
  <?php foreach($widthGroups as $group):?><div class="foundation-range"><div class="foundation-range-title">Rentang lebar B = <?=report_number($group[0],1)?> sampai <?=report_number(end($group),1)?> m</div><table class="foundation-table"><thead><tr><th rowspan="2">Df<span class="unit">m</span></th><th rowspan="2"><span class="math-symbol">q&#772;</span>c<span class="unit">kg/cm²</span></th><th colspan="<?=count($group)?>" class="group">P izin (kN) untuk lebar pondasi B = L (m)</th></tr><tr><?php foreach($group as $width):?><th><?=report_number($width,1)?></th><?php endforeach;?></tr></thead><tbody>
  <?php foreach($rows as $row):$average=report_average_qc($rows,(float)$row['kedalaman']);?><tr><td><?=report_number((float)$row['kedalaman'],2)?></td><td><?=report_number($average,1)?></td><?php foreach($group as $width):?><td><?=report_number(report_meyerhof($average,$width),1)?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endforeach;?>
</section>

<section class="report-flow">
  <div class="point-head"><b>PONDASI DANGKAL — S<?=e($point['nomor_urut'])?></b><span>Schmertmann (1978)</span></div>
  <div class="formula"><?=report_formula_html('Lempung/lanau: qu = 5 + 0,34qc. Pasir: qu = 48 − 0,009(300 − qc)<sup>1,5</sup>. qa = qu/3; Df/B ≤ 1,5.')?></div>
  <?php foreach($widthGroups as $group):?><div class="foundation-range"><div class="foundation-range-title">Rentang lebar B = <?=report_number($group[0],1)?> sampai <?=report_number(end($group),1)?> m</div><table class="foundation-table"><thead><tr><th rowspan="2">Df<span class="unit">m</span></th><th rowspan="2">qc<span class="unit">kg/cm²</span></th><th rowspan="2">Jenis</th><th colspan="<?=count($group)?>" class="group">P izin (kN) untuk lebar pondasi B = L (m)</th></tr><tr><?php foreach($group as $width):?><th><?=report_number($width,1)?></th><?php endforeach;?></tr></thead><tbody>
  <?php foreach($rows as $row):?><tr><td><?=report_number((float)$row['kedalaman'],2)?></td><td><?=report_number((float)$row['qc'],0)?></td><td><?=e(str_starts_with((string)$row['jenis_tanah'],'Lempung')||str_starts_with((string)$row['jenis_tanah'],'Lanau')?'Kohesif':'Pasir')?></td><?php foreach($group as $width):$capacity=report_schmertmann((float)$row['qc'],(string)$row['jenis_tanah'],(float)$row['kedalaman'],$width);?><td><?=$capacity===null?'—':report_number($capacity,1)?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endforeach;?>
</section>
<?php endif;?>
<?php endif;$firstPoint=false;endforeach;?>

<section class="page" id="bab-5">
  <h1 class="chapter">BAB V<br>Penutup</h1><h2>5.1 Kesimpulan</h2>
  <p>Penyelidikan tanah pada proyek <?=e($project['nama_proyek'])?> telah dilaksanakan pada <?=$pointCount?> titik. Rangkuman kondisi setiap titik adalah sebagai berikut:</p>
  <table class="soil-table"><tr><th>Titik</th><th>Kedalaman uji</th><th>qc maksimum</th><th>Indikasi tanah keras</th><th>Lapisan akhir</th><th>Muka air</th></tr><?php foreach($points as $i=>$point):$stat=$stats[$i];?><tr><td>S<?=e($point['nomor_urut'])?></td><td><?=report_number($stat['depth'],2)?> m</td><td><?=report_number($stat['qcMax'],0)?> kg/cm²</td><td><?=$stat['hardDepth']===null?'Belum teridentifikasi':report_number($stat['hardDepth'],2).' m'?></td><td><?=e($stat['soil'])?></td><td><?=$point['muka_air_tanah']===null?'Tidak teramati':report_number((float)$point['muka_air_tanah'],2).' m'?></td></tr><?php endforeach;?></table>
  <h2>5.2 Rekomendasi</h2>
  <ol><li>Gunakan tabel daya dukung sebagai korelasi awal; dimensi akhir pondasi ditentukan dari reaksi struktur dan pemeriksaan penurunan.</li><li>Untuk pondasi dangkal, pilih lapisan yang seragam dan memenuhi syarat kedalaman/lebar metode yang digunakan.</li><li>Untuk beban besar atau lapisan atas yang lemah/tidak seragam, evaluasi pondasi tiang pada kedalaman yang memiliki qc dan Tf memadai.</li><li>Lakukan verifikasi geoteknik lanjutan bila terdapat perbedaan elevasi, urugan, muka air, atau kondisi lapangan yang tidak terwakili oleh titik sondir.</li></ol>
  <div class="warning"><b>Batasan:</b> interpretasi jenis tanah dari sondir merupakan korelasi empiris. Identifikasi visual dan parameter desain sebaiknya dikonfirmasi dengan boring, sampling, dan pengujian laboratorium bila tingkat risiko proyek memerlukannya.</div>
</section>

<section class="page" id="pengesahan">
  <h1 class="chapter section-title">Halaman Pengesahan</h1>
  <h2 class="center">LAPORAN HASIL UJI SONDIR<br>(CONE PENETRATION TEST)</h2>
  <p class="center">Laporan ini memuat seluruh <?=$pointCount?> titik sondir pada satu proyek dan telah disusun oleh <?=e($labName)?>, <?=e($institution)?>.</p>
  <table class="info"><tr><td>Nomor laporan</td><td><?=e($reportNo)?></td><td>Jumlah titik</td><td><?=$pointCount?> titik</td></tr><tr><td>Proyek</td><td><?=e($project['nama_proyek'])?></td><td>Tanggal uji</td><td><?=e(tanggal_id($reportDate))?></td></tr><tr><td>Lokasi</td><td colspan="3"><?=e($address?:'-')?></td></tr></table>
  <table class="approval"><tr><td>Pelaksana Geoteknik<div class="signature-space"></div><b><?=e($operator?:'-')?></b></td><td><?=e(($settings['examiner_city']?:($lab['kabupaten']??'Baubau')).', '.tanggal_id(date('Y-m-d')))?><br><?=e($settings['signer_position']?:'Kepala Laboratorium / Pemeriksa')?><div class="signature-space"><?php if($stampUri):?><img class="stamp-image" src="<?=$stampUri?>" alt="Stempel"><?php endif;?><?php if($signatureUri):?><img class="signature-image" src="<?=$signatureUri?>" alt="Tanda tangan"><?php else:?><img class="signer-qr" src="<?=$signerQr?>" alt="QR penanda tangan Kepala Laboratorium"><?php endif;?></div><div class="signer-name"><b><?=e($head)?></b><?php if($settings['signer_identity']):?><br><?=e($settings['signer_identity'])?><?php endif;?><br><?=e($examiner&&$examiner!==$head?'Pemeriksa: '.$examiner:'')?></div></td></tr></table>
  <div class="legal-box"><img src="<?=$legalQr?>" alt="QR legalitas laporan"><b>LEGALITAS LAPORAN ELEKTRONIK</b><p>Pindai kode ini untuk memeriksa nomor laporan, proyek, status data, dan identitas penerbit. QR legalitas ini berbeda dari QR penanda tangan Kepala Laboratorium.</p><div class="legal-code">KODE: <?=e($legalToken)?></div></div>
</section>

<?php if($includeDocumentation):?><section class="page" id="lampiran">
  <h1 class="chapter section-title">Lampiran Dokumentasi</h1>
  <?php $documents=[];foreach($points as $point)foreach($point['documentation'] as $doc)$documents[]=[$point,$doc];?>
  <?php if(!$documents):?><div class="empty-photo">Belum ada dokumentasi foto yang tersimpan untuk proyek ini.</div><p class="center muted">Lampiran akan terisi otomatis setelah foto ditambahkan pada titik sondir.</p>
  <?php else:?><table class="photo-grid"><?php foreach(array_chunk($documents,2) as $pair):?><tr><?php foreach($pair as [$point,$doc]):$path=report_documentation_path((string)$doc['nama_file']);?><td><?php if($path&&($uri=report_image_data_uri($path))):?><img src="<?=$uri?>" alt="<?=e($doc['judul'])?>"><?php else:?><div class="empty-photo">Berkas foto tidak ditemukan</div><?php endif;?><b>S<?=e($point['nomor_urut'])?> — <?=e($doc['judul']?:$doc['jenis_foto'])?></b><br><span class="small"><?=e($doc['keterangan']?:'Dokumentasi pengujian lapangan')?></span></td><?php endforeach;?><?php if(count($pair)===1):?><td></td><?php endif;?></tr><?php endforeach;?></table><?php endif;?>
</section><?php endif;?>
</body></html>
    <?php
    return ['html'=>(string)ob_get_clean(),'filename'=>'Laporan-Proyek-'.$project['kode_proyek'].'.pdf','number'=>$reportNo,'project'=>$project,'points'=>$points];
}
