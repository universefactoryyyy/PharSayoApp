<?php
include_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../lib/medication_lookup.php';
require_once __DIR__ . '/../lib/medication_kb.php';

// ── Debug logger ──────────────────────────────────────────────────────────────
function pharsayo_debug($tag, $val) {
    $line = '[' . date('H:i:s') . '] [' . $tag . '] ' . (is_string($val) ? $val : json_encode($val)) . "\n";
    @file_put_contents('/tmp/pharsayo_chat.log', $line, FILE_APPEND);
}

// ── cURL POST JSON (supports custom headers) ──────────────────────────────────
function pharsayo_curl_post_json($url, $payload, $timeout = 25, $extra_headers = []) {
    if (!function_exists('curl_init')) {
        pharsayo_debug('curl_post', 'curl not available');
        return null;
    }
    $ch = curl_init($url);
    $headers = array_merge(['Content-Type: application/json'], $extra_headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw      = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $raw === '') {
        pharsayo_debug('curl_post_err', "HTTP $httpCode cURL: $err");
        return null;
    }
    pharsayo_debug('curl_post_ok', "HTTP $httpCode len=" . strlen($raw));
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
$body     = json_decode(file_get_contents('php://input'));
$message  = isset($body->message) ? trim((string)$body->message) : '';
$lang     = (isset($body->lang) && (string)$body->lang === 'en') ? 'en' : 'fil';
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
        '/^(can|could|should|is|are|what|when|where|why|how|does|do|pwede|pede|maaari|ano|bakit|paano|kailan|saan|medicine|gamot|treatment|lunas)\b/i',
        $msg
    );
}

function pharsayo_chat_extract_drug_name($msg) {
    if (preg_match('/^what\s+is\s+(.+?)\s+for\??$/i', $msg, $m)) return trim($m[1]);
    if (preg_match('/^para\s+saan\s+ang\s+(.+?)\??$/i', $msg, $m)) return trim($m[1]);
    if (preg_match('/^ano\s+ang\s+(.+?)\??$/i',         $msg, $m)) return trim($m[1]);
    return $msg;
}

function pharsayo_chat_normalize($text) {
    return preg_replace('/[^a-z0-9]/', '', strtolower((string)$text));
}

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
        $searchRaw = pharsayo_curl_get_text('https://en.wikipedia.org/w/api.php?action=query&list=search&format=json&utf8=1&srlimit=1&srsearch=' . rawurlencode($q));
        if ($searchRaw !== '') {
            $s     = json_decode($searchRaw, true);
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

// ── Core query ────────────────────────────────────────────────────────────────
$query      = pharsayo_chat_truncate(pharsayo_chat_extract_drug_name($message), 120);
$isQuestion = pharsayo_chat_is_question($message);
$googleLink = 'https://www.google.com/search?q=' . rawurlencode($query . ' medicine drug');

$baseSources = [
    ['title' => 'RxNav',                     'url' => 'https://rxnav.nlm.nih.gov/REST/drugs.json?name=' . rawurlencode($query)],
    ['title' => 'openFDA (label search)',    'url' => 'https://api.fda.gov/drug/label.json?search=' . rawurlencode($query) . '&limit=1'],
    ['title' => 'Google search: ' . $query,  'url' => $googleLink],
];

// ── API Keys ──────────────────────────────────────────────────────────────────
// Set these as environment variables on your server:
//   GEMINI_API_KEY   — from Google AI Studio (aistudio.google.com)
//   GOOGLE_CSE_KEY   — from Google Cloud Console (Custom Search API key)
//   GOOGLE_CSE_CX    — your Custom Search Engine ID (programmablesearchengine.google.com)
//
// Alternatively, hard-code them below (not recommended for production):
$gemini_key = trim((string)getenv('GEMINI_API_KEY'));
$gemini_key = 'AIzaSyCNijqrvhm4tsP-TP9XQk8K25VDZlAslzg';  // fallback hard-code

$google_cse_key  = trim((string)getenv('GOOGLE_CSE_KEY'));
$google_cse_key = 'AIzaSyDc-p_35EnBYiIINavD8jkvpUurPHjNPCw';

$google_cse_cx  = trim((string)getenv('GOOGLE_CSE_CX'));
$google_cse_cx = 'd090cedb79fe64135';

pharsayo_debug('keys', "gemini=" . ($gemini_key !== '' ? 'set' : 'MISSING') . " cse=" . ($google_cse_key !== '' ? 'set' : 'MISSING'));

// ── STEP 1: Gemini 2.0 Flash with Google Search grounding ────────────────────
if ($gemini_key !== '') {
    $instruction = $lang === 'en'
        ? "You are a careful pharmacist assistant for Filipino users. Answer EXACTLY what the user asked — including general questions like 'medicine for toothache', 'what helps with fever', 'gamot sa ubo'. Use Google Search to find accurate, current information. List specific medicine names with dosage and key warnings. Be concise (4–7 sentences). Never say 'check Google' — YOU are the search. End every reply with exactly: Reminder: You must still adhere in accordance to your doctor's prescription and guidelines."
        : "Ikaw ay maingat na pharmacist assistant para sa mga Pilipinong users. Sagutin nang EKSAKTO ang tinanong ng user — kasama ang mga pangkalahatang tanong tulad ng 'gamot sa ngipin', 'gamot sa lagnat', 'medicine for toothache'. Gamitin ang Google Search para mahanap ang tumpak at updated na impormasyon. Ibigay ang mga specific na pangalan ng gamot, dosage, at mahahalagang babala. Maging maikli (4–7 pangungusap). Tapusin ang bawat sagot ng: Paalala: Dapat mo pa ring sundin ang reseta at mga gabay ng iyong doktor.";

    // Build conversation history for Gemini
    $contents = [];
    foreach ($messages as $hm) {
        $contents[] = [
            'role'  => $hm['role'] === 'bot' ? 'model' : 'user',
            'parts' => [['text' => (string)$hm['content']]],
        ];
    }
    // Inject system instruction as first user turn if no history
    if (empty($contents)) {
        $contents[] = ['role' => 'user', 'parts' => [['text' => $instruction . "\n\nUser question: " . $message]]];
    } else {
        // Prepend instruction to the latest user message
        $last = count($contents) - 1;
        if ($contents[$last]['role'] === 'user') {
            $contents[$last]['parts'][0]['text'] = $instruction . "\n\nUser question: " . $contents[$last]['parts'][0]['text'];
        } else {
            $contents[] = ['role' => 'user', 'parts' => [['text' => $instruction . "\n\nUser question: " . $message]]];
        }
    }

    $geminiPayload = [
        'contents'         => $contents,
        'tools'            => [['google_search' => (object)[]]],
        'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 800],
    ];

    $geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$gemini_key}";
    $result    = pharsayo_curl_post_json($geminiUrl, $geminiPayload, 25);

    // Also try legacy helper if our cURL fails
    if (!is_array($result) && function_exists('pharsayo_http_post_json')) {
        pharsayo_debug('gemini_fallback', 'trying pharsayo_http_post_json');
        $result = pharsayo_http_post_json($geminiUrl, $geminiPayload);
    }

    pharsayo_debug('gemini_result', is_array($result) ? 'ok keys=' . implode(',', array_keys($result)) : gettype($result));

    $geminiText = '';
    if (is_array($result) && !empty($result['candidates'][0]['content']['parts'][0]['text'])) {
        $geminiText = trim((string)$result['candidates'][0]['content']['parts'][0]['text']);
        $geminiText = trim(preg_replace('/^```(?:text)?\s*|\s*```$/i', '', $geminiText));
        pharsayo_debug('gemini_text', substr($geminiText, 0, 120));
    } elseif (is_array($result) && isset($result['error'])) {
        pharsayo_debug('gemini_api_error', json_encode($result['error']));
    }

    if ($geminiText !== '') {
        $text     = pharsayo_chat_append_reminder($geminiText, $lang);
        $gSources = pharsayo_chat_grounding_sources($result);
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
}

// ── STEP 2: Google Custom Search API → feed snippets to Gemini ───────────────
// This handles questions like "medicine for toothache" even when Gemini grounding fails.
// Requires: GOOGLE_CSE_KEY + GOOGLE_CSE_CX environment variables
// Setup: programmablesearchengine.google.com → create engine → search the whole web
if ($google_cse_key !== '' && $google_cse_cx !== '') {
    pharsayo_debug('step', 'trying Google CSE');

    $searchQuery = $query . ' medicine drug Philippines';
    $cseUrl = 'https://www.googleapis.com/customsearch/v1?'
        . 'key=' . rawurlencode($google_cse_key)
        . '&cx=' . rawurlencode($google_cse_cx)
        . '&q=' . rawurlencode($searchQuery)
        . '&num=5'
        . '&safe=active';

    $cseRaw = pharsayo_curl_get_text($cseUrl, 12);
    $cseData = $cseRaw !== '' ? json_decode($cseRaw, true) : null;

    $cseSnippets = '';
    $cseSources  = [];
    if (is_array($cseData) && !empty($cseData['items'])) {
        foreach ($cseData['items'] as $item) {
            $title   = trim((string)($item['title']   ?? ''));
            $snippet = trim((string)($item['snippet'] ?? ''));
            $link    = trim((string)($item['link']    ?? ''));
            if ($snippet !== '') {
                $cseSnippets .= "- [{$title}]: {$snippet}\n";
            }
            if ($link !== '') {
                $cseSources[] = ['title' => $title ?: 'Web source', 'url' => $link];
            }
        }
    }

    if ($cseSnippets !== '' && $gemini_key !== '') {
        pharsayo_debug('cse_snippets', substr($cseSnippets, 0, 200));

        $synthesisPrompt = $lang === 'en'
            ? "You are a pharmacist assistant. Based on these Google search results, answer the user's question: \"{$message}\"\n\nSearch results:\n{$cseSnippets}\n\nProvide a clear, accurate answer with specific medicine names, uses, and key warnings. Be concise (4–7 sentences). End with: Reminder: You must still adhere in accordance to your doctor's prescription and guidelines."
            : "Ikaw ay pharmacist assistant. Batay sa mga resulta ng Google search, sagutin ang tanong ng user: \"{$message}\"\n\nMga resulta ng paghahanap:\n{$cseSnippets}\n\nMagbigay ng malinaw at tumpak na sagot na may mga specific na pangalan ng gamot, paggamit, at mahahalagang babala. Maging maikli (4–7 pangungusap). Tapusin ng: Paalala: Dapat mo pa ring sundin ang reseta at mga gabay ng iyong doktor.";

        $synthPayload = [
            'contents'         => [['role' => 'user', 'parts' => [['text' => $synthesisPrompt]]]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 700],
        ];

        $synthUrl    = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$gemini_key}";
        $synthResult = pharsayo_curl_post_json($synthUrl, $synthPayload, 20);

        $synthText = '';
        if (is_array($synthResult) && !empty($synthResult['candidates'][0]['content']['parts'][0]['text'])) {
            $synthText = trim((string)$synthResult['candidates'][0]['content']['parts'][0]['text']);
            $synthText = trim(preg_replace('/^```(?:text)?\s*|\s*```$/i', '', $synthText));
        }

        if ($synthText !== '') {
            $cseSources[] = ['title' => 'Google search: ' . $query, 'url' => $googleLink];
            $allSources   = array_merge($cseSources, $baseSources);
            $out = []; $seen = [];
            foreach ($allSources as $s) {
                $u = trim((string)($s['url'] ?? ''));
                if ($u === '' || isset($seen[$u])) continue;
                $seen[$u] = true;
                $out[] = ['title' => trim((string)($s['title'] ?? '')) ?: 'Source', 'url' => $u];
                if (count($out) >= 10) break;
            }
            $reply = pharsayo_chat_append_reminder($synthText, $lang);
            echo json_encode(['success' => true, 'source' => 'google_cse', 'reply' => $reply, 'sources' => $out]);
            exit;
        }
    }

    // CSE found results but no Gemini key — return raw snippets
    if ($cseSnippets !== '' && $gemini_key === '') {
        $summary = $lang === 'en'
            ? "Here's what I found for \"{$query}\":\n\n" . $cseSnippets
            : "Narito ang nahanap ko para sa \"{$query}\":\n\n" . $cseSnippets;
        $cseSources[] = ['title' => 'Google search: ' . $query, 'url' => $googleLink];
        echo json_encode(['success' => true, 'source' => 'google_cse_raw', 'reply' => pharsayo_chat_append_reminder($summary, $lang), 'sources' => array_merge($cseSources, $baseSources)]);
        exit;
    }
}

