<?php
declare(strict_types=1);

require_once __DIR__.'/UnitConversionService.php';

final class MechanicalSondirCalculator
{
    public const METHOD='Mechanical sondir - configured geometry';
    public const VERSION='mechanical-v1.0';

    public function calculate(array $tool,float $depth,float $cw,float $tw,float $previousTf=0.0,?float $interval=null): array
    {
        $api=(float)($tool['luas_piston']??$tool['api']??0);$ac=(float)($tool['luas_konus']??$tool['ac']??0);$as=(float)($tool['luas_selimut']??$tool['ass']??0);
        if($api<=0||$ac<=0||$as<=0)throw new InvalidArgumentException('Luas piston, konus, dan friction sleeve harus lebih dari nol.');
        $interval=$interval??(float)($tool['interval_kedalaman']??$tool['interval_standar']??.2);
        if($interval<=0)throw new InvalidArgumentException('Interval pembacaan harus lebih dari nol.');
        $unit=(string)($tool['satuan_kapasitas']??'kg/cm2');$fk=(float)($tool['faktor_kalibrasi_konus']??$tool['fk']??1);$ft=(float)($tool['faktor_kalibrasi_total']??$tool['ft']??1);
        $kw=$tw-$cw;$qc=$cw*($api/$ac)*$fk;$fs=$kw*($api/$as)*$ft;$tf=$previousTf+$fs*($interval*100);$rf=$qc!=0?$fs/$qc*100:0.0;
        return [
            'depth'=>$depth,'cw'=>$cw,'tw'=>$tw,'kw'=>$kw,'qc'=>$qc,'fs'=>$fs,'tf'=>$tf,'rf'=>$rf,
            'qc_kpa'=>UnitConversionService::toKpa($qc,$unit),'qc_mpa'=>UnitConversionService::toMpa($qc,$unit),
            'fs_kpa'=>UnitConversionService::toKpa($fs,$unit),'fs_mpa'=>UnitConversionService::toMpa($fs,$unit),
            'native_unit'=>$unit,'interval'=>$interval,'method'=>self::METHOD,'version'=>self::VERSION,
            'formula'=>['qc'=>'Cw x Api/Ac x faktor kalibrasi konus','fs'=>'(Tw-Cw) x Api/As x faktor kalibrasi total','Rf'=>'fs/qc x 100%','Tf'=>'Tf sebelumnya + fs x interval(cm)'],
        ];
    }
}
