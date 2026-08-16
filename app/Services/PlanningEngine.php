<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\Marker;
use App\Models\WorkOrder;

/**
 * ═══════════════════════════════════════════════════════════════════
 *  محرك التخطيط — قلب السيستم
 * ═══════════════════════════════════════════════════════════════════
 *
 * كل شاشة في السيستم مجرد واجهة حوالين المعادلات دي.
 *
 * المدخلات:
 *   • min_width_cm      أقل عرض من الفحص  ← مش المتوسط! الماركر ما ينفعش
 *                        يطلع أكبر من عرض القماش، وإلا هنحرق الجنب ونرميه.
 *   • avg_gsm           متوسط البنشر من تقرير المعمل.
 *   • spread_length_m   طول الفرشة من الماركر.
 *   • pieces_per_spread عدد القطع في الفرشة من الماركر.
 *
 * المعادلات:
 *   عدد الرِقّات   = FLOOR(طول التوب ÷ طول الفرشة)
 *   وزن الرِقّة    = طول الفرشة × (العرض ÷ 100) × البنشر ÷ 1000
 *   استهلاك القطعة = وزن الرِقّة ÷ عدد القطع في الفرشة
 *   القطع المتوقعة = الكيلوهات المخصصة ÷ استهلاك القطعة
 *
 * تحذير دائم: كل رقم طالع من هنا "متوقّع" لأنه مبني على متوسطات عيّنة
 * فحص (6-7 أتواب من 40). الفعلي بيظهر في المصنع. الفرق الطبيعي 2-4%.
 */
class PlanningEngine
{
    /**
     * حسبة كاملة من الأرقام الخام.
     *
     * @param  float $widthCm        عرض القماش المستخدم (أقل عرض)
     * @param  float $gsm            متوسط البنشر (جرام/م²)
     * @param  float $spreadLengthM  طول الفرشة بالمتر
     * @param  int   $piecesPerSpread عدد القطع في الفرشة
     * @param  float $availableKg    الكيلوهات المتاحة/المخصصة
     * @param  float|null $rollLengthM طول التوب (لحساب الرِقّات)
     */
    public static function compute(
        float $widthCm,
        float $gsm,
        float $spreadLengthM,
        int $piecesPerSpread,
        float $availableKg = 0,
        ?float $rollLengthM = null
    ): array {
        $errors = [];

        if ($widthCm <= 0)        $errors[] = 'عرض القماش لازم يكون أكبر من صفر.';
        if ($gsm <= 0)            $errors[] = 'وزن البنشر لازم يكون أكبر من صفر.';
        if ($spreadLengthM <= 0)  $errors[] = 'طول الفرشة لازم يكون أكبر من صفر.';
        if ($piecesPerSpread <= 0)$errors[] = 'عدد القطع في الفرشة لازم يكون أكبر من صفر.';

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        // وزن الرِقّة الواحدة (كجم)
        //   المساحة = الطول (م) × العرض (م)   →   الوزن = المساحة × البنشر (جم/م²) ÷ 1000
        $plyWeightKg = $spreadLengthM * ($widthCm / 100) * $gsm / 1000;

        // استهلاك القطعة الواحدة (كجم)
        $kgPerPiece = $plyWeightKg / $piecesPerSpread;

        // عدد الرِقّات من التوب الواحد — الكسر بيتقطع، مش بيتقرّب
        $pliesPerRoll = $rollLengthM && $rollLengthM > 0
            ? (int) floor($rollLengthM / $spreadLengthM)
            : null;

        // الفاقد في التوب بعد آخر رِقّة كاملة
        $wasteM = $pliesPerRoll !== null
            ? round($rollLengthM - ($pliesPerRoll * $spreadLengthM), 2)
            : null;

        // القطع المتوقعة من الكيلوهات المتاحة
        $expectedPieces = $kgPerPiece > 0 ? (int) floor($availableKg / $kgPerPiece) : 0;

        // إجمالي الرِقّات المتوقعة من الكمية كلها
        $expectedPlies = $piecesPerSpread > 0 ? (int) floor($expectedPieces / $piecesPerSpread) : 0;

        return [
            'ok'                => true,
            'errors'            => [],
            'ply_weight_kg'     => round($plyWeightKg, 4),
            'kg_per_piece'      => round($kgPerPiece, 5),
            'g_per_piece'       => round($kgPerPiece * 1000, 1),
            'plies_per_roll'    => $pliesPerRoll,
            'waste_per_roll_m'  => $wasteM,
            'expected_pieces'   => $expectedPieces,
            'expected_plies'    => $expectedPlies,
            'inputs'            => [
                'width_cm'          => $widthCm,
                'gsm'               => $gsm,
                'spread_length_m'   => $spreadLengthM,
                'pieces_per_spread' => $piecesPerSpread,
                'available_kg'      => $availableKg,
                'roll_length_m'     => $rollLengthM,
            ],
        ];
    }