// ── STEP 3: Wikipedia ─────────────────────────────────────────────────────────
pharsayo_debug('step', 'trying wikipedia for: ' . $query);
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

// ── STEP 4: Online drug lookup ────────────────────────────────────────────────
pharsayo_debug('step', 'trying online lookup');
if (!$isQuestion && function_exists('pharsayo_lookup_medication_online')) {
    $online = pharsayo_lookup_medication_online($query, $query);
    if (is_array($online)) {
        $name     = trim((string)($online['display_name']   ?? $query));
        $purpose  = trim((string)($online['purpose_en']     ?? ''));
        $warnings = trim((string)($online['precautions_en'] ?? ''));
        $rxcui    = trim((string)($online['rxcui']          ?? ''));
        $out = $name . ': ' . ($purpose !== '' ? pharsayo_chat_truncate($purpose, 400) : 'See linked references.');
        if ($warnings !== '') $out .= "\n\nImportant warnings: " . pharsayo_chat_truncate($warnings, 260);
        $sources = [
            ['title' => 'RxNav', 'url' => 'https://rxnav.nlm.nih.gov/REST/drugs.json?name=' . rawurlencode($query)],
            ['title' => 'openFDA', 'url' => $rxcui !== ''
                ? 'https://api.fda.gov/drug/label.json?search=openfda.rxcui%3A%22' . rawurlencode($rxcui) . '%22&limit=1'
                : 'https://api.fda.gov/drug/label.json?search=' . rawurlencode($query) . '&limit=1'],
            ['title' => 'Google search: ' . $query, 'url' => $googleLink],
        ];
        echo json_encode(['success' => true, 'source' => 'online_lookup', 'reply' => pharsayo_chat_append_reminder($out, $lang), 'sources' => $sources]);
        exit;
    }
}

// ── STEP 5: Local KB ──────────────────────────────────────────────────────────
pharsayo_debug('step', 'trying local KB');
$local = pharsayo_chat_local_kb($query);
if (is_array($local)) {
    $name     = trim((string)($local['name']           ?? $query));
    $purpose  = trim((string)($local['purpose_en']     ?? ''));
    $warnings = trim((string)($local['precautions_en'] ?? ''));
    $out = $name . ': ' . ($purpose !== '' ? pharsayo_chat_truncate($purpose, 380) : 'Information found in local references.');
    if ($warnings !== '') $out .= "\n\nImportant warnings: " . pharsayo_chat_truncate($warnings, 240);
    echo json_encode(['success' => true, 'source' => 'local_kb', 'reply' => pharsayo_chat_append_reminder($out, $lang), 'sources' => $baseSources]);
    exit;
}

// ── STEP 6: Hardcoded common drugs + symptom keywords ────────────────────────
pharsayo_debug('step', 'using hardcoded fallback');
 
