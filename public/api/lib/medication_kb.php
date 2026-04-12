<?php
/**
 * Optional local shortcuts only (common OTC / demo entries).
 * You do NOT need to add every drug here: scanning uses pharsayo_lookup_medication_online()
 * in medication_lookup.php (RxNav + OpenFDA) for any medicine name read from the label.
 */

function pharsayo_local_medication_kb(): array {
    return [
        'symdex' => [
            'name' => 'Symdex-D Forte',
            'dosage' => '25mg/2mg/500mg Tablet',
            'frequency' => 'Every 6 hours as needed',
            'purpose_fil' => 'Para sa baradong ilong, ubo, at sakit ng ulo (Decongestant/Antihistamine/Analgesic)',
            'purpose_en' => 'For nasal congestion, cough, and headache (Decongestant/Antihistamine/Analgesic)',
            'precautions_fil' => 'Maaaring magdulot ng pagkaantok. Huwag magmaneho pagkatapos uminom.',
            'precautions_en' => 'May cause drowsiness. Do not drive or operate machinery after taking.',
        ],
        'biogesic' => [
            'name' => 'Biogesic (Paracetamol)',
            'dosage' => '500mg',
            'frequency' => 'Every 4-6 hours as needed',
            'purpose_fil' => 'Para sa lagnat at sakit ng katawan o ulo (Analgesic/Antipyretic)',
            'purpose_en' => 'For fever and pain relief (Analgesic/Antipyretic)',
            'precautions_fil' => 'Huwag uminom ng higit sa 4,000mg sa loob ng 24 oras.',
            'precautions_en' => 'Do not exceed 4,000mg in 24 hours.',
        ],
        'neozep' => [
            'name' => 'Neozep Forte',
            'dosage' => 'Phenylephrine + Chlorphenamine + Paracetamol',
            'frequency' => 'Every 6 hours as needed',
            'purpose_fil' => 'Para sa sipon, baradong ilong, at sakit ng ulo',
            'purpose_en' => 'For common cold, nasal congestion, and headache',
            'precautions_fil' => 'Maaaring magdulot ng pagkaantok.',
            'precautions_en' => 'May cause drowsiness.',
        ],
        'bioflu' => [
            'name' => 'Bioflu',
            'dosage' => 'Phenylephrine + Chlorphenamine + Paracetamol',
            'frequency' => 'Every 6 hours as needed',
            'purpose_fil' => 'Para sa trangkaso, lagnat, at baradong ilong',
            'purpose_en' => 'For flu, fever, and nasal congestion',
            'precautions_fil' => 'Maaaring magdulot ng pagkaantok.',
            'precautions_en' => 'May cause drowsiness.',
        ],
        'alaxan' => [
            'name' => 'Alaxan FR',
            'dosage' => 'Ibuprofen + Paracetamol',
            'frequency' => 'Every 6 hours as needed',
            'purpose_fil' => 'Para sa matinding sakit ng katawan, kalamnan, at rayuma',
            'purpose_en' => 'For severe body aches, muscle pain, and rheumatism',
            'precautions_fil' => 'Inumin kasabay ng pagkain.',
            'precautions_en' => 'Take with food.',
        ],
        'kremils' => [
            'name' => 'Kremil-S',
            'dosage' => 'Aluminum + Magnesium + Simethicone',
            'frequency' => '1-2 tablets an hour after meals',
            'purpose_fil' => 'Para sa hyperacidity, heartburn, at sakit ng sikmura',
            'purpose_en' => 'For hyperacidity, heartburn, and stomach pain',
            'precautions_fil' => 'Huwag uminom ng higit sa 8 tableta sa loob ng 24 oras.',
            'precautions_en' => 'Do not exceed 8 tablets in 24 hours.',
        ],
        'solmux' => [
            'name' => 'Solmux (Carbocisteine)',
            'dosage' => '500mg',
            'frequency' => 'Every 8 hours',
            'purpose_fil' => 'Para sa ubo na may plema (Mucolytic)',
            'purpose_en' => 'For productive cough (Mucolytic)',
            'precautions_fil' => 'Kumonsulta sa doktor kung buntis.',
            'precautions_en' => 'Consult a doctor if pregnant.',
        ],
        'paracetamol' => [
            'name' => 'Paracetamol',
            'dosage' => '500mg',
            'frequency' => 'Every 4-6 hours as needed',
            'purpose_fil' => 'Para sa lagnat at pananakit ng katawan (Analgesic/Antipyretic)',
            'purpose_en' => 'For fever and pain relief (Analgesic/Antipyretic)',
            'precautions_fil' => 'Huwag uminom ng higit sa 4,000mg sa loob ng 24 oras. Iwasan ang alak.',
            'precautions_en' => 'Do not exceed 4,000mg in 24 hours. Avoid alcohol.',
        ],
        'mefenamic' => [
            'name' => 'Mefenamic Acid (Ponstan)',
            'dosage' => '500mg',
            'frequency' => 'Every 8 hours as needed',
            'purpose_fil' => 'Para sa katamtamang sakit gaya ng sakit ng ngipin o dysmenorrhea (NSAID)',
            'purpose_en' => 'For mild to moderate pain such as dental pain or dysmenorrhea (NSAID)',
            'precautions_fil' => 'Inumin kasabay ng pagkain. Huwag gamitin ng mahigit sa 7 araw.',
            'precautions_en' => 'Take with food. Do not use for more than 7 days.',
        ],
        'amoxicillin' => [
            'name' => 'Amoxicillin',
            'dosage' => '500mg',
            'frequency' => 'Every 8 hours for 7 days or as prescribed',
            'purpose_fil' => 'Antibiotic para sa impeksyon dulot ng bacteria.',
            'purpose_en' => 'Antibiotic for bacterial infections.',
            'precautions_fil' => 'Tapusin ang buong kurso ng gamot kahit maayos na ang pakiramdam.',
            'precautions_en' => 'Complete the full course of medicine even if you feel better.',
        ],
        'losartan' => [
            'name' => 'Losartan Potassium',
            'dosage' => '500mg or as prescribed',
            'frequency' => 'Once daily or as prescribed',
            'purpose_fil' => 'Para sa mataas na presyon ng dugo (Anti-hypertensive)',
            'purpose_en' => 'For high blood pressure (Anti-hypertensive)',
            'precautions_fil' => 'Huwag uminom kung buntis. Kumonsulta sa doktor para sa tamang dosis.',
            'precautions_en' => 'Do not take if pregnant. Consult a doctor for correct dosage.',
        ],
        'metformin' => [
            'name' => 'Metformin HCl',
            'dosage' => '500mg or 850mg',
            'frequency' => '1-2 times daily with meals',
            'purpose_fil' => 'Para sa kontrol ng asukal sa dugo (Anti-diabetic)',
            'purpose_en' => 'For blood sugar control (Anti-diabetic)',
            'precautions_fil' => 'Inumin kasabay ng pagkain upang maiwasan ang sakit ng tiyan.',
            'precautions_en' => 'Take with meals to avoid stomach upset.',
        ],
        'cetirizine' => [
            'name' => 'Cetirizine HCl',
            'dosage' => '10mg',
            'frequency' => 'Once daily',
            'purpose_fil' => 'Para sa allergy, pangangati, at bahing (Antihistamine)',
            'purpose_en' => 'For allergies, itching, and sneezing (Antihistamine)',
            'precautions_fil' => 'Maaaring magdulot ng bahagyang pagkaantok sa ibang tao.',
            'precautions_en' => 'May cause mild drowsiness in some people.',
        ],
        'multivitamins' => [
            'name' => 'Multivitamins + Minerals',
            'dosage' => '1 tablet',
            'frequency' => 'Once daily',
            'purpose_fil' => 'Supplement na bitamina para pampalakas ng resistensya.',
            'purpose_en' => 'Vitamin supplement to boost immune system.',
            'precautions_fil' => 'Pinakamainam inumin sa umaga pagkatapos kumain.',
            'precautions_en' => 'Best taken in the morning after a meal.',
        ],
        'ibuprofen' => [
            'name' => 'Ibuprofen',
            'dosage' => '400mg',
            'frequency' => 'Every 4-6 hours as needed for pain or fever',
            'purpose_fil' => 'Para sa pamamaga, lagnat, at matinding sakit ng katawan (NSAID)',
            'purpose_en' => 'For inflammation, fever, and severe pain relief (NSAID)',
            'precautions_fil' => 'Uminom pagkatapos kumain upang hindi mahapdi ang sikmura.',
            'precautions_en' => 'Take after meals to avoid stomach upset.',
        ],
        'salbutamol' => [
            'name' => 'Salbutamol',
            'dosage' => '2mg Tablet',
            'frequency' => '3-4 times a day',
            'purpose_fil' => 'Para sa hika at hirap sa paghinga (Anti-asthma)',
            'purpose_en' => 'For asthma and breathing difficulties (Anti-asthma)',
            'precautions_fil' => 'Maaaring magdulot ng panginginig o mabilis na tibok ng puso.',
            'precautions_en' => 'May cause tremors or rapid heartbeat.',
        ],
        'tylenol' => [
            'name' => 'Tylenol (Acetaminophen)',
            'dosage' => '500mg Extra Strength',
            'frequency' => '1-2 tablets every 6 hours',
            'purpose_fil' => 'Para sa matinding sakit at lagnat (Pain Reliever/Fever Reducer)',
            'purpose_en' => 'For severe pain and fever (Pain Reliever/Fever Reducer)',
            'precautions_fil' => 'Huwag uminom ng higit sa 6 na tableta sa loob ng 24 oras.',
            'precautions_en' => 'Do not take more than 6 tablets in 24 hours.',
        ],
        'loperamide' => [
            'name' => 'Loperamide (Imodium)',
            'dosage' => '2mg capsule or as on label',
            'frequency' => 'As directed for acute diarrhea; do not exceed label maximum',
            'purpose_fil' => 'Para sa pagtigil ng matinding pagtatae (antidiarrheal / antimotility).',
            'purpose_en' => 'For acute diarrhea (antidiarrheal; slows gut motility).',
            'precautions_fil' => 'Huwag gamitin kung may dugo sa dumi, matinding sakit ng tiyan, o lagnat nang walang payo ng doktor. Iwasan ang sobrang dosis.',
            'precautions_en' => 'Do not use if you have bloody stool, high fever, or severe abdominal pain unless a clinician advises. Avoid overdose; seek care if symptoms persist.',
        ],
        'carbocisteine' => [
            'name' => 'Carbocisteine',
            'dosage' => 'Typical adult: 375–750 mg two or three times daily (follow your product label)',
            'frequency' => 'Usually 2–3 times daily with meals or as prescribed',
            'purpose_fil' => 'Mucolytic: pinapadulas ang plema at tinutulungan alisin ang baradong plema sa baga at lalamunan (pangkaraniwang gamot sa ubo na may plema).',
            'purpose_en' => 'Mucolytic: thins and loosens mucus in the airways (often used for productive cough with thick phlegm).',
            'precautions_fil' => 'Huwag gamitin nang walang payo ng doktor kung may aktibong tisis o impeksyon sa baga, o kung buntis/nagpapasuso. Mag-ingat kung may ulcer sa sikmura. Sundin ang dosis sa label o reseta.',
            'precautions_en' => 'Seek medical advice before use if you have active peptic ulcer, severe respiratory infection, or are pregnant/breastfeeding. Use only as directed on the label or by your clinician.',
        ],
    ];
}

