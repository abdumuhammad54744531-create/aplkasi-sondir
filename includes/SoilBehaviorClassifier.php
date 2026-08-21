<?php
declare(strict_types=1);

final class SoilClassifier
{
    private array $zones;
    private string $version;
    private float $maxFr;

    public function __construct(string $configPath)
    {
        $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        $this->zones = $config['zones'];
        $this->version = (string)($config['version'] ?? 'unknown');
        $this->maxFr = (float)($config['display_fr_max'] ?? 8);
    }

    public function version(): string { return $this->version; }

    public function classify(float $qc, float $fr): array
    {
        if ($qc <= 0 || $fr < 0) {
            throw new InvalidArgumentException('qc harus > 0 dan Fr harus >= 0.');
        }
        if ($qc < 0.1 || $qc > 100 || $fr > $this->maxFr) {
            return $this->outside();
        }

        foreach ($this->zones as $zone) {
            $boundary = $this->onBoundary($fr, $qc, $zone['polygon']);
            if ($boundary || $this->inside($fr, $qc, $zone['polygon'])) {
                return [
                    'zone_number' => $zone['zone'],
                    'soil_type_en' => $zone['name_en'],
                    'soil_type_id' => $zone['name_id'],
                    'color' => $zone['color'],
                    'boundary_flag' => $boundary,
                ];
            }
        }
        return $this->outside();
    }

    public function groupLayers(array $rows, bool $smooth = false): array
    {
        usort($rows, fn(array $a, array $b) => $a['depth_m'] <=> $b['depth_m']);
        if ($smooth && count($rows) >= 3) {
            for ($i = 1, $max = count($rows) - 1; $i < $max; $i++) {
                if ($rows[$i - 1]['zone_number'] === $rows[$i + 1]['zone_number']
                    && $rows[$i]['zone_number'] !== $rows[$i - 1]['zone_number']
                    && $this->sequential($rows[$i - 1]['depth_m'], $rows[$i]['depth_m'])
                    && $this->sequential($rows[$i]['depth_m'], $rows[$i + 1]['depth_m'])) {
                    foreach (['zone_number','soil_type_en','soil_type_id','color'] as $key) {
                        $rows[$i][$key] = $rows[$i - 1][$key];
                    }
                    $rows[$i]['smoothed'] = true;
                }
            }
        }

        $groups = [];
        foreach ($rows as $row) {
            $last = array_key_last($groups);
            if ($last === null || $groups[$last]['zone_number'] !== $row['zone_number']
                || !$this->sequential($groups[$last]['end_depth_m'], $row['depth_m'])) {
                $groups[] = [
                    'layer_number' => count($groups) + 1,
                    'start_depth_m' => $row['depth_m'], 'end_depth_m' => $row['depth_m'],
                    'zone_number' => $row['zone_number'], 'soil_type_id' => $row['soil_type_id'],
                    'soil_type_en' => $row['soil_type_en'], 'color' => $row['color'] ?? '#94a3b8',
                    'points' => [],
                ];
                $last = array_key_last($groups);
            }
            $groups[$last]['points'][] = $row;
            $groups[$last]['end_depth_m'] = $row['depth_m'];
        }

        foreach ($groups as &$group) {
            $qc = array_column($group['points'], 'qc_mpa');
            $fr = array_column($group['points'], 'friction_ratio_percent');
            $group['thickness_m'] = round($group['end_depth_m'] - $group['start_depth_m'] + 0.20, 2);
            $group['point_count'] = count($group['points']);
            $group['average_qc'] = round(array_sum($qc) / count($qc), 3);
            $group['average_fr'] = round(array_sum($fr) / count($fr), 3);
            $group['boundary_point_count'] = count(array_filter($group['points'], fn($p) => !empty($p['boundary_flag'])));
            $group['confidence_status'] = $group['boundary_point_count'] ? 'Periksa batas' : ($group['point_count'] === 1 ? 'Titik tunggal' : 'Baik');
            unset($group['points']);
        }
        return $groups;
    }

    private function sequential(float $a, float $b): bool { $d = $b - $a; return $d >= 0.19 && $d <= 0.21; }
    private function outside(): array { return ['zone_number'=>null,'soil_type_en'=>'Outside classification range','soil_type_id'=>'Di luar rentang klasifikasi','color'=>'#94a3b8','boundary_flag'=>false]; }

    private function inside(float $x, float $y, array $polygon): bool
    {
        $y = log10($y);
        $inside = false; $j = count($polygon) - 1;
        for ($i = 0, $n = count($polygon); $i < $n; $j = $i++) {
            [$xi,$yi] = $polygon[$i]; [$xj,$yj] = $polygon[$j];
            $yi = log10($yi); $yj = log10($yj);
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj-$xi)*($y-$yi)/(($yj-$yi) ?: 1e-12)+$xi)) $inside = !$inside;
        }
        return $inside;
    }

    private function onBoundary(float $x, float $y, array $polygon): bool
    {
        $ly = log10($y); $eps = 0.006;
        for ($i=0,$n=count($polygon); $i<$n; $i++) {
            [$x1,$y1] = $polygon[$i]; [$x2,$y2] = $polygon[($i+1)%$n];
            $y1=log10($y1); $y2=log10($y2);
            $dx=$x2-$x1; $dy=$y2-$y1; $len=$dx*$dx+$dy*$dy;
            $t=$len ? max(0,min(1,(($x-$x1)*$dx+($ly-$y1)*$dy)/$len)) : 0;
            $dist=hypot($x-($x1+$t*$dx),$ly-($y1+$t*$dy));
            if ($dist <= $eps) return true;
        }
        return false;
    }
}


