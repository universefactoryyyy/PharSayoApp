<?php
include_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../lib/medication_lookup.php';
require_once __DIR__ . '/../lib/medication_kb.php';

$body = json_decode(file_get_contents('php://input'));
$message = isset($body->message) ? trim((string)$body->message) : '';
$lang = (isset($body->lang) && (string)$body->lang === 'en') ? 'en' : 'fil';
$messages = [];
if (!empty($body->messages) && is_array($body->messages)) {
    foreach ($body->messages as $m) {
        if (!is_object($m)) {
            continue;
        }
        $role = isset($m->role) ? trim((string)$m->role) : '';
        $content = isset($m->content) ? trim((string)$m->content) : '';
        if ($content === '') {
            continue;
        }
        if ($role !== 'user' && $role !== 'bot') {
            continue;
        }
        if (strlen($content) > 1400) {
            $content = substr($content, 0, 1400);
        }
        $messages[] = ['role' => $role, 'content' => $content];
        if (count($messages) >= 12) {
            break;
        }
    }
}

if ($message === '') {
    echo json_encode([
        'success' => false,
        'message' => $lang === 'en' ? 'Please type a medicine name or question.' : 'Mag-type ng pangalan ng gamot o tanong.',
    ]);
    exit;
}

if (strlen($message) > 500) {
    $message = substr($message, 0, 500);
}

function pharsayo_ai_chat_extract_query($msg) {
    $msg = trim((string)$msg);
    $m = [];
    if (preg_match('/\bwhat\s+is\s+(.+?)\s+for\b/i', $msg, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/\bpara\s+saan\s+ang\s+(.+?)(\?|$)/i', $msg, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/\bano\s+ang\s+(.+?)(\?|$)/i', $msg, $m)) {
        return trim($m[1]);
    }
    return $msg;
}