// Symptom/condition → medicine mappings
$symptom_map = [
 
    // ── Pain ──────────────────────────────────────────────────────────────────
    'toothache'            => ['Mefenamic Acid (Ponstan) 500mg every 6–8 hours or Ibuprofen 400mg every 6 hours are the most commonly used medicines for toothache pain in the Philippines. Paracetamol (500mg–1,000mg every 4–6 hours) is a gentler option if you cannot take NSAIDs. For infection-related toothache, Amoxicillin 500mg 3× daily (prescription required) may be prescribed by your dentist.', 'Take NSAIDs with food to avoid stomach irritation. These medicines relieve pain but do not treat the underlying cause — see a dentist as soon as possible.'],
    'sakit ng ngipin'      => ['Ang Mefenamic Acid (Ponstan) 500mg tuwing 6–8 oras o Ibuprofen 400mg tuwing 6 oras ang pinakakaraniwang gamot para sa sakit ng ngipin. Ang Paracetamol (500mg–1,000mg tuwing 4–6 oras) ay mas maamo kung hindi ka makakainon ng NSAIDs. Para sa impeksyon, maaaring iresetang Amoxicillin 500mg 3× araw ng iyong dentista.', 'Inumin ang NSAIDs pagkatapos kumain. Pumunta sa dentista para sa tamang paggamot.'],
    'headache'             => ['Paracetamol (500mg–1,000mg every 4–6 hours) is first-line for headaches. Ibuprofen (400mg every 6–8 hours) or Mefenamic Acid (500mg every 8 hours) are alternatives for tension headaches. Aspirin 325–650mg may also be used for adults.', 'Do not exceed 4,000mg of paracetamol in 24 hours. If headaches are severe, frequent, or with vision changes, seek medical attention.'],
    'sakit ng ulo'         => ['Ang Paracetamol (500mg–1,000mg tuwing 4–6 oras) ang pangunahing gamot para sa sakit ng ulo. Ang Ibuprofen (400mg tuwing 6–8 oras) o Mefenamic Acid ay alternatibo. Iwasang lampasan ang 4,000mg ng paracetamol sa 24 oras.', 'Kung madalas o matinding sakit ng ulo, kumonsulta sa doktor.'],
    'migraine'             => ['For migraine: Ibuprofen (400–600mg) or Naproxen (500mg) at the first sign of attack. Paracetamol (1,000mg) is an alternative. Caffeine-containing combinations (Cafergot) may help. Triptans (Sumatriptan) are prescription drugs for moderate-to-severe migraines.', 'Rest in a dark, quiet room. Avoid known triggers (bright lights, strong smells, missed meals). See a doctor if migraines are frequent or debilitating.'],
    'dysmenorrhea'         => ['Mefenamic Acid (Ponstan) 500mg every 8 hours starting 1–2 days before your period is the most recommended medicine for menstrual cramps in the Philippines. Ibuprofen 400mg every 6–8 hours is an equally effective alternative. Both should be taken with food.', 'NSAIDs are most effective when started before pain becomes severe. Avoid if you have ulcers or kidney problems. Consult a doctor if pain is severe and unrelieved.'],
    'menstrual cramps'     => ['Mefenamic Acid (Ponstan) 500mg every 8 hours or Ibuprofen 400mg every 6–8 hours are the standard medicines for menstrual cramps. Start taking 1–2 days before your expected period for best results.', 'Take with food. If cramps are unusually severe, see a gynecologist to rule out endometriosis or other conditions.'],
    'regla'                => ['Para sa sakit ng regla: Mefenamic Acid (Ponstan) 500mg tuwing 8 oras o Ibuprofen 400mg tuwing 6–8 oras. Simulan ang pag-inom 1–2 araw bago dumating ang regla para sa mas magandang epekto.', 'Inumin pagkatapos kumain. Kung matinding sakit, kumonsulta sa doktor.'],
    'muscle pain'          => ['For muscle pain: Ibuprofen (400mg every 6–8 hours) or Diclofenac (50mg twice daily) are commonly used NSAIDs. Methocarbamol (Robaxin) or Orphenadrine are muscle relaxants available in the Philippines. Topical diclofenac gel or Counterpain cream can be applied directly to sore muscles.', 'Rest the affected muscle. Apply ice for the first 48 hours, then warm compress. See a doctor if pain is severe or does not improve after 3–5 days.'],
    'back pain'            => ['For back pain: Ibuprofen (400mg every 6–8 hours) or Mefenamic Acid (500mg every 8 hours) for mild-to-moderate pain. Methocarbamol (Robaxin) for muscle spasm. Topical Diclofenac gel or Counterpain cream for localised relief.', 'Avoid prolonged bed rest — gentle movement helps. See a doctor if pain radiates to the legs, or is accompanied by numbness or weakness.'],
    'sakit ng likod'       => ['Para sa sakit ng likod: Ibuprofen (400mg tuwing 6–8 oras) o Mefenamic Acid (500mg tuwing 8 oras). Ang Methocarbamol ay para sa muscle spasm. Ang topical Diclofenac gel o Counterpain cream ay maaaring i-apply direkta sa masakit na bahagi.', 'Mag-ingat sa matagal na pag-upo. Kumonsulta sa doktor kung may panlalamig o pamamanhid sa binti.'],
    'joint pain'           => ['For joint pain: Ibuprofen (400mg every 6–8 hours) or Celecoxib (200mg once or twice daily) for arthritis-related pain. Diclofenac gel applied to the joint. Fish oil supplements may help with inflammatory joint conditions.', 'Warm compress for stiffness, cold pack for swelling. See a doctor for persistent or worsening joint pain.'],
    'arthritis'            => ['For arthritis: Celecoxib (Celebrex 200mg once daily) is a COX-2 inhibitor commonly prescribed in the Philippines. Meloxicam (7.5–15mg once daily) is another option. Paracetamol for mild pain. Topical Diclofenac for localised joint pain.', 'Take NSAIDs with food. Long-term use requires medical supervision. Physiotherapy and weight management are also important.'],
 
    // ── Fever / Flu ───────────────────────────────────────────────────────────
    'fever'                => ['Paracetamol (500mg–1,000mg every 4–6 hours for adults; 10–15mg/kg every 4–6 hours for children) is the standard medicine for fever in the Philippines. Ibuprofen (200–400mg every 6–8 hours) is an alternative for adults and children over 6 months. Increase fluid intake and rest.', 'Do not give Aspirin to children under 18. Seek medical attention if fever exceeds 39.5°C, lasts more than 3 days, or is accompanied by rash, stiff neck, or difficulty breathing.'],
    'lagnat'               => ['Ang Paracetamol (500mg–1,000mg tuwing 4–6 oras para sa mga matatanda; 10–15mg/kg para sa mga bata) ang pamantayang gamot para sa lagnat. Ang Ibuprofen ay alternatibo para sa mga taong higit sa 6 na buwan.', 'Huwag bigyan ng Aspirin ang mga bata. Kumonsulta sa doktor kung ang lagnat ay higit sa 39.5°C o tumatagal ng higit sa 3 araw.'],
    'flu'                  => ['For flu: Paracetamol (500mg–1,000mg every 4–6 hours) for fever and body aches. Cetirizine or Loratadine for runny nose. Vitamin C (500–1,000mg daily) and Zinc supplements to support immune function. Oseltamivir (Tamiflu) — prescription only — may shorten flu duration if started within 48 hours.', 'Rest and increase fluid intake. Antibiotics do not treat influenza. See a doctor if symptoms are severe or you are in a high-risk group (elderly, pregnant, immunocompromised).'],
    'trangkaso'            => ['Para sa trangkaso: Paracetamol (500mg–1,000mg tuwing 4–6 oras) para sa lagnat at pananakit ng katawan. Cetirizine o Loratadine para sa runny nose. Vitamin C at Zinc para sa immune system. Ang Oseltamivir (Tamiflu) ay nangangailangan ng reseta.', 'Magpahinga at uminom ng maraming tubig. Ang antibiotics ay hindi epektibo sa trangkaso.'],
 
    // ── Respiratory ───────────────────────────────────────────────────────────
    'cough'                => ['For dry cough: Dextromethorphan (DXM) — found in Robitussin DM, Tuseran Forte. For productive/wet cough with phlegm: Carbocisteine (Solmux) 500mg 3× daily or Guaifenesin (Mucosolvan) to loosen mucus. Lagundi (Vitex negundo) tablet or syrup is a DOH-approved Philippine herbal medicine for cough.', 'If cough lasts more than 2 weeks or is accompanied by fever, blood, or difficulty breathing, see a doctor. Do not give cough suppressants to children under 6.'],
    'ubo'                  => ['Para sa tuyong ubo: Dextromethorphan (DXM) — makikita sa Robitussin, Tuseran. Para sa may plema: Carbocisteine (Solmux) 500mg 3× araw o Guaifenesin (Mucosolvan). Ang Lagundi tablet o syrup ay aprubadong herbal na gamot ng DOH para sa ubo.', 'Kung ang ubo ay tumatagal ng mahigit 2 linggo o may lagnat o dugo, pumunta sa doktor.'],
    'cold'                 => ['For colds: Antihistamines like Cetirizine (10mg once daily) or Loratadine (10mg once daily) help with runny nose. Phenylephrine or Pseudoephedrine for nasal congestion. Vitamin C (500–1,000mg daily). Decolgen or Neozep are popular combination cold medicines in the Philippines.', 'Most colds resolve within 7–10 days. Antibiotics do not treat viral colds. Rest and increase fluids.'],
    'sipon'                => ['Para sa sipon: Cetirizine (10mg isang beses sa isang araw) o Loratadine (10mg). Phenylephrine para sa congestion. Vitamin C (500–1,000mg araw-araw). Ang Decolgen, Neozep, o Bioflu ay mga popular na gamot sa sipon sa Pilipinas.', 'Karamihan sa sipon ay gumagaling sa loob ng 7–10 araw. Magpahinga at uminom ng maraming tubig.'],
    'sore throat'          => ['For sore throat: Paracetamol or Ibuprofen for pain and fever. Benzydamine (Tantum Verde) or Flurbiprofen (Strefen) throat lozenges for local relief. Betadine Gargle or warm saltwater gargling helps soothe the throat. Strepsils lozenges are widely available in Philippine pharmacies.', 'If sore throat is severe, lasts more than 1 week, or is accompanied by high fever and white patches on the tonsils, see a doctor — it may need antibiotic treatment for strep throat.'],
    'sakit ng lalamunan'   => ['Para sa sakit ng lalamunan: Paracetamol o Ibuprofen para sa sakit at lagnat. Tantum Verde spray o Strepsils lozenges para sa local na ginhawa. Betadine Gargle o mainit na inasnan na tubig para sa pag-gargle.', 'Kung tumatagal ng mahigit 1 linggo o may mataas na lagnat at puting plema sa tonsil, kumonsulta sa doktor.'],
    'asthma'               => ['For asthma: Salbutamol (Ventolin) inhaler 2 puffs every 4–6 hours as a reliever/rescue inhaler. For maintenance: Budesonide (Pulmicort) or Beclomethasone inhaled corticosteroids. Montelukast (Singulair) 10mg once daily for mild persistent asthma. Lagundi syrup may provide additional bronchodilator support.', 'Always carry your rescue inhaler. Avoid triggers (dust, smoke, cold air). See a doctor immediately if an attack does not respond to your rescue inhaler.'],
    'hika'                 => ['Para sa hika: Salbutamol (Ventolin) inhaler 2 puffs tuwing 4–6 oras bilang rescue inhaler. Para sa maintenance: Budesonide inhaled corticosteroid. Montelukast (Singulair) 10mg isang beses sa isang araw.', 'Laging dalhin ang iyong rescue inhaler. Umiwas sa mga trigger tulad ng alikabok at usok. Pumunta agad sa doktor kung hindi gumagaling ang atake.'],
    'pneumonia'            => ['Pneumonia requires a doctor\'s prescription. Common antibiotics used in the Philippines include Amoxicillin-Clavulanate (Co-amoxiclav/Augmentin), Azithromycin (Zithromax), or Levofloxacin (Cravit) depending on severity. Paracetamol for fever. Carbocisteine (Solmux) to help clear secretions.', 'Pneumonia can be life-threatening — do not self-medicate. Go to a doctor or hospital immediately if you have high fever, difficulty breathing, or chest pain.'],
    'bronchitis'           => ['For bronchitis: Carbocisteine (Solmux) 500mg 3× daily or Ambroxol (Mucosolvan) to thin mucus. Salbutamol inhaler for bronchospasm. Paracetamol for fever. If bacterial, Amoxicillin or Azithromycin (prescription required).', 'Rest, drink plenty of fluids, and avoid smoke. Most acute bronchitis is viral and does not need antibiotics. See a doctor if symptoms last more than 3 weeks or you have breathing difficulty.'],
 
    // ── Gastrointestinal ──────────────────────────────────────────────────────
    'diarrhea'             => ['Loperamide (Diatabs/Imodium) 2mg initially, then 1mg after each loose stool (max 8mg/day) for acute non-infectious diarrhea. Oral Rehydration Salts (ORS/Hydrite/Oresol) are essential to prevent dehydration. Probiotics (Lacteol Fort, Erceflora) help restore gut flora.', 'Do not use Loperamide if there is blood in stool or high fever. Seek immediate medical help if diarrhea lasts more than 2 days, or signs of dehydration appear (dry mouth, no urination, dizziness).'],
    'pagtatae'             => ['Ang Loperamide (Diatabs) 2mg una, pagkatapos 1mg pagkatapos ng bawat malambot na dumi (max 8mg/araw). Ang ORS/Hydrite/Oresol ay mahalaga para maiwasan ang dehydration. Ang Lacteol Fort o Erceflora ay makakatulong sa gut flora.', 'Huwag gumamit ng Loperamide kung may dugo sa dumi o mataas na lagnat. Kumonsulta sa doktor kung tumatagal ng mahigit 2 araw.'],
    'stomach pain'         => ['For stomach/abdominal pain: Simethicone (Mylicon, Gas-X) for gas pain and bloating. Omeprazole (20mg before meals) for acid-related pain. Hyoscine Butylbromide (Buscopan 10mg) for cramping or spasms. Antacids like Kremil-S, Maalox, or Gaviscon for heartburn.', 'Sudden severe stomach pain, pain with fever, or vomiting blood requires immediate emergency care.'],
    'sakit ng tiyan'       => ['Para sa sakit ng tiyan: Simethicone para sa hangin at bloating. Omeprazole (20mg bago kumain) para sa acid. Hyoscine Butylbromide (Buscopan) para sa cramping. Kremil-S o Maalox para sa heartburn.', 'Biglang matinding sakit ng tiyan na may lagnat o pagsusuka ng dugo ay kailangan ng agarang medikal na atensyon.'],
    'hyperacidity'         => ['For hyperacidity/acid reflux: Omeprazole (20mg once daily, 30 minutes before breakfast) or Pantoprazole (40mg once daily) are proton pump inhibitors. Antacids like Kremil-S, Maalox, or Gaviscon provide quick but temporary relief. Famotidine (40mg at bedtime) is an H2 blocker alternative.', 'Avoid trigger foods (spicy, fatty, acidic foods, coffee, alcohol). Eat small, frequent meals. See a doctor if symptoms persist or you have difficulty swallowing.'],
    'acid reflux'          => ['For acid reflux (GERD): Omeprazole (20mg once daily before meals) or Lansoprazole (30mg once daily) are first-line treatments. Antacids (Kremil-S, Maalox, Gaviscon) for immediate symptom relief. Domperidone (Motilium) 10mg before meals can help if there is also nausea.', 'Avoid lying down after meals. Elevate head of bed. Avoid alcohol, caffeine, chocolate, and fatty foods. See a doctor for long-term management.'],
    'ulcer'                => ['For peptic ulcer: Omeprazole (20mg–40mg once daily) or Pantoprazole to reduce acid. If H. pylori infection is confirmed, triple therapy is needed: Omeprazole + Amoxicillin + Clarithromycin for 7–14 days (prescription required). Antacids for symptomatic relief.', 'Avoid NSAIDs (Ibuprofen, Mefenamic Acid, Aspirin) as they can worsen ulcers. Avoid spicy food, coffee, and alcohol. Diagnosis requires endoscopy.'],
    'nausea'               => ['For nausea: Metoclopramide (Plasil) 10mg 3× daily before meals is commonly used in the Philippines. Domperidone (Motilium) 10mg before meals is an alternative. For motion sickness: Meclizine (Bonamine) 25mg or Dimenhydrinate (Dramamine) 50mg 30 minutes before travel.', 'Sip clear fluids slowly. Eat small, bland meals (crackers, rice, bananas). See a doctor if nausea is persistent, severe, or accompanied by vomiting blood.'],
    'vomiting'             => ['For vomiting: Metoclopramide (Plasil) 10mg 3× daily or Domperidone (Motilium) 10mg 3× daily. ORS (Oresol, Hydrite) to replace lost fluids and electrolytes. Start with small sips of clear liquids before progressing to solid foods.', 'Do not give anti-vomiting medicine to children under 2 without a doctor\'s advice. Seek medical attention if vomiting is severe, bloody, or persists more than 24 hours.'],
    'pagsusuka'            => ['Para sa pagsusuka: Metoclopramide (Plasil) 10mg 3× araw o Domperidone (Motilium). ORS (Oresol, Hydrite) para palitan ang nawawalang fluids. Magsimula sa maliliit na higop ng malinaw na likido.', 'Kumonsulta sa doktor kung ang pagsusuka ay matagal, matindi, o may dugo.'],
    'constipation'         => ['For constipation: Bisacodyl (Dulcolax) 5–10mg at bedtime for occasional constipation. Lactulose syrup (15–30ml once daily) is a gentler osmotic laxative suitable for children and the elderly. Psyllium husk (Metamucil) as a bulk-forming fibre supplement.', 'Increase dietary fibre (fruits, vegetables, whole grains) and fluid intake. Regular physical activity helps. Avoid prolonged use of stimulant laxatives without medical advice.'],
    'semento'              => ['Para sa tibi/semento: Bisacodyl (Dulcolax) 5–10mg bago matulog. Lactulose syrup para sa mas maamo na epekto. Dagdagan ang pagkain ng gulay, prutas, at maraming tubig.', 'Iwasang umasa sa laxatives nang matagal. Kumonsulta sa doktor kung ang tibi ay tumatagal ng mahigit 2 linggo.'],
    'tibi'                 => ['Para sa tibi: Bisacodyl (Dulcolax) 5–10mg bago matulog. Lactulose syrup para sa mas maamo na epekto. Dagdagan ang pagkain ng gulay at prutas at maraming tubig.', 'Huwag umasa sa laxatives nang matagal. Kumonsulta sa doktor kung ang tibi ay tumatagal ng mahigit 2 linggo.'],
    'bloating'             => ['For bloating and gas: Simethicone (Mylicon, Phazyme) 80–125mg after meals and at bedtime. Activated charcoal capsules may help reduce gas. Kremil-S (which contains Simethicone) is a popular Philippine option.', 'Eat slowly, avoid carbonated drinks and gas-producing foods (beans, cabbage, onions). If bloating is persistent or painful, see a doctor.'],
    'gas pain'             => ['For gas pain: Simethicone (Mylicon) 80–125mg after meals. Kremil-S tablets contain simethicone and antacids for combined relief. Warm water or peppermint tea may also help.', 'Avoid carbonated drinks and foods that cause gas. If pain is severe or persistent, consult a doctor.'],
 
    // ── Allergy / Skin ────────────────────────────────────────────────────────
    'allergy'              => ['For allergies: Cetirizine (10mg once daily) or Loratadine (10mg once daily) are non-drowsy antihistamines. Diphenhydramine (Benadryl 25–50mg) is effective for acute reactions but causes drowsiness. Fexofenadine (Telfast 120–180mg) is another non-drowsy option. Hydrocortisone cream 1% for skin allergy rashes.', 'For severe allergic reactions (difficulty breathing, swelling of throat), seek emergency care immediately — epinephrine may be needed.'],
    'skin allergy'         => ['For skin allergy/urticaria (hives): Cetirizine 10mg or Loratadine 10mg daily. Diphenhydramine (Benadryl) for acute reactions. Hydrocortisone cream 1% or Betamethasone cream (prescription) for localised rashes. Calamine lotion for itching and mild rashes.', 'Identify and avoid the allergen. See a doctor if rash spreads, blisters, or does not improve in 3–5 days.'],
    'hives'                => ['For hives (urticaria): Cetirizine (10mg once daily) or Loratadine (10mg once daily). Diphenhydramine (Benadryl) for rapid relief. Hydroxyzine (Iterax) for more severe cases — available by prescription in the Philippines.', 'Avoid known triggers. Apply cold compress to affected areas. Seek medical help if hives are widespread or accompanied by breathing difficulty.'],
    'rash'                 => ['For skin rash: Identify the cause first. Calamine lotion for mild itchy rashes. Hydrocortisone cream 1% for allergic contact dermatitis. Antihistamines (Cetirizine, Loratadine) for allergic rashes. Clotrimazole cream for fungal rashes (ringworm, athlete\'s foot).', 'See a doctor if the rash covers large areas, blisters, is painful, or is accompanied by fever.'],
    'skin rash'            => ['For skin rash: Calamine lotion for mild itchy rashes. Hydrocortisone 1% cream for allergic rashes. Cetirizine or Loratadine for allergic component. Clotrimazole for fungal rashes. Mupirocin (Bactroban) for infected rashes.', 'See a doctor if the rash spreads rapidly, blisters, is painful, or is accompanied by fever.'],
    'itching'              => ['For itching (pruritus): Cetirizine (10mg once daily) or Loratadine (10mg) systemically. Hydrocortisone cream 1% or Calamine lotion topically. Caladryl lotion for skin irritation.', 'Avoid scratching to prevent infection. Moisturise dry skin regularly. See a doctor if itching is severe, generalised, or accompanied by rash.'],
    'kati'                 => ['Para sa pangangati: Cetirizine (10mg isang beses sa isang araw) o Loratadine. Hydrocortisone cream 1% o Calamine lotion sa apektadong bahagi.', 'Huwag kumamot para maiwasan ang impeksyon. Kumonsulta sa doktor kung matindi o malawak ang pangangati.'],
    'eczema'               => ['For eczema: Hydrocortisone cream 1% (OTC) for mild flares. Betamethasone or Clobetasol cream (prescription) for moderate-to-severe. Regular moisturising with fragrance-free cream (Cetaphil, Physiogel). Cetirizine or Loratadine for itching.', 'Avoid soaps and detergents that trigger flares. Keep skin moisturised. See a dermatologist for persistent or severe eczema.'],
    'fungal infection'     => ['For fungal skin infection (ringworm, athlete\'s foot, jock itch): Clotrimazole cream (Canesten) 1% applied twice daily for 2–4 weeks. Miconazole cream (Mycoderm/Daktarin) is an alternative. Ketoconazole shampoo for scalp fungal infection. Fluconazole (oral) for widespread or resistant cases — prescription required.', 'Keep the affected area clean and dry. Do not share towels or clothing. Complete the full course even if symptoms improve.'],
    'buni'                 => ['Para sa buni (ringworm): Clotrimazole cream (Canesten) 1% dalawang beses sa isang araw sa loob ng 2–4 linggo. Miconazole cream o Ketoconazole cream ay mga alternatibo.', 'Panatilihing malinis at tuyo ang apektadong bahagi. Huwag magbahagi ng tuwalya. Tapusin ang buong kurso ng gamot.'],
    'athlete\'s foot'      => ['For athlete\'s foot (alipunga): Clotrimazole cream (Canesten) or Miconazole cream applied between toes twice daily for 2–4 weeks. Tolnaftate powder (Tinactin) for prevention. Terbinafine cream (Lamisil) is a highly effective alternative.', 'Keep feet clean and dry, especially between toes. Wear breathable footwear. Do not share socks or shoes.'],
    'alipunga'             => ['Para sa alipunga (athlete\'s foot): Clotrimazole cream (Canesten) o Miconazole cream sa pagitan ng mga daliri ng paa dalawang beses sa isang araw sa loob ng 2–4 linggo. Terbinafine cream (Lamisil) ay lubhang epektibo.', 'Panatilihing malinis at tuyo ang mga paa. Magsuot ng breathable na sapatos.'],
    'acne'                 => ['For acne: Benzoyl peroxide 2.5–5% gel or wash (Benzac, Oxy) applied once or twice daily. Salicylic acid 0.5–2% cleanser. Clindamycin gel (prescription) for inflammatory acne. Tretinoin cream (prescription) for moderate-to-severe acne.', 'Do not pop pimples — this causes scarring and spreads bacteria. See a dermatologist for severe or persistent acne.'],
    'pimples'              => ['For pimples: Benzoyl peroxide 2.5–5% (Benzac AC) applied to affected areas. Salicylic acid cleanser. Clindamycin gel (prescription) for inflamed pimples. Tea tree oil as a natural mild antiseptic.', 'Wash face twice daily with a gentle cleanser. Avoid touching your face. See a dermatologist if pimples are cystic or cause scarring.'],
    'pigsa'                => ['Para sa pigsa (boil/furuncle): Warm compress 3–4 beses sa isang araw para tulungang lumabas ang nana. Mupirocin (Bactroban) cream para sa impeksyon. Kung malaki o masakit, ang Amoxicillin o Cloxacillin (prescription) ay maaaring kailanganin.', 'Huwag putukin o pigsilin ang pigsa — maaaring kumalat ang impeksyon. Kumonsulta sa doktor kung malaki, masakit, o may lagnat.'],
    'wound'                => ['For wound care: Clean the wound with clean water and mild soap. Apply Povidone-iodine (Betadine) solution or Chlorhexidine (Hibitane) for antisepsis. Cover with sterile gauze or bandage. For infected wounds: Mupirocin (Bactroban) cream topically.', 'Seek medical attention for deep wounds, wounds with signs of infection (pus, increasing redness, warmth), animal bites, or puncture wounds. Tetanus immunisation should be up to date.'],
    'sugat'                => ['Para sa sugat: Linisin ang sugat ng malinis na tubig at sabon. Mag-apply ng Betadine solution o Chlorhexidine para sa antisepsis. Takpan ng sterile na gauze o bandage.', 'Pumunta sa doktor para sa malalim na sugat, tuklaw ng hayop, o sugat na may palatandaan ng impeksyon.'],
    'burns'                => ['For minor burns (1st degree): Cool the burn under cool running water for 10–20 minutes. Apply Aloe vera gel or Silver sulfadiazine cream (Flamazine — prescription). Do not apply ice, butter, or toothpaste. Paracetamol for pain.', 'Seek emergency care for burns covering large areas, burns on face/hands/genitals, chemical or electrical burns, or burns with blistering over large areas.'],
 
    // ── Eyes / Ears / Nose ────────────────────────────────────────────────────
    'eye infection'        => ['For bacterial conjunctivitis (sore eyes): Tobramycin eye drops (Tobrex) or Ciprofloxacin eye drops, 1–2 drops every 4–6 hours — prescription usually required. Chloramphenicol eye drops 0.5% (available OTC in some Philippine pharmacies). Artificial tears for irritation.', 'Do not share eye drops or towels. Wash hands frequently. See an ophthalmologist if vision is affected or symptoms worsen after 48 hours.'],
    'sore eyes'            => ['For sore eyes (conjunctivitis): Chloramphenicol eye drops 0.5% applied every 2–4 hours. Cold compress for relief of swelling. Artificial tears for irritation and redness.', 'Do not share eye drops or face towels — sore eyes is highly contagious. See a doctor if symptoms worsen or vision is affected.'],
    'ear infection'        => ['For ear infection (otitis media): Amoxicillin 500mg 3× daily for 7–10 days (prescription required). Paracetamol or Ibuprofen for pain and fever. Otrivin nasal drops to reduce congestion. Do not insert objects into the ear.', 'See a doctor to confirm diagnosis before using antibiotic ear drops. Never put any liquid in the ear if you suspect a perforated eardrum.'],
    'ear pain'             => ['For ear pain: Paracetamol (500mg–1,000mg every 4–6 hours) or Ibuprofen (400mg every 6–8 hours) for pain relief. Warm compress over the ear. See a doctor promptly to determine the cause.', 'Do not insert cotton buds or other objects into the ear. See a doctor if pain is severe or accompanied by hearing loss, discharge, or fever.'],
    'nasal congestion'     => ['For nasal congestion: Phenylephrine nasal drops (Iliadin) or Xylometazoline nasal spray (Otrivin) provide quick decongestant relief. Oral Pseudoephedrine (in Decolgen, Neozep) or Phenylephrine. Saline nasal rinse (NeilMed, Sterimar) is a safe non-medicated option.', 'Do not use decongestant nasal sprays for more than 3–5 consecutive days — rebound congestion may occur. See a doctor if congestion lasts more than 10 days.'],
 
    // ── Urinary / Kidney ──────────────────────────────────────────────────────
    'urinary tract infection' => ['For urinary tract infection (UTI): Nitrofurantoin (Macrobid) 100mg twice daily for 5 days or Cotrimoxazole (Bactrim) 960mg twice daily for 3 days are first-line antibiotics — all require prescription. Phenazopyridine (Pyridium) 200mg 3× daily relieves burning and urgency (turns urine orange). Cranberry supplements or juice may help prevent recurrence.', 'Complete the full antibiotic course. Drink plenty of water. See a doctor immediately — UTIs can progress to kidney infection if untreated.'],
    'uti'                  => ['For UTI: Nitrofurantoin (Macrobid) 100mg twice daily or Cotrimoxazole (Bactrim) twice daily are common prescription antibiotics. Phenazopyridine (Pyridium) for burning/urgency relief. Drink at least 8 glasses of water daily.', 'Prescription is required for antibiotics. See a doctor promptly — symptoms (burning urination, frequency, lower abdominal pain) need proper diagnosis.'],
    'kidney stones'        => ['For kidney stone pain: Ketorolac (Toradol) injection or Diclofenac (Voltaren) suppository/tablet for severe pain — prescription required. Hyoscine Butylbromide (Buscopan) to relieve ureteral spasm. Drink at least 2–3 litres of water daily to help pass small stones.', 'Seek emergency care for severe uncontrolled pain, fever with stone, or inability to urinate. Most stones <5mm pass on their own; larger stones may need urological intervention.'],
 
    // ── Cardiovascular / Hypertension ─────────────────────────────────────────
    'hypertension'         => ['For hypertension (high blood pressure): Amlodipine (5–10mg once daily), Losartan (50–100mg once daily), and Enalapril (5–20mg once daily) are among the most commonly prescribed antihypertensives in the Philippines. Most require a doctor\'s prescription.', 'Do not stop taking blood pressure medicines without consulting your doctor. Monitor BP regularly. Reduce salt intake, exercise regularly, avoid smoking and excessive alcohol.'],
    'high blood pressure'  => ['For high blood pressure: Amlodipine (5–10mg once daily) or Losartan (50–100mg once daily) are first-line medicines in the Philippines. These require a doctor\'s prescription. Lifestyle changes (low-salt diet, exercise, weight loss) are equally important.', 'Never stop antihypertensive medicine abruptly. See your doctor regularly for BP monitoring and medication adjustments.'],
    'mataas na blood pressure' => ['Para sa mataas na blood pressure: Ang Amlodipine (5–10mg isang beses sa isang araw) o Losartan (50–100mg) ay mga karaniwang inireseta sa Pilipinas. Lahat ay nangangailangan ng reseta ng doktor.', 'Huwag ihinto ang gamot nang walang pahintulot ng doktor. Regular na subaybayan ang blood pressure.'],
 
    // ── Diabetes ──────────────────────────────────────────────────────────────
    'diabetes'             => ['For Type 2 diabetes: Metformin (500mg–1,000mg twice daily with meals) is the most commonly prescribed first-line oral antidiabetic in the Philippines. Glimepiride (1–4mg once daily) or Gliclazide are sulphonylurea alternatives. Insulin therapy for more advanced cases.', 'All diabetes medicines require a doctor\'s prescription. Monitor blood sugar regularly. Follow a low-sugar, low-carbohydrate diet, and exercise regularly.'],
    'mataas na asukal'     => ['Para sa mataas na asukal (diabetes): Ang Metformin (500mg–1,000mg dalawang beses sa isang araw kasabay ng pagkain) ang pinakakaraniwang inireseta sa Pilipinas. Nangangailangan ng reseta ng doktor ang lahat ng gamot para sa diabetes.', 'Regular na subaybayan ang blood sugar. Iwasan ang matamis at mataas na carbohydrate na pagkain.'],
 
    // ── Mental Health / Sleep ─────────────────────────────────────────────────
    'anxiety'              => ['For anxiety: Consult a doctor. Commonly prescribed medicines in the Philippines include Alprazolam (Xanax — controlled substance, prescription required), Lorazepam (Ativan), or SSRIs such as Sertraline (Zoloft) or Escitalopram for long-term management. Hydroxyzine (Iterax) 25–50mg is a non-controlled option for mild anxiety.', 'Do not self-medicate with controlled substances. Therapy and lifestyle changes (exercise, sleep, stress management) are important alongside medication.'],
    'insomnia'             => ['For occasional insomnia: Diphenhydramine (Benadryl, Nytol) 25–50mg at bedtime has mild sedating effects. Melatonin (3–10mg) 30–60 minutes before bed is a gentler over-the-counter option. For chronic insomnia, a doctor may prescribe Zolpidem (Stilnox) or Clonazepam.', 'Practice good sleep hygiene: consistent sleep schedule, dark and cool room, avoid screens before bed. See a doctor if insomnia is chronic or significantly affects daily functioning.'],
    'hindi makatulog'      => ['Para sa hindi makatulog: Melatonin (3–10mg) 30–60 minuto bago matulog. Diphenhydramine (Benadryl) 25–50mg ay may mildong epektong pang-antok.', 'Magtakda ng regular na oras ng pagtulog. Iwasan ang screen bago matulog. Kumonsulta sa doktor kung kronikong insomnia.'],
 
    // ── Vitamins / Supplements ────────────────────────────────────────────────
    'vitamins'             => ['Popular vitamins and supplements in the Philippines: Vitamin C (500mg–1,000mg daily) for immune support. Vitamin D3 (1,000–2,000 IU daily) especially for those with limited sun exposure. Zinc (10–25mg daily) supports immunity and wound healing. B-complex vitamins (Neurobion, Becozyme) for nerve health and energy. Iron (ferrous sulfate) for anaemia.', 'Vitamins should supplement, not replace, a balanced diet. Consult a doctor before taking high-dose supplements.'],
    'vitamin c'            => ['Vitamin C (Ascorbic acid) 500mg–1,000mg daily for adults supports immune function, skin health, iron absorption, and wound healing. High doses (>2,000mg/day) may cause diarrhoea or kidney stones in susceptible individuals.', 'Vitamin C is water-soluble — excess is excreted. However, very high doses over long periods can be harmful. Get Vitamin C from fruits and vegetables when possible.'],
    'vitamin d'            => ['Vitamin D3 1,000–2,000 IU daily is commonly recommended for Filipinos with limited sun exposure. It supports bone health (calcium absorption), immune function, and mood. Deficiency is associated with increased risk of infections, osteoporosis, and depression.', 'Take with a meal containing fat for better absorption. Excessive supplementation (>10,000 IU/day long-term) can cause toxicity. See a doctor for blood testing if you suspect deficiency.'],
    'iron supplement'      => ['Ferrous sulfate (325mg) once daily or Ferrous fumarate (200mg) are commonly used iron supplements in the Philippines for iron-deficiency anaemia. Maltofer (Iron polymaltose complex) is a gentler alternative with fewer GI side effects.', 'Take on an empty stomach with Vitamin C for best absorption, unless it causes stomach upset. Iron can cause dark/black stools — this is normal. See a doctor for confirmed anaemia before supplementing.'],
    'multivitamins'        => ['Popular multivitamins in the Philippines include Centrum, Enervon-C, Berocca, Conzace, and Stresstabs. They provide a range of essential vitamins and minerals for general health maintenance.', 'Multivitamins are not a substitute for a healthy diet. Follow dosage instructions. Some multivitamins contain iron — avoid giving adult formulations to young children.'],
 
    // ── Eye drops / Wounds / First aid ───────────────────────────────────────
    'wound infection'      => ['For infected wounds: Mupirocin (Bactroban) 2% cream applied 3× daily for localised impetigo or skin infections. For more serious infections, Amoxicillin-Clavulanate (Co-amoxiclav) or Cloxacillin (prescription required). Clean with Povidone-iodine (Betadine) solution daily.', 'See a doctor if there is spreading redness, pus, red streaks, or fever — these suggest a serious infection requiring systemic antibiotics.'],
    'burns treatment'      => ['For minor burns: Cool under running water for 10–20 minutes immediately. Apply Silver sulfadiazine cream (Flamazine — prescription) or Aloe vera gel. Cover with non-stick dressing. Paracetamol for pain.', 'Seek emergency care for large, deep, or facial burns. Do not apply ice, butter, toothpaste, or oil on burns.'],
 
    // ── Motion sickness / Travel ──────────────────────────────────────────────
    'motion sickness'      => ['For motion sickness: Meclizine (Bonamine) 25–50mg taken 1 hour before travel — effective for up to 24 hours. Dimenhydrinate (Dramamine) 50mg every 4–6 hours. Scopolamine patch (Transderm Scop — prescription) behind the ear for sea travel.', 'Sit in the front seat or middle of the vehicle. Look at the horizon. Avoid reading during travel. Avoid heavy meals before travel.'],
    'mabiyahe'             => ['Para sa pag-aayos ng mabiyahe (motion sickness): Meclizine (Bonamine) 25–50mg 1 oras bago maglakbay. Dimenhydrinate (Dramamine) 50mg tuwing 4–6 oras.', 'Umupo sa harapan o gitna ng sasakyan. Huwag magbasa habang naglalakbay. Iwasan ang mabigat na pagkain bago maglakbay.'],
];
 