    /**
     * حسبة أمر شغل من الحوض + الماركر مباشرة.
     */
    public static function forWorkOrder(Consignment $consignment, Marker $marker, float $allocatedKg): array
    {
        $result = self::compute(
            widthCm:         (float) ($consignment->min_width_cm ?? 0),
            gsm:             (float) ($consignment->avg_gsm ?? 0),
            spreadLengthM:   (float) $marker->spread_length_m,
            piecesPerSpread: (int) $marker->pieces_per_spread,
            availableKg:     $allocatedKg,
            rollLengthM:     $consignment->rolls_count > 0
                                ? (float) $consignment->total_length_m / $consignment->rolls_count
                                : null,
        );

        $result['warnings'] = self::warnings($consignment, $marker, $allocatedKg);

        return $result;
    }

    /**
     * التحذيرات اللي بتمنع أخطاء حقيقية كلّفت فلوس قبل كده.
     */
    public static function warnings(Consignment $consignment, ?Marker $marker, float $allocatedKg = 0): array
    {
        $w = [];

        // 1) الماركر أعرض من القماش = حرق الجنب ورميه
        if ($marker && $consignment->min_width_cm) {
            $need = (float) ($marker->marker_width_cm ?: $marker->fabric_width_cm);
            if ($need > (float) $consignment->min_width_cm) {
                $w[] = [
                    'level' => 'danger',
                    'text'  => 'عرض الماركر (' . $need . ' سم) أكبر من أقل عرض في الحوض ('
                             . $consignment->min_width_cm . ' سم). ده معناه حرق الجنب ورميه — غيّر الماركر أو استبعد الأتواب الضيقة.',
                ];
            }
        }

        // 2) الفحص عيّنة صغيرة — كل الأرقام تقديرية
        $insp = $consignment->inspections()->latest('id')->first();
        if ($insp) {
            if ($insp->sample_pct < (float) config('lvplanning.min_inspection_sample_pct', 15)) {
                $w[] = [
                    'level' => 'warning',
                    'text'  => 'العينة المفحوصة ' . rtrim(rtrim(number_format((float) $insp->sample_pct, 1), '0'), '.')
                             . '% فقط من الأتواب (' . $insp->sampled_rolls . ' من ' . $insp->total_rolls
                             . '). كل الأرقام اللي بعد كده تقديرية.',
                ];
            }
            // 3) فرق العرض الكبير بين أتواب نفس الحوض = القماش نفسه فيه مشكلة
            if ($insp->width_alert) {
                $w[] = [
                    'level' => 'danger',
                    'text'  => 'فرق العرض بين أتواب الحوض ' . $insp->width_spread_cm
                             . ' سم — مش طبيعي لقماش حوض واحد. راجع القماش قبل التشغيل.',
                ];
            }
        } else {
            $w[] = ['level' => 'danger', 'text' => 'الحوض ده لسه ما اتفحصش — مفيش عرض ولا بنشر نبني عليهم.'];
        }

        // 4) مفيش تقرير معمل
        if (!$consignment->avg_gsm) {
            $w[] = ['level' => 'danger', 'text' => 'مفيش متوسط بنشر من المعمل — الحسبة مش هتطلع صح.'];
        }

        // 5) الكمية المطلوبة أكبر من المتاح في الحوض
        if ($allocatedKg > 0 && $allocatedKg > (float) $consignment->remaining_kg + 0.001) {
            $w[] = [
                'level' => 'danger',
                'text'  => 'الكمية المطلوبة (' . number_format($allocatedKg, 2) . ' كجم) أكبر من المتبقي في الحوض ('
                         . number_format((float) $consignment->remaining_kg, 2) . ' كجم).',
            ];
        }

        // 6) البنشر خارج مواصفة الخامة
        $ft = $consignment->fabricType;
        if ($ft && $consignment->avg_gsm && $ft->gsmInSpec((float) $consignment->avg_gsm) === false) {
            $w[] = [
                'level' => 'warning',
                'text'  => 'متوسط البنشر (' . $consignment->avg_gsm . ') خارج مواصفة الخامة ('
                         . $ft->spec_gsm_min . ' - ' . $ft->spec_gsm_max . ').',
            ];
        }

        return $w;
    }


