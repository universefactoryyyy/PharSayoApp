<?php
// api/ocr/scan.php
include_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../lib/medication_lookup.php';
require_once __DIR__ . '/../lib/medication_kb.php';

$data = json_decode(file_get_contents('php://input'));
$barcodeRaw = isset($data->barcode) ? trim((string)$data->barcode) : '';
$extractedText = isset($data->extracted_text) ? trim($data->extracted_text) : '';
$clientCandidates = [];
if (!empty($data->candidate_names) && is_array($data->candidate_names)) {
    foreach ($data->candidate_names as $c) {
        $c = is_string($c) ? trim($c) : '';
        if ($c !== '') {
            $clientCandidates[] = $c;
        }
    }
    $clientCandidates = array_values(array_unique($clientCandidates));
}

$scanLang = isset($data->lang) && (string)$data->lang === 'en' ? 'en' : 'fil';

if ($barcodeRaw !== '') {
    $byCode = pharsayo_lookup_medication_by_barcode($barcodeRaw, $scanLang, $extractedText);
    if ($byCode === null) {
        $byCode = pharsayo_barcode_placeholder_card($barcodeRaw, $scanLang);
    }
    $dosage_line = $byCode['dosage_hint'];
    $responseSource = !empty($byCode['barcode_source']) ? (string)$byCode['barcode_source'] : 'barcode';
    if (isset($byCode['barcode_source'])) {
        unset($byCode['barcode_source']);
    }
    echo json_encode([
        'success' => true,
        'source' => $responseSource,
        'scan_kind' => 'barcode',
        'data' => [
            'name' => $byCode['display_name'],
            'dosage' => $dosage_line,
            'frequency' => $byCode['frequency_hint'],
            'purpose_en' => $byCode['purpose_en'],
            'purpose_fil' => $byCode['purpose_fil'],
            'precautions_en' => $byCode['precautions_en'],
            'precautions_fil' => $byCode['precautions_fil'],
            'rxcui' => isset($byCode['rxcui']) ? (string)$byCode['rxcui'] : '',
            'barcode' => $barcodeRaw,
            'references_en' => isset($byCode['references_en']) ? (string)$byCode['references_en'] : '',
            'references_fil' => isset($byCode['references_fil']) ? (string)$byCode['references_fil'] : '',
        ],
    ]);
    exit;
}

if ($extractedText === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Could not extract any text to search.',
    ]);
    exit;
}

function scan_is_garbage_line($t) {
    $t = trim($t);
    if (strlen($t) < 4) {
        return true;
    }
    if (preg_match('/^[0-9\s.,\-]+$/', $t)) {
        return true;
    }
    if (preg_match('/^[\=\+\|\*\_\-\s\.]{2,}$/', $t)) {
        return true;
    }
    $letters = preg_match_all('/[A-Za-z]/', $t, $m);
    if ($letters < 4) {
        return true;
    }
    $ratio = $letters / max(strlen($t), 1);
    return $ratio < 0.35;
}

function scan_extract_word_tokens($text) {
    preg_match_all('/[A-Za-z][A-Za-z\-]{3,}\b/', $text, $m);
    $stop = ['capsule', 'capsules', 'tablet', 'tablets', 'oral', 'each', 'with', 'and', 'the', 'for', 'per', 'mg', 'ml', 'mcg'];
    $out = [];
    foreach ($m[0] as $w) {
        $lw = strtolower($w);
        if (in_array($lw, $stop, true)) {
            continue;
        }
        $out[] = $w;
    }
    return array_values(array_unique($out));
}

function scan_ranked_name_candidates($extractedText) {
    $lines = preg_split('/\R/', $extractedText);
    $scored = [];
    foreach ($lines as $line) {
        $t = trim($line);
        if (scan_is_garbage_line($t)) {
            continue;
        }
        $letters = preg_match_all('/[A-Za-z]/', $t, $mm);
        $ratio = $letters / max(strlen($t), 1);
        $score = $ratio * 20 + min(strlen($t), 48) * 0.25;
        if (preg_match('/^[A-Z][a-z]{2,}/', $t)) {
            $score += 18;
        }
        $weird = preg_match_all('/[^A-Za-z0-9\s.,\-]/', $t);
        $score -= $weird * 10;
        $scored[] = ['t' => $t, 's' => $score];
    }
    usort($scored, function ($a, $b) {
        return $b['s'] <=> $a['s'];
    });
    $fromLines = array_map(function ($x) {
        return $x['t'];
    }, $scored);
    $words = scan_extract_word_tokens($extractedText);
    $merged = array_merge($words, $fromLines);
    $seen = [];
    $uniq = [];
    foreach ($merged as $c) {
        $k = strtolower(trim($c));
        if ($k === '' || strlen($k) < 4) {
            continue;
        }
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $uniq[] = trim($c);
    }
    return $uniq;
}

