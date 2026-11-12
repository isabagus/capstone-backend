<?php

namespace App\Services;

class BomCalculatorService
{
    /**
     * Hitung kalkulasi lengkap BOM, Layout Potong 2D & Ukuran Sisa Bahan (Offcut)
     *
     * @param array $params [
     *   'plano_length'    => float (cm),
     *   'plano_width'     => float (cm),
     *   'cut_length'      => float (cm),
     *   'cut_width'       => float (cm),
     *   'target_quantity' => int (pcs),
     *   'gsm'             => float,
     *   'waste_percent'   => float (%),
     *   'insheet_qty'     => int (lembar),
     *   'mode'            => string ('best'|'tegak'|'miring')
     * ]
     * @return array
     */
    public function calculate(array $params): array
    {
        $rawPW = max(1.0, (float) ($params['plano_width'] ?? 79.0));
        $rawPL = max(1.0, (float) ($params['plano_length'] ?? 109.0));
        $sheetW = round(max($rawPL, $rawPW), 2);
        $sheetH = round(min($rawPL, $rawPW), 2);

        $rawCW = max(0.1, (float) ($params['cut_width'] ?? 33.0));
        $rawCL = max(0.1, (float) ($params['cut_length'] ?? 48.0));
        $cLong  = round(max($rawCL, $rawCW), 2);
        $cShort = round(min($rawCL, $rawCW), 2);

        $targetQty  = max(1, (int) ($params['target_quantity'] ?? 1000));
        $gsm        = max(1.0, (float) ($params['gsm'] ?? 300.0));
        $wastePct   = max(0.0, (float) ($params['waste_percent'] ?? 5.0));
        $insheetQty = max(0, (int) ($params['insheet_qty'] ?? 100));
        $mode       = $params['mode'] ?? 'best';

        $totalAreaCm2 = round($sheetW * $sheetH, 2);

        // 1. Solusi Murni Searah Plano (cLong x cShort)
        $solTegak = $this->solvePureGrid('Murni Searah Plano', $sheetW, $sheetH, $cLong, $cShort, 'tegak', $totalAreaCm2);

        // 2. Solusi Murni Putar 90° (cShort x cLong)
        $solMiring = $this->solvePureGrid('Murni Putar 90°', $sheetW, $sheetH, $cShort, $cLong, 'miring', $totalAreaCm2);

        // 3. Solusi Kombinasi Terbaik (Guillotine Split)
        $solBest = $solTegak['total'] >= $solMiring['total'] ? $solTegak : $solMiring;
        $solBest = $this->findBestCombination($sheetW, $sheetH, $cLong, $cShort, $totalAreaCm2, $solBest);

        // Pilih solusi aktif
        $active = match ($mode) {
            'tegak'  => $solTegak,
            'miring' => $solMiring,
            default  => $solBest,
        };

        $cutsPerPlano = max(1, $active['total']);

        // Akumulasi Lembar Plano & Konversi Industri Percetakan
        $netSheets   = (int) ceil($targetQty / $cutsPerPlano);
        $wasteSheets = (int) ceil($netSheets * ($wastePct / 100));
        $totalPlano  = $netSheets + $wasteSheets + $insheetQty;

        // 1 Rim = 500 Lembar Plano
        $totalRims = round($totalPlano / 500, 2);

        // Rumus Standar Berat Kertas Percetakan:
        // Berat (Kg) = (P_meter * L_meter * GSM * totalLembar) / 1000
        $pMeter = $sheetW / 100;
        $lMeter = $sheetH / 100;
        $totalWeightKg = round(($pMeter * $lMeter * $gsm * $totalPlano) / 1000, 2);

        // Estimasi Bahan Pendukung / Consumables
        $inkEstimateKg  = round(($totalPlano / 1000) * 0.5, 2);
        $glueEstimateKg = round(($targetQty / 1000) * 0.8, 2);
        $foilLengthM    = round(($targetQty * ($cLong / 100)) * 1.05, 1);

        return [
            'plano_dimension' => [
                'length_cm' => $sheetW,
                'width_cm'  => $sheetH,
                'area_cm2'  => $totalAreaCm2,
            ],
            'cut_dimension' => [
                'length_cm' => $cLong,
                'width_cm'  => $cShort,
                'area_cm2'  => round($cLong * $cShort, 2),
            ],
            'layout' => [
                'mode'                    => $mode,
                'strategy_name'           => $active['name'],
                'cuts_per_plano'          => $cutsPerPlano,
                'tegak_count'             => $active['tegak_count'],
                'miring_count'            => $active['miring_count'],
                'used_area_cm2'           => $active['used_area_cm2'],
                'waste_area_cm2'          => $active['waste_area_cm2'],
                'usable_remnant_area_cm2' => $active['usable_remnant_area_cm2'],
                'efficiency_rate'         => $active['efficiency_percent'],
                'pieces'                  => $active['pieces'],
                'remnants'                => $active['remnants'],
            ],
            'schemes' => [
                'best_total'   => $solBest['total'],
                'tegak_total'  => $solTegak['total'],
                'miring_total' => $solMiring['total'],
            ],
            'materials_summary' => [
                'target_quantity'    => $targetQty,
                'paper_gsm'          => $gsm,
                'waste_percent'      => $wastePct,
                'insheet_quantity'   => $insheetQty,
                'net_plano_sheets'   => $netSheets,
                'waste_plano_sheets' => $wasteSheets,
                'total_plano_sheets' => $totalPlano,
                'total_rims'         => $totalRims,
                'total_weight_kg'    => $totalWeightKg,
            ],
            'consumables_estimate' => [
                'ink_offset_kg' => $inkEstimateKg,
                'glue_kg'       => $glueEstimateKg,
                'foil_meter'    => $foilLengthM,
            ],
        ];
    }

