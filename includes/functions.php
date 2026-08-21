<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function flash(string $type, string $message): void { $_SESSION['flash'] = compact('type', 'message'); }
function old(string $key, mixed $default = ''): mixed { return $_SESSION['old'][$key] ?? $default; }
function tanggal_id(?string $date, bool $withTime = false): string {
    if (!$date) return '-';
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $bulan[(int)$d->format('n')] . ' ' . $d->format('Y') . ($withTime ? ' ' . $d->format('H:i') : '');
}
function status_badge(string $status): string {
    $map = ['draft'=>'secondary','sedang_diuji'=>'primary','menunggu_pemeriksaan'=>'warning','perlu_revisi'=>'danger','disetujui'=>'success','diterbitkan'=>'info','dibatalkan'=>'dark','aktif'=>'success','tidak_aktif'=>'secondary'];
    return '<span class="badge text-bg-' . ($map[$status] ?? 'secondary') . '">' . e(ucwords(str_replace('_',' ',$status))) . '</span>';
}
function audit(PDO $pdo, string $aktivitas, ?string $tabel = null, ?int $id = null, mixed $lama = null, mixed $baru = null): void {
    $q=$pdo->prepare('INSERT INTO audit_log(user_id,aktivitas,nama_tabel,data_id,data_lama,data_baru,alamat_ip,user_agent) VALUES(?,?,?,?,?,?,?,?)');
    $q->execute([$_SESSION['user']['id']??null,$aktivitas,$tabel,$id,$lama?json_encode($lama):null,$baru?json_encode($baru):null,$_SERVER['REMOTE_ADDR']??'',substr($_SERVER['HTTP_USER_AGENT']??'',0,1000)]);
}
function paginate(int $total, int $page, int $perPage): string {
    $perPage = max(1, $perPage);
    $pages=(int)ceil($total/$perPage); if($pages<=1)return '';
    $html='<nav aria-label="Navigasi halaman"><ul class="pagination pagination-sm mb-0">';
    for($i=1;$i<=$pages;$i++){ $qs=$_GET; $qs['page']=$i; $html.='<li class="page-item '.($i===$page?'active':'').'"><a class="page-link" href="?'.e(http_build_query($qs)).'">'.$i.'</a></li>'; }
    return $html.'</ul></nav>';
}
function next_code(PDO $pdo, string $table, string $column, string $prefix, int $digits = 4): string {
    $allowed=['klien'=>'kode_klien','proyek'=>'kode_proyek','alat_sondir'=>'kode_alat'];
    if(($allowed[$table]??null)!==$column) throw new InvalidArgumentException('Kode tidak valid');
    $stmt=$pdo->query("SELECT MAX(id) n FROM {$table}"); $n=(int)$stmt->fetchColumn()+1;
    return $prefix.str_pad((string)$n,$digits,'0',STR_PAD_LEFT);
}

const KG_CM2_TO_MPA = 0.0980665;

function sondir_soil_chart_config(): array {
    static $config;
    if ($config === null) {
        $path=APP_ROOT.'/config/soil_classification_zones.json';
        $config=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    }
    return $config;
}

/**
 * Klasifikasi Soil Behavior Type (SBT) Robertson 1986, 12 zona.
 * Data Sondir menyimpan qc dalam kg/cm2, sedangkan diagram memakai qc dalam MPa.
 */
function sondir_soil_classification(float $qc, float $frictionRatio): array {
    if ($qc <= 0 || $frictionRatio < 0) {
        return ['ic'=>null,'jenis'=>'','zone_number'=>null,'warna'=>'#94a3b8','boundary_flag'=>false,'qc_mpa'=>0.0,'versi'=>sondir_soil_chart_config()['version']??'unknown'];
    }

    static $classifier;
    $classifier??=new SoilClassifier(APP_ROOT.'/config/soil_classification_zones.json');
    $qcMpa=$qc*KG_CM2_TO_MPA;
    $result=$classifier->classify($qcMpa,$frictionRatio);
    return [
        'ic'=>null,
        'jenis'=>$result['soil_type_id'],
        'zone_number'=>$result['zone_number'],
        'warna'=>$result['color'],
        'boundary_flag'=>$result['boundary_flag'],
        'qc_mpa'=>round($qcMpa,4),
        'versi'=>$classifier->version(),
    ];
}

function sondir_soil_classification_mpa(float $qcMpa,float $frictionRatio): array {
    if($qcMpa<=0||$frictionRatio<0)return ['ic'=>null,'jenis'=>'','zone_number'=>null,'warna'=>'#94a3b8','boundary_flag'=>false,'qc_mpa'=>0.0,'versi'=>sondir_soil_chart_config()['version']??'unknown'];
    static $classifier;$classifier??=new SoilClassifier(APP_ROOT.'/config/soil_classification_zones.json');$result=$classifier->classify($qcMpa,$frictionRatio);
    return ['ic'=>null,'jenis'=>$result['soil_type_id'],'zone_number'=>$result['zone_number'],'warna'=>$result['color'],'boundary_flag'=>$result['boundary_flag'],'qc_mpa'=>round($qcMpa,6),'versi'=>$classifier->version()];
}

/**
 * Konsistensi/kepadatan berdasarkan qc dan kelompok jenis tanah.
 * Lanau/lempung diperlakukan sebagai kohesif; pasir/kerikil non-kohesif.
 */
function sondir_strength_classification(float $qc, string $soilType): string {
    if ($qc <= 0 || $soilType === '' || $soilType === 'Di luar rentang klasifikasi') return '';

    $cohesive=str_starts_with($soilType,'Lempung')
        ||str_starts_with($soilType,'Lanau')
        ||str_starts_with($soilType,'Tanah berbutir halus')
        ||str_starts_with($soilType,'Tanah organik');
    if ($cohesive) {
        return match(true){
            $qc<5=>'Sangat Lunak',
            $qc<10=>'Lunak',
            $qc<20=>'Teguh / Sedang',
            $qc<40=>'Kaku',
            $qc<=80=>'Sangat Kaku',
            default=>'Keras',
        };
    }

    return match(true){
        $qc<20=>'Sangat Lepas',
        $qc<40=>'Lepas',
        $qc<120=>'Agak Padat',
        $qc<=200=>'Padat',
        default=>'Sangat Padat',
    };
}

function sondir_soil_display_name(string $soilType): string {
    return $soilType;
}
