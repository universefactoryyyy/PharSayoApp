<?php
include_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../lib/medication_lookup.php';
require_once __DIR__ . '/../lib/medication_kb.php';

// ── Debug logger — writes to /tmp/pharsayo_chat.log on your server ──────────
function pharsayo_debug($tag, $val) {
    $line = '[' . date('H:i:s') . '] [' . $tag . '] ' . (is_string($val) ? $val : json_encode($val)) . "\n";
    @file_put_contents('/tmp/pharsayo_chat.log', $line, FILE_APPEND);
}

// ── Own cURL wrapper — bypasses whatever pharsayo_http_post_json does ────────
function pharsayo_curl_post_json($url, $payload, $timeout = 20) {
    if (!function_exists('curl_init')) {
        pharsayo_debug('curl_post', 'curl not available');
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $raw === '') {
        pharsayo_debug('curl_post_err', "HTTP $httpCode cURL: $err url=$url");
        return null;
    }
    pharsayo_debug('curl_post_ok', "HTTP $httpCode url=$url resp_len=" . strlen($raw));
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        pharsayo_debug('curl_post_json_err', substr($raw, 0, 300));
        return null;
    }
    return $decoded;
}

function pharsayo_curl_get_text($url, $timeout = 14) {
    if (!function_exists('curl_init')) return '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (compatible; PharsayoBot/1.0)',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $raw === '') {
        pharsayo_debug('curl_get_err', "HTTP $code cURL: $err url=$url");
        return '';
    }
    pharsayo_debug('curl_get_ok', "HTTP $code len=" . strlen($raw) . " url=$url");
    return (string)$raw;
}

// ── Input parsing ─────────────────────────────────────────────────────────────
$body    = json_decode(file_get_contents('php://input'));
$message = isset($body->message) ? trim((string)$body->message) : '';
$lang    = (isset($body->lang) && (string)$body->lang === 'en') ? 'en' : 'fil';
$messages = [];
if (!empty($body->messages) && is_array($body->messages)) {
    foreach ($body->messages as $m) {
        if (!is_object($m)) continue;
        $role    = isset($m->role)    ? trim((string)$m->role)    : '';
        $content = isset($m->content) ? trim((string)$m->content) : '';
        if ($content === '' || ($role !== 'user' && $role !== 'bot')) continue;
        if (strlen($content) > 1400) $content = substr($content, 0, 1400);
        $messages[] = ['role' => $role, 'content' => $content];
        if (count($messages) >= 12) break;
    }
}

pharsayo_debug('request', "lang=$lang msg=" . substr($message, 0, 120));

if ($message === '') {
    echo json_encode(['success' => false, 'message' => $lang === 'en'
        ? 'Please type a medicine name or question.'
        : 'Mag-type ng pangalan ng gamot o tanong.']);
    exit;
}
if (strlen($message) > 500) $message = substr($message, 0, 500);

// ── Helpers ───────────────────────────────────────────────────────────────────
function pharsayo_chat_truncate($text, $max) {
    $text = trim((string)$text);
    if (strlen($text) <= $max) return $text;
    return substr($text, 0, $max) . '…';
}

function pharsayo_chat_reminder($lang) {
    return $lang === 'en'
        ? "Reminder: You must still adhere in accordance to your doctor's prescription and guidelines."
        : "Paalala: Dapat mo pa ring sundin ang reseta at mga gabay ng iyong doktor.";
}

function pharsayo_chat_append_reminder($text, $lang) {
    $text = trim((string)$text);
    $r    = pharsayo_chat_reminder($lang);
    if ($text === '') return $r;
    if (stripos($text, 'Reminder:') !== false || stripos($text, 'Paalala:') !== false) return $text;
    return $text . "\n\n" . $r;
}

function pharsayo_chat_is_question($msg) {
    $msg = trim((string)$msg);
    if ($msg === '') return false;
    if (strpos($msg, '?') !== false) return true;
    return (bool)preg_match(
        '/^(can|could|should|is|are|what|when|where|why|how|does|do|pwede|pede|maaari|ano|bakit|paano|kailan|saan)\b/i',
        $msg
    );
}