    private function solvePureGrid(
        string $label,
        float $sheetW,
        float $sheetH,
        float $pw,
        float $ph,
        string $orientation,
        float $totalArea
    ): array {
        $cols = (int) floor($sheetW / $pw);
        $rows = (int) floor($sheetH / $ph);

        if ($cols <= 0 || $rows <= 0) {
            return $this->buildSolution(
                $label,
                [],
                [['id' => 1, 'x' => 0, 'y' => 0, 'w' => $sheetW, 'h' => $sheetH, 'position' => 'Plano Utuh']],
                $totalArea
            );
        }

        $usedW = round($cols * $pw, 2);
        $usedH = round($rows * $ph, 2);
        $remTopH = round($sheetH - $usedH, 2);
        $remRightW = round($sheetW - $usedW, 2);
        $yStart = $remTopH;

        $pieces = [];
        $remnants = [];
        $pId = 1;
        $rId = 1;

        if ($remTopH > 0.01) {
            $remnants[] = [
                'id'       => $rId++,
                'x'        => 0.0,
                'y'        => 0.0,
                'w'        => $sheetW,
                'h'        => $remTopH,
                'position' => 'Sisa Atas',
            ];
        }

        for ($r = 0; $r < $rows; $r++) {
            $rowY = round($yStart + $r * $ph, 2);
            for ($c = 0; $c < $cols; $c++) {
                $pieces[] = [
                    'id'          => $pId++,
                    'x'           => round($c * $pw, 2),
                    'y'           => $rowY,
                    'w'           => $pw,
                    'h'           => $ph,
                    'orientation' => $orientation,
                ];
            }
            if ($remRightW > 0.01) {
                $remnants[] = [
                    'id'       => $rId++,
                    'x'        => $usedW,
                    'y'        => $rowY,
                    'w'        => $remRightW,
                    'h'        => $ph,
                    'position' => 'Sisa Kanan',
                ];
            }
        }

        return $this->buildSolution($label, $pieces, $remnants, $totalArea);
    }