function scan_guess_name_from_text($extractedText) {
    $c = scan_ranked_name_candidates($extractedText);
    if (!empty($c)) {
        return $c[0];
    }
    return 'Unknown Medicine';
}

function scan_guess_dosage_from_text($extractedText) {
    if (preg_match('/(\d+\s*(mg|ml|g|mcg))\b/i', $extractedText, $matches)) {
        return $matches[1];
    }
    return '';
}

/**
 * Prefer candidate strings that actually appear in the OCR text (reduces wrong-drug matches).
 */
function scan_prioritize_candidates($extractedText, $candidates) {
    $lower = strtolower($extractedText);
    $scored = [];
    foreach ($candidates as $c) {
        $c = trim((string)$c);
        if ($c === '') {
            continue;
        }
        $lc = strtolower($c);
        $score = 0.0;
        if ($lc !== '' && strpos($lower, $lc) !== false) {
            $score += 200 + min(strlen($c), 60);
        }
        $score += min(strlen($c), 80) * 0.3;
        $scored[] = ['c' => $c, 's' => $score];
    }
    usort($scored, function ($a, $b) {
        return $b['s'] <=> $a['s'];
    });
    $out = [];
    foreach ($scored as $x) {
        $out[] = $x['c'];
    }
    return array_values(array_unique($out));
}

// 1) Internet / open drug APIs first (RxNav + OpenFDA via medication_lookup.php)
$serverRanked = scan_ranked_name_candidates($extractedText);
$candidates = [];
foreach ($clientCandidates as $c) {
    $candidates[] = $c;
}
foreach ($serverRanked as $c) {
    $candidates[] = $c;
}
$candidates = array_values(array_unique($candidates));
if (empty($candidates)) {
    $candidates = [scan_guess_name_from_text($extractedText)];
}
$candidates = scan_prioritize_candidates($extractedText, $candidates);

$online = null;
foreach (array_slice($candidates, 0, 14) as $cand) {
    $online = pharsayo_lookup_medication_online($cand, $extractedText);
    if ($online) {
        break;
    }
}

if ($online) {
    $dosage_line = $online['dosage_hint'];
    if ($dosage_line === 'As prescribed by your physician'
        || strpos($dosage_line, 'Sundin ang reseta ng doktor') === 0) {
        $from_label = scan_guess_dosage_from_text($extractedText);
        if ($from_label !== '') {
            $dosage_line = $from_label;
        }
    }

    echo json_encode([
        'success' => true,
        'source' => 'open_data',
        'data' => [
            'name' => $online['display_name'],
            'dosage' => $dosage_line,
            'frequency' => $online['frequency_hint'],
            'purpose_en' => $online['purpose_en'],
            'purpose_fil' => $online['purpose_fil'],
            'precautions_en' => $online['precautions_en'],
            'precautions_fil' => $online['precautions_fil'],
            'rxcui' => $online['rxcui'],
            'references_en' => isset($online['references_en']) ? (string)$online['references_en'] : '',
            'references_fil' => isset($online['references_fil']) ? (string)$online['references_fil'] : '',
        ],
    ]);
    exit;
}

// 2) Local KB if online did not resolve (offline or unknown on public APIs)
$localHit = pharsayo_try_local_kb($extractedText);
if ($localHit !== null) {
    echo json_encode([
        'success' => true,
        'source' => 'internal_db',
        'data' => $localHit,
    ]);
    exit;
}

// 3) Heuristic fallback
$guessed_name = scan_guess_name_from_text($extractedText);
$dosage = scan_guess_dosage_from_text($extractedText);
if ($dosage === '') {
    $dosage = 'As prescribed';
}

echo json_encode([
    'success' => true,
    'source' => 'heuristic',
    'data' => [
        'name' => $guessed_name,
        'dosage' => $dosage,
        'frequency' => 'Follow your prescription label',
        'purpose_en' => 'Could not retrieve verified labeling automatically. Please confirm with your doctor or pharmacist.',
        'purpose_fil' => 'Hindi makumpirma ang detalye mula sa internet. Pakikumpirma sa doktor o pharmacist.',
        'precautions_en' => 'Do not rely on this screen alone for dosing or safety. Read your package insert.',
        'precautions_fil' => 'Huwag umasa lamang sa app na ito. Basahin ang label at kumonsulta sa propesyonal.',
        'is_dynamic' => true,
    ],
]);
exit;