    /* ═══════════════════════════════════════════════════════════════
       حسبة الخامة الواحدة داخل أمر شغل متعدد الخامات
       ═══════════════════════════════════════════════════════════════

       الواقع من ورق المصنع: المنتج بيتعمل من أكتر من خامة، وكل خامة
       بتتحسب بطريقة مختلفة:

       • بالطول (تل مستورد — بالمتر)
             استهلاك القطعة = طول الفرشة ÷ عدد القطع في الفرشة
             عدد الرقات     = FLOOR(المتاح بالمتر ÷ طول الفرشة)

       • بالوزن (مياي/سنجل — بالكيلو)
             وزن الراق      = طول الفرشة × عرض القماش (م) × البنشر (كجم/م²)
             استهلاك القطعة = وزن الراق ÷ عدد القطع في الفرشة
             عدد الرقات     = FLOOR(المتاح بالكيلو ÷ وزن الراق)

       في الحالتين: القص المتوقع = عدد الرقات × عدد القطع في الفرشة

       ★ طول الفرشة المستخدم هو **طول الأمان** لو متحدد، مش الطول الأساسي.
         (متحقق من ورقة KB106: 3.75 بالأمان مش 3.7 هي اللي طلّعت وزن راق 1.433)
    */
    public static function computeFabric(array $in): array
    {
        $mode    = $in['calc_mode'] ?? 'weight';
        $spread  = (float) ($in['spread_length_safe_m'] ?? 0) ?: (float) ($in['spread_length_m'] ?? 0);
        $pps     = (int) ($in['pieces_per_spread'] ?? 0);
        $avail   = (float) ($in['available'] ?? 0);
        $widthM  = (float) ($in['fabric_width_m'] ?? 0);
        $gsm     = (float) ($in['gsm_kg_m2'] ?? 0);

        $errors = [];
        if ($spread <= 0) $errors[] = 'طول الفرشة لازم يكون أكبر من صفر.';
        if ($pps <= 0)    $errors[] = 'عدد القطع في الفرشة لازم يكون أكبر من صفر.';

        if ($mode === 'weight') {
            if ($widthM <= 0) $errors[] = 'عرض القماش لازم يكون أكبر من صفر للخامة اللي بتتحسب بالوزن.';
            if ($gsm <= 0)    $errors[] = 'وزن البنشر لازم يكون أكبر من صفر للخامة اللي بتتحسب بالوزن.';
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        if ($mode === 'weight') {
            $plyWeight   = $spread * $widthM * $gsm;          // كجم للراق الواحد
            $consumption = $plyWeight / $pps;                  // كجم للقطعة
            $perPly      = $plyWeight;                         // الوحدة المستهلكة لكل رقة
        } else {
            $plyWeight   = null;
            $consumption = $spread / $pps;                     // متر للقطعة
            $perPly      = $spread;                            // متر لكل رقة
        }

        $plies    = $perPly > 0 ? (int) floor($avail / $perPly) : 0;
        $expected = $plies * $pps;
        $leftover = $perPly > 0 ? round($avail - ($plies * $perPly), 3) : null;

        return [
            'ok'                    => true,
            'errors'                => [],
            'calc_mode'             => $mode,
            'unit'                  => $mode === 'weight' ? 'كجم' : 'متر',
            'spread_used_m'         => round($spread, 3),
            'ply_weight_kg'         => $plyWeight !== null ? round($plyWeight, 4) : null,
            'consumption_per_piece' => round($consumption, 5),
            'plies'                 => $plies,
            'expected_pieces'       => $expected,
            'leftover'              => $leftover,
            'needed_for'            => fn (int $pieces) => round($consumption * $pieces, 3),
        ];
    }

    /**
     * أمر شغل بأكتر من خامة.
     *
     * بيحسب كل خامة لوحدها، وبيحدد **الخامة الحاكمة** = اللي بتدي أقل قطع.
     * دي اللي بتوقف الإنتاج فعليًا، وعلى الورق الفرق ده بيبقى مخفي تمامًا.
     *
     * @param  array $fabrics  كل عنصر = مدخلات computeFabric + 'label'
     */
    public static function computeWorkOrder(array $fabrics): array
    {
        $rows = [];
        $min  = null;

        foreach ($fabrics as $i => $f) {
            $r = self::computeFabric($f);
            $r['label'] = $f['label'] ?? ('خامة ' . ($i + 1));
            $r['index'] = $i;
            $rows[] = $r;

            if (($r['ok'] ?? false) && ($min === null || $r['expected_pieces'] < $min)) {
                $min = $r['expected_pieces'];
            }
        }

        $governing = null;
        foreach ($rows as $k => $r) {
            $is = ($r['ok'] ?? false) && $r['expected_pieces'] === $min;
            $rows[$k]['is_governing'] = $is;
            if ($is && $governing === null) $governing = $k;
        }

        // الفجوة بين الخامة الحاكمة وأعلى خامة — ده الرقم اللي بيوضّح النقص
        $max  = collect($rows)->where('ok', true)->max('expected_pieces');
        $gaps = [];
        foreach ($rows as $r) {
            if (!($r['ok'] ?? false) || $r['expected_pieces'] <= (int) $min) continue;
            $gaps[] = [
                'label'   => $r['label'],
                'surplus' => $r['expected_pieces'] - (int) $min,
            ];
        }

        return [
            'ok'             => collect($rows)->every(fn ($r) => $r['ok'] ?? false),
            'fabrics'        => $rows,
            'governing_qty'  => (int) ($min ?? 0),
            'governing_index'=> $governing,
            'max_qty'        => (int) ($max ?? 0),
            'gaps'           => $gaps,
            'warnings'       => self::fabricWarnings($rows, (int) ($min ?? 0)),
        ];
    }

    /** تحذيرات الخامات المتعددة */
    private static function fabricWarnings(array $rows, int $min): array
    {
        $w = [];

        foreach ($rows as $r) {
            if (!($r['ok'] ?? false)) {
                $w[] = ['level' => 'danger', 'text' => $r['label'] . ': ' . implode(' ', $r['errors'])];
                continue;
            }
            if ($r['expected_pieces'] === 0) {
                $w[] = ['level' => 'danger',
                        'text'  => $r['label'] . ': الكمية المتاحة مش كفاية ولا رِقّة واحدة.'];
            }
        }

        $okRows = array_values(array_filter($rows, fn ($r) => ($r['ok'] ?? false) && $r['expected_pieces'] > 0));

        if (count($okRows) > 1 && $min > 0) {
            $max = max(array_column($okRows, 'expected_pieces'));
            if ($max > $min) {
                $pct = round((($max - $min) / $max) * 100, 1);
                $gov = collect($okRows)->firstWhere('expected_pieces', $min)['label'] ?? '';
                $w[] = [
                    'level' => $pct > 10 ? 'danger' : 'warning',
                    'text'  => "الخامات مش متوازنة: «{$gov}» هتدي {$min} قطعة بس، وأعلى خامة هتدي {$max} "
                             . "— فرق {$pct}%. يا تزوّد الخامة الحاكمة يا هتسيب باقي الخامات راكدة.",
                ];
            }
        }

        return $w;
    }

    /**
     * تأثير الفرق في طول الفرشة.
     *
     * "لو زوّدت في الطول 5 سنتيمتر، هتاكل من كل رِقّة وهيقل عدد الرِقّات."
     * ده أكبر مصدر فاقد صامت في الدورة كلها.
     */
    public static function spreadImpact(float $rollLengthM, float $plannedSpreadM, float $actualSpreadM, int $piecesPerSpread): array
    {
        if ($rollLengthM <= 0 || $plannedSpreadM <= 0 || $actualSpreadM <= 0) {
            return ['ok' => false];
        }

        $plannedPlies = (int) floor($rollLengthM / $plannedSpreadM);
        $actualPlies  = (int) floor($rollLengthM / $actualSpreadM);
        $lostPlies    = $plannedPlies - $actualPlies;

        return [
            'ok'             => true,
            'planned_plies'  => $plannedPlies,
            'actual_plies'   => $actualPlies,
            'lost_plies'     => $lostPlies,
            'lost_pieces'    => $lostPlies * $piecesPerSpread,
            'deviation_cm'   => round(($actualSpreadM - $plannedSpreadM) * 100, 2),
            'loss_pct'       => $plannedPlies > 0 ? round(($lostPlies / $plannedPlies) * 100, 2) : 0,
        ];
    }

    /**
     * حساب الانحراف وتصنيفه.
     * الحدود من config: 2% أخضر، لحد 4% أصفر، فوقها أحمر ولازم سبب.
     */
    public static function variance(float $planned, float $actual): array
    {
        if ($planned <= 0) {
            return ['pct' => null, 'flag' => null, 'label' => '—'];
        }

        $pct = round((($actual - $planned) / $planned) * 100, 3);
        $abs = abs($pct);

        $ok   = (float) config('lvplanning.variance.ok_pct', 2);
        $warn = (float) config('lvplanning.variance.warn_pct', 4);

        $flag = $abs <= $ok ? 'ok' : ($abs <= $warn ? 'warn' : 'danger');

        return [
            'pct'   => $pct,
            'flag'  => $flag,
            'label' => WorkOrder::VARIANCE_FLAGS[$flag] ?? $flag,
            'needs_reason' => $flag === 'danger',
        ];
    }

    /**
     * انفجار الإكسسوارات (BOM) لأمر شغل.
     * بيرجع: [accessory_id => ['required' => x, 'available' => y, 'shortage' => z]]
     */
    public static function explodeAccessories(WorkOrder $workOrder): array
    {
        $workOrder->loadMissing('lines.productModel');
        $out = [];

        foreach ($workOrder->lines as $line) {
            $model = $line->productModel;
            if (!$model) continue;

            $boms = \App\Models\ModelBom::with('accessory')
                ->where('product_model_id', $model->id)
                ->where(function ($q) use ($line) {
                    $q->whereNull('size_id')->orWhere('size_id', $line->size_id);
                })->get();

            foreach ($boms as $bom) {
                if (!$bom->accessory) continue;
                $id = $bom->accessory_id;
                $need = (float) $bom->qty_per_piece * (int) $line->planned_qty;

                if (!isset($out[$id])) {
                    $out[$id] = [
                        'accessory' => $bom->accessory,
                        'required'  => 0.0,
                        'available' => (float) $bom->accessory->stock_qty,
                        'shortage'  => 0.0,
                    ];
                }
                $out[$id]['required'] += $need;
            }
        }

        foreach ($out as $id => $row) {
            $out[$id]['required'] = round($row['required'], 3);
            $out[$id]['shortage'] = round(max(0, $row['required'] - $row['available']), 3);
        }

        return $out;
    }
}