function pharsayo_local_keyword_map(): array {
    return [
        'paracetamol' => ['paracetamol', 'panadol', 'biogesic', 'tempra', 'acetaminophen', 'calpol', 'rexidol', 'saridon'],
        'mefenamic' => ['mefenamic', 'ponstan', 'dolfenal', 'pain', '500mg', 'gardan'],
        'amoxicillin' => ['amoxicillin', 'amoxil', 'antibiotic', '500mg'],
        'losartan' => ['losartan', 'potassium', 'hypertension', 'blood pressure', 'cozaar'],
        'metformin' => ['metformin', 'diabetes', 'sugar', '850mg', '500mg', 'glucophage'],
        'cetirizine' => ['cetirizine', 'virlix', 'allergy', 'antihistamine', '10mg', 'alerkid'],
        'multivitamins' => ['multivitamins', 'minerals', 'vitamin', 'supplement', 'enervon', 'revicon', 'centrum', 'stresstabs', 'conzace'],
        'biogesic' => ['biogesic', 'paracetamol', 'analgesic', 'antipyretic', '500mg'],
        'neozep' => ['neozep', 'forte', 'decongestant', 'antihistamine', 'paracetamol', 'phenylephrine'],
        'bioflu' => ['bioflu', 'flu', 'fever', 'congestion', 'paracetamol'],
        'alaxan' => ['alaxan', 'ibuprofen', 'paracetamol', 'muscle', 'pain'],
        'kremils' => ['kremils', 'antacid', 'hyperacidity', 'heartburn', 'simethicone'],
        'solmux' => ['solmux', 'carbocisteine', 'carbocysteine', 'mucolytic', '500mg'],
        'decolgen' => ['decolgen', 'clogged', 'nose', 'runny', 'paracetamol'],
        'tuseran' => ['tuseran', 'cough', 'cold', 'dextromethorphan'],
        'advil' => ['advil', 'ibuprofen', 'pain', 'fever', 'softgel'],
        'ponstan' => ['ponstan', 'mefenamic', 'acid', 'dental', 'pain'],
        'tempra' => ['tempra', 'paracetamol', 'fever', 'kids', 'children'],
        'calpol' => ['calpol', 'paracetamol', 'fever', 'kids', 'children'],
        'enervon' => ['enervon', 'vitamin', 'energy', 'b-complex'],
        'ceelin' => ['ceelin', 'ascorbic', 'vitamin c', 'zinc', 'kids'],
        'myra' => ['myra', 'vitamin e', 'tocopherol', 'skin'],
        'cherifer' => ['cherifer', 'growth', 'zinc', 'height'],
        'centrum' => ['centrum', 'multivitamins', 'complete'],
        'diatabs' => ['diatabs', 'loperamide', 'diarrhea'],
        'buscopan' => ['buscopan', 'hyoscine', 'stomach', 'cramp'],
        'gaviscon' => ['gaviscon', 'heartburn', 'reflux', 'acid'],
        'strepsils' => ['strepsils', 'sore', 'throat', 'lozenges'],
        'bactidol' => ['bactidol', 'gargle', 'mouth', 'throat'],
        'betadine' => ['betadine', 'povidone', 'iodine', 'wound'],
        'canesten' => ['canesten', 'clotrimazole', 'fungal', 'cream'],
        'vicks' => ['vicks', 'vaporub', 'cough', 'rub'],
        'potencee' => ['potencee', 'poten-cee', 'vitamin c', 'ascorbic'],
        'symdex' => ['symdex', 'forte', 'decongestant', 'antihistamine'],
        'ibuprofen' => ['ibuprofen', 'saphfen', '400mg', 'nsaid'],
        'salbutamol' => ['salbutamol', 'ricemed', '2mg', 'asthma'],
        'tylenol' => ['tylenol', 'acetaminophen', 'extra strength'],
        'loperamide' => ['loperamide', 'imodium', 'antimotility', 'antidiarrheal', 'diarrhea', '2mg'],
        'carbocisteine' => ['carbocisteine', 'carbocysteine', 'mucolytic', 'solmux', 'mucofen', 'transbroncho'],
    ];
}

