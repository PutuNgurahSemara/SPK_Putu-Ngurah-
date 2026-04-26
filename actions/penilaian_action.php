<?php
// ============================================================
// actions/penilaian_action.php — Handler POST Form Penilaian SPK
// Menyimpan nilai C1–C5, lalu jalankan SawEngine.hitungUlangSemua()
// ============================================================
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../lib/SawEngine.php';

// ── Guard ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=penilaian');
    exit;
}

function redirectFlash(string $page, string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
    header("Location: ../index.php?hal={$page}");
    exit;
}

$db = getDB();

// ── Ambil & Validasi Input ────────────────────────────────
$perangkatId     = (int)($_POST['perangkat_id']   ?? 0);
$c1              = (int)($_POST['c1_usia']         ?? 0);
$c2              = (int)($_POST['c2_kerusakan']    ?? 0);
$c3              = (int)($_POST['c3_part']          ?? 0);
$c4              = (int)($_POST['c4_kompleksitas'] ?? 0);
$c5              = (int)($_POST['c5_garansi']      ?? 0);

// Validasi ID perangkat
if ($perangkatId <= 0) {
    redirectFlash('penilaian', 'error', 'Perangkat wajib dipilih.');
}

// Validasi semua nilai ada dan dalam rentang 1-3
$nilaiKriteria = [$c1, $c2, $c3, $c4, $c5];
foreach ($nilaiKriteria as $val) {
    if ($val < 1 || $val > 3) {
        redirectFlash('penilaian', 'error', 'Semua nilai kriteria harus dipilih (skala 1–3).');
    }
}

// Cek perangkat benar-benar ada di database
$stmtCek = $db->prepare("SELECT id, kode_aset FROM perangkat WHERE id = :id");
$stmtCek->execute([':id' => $perangkatId]);
$perangkat = $stmtCek->fetch();

if (!$perangkat) {
    redirectFlash('penilaian', 'error', 'Perangkat tidak ditemukan di database.');
}

// ── Simpan/Update Penilaian ───────────────────────────────
try {
    $db->beginTransaction();

    // Cek apakah sudah pernah dinilai (ambil penilaian terbaru)
    $stmtCekExist = $db->prepare("
        SELECT id FROM penilaian_spk
        WHERE perangkat_id = :pid
        ORDER BY tanggal_penilaian DESC
        LIMIT 1
    ");
    $stmtCekExist->execute([':pid' => $perangkatId]);
    $existingId = $stmtCekExist->fetchColumn();

    if ($existingId) {
        // UPDATE penilaian yang sudah ada
        $stmt = $db->prepare("
            UPDATE penilaian_spk
               SET c1_usia         = :c1,
                   c2_kerusakan    = :c2,
                   c3_part          = :c3,
                   c4_kompleksitas  = :c4,
                   c5_garansi       = :c5,
                   tanggal_penilaian = CURRENT_TIMESTAMP,
                   skor_akhir       = NULL,
                   rekomendasi      = NULL
             WHERE id = :id
        ");
        $stmt->execute([
            ':c1' => $c1, ':c2' => $c2, ':c3' => $c3,
            ':c4' => $c4, ':c5' => $c5, ':id' => (int)$existingId,
        ]);
        $opLabel = 'diperbarui';
    } else {
        // INSERT penilaian baru
        $stmt = $db->prepare("
            INSERT INTO penilaian_spk
                   (perangkat_id, c1_usia, c2_kerusakan, c3_part, c4_kompleksitas, c5_garansi)
            VALUES (:pid, :c1, :c2, :c3, :c4, :c5)
        ");
        $stmt->execute([
            ':pid' => $perangkatId,
            ':c1'  => $c1, ':c2' => $c2, ':c3' => $c3,
            ':c4'  => $c4, ':c5' => $c5,
        ]);
        $opLabel = 'ditambahkan';
    }

    // ── Jalankan SAW Engine: hitung ulang SEMUA skor ──────
    // Ini penting agar normalisasi (berdasarkan MAX seluruh data) selalu akurat
    $engine  = new SawEngine($db);
    $updated = $engine->hitungUlangSemua();

    $db->commit();

    // Ambil skor hasil hitungan untuk perangkat ini
    $stmtSkor = $db->prepare("
        SELECT skor_akhir, rekomendasi
        FROM penilaian_spk
        WHERE perangkat_id = :pid
        ORDER BY tanggal_penilaian DESC
        LIMIT 1
    ");
    $stmtSkor->execute([':pid' => $perangkatId]);
    $hasilSkor = $stmtSkor->fetch();

    $skor = number_format((float)($hasilSkor['skor_akhir'] ?? 0), 4);
    $rek  = $hasilSkor['rekomendasi'] ?? '-';

    redirectFlash(
        'hasil',
        'success',
        "Penilaian {$perangkat['kode_aset']} berhasil {$opLabel}. "
        . "Skor SAW: {$skor} → Rekomendasi: {$rek}. "
        . "({$updated} data dihitung ulang)"
    );

} catch (\PDOException $e) {
    $db->rollBack();
    error_log('[Penilaian Action] ' . $e->getMessage());
    redirectFlash('penilaian', 'error', 'Gagal menyimpan penilaian. Silakan coba lagi.');
}
