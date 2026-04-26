<?php
declare(strict_types=1);
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'spk_aset_it');
define('DB_USER', 'postgres');
define('DB_PASS', 'putu2520');   

// ============================================================
// KONEKSI PDO SINGLETON
// ============================================================

/**
 * Mengembalikan instance PDO (koneksi PostgreSQL).
 * Pola Singleton: koneksi hanya dibuat SATU KALI per siklus request.
 *
 * @throws \PDOException Jika koneksi gagal
 * @return \PDO
 */
function getDB(): \PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            DB_HOST, DB_PORT, DB_NAME
        );

        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new \PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            error_log('[DB Connection Error] ' . $e->getMessage());
            // Tampilkan halaman error yang aman (tidak bocorkan detail)
            http_response_code(503);
            die('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
                <title>Error Koneksi</title>
                <script src="https://cdn.tailwindcss.com"></script></head>
                <body class="bg-gray-900 flex items-center justify-center min-h-screen">
                <div class="text-center p-8 bg-red-900/30 border border-red-500 rounded-xl max-w-md">
                    <p class="text-3xl mb-3">⚠️</p>
                    <h1 class="text-red-400 font-bold text-xl mb-2">Koneksi Database Gagal</h1>
                    <p class="text-gray-300 text-sm">Silakan hubungi administrator IT.</p>
                </div></body></html>');
        }
    }

    return $pdo;
}

// ============================================================
// FUNGSI HELPER: AMBIL BOBOT KRITERIA DARI DATABASE (DINAMIS)
// Bobot tidak di-hardcode — selalu diambil fresh dari tabel `kriteria`
// ============================================================

/**
 * Mengambil semua data kriteria beserta bobotnya dari database.
 * Return array asosiatif: ['c1_usia' => 0.15, 'c2_kerusakan' => 0.30, ...]
 *
 * @return array<string, float>
 */
function getBobotKriteria(): array
{
    // Map kode_kriteria → nama kolom di tabel penilaian_spk
    $kodeToKolom = [
        'C1' => 'c1_usia',
        'C2' => 'c2_kerusakan',
        'C3' => 'c3_part',
        'C4' => 'c4_kompleksitas',
        'C5' => 'c5_garansi',
    ];

    $stmt = getDB()->query(
        "SELECT kode_kriteria, bobot FROM kriteria ORDER BY kode_kriteria ASC"
    );
    $rows  = $stmt->fetchAll();
    $bobot = [];

    foreach ($rows as $row) {
        $kode = strtoupper(trim($row['kode_kriteria']));
        if (isset($kodeToKolom[$kode])) {
            $bobot[$kodeToKolom[$kode]] = (float) $row['bobot'];
        }
    }

    return $bobot;
}

/**
 * Mengambil semua data kriteria lengkap (untuk halaman Pengaturan Bobot).
 *
 * @return array
 */
function getAllKriteria(): array
{
    $stmt = getDB()->query(
        "SELECT * FROM kriteria ORDER BY kode_kriteria ASC"
    );
    return $stmt->fetchAll();
}

// ============================================================
// KONSTANTA APLIKASI
// ============================================================

/** Threshold skor SAW untuk penentuan rekomendasi */
define('SAW_THRESHOLD', 0.70);

/** Jenis perangkat yang valid (sesuai CHECK constraint di DB) */
define('JENIS_PERANGKAT', ['Thin Client', 'PC Desktop', 'Printer', 'Network']);

/** Skala nilai dropdown per kriteria (label deskriptif untuk user) */
define('SAW_SKALA', [
    'c1_usia' => [
        1 => '1 — Di bawah 3 tahun',
        2 => '2 — Antara 3 sampai 5 tahun',
        3 => '3 — Di atas 5 tahun',
    ],
    'c2_kerusakan' => [
        1 => '1 — Kerusakan Ringan',
        2 => '2 — Kerusakan Sedang',
        3 => '3 — Kerusakan Berat',
    ],
    'c3_part' => [
        1 => '1 — Suku Cadang Mudah Didapat',
        2 => '2 — Suku Cadang Sulit Didapat',
        3 => '3 — Suku Cadang Tidak Tersedia',
    ],
    'c4_kompleksitas' => [
        1 => '1 — Pengerjaan Mudah',
        2 => '2 — Pengerjaan Sedang',
        3 => '3 — Pengerjaan Sulit / Kompleks',
    ],
    'c5_garansi' => [
        1 => '1 — Garansi Masih Aktif',
        2 => '2 — Garansi Habis < 1 Tahun',
        3 => '3 — Garansi Habis > 1 Tahun',
    ],
]);
