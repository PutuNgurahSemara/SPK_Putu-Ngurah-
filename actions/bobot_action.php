<?php
// ============================================================
// actions/bobot_action.php — Handler POST: Simpan Bobot Kriteria
// URL: POST actions/bobot_action.php (redirect kembali ke halaman)
// ============================================================
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/koneksi.php';

// ── Guard: hanya izinkan POST ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=pengaturan_bobot');
    exit;
}

// ── Ambil & Validasi Input ────────────────────────────────
$ids   = $_POST['id']            ?? [];
$namas = $_POST['nama_kriteria'] ?? [];
$bobots = $_POST['bobot']        ?? [];

// Pastikan jumlah array sama (5 elemen)
if (count($ids) !== 5 || count($namas) !== 5 || count($bobots) !== 5) {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Data tidak lengkap. Pastikan semua kriteria terisi.',
    ];
    header('Location: ../index.php?hal=pengaturan_bobot');
    exit;
}

// Cast & sanitasi
$bobotFloat = array_map('floatval', $bobots);
$totalBobot = array_sum($bobotFloat);

// ── Validasi Bisnis: Total Bobot Wajib = 1.00 ────────────
if (abs($totalBobot - 1.00) > 0.001) {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => sprintf(
            'Total bobot harus 1.00, bukan %.4f. Periksa kembali nilai bobot Anda.',
            $totalBobot
        ),
    ];
    header('Location: ../index.php?hal=pengaturan_bobot');
    exit;
}

// Validasi tiap bobot > 0
foreach ($bobotFloat as $i => $b) {
    if ($b <= 0 || $b > 1) {
        $_SESSION['flash'] = [
            'type'    => 'error',
            'message' => 'Setiap bobot harus bernilai antara 0.01 dan 1.00.',
        ];
        header('Location: ../index.php?hal=pengaturan_bobot');
        exit;
    }
}

// ── Update Database ───────────────────────────────────────
try {
    $db = getDB();
    $db->beginTransaction();

    $stmt = $db->prepare("
        UPDATE kriteria
           SET nama_kriteria = :nama,
               bobot         = :bobot
         WHERE id = :id
    ");

    foreach ($ids as $i => $id) {
        $stmt->execute([
            ':nama'  => trim($namas[$i]),
            ':bobot' => $bobotFloat[$i],
            ':id'    => (int) $id,
        ]);
    }

    $db->commit();

    $_SESSION['flash'] = [
        'type'    => 'success',
        'message' => 'Bobot kriteria berhasil diperbarui. Total bobot = ' . number_format($totalBobot, 2),
    ];

} catch (\PDOException $e) {
    $db->rollBack();
    error_log('[Bobot Update Error] ' . $e->getMessage());
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Gagal menyimpan bobot. Silakan coba lagi.',
    ];
}

header('Location: ../index.php?hal=pengaturan_bobot');
exit;