    private function findBestCombination(
        float $sheetW,
        float $sheetH,
        float $cLong,
        float $cShort,
        float $totalArea,
        array $currentBest
    ): array {
        $best = $currentBest;

        $isBetter = function (array $cand, array $curr): bool {
            if ($cand['total'] !== $curr['total']) {
                return $cand['total'] > $curr['total'];
            }
            return $cand['usable_remnant_area_cm2'] > $curr['usable_remnant_area_cm2'];
        };

        $tryHorizontal = function (
            string $label,
            float $w1,
            float $h1,
            string $o1,
            float $w2,
            float $h2,
            string $o2
        ) use ($sheetW, $sheetH, $totalArea, &$best, $isBetter) {
            $cols1 = (int) floor($sheetW / $w1);
            $maxRows1 = (int) floor($sheetH / $h1);
            $cols2 = (int) floor($sheetW / $w2);
            if ($cols1 <= 0 || $cols2 <= 0) {
                return;
            }

            for ($k = 1; $k <= $maxRows1; $k++) {
                $remH = round($sheetH - ($k * $h1), 2);
                $r2 = (int) floor($remH / $h2);
                if ($r2 <= 0) {
                    continue;
                }

                $usedH = round(($k * $h1) + ($r2 * $h2), 2);
                $remTopH = round($sheetH - $usedH, 2);
                $yStart1 = $remTopH;
                $yStart2 = round($yStart1 + ($k * $h1), 2);

                $pieces = [];
                $remnants = [];
                $pId = 1;
                $rId = 1;

                if ($remTopH > 0.01) {
                    $remnants[] = [
                        'id'       => $rId++,
                        'x'        => 0.0,
                        'y'        => 0.0,
                        'w'        => $sheetW,
                        'h'        => $remTopH,
                        'position' => 'Sisa Atas',
                    ];
                }

                $remRight1 = round($sheetW - ($cols1 * $w1), 2);
                for ($r = 0; $r < $k; $r++) {
                    $rowY = round($yStart1 + ($r * $h1), 2);
                    for ($c = 0; $c < $cols1; $c++) {
                        $pieces[] = [
                            'id'          => $pId++,
                            'x'           => round($c * $w1, 2),
                            'y'           => $rowY,
                            'w'           => $w1,
                            'h'           => $h1,
                            'orientation' => $o1,
                        ];
                    }
                    if ($remRight1 > 0.01) {
                        $remnants[] = [
                            'id'       => $rId++,
                            'x'        => round($cols1 * $w1, 2),
                            'y'        => $rowY,
                            'w'        => $remRight1,
                            'h'        => $h1,
                            'position' => 'Sisa Kanan Atas',
                        ];
                    }
                }

                $remRight2 = round($sheetW - ($cols2 * $w2), 2);
                for ($r = 0; $r < $r2; $r++) {
                    $rowY = round($yStart2 + ($r * $h2), 2);
                    for ($c = 0; $c < $cols2; $c++) {
                        $pieces[] = [
                            'id'          => $pId++,
                            'x'           => round($c * $w2, 2),
                            'y'           => $rowY,
                            'w'           => $w2,
                            'h'           => $h2,
                            'orientation' => $o2,
                        ];
                    }
                    if ($remRight2 > 0.01) {
                        $remnants[] = [
                            'id'       => $rId++,
                            'x'        => round($cols2 * $w2, 2),
                            'y'        => $rowY,
                            'w'        => $remRight2,
                            'h'        => $h2,
                            'position' => 'Sisa Kanan Bawah',
                        ];
                    }
                }

                $cand = $this->buildSolution($label, $pieces, $remnants, $totalArea);
                if ($isBetter($cand, $best)) {
                    $best = $cand;
                }
            }
        };

        $tryVertical = function (
            string $label,
            float $w1,
            float $h1,
            string $o1,
            float $w2,
            float $h2,
            string $o2
        ) use ($sheetW, $sheetH, $totalArea, &$best, $isBetter) {
            $maxCols1 = (int) floor($sheetW / $w1);
            $r1 = (int) floor($sheetH / $h1);
            $r2 = (int) floor($sheetH / $h2);
            if ($r1 <= 0 || $r2 <= 0) {
                return;
            }

            for ($m = 1; $m <= $maxCols1; $m++) {
                $leftW = round($m * $w1, 2);
                $remW = round($sheetW - $leftW, 2);
                $c2 = (int) floor($remW / $w2);
                if ($c2 <= 0) {
                    continue;
                }

                $pieces = [];
                $remnants = [];
                $pId = 1;
                $rId = 1;

                $usedH1 = round($r1 * $h1, 2);
                $remTop1 = round($sheetH - $usedH1, 2);
                if ($remTop1 > 0.01) {
                    $remnants[] = [
                        'id'       => $rId++,
                        'x'        => 0.0,
                        'y'        => 0.0,
                        'w'        => $leftW,
                        'h'        => $remTop1,
                        'position' => 'Sisa Atas Kiri',
                    ];
                }
                for ($r = 0; $r < $r1; $r++) {
                    $rowY = round($remTop1 + ($r * $h1), 2);
                    for ($c = 0; $c < $m; $c++) {
                        $pieces[] = [
                            'id'          => $pId++,
                            'x'           => round($c * $w1, 2),
                            'y'           => $rowY,
                            'w'           => $w1,
                            'h'           => $h1,
                            'orientation' => $o1,
                        ];
                    }
                }

                $usedH2 = round($r2 * $h2, 2);
                $remTop2 = round($sheetH - $usedH2, 2);
                if ($remTop2 > 0.01) {
                    $remnants[] = [
                        'id'       => $rId++,
                        'x'        => $leftW,
                        'y'        => 0.0,
                        'w'        => $remW,
                        'h'        => $remTop2,
                        'position' => 'Sisa Atas Kanan',
                    ];
                }

                $remRight2 = round($remW - ($c2 * $w2), 2);
                for ($r = 0; $r < $r2; $r++) {
                    $rowY = round($remTop2 + ($r * $h2), 2);
                    for ($c = 0; $c < $c2; $c++) {
                        $pieces[] = [
                            'id'          => $pId++,
                            'x'           => round($leftW + ($c * $w2), 2),
                            'y'           => $rowY,
                            'w'           => $w2,
                            'h'           => $h2,
                            'orientation' => $o2,
                        ];
                    }
                    if ($remRight2 > 0.01) {
                        $remnants[] = [
                            'id'       => $rId++,
                            'x'        => round($leftW + ($c2 * $w2), 2),
                            'y'        => $rowY,
                            'w'        => $remRight2,
                            'h'        => $h2,
                            'position' => 'Sisa Kanan',
                        ];
                    }
                }

                $cand = $this->buildSolution($label, $pieces, $remnants, $totalArea);
                if ($isBetter($cand, $best)) {
                    $best = $cand;
                }
            }
        };

        $tryHorizontal('Kombinasi Horizontal (Searah + Putar 90°)', $cLong, $cShort, 'tegak', $cShort, $cLong, 'miring');
        $tryHorizontal('Kombinasi Horizontal (Putar 90° + Searah)', $cShort, $cLong, 'miring', $cLong, $cShort, 'tegak');
        $tryVertical('Kombinasi Vertikal (Kiri Searah, Kanan Putar 90°)', $cLong, $cShort, 'tegak', $cShort, $cLong, 'miring');
        $tryVertical('Kombinasi Vertikal (Kiri Putar 90°, Kanan Searah)', $cShort, $cLong, 'miring', $cLong, $cShort, 'tegak');

        return $best;
    }