function pharsayo_chat_extract_drug_name($msg) {
    // Only extract a shorter name for simple "what is X for?" patterns
    if (preg_match('/^what\s+is\s+(.+?)\s+for\??$/i', $msg, $m)) return trim($m[1]);
    if (preg_match('/^para\s+saan\s+ang\s+(.+?)\??$/i', $msg, $m)) return trim($m[1]);
    if (preg_match('/^ano\s+ang\s+(.+?)\??$/i',         $msg, $m)) return trim($m[1]);
    return $msg; // return full message for everything else
}

function pharsayo_chat_normalize($text) {
    return preg_replace('/[^a-z0-9]/', '', strtolower((string)$text));
}

// Strict exact-match local KB (no false substring hits)
function pharsayo_chat_local_kb($query) {
    if (!function_exists('pharsayo_local_medication_kb')) return null;
    $qNorm = pharsayo_chat_normalize($query);
    if (strlen($qNorm) < 3) return null;
    $db = pharsayo_local_medication_kb();
    foreach ($db as $id => $row) {
        $idN   = pharsayo_chat_normalize((string)$id);
        $nameN = pharsayo_chat_normalize((string)($row['name'] ?? ''));
        if ($qNorm === $idN || $qNorm === $nameN) return $row;
    }
    return null;
}

