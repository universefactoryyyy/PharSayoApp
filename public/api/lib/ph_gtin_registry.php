<?php
/**
 * Philippines-first GTIN helpers: local JSON registry + shared GTIN variants for lookups.
 */

function pharsayo_ph_registry_trunc($text, $max) {
    $text = preg_replace('/\s+/', ' ', trim((string)$text));
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, $max) . '...';
}

/**
 * @return list<string>
 */
function pharsayo_barcode_gtin_variants($digits) {
    $digits = preg_replace('/\D+/', '', (string)$digits);
    if ($digits === '') {
        return [];
    }
    $len = strlen($digits);
    $codes = [$digits];

    // Common variations for GTIN/EAN/UPC
    if ($len === 8) {
        // EAN-8 to EAN-13/GTIN-14
        $codes[] = str_pad($digits, 13, '0', STR_PAD_LEFT);
        $codes[] = str_pad($digits, 14, '0', STR_PAD_LEFT);
    } elseif ($len === 12) {
        // UPC-A to EAN-13/GTIN-14
        $codes[] = '0' . $digits;
        $codes[] = '00' . $digits;
    } elseif ($len === 13) {
        // EAN-13 to UPC-A (if leading zero) or GTIN-14
        if ($digits[0] === '0') {
            $codes[] = substr($digits, 1);
        }
        $codes[] = '0' . $digits;
    } elseif ($len === 14) {
        // GTIN-14 to EAN-13 or UPC-A
        $stripped = ltrim($digits, '0');
        if (strlen($stripped) === 13) {
            $codes[] = $stripped;
        } elseif (strlen($stripped) === 12) {
            $codes[] = $stripped;
            $codes[] = '0' . $stripped; // Also try as EAN-13
        }
    }

    // Philippines specific: some local barcodes might be padded differently or missing checksums in some DBs
    // but for GTIN matching we usually want the standard lengths.
    
    return array_values(array_unique(array_filter($codes)));
}

function pharsayo_ph_gtin_registry_path() {
    return __DIR__ . '/../data/ph_gtin_registry.json';
}

/**
 * Curated Philippine / app-local GTIN rows (expandable; not a government register).
 *
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string}|null
 */
function pharsayo_lookup_ph_gtin_registry($digits) {
    static $map = null;
    if ($map === null) {
        $map = [];
        // Hardcoded critical fallbacks
        $map['5290665007802'] = [
            'display_name' => 'Ibuprofen 200mg Tablets',
            'dosage_hint' => '200mg Ibuprofen per tablet',
            'purpose_en' => 'Pain reliever and fever reducer (NSAID). Used for headache, muscle pain, and minor aches.',
            'purpose_fil' => 'Gamot sa kirot at lagnat (NSAID). Ginagamit sa sakit ng ulo, kalamnan, at pananakit ng katawan.',
            'precautions_en' => 'Take after meals. Avoid if you have stomach ulcers. Consult a doctor if you have heart or kidney issues.',
            'precautions_fil' => 'Inumin pagkatapos kumain. Huwag gamitin kung may ulcer. Magtanong sa doktor kung may sakit sa puso o bato.',
            'frequency_hint' => '1 to 2 tablets every 4 to 6 hours as needed. Do not exceed 6 tablets in 24 hours.',
            'rxcui' => '213233'
        ];

        $path = pharsayo_ph_gtin_registry_path();
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                foreach ($j as $k => $v) {
                    $sk = (string)$k;
                    if ($sk === '' || $sk[0] === '_') continue;
                    if (!is_array($v)) continue;
                    $kk = preg_replace('/\D+/', '', $sk);
                    if ($kk !== '') $map[$kk] = $v;
                }
            }
        }
    }

    $cleanDigits = preg_replace('/\D+/', '', (string)$digits);
    $variants = pharsayo_barcode_gtin_variants($cleanDigits);
    
    foreach ($variants as $code) {
        if (isset($map[$code])) {
            $v = $map[$code];
            $name = isset($v['display_name']) ? trim((string)$v['display_name']) : '';
            if ($name === '') continue;

            $dosage = isset($v['dosage_hint']) ? trim((string)$v['dosage_hint']) : 'See package label.';
            $purpose_en = isset($v['purpose_en']) ? trim((string)$v['purpose_en']) : 'Verified medicine information.';
            $purpose_fil = isset($v['purpose_fil']) ? trim((string)$v['purpose_fil']) : 'Impormasyon ng gamot.';
            $prec_en = isset($v['precautions_en']) ? trim((string)$v['precautions_en']) : 'Read package insert.';
            $prec_fil = isset($v['precautions_fil']) ? trim((string)$v['precautions_fil']) : 'Basahin ang impormasyon.';
            $freq = isset($v['frequency_hint']) ? trim((string)$v['frequency_hint']) : 'Follow package instructions.';
            $rxcui = isset($v['rxcui']) ? trim((string)$v['rxcui']) : '';

            return [
                'rxcui' => $rxcui,
                'display_name' => $name,
                'dosage_hint' => $dosage,
                'purpose_en' => $purpose_en,
                'purpose_fil' => $purpose_fil,
                'precautions_en' => $prec_en,
                'precautions_fil' => $prec_fil,
                'frequency_hint' => $freq,
                'barcode_source' => 'ph_local_registry',
            ];
        }
    }
    return null;
}

/**
 * Persist a newly discovered barcode/medicine to the local JSON registry.
 */
function pharsayo_save_to_ph_gtin_registry($digits, array $row) {
    $digits = preg_replace('/\D+/', '', (string)$digits);
    if ($digits === '' || strlen($digits) < 8) {
        return false;
    }
    $path = pharsayo_ph_gtin_registry_path();
    $raw = @file_get_contents($path);
    $j = $raw ? json_decode($raw, true) : null;
    if (!is_array($j)) {
        $j = ['_info' => 'PharSayo Auto-cached Registry'];
    }
    
    // Only save if not already exists with a better name
    if (isset($j[$digits]) && !empty($j[$digits]['display_name'])) {
        return true;
    }

    $j[$digits] = [
        'display_name' => $row['display_name'] ?? '',
        'dosage_hint' => $row['dosage_hint'] ?? '',
        'purpose_en' => $row['purpose_en'] ?? '',
        'purpose_fil' => $row['purpose_fil'] ?? '',
        'precautions_en' => $row['precautions_en'] ?? '',
        'precautions_fil' => $row['precautions_fil'] ?? '',
        'frequency_hint' => $row['frequency_hint'] ?? '',
        'rxcui' => $row['rxcui'] ?? '',
    ];

    $newRaw = json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($newRaw) {
        return (bool)@file_put_contents($path, $newRaw);
    }
    return false;
}