    private function buildSolution(string $name, array $pieces, array $remnants, float $totalArea): array
    {
        $usedArea = 0.0;
        $tegak = 0;
        $miring = 0;

        foreach ($pieces as $p) {
            $usedArea += $p['w'] * $p['h'];
            if ($p['orientation'] === 'tegak') {
                $tegak++;
            } else {
                $miring++;
            }
        }

        $usableRemnantArea = 0.0;
        foreach ($remnants as $rem) {
            if (min($rem['w'], $rem['h']) >= 5.0) {
                $usableRemnantArea += $rem['w'] * $rem['h'];
            }
        }

        $wasteArea = max(0.0, $totalArea - $usedArea);
        $efficiency = $totalArea > 0 ? round(($usedArea / $totalArea) * 100, 1) : 0.0;

        return [
            'name'                    => $name,
            'total'                   => count($pieces),
            'pieces'                  => $pieces,
            'remnants'                => $remnants,
            'used_area_cm2'           => round($usedArea, 2),
            'waste_area_cm2'          => round($wasteArea, 2),
            'usable_remnant_area_cm2' => round($usableRemnantArea, 2),
            'efficiency_percent'      => $efficiency,
            'tegak_count'             => $tegak,
            'miring_count'            => $miring,
        ];
    }
}