function pharsayo_ai_chat_wikipedia_fallback($query, $timeout_sec = 10) {
    $q = trim((string)$query);
    if ($q === '') {
        return null;
    }
    $searchUrl = 'https://en.wikipedia.org/w/api.php?action=query&list=search&format=json&utf8=1&srlimit=2&srsearch=' . rawurlencode($q);
    $search = pharsayo_http_get_json($searchUrl, $timeout_sec);
    if (empty($search['query']['search'][0]['title'])) {
        return null;
    }
    $title = (string)$search['query']['search'][0]['title'];
    $summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode(str_replace(' ', '_', $title));
    $summary = pharsayo_http_get_json($summaryUrl, $timeout_sec);
    if (empty($summary['extract'])) {
        return null;
    }
    return [
        'title' => $title,
        'extract' => (string)$summary['extract'],
        'url' => isset($summary['content_urls']['desktop']['page']) ? (string)$summary['content_urls']['desktop']['page'] : ('https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title))),
    ];
}

function pharsayo_ai_chat_sources_basic($query, $rxcui = '', $wikiUrl = '') {
    $sources = [];
    $sources[] = [
        'title' => 'RxNav',
        'url' => 'https://rxnav.nlm.nih.gov/REST/drugs.json?name=' . rawurlencode((string)$query),
    ];
    if ((string)$rxcui !== '') {
        $sources[] = [
            'title' => 'openFDA (label search)',
            'url' => 'https://api.fda.gov/drug/label.json?search=openfda.rxcui%3A%22' . rawurlencode((string)$rxcui) . '%22&limit=1',
        ];
    } else {
        $sources[] = [
            'title' => 'openFDA (label search)',
            'url' => 'https://api.fda.gov/drug/label.json?search=' . rawurlencode((string)$query) . '&limit=1',
        ];
    }
    if (trim((string)$wikiUrl) !== '') {
        $sources[] = [
            'title' => 'Wikipedia',
            'url' => (string)$wikiUrl,
        ];
    }
    return $sources;
}

function pharsayo_ai_chat_reminder_text($lang) {
    return $lang === 'en'
        ? "Reminder: You must still adhere in accordance to your doctor's prescription and guidelines."
        : "Paalala: Dapat mo pa ring sundin ang reseta at mga gabay ng iyong doktor.";
}

function pharsayo_ai_chat_append_reminder($text, $lang) {
    $text = trim((string)$text);
    $reminder = pharsayo_ai_chat_reminder_text($lang);
    if ($text === '') {
        return $reminder;
    }
    if (stripos($text, 'Reminder:') !== false || stripos($text, 'Paalala:') !== false) {
        return $text;
    }
    return $text . "\n\n" . $reminder;
}

function pharsayo_ai_chat_google_link($query) {
    $q = trim((string)$query);
    if ($q === '') {
        return '';
    }
    return 'https://www.google.com/search?q=' . rawurlencode($q);
}

function pharsayo_ai_chat_extract_grounding_sources($result) {
    $out = [];
    if (!is_array($result) || empty($result['candidates']) || !is_array($result['candidates'])) {
        return $out;
    }
    foreach ($result['candidates'] as $cand) {
        if (!is_array($cand) || empty($cand['groundingMetadata']) || !is_array($cand['groundingMetadata'])) {
            continue;
        }
        $gm = $cand['groundingMetadata'];
        if (!empty($gm['groundingChunks']) && is_array($gm['groundingChunks'])) {
            foreach ($gm['groundingChunks'] as $chunk) {
                if (!is_array($chunk) || empty($chunk['web']) || !is_array($chunk['web'])) {
                    continue;
                }
                $title = isset($chunk['web']['title']) ? trim((string)$chunk['web']['title']) : '';
                $uri = isset($chunk['web']['uri']) ? trim((string)$chunk['web']['uri']) : '';
                if ($uri === '') {
                    continue;
                }
                $key = $uri;
                if (!isset($out[$key])) {
                    $out[$key] = [
                        'title' => $title !== '' ? $title : 'Web source',
                        'url' => $uri,
                    ];
                }
                if (count($out) >= 8) {
                    break 2;
                }
            }
        }
    }
    return array_values($out);
}

function pharsayo_ai_chat_build_local_reply($lang, $query, $online, $local, $wiki) {
    $q = trim((string)$query);
    if ($q === '') {
        return pharsayo_ai_chat_reminder_text($lang);
    }

    if (is_array($online)) {
        $name = trim((string)($online['display_name'] ?? $q));
        $purpose = trim((string)($online['purpose_en'] ?? ''));
        $warnings = trim((string)($online['precautions_en'] ?? ''));
        if ($lang === 'en') {
            $out = $name . " is commonly used for: " . ($purpose !== '' ? pharsayo_truncate_text($purpose, 420) : 'See linked references.');
            if ($warnings !== '') {
                $out .= "\n\nImportant warnings: " . pharsayo_truncate_text($warnings, 260);
            }
            return pharsayo_ai_chat_append_reminder($out, $lang);
        }
        $out = $name . " ay karaniwang ginagamit para sa: " . ($purpose !== '' ? pharsayo_truncate_text($purpose, 380) : 'Tingnan ang mga source link.');
        if ($warnings !== '') {
            $out .= "\n\nMahalagang babala: " . pharsayo_truncate_text($warnings, 230);
        }
        return pharsayo_ai_chat_append_reminder($out, $lang);
    }

    if (is_array($local)) {
        $name = trim((string)($local['name'] ?? $q));
        $purpose = trim((string)($local['purpose_en'] ?? ''));
        $warnings = trim((string)($local['precautions_en'] ?? ''));
        if ($lang === 'en') {
            $out = $name . ": " . ($purpose !== '' ? pharsayo_truncate_text($purpose, 380) : 'Information found in local references.');
            if ($warnings !== '') {
                $out .= "\n\nImportant warnings: " . pharsayo_truncate_text($warnings, 240);
            }
            return pharsayo_ai_chat_append_reminder($out, $lang);
        }
        $out = $name . ": " . ($purpose !== '' ? pharsayo_truncate_text($purpose, 340) : 'May impormasyon sa local references.');
        if ($warnings !== '') {
            $out .= "\n\nMahalagang babala: " . pharsayo_truncate_text($warnings, 220);
        }
        return pharsayo_ai_chat_append_reminder($out, $lang);
    }

    if (is_array($wiki)) {
        $extract = trim((string)($wiki['extract'] ?? ''));
        if ($extract !== '') {
            $out = $lang === 'en'
                ? pharsayo_truncate_text($extract, 520)
                : ('Mula sa pampublikong sanggunian: ' . pharsayo_truncate_text($extract, 460));
            return pharsayo_ai_chat_append_reminder($out, $lang);
        }
    }

    $out = $lang === 'en'
        ? "I could not get a full AI response right now, but you can use the links below to check trusted medicine references for \"" . $q . "\"."
        : "Hindi ko makuha ang buong AI response ngayon, pero maaari mong gamitin ang links sa ibaba para sa mapagkakatiwalaang impormasyon tungkol sa \"" . $q . "\".";
    return pharsayo_ai_chat_append_reminder($out, $lang);
}

$query = pharsayo_ai_chat_extract_query($message);
$query = pharsayo_truncate_text($query, 120);

$wikiUrl = '';
$rxcui = '';
$context = '';
$wiki = null;
$local = null;
$online = pharsayo_lookup_medication_online($query, $query);
if (is_array($online)) {
    $rxcui = isset($online['rxcui']) ? trim((string)$online['rxcui']) : '';
    $context = "Reference (public drug labeling summary):\n"
        . "Name: " . (string)($online['display_name'] ?? $query) . "\n"
        . "Frequency: " . (string)($online['frequency_hint'] ?? '') . "\n"
        . "Purpose (EN): " . (string)($online['purpose_en'] ?? '') . "\n"
        . "Warnings (EN): " . (string)($online['precautions_en'] ?? '') . "\n";
} else {
    $local = pharsayo_try_local_kb($query);
    if (is_array($local)) {
        $context = "Reference (local list):\n"
            . "Name: " . (string)($local['name'] ?? $query) . "\n"
            . "Frequency: " . (string)($local['frequency'] ?? '') . "\n"
            . "Purpose (EN): " . (string)($local['purpose_en'] ?? '') . "\n"
            . "Warnings (EN): " . (string)($local['precautions_en'] ?? '') . "\n";
    } else {
        $wiki = pharsayo_ai_chat_wikipedia_fallback($query);
        if (is_array($wiki)) {
            $wikiUrl = (string)$wiki['url'];
            $context = "Reference (Wikipedia summary):\n"
                . pharsayo_truncate_text((string)$wiki['extract'], 900);
        }
    }
}

$api_key = trim((string)getenv('GEMINI_API_KEY'));
if ($api_key === '') {
    $api_key = 'AIzaSyCNijqrvhm4tsP-TP9XQk8K25VDZlAslzg';
}

if ($api_key === '' || strpos($api_key, 'YOUR_GEMINI') !== false) {
    echo json_encode([
        'success' => false,
        'message' => $lang === 'en'
            ? 'Gemini API key is missing on the server.'
            : 'Walang Gemini API key sa server.',
    ]);
    exit;
}

$instruction = $lang === 'en'
    ? "You are a careful pharmacist assistant for educational use. Use Google Search grounding to answer medicine questions like a mini search engine for patients. Answer briefly and clearly. If unsure, say you are not sure. Do not give personalized dosing; tell the user to follow their prescription label and consult a licensed clinician. If you mention risks or interactions, tell them to confirm with a pharmacist/doctor."
    : "Ikaw ay maingat na pharmacist assistant para sa pang-edukasyon lamang. Gamitin ang Google Search grounding para sumagot na parang mini search engine para sa mga pasyente. Sagutin nang maikli at malinaw. Kapag hindi sigurado, sabihin na hindi ka sigurado. Huwag magbigay ng personal na dosing; sabihin na sundin ang reseta/label at kumonsulta sa doktor o pharmacist. Kung may babala o interaction, ipasuri sa lisensyadong clinician.";

$sources = pharsayo_ai_chat_sources_basic($query, $rxcui, $wikiUrl);
$sourcesText = '';
foreach ($sources as $s) {
    if (!empty($s['url'])) {
        $sourcesText .= "- " . (string)$s['title'] . ": " . (string)$s['url'] . "\n";
    }
}

$first = $instruction . "\n\n";
if (trim($context) !== '') {
    $first .= "Context:\n" . $context . "\n\n";
}
if ($sourcesText !== '') {
    $first .= "Reference links:\n" . $sourcesText . "\n";
}

$contents = [];
$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $first]],
];

