<?php
date_default_timezone_set('Asia/Jakarta');

function koreksiTypoCanggih($msg)
{
    $msg = strtolower(trim($msg));
    $msg = preg_replace('/\s+/', ' ', $msg);

    // 1. Dictionary besar (bisa ditambah lagi)
    $dictionary = array_merge(
        ['masuk', 'hadir', 'datang', 'ikut', 'oke', 'siap', 'izin', 'sakit', 'libur', 'cuti', 'off', 'kosong', 'job', 'kerja', 'kerjaan', 'pekerjaan', 'shift', 'jadwal', 'tidak', 'gak', 'ga', 'tak', 'bukan', 'belum', 'enggak', 'lupa', 'hari ini', 'kemarin', 'besok', 'lusa'],
        ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'],
        ['demam', 'pusing', 'flu', 'batuk', 'mual', 'lemas', 'pilek', 'sariawan', 'migren', 'permisi', 'acara', 'keperluan', 'urusan', 'tugas', 'projek', 'dinas', 'office', 'present', 'join', 'ready']
    );

    // split pesan per kata
    $words = explode(' ', $msg);
    $corrected = [];

    foreach ($words as $w) {
        // lewati angka (supaya "16" tidak dikoreksi jadi "ga")
        if (is_numeric($w)) {
            $corrected[] = $w;
            continue;
        }

        $closest = $w;
        $shortest = 999;
        foreach ($dictionary as $v) {
            $lev = levenshtein($w, $v); // jarak edit
            $similar = 0;
            similar_text($w, $v, $similar); // persentase kemiripan

            // threshold: jarak edit ≤2 atau similarity ≥70%
            if ($lev <= 2 || $similar >= 70) {
                if ($lev < $shortest) {
                    $shortest = $lev;
                    $closest = $v;
                }
            }
        }
        $corrected[] = $closest;
    }

    return implode(' ', $corrected);
}

