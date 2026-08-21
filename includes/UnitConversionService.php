<?php
declare(strict_types=1);

final class UnitConversionService
{
    private const TO_KPA=[
        'kpa'=>1.0,
        'kn/m2'=>1.0,
        'mpa'=>1000.0,
        'kg/cm2'=>98.0665,
        'ton/m2'=>9.80665,
        'kpa/100'=>100.0,
    ];

    public static function normalize(string $unit): string
    {
        $unit=mb_strtolower(trim(str_replace(['²','^2',' '],['2','2',''],$unit)));
        return match($unit){
            'kgf/cm2','kg/cm2'=>'kg/cm2',
            'kn/m²','kn/m2'=>'kn/m2',
            'tonf/m2','t/m2','ton/m2'=>'ton/m2',
            'kpa/100'=>'kpa/100',
            'mpa'=>'mpa',
            default=>'kpa',
        };
    }

    public static function convert(float $value,string $from,string $to): float
    {
        $from=self::normalize($from);$to=self::normalize($to);
        return $value*self::TO_KPA[$from]/self::TO_KPA[$to];
    }

    public static function toKpa(float $value,string $from): float{return self::convert($value,$from,'kpa');}
    public static function toMpa(float $value,string $from): float{return self::convert($value,$from,'mpa');}
}

