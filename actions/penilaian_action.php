<?php
// ============================================================
// actions/penilaian_action.php — Handler POST Form Penilaian SPK (v2)
// Menyimpan nilai per kriteria ke penilaian_detail, lalu SawEngine
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
$perangkatId = (int)($_POST['perangkat_id'] ?? 0);
$nilaiInput  = $_POST['nilai'] ?? []; // [kriteria_id => nilai(1-3)]

// Validasi ID perangkat
if ($perangkatId <= 0) {
    redirectFlash('penilaian', 'error', 'Perangkat wajib dipilih.');
}

// Ambil semua kriteria aktif dari DB
$semuaKriteria = getAllKriteria();
if (empty($semuaKriteria)) {
    redirectFlash('penilaian', 'error', 'Belum ada kriteria aktif. Tambahkan kriteria di halaman Pengaturan Bobot.');
}

// Validasi: semua kriteria harus diisi dengan nilai 1-3
$nilaiValid = [];
foreach ($semuaKriteria as $kr) {
    $kid = (int)$kr['id'];
    $val = (int)($nilaiInput[$kid] ?? 0);
    if ($val < 1 || $val > 3) {
        redirectFlash(
            'penilaian',
            'error',
            "Nilai untuk kriteria {$kr['kode_kriteria']} ({$kr['nama_kriteria']}) wajib dipilih (skala 1–3)."
        );
    }
    $nilaiValid[$kid] = $val;
}

// Cek perangkat ada di database
$stmtCek = $db->prepare("SELECT id, kode_aset FROM perangkat WHERE id = :id");
$stmtCek->execute([':id' => $perangkatId]);
$perangkat = $stmtCek->fetch();

if (!$perangkat) {
    redirectFlash('penilaian', 'error', 'Perangkat tidak ditemukan di database.');
}

// ── Simpan/Update Penilaian ───────────────────────────────
try {
    $db->beginTransaction();

    // Cek apakah sudah pernah dinilai (penilaian terbaru)
    $stmtCekExist = $db->prepare("
        SELECT id FROM penilaian_spk
        WHERE perangkat_id = :pid
        ORDER BY tanggal_penilaian DESC
        LIMIT 1
    ");
    $stmtCekExist->execute([':pid' => $perangkatId]);
    $existingId = $stmtCekExist->fetchColumn();

    if ($existingId) {
        // UPDATE header penilaian (reset skor & rek)
        $db->prepare("
            UPDATE penilaian_spk
               SET tanggal_penilaian = CURRENT_TIMESTAMP,
                   skor_akhir        = NULL,
                   rekomendasi       = NULL
             WHERE id = :id
        ")->execute([':id' => (int)$existingId]);

        // Hapus detail lama lalu insert baru
        $db->prepare("DELETE FROM penilaian_detail WHERE penilaian_id = :pid")
           ->execute([':pid' => (int)$existingId]);

        $penilaianId = (int)$existingId;
        $opLabel     = 'diperbarui';
    } else {
        // INSERT header penilaian baru
        $db->prepare("
            INSERT INTO penilaian_spk (perangkat_id)
            VALUES (:pid)
        ")->execute([':pid' => $perangkatId]);

        $penilaianId = (int)$db->lastInsertId();
        $opLabel     = 'ditambahkan';
    }

    // Insert nilai per kriteria ke penilaian_detail
    $stmtDetail = $db->prepare("
        INSERT INTO penilaian_detail (penilaian_id, kriteria_id, nilai)
        VALUES (:pid, :kid, :nilai)
    ");

    foreach ($nilaiValid as $kid => $val) {
        $stmtDetail->execute([
            ':pid'   => $penilaianId,
            ':kid'   => $kid,
            ':nilai' => $val,
        ]);
    }

    // ── Jalankan SAW Engine: hitung ulang SEMUA skor ──────
    $engine  = new SawEngine($db);
    $updated = $engine->hitungUlangSemua();

    $db->commit();

    // Ambil skor hasil untuk perangkat ini
    $stmtSkor = $db->prepare("
        SELECT skor_akhir, rekomendasi
        FROM penilaian_spk
        WHERE id = :pid
    ");
    $stmtSkor->execute([':pid' => $penilaianId]);
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
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Penilaian Action] ' . $e->getMessage());
    redirectFlash('penilaian', 'error', 'Gagal menyimpan penilaian. Silakan coba lagi.');
}