function klasifikasiPesan($message)
{
    $msg = strtolower(trim($message));
    $msg = koreksiTypoCanggih($msg);

    // daftar kata kunci utama
    $keywords = [
        'masuk' => ['masuk', 'hadir', 'datang', 'ikut', 'oke', 'siap'],
        'izin' => ['izin', 'ijin', 'keperluan', 'urusan', 'acara'],
        'sakit' => ['sakit', 'demam', 'pusing', 'flu', 'batuk'],
        'libur' => ['libur', 'cuti', 'off', 'kosong'],
    ];

    // kata tambahan terkait kerja/jadwal
    $kerjaWords = ['job', 'kerja', 'kerjaan', 'pekerjaan', 'shift', 'jadwal'];

    // kata negasi
    $negasi = ['tidak', 'gak', 'ga', 'tak', 'bukan', 'belum', 'enggak'];

    // kata waktu sederhana
    $waktuMap = [
        'hari ini' => date('d-m-Y'),
        'kemarin' => date('d-m-Y', strtotime('-1 day')),
        'besok' => date('d-m-Y', strtotime('+1 day')),
    ];

    // mapping nama hari ke tanggal terdekat sebelumnya
    $hariMap = [
        'senin' => 'monday',
        'selasa' => 'tuesday',
        'rabu' => 'wednesday',
        'kamis' => 'thursday',
        'jumat' => 'friday',
        'sabtu' => 'saturday',
        'minggu' => 'sunday',
    ];

    // deteksi waktu
    $tanggal = date('d-m-Y'); // default: hari ini
    $adaWaktu = false;
    $isLampau = false;
    foreach ($waktuMap as $k => $tgl) {
        if (strpos($msg, $k) !== false) {
            $tanggal = $tgl;
            $adaWaktu = true;
            if ($k === 'kemarin')
                $isLampau = true;
            break;
        }
    }
    foreach ($hariMap as $indo => $eng) {
        if (strpos($msg, $indo) !== false) {
            $tanggal = date('d-m-Y', strtotime("last $eng"));
            $adaWaktu = true;
            $isLampau = true;
            break;
        }
    }
    if (preg_match('/\b(\d{1,2})\s+([a-zA-Z]+)(?:\s+(\d{4}))?/', $msg, $m)) {
        $day = $m[1];
        $monthName = strtolower($m[2]);
        $year = isset($m[3]) ? $m[3] : date('Y');
        $bulanMap = [
            'januari' => '01',
            'februari' => '02',
            'maret' => '03',
            'april' => '04',
            'mei' => '05',
            'juni' => '06',
            'juli' => '07',
            'agustus' => '08',
            'september' => '09',
            'oktober' => '10',
            'november' => '11',
            'desember' => '12'
        ];
        if (isset($bulanMap[$monthName])) {
            $tanggal = sprintf("%02d-%02d-%04d", $day, $bulanMap[$monthName], $year);
            $adaWaktu = true;
            $isLampau = true;
        }
    }

    // kumpulkan kandidat kategori
    $foundPositive = [];
    $foundNegasi = [];

    foreach ($keywords as $kat => $list) {
        foreach ($list as $word) {
            if (strpos($msg, $word) !== false) {
                // cek negasi dekat
                $hasNegasiDekat = false;
                foreach ($negasi as $n) {
                    if (preg_match('/\b' . $n . '\s+' . $word . '\b/', $msg)) {
                        $hasNegasiDekat = true;
                        break;
                    }
                }

                if ($hasNegasiDekat) {
                    if ($kat === 'masuk')
                        $foundNegasi[] = 'izin';
                    elseif ($kat === 'libur')
                        $foundNegasi[] = 'masuk';
                    else
                        $foundNegasi[] = $kat;
                } else {
                    $foundPositive[] = $kat;
                }
            }
        }
    }

    // aturan tambahan: job/kerja + negasi → libur
    foreach ($kerjaWords as $w) {
        if (strpos($msg, $w) !== false) {
            foreach ($negasi as $n) {
                if (strpos($msg, $n) !== false) {
                    $foundPositive[] = 'libur';
                    break 2;
                }
            }
        }
    }

    // tentukan kategori akhir
    $prioritas = ['sakit', 'izin', 'libur', 'masuk'];
    $kategori = null;

    if (!empty($foundPositive)) {
        foreach ($prioritas as $p) {
            if (in_array($p, $foundPositive)) {
                $kategori = $p;
                break;
            }
        }
    } elseif (!empty($foundNegasi)) {
        foreach ($prioritas as $p) {
            if (in_array($p, $foundNegasi)) {
                $kategori = $p;
                break;
            }
        }
    }

    // cek mode lupa (kata 'lupa' atau waktu lampau)
    $isLupa = (strpos($msg, 'lupa') !== false) || $isLampau;

    // khusus: ada lupa + ada waktu, tapi tidak ada kategori
    if ($isLupa && !$kategori && $adaWaktu) {
        return "lupa"; // biar di-handleCommand diformat jadi "lupa absen ..."
    }

    // kalau tidak ada kategori
    if (!$kategori) {
        return $msg;
    }

    // mode lupa (ada kategori)
    if ($isLupa) {
        // ambil catatan setelah kategori
        $catatan = '';
        foreach ($keywords[$kategori] as $kw) {
            $pos = strpos($msg, $kw);
            if ($pos !== false) {
                $catatan = trim(substr($msg, $pos + strlen($kw)));
                break;
            }
        }

        // filter kata tidak relevan
        $filterWords = array_merge(
            $negasi,
            array_keys($keywords),
            $kerjaWords,
            ['hari ini', 'kemarin', 'besok'],
            array_keys($hariMap)
        );
        foreach ($filterWords as $fw) {
            $catatan = preg_replace('/\b' . preg_quote($fw, '/') . '\b/', '', $catatan);
        }
        $catatan = trim($catatan);

        if (!$catatan) {
            $catatan = "lupa absen $msg";
        }

        return "lupa $kategori $tanggal $catatan";
    }

    return $kategori . " " . $msg;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Uji Coba Klasifikasi Pesan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        input[type="text"] {
            width: 300px;
            padding: 8px;
        }

        button {
            padding: 8px 15px;
            margin-left: 5px;
        }

        .hasil {
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h2>Uji Coba Klasifikasi Pesan</h2>
    <form method="post">
        <input type="text" name="pesan" placeholder="Ketik pesan..." required>
        <button type="submit">Klasifikasi</button>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php $pesan = $_POST['pesan']; ?>
        <div class="hasil">
            Pesan: <em>
                <?php echo htmlspecialchars($pesan); ?>
            </em><br>
            Koreksi: <em>
                <?php echo koreksiTypoCanggih($pesan); ?>
            </em><br>
            Hasil Klasifikasi:
            <span style="color:blue;">
                <?php echo klasifikasiPesan($pesan); ?>
            </span>
        </div>
    <?php endif; ?>
</body>

</html>