foreach ($messages as $m) {
    $role = $m['role'] === 'bot' ? 'model' : 'user';
    $contents[] = [
        'role' => $role,
        'parts' => [['text' => (string)$m['content']]],
    ];
}

$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $message]],
];

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$api_key}";
$result = pharsayo_http_post_json($url, [
    'contents' => $contents,
    'tools' => [
        ['google_search' => (object)[]],
    ],
    'generationConfig' => [
        'temperature' => 0.2,
        'maxOutputTokens' => 650,
    ],
]);

if (!$result) {
    $fallbackReply = pharsayo_ai_chat_build_local_reply($lang, $query, $online, $local, $wiki);
    $googleLink = pharsayo_ai_chat_google_link($query);
    $fallbackSources = $sources;
    if ($googleLink !== '') {
        $fallbackSources[] = [
            'title' => 'Google search: ' . $query,
            'url' => $googleLink,
        ];
    }
    echo json_encode([
        'success' => true,
        'source' => 'fallback_lookup',
        'reply' => $fallbackReply,
        'sources' => $fallbackSources,
    ]);
    exit;
}

$text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
$text = trim((string)$text);
if ($text === '') {
    $fallbackReply = pharsayo_ai_chat_build_local_reply($lang, $query, $online, $local, $wiki);
    $googleLink = pharsayo_ai_chat_google_link($query);
    $fallbackSources = $sources;
    if ($googleLink !== '') {
        $fallbackSources[] = [
            'title' => 'Google search: ' . $query,
            'url' => $googleLink,
        ];
    }
    echo json_encode([
        'success' => true,
        'source' => 'fallback_lookup',
        'reply' => $fallbackReply,
        'sources' => $fallbackSources,
    ]);
    exit;
}

