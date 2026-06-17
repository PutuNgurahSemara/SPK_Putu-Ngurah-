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
// FUNGSI HELPER: AMBIL DATA KRITERIA DARI DATABASE (DINAMIS)
// Tidak ada hardcode kode/kolom — semua dari tabel `kriteria`
// ============================================================

/**
 * Mengambil semua data kriteria lengkap, diurutkan berdasarkan urutan/kode.
 *
 * @return array<int, array<string, mixed>>
 */
function getAllKriteria(): array
{
    $stmt = getDB()->query(
        "SELECT * FROM kriteria ORDER BY urutan, kode_kriteria ASC"
    );
    return $stmt->fetchAll();
}

/**
 * Mengambil bobot per kriteria_id (bukan per nama kolom).
 * Return: [kriteria_id => bobot_float]
 *
 * @return array<int, float>
 */
function getBobotKriteria(): array
{
    $stmt = getDB()->query(
        "SELECT id, bobot FROM kriteria ORDER BY urutan, kode_kriteria ASC"
    );
    $bobot = [];
    foreach ($stmt->fetchAll() as $row) {
        $bobot[(int)$row['id']] = (float)$row['bobot'];
    }
    return $bobot;
}

/**
 * Auto-generate kode kriteria berikutnya (C1, C2, ..., Cn).
 * Mencari angka tertinggi yang sudah ada lalu +1.
 *
 * @return string  Contoh: 'C6'
 */
function generateNextKodeKriteria(): string
{
    $stmt = getDB()->query(
        "SELECT MAX(CAST(REGEXP_REPLACE(kode_kriteria, '[^0-9]', '', 'g') AS INTEGER))
         FROM kriteria
         WHERE kode_kriteria ~ '^C[0-9]+$'"
    );
    $maxNum = (int)($stmt->fetchColumn() ?? 0);
    return 'C' . ($maxNum + 1);
}

// ============================================================
// KONSTANTA APLIKASI
// ============================================================

/** Threshold skor SAW untuk penentuan rekomendasi */
define('SAW_THRESHOLD', 0.70);

/** Jenis perangkat yang valid (sesuai CHECK constraint di DB) */
define('JENIS_PERANGKAT', ['Thin Client', 'PC Desktop', 'Printer', 'Network']);

// Catatan: SAW_SKALA dihapus — skala nilai kini dinamis dari kolom
// nilai_1, nilai_2, nilai_3 di tabel kriteria