// ── Hardcoded individual drug information ────────────────────────────────────
$hardcoded = [
 
    // ── Analgesics / NSAIDs ───────────────────────────────────────────────────
    'paracetamol'          => ['Paracetamol (acetaminophen) is used to relieve mild-to-moderate pain and reduce fever. It is one of the safest and most widely used OTC medicines in the Philippines for headaches, toothaches, colds, and flu. Adult dose: 500mg–1,000mg every 4–6 hours (max 4,000mg/day).', 'Do not exceed 4,000mg in 24 hours. Avoid alcohol. Overdose causes severe liver damage — it is the leading cause of acute liver failure worldwide.'],
    'biogesic'             => ['Biogesic (Paracetamol 500mg) is a widely used OTC analgesic and antipyretic in the Philippines for fever, headache, toothache, and mild body pain.', 'Do not exceed 8 tablets (4,000mg) in 24 hours. Avoid alcohol. Consult a doctor for use in children under 2 years.'],
    'ibuprofen'            => ['Ibuprofen is an NSAID used for pain, fever, and inflammation — including headaches, muscle aches, menstrual cramps, dental pain, and arthritis. Typical adult dose: 200–400mg every 4–6 hours (max 1,200mg/day without medical supervision).', 'Take with food to reduce stomach irritation. Avoid in kidney disease, peptic ulcer, or if on blood thinners. Not recommended in the last trimester of pregnancy.'],
    'advil'                => ['Advil (Ibuprofen 200mg) is an OTC NSAID used for mild-to-moderate pain relief and fever reduction — headaches, toothaches, menstrual pain, and muscle aches.', 'Take with food. Do not exceed 1,200mg per day without medical advice. Avoid in kidney problems or peptic ulcer.'],
    'mefenamic acid'       => ['Mefenamic acid is an NSAID commonly used in the Philippines for menstrual pain, toothache, headache, post-surgical pain, and fever. Standard adult dose: 500mg every 8 hours, taken with food.', 'Do not use for more than 7 days unless directed by a doctor. Avoid in kidney or liver disease, peptic ulcer, or during pregnancy. Take with food.'],
    'ponstan'              => ['Ponstan (Mefenamic Acid 500mg) is one of the most popular pain relievers in the Philippines — especially for menstrual cramps, toothache, headache, and post-surgical pain.', 'Take with food. Do not exceed 3 tablets per day. Not recommended for more than 7 consecutive days without medical supervision.'],
    'dolfenal'             => ['Dolfenal (Mefenamic Acid 500mg) is the same active ingredient as Ponstan, used for mild-to-moderate pain and fever in the Philippines.', 'Take with food. Do not use for more than 7 days without medical advice. Avoid in peptic ulcer, kidney disease, and pregnancy.'],
    'naproxen'             => ['Naproxen is an NSAID used for pain, inflammation, and fever — including arthritis, menstrual pain, muscle aches, and migraine. Typical adult dose: 250–500mg twice daily.', 'Take with food or milk. Avoid in kidney disease or peptic ulcer. Do not combine with other NSAIDs. Maximum 1,000mg per day without medical advice.'],
    'diclofenac'           => ['Diclofenac (Voltaren) is an NSAID available as tablets, gel, and suppositories. Used for arthritis, back pain, muscle pain, dental pain, and post-operative pain. Tablet: 50mg 2–3× daily.', 'Take with food. Avoid in kidney or liver disease. Long-term use increases cardiovascular and GI risk. Topical gel has fewer systemic side effects.'],
    'celecoxib'            => ['Celecoxib (Celebrex 200mg) is a COX-2 selective NSAID used for osteoarthritis, rheumatoid arthritis, and acute pain. It has a lower risk of GI ulcers compared to traditional NSAIDs.', 'Prescription required. May increase cardiovascular risk with long-term use. Avoid in sulphonamide allergy.'],
    'aspirin'              => ['Aspirin 75–100mg daily is used as an antiplatelet agent to prevent heart attacks and strokes in high-risk patients. Aspirin 325–500mg is used for mild-to-moderate pain and fever in adults.', 'Never give Aspirin to children under 18 (risk of Reye\'s syndrome). Take with food. Avoid in peptic ulcer, bleeding disorders, or if on anticoagulants.'],
    'meloxicam'            => ['Meloxicam (Mobic) 7.5–15mg once daily is an NSAID used for osteoarthritis and rheumatoid arthritis. It has preferential COX-2 inhibition with a lower GI risk than older NSAIDs.', 'Prescription required. Take with food. Monitor kidney function with long-term use. Avoid in last trimester of pregnancy.'],
    'ketorolac'            => ['Ketorolac (Toradol) is a potent NSAID used short-term (≤5 days) for moderate-to-severe acute pain — post-operative, renal colic, musculoskeletal injury. Available as injection and tablet in the Philippines.', 'Prescription required. Not for chronic use. High risk of GI bleeding. Avoid in kidney impairment, peptic ulcer, or bleeding disorders.'],
 
    // ── Antibiotics ───────────────────────────────────────────────────────────
    'amoxicillin'          => ['Amoxicillin is a broad-spectrum penicillin antibiotic used for respiratory tract infections, ear infections, sinusitis, UTIs, dental infections, and skin infections. Standard adult dose: 500mg every 8 hours for 7–10 days.', 'Prescription required. Complete the full course. Do not use if allergic to penicillin. Common side effects: diarrhoea, rash, stomach upset.'],
    'augmentin'            => ['Augmentin (Amoxicillin-Clavulanate / Co-amoxiclav) is used for more resistant bacterial infections — sinusitis, community-acquired pneumonia, skin infections, and UTIs. Adult dose: 625mg (500/125mg) every 8–12 hours.', 'Prescription required. Take with food to reduce stomach upset. Do not use if allergic to penicillin. May cause diarrhoea.'],
    'co-amoxiclav'         => ['Co-amoxiclav (Amoxicillin-Clavulanate) is a combination antibiotic for infections resistant to plain Amoxicillin — sinusitis, pneumonia, skin infections, UTIs. Adult dose: 625mg every 8–12 hours with food.', 'Prescription required. Complete the full course. Do not use in penicillin allergy. Associated with cholestatic jaundice — stop and see doctor if jaundice occurs.'],
    'azithromycin'         => ['Azithromycin (Zithromax) is a macrolide antibiotic used for respiratory infections, sinusitis, skin infections, STIs (chlamydia), and community-acquired pneumonia. Typical dose: 500mg on Day 1, then 250mg once daily on Days 2–5.', 'Prescription required. May prolong QT interval — caution in heart conditions. Complete the full course.'],
    'clarithromycin'       => ['Clarithromycin (Klacid) 250–500mg twice daily is a macrolide antibiotic used for respiratory infections, H. pylori eradication, and skin infections.', 'Prescription required. Many drug interactions — inform your doctor of all medicines you are taking. Complete the full course.'],
    'ciprofloxacin'        => ['Ciprofloxacin (Ciprobay) 500mg twice daily is a fluoroquinolone antibiotic for UTIs, traveller\'s diarrhoea, respiratory and skin infections.', 'Prescription required. Avoid antacids and dairy within 2 hours of dose. May cause tendon rupture (especially in elderly). Avoid in children and pregnant women.'],
    'cotrimoxazole'        => ['Cotrimoxazole (Bactrim/Septrin — Trimethoprim + Sulfamethoxazole) is used for UTIs, respiratory infections, and Pneumocystis jirovecii pneumonia (PCP) prophylaxis. Standard adult dose: 960mg (DS) twice daily.', 'Prescription required. Drink plenty of water. Avoid in sulphonamide allergy, severe kidney or liver disease. Complete the full course.'],
    'doxycycline'          => ['Doxycycline 100mg twice daily is a tetracycline antibiotic for respiratory infections, acne, chlamydia, Lyme disease, and malaria prophylaxis.', 'Prescription required. Take with a full glass of water, remain upright for 30 minutes after. Avoid in pregnant women and children under 8. Use sunscreen — increases sun sensitivity.'],
    'metronidazole'        => ['Metronidazole (Flagyl) 500mg 3× daily is an antibiotic/antiparasitic used for anaerobic bacterial infections, H. pylori (triple therapy), trichomoniasis, and bacterial vaginosis.', 'Prescription required. Absolutely avoid alcohol during treatment and for 48 hours after — severe nausea and vomiting can result. Complete the full course.'],
    'clindamycin'          => ['Clindamycin (Dalacin-C) 150–300mg every 6 hours is used for serious skin and soft tissue infections, bone infections, and dental infections in penicillin-allergic patients.', 'Prescription required. May cause Clostridioides difficile-associated diarrhoea (severe, watery, or bloody diarrhoea). Complete the full course.'],
 
    // ── Antacids / GI ─────────────────────────────────────────────────────────
    'kremil s'             => ['Kremil-S is an antacid/antiflatulent containing Aluminium hydroxide, Magnesium hydroxide, and Simethicone. Used to relieve hyperacidity, heartburn, stomach pain, and gas. Typical dose: 1–2 tablets after meals and at bedtime.', 'Do not exceed 8 tablets in 24 hours. Do not use for more than 2 weeks without medical advice. May interfere with absorption of other medicines — take 2 hours apart.'],
    'kremil-s'             => ['Kremil-S is an antacid/antiflatulent containing Aluminium hydroxide, Magnesium hydroxide, and Simethicone — used for hyperacidity, heartburn, and gas. Typical dose: 1–2 tablets after meals and at bedtime.', 'Do not exceed 8 tablets in 24 hours. Do not use for more than 2 weeks without medical advice. Separate from other medicines by 2 hours.'],
    'omeprazole'           => ['Omeprazole (Losec, Omepron) is a proton pump inhibitor (PPI) that reduces stomach acid production. Used for GERD, peptic ulcers, H. pylori eradication, and Zollinger-Ellison syndrome. Standard dose: 20mg once daily, 30 minutes before breakfast.', 'Long-term use (>1 year) may reduce magnesium, Vitamin B12, and increase fracture risk. Do not crush or chew enteric-coated capsules. See a doctor if symptoms persist beyond 2 weeks.'],
    'lansoprazole'         => ['Lansoprazole (Prevacid) 30mg once daily is a PPI used for GERD, peptic ulcer disease, and H. pylori eradication. Take 30–60 minutes before meals.', 'Similar precautions to omeprazole. Long-term use requires medical supervision. Do not crush capsule — can be opened and granules sprinkled on soft food if needed.'],
    'pantoprazole'         => ['Pantoprazole (Protonix, Nexpro) 40mg once daily is a PPI for GERD and peptic ulcer disease. Often preferred over omeprazole due to fewer drug interactions.', 'Take before meals. Long-term use requires monitoring. Available in IV form for hospitalised patients with GI bleeding.'],
    'famotidine'           => ['Famotidine (Pepcid) 20–40mg at bedtime is an H2 receptor blocker that reduces stomach acid. Used for heartburn, GERD, and peptic ulcer — faster acting than PPIs but less potent for severe acid conditions.', 'May be taken with or without food. Reduce dose in kidney impairment. Good option for occasional heartburn not requiring long-term PPI use.'],
    'ranitidine'           => ['Ranitidine (Zantac) was an H2 blocker for acid reflux and ulcers. Note: ranitidine was recalled globally (including in the Philippines) in 2019–2020 due to NDMA contamination. Famotidine or Cimetidine are used as alternatives.', 'Ranitidine products have been recalled. Do not use old stock. Use Famotidine or consult your doctor for alternatives.'],
    'metoclopramide'       => ['Metoclopramide (Plasil) 10mg 3× daily before meals is used for nausea, vomiting, gastroparesis, and heartburn. It speeds up gastric emptying.', 'Prescription often required. Do not use for more than 3 months — risk of tardive dyskinesia (involuntary movements). Not for children under 1 year.'],
    'domperidone'          => ['Domperidone (Motilium) 10mg before meals and at bedtime is used for nausea, vomiting, bloating, and slow gastric emptying. It is safer than Metoclopramide with fewer CNS effects.', 'Prescription required for some doses. Use the lowest effective dose for the shortest time. May affect heart rhythm at high doses.'],
    'loperamide'           => ['Loperamide (Diatabs, Imodium) 2mg initially, then 1mg after each loose stool (max 8mg/day) is used for acute non-infectious diarrhoea.', 'Do not use if diarrhoea is bloody or accompanied by high fever. Seek medical attention if diarrhoea persists more than 2 days. Do not give to children under 2.'],
    'diatabs'              => ['Diatabs (Loperamide 2mg) is the most popular anti-diarrhoeal in the Philippines. Used for acute, non-infectious loose stools. Take 2 tablets initially, then 1 after each loose stool (max 8 tablets/day).', 'Do not use for bloody diarrhoea, high fever, or in children under 2. Seek medical help if symptoms last more than 2 days.'],
    'bisacodyl'            => ['Bisacodyl (Dulcolax) 5–10mg tablet at bedtime or 10mg suppository acts within 6–12 hours (tablet) or 15–60 minutes (suppository) for constipation relief.', 'Do not use daily for more than 1 week. May cause stomach cramps. Swallow tablets whole — do not crush. Not for children under 6 without medical advice.'],
    'lactulose'            => ['Lactulose 15–30ml once daily is an osmotic laxative for constipation. It is safe for prolonged use and suitable for children and the elderly. Also used to reduce ammonia in hepatic encephalopathy.', 'May cause bloating and gas initially. Can take 24–48 hours before effect. Adjust dose to achieve 2–3 soft stools per day.'],
    'simethicone'          => ['Simethicone (Mylicon, Phazyme) 80–125mg after meals and at bedtime relieves gas, bloating, and flatulence by breaking up gas bubbles in the GI tract.', 'Very safe — not absorbed by the body. Kremil-S contains simethicone combined with antacids. Suitable for infants (40mg drops) as well as adults.'],
 
    // ── Cough / Cold / Respiratory ────────────────────────────────────────────
    'carbocisteine'        => ['Carbocisteine (Solmux, Flemex) 500mg 3× daily is a mucolytic agent that thins respiratory mucus. Used for productive cough with phlegm, bronchitis, COPD, and sinusitis.', 'Take with or after food. Not suitable for active peptic ulcer. Consult a doctor if symptoms persist beyond 1 week.'],
    'solmux'               => ['Solmux (Carbocisteine 500mg) is the most popular mucolytic in the Philippines for wet cough with phlegm. It loosens and thins mucus to make coughing more productive. Dose: 1 capsule 3× daily.', 'Take with food. May cause mild stomach upset. Not for active peptic ulcer. Consult a doctor if cough persists more than 1 week.'],
    'ambroxol'             => ['Ambroxol (Mucosolvan, Ambrox) is a mucoactive agent that stimulates surfactant production and clears mucus. Used for acute and chronic respiratory conditions with mucus. Dose: 30mg 3× daily (tablet) or syrup.', 'Generally well tolerated. Available in tablet, syrup, and drops. May cause mild nausea or stomach upset. Consult a doctor if used in children under 6.'],
    'guaifenesin'          => ['Guaifenesin is an expectorant that loosens chest congestion and makes coughs more productive. Found in many OTC cough medicines in the Philippines (Robitussin Chesty, Mucinex).', 'Drink plenty of water when taking guaifenesin to maximise its effect. Do not take more than the recommended dose.'],
    'dextromethorphan'     => ['Dextromethorphan (DXM) is an OTC cough suppressant for dry, non-productive cough. Found in Tuseran Forte, Robitussin DM, and many combination cold medicines.', 'Do not use for productive cough (with phlegm). Not for children under 4. Avoid in people taking MAOIs. May cause drowsiness at high doses.'],
    'salbutamol'           => ['Salbutamol (albuterol / Ventolin) is a short-acting beta-2 agonist bronchodilator — the most commonly used rescue inhaler for asthma and COPD in the Philippines. 2 puffs (200mcg) every 4–6 hours as needed.', 'Shake inhaler before use. Rinse mouth after use to prevent oral thrush. See a doctor immediately if you need it more than 3× per week — your asthma may be poorly controlled.'],
    'ventolin'             => ['Ventolin (Salbutamol 100mcg/puff inhaler) is the standard rescue inhaler for asthma attacks and exercise-induced bronchospasm in the Philippines.', 'Shake well before use. 2 puffs during acute attack. If symptoms do not improve after 2–3 puffs, seek emergency care. Clean the mouthpiece weekly.'],
    'montelukast'          => ['Montelukast (Singulair) 10mg once daily at bedtime is a leukotriene receptor antagonist used for maintenance therapy of asthma and allergic rhinitis. It prevents, not relieves, symptoms.', 'Prescription required. Not for acute attacks — use salbutamol for rescue. May cause neuropsychiatric side effects (mood changes, sleep disturbances) — report to your doctor.'],
    'budesonide'           => ['Budesonide (Pulmicort) inhaler is an inhaled corticosteroid for long-term asthma control. It reduces airway inflammation when used regularly. Available as inhaler and nebulising solution.', 'Rinse mouth after each use to prevent oral thrush. Do not stop using suddenly. Not a rescue inhaler — use salbutamol for acute attacks. Prescription required.'],
    'lagundi'              => ['Lagundi (Vitex negundo) is a DOH-approved Philippine herbal medicine for cough and mild asthma. Available as tablet (600mg) and syrup. It acts as a bronchodilator and anti-inflammatory.', 'Follow dosage on the package. Consult a doctor if cough persists more than 2 weeks or is accompanied by fever or blood. Not a replacement for standard asthma controller therapy.'],
    'neozep'               => ['Neozep (Phenylephrine + Chlorphenamine + Paracetamol) is a popular OTC combination cold medicine in the Philippines for runny nose, nasal congestion, sneezing, and fever.', 'Causes drowsiness — avoid driving. Do not combine with other paracetamol-containing products. Not for children under 12 without medical advice.'],
    'decolgen'             => ['Decolgen (Pseudoephedrine + Paracetamol + Chlorphenamine) is an OTC cold remedy in the Philippines for nasal congestion, runny nose, and fever.', 'Causes drowsiness. Avoid in hypertension or heart disease (pseudoephedrine raises blood pressure). Do not combine with other paracetamol products.'],
    'bioflu'               => ['Bioflu (Phenylephrine + Chlorphenamine + Paracetamol) is a popular OTC flu and cold medicine in the Philippines. Relieves nasal congestion, runny nose, sneezing, fever, and body aches.', 'Causes drowsiness — avoid driving. Do not combine with other paracetamol products. Not recommended with MAOIs or in uncontrolled hypertension.'],
    'tuseran'              => ['Tuseran Forte (Dextromethorphan + Phenylpropanolamine + Chlorphenamine) is a widely used OTC cough and cold combination medicine in the Philippines.', 'Causes drowsiness. Do not exceed the recommended dose. Not for children under 12 without medical advice.'],
 
    // ── Antihistamines ────────────────────────────────────────────────────────
    'cetirizine'           => ['Cetirizine (Zyrtec, Ritemed Cetirizine) 10mg once daily is a second-generation antihistamine for allergic rhinitis, urticaria, and allergic skin conditions. It is largely non-drowsy.', 'May cause mild drowsiness in some users. Avoid alcohol. Reduce dose in kidney impairment. Generally safe for adults and children ≥6 years (5mg for ages 2–5).'],
    'loratadine'           => ['Loratadine (Claritin) 10mg once daily is a non-drowsy second-generation antihistamine for allergic rhinitis and chronic urticaria.', 'Take at the same time each day. One of the safest antihistamines for daytime use. Adjust dose in liver impairment.'],
    'fexofenadine'         => ['Fexofenadine (Telfast 120mg or 180mg) once daily is a third-generation, non-drowsy antihistamine for seasonal allergic rhinitis and urticaria. Very low CNS side effects.', 'Avoid taking with fruit juices (grapefruit, orange, apple) — reduces absorption. Suitable for people who need to remain fully alert.'],
    'diphenhydramine'      => ['Diphenhydramine (Benadryl 25–50mg) is a first-generation antihistamine for allergic reactions, insomnia, and motion sickness. Effective but causes significant drowsiness.', 'Causes marked drowsiness — do not drive or operate machinery. Not for use in children under 2. Avoid in elderly patients (risk of confusion and falls).'],
    'chlorphenamine'       => ['Chlorphenamine (Chlorpheniramine) is a first-generation antihistamine found in many OTC cold combinations (Neozep, Decolgen) for runny nose and sneezing.', 'Causes drowsiness. Do not drive or operate machinery. Avoid alcohol. Do not take in enlarged prostate or glaucoma.'],
    'hydroxyzine'          => ['Hydroxyzine (Iterax) 25–50mg is a first-generation antihistamine with anxiolytic and sedating properties. Used for anxiety, urticaria, and pruritus in the Philippines.', 'Causes significant drowsiness — do not drive. Prescription required for anxiety indication. Not for children under 6 without medical supervision.'],
 
    // ── Cardiovascular ────────────────────────────────────────────────────────
    'amlodipine'           => ['Amlodipine (Norvasc) 5–10mg once daily is a calcium channel blocker widely used in the Philippines for hypertension and stable angina. It relaxes and widens blood vessels.', 'Prescription required. May cause ankle swelling, flushing, or headache. Do not stop suddenly without medical advice. Take at the same time each day.'],
    'losartan'             => ['Losartan (Cozaar) 50–100mg once daily is an ARB (angiotensin receptor blocker) used for hypertension and diabetic nephropathy. It is one of the most prescribed antihypertensives in the Philippines.', 'Prescription required. Do not use in pregnancy. Monitor potassium and kidney function. May cause dizziness on standing — rise slowly.'],
    'enalapril'            => ['Enalapril (Enacard, Renitec) 5–20mg once or twice daily is an ACE inhibitor for hypertension and heart failure. Widely used in Philippine public health facilities.', 'Prescription required. Do not use in pregnancy. May cause a persistent dry cough (switch to ARB if intolerable). Monitor kidney function and potassium.'],
    'atorvastatin'         => ['Atorvastatin (Lipitor) 10–80mg once daily at any time is a statin used to lower LDL cholesterol and reduce cardiovascular risk.', 'Prescription required. Avoid grapefruit juice. Report unexplained muscle pain or weakness immediately — rarely can cause serious muscle breakdown (rhabdomyolysis). Avoid in pregnancy.'],
    'simvastatin'          => ['Simvastatin (Zocor) 10–40mg once daily in the evening is used to lower cholesterol and reduce the risk of cardiovascular events.', 'Prescription required. Do not take with grapefruit juice. Report muscle pain. Many drug interactions — check with your doctor or pharmacist.'],
    'metoprolol'           => ['Metoprolol (Betaloc, Lopresor) 25–100mg once or twice daily is a cardioselective beta-blocker used for hypertension, angina, heart failure, and arrhythmias.', 'Prescription required. Do not stop suddenly — taper dose. May cause fatigue, bradycardia, and cold extremities. Use with caution in asthma or COPD.'],
 
    // ── Diabetes ──────────────────────────────────────────────────────────────
    'metformin'            => ['Metformin (Glucophage) 500mg–1,000mg twice daily with meals is the first-line oral antidiabetic for Type 2 diabetes. It reduces hepatic glucose production and improves insulin sensitivity.', 'Prescription required. Take with food to reduce GI side effects. Do not use in severe kidney impairment. Hold before contrast imaging procedures. May reduce Vitamin B12 absorption with long-term use.'],
    'glimepiride'          => ['Glimepiride 1–4mg once daily before breakfast is a sulphonylurea that stimulates insulin secretion. Used for Type 2 diabetes when metformin alone is insufficient.', 'Prescription required. Risk of hypoglycaemia — do not skip meals. Monitor blood sugar regularly.'],
    'insulin'              => ['Insulin is essential for Type 1 diabetes and used in advanced Type 2 diabetes. Types available in the Philippines: Rapid-acting (Novorapid, Humalog), Intermediate (NPH/Humulin N), Long-acting (Lantus/Glargine, Tresiba).', 'Prescription and proper training required. Store unopened insulin in the refrigerator (2–8°C). Rotate injection sites. Monitor blood sugar regularly. Know the signs of hypoglycaemia.'],
 
    // ── Thyroid ───────────────────────────────────────────────────────────────
    'levothyroxine'        => ['Levothyroxine (Synthroid, Euthyrox) is used for hypothyroidism (underactive thyroid). Take on an empty stomach 30–60 minutes before breakfast with a full glass of water.', 'Prescription required. Take at the same time every day. Antacids, iron, and calcium supplements interfere with absorption — take 4 hours apart. Regular TSH monitoring required.'],
 
    // ── Vitamins / Minerals ───────────────────────────────────────────────────
    'ascorbic acid'        => ['Ascorbic acid (Vitamin C) supports immune function, skin health, iron absorption, and wound healing. Typical adult dose: 500mg–1,000mg once daily. Widely available OTC in the Philippines (Cecon, Clusivol, Fern-C).', 'High doses >2,000mg/day may cause diarrhoea and increase kidney stone risk in susceptible individuals. Water-soluble — excess is generally excreted safely.'],
    'vitamin c'            => ['Vitamin C (Ascorbic Acid) 500mg–1,000mg once daily supports immunity, collagen synthesis, and iron absorption. Common Philippine brands: Cecon, Fern-C, Clusivol 500.', 'Generally safe. Doses >2,000mg/day may cause GI upset. Best absorbed in divided doses. Get from fruits and vegetables when possible.'],
    'ferrous sulfate'      => ['Ferrous sulfate 325mg (65mg elemental iron) once or twice daily is used to treat iron-deficiency anaemia. Take on an empty stomach with Vitamin C to enhance absorption.', 'May cause dark stools, constipation, nausea. Take 2 hours apart from antacids, dairy, or other minerals. See a doctor before supplementing — confirm anaemia with blood test first.'],
    'folic acid'           => ['Folic acid 400–800mcg daily is essential for pregnant women to prevent neural tube defects in the baby. Also used to treat megaloblastic anaemia. Available OTC in the Philippines.', 'Start at least 1 month before conception and continue through the first trimester. Higher doses (5mg) are prescribed for women with previous neural tube defect pregnancies.'],
    'calcium carbonate'    => ['Calcium carbonate (Caltrate, Osteocare) 500–1,200mg daily is used to prevent and treat calcium deficiency, osteoporosis, and as a phosphate binder in kidney disease.', 'Take with meals for best absorption. Can cause constipation and bloating. Do not take at the same time as iron supplements, thyroid medicines, or certain antibiotics — take 2 hours apart.'],
    'vitamin d3'           => ['Vitamin D3 (cholecalciferol) 1,000–2,000 IU daily supports calcium absorption, bone health, immune function, and mood. Many Filipinos are Vitamin D insufficient despite living in a sunny country.', 'Take with a fatty meal for best absorption. Toxicity is possible with very high doses (>10,000 IU/day long-term). Have blood 25-OH Vitamin D levels checked if you suspect deficiency.'],
    'zinc'                 => ['Zinc 10–25mg daily supports immune function, wound healing, taste/smell, and reproductive health. Used as an adjunct in diarrhoea treatment for children (ORS + Zinc reduces duration).', 'High doses (>40mg/day) can interfere with copper absorption. Take with food if stomach upset occurs. Do not combine with iron supplements or antibiotics (take 2 hours apart).'],
    'b complex'            => ['B-complex vitamins (Neurobion, Becozyme, Stresstabs) contain B1 (Thiamine), B2 (Riboflavin), B3 (Niacin), B6, B12, and Folic acid. Used for nerve health, energy metabolism, and anaemia prevention.', 'Generally safe. B vitamins are water-soluble — excess is excreted. High-dose B6 (>200mg/day long-term) can cause peripheral neuropathy.'],
    'neurobion'            => ['Neurobion (Vitamins B1+B6+B12) is one of the most popular B-complex supplements in the Philippines. Used for peripheral neuropathy, nerve pain, and vitamin B deficiency.', 'Available in regular and forte formulations. Generally safe. Follow recommended dosage. See a doctor for persistent nerve symptoms.'],
 
    // ── Skin Topicals ─────────────────────────────────────────────────────────
    'betadine'             => ['Betadine (Povidone-iodine) solution 10% is used as a topical antiseptic for wound cleaning, minor cuts, abrasions, and skin infections. Also available as gargle, surgical scrub, and feminine wash.', 'Do not use in deep puncture wounds or near the eyes without medical guidance. Not for use in newborns. Prolonged use on large areas may affect thyroid function. Clean wound with water first before applying.'],
    'clotrimazole'         => ['Clotrimazole (Canesten) cream 1% applied twice daily is used for fungal skin infections — ringworm, athlete\'s foot, jock itch, and cutaneous candidiasis. Apply for 2–4 weeks even after symptoms clear.', 'For external use only. Avoid contact with eyes. Complete the full course even if symptoms improve early. Consult a doctor if no improvement after 4 weeks.'],
    'hydrocortisone'       => ['Hydrocortisone cream 1% (OTC) reduces inflammation and itching in mild eczema, contact dermatitis, insect bites, and mild allergic rashes. Apply a thin layer to the affected area 2× daily.', 'Do not use on the face for more than 1 week. Do not apply to infected skin. Prolonged use causes skin thinning. For children under 2, consult a doctor before use.'],
    'mupirocin'            => ['Mupirocin (Bactroban) 2% cream is a topical antibiotic used for impetigo, small infected wounds, and secondary skin infections caused by Staphylococcus and Streptococcus. Apply 3× daily for 5–10 days.', 'For external use only. Prescription required. Do not use inside the nose without medical advice. Complete the full course.'],
    'calamine'             => ['Calamine lotion provides soothing relief for itching due to insect bites, chickenpox, mild sunburn, and contact dermatitis. Shake well before use and apply to affected areas.', 'For external use only. Avoid contact with eyes. Safe for most ages. If itching is severe or widespread, also consider an oral antihistamine.'],
 
    // ── Antispasmodics / GI motility ──────────────────────────────────────────
    'buscopan'             => ['Buscopan (Hyoscine Butylbromide 10mg) is an antispasmodic for abdominal cramping, IBS pain, renal colic, biliary colic, and menstrual cramps. Dose: 1–2 tablets 3× daily.', 'May cause dry mouth, blurred vision, urinary retention, or rapid heartbeat. Not for children under 6. Not for use in glaucoma. Prescription required for injectable form.'],
    'hyoscine'             => ['Hyoscine Butylbromide (Buscopan) 10mg 3× daily relieves smooth muscle spasm in the GI and urinary tracts. Used for abdominal cramps, IBS, and menstrual pain.', 'May cause anticholinergic side effects: dry mouth, constipation, blurred vision. Not for use in glaucoma or prostate enlargement.'],
 
    // ── Topical pain relievers ─────────────────────────────────────────────────
    'counterpain'          => ['Counterpain cream (Methyl salicylate + Thymol + Eugenol + Menthol) is a topical analgesic balm widely used in the Philippines for muscle pain, joint pain, backache, and sprains. Apply and massage gently to the affected area.', 'For external use only. Avoid contact with eyes and mucous membranes. Do not apply to broken skin. Do not use with tight bandaging.'],
    'diclofenac gel'       => ['Diclofenac gel (Voltaren Emulgel) 1% is a topical NSAID for localised muscle and joint pain, minor sports injuries, and osteoarthritis. Apply 2–4× daily to the painful area.', 'For external use only. Wash hands after application. Fewer systemic side effects than oral NSAIDs. Avoid on broken skin or near eyes.'],
    'efficascent'          => ['Efficascent Oil (Methyl salicylate + Menthol + Eucalyptus oil) is a popular Filipino topical liniment for muscle pain, backache, arthritis, sprains, and headache. Apply and massage to the affected area.', 'For external use only. Avoid contact with eyes. Do not apply to open wounds. Popular household remedy in Philippine homes.'],
 
    // ── Sleep / Anxiety ───────────────────────────────────────────────────────
    'melatonin'            => ['Melatonin 3–10mg taken 30–60 minutes before bedtime helps regulate the sleep-wake cycle. Used for jet lag, shift work sleep disorder, and occasional insomnia. Available OTC in the Philippines.', 'Start with the lowest dose (0.5–3mg). Generally safe for short-term use. May cause drowsiness the next morning at higher doses. Consult a doctor for children and long-term use.'],
 
    // ── Eye drops ─────────────────────────────────────────────────────────────
    'systane'              => ['Systane Ultra eye drops are lubricating artificial tears used for dry eye syndrome, eye irritation from screens, wind, or smoke. Available OTC in Philippine pharmacies.', 'Instil 1–2 drops in affected eye(s) as needed. Remove contact lenses before use (reinsert after 15 minutes). If used more than 4× daily for more than 2 weeks, consult an eye doctor.'],
    'visine'               => ['Visine (Tetrahydrozoline) eye drops relieve eye redness caused by minor irritation (smoke, dust, screens). 1–2 drops up to 4× daily.', 'Do not use for more than 3–4 consecutive days — rebound redness can occur. Not a treatment for infections or serious eye conditions. See an ophthalmologist for persistent redness.'],
];
 