function pharsayo_chat_wikipedia($query) {
    $q = trim((string)$query);
    if ($q === '') return null;
    $raw = pharsayo_curl_get_text('https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode(str_replace(' ', '_', $q)));
    if ($raw === '') {
        // try search first
        $searchRaw = pharsayo_curl_get_text('https://en.wikipedia.org/w/api.php?action=query&list=search&format=json&utf8=1&srlimit=1&srsearch=' . rawurlencode($q));
        if ($searchRaw !== '') {
            $s = json_decode($searchRaw, true);
            $title = $s['query']['search'][0]['title'] ?? '';
            if ($title !== '') {
                $raw = pharsayo_curl_get_text('https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode(str_replace(' ', '_', $title)));
            }
        }
    }
    if ($raw === '') return null;
    $d = json_decode($raw, true);
    if (empty($d['extract'])) return null;
    return ['extract' => (string)$d['extract'], 'url' => (string)($d['content_urls']['desktop']['page'] ?? '')];
}

function pharsayo_chat_grounding_sources($result) {
    $out = [];
    foreach (($result['candidates'] ?? []) as $cand) {
        foreach (($cand['groundingMetadata']['groundingChunks'] ?? []) as $chunk) {
            $uri   = trim((string)($chunk['web']['uri']   ?? ''));
            $title = trim((string)($chunk['web']['title'] ?? ''));
            if ($uri === '' || isset($out[$uri])) continue;
            $out[$uri] = ['title' => $title ?: 'Web source', 'url' => $uri];
            if (count($out) >= 8) break 2;
        }
    }
    return array_values($out);
}

// ── Core drug name / query ────────────────────────────────────────────────────
$query      = pharsayo_chat_truncate(pharsayo_chat_extract_drug_name($message), 120);
$isQuestion = pharsayo_chat_is_question($message);
$googleLink = 'https://www.google.com/search?q=' . rawurlencode($query);

$baseSources = [
    ['title' => 'RxNav',                    'url' => 'https://rxnav.nlm.nih.gov/REST/drugs.json?name=' . rawurlencode($query)],
    ['title' => 'openFDA (label search)',   'url' => 'https://api.fda.gov/drug/label.json?search=' . rawurlencode($query) . '&limit=1'],
    ['title' => 'Google search: ' . $query, 'url' => $googleLink],
];

// ── STEP 1: Gemini 2.0 Flash with Google Search grounding ────────────────────
$api_key = trim((string)getenv('GEMINI_API_KEY'));
if ($api_key === '') $api_key = 'AIzaSyCNijqrvhm4tsP-TP9XQk8K25VDZlAslzg';
pharsayo_debug('api_key_set', $api_key !== '' ? 'yes (len=' . strlen($api_key) . ')' : 'NO');

$instruction = $lang === 'en'
    ? "You are a careful pharmacist assistant. Answer the user's exact question or drug name lookup directly and accurately. If they typed a drug name (like 'ascorbic acid'), explain what it is, what it is used for, and any key warnings. If they asked a question about a drug, answer that specific question. Be concise (3–6 sentences). Do NOT mention a different drug. Do NOT tell the user to check Google — you ARE their search. End every reply with: Reminder: You must still adhere in accordance to your doctor's prescription and guidelines."
    : "Ikaw ay maingat na pharmacist assistant. Sagutin ang eksaktong tanong o drug name ng user nang direkta at tama. Kung nag-type sila ng pangalan ng gamot (tulad ng 'ascorbic acid'), ipaliwanag kung ano ito, para saan, at anong mahahalagang babala. Kung nagtanong sila tungkol sa gamot, sagutin ang tanong na iyon. Maging maikli (3–6 pangungusap). HUWAG banggitin ang ibang gamot. HUWAG sabihin na mag-Google — ikaw ang kanilang search. Tapusin ang bawat sagot ng: Paalala: Dapat mo pa ring sundin ang reseta at mga gabay ng iyong doktor.";

$contents = [['role' => 'user', 'parts' => [['text' => $instruction]]]];
foreach ($messages as $hm) {
    $contents[] = ['role' => $hm['role'] === 'bot' ? 'model' : 'user', 'parts' => [['text' => (string)$hm['content']]]];
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

$geminiPayload = [
    'contents'         => $contents,
    'tools'            => [['google_search' => (object)[]]],
    'generationConfig' => ['temperature' => 0.15, 'maxOutputTokens' => 700],
];

$geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$api_key}";

// Try our own cURL first, then fall back to whatever the app provides
$result = pharsayo_curl_post_json($geminiUrl, $geminiPayload, 22);
if (!is_array($result) && function_exists('pharsayo_http_post_json')) {
    pharsayo_debug('gemini_fallback', 'trying pharsayo_http_post_json');
    $result = pharsayo_http_post_json($geminiUrl, $geminiPayload);
}

pharsayo_debug('gemini_result', is_array($result) ? 'array keys=' . implode(',', array_keys($result)) : gettype($result));

$geminiText = '';
if (is_array($result) && !empty($result['candidates'][0]['content']['parts'][0]['text'])) {
    $geminiText = trim((string)$result['candidates'][0]['content']['parts'][0]['text']);
    $geminiText = trim(preg_replace('/^```(?:text)?\s*|\s*```$/i', '', $geminiText));
    pharsayo_debug('gemini_text', substr($geminiText, 0, 120));
} elseif (is_array($result) && isset($result['error'])) {
    pharsayo_debug('gemini_api_error', json_encode($result['error']));
}

if ($geminiText !== '') {
    $text       = pharsayo_chat_append_reminder($geminiText, $lang);
    $gSources   = pharsayo_chat_grounding_sources($result);
    $gSources[] = ['title' => 'Google search: ' . $query, 'url' => $googleLink];
    $out = []; $seen = [];
    foreach (array_merge($gSources, $baseSources) as $s) {
        $u = trim((string)($s['url'] ?? ''));
        if ($u === '' || isset($seen[$u])) continue;
        $seen[$u] = true;
        $out[] = ['title' => trim((string)($s['title'] ?? '')) ?: 'Source', 'url' => $u];
        if (count($out) >= 10) break;
    }
    echo json_encode(['success' => true, 'source' => 'gemini', 'reply' => $text, 'sources' => $out]);
    exit;
}

// ── STEP 2: Wikipedia (reliable, always has ascorbic acid etc.) ───────────────
pharsayo_debug('step', 'gemini failed, trying wikipedia');
$wiki = pharsayo_chat_wikipedia($query);
if (is_array($wiki) && trim((string)($wiki['extract'] ?? '')) !== '') {
    $extract = pharsayo_chat_truncate((string)$wiki['extract'], 520);
    $reply   = pharsayo_chat_append_reminder($extract, $lang);
    $sources = $baseSources;
    if (!empty($wiki['url'])) {
        array_unshift($sources, ['title' => 'Wikipedia: ' . $query, 'url' => $wiki['url']]);
    }
    echo json_encode(['success' => true, 'source' => 'wikipedia', 'reply' => $reply, 'sources' => $sources]);
    exit;
}

// ── STEP 3: Online drug lookup ────────────────────────────────────────────────
pharsayo_debug('step', 'wikipedia failed, trying online lookup');
if (!$isQuestion && function_exists('pharsayo_lookup_medication_online')) {
    $online = pharsayo_lookup_medication_online($query, $query);
    pharsayo_debug('online_lookup', is_array($online) ? 'found' : 'null');
    if (is_array($online)) {
        $name     = trim((string)($online['display_name']   ?? $query));
        $purpose  = trim((string)($online['purpose_en']     ?? ''));
        $warnings = trim((string)($online['precautions_en'] ?? ''));
        $rxcui    = trim((string)($online['rxcui']          ?? ''));
        $out = $name . ': ' . ($purpose !== '' ? pharsayo_chat_truncate($purpose, 400) : 'See linked references.');
        if ($warnings !== '') $out .= "\n\nImportant warnings: " . pharsayo_chat_truncate($warnings, 260);
        $sources = [
            ['title' => 'RxNav', 'url' => 'https://rxnav.nlm.nih.gov/REST/drugs.json?name=' . rawurlencode($query)],
            ['title' => 'openFDA (label search)', 'url' => $rxcui !== ''
                ? 'https://api.fda.gov/drug/label.json?search=openfda.rxcui%3A%22' . rawurlencode($rxcui) . '%22&limit=1'
                : 'https://api.fda.gov/drug/label.json?search=' . rawurlencode($query) . '&limit=1'],
            ['title' => 'Google search: ' . $query, 'url' => $googleLink],
        ];
        echo json_encode(['success' => true, 'source' => 'online_lookup', 'reply' => pharsayo_chat_append_reminder($out, $lang), 'sources' => $sources]);
        exit;
    }
}

// ── STEP 4: Local KB (exact match only) ───────────────────────────────────────
pharsayo_debug('step', 'trying local KB');
$local = pharsayo_chat_local_kb($query);
pharsayo_debug('local_kb', is_array($local) ? 'found: ' . ($local['name'] ?? '?') : 'null');
if (is_array($local)) {
    $name     = trim((string)($local['name']          ?? $query));
    $purpose  = trim((string)($local['purpose_en']    ?? ''));
    $warnings = trim((string)($local['precautions_en'] ?? ''));
    $out = $name . ': ' . ($purpose !== '' ? pharsayo_chat_truncate($purpose, 380) : 'Information found in local references.');
    if ($warnings !== '') $out .= "\n\nImportant warnings: " . pharsayo_chat_truncate($warnings, 240);
    echo json_encode(['success' => true, 'source' => 'local_kb', 'reply' => pharsayo_chat_append_reminder($out, $lang), 'sources' => $baseSources]);
    exit;
}

// ── STEP 5: Hardcoded common drugs — guaranteed never empty ───────────────────
pharsayo_debug('step', 'using hardcoded fallback');
$hardcoded = [
    'ascorbic acid'  => ['Ascorbic acid (Vitamin C) is an essential nutrient used to prevent and treat Vitamin C deficiency (scurvy). It also supports immune function, skin health, and wound healing. Common forms include tablets, capsules, and effervescent drinks. Typical adult dose is 500 mg–1,000 mg daily. High doses (>2,000 mg/day) may cause stomach upset or diarrhea.', 'Do not exceed recommended daily intake unless directed by a doctor.'],
    'paracetamol'    => ['Paracetamol (acetaminophen) is used to relieve mild-to-moderate pain and reduce fever. It is one of the most commonly used over-the-counter medicines for headaches, toothaches, colds, and flu. Adult dose is usually 500 mg–1,000 mg every 4–6 hours.', 'Do not exceed 4,000 mg in 24 hours. Avoid alcohol. Overdose can cause severe liver damage.'],
    'ibuprofen'      => ['Ibuprofen is a nonsteroidal anti-inflammatory drug (NSAID) used for pain, fever, and inflammation — including headaches, muscle aches, menstrual cramps, and arthritis. Typical adult dose is 200 mg–400 mg every 4–6 hours.', 'Take with food to reduce stomach irritation. Avoid if you have kidney problems or peptic ulcer. Do not exceed 1,200 mg per day without medical advice.'],
    'amoxicillin'    => ['Amoxicillin is a penicillin-type antibiotic used to treat bacterial infections such as ear infections, chest infections, urinary tract infections, and dental abscesses. It must be prescribed by a doctor.', 'Complete the full course even if you feel better. Do not use if allergic to penicillin. May cause diarrhea or rash.'],
    'kremil s'       => ['Kremil-S is an antacid/antiflatulent used to relieve hyperacidity, heartburn, stomach pain, and gas. It contains aluminum hydroxide, magnesium hydroxide, and simethicone.', 'Do not exceed 8 tablets in 24 hours. Do not take for more than 2 weeks without medical advice.'],
    'kremil-s'       => ['Kremil-S is an antacid/antiflatulent used to relieve hyperacidity, heartburn, stomach pain, and gas. It contains aluminum hydroxide, magnesium hydroxide, and simethicone.', 'Do not exceed 8 tablets in 24 hours. Do not take for more than 2 weeks without medical advice.'],
    'mefenamic acid' => ['Mefenamic acid is an NSAID used to relieve mild-to-moderate pain, including menstrual pain, dental pain, and headaches. It is also used to reduce fever.', 'Take with food. Do not use for more than 7 days unless directed by a doctor. Avoid in kidney or liver disease.'],
    'cetirizine'     => ['Cetirizine is an antihistamine used to relieve allergy symptoms such as runny nose, sneezing, itchy eyes, and skin rashes (urticaria). It is a non-drowsy antihistamine for most people.', 'May cause mild drowsiness in some users. Avoid alcohol. Typical adult dose is 10 mg once daily.'],
    'loperamide'     => ['Loperamide is used to treat diarrhea. It slows down intestinal movement to reduce the frequency of loose stools.', 'Do not use if diarrhea is caused by infection (bloody stool or high fever). Seek medical attention if diarrhea lasts more than 2 days.'],
    'omeprazole'     => ['Omeprazole is a proton pump inhibitor (PPI) used to reduce stomach acid. It treats acid reflux (GERD), peptic ulcers, and heartburn.', 'Usually taken 30 minutes before a meal. Long-term use should be supervised by a doctor. May reduce magnesium levels.'],
    'metformin'      => ['Metformin is an oral diabetes medicine used to lower blood sugar in type 2 diabetes. It works by decreasing glucose production in the liver.', 'Take with food to reduce stomach side effects. Do not use if you have kidney problems. Inform your doctor before any surgery or imaging with contrast dye.'],
    'losartan'       => ['Losartan is an angiotensin receptor blocker (ARB) used to treat high blood pressure (hypertension) and to protect the kidneys in diabetic patients.', 'Do not take if pregnant. Monitor blood pressure regularly. May cause dizziness, especially when standing up quickly.'],
    'amlodipine'     => ['Amlodipine is a calcium channel blocker used to treat high blood pressure and chest pain (angina). It helps relax and widen blood vessels.', 'May cause ankle swelling or flushing. Do not stop taking suddenly without consulting your doctor.'],
    'atorvastatin'   => ['Atorvastatin is a statin medicine used to lower cholesterol and reduce the risk of heart disease and stroke.', 'Take at any time of day. Avoid grapefruit juice. Report any unexplained muscle pain or weakness to your doctor immediately.'],
    'salbutamol'     => ['Salbutamol (albuterol) is a bronchodilator used to relieve and prevent bronchospasm in asthma and COPD. It is commonly given via inhaler.', 'Shake the inhaler before use. If symptoms worsen or you need to use it more than usual, consult your doctor immediately.'],
];

$qLow = strtolower(trim($query));
// Try exact match first, then partial for hardcoded list
$hcMatch = null;
foreach ($hardcoded as $k => $v) {
    if ($qLow === $k) { $hcMatch = $v; break; }
}
if ($hcMatch === null) {
    foreach ($hardcoded as $k => $v) {
        if (strpos($qLow, $k) !== false || strpos($k, $qLow) !== false) {
            $hcMatch = $v; break;
        }
    }
}

if ($hcMatch !== null) {
    $out = $hcMatch[0] . "\n\nImportant warnings: " . $hcMatch[1];
    echo json_encode(['success' => true, 'source' => 'hardcoded', 'reply' => pharsayo_chat_append_reminder($out, $lang), 'sources' => $baseSources]);
    exit;
}

// ── STEP 6: Absolute last resort with a useful message ───────────────────────
pharsayo_debug('step', 'all sources failed for: ' . $query);
$out = $lang === 'en'
    ? "I wasn't able to retrieve live information for \"$query\" right now (the AI service may be temporarily unavailable). Based on general knowledge: please search for \"$query\" on Medscape, WebMD, or ask your local pharmacist for accurate guidance."
    : "Hindi ko makuha ang live na impormasyon para sa \"$query\" ngayon (maaaring may pansamantalang problema ang AI service). Mangyaring maghanap ng \"$query\" sa Medscape o WebMD, o magtanong sa iyong parmaseutiko.";
echo json_encode(['success' => true, 'source' => 'none', 'reply' => pharsayo_chat_append_reminder($out, $lang), 'sources' => $baseSources]);
exit;