<?php
declare(strict_types=1);

final class SondirValidationService
{
    public function validateReading(array $current,?array $previous,array $tool): array
    {
        $issues=[];$add=function(string $level,string $code,string $message)use(&$issues):void{$issues[]=['level'=>$level,'code'=>$code,'message'=>$message];};
        if($current['depth']<=0)$add('error','DEPTH_INVALID','Kedalaman harus lebih dari nol.');
        if($previous&&$current['depth']<=$previous['depth'])$add('error','DEPTH_NOT_INCREASING','Kedalaman harus bertambah dan tidak boleh duplikat.');
        if($current['cw']<0||$current['tw']<0)$add('error','NEGATIVE_READING','Cw dan Tw tidak boleh negatif.');
        if($current['tw']<$current['cw'])$add('error','TW_LT_CW','Tw lebih kecil dari Cw sehingga fs negatif.');
        if($current['qc']<0||$current['fs']<0)$add('error','NEGATIVE_RESULT','qc atau fs bernilai negatif.');
        if($current['rf']>10)$add('warning','RF_HIGH','Rf di atas 10%; periksa pembacaan dan kondisi lapangan.');
        $capacity=(float)($tool['kapasitas_maksimum']??0);if($capacity>0&&$current['qc']>$capacity)$add('error','CAPACITY_EXCEEDED','qc melebihi kapasitas alat.');
        if($previous){$expected=(float)($current['interval']??.2);$actual=$current['depth']-$previous['depth'];if(abs($actual-$expected)>.001)$add('warning','INTERVAL_INCONSISTENT','Interval kedalaman tidak konsisten dengan konfigurasi.');if($previous['qc']>0&&$current['qc']/$previous['qc']>8)$add('warning','QC_SPIKE','Lonjakan qc lebih dari 8 kali pembacaan sebelumnya.');}
        return $issues;
    }

    public static function status(array $issues): string
    {
        if(array_filter($issues,fn($i)=>$i['level']==='error'))return 'tidak_valid';
        return $issues?'peringatan':'valid';
    }
}

