<?php
/**
 * PharSayo - resolve scanned drug names to public labeling data (RxNav + OpenFDA).
 * Educational use only; not a substitute for professional medical advice.
 */

require_once __DIR__ . '/ph_gtin_registry.php';

function pharsayo_http_get_json($url, $timeout_sec = 14) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout_sec,
            'header' => "Accept: application/json\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * @param array<string,mixed> $bodyArray
 * @return array<string,mixed>|null
 */
function pharsayo_http_post_json($url, array $bodyArray, $timeout_sec = 14) {
    $body = json_encode($bodyArray);
    if ($body === false) {
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_sec);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($raw === false) {
            error_log("PharSayo CURL Error: " . $err);
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    // Fallback to stream context if CURL is missing
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => $timeout_sec,
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
            'content' => $body,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Raw HTML/UTF-8 text fetch (barcode-list.com and similar).
 */
function pharsayo_http_get_text($url, $timeout_sec = 14) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout_sec,
            'header' => "Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw !== false ? (string)$raw : '';
}

/**
 * @return list<string>
 */
function pharsayo_barcodelist_extract_names_from_html($html) {
    $names = [];
    if (preg_match('/<meta\s+name="description"\s+content="([^"]+)"/i', (string)$html, $m)) {
        $desc = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/products?:\s*(.+)$/iu', $desc, $pm)) {
            foreach (preg_split('/\s*;\s*/', $pm[1]) as $chunk) {
                $n = trim(preg_replace('/\s+/', ' ', (string)$chunk));
                if (strlen($n) > 2 && !preg_match('/^barcode\s*:/iu', $n)) {
                    $names[] = $n;
                }
            }
        }
    }
    if (preg_match('/<title>([^<]+)<\/title>/i', (string)$html, $tm)) {
        $t = trim(html_entity_decode($tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/^(.+?)\s*-\s*Barcode\s*:/iu', $t, $xm)) {
            array_unshift($names, trim($xm[1]));
        }
    }

    // Try finding the specific product name container if the above fails
    // barcode-list.com often uses <h2> or specific class for the product name
    if (preg_match('/<h2[^>]*>(.+?)<\/h2>/i', (string)$html, $hm)) {
        $h = trim(html_entity_decode(strip_tags($hm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (strlen($h) > 2 && stripos($h, 'barcode') === false) {
            $names[] = $h;
        }
    }
    
    // Sometimes it's in a specific div
    if (preg_match('/<div[^>]*class="product-name"[^>]*>(.+?)<\/div>/i', (string)$html, $dm)) {
        $d = trim(html_entity_decode(strip_tags($dm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (strlen($d) > 2) {
            $names[] = $d;
        }
    }

    return array_values(array_unique(array_filter($names)));
}

/**
 * @param list<string> $names
 */
function pharsayo_barcodelist_pick_best_name(array $names) {
    $best = '';
    $bestScore = -1;
    foreach ($names as $n) {
        $n = trim((string)$n);
        if ($n === '') {
            continue;
        }
        $score = strlen($n);
        if (preg_match('/\d+\s*(mg|ml|mcg|g)\b/iu', $n)) {
            $score += 40;
        }
        if (preg_match('/tablet|capsule|caplet|syrup|drops|inject|patch|cream|ointment/iu', $n)) {
            $score += 20;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $n;
        }
    }
    return $best;
}

/**
 * Community barcode directory (barcode-list.com). See https://barcode-list.com/
 *
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string}|null
 */
function pharsayo_lookup_barcode_barcodelist_com($digits) {
    $digits = preg_replace('/\D+/', '', (string)$digits);
    if (strlen($digits) < 8 || strlen($digits) > 14) {
        return null;
    }
    $url = 'https://barcode-list.com/barcode/EN/barcode-' . rawurlencode($digits) . '/Search.htm';
    $html = pharsayo_http_get_text($url, 14);
    if ($html === '') {
        return null;
    }
    $names = pharsayo_barcodelist_extract_names_from_html($html);
    $best = pharsayo_barcodelist_pick_best_name($names);
    if ($best === '') {
        return null;
    }
    $display = pharsayo_truncate_text($best, 220);
    $detail_en = 'Matched via barcode-list.com (user-contributed product names). Verify strength, form, and instructions on your package.';
    $detail_fil = 'Nakita sa barcode-list.com (mula sa kontribusyon ng mga user). Kumpirmahin sa pakete ang lakas, porma, at direksyon.';
    $base = [
        'rxcui' => '',
        'display_name' => $display,
        'dosage_hint' => 'See package label for strength and how to take it.',
        'purpose_en' => pharsayo_truncate_text($detail_en, 700),
        'purpose_fil' => pharsayo_truncate_text($detail_fil, 700),
        'precautions_en' => 'Community entries may be wrong — always confirm with your pharmacist or clinician.',
        'precautions_fil' => 'Maaaring mali ang datos ng komunidad — kumpirmahin sa pharmacist o doktor.',
        'frequency_hint' => 'Follow package or prescriber instructions.',
        'barcode_source' => 'barcode_barcodelist',
    ];
    $base = pharsayo_barcode_row_append_references($base, [
        'barcode-list.com: ' . $url,
    ]);
    return pharsayo_barcode_row_try_rxnav_enrich($base, true);
}

function pharsayo_truncate_text($text, $max = 600) {
    $text = preg_replace('/\s+/', ' ', trim((string)$text));
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, $max) . '…';
}

/**
 * Flatten RxNav /drugs.json concept groups into [{ rxcui, name, tty }, ...].
 */
function pharsayo_rxnav_collect_concepts_from_drugs($json) {
    $out = [];
    if (empty($json['drugGroup']['conceptGroup'])) {
        return $out;
    }
    $groups = $json['drugGroup']['conceptGroup'];
    if (isset($groups['tty'])) {
        $groups = [$groups];
    }
    if (!is_array($groups)) {
        return $out;
    }
    foreach ($groups as $g) {
        $tty = isset($g['tty']) ? (string)$g['tty'] : '';
        $props = $g['conceptProperties'] ?? null;
        if ($props === null) {
            continue;
        }
        if (isset($props['rxcui'])) {
            $props = [$props];
        }
        if (!is_array($props)) {
            continue;
        }
        foreach ($props as $p) {
            if (empty($p['rxcui'])) {
                continue;
            }
            $out[] = [
                'rxcui' => (string)$p['rxcui'],
                'name' => isset($p['name']) ? (string)$p['name'] : '',
                'tty' => $tty,
            ];
        }
    }
    return $out;
}

function pharsayo_rxnav_concept_score($c) {
    $name = (string)($c['name'] ?? '');
    $tty = (string)($c['tty'] ?? '');
    if ($name === '') {
        return -9999.0;
    }
    $s = 0.0;
    if (strpos($name, '{') !== false) {
        $s -= 85;
    }
    if (preg_match('/\}\s*Pack\b/i', $name)) {
        $s -= 70;
    }
    if (preg_match('/\bPack\b/i', $name)) {
        $s -= 25;
    }
    if (substr_count($name, '/') >= 2 && strpos($name, '{') !== false) {
        $s -= 35;
    }
    $ttyRank = [
        'SBD' => 55,
        'SCD' => 52,
        'BN' => 38,
        'IN' => 32,
        'SY' => 22,
        'MIN' => 18,
        'GPCK' => -15,
        'BPCK' => -15,
        'DF' => 8,
        'DFG' => 8,
    ];
    $s += $ttyRank[$tty] ?? 0;
    $len = strlen($name);
    if ($len > 140) {
        $s -= 40;
    }
    if ($len >= 10 && $len <= 90) {
        $s += 12;
    }
    return $s;
}

function pharsayo_rxnav_pick_best_concept($concepts) {
    if (empty($concepts)) {
        return null;
    }
    usort($concepts, function ($a, $b) {
        return pharsayo_rxnav_concept_score($b) <=> pharsayo_rxnav_concept_score($a);
    });
    return $concepts[0];
}

/**
 * Top RxNav concepts after scoring (for trying multiple seeds).
 *
 * @return list<array{rxcui:string,name:string,tty:string}>
 */
function pharsayo_rxnav_pick_top_concepts($concepts, $max = 4) {
    if (empty($concepts)) {
        return [];
    }
    usort($concepts, function ($a, $b) {
        return pharsayo_rxnav_concept_score($b) <=> pharsayo_rxnav_concept_score($a);
    });
    $out = [];
    $seen = [];
    foreach ($concepts as $c) {
        $id = $c['rxcui'] ?? '';
        if ($id === '' || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $out[] = $c;
        if (count($out) >= $max) {
            break;
        }
    }
    return $out;
}

/**
 * Related ingredients / clinical drugs for fallback FDA label lookup (pack RxCUIs often have no SPL).
 */
function pharsayo_rxnav_related_concepts($rxcui) {
    $url = 'https://rxnav.nlm.nih.gov/REST/rxcui/' . rawurlencode((string)$rxcui) . '/related.json?tty=SCD+SBD+IN';
    $json = pharsayo_http_get_json($url);
    $out = [];
    if (empty($json['relatedGroup']['conceptGroup'])) {
        return $out;
    }
    $groups = $json['relatedGroup']['conceptGroup'];
    if (isset($groups['tty'])) {
        $groups = [$groups];
    }
    if (!is_array($groups)) {
        return $out;
    }
    foreach ($groups as $g) {
        $tty = isset($g['tty']) ? (string)$g['tty'] : '';
        $props = $g['conceptProperties'] ?? null;
        if ($props === null) {
            continue;
        }
        if (isset($props['rxcui'])) {
            $props = [$props];
        }
        if (!is_array($props)) {
            continue;
        }
        foreach ($props as $p) {
            if (empty($p['rxcui'])) {
                continue;
            }
            $rid = (string)$p['rxcui'];
            if ($rid === (string)$rxcui) {
                continue;
            }
            $out[] = [
                'rxcui' => $rid,
                'name' => isset($p['name']) ? (string)$p['name'] : '',
                'tty' => $tty,
            ];
        }
    }
    return $out;
}

function pharsayo_openfda_label_by_rxcui($rxcui, $limit = 4) {
    $url = 'https://api.fda.gov/drug/label.json?search=openfda.rxcui:' . rawurlencode((string)$rxcui)
        . '&limit=' . (int)$limit;
    return pharsayo_http_get_json($url);
}

function pharsayo_openfda_first_string($row, $key) {
    if (empty($row[$key]) || !is_array($row[$key])) {
        return '';
    }
    $v = $row[$key][0];
    return is_string($v) ? $v : '';
}

function pharsayo_openfda_extract_from_result($r) {
    $indications = pharsayo_openfda_first_string($r, 'indications_and_usage');
    if ($indications === '') {
        $indications = pharsayo_openfda_first_string($r, 'purpose');
    }
    $dosage_admin = pharsayo_openfda_first_string($r, 'dosage_and_administration');
    $warnings = pharsayo_openfda_first_string($r, 'warnings');
    if ($warnings === '') {
        $warnings = pharsayo_openfda_first_string($r, 'warnings_and_cautions');
    }
    if ($warnings === '') {
        $warnings = pharsayo_openfda_first_string($r, 'contraindications');
    }
    $forms = '';
    if (!empty($r['dosage_forms_and_strengths']) && is_array($r['dosage_forms_and_strengths'])) {
        $forms = (string)$r['dosage_forms_and_strengths'][0];
    }
    return [$indications, $dosage_admin, $warnings, $forms];
}

function pharsayo_openfda_display_name_from_result($r, $fallback) {
    if (!empty($r['openfda']['brand_name'][0])) {
        return (string)$r['openfda']['brand_name'][0];
    }
    if (!empty($r['openfda']['generic_name'][0])) {
        return (string)$r['openfda']['generic_name'][0];
    }
    return $fallback;
}

/**
 * Lowercased haystack of openfda ingredient / brand fields for matching OCR intent.
 */
function pharsayo_openfda_label_haystack($r) {
    if (!is_array($r) || empty($r['openfda']) || !is_array($r['openfda'])) {
        return '';
    }
    $o = $r['openfda'];
    $parts = [];
    foreach (['generic_name', 'brand_name', 'substance_name'] as $k) {
        if (empty($o[$k]) || !is_array($o[$k])) {
            continue;
        }
        foreach ($o[$k] as $g) {
            if (is_string($g) && $g !== '') {
                $parts[] = strtolower($g);
            }
        }
    }
    return implode(' | ', $parts);
}

/**
 * Normalized tokens from the query string + full OCR/context text (ingredient-like words only).
 *
 * @return list<string>
 */
function pharsayo_build_match_needles($query_name, $context_text) {
    $needles = [];
    $add = function ($w) use (&$needles) {
        $w = strtolower(preg_replace('/[^a-z0-9]/', '', (string)$w));
        if (strlen($w) >= 4) {
            $needles[$w] = true;
        }
    };
    $add($query_name);
    $blob = $context_text . ' ' . $query_name;
    if (preg_match_all('/[A-Za-z][A-Za-z\-]{3,}\b/', $blob, $m)) {
        $stop = [
            'capsule', 'capsules', 'tablet', 'tablets', 'oral', 'each', 'with', 'and', 'the', 'for', 'per',
            'film', 'coated', 'modified', 'release', 'extended', 'sustained', 'chewable', 'dispersible',
            'tab', 'tabs', 'cap', 'caps', 'syrup', 'suspension', 'solution', 'drops', 'spray', 'ointment',
            'cream', 'gel', 'pack', 'dose', 'doses', 'every', 'hours', 'hour', 'daily', 'once', 'twice',
            'generic', 'brand', 'this', 'that', 'take', 'your', 'you', 'use', 'used', 'please', 'read',
            'before', 'after', 'food', 'meal', 'meals', 'adult', 'adults', 'children', 'child', 'mg', 'ml',
            'mcg', 'units', 'unit', 'contains', 'containing', 'ingredient', 'active', 'inert', 'other',
            'ingredients', 'see', 'insert', 'label', 'manufacturer', 'distributed', 'laboratories', 'laboratory',
        ];
        foreach ($m[0] as $w) {
            $lw = strtolower($w);
            if (strlen($lw) < 5) {
                continue;
            }
            if (in_array($lw, $stop, true)) {
                continue;
            }
            $add($w);
            // U.S. labels often use "carbocysteine"; PH labels often "carbocisteine"
            if (preg_match('/cisteine/i', $w) && !preg_match('/cysteine/i', $w)) {
                $add(preg_replace('/cisteine/i', 'cysteine', $w));
            }
        }
    }
    foreach (array_keys($needles) as $k) {
        if (strpos($k, 'cisteine') !== false && strpos($k, 'cysteine') === false) {
            $needles[str_replace('cisteine', 'cysteine', $k)] = true;
        }
    }
    return array_keys($needles);
}

function pharsayo_row_matches_needles($row, array $needles) {
    if (empty($needles)) {
        return false;
    }
    $hay = pharsayo_openfda_label_haystack($row);
    if ($hay === '') {
        return false;
    }
    foreach ($needles as $n) {
        $len = strlen($n);
        if ($len < 4) {
            continue;
        }
        if (strpos($hay, $n) !== false) {
            return true;
        }
        if ($len >= 6) {
            foreach (preg_split('/[^a-z]+/', $hay) as $part) {
                if (strlen($part) < 5) {
                    continue;
                }
                similar_text($n, $part, $pct);
                if ($pct >= 88.0) {
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Richest SPL row that both matches OCR needles and has labeling text.
 */
function pharsayo_openfda_pick_best_matching_row($fda_json, array $needles) {
    if (empty($fda_json['results']) || !is_array($fda_json['results']) || empty($needles)) {
        return null;
    }
    $best = null;
    $bestScore = -1;
    foreach ($fda_json['results'] as $r) {
        if (!is_array($r)) {
            continue;
        }
        if (!pharsayo_row_matches_needles($r, $needles)) {
            continue;
        }
        [$ind, $dos, $war] = pharsayo_openfda_extract_from_result($r);
        if ($ind === '' && $dos === '' && $war === '') {
            continue;
        }
        $score = strlen($ind) * 2 + strlen($dos) + strlen($war);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $r;
        }
    }
    return $best;
}

/**
 * OpenFDA fallback when pack/combo RxCUI has no SPL — search by substance / generic name token.
 *
 * @return array|null Full FDA JSON payload that contains at least one matching row
 */
function pharsayo_openfda_label_search_token($token, array $needles) {
    $token = trim($token);
    if (strlen($token) < 4) {
        return null;
    }
    $q = rawurlencode($token);
    $urls = [
        'https://api.fda.gov/drug/label.json?search=openfda.substance_name:"' . $q . '"&limit=8',
        'https://api.fda.gov/drug/label.json?search=openfda.generic_name:"' . $q . '"&limit=8',
    ];
    foreach ($urls as $url) {
        $fda = pharsayo_http_get_json($url);
        $row = pharsayo_openfda_pick_best_matching_row($fda ?: [], $needles);
        if ($row !== null) {
            return $fda;
        }
    }
    return null;
}

function pharsayo_extract_search_tokens_from_query($query_name) {
    $clean = preg_replace('/[{}]/', ' ', $query_name);
    $clean = preg_replace('/\s+/', ' ', trim($clean));
    $words = preg_split('/[^A-Za-z]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
    $stop = [
        'oral', 'tablet', 'tablets', 'capsule', 'capsules', 'solution', 'suspension', 'pack', 'each',
        'with', 'and', 'the', 'for', 'per', 'mg', 'ml', 'mcg', 'hydrochloride', 'hydrobromide',
    ];
    $out = [];
    foreach ($words as $w) {
        $lw = strtolower($w);
        if (strlen($lw) < 5) {
            continue;
        }
        if (in_array($lw, $stop, true)) {
            continue;
        }
        $out[] = $w;
        if (count($out) >= 4) {
            break;
        }
    }
    return array_values(array_unique($out));
}

function pharsayo_frequency_from_dosage($dosage_admin) {
    $t = pharsayo_truncate_text(strip_tags((string)$dosage_admin), 1200);
    if ($t === '') {
        return 'Sundin ang iskedyul sa reseta o sa package insert.';
    }
    if (preg_match('/\b(once\s+daily|twice\s+daily|three\s+times\s+daily|every\s+\d+\s*(?:hours?|h)|\d+\s*(?:to|-|–)\s*\d+\s*times\s+per\s+day)[^.]{0,120}/i', $t, $m)) {
        return pharsayo_truncate_text(trim($m[0]), 240);
    }
    if (preg_match('/^.{10,400}?(\d+\s*(?:mg|mL|ml|mcg)[^.]{0,200}\.)/i', $t, $m)) {
        return pharsayo_truncate_text(trim($m[1]), 260);
    }
    return pharsayo_truncate_text($t, 320);
}

/**
 * Pick the SPL row with the most usable labeling text (no OCR needle matching).
 *
 * @param array|null $fda_json
 * @return array|null
 */
function pharsayo_openfda_pick_richest_row($fda_json) {
    if (empty($fda_json['results']) || !is_array($fda_json['results'])) {
        return null;
    }
    $best = null;
    $bestScore = -1;
    foreach ($fda_json['results'] as $r) {
        if (!is_array($r)) {
            continue;
        }
        [$ind, $dos, $war, $forms] = pharsayo_openfda_extract_from_result($r);
        if ($ind === '' && $dos === '' && $war === '') {
            continue;
        }
        $score = strlen($ind) * 2 + strlen($dos) + strlen($war);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $r;
        }
    }
    return $best;
}

/**
 * Build the standard medication lookup array from a chosen OpenFDA label SPL row.
 *
 * @param array $bestRow
 * @param string $fallbackDisplay
 * @param string $matchedRxcui
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string}
 */
function pharsayo_build_lookup_result_from_openfda_row($bestRow, $fallbackDisplay, $matchedRxcui) {
    $display_name = pharsayo_openfda_display_name_from_result($bestRow, $fallbackDisplay);
    if (strpos($display_name, '{') !== false && $fallbackDisplay !== '' && strpos($fallbackDisplay, '{') === false) {
        $display_name = $fallbackDisplay;
    }

    [$indications, $dosage_admin, $warnings, $forms] = pharsayo_openfda_extract_from_result($bestRow);

    $dosage_hint = 'Sundin ang reseta ng doktor o ang nakasulat sa label.';
    if ($dosage_admin !== '') {
        $dosage_hint = pharsayo_truncate_text(strip_tags($dosage_admin), 900);
    } elseif ($forms !== '') {
        $dosage_hint = pharsayo_truncate_text(strip_tags($forms), 400);
    }

    $frequency_hint = pharsayo_frequency_from_dosage($dosage_admin);

    $purpose_en = $indications !== ''
        ? pharsayo_truncate_text(strip_tags($indications), 1200)
        : 'Educational summary from public drug labeling (U.S. FDA). Confirm use with your doctor or pharmacist.';

    $precautions_en = $warnings !== ''
        ? pharsayo_truncate_text(strip_tags($warnings), 1200)
        : 'Read the package insert and discuss risks with a licensed clinician.';

    if ($indications !== '') {
        $purpose_fil = 'Mula sa U.S. FDA drug label (Ingles — pang-edukasyon lamang): '
            . pharsayo_truncate_text(strip_tags($indications), 1000);
    } else {
        $purpose_fil = 'Impormasyong pang-edukasyon mula sa pampublikong U.S. FDA drug label. '
            . 'Kumpirmahin sa inyong doktor o pharmacist bago gamitin.';
    }

    if ($warnings !== '') {
        $precautions_fil = 'Mga babala mula sa U.S. FDA label (Ingles — pang-edukasyon lamang): '
            . pharsayo_truncate_text(strip_tags($warnings), 1000);
    } else {
        $precautions_fil = 'Basahin ang package insert at kumonsulta sa doktor tungkol sa panganib at side effects.';
    }

    $mr = (string)$matchedRxcui;
    if ($mr === '' && !empty($bestRow['openfda']['rxcui'][0])) {
        $mr = (string)$bestRow['openfda']['rxcui'][0];
    }

    return [
        'rxcui' => $mr,
        'display_name' => pharsayo_truncate_text($display_name, 220),
        'dosage_hint' => $dosage_hint,
        'purpose_en' => $purpose_en,
        'purpose_fil' => $purpose_fil,
        'precautions_en' => $precautions_en,
        'precautions_fil' => $precautions_fil,
        'frequency_hint' => $frequency_hint,
    ];
}

/**
 * @return array{rxcui:string,conceptName:string}|null
 */
function pharsayo_rxnav_ndcstatus_lookup($ndc) {
    $ndc = trim((string)$ndc);
    if ($ndc === '' || !preg_match('/\d/', $ndc)) {
        return null;
    }
    $url = 'https://rxnav.nlm.nih.gov/REST/ndcstatus.json?ndc=' . rawurlencode($ndc);
    $json = pharsayo_http_get_json($url);
    if (empty($json['ndcStatus']) || !is_array($json['ndcStatus'])) {
        return null;
    }
    $st = $json['ndcStatus'];
    $rxcui = isset($st['rxcui']) ? trim((string)$st['rxcui']) : '';
    if ($rxcui === '') {
        return null;
    }
    $concept = isset($st['conceptName']) ? trim((string)$st['conceptName']) : '';
    return ['rxcui' => $rxcui, 'conceptName' => $concept];
}

/**
 * @return list<string>
 */
function pharsayo_barcode_collect_ndc_candidates($raw) {
    $raw = trim((string)$raw);
    $digits = preg_replace('/\D+/', '', $raw);
    $cands = [];
    $add = function ($s) use (&$cands) {
        $s = trim((string)$s);
        if ($s === '' || !preg_match('/\d/', $s)) {
            return;
        }
        if (!in_array($s, $cands, true)) {
            $cands[] = $s;
        }
    };
    if ($raw !== '') {
        $add($raw);
    }
    if ($digits === '') {
        return $cands;
    }
    $add($digits);
    $len = strlen($digits);
    
    // NDC formats are often 11 digits (5-4-2) or 10 digits (4-4-2, 5-3-2, 5-4-1).
    if ($len === 11) {
        $add(substr($digits, 0, 5) . '-' . substr($digits, 5, 4) . '-' . substr($digits, 9, 2));
    } elseif ($len === 10) {
        $add(substr($digits, 0, 4) . '-' . substr($digits, 4, 4) . '-' . substr($digits, 8, 2));
        $add(substr($digits, 0, 5) . '-' . substr($digits, 5, 3) . '-' . substr($digits, 8, 2));
        $add(substr($digits, 0, 5) . '-' . substr($digits, 5, 4) . '-' . substr($digits, 9, 1));
        // Also try adding a leading zero if 10 digits
        $add('0' . $digits);
    } elseif ($len === 9) {
        // Some older NDCs
        $add(substr($digits, 0, 4) . '-' . substr($digits, 4, 4) . '-' . substr($digits, 8, 1));
    }
    
    return array_values(array_unique(array_filter($cands)));
}

/**
 * @return list<string>
 */
function pharsayo_barcode_collect_upc_candidates($digits) {
    $d = preg_replace('/\D+/', '', (string)$digits);
    if ($d === '') {
        return [];
    }
    $out = [];
    $add = function ($s) use (&$out) {
        if ($s === '' || in_array($s, $out, true)) {
            return;
        }
        $out[] = $s;
    };
    
    $len = strlen($d);
    if ($len === 12) {
        $add($d);
    } elseif ($len === 13) {
        // EAN-13 (could be UPC-A with leading zero, or actual EAN-13)
        $add($d);
        if ($d[0] === '0') {
            $add(substr($d, 1, 12));
        }
    } elseif ($len === 14) {
        // GTIN-14 (could be UPC-A with two leading zeros, or EAN-13 with one leading zero)
        $add($d);
        $stripped = ltrim($d, '0');
        if (strlen($stripped) === 12 || strlen($stripped) === 13) {
            $add($stripped);
        }
    } elseif ($len >= 8 && $len < 12) {
        // Pad EAN-8 to UPC-A (though usually they are separate, some DBs might store them padded)
        $add(str_pad($d, 12, '0', STR_PAD_LEFT));
    }
    
    return array_values(array_unique(array_filter($out)));
}

/**
 * @param array|null $json
 * @return string|null
 */
function pharsayo_openfda_ndc_json_first_rxcui($json) {
    if (empty($json['results'][0]) || !is_array($json['results'][0])) {
        return null;
    }
    $row = $json['results'][0];
    if (!empty($row['openfda']['rxcui'][0])) {
        return (string)$row['openfda']['rxcui'][0];
    }
    return null;
}

/**
 * @param string $searchFragment e.g. openfda.upc:"012345678901" or packaging.package_ndc:00071-0155-23
 * @return array|null
 */
function pharsayo_openfda_ndc_directory_search($searchFragment) {
    $frag = trim((string)$searchFragment);
    if ($frag === '') {
        return null;
    }
    $url = 'https://api.fda.gov/drug/ndc.json?search=' . rawurlencode($frag) . '&limit=5';
    return pharsayo_http_get_json($url);
}

/**
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string}|null
 */
function pharsayo_lookup_medication_from_rxcui_barcode($rxcui, $fallbackDisplay) {
    $rxcui = trim((string)$rxcui);
    if ($rxcui === '') {
        return null;
    }
    $fda = pharsayo_openfda_label_by_rxcui($rxcui, 12);
    $row = pharsayo_openfda_pick_richest_row($fda ?: []);
    if ($row === null) {
        return null;
    }
    return pharsayo_build_lookup_result_from_openfda_row($row, $fallbackDisplay, $rxcui);
}

/**
 * @param array $p Open Food Facts "product" object
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source:string}|null
 */
function pharsayo_openfacts_product_to_lookup_row($p, $barcodeSource) {
    if (!is_array($p)) {
        return null;
    }
    $name = '';
    foreach (['product_name', 'product_name_en', 'generic_name', 'abbreviated_product_name'] as $k) {
        if (!empty($p[$k]) && is_string($p[$k])) {
            $name = trim($p[$k]);
            if ($name !== '') {
                break;
            }
        }
    }
    if ($name === '') {
        return null;
    }
    $brands = isset($p['brands']) && is_string($p['brands']) ? trim($p['brands']) : '';
    $qty = isset($p['quantity']) && is_string($p['quantity']) ? trim($p['quantity']) : '';
    $ingredients = isset($p['ingredients_text']) && is_string($p['ingredients_text']) ? trim(strip_tags($p['ingredients_text'])) : '';
    $cats = '';
    if (!empty($p['categories']) && is_string($p['categories'])) {
        $cats = trim($p['categories']);
    }
    $display = $name;
    if ($brands !== '' && stripos($name, $brands) === false) {
        $display = $brands . ' — ' . $name;
    }
    $dosage = $qty !== '' ? $qty : 'See package label for strength and how to take it.';
    $isPhMirror = ($barcodeSource === 'openfoodfacts_ph');
    $isOpenProducts = ($barcodeSource === 'openproductsfacts' || $barcodeSource === 'openproductsfacts_search');
    if ($isPhMirror) {
        $purpose_en = 'Matched via the Philippines Open Food Facts mirror (community data: food, supplements, etc.). '
            . 'This is not official FDA Philippines or US FDA labeling. '
            . ($cats !== '' ? 'Categories: ' . pharsayo_truncate_text($cats, 220) . ' ' : '')
            . 'Confirm with your doctor or pharmacist.';
        $purpose_fil = 'Nakita sa Philippines Open Food Facts mirror (komunidad na datos). '
            . 'Hindi ito opisyal na label ng FDA Philippines o US FDA. '
            . 'Kumpirmahin sa doktor o pharmacist.';
    } elseif ($isOpenProducts) {
        $purpose_en = 'Matched by barcode in Open Products Facts (community database for medicines, supplements, cosmetics, and personal care). '
            . 'This is not a substitute for official drug labeling. '
            . ($cats !== '' ? 'Categories: ' . pharsayo_truncate_text($cats, 220) . ' ' : '')
            . 'Confirm use, dose, and warnings with your doctor or pharmacist.';
        $purpose_fil = 'Nakita ang barcode sa Open Products Facts (komunidad na database para sa gamot, supplement, at personal care). '
            . 'Hindi ito kapalit ng opisyal na drug label. '
            . 'Kumpirmahin sa doktor o pharmacist ang tamang gamit at babala.';
    } else {
        $purpose_en = 'Matched by barcode in the Open Food Facts community database (food, supplements, cosmetics, or pet products). '
            . 'This is not US FDA drug labeling. '
            . ($cats !== '' ? 'Categories: ' . pharsayo_truncate_text($cats, 220) . ' ' : '')
            . 'Confirm use, dose, and warnings with your doctor or pharmacist.';
        $purpose_fil = 'Nakita gamit ang barcode sa Open Food Facts (komunidad na database). '
            . 'Hindi ito opisyal na US FDA drug label. '
            . 'Kumpirmahin sa doktor o pharmacist ang tamang gamit at babala.';
    }
    $allergens = isset($p['allergens']) && is_string($p['allergens']) ? trim(strip_tags($p['allergens'])) : '';
    $prec_en = $allergens !== '' ? 'Allergens (from database): ' . pharsayo_truncate_text($allergens, 320) : 'Check the physical package for allergens and warnings.';
    $prec_fil = $allergens !== '' ? 'Mga allergen (mula sa database): ' . pharsayo_truncate_text($allergens, 320) : 'Tingnan ang aktwal na pakete para sa allergen at babala.';
    if ($ingredients !== '') {
        $prec_en .= ' Ingredients (from database): ' . pharsayo_truncate_text($ingredients, 450);
    }

    return [
        'rxcui' => '',
        'display_name' => pharsayo_truncate_text($display, 220),
        'dosage_hint' => pharsayo_truncate_text($dosage, 160),
        'purpose_en' => pharsayo_truncate_text($purpose_en, 700),
        'purpose_fil' => pharsayo_truncate_text($purpose_fil, 700),
        'precautions_en' => pharsayo_truncate_text($prec_en, 700),
        'precautions_fil' => pharsayo_truncate_text($prec_fil, 700),
        'frequency_hint' => 'Follow package or prescriber instructions.',
        'barcode_source' => $barcodeSource,
    ];
}

/**
 * Open *Facts product APIs — Philippines mirror first, then global (used in PH deployments).
 *
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string}|null
 */
function pharsayo_lookup_medication_by_barcode_open_facts($digits) {
    $digits = preg_replace('/\D+/', '', (string)$digits);
    $codes = pharsayo_barcode_gtin_variants($digits);
    if (empty($codes)) {
        return null;
    }

    $hosts = [
        ['host' => 'https://ph.openfoodfacts.org', 'source' => 'openfoodfacts_ph'],
        ['host' => 'https://world.openfoodfacts.org', 'source' => 'openfoodfacts'],
        ['host' => 'https://world.openproductsfacts.org', 'source' => 'openproductsfacts'],
        ['host' => 'https://world.openbeautyfacts.org', 'source' => 'openbeautyfacts'],
        ['host' => 'https://world.openpetfoodfacts.org', 'source' => 'openpetfoodfacts'],
    ];

    foreach ($hosts as $h) {
        foreach ($codes as $code) {
            $url = $h['host'] . '/api/v0/product/' . rawurlencode($code) . '.json';
            $json = pharsayo_http_get_json($url, 12);
            if ($json === null || empty($json['status']) || (int)$json['status'] !== 1) {
                continue;
            }
            if (empty($json['product']) || !is_array($json['product'])) {
                continue;
            }
            $row = pharsayo_openfacts_product_to_lookup_row($json['product'], $h['source']);
            if ($row !== null) {
                return $row;
            }
        }
    }
    return null;
}

/**
 * @param list<string> $lines
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string,references_en?:string,references_fil?:string}
 */
function pharsayo_barcode_row_append_references(array $row, array $lines) {
    $lines = array_values(array_filter(array_map(function ($x) {
        return trim((string)$x);
    }, $lines)));
    if (empty($lines)) {
        return $row;
    }
    $block = implode("\n", $lines);
    $row['references_en'] = (isset($row['references_en']) && (string)$row['references_en'] !== '')
        ? (string)$row['references_en'] . "\n" . $block
        : $block;
    $row['references_fil'] = (isset($row['references_fil']) && (string)$row['references_fil'] !== '')
        ? (string)$row['references_fil'] . "\n" . $block
        : $block;
    return $row;
}

/**
 * After a public product match (UPCitemdb, Wikidata, …), try RxNav + OpenFDA by product name to add clinical labeling.
 *
 * @param array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string,references_en?:string,references_fil?:string} $row
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string,references_en?:string,references_fil?:string}
 */
function pharsayo_barcode_row_should_try_rx_enrich(array $row) {
    $t = strtolower((string)($row['display_name'] ?? '') . ' ' . (string)($row['purpose_en'] ?? ''));
    return (bool)preg_match(
        '/\b(mg|mcg|mcg\/|g\/|ml|tablet|tablets|capsule|capsules|injection|inject|syrup|drops|spray|penicillin|paracetamol|acetaminophen|ibuprofen|aspirin|amoxicillin|insulin|vitamin|supplement|ointment|cream|lotion|pharma|antibiotic|analgesic|rx|otc|rxnav|fda|mefenamic|naproxen|diclofenac|celecoxib|omeprazole|metformin|losartan|amlodipine|simvastatin|atorvastatin|multivitamins|ascorbic|zinc|lagundi|carbocisteine|ambroxol|salbutamol|terbutaline|cetirizine|loratadine|diphenhydramine|chlorphenamine|phenylephrine|phenylpropanolamine|dextromethorphan|guaifenesin|loperamide|simethicone|aluminum|magnesium|calcium|vitamin|vitamins)\b/i',
        $t
    );
}

/**
 * @param bool $onlyIfDrugLike If true, skip Rx merge for plain food / cosmetic titles (reduces wrong FDA text).
 */
function pharsayo_barcode_row_try_rxnav_enrich(array $row, $onlyIfDrugLike = false) {
    if ($onlyIfDrugLike && !pharsayo_barcode_row_should_try_rx_enrich($row)) {
        return $row;
    }
    $title = isset($row['display_name']) ? trim((string)$row['display_name']) : '';
    if ($title === '') {
        return $row;
    }
    $search = preg_replace('/^[^—\-|:]+[—\-|:\s]+/u', '', $title);
    $search = trim((string)$search);
    if (strlen($search) < 3) {
        $search = $title;
    }
    $ctx = $title . ' ' . (string)($row['purpose_en'] ?? '');
    $rx = pharsayo_lookup_medication_online($search, $ctx);
    if ($rx === null) {
        return $row;
    }
    $rxPe = strlen((string)($rx['purpose_en'] ?? ''));
    $basePe = strlen((string)($row['purpose_en'] ?? ''));
    if ($rxPe > max(220, $basePe + 40)) {
        $row['purpose_en'] = $rx['purpose_en'];
        $row['purpose_fil'] = $rx['purpose_fil'];
        $row['precautions_en'] = $rx['precautions_en'];
        $row['precautions_fil'] = $rx['precautions_fil'];
        if (strlen((string)($rx['dosage_hint'] ?? '')) > strlen((string)($row['dosage_hint'] ?? ''))) {
            $row['dosage_hint'] = $rx['dosage_hint'];
        }
        $row['frequency_hint'] = $rx['frequency_hint'];
        if (($rx['rxcui'] ?? '') !== '') {
            $row['rxcui'] = $rx['rxcui'];
        }
    } elseif ($rxPe > 120) {
        $snippet = pharsayo_truncate_text((string)$rx['purpose_en'], 720);
        $row['purpose_en'] = pharsayo_truncate_text(
            (string)($row['purpose_en'] ?? '') . "\n\n— U.S. FDA labeling excerpt (name match: \"" . pharsayo_truncate_text($search, 72) . "\"):\n" . $snippet,
            1800
        );
        $sf = pharsayo_truncate_text((string)$rx['purpose_fil'], 620);
        $row['purpose_fil'] = pharsayo_truncate_text(
            (string)($row['purpose_fil'] ?? '') . "\n\n— Dagdag mula sa U.S. FDA label:\n" . $sf,
            1800
        );
    }
    $refs = [];
    if (($rx['rxcui'] ?? '') !== '') {
        $rc = rawurlencode((string)$rx['rxcui']);
        $refs[] = 'RxNav (NLM) RxCUI ' . $rx['rxcui'] . ': https://mor.nlm.nih.gov/RxNav/RxOverview.jsp?rxcui=' . $rc;
        $refs[] = 'openFDA drug labels (JSON): https://api.fda.gov/drug/label.json?search=openfda.rxcui:"' . $rc . '"';
    }
    $refs[] = 'RxNav drug search: https://rxnav.nlm.nih.gov/REST/drugs.json?name=' . rawurlencode($search);
    $refs[] = 'DailyMed search (NIH): https://dailymed.nlm.nih.gov/dailymed/search.cfm?labeltype=human&query=' . rawurlencode($search);
    return pharsayo_barcode_row_append_references($row, $refs);
}

/**
 * Wikidata Query Service — GTIN / barcode statements (community graph; verify externally).
 *
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string,references_en?:string,references_fil?:string}|null
 */
function pharsayo_lookup_barcode_wikidata($digits) {
    $variants = pharsayo_barcode_gtin_variants($digits);
    if (empty($variants)) {
        return null;
    }
    $vals = [];
    foreach (array_slice($variants, 0, 8) as $v) {
        $v = preg_replace('/[^0-9]/', '', (string)$v);
        if ($v !== '') {
            $vals[] = '"' . $v . '"';
        }
    }
    if (empty($vals)) {
        return null;
    }
    $valueBlock = implode(' ', $vals);
    $sparql = <<<SPARQL
SELECT ?item ?itemLabel ?itemDescription ?wp WHERE {
  VALUES ?gtin { $valueBlock }
  {
    ?item wdt:P6298 ?gtin .
  } UNION {
    ?item p:P6298 ?st . ?st ps:P6298 ?gtin .
  }
  OPTIONAL {
    ?wp schema:about ?item .
    FILTER(STRSTARTS(STR(?wp), "https://en.wikipedia.org/wiki/"))
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en,tl". }
}
LIMIT 6
SPARQL;

    $url = 'https://query.wikidata.org/sparql?format=json&query=' . rawurlencode($sparql);
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 18,
            'header' => "Accept: application/sparql-results+json\r\nUser-Agent: PharSayo/1.0 (barcode lookup; educational)\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $json = json_decode($raw, true);
    if (empty($json['results']['bindings'][0]) || !is_array($json['results']['bindings'][0])) {
        return null;
    }
    $b = $json['results']['bindings'][0];
    $label = isset($b['itemLabel']['value']) ? trim((string)$b['itemLabel']['value']) : '';
    if ($label === '') {
        return null;
    }
    $desc = isset($b['itemDescription']['value']) ? trim((string)$b['itemDescription']['value']) : '';
    $itemUri = isset($b['item']['value']) ? (string)$b['item']['value'] : '';
    $wp = isset($b['wp']['value']) ? trim((string)$b['wp']['value']) : '';

    $purpose_en = 'Matched by barcode in Wikidata (community-maintained knowledge graph). '
        . 'This is not official drug approval text — always confirm with your pharmacist or clinician.';
    if ($desc !== '') {
        $purpose_en .= ' Description: ' . pharsayo_truncate_text($desc, 520);
    }
    $purpose_fil = 'Nakita ang barcode sa Wikidata (komunidad na knowledge graph). '
        . 'Hindi ito opisyal na apruba ng gamot — kumpirmahin sa pharmacist o doktor.';
    if ($desc !== '') {
        $purpose_fil .= ' Deskripsyon: ' . pharsayo_truncate_text($desc, 420);
    }

    $refs = [];
    if ($itemUri !== '') {
        $refs[] = 'Wikidata item: ' . $itemUri;
    }
    if ($wp !== '') {
        $refs[] = 'Wikipedia (English): ' . $wp;
    }
    $refs[] = 'Wikidata query service: https://query.wikidata.org/';

    $row = [
        'rxcui' => '',
        'display_name' => pharsayo_truncate_text($label, 220),
        'dosage_hint' => 'See package, prescribing information, or Wikidata structured data for strength.',
        'purpose_en' => pharsayo_truncate_text($purpose_en, 900),
        'purpose_fil' => pharsayo_truncate_text($purpose_fil, 900),
        'precautions_en' => 'Wikidata may be incomplete or wrong — verify identity, dose, and warnings on the physical product.',
        'precautions_fil' => 'Maaaring kulang o mali ang Wikidata — beripikahin sa aktwal na pakete.',
        'frequency_hint' => 'Follow prescriber or package instructions.',
        'barcode_source' => 'wikidata_gtin',
    ];
    return pharsayo_barcode_row_append_references($row, $refs);
}

/**
 * Public barcode catalog (UPCitemdb trial) — many international GTINs.
 *
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string}|null
 */
function pharsayo_lookup_barcode_upcitemdb($digits) {
    $digits = preg_replace('/\D+/', '', (string)$digits);
    if (strlen($digits) < 8 || strlen($digits) > 14) {
        return null;
    }
    $json = pharsayo_http_post_json('https://api.upcitemdb.com/prod/trial/lookup', ['upc' => $digits], 12);
    if ($json === null || empty($json['items'][0]) || !is_array($json['items'][0])) {
        $json = pharsayo_http_get_json(
            'https://api.upcitemdb.com/prod/trial/lookup?upc=' . rawurlencode($digits),
            12,
        );
    }
    if ($json === null || empty($json['items'][0]) || !is_array($json['items'][0])) {
        return null;
    }
    $it = $json['items'][0];
    $title = isset($it['title']) ? trim((string)$it['title']) : '';
    if ($title === '') {
        return null;
    }
    $brand = isset($it['brand']) ? trim((string)$it['brand']) : '';
    $desc = isset($it['description']) ? trim(strip_tags((string)$it['description'])) : '';
    $cat = isset($it['category']) ? trim((string)$it['category']) : '';
    $display = $title;
    if ($brand !== '' && stripos($title, $brand) === false) {
        $display = $brand . ' — ' . $title;
    }
    $detail_en = 'Matched via public barcode lookup (UPCitemdb). ';
    if ($cat !== '') {
        $detail_en .= 'Category: ' . pharsayo_truncate_text($cat, 180) . ' ';
    }
    $detail_en .= $desc !== '' ? pharsayo_truncate_text($desc, 420) : 'Confirm every detail on your package.';
    $detail_fil = 'Nakita sa pampublikong barcode lookup (UPCitemdb). Kumpirmahin ang lahat ng detalye sa pakete.';

    $base = [
        'rxcui' => '',
        'display_name' => pharsayo_truncate_text($display, 220),
        'dosage_hint' => 'See package label for strength and dosing.',
        'purpose_en' => pharsayo_truncate_text($detail_en, 700),
        'purpose_fil' => pharsayo_truncate_text($detail_fil, 700),
        'precautions_en' => 'Third-party listings can be wrong or outdated — verify with your pharmacist.',
        'precautions_fil' => 'Maaaring mali o luma ang datos — kumpirmahin sa pharmacist.',
        'frequency_hint' => 'Follow package or prescriber instructions.',
        'barcode_source' => 'barcode_upcitemdb',
    ];
    $base = pharsayo_barcode_row_append_references($base, [
        'UPCitemdb (public trial API): https://www.upcitemdb.com/upc/' . preg_replace('/\D+/', '', $digits),
    ]);
    return pharsayo_barcode_row_try_rxnav_enrich($base, false);
}

/**
 * Open *Facts text search by GTIN on a given host, then load full product JSON.
 *
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string}|null
 */
function pharsayo_lookup_barcode_openfacts_host_search($digits, $hostBase, $sourceKey, $refPrefix) {
    $digits = preg_replace('/\D+/', '', (string)$digits);
    if ($digits === '' || !preg_match('#^https://[a-z0-9.-]+$#i', $hostBase)) {
        return null;
    }
    $url = rtrim($hostBase, '/') . '/cgi/search.pl?search_terms=' . rawurlencode($digits)
        . '&search_simple=1&action=process&json=1&page_size=20';
    $json = pharsayo_http_get_json($url, 18);
    if ($json === null || empty($json['products']) || !is_array($json['products'])) {
        return null;
    }
    $variants = pharsayo_barcode_gtin_variants($digits);
    $tryCodes = [];
    foreach ($json['products'] as $p) {
        if (!is_array($p)) {
            continue;
        }
        $c = isset($p['code']) ? preg_replace('/\D+/', '', (string)$p['code']) : '';
        if ($c === '') {
            continue;
        }
        if (in_array($c, $variants, true)) {
            array_unshift($tryCodes, $c);
        } else {
            $tryCodes[] = $c;
        }
    }
    $tryCodes = array_values(array_unique($tryCodes));
    foreach ($tryCodes as $code) {
        $pj = pharsayo_http_get_json(rtrim($hostBase, '/') . '/api/v0/product/' . rawurlencode($code) . '.json', 12);
        if ($pj === null || (int)($pj['status'] ?? 0) !== 1 || empty($pj['product']) || !is_array($pj['product'])) {
            continue;
        }
        $row = pharsayo_openfacts_product_to_lookup_row($pj['product'], $sourceKey);
        if ($row !== null) {
            $row = pharsayo_barcode_row_append_references($row, [
                $refPrefix . ': ' . rtrim($hostBase, '/') . '/product/' . rawurlencode($code),
            ]);
            return pharsayo_barcode_row_try_rxnav_enrich($row, true);
        }
    }
    return null;
}

/**
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string}|null
 */
function pharsayo_lookup_barcode_openfoodfacts_search($digits) {
    return pharsayo_lookup_barcode_openfacts_host_search(
        $digits,
        'https://world.openfoodfacts.org',
        'openfoodfacts_search',
        'Open Food Facts product',
    );
}

/**
 * Prefer community product databases (often find GTINs UPCitemdb trial misses), then UPCitemdb.
 *
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string}|null
 */
function pharsayo_lookup_barcode_public_catalogs($raw, $digits) {
    $row = pharsayo_lookup_barcode_openfoodfacts_search($digits);
    if ($row !== null) {
        return $row;
    }
    $row = pharsayo_lookup_barcode_openfacts_host_search(
        $digits,
        'https://world.openproductsfacts.org',
        'openproductsfacts_search',
        'Open Products Facts product',
    );
    if ($row !== null) {
        return $row;
    }
    $row = pharsayo_lookup_barcode_barcodelist_com($digits);
    if ($row !== null) {
        return $row;
    }
    return pharsayo_lookup_barcode_upcitemdb($digits);
}

/**
 * Last resort: still return a card so the patient can save and discuss with a clinician.
 *
 * @param string $lang en|fil
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source:string}
 */
function pharsayo_barcode_placeholder_card($raw, $lang) {
    $raw = trim((string)$raw);
    $isEn = ($lang === 'en');
    $name = $isEn ? ('Product (barcode ' . $raw . ')') : ('Produkto (barcode ' . $raw . ')');
    $purpose_en = 'No public database returned a full match for this barcode. Check the product name and instructions printed on the package, or ask your pharmacist. You can still save this entry as a personal reminder.';
    $purpose_fil = 'Walang kumpletong tugma sa pampublikong database para sa barcode na ito. Tingnan ang pangalan at direksyon sa pakete, o magtanong sa pharmacist. Maaari pa ring i-save bilang personal na paalala.';
    $prec_en = 'Information in PharSayo is educational only — not a diagnosis or prescription.';
    $prec_fil = 'Ang impormasyon sa PharSayo ay para sa edukasyon lamang — hindi diagnosis o reseta.';

    return [
        'rxcui' => '',
        'display_name' => pharsayo_truncate_text($name, 220),
        'dosage_hint' => $isEn ? 'Use the label on your package.' : 'Gamitin ang nakasulat sa pakete.',
        'purpose_en' => pharsayo_truncate_text($purpose_en, 700),
        'purpose_fil' => pharsayo_truncate_text($purpose_fil, 700),
        'precautions_en' => pharsayo_truncate_text($prec_en, 500),
        'precautions_fil' => pharsayo_truncate_text($prec_fil, 500),
        'frequency_hint' => $isEn ? 'Set times with your doctor or caregiver.' : 'Itakda ang oras kasama ang doktor o tagapag-alaga.',
        'barcode_source' => 'barcode_placeholder',
    ];
}

/**
 * Try Open Products Facts specifically (medicines, supplements, personal care) — good for PH OTC products.
 *
 * @return array|null
 */
function pharsayo_lookup_openproductsfacts_direct($digits) {
    $codes = pharsayo_barcode_gtin_variants($digits);
    foreach ($codes as $code) {
        $url = 'https://world.openproductsfacts.org/api/v0/product/' . rawurlencode($code) . '.json';
        $json = pharsayo_http_get_json($url, 12);
        if ($json === null || empty($json['status']) || (int)$json['status'] !== 1) {
            continue;
        }
        if (empty($json['product']) || !is_array($json['product'])) {
            continue;
        }
        $row = pharsayo_openfacts_product_to_lookup_row($json['product'], 'openproductsfacts');
        if ($row !== null) {
            return pharsayo_barcode_row_try_rxnav_enrich($row, true);
        }
    }
    return null;
}

/**
 * Try the Open Food Facts Philippines mirror directly — best for local PH consumer products.
 *
 * @return array|null
 */
function pharsayo_lookup_openfoodfacts_ph_direct($digits) {
    $codes = pharsayo_barcode_gtin_variants($digits);
    foreach ($codes as $code) {
        $url = 'https://ph.openfoodfacts.org/api/v0/product/' . rawurlencode($code) . '.json';
        $json = pharsayo_http_get_json($url, 12);
        if ($json === null || empty($json['status']) || (int)$json['status'] !== 1) {
            continue;
        }
        if (empty($json['product']) || !is_array($json['product'])) {
            continue;
        }
        $row = pharsayo_openfacts_product_to_lookup_row($json['product'], 'openfoodfacts_ph');
        if ($row !== null) {
            return pharsayo_barcode_row_try_rxnav_enrich($row, true);
        }
    }
    return null;
}

/**
 * Resolve a scanned barcode / NDC / UPC digit string to the same structure as pharsayo_lookup_medication_online().
 * Always returns a row when $raw is non-empty (public web + placeholder as last resort).
 * Philippines-optimized lookup chain: local registry -> PH Open Food Facts -> Open Products Facts ->
 * RxNav/OpenFDA (NDC/UPC) -> global product catalogs -> Wikidata -> placeholder.
 *
 * @param string $lang en|fil
 * @param string $extractedText Optional OCR text from the same scan to refine the result
 * @return array{rxcui:string,display_name:string,dosage_hint:string,purpose_en:string,purpose_fil:string,precautions_en:string,precautions_fil:string,frequency_hint:string,barcode_source?:string}|null
 */
function pharsayo_lookup_medication_by_barcode($raw, $lang = 'fil', $extractedText = '') {
    $digits = preg_replace('/\\D+/', '', (string)$raw);
    if (trim((string)$raw) === '' && $digits === '') {
        return null;
    }

    // 1) Philippines-first: curated local GTINs (highest confidence, instant result)
    $phLocal = pharsayo_lookup_ph_gtin_registry($digits);
    if ($phLocal !== null) {
        return $phLocal;
    }

    // OCR text KB match (catches labelled PH products scanned with camera)
    if ($extractedText !== '') {
        $localKb = pharsayo_try_local_kb($extractedText);
        if ($localKb !== null) {
            $localKb['barcode_source'] = 'internal_db_ocr_match';
            if (!empty($digits)) {
                pharsayo_save_to_ph_gtin_registry($digits, $localKb);
            }
            return $localKb;
        }
    }

    // 2) Gemini AI - HIGH PRIORITY (The ultimate brain)
    // We try AI very early because it can combine barcode + OCR text hint.
    $aiMatch = pharsayo_gemini_medicine_lookup($digits, $extractedText);
    if ($aiMatch !== null) {
        $aiMatch['barcode_source'] = 'gemini_ai_lookup';
        // Auto-cache so future scans of same barcode are instant
        if (!empty($digits)) {
            pharsayo_save_to_ph_gtin_registry($digits, $aiMatch);
        }
        return $aiMatch;
    }

    // Helper to auto-cache results so future scans of same barcode are instant
    $returnAndCache = function($row) use ($digits) {
        if ($row !== null && !empty($digits)) {
            pharsayo_save_to_ph_gtin_registry($digits, $row);
        }
        return $row;
    };

    // 4) Philippines Open Food Facts mirror — best for locally-sold medicines & supplements
    $phFacts = pharsayo_lookup_openfoodfacts_ph_direct($digits);
    if ($phFacts !== null) {
        return $returnAndCache($phFacts);
    }

    // 4) Open Products Facts — global DB with medicines/OTC/supplements (often has PH GTINs)
    $opf = pharsayo_lookup_openproductsfacts_direct($digits);
    if ($opf !== null) {
        return $returnAndCache($opf);
    }

    // 5) Global Open *Facts (food/beauty/pet) — may still contain PH health products
    $open = pharsayo_lookup_medication_by_barcode_open_facts($digits);
    if ($open !== null) {
        return $returnAndCache($open);
    }

    // 6) RxNav NDC status lookup (US NDC codes; some PH imports have these)
    foreach (pharsayo_barcode_collect_ndc_candidates($raw) as $cand) {
        $st = pharsayo_rxnav_ndcstatus_lookup($cand);
        if ($st === null) {
            continue;
        }
        $fallback = $st['conceptName'] !== '' ? $st['conceptName'] : $cand;
        $info = pharsayo_lookup_medication_from_rxcui_barcode($st['rxcui'], $fallback);
        if ($info !== null) {
            return $returnAndCache($info);
        }
    }

    // 7) OpenFDA NDC directory by UPC (catches US-registered products with EAN/UPC barcodes)
    $allUpcVariants = pharsayo_barcode_collect_upc_candidates($digits);
    foreach ($allUpcVariants as $upc) {
        $ndcJson = pharsayo_openfda_ndc_directory_search('openfda.upc:"' . $upc . '"');
        $rxcui = pharsayo_openfda_ndc_json_first_rxcui($ndcJson);
        if ($rxcui !== null) {
            $info = pharsayo_lookup_medication_from_rxcui_barcode($rxcui, $upc);
            if ($info !== null) {
                return $returnAndCache($info);
            }
        }
    }

    // 8) OpenFDA NDC directory by NDC-formatted code
    foreach (pharsayo_barcode_collect_ndc_candidates($raw) as $cand) {
        if (strlen(preg_replace('/\\D+/', '', $cand)) < 8) {
            continue;
        }
        $searches = [
            'packaging.package_ndc:' . $cand,
            'product_ndc:' . $cand,
        ];
        foreach ($searches as $frag) {
            $ndcJson = pharsayo_openfda_ndc_directory_search($frag);
            $rxcui = pharsayo_openfda_ndc_json_first_rxcui($ndcJson);
            if ($rxcui === null) {
                continue;
            }
            $info = pharsayo_lookup_medication_from_rxcui_barcode($rxcui, $cand);
            if ($info !== null) {
                return $returnAndCache($info);
            }
            if (!empty($ndcJson['results'][0]) && is_array($ndcJson['results'][0])) {
                $row0 = $ndcJson['results'][0];
                $g = isset($row0['generic_name']) ? trim((string)$row0['generic_name']) : '';
                $b = isset($row0['brand_name']) ? trim((string)$row0['brand_name']) : '';
                $nameTry = $b !== '' ? $b : $g;
                if ($nameTry !== '') {
                    $hit = pharsayo_lookup_medication_online($nameTry, $nameTry . ' ' . $g);
                    if ($hit !== null) {
                        return $returnAndCache($hit);
                    }
                }
            }
        }
    }

    // 9) Public product catalogs: Open Food Facts search, barcode-list.com, UPCitemdb
    $web = pharsayo_lookup_barcode_public_catalogs($raw, $digits);
    if ($web !== null) {
        return $returnAndCache($web);
    }

    // 10) Wikidata GTIN lookup (community graph; sometimes has PH brand entries)
    $wd = pharsayo_lookup_barcode_wikidata($digits);
    if ($wd !== null) {
        return $returnAndCache(pharsayo_barcode_row_try_rxnav_enrich($wd, false));
    }

    // 11) Nothing found — informative placeholder so patient can still save the entry
    return pharsayo_lookup_barcode_web_fallback($raw, $lang);
}

/**
 * Fallback to Gemini AI if barcode lookup fails.
 * 
 * @param string $barcode The barcode digits
 * @param string $product_name_hint Optional OCR text hint
 * @return array|null The identified medicine data or null
 */
function pharsayo_gemini_medicine_lookup($barcode, $product_name_hint = '') {
    $api_key = 'AIzaSyCNijqrvhm4tsP-TP9XQk8K25VDZlAslzg'; // Free at aistudio.google.com
    if ($api_key === '' || strpos($api_key, 'YOUR_GEMINI') !== false) return null;

    $prompt = "You are a Philippine pharmacist assistant. ";
    $prompt .= "A user scanned a product with barcode: {$barcode}. ";
    if ($product_name_hint) {
        $prompt .= "The package text says: '{$product_name_hint}'. ";
    }
    $prompt .= "Identify this medicine if it is sold in the Philippines. ";
    $prompt .= "Return ONLY a JSON object with these fields: ";
    $prompt .= "display_name, dosage_hint, purpose_en, purpose_fil, precautions_en, precautions_fil, frequency_hint. ";
    $prompt .= "If you don't know this specific product, return null.";

    $body = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'temperature' => 0.1
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$api_key}";
    $result = pharsayo_http_post_json($url, $body);
    
    if (!$result) {
        error_log("PharSayo: Gemini API request failed (null result)");
        return null;
    }

    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$text) {
        error_log("PharSayo: Gemini API result structure missing text: " . json_encode($result));
        return null;
    }
    
    // Clean potential markdown blocks
    $text = trim($text);
    if (strpos($text, '```') === 0) {
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
    }
    
    $data = json_decode($text, true);
    if (is_array($data) && !empty($data['display_name'])) {
        // Ensure all fields exist
        $data['rxcui'] = $data['rxcui'] ?? '';
        $data['barcode_source'] = 'gemini_ai_lookup';
        return $data;
    }
    return null;
}

/**
 * Fallback to a "Web Search" simulation for barcodes not found in any standard DB.
 * It extracts potential name tokens from the raw OCR if it was a photo, or just uses the barcode.
 * 
 * @param string $raw The barcode number
 * @return array The medicine info row
 */
function pharsayo_lookup_barcode_web_fallback($raw, $lang = 'fil') {
    $isEn = ($lang === 'en');
    $code = preg_replace('/\D+/', '', (string)$raw);

    $msg_en = 'This product was not found in our curated list. Please refer to your physical package for the correct dosage, frequency, and purpose.';
    $msg_fil = 'Hindi nahanap ang produktong ito sa aming listahan. Mangyaring sumangguni sa iyong pakete para sa tamang dosis, dalas, at gamit ng gamot.';

    return [
        'rxcui' => '',
        'display_name' => $isEn ? "Medicine ($code)" : "Gamot ($code)",
        'dosage_hint' => $isEn ? 'Follow the instructions on your physical package or from your doctor.' : 'Sundin ang mga tagubilin sa iyong pakete o mula sa iyong doktor.',
        'purpose_en' => $msg_en,
        'purpose_fil' => $msg_fil,
        'precautions_en' => 'Always confirm with a licensed clinician before taking any medication.',
        'precautions_fil' => 'Laging kumonsulta sa doktor bago uminom ng anumang gamot.',
        'frequency_hint' => $isEn ? 'Follow package or prescriber instructions.' : 'Sundin ang pakete o direksyon ng doktor.',
        'barcode_source' => 'web_search_fallback',
    ];
}

/**
 * Try several RxCUIs (primary + related) and optional token search until labeling matches OCR intent.
 *
 * @param string $context_text Full OCR / label text used to build match needles (same scan).
 * @return array|null { rxcui, display_name, dosage_hint, purpose_en, purpose_fil, precautions_en, precautions_fil, frequency_hint }
 */
function pharsayo_lookup_medication_online($query_name, $context_text = '') {
    $query_name = trim($query_name);
    if (strlen($query_name) < 2) {
        return null;
    }
    $ctx = trim((string)$context_text) !== '' ? trim((string)$context_text) : $query_name;
    $needles = pharsayo_build_match_needles($query_name, $ctx);
    if (empty($needles)) {
        return null;
    }

    $seeds = [];
    $addSeed = function ($id, $hintName = '') use (&$seeds) {
        $id = (string)$id;
        if ($id === '') {
            return;
        }
        if (!isset($seeds[$id])) {
            $seeds[$id] = (string)$hintName;
        }
    };

    $rxUrl = 'https://rxnav.nlm.nih.gov/REST/drugs.json?name=' . rawurlencode($query_name);
    $rx = pharsayo_http_get_json($rxUrl);
    $concepts = pharsayo_rxnav_collect_concepts_from_drugs($rx ?: []);
    foreach (pharsayo_rxnav_pick_top_concepts($concepts, 5) as $c) {
        $addSeed($c['rxcui'], $c['name']);
    }

    $approxUrl = 'https://rxnav.nlm.nih.gov/REST/approximateTerm.json?name=' . rawurlencode($query_name) . '&maxEntries=8';
    $approx = pharsayo_http_get_json($approxUrl);
    if (!empty($approx['approximateGroup']['candidate']) && is_array($approx['approximateGroup']['candidate'])) {
        foreach ($approx['approximateGroup']['candidate'] as $cand) {
            if (!empty($cand['rxcui'])) {
                $addSeed((string)$cand['rxcui'], isset($cand['name']) ? (string)$cand['name'] : '');
            }
        }
    }

    if (empty($seeds)) {
        return null;
    }

    $bestRow = null;
    $matchedRxcui = '';
    $fallbackDisplay = $query_name;

    foreach ($seeds as $seedRxcui => $hintName) {
        $rxcuisToTry = [$seedRxcui];
        $relatedSorted = pharsayo_rxnav_related_concepts($seedRxcui);
        usort($relatedSorted, function ($a, $b) {
            return pharsayo_rxnav_concept_score($b) <=> pharsayo_rxnav_concept_score($a);
        });
        foreach ($relatedSorted as $rc) {
            if (!in_array($rc['rxcui'], $rxcuisToTry, true)) {
                $rxcuisToTry[] = $rc['rxcui'];
            }
            if (count($rxcuisToTry) >= 12) {
                break;
            }
        }

        foreach ($rxcuisToTry as $tryCui) {
            $fda = pharsayo_openfda_label_by_rxcui($tryCui, 8);
            $row = pharsayo_openfda_pick_best_matching_row($fda ?: [], $needles);
            if ($row !== null) {
                $bestRow = $row;
                $matchedRxcui = $tryCui;
                if ($hintName !== '') {
                    $fallbackDisplay = $hintName;
                }
                break 2;
            }
        }
    }

    if ($bestRow === null) {
        foreach (pharsayo_extract_search_tokens_from_query($ctx . ' ' . $query_name) as $tok) {
            $fda = pharsayo_openfda_label_search_token($tok, $needles);
            if ($fda === null) {
                continue;
            }
            $row = pharsayo_openfda_pick_best_matching_row($fda, $needles);
            if ($row !== null) {
                $bestRow = $row;
                $matchedRxcui = '';
                break;
            }
        }
    }

    if ($bestRow === null) {
        return null;
    }

    return pharsayo_build_lookup_result_from_openfda_row($bestRow, $fallbackDisplay, $matchedRxcui);
}
