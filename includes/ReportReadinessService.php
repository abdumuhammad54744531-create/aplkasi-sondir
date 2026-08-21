<?php
declare(strict_types=1);

final class ReportReadinessService
{
    public function __construct(private PDO $pdo){}

    public function evaluate(int $projectId,bool $persist=true): array
    {
        $q=$this->pdo->prepare('SELECT * FROM proyek WHERE id=?');$q->execute([$projectId]);$project=$q->fetch();if(!$project)throw new InvalidArgumentException('Proyek tidak ditemukan.');
        $q=$this->pdo->prepare("SELECT t.*,a.tanggal_kedaluwarsa,a.nomor_sertifikat,(SELECT COUNT(*) FROM hasil_sondir h WHERE h.titik_sondir_id=t.id) reading_count,(SELECT COUNT(*) FROM hasil_sondir h WHERE h.titik_sondir_id=t.id AND h.status_validasi='tidak_valid') error_count FROM titik_sondir t LEFT JOIN alat_sondir a ON a.id=t.alat_id WHERE t.proyek_id=? ORDER BY t.nomor_urut");$q->execute([$projectId]);$points=$q->fetchAll();
        $checks=[];$add=function(string $code,string $label,string $status,string $detail='')use(&$checks):void{$checks[]=['code'=>$code,'label'=>$label,'status'=>$status,'detail'=>$detail];};
        $add('PROJECT', 'Data proyek',trim((string)$project['nama_proyek'])!==''&&trim((string)$project['alamat_lokasi'])!==''?'valid':'error','Nama proyek dan lokasi wajib tersedia.');
        $add('POINTS','Minimal satu titik sondir',$points?'valid':'error',$points?count($points).' titik tersedia.':'Belum ada titik.');
        $add('EQUIPMENT','Data alat',!$points||array_filter($points,fn($p)=>empty($p['alat_id']))?'error':'valid','Setiap titik harus memiliki alat.');
        $expired=array_filter($points,fn($p)=>empty($p['nomor_sertifikat'])||(!empty($p['tanggal_kedaluwarsa'])&&$p['tanggal_kedaluwarsa']<date('Y-m-d')));$add('CALIBRATION','Kalibrasi alat',$expired?'warning':'valid',$expired?'Sertifikat/masa berlaku beberapa alat perlu diperiksa.':'Kalibrasi tersedia.');
        $add('OPERATOR','Operator',!$points||array_filter($points,fn($p)=>empty($p['operator_id']))?'error':'valid','Operator wajib tercatat pada setiap titik.');
        $add('READINGS','Data pengukuran',!$points||array_filter($points,fn($p)=>(int)$p['reading_count']===0)?'error':'valid','Setiap titik harus memiliki reading.');
        $invalid=array_sum(array_map(fn($p)=>(int)$p['error_count'],$points));$add('CALCULATION','Perhitungan dan validasi',$invalid?'error':'valid',$invalid?"$invalid baris memiliki error fatal.":'Tidak ada error fatal.');
        $add('COORDINATES','Koordinat',!$points||array_filter($points,fn($p)=>$p['latitude']===null||$p['longitude']===null)?'warning':'valid','Koordinat dipakai pada peta laporan.');
        $add('STOP_NOTE','Catatan penghentian',!$points||array_filter($points,fn($p)=>trim((string)$p['alasan_penghentian'])==='')?'warning':'valid','Alasan penghentian tidak boleh diasumsikan sebagai bedrock.');
        $add('INTERPRETATION','Interpretasi',!$points||array_filter($points,fn($p)=>(int)$p['reading_count']>0&&(int)$this->zoneCount((int)$p['id'])===0)?'warning':'valid','SBT/korelasi harus dapat direview.');
        $add('REVIEWER','Reviewer',empty($project['pemeriksa_id'])?'warning':'valid','Pemeriksa proyek perlu ditetapkan.');
        $valid=count(array_filter($checks,fn($c)=>$c['status']==='valid'));$warnings=count(array_filter($checks,fn($c)=>$c['status']==='warning'));$errors=count($checks)-$valid-$warnings;$score=round(($valid+$warnings*.5)/count($checks)*100,2);$category=$errors?'Belum Lengkap':($warnings?'Perlu Review':($score>=100?'Siap Disahkan':'Perlu Review'));
        if($persist){$statement=$this->pdo->prepare("INSERT INTO report_readiness_checks(proyek_id,check_code,label,status,detail,checked_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE label=VALUES(label),status=VALUES(status),detail=VALUES(detail),checked_at=NOW()");foreach($checks as $check)$statement->execute([$projectId,$check['code'],$check['label'],$check['status'],$check['detail']]);}
        return compact('score','category','valid','warnings','errors','checks');
    }

    private function zoneCount(int $pointId): int{$q=$this->pdo->prepare('SELECT COUNT(*) FROM hasil_sondir WHERE titik_sondir_id=? AND zona_sbt IS NOT NULL');$q->execute([$pointId]);return (int)$q->fetchColumn();}
}

