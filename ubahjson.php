<?php
$inputFile = 'dictionary_JSON.json';
$outputFile = 'kbbi_words_simple.json';

echo "📂 Membaca file JSON KBBI...\n";
$dataRaw = file_get_contents($inputFile);
$data = json_decode($dataRaw, true);

if (!isset($data['dictionary'])) {
    die("❌ Struktur JSON tidak valid. Tidak ditemukan key 'dictionary'.\n");
}

echo "🔄 Memproses kata-kata...\n";
$words = [];
$total = count($data['dictionary']);

foreach ($data['dictionary'] as $index => $item) {
    $word = strtolower(trim($item['word']));
    if (strlen($word) < 2)
        continue; // abaikan kata 1 huruf

    // aturan: buang kata lebih dari 1 kata kecuali ada tanda hubung
    if (strpos($word, ' ') !== false) {
        // cek apakah ada tanda hubung sebelum spasi pertama
        $firstPart = explode(' ', $word)[0];
        if (strpos($firstPart, '-') !== false) {
            $word = $firstPart; // tetap ambil kata sebelum spasi
        } else {
            $word = $firstPart; // ambil kata pertama saja
        }
    }

    $words[$word] = true; // key array → unik

    if ($index % 10000 === 0) {
        echo "  ✔ Diproses: $index / $total\r";
    }
}

// ambil kata unik dan urutkan
$uniqueWords = array_keys($words);
sort($uniqueWords);

file_put_contents($outputFile, json_encode($uniqueWords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n✅ Selesai! Total kata unik: " . count($uniqueWords) . "\n";
echo "📄 File hasil: $outputFile\n";