// ── STEP 6 matching logic ─────────────────────────────────────────────────────
$qLow  = strtolower(trim($query));
$qLow2 = strtolower(trim($message));

// Match symptom map
$smMatch = null;
foreach ($symptom_map as $k => $v) {
    if (strpos($qLow2, $k) !== false || strpos($qLow, $k) !== false) {
        $smMatch = $v;
        break;
    }
}
// Also try exact / partial match on query
if ($smMatch === null) {
    foreach ($symptom_map as $k => $v) {
        if ($qLow2 === $k || $qLow === $k) {
            $smMatch = $v;
            break;
        }
    }
}

// Check symptom map first (higher priority for general questions)
if ($smMatch !== null) {
    $out = $smMatch[0] . "\n\nImportant warnings: " . $smMatch[1];
    echo json_encode(['success' => true, 'source' => 'symptom_kb', 'reply' => pharsayo_chat_append_reminder($out, $lang), 'sources' => $baseSources]);
    exit;
}

// Then check drug names
$hcMatch = null;
foreach ($hardcoded as $k => $v) {
    if ($qLow2 === $k || $qLow === $k) { $hcMatch = $v; break; }
}
if ($hcMatch === null) {
    foreach ($hardcoded as $k => $v) {
        if (strpos($qLow2, $k) !== false || strpos($k, $qLow2) !== false ||
            strpos($qLow,  $k) !== false || strpos($k, $qLow)  !== false) {
            $hcMatch = $v; break;
        }
    }
}