if (strpos($text, '```') === 0) {
    $text = preg_replace('/^```(?:text)?\s*|\s*```$/i', '', $text);
    $text = trim((string)$text);
}

$text = pharsayo_ai_chat_append_reminder($text, $lang);
$sourcesFromGrounding = pharsayo_ai_chat_extract_grounding_sources($result);
$googleLink = pharsayo_ai_chat_google_link($query);
if ($googleLink !== '') {
    $sourcesFromGrounding[] = [
        'title' => $lang === 'en' ? ('Google search: ' . $query) : ('Google search: ' . $query),
        'url' => $googleLink,
    ];
}
$sourcesOut = [];
$seenUrls = [];
foreach (array_merge($sourcesFromGrounding, $sources) as $s) {
    if (!is_array($s)) {
        continue;
    }
    $u = isset($s['url']) ? trim((string)$s['url']) : '';
    if ($u === '' || isset($seenUrls[$u])) {
        continue;
    }
    $seenUrls[$u] = true;
    $sourcesOut[] = [
        'title' => isset($s['title']) && trim((string)$s['title']) !== '' ? trim((string)$s['title']) : 'Source',
        'url' => $u,
    ];
    if (count($sourcesOut) >= 10) {
        break;
    }
}

echo json_encode([
    'success' => true,
    'source' => 'gemini',
    'reply' => $text,
    'sources' => $sourcesOut,
]);
exit;