function pharsayo_kb_fuzzy_word_match(string $input, string $target): bool {
    $input = preg_replace('/[^a-z0-9]/', '', strtolower($input));
    $target = preg_replace('/[^a-z0-9]/', '', strtolower($target));
    if (strlen($input) < 3) {
        return false;
    }
    if (strpos($target, $input) !== false || strpos($input, $target) !== false) {
        return true;
    }
    $dist = levenshtein($input, $target);
    $threshold = (int) floor(strlen($target) * 0.3);
    return $dist <= $threshold;
}

/**
 * @return array|null Full medication row if local KB matches OCR text, else null
 */
function pharsayo_try_local_kb(string $extractedText): ?array {
    $extractedText = trim($extractedText);
    if ($extractedText === '') {
        return null;
    }
    $db = pharsayo_local_medication_kb();
    $keyword_map = pharsayo_local_keyword_map();
    $lower_text = strtolower($extractedText);
    $words = preg_split('/[\s\n,.]+/', $lower_text);

    $found_id = null;
    $max_score = 0;
    foreach ($keyword_map as $id => $keywords) {
        $score = 0;
        foreach ($keywords as $kw) {
            foreach ($words as $word) {
                if (pharsayo_kb_fuzzy_word_match($word, $kw)) {
                    $score += (strlen($kw) > 4) ? 2 : 1;
                    break;
                }
            }
        }
        if ($score > $max_score) {
            $max_score = $score;
            $found_id = $id;
        }
    }

    if ($found_id !== null && $max_score >= 1 && isset($db[$found_id])) {
        return $db[$found_id];
    }
    return null;
}