if ($hcMatch !== null) {
    $out = $hcMatch[0] . "\n\nImportant warnings: " . $hcMatch[1];
    echo json_encode(['success' => true, 'source' => 'hardcoded', 'reply' => pharsayo_chat_append_reminder($out, $lang), 'sources' => $baseSources]);
    exit;
}

// ── STEP 7: Absolute last resort ──────────────────────────────────────────────
pharsayo_debug('step', 'all sources failed for: ' . $query);

// Provide a diagnostic hint to help the admin set up keys
$keyHint = '';
if ($gemini_key === '' && $google_cse_key === '') {
    $keyHint = ' (Note to admin: Set GEMINI_API_KEY and/or GOOGLE_CSE_KEY + GOOGLE_CSE_CX as environment variables to enable live AI responses.)';
}

$out = $lang === 'en'
    ? "I couldn't retrieve live information for \"{$query}\" right now. For accurate guidance, please search for \"{$query}\" on Medscape (medscape.com), Drugs.com, or WebMD — or ask your local pharmacist or doctor." . $keyHint
    : "Hindi ko makuha ang live na impormasyon para sa \"{$query}\" ngayon. Mangyaring maghanap ng \"{$query}\" sa Medscape, Drugs.com, o WebMD — o magtanong sa iyong parmaseutiko o doktor.";

echo json_encode(['success' => true, 'source' => 'none', 'reply' => pharsayo_chat_append_reminder($out, $lang), 'sources' => $baseSources]);
exit;