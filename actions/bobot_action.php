<?php
// ============================================================
// actions/bobot_action.php — Handler POST: Kelola Kriteria & Bobot (v2)
// Mendukung: UPDATE bobot/nama, INSERT kriteria baru, DELETE kriteria
// URL: POST actions/bobot_action.php
// ============================================================
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../lib/SawEngine.php';

// ── Guard: hanya izinkan POST ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=pengaturan_bobot');
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
    header('Location: ../index.php?hal=pengaturan_bobot');
    exit;
}

// ── Ambil Data POST ───────────────────────────────────────
$ids      = $_POST['id']             ?? [];
$namas    = $_POST['nama_kriteria']  ?? [];
$bobots   = $_POST['bobot']          ?? [];
$atributs = $_POST['atribut']        ?? [];
$nilai1s  = $_POST['nilai_1']        ?? [];
$nilai2s  = $_POST['nilai_2']        ?? [];
$nilai3s  = $_POST['nilai_3']        ?? [];

// ID yang akan dihapus (string CSV dari hidden input)
$hapusIdsStr = trim($_POST['hapus_ids'] ?? '');
$hapusIds    = array_filter(array_map('intval', explode(',', $hapusIdsStr)));

// ── Validasi Struktur Data ────────────────────────────────
$rowCount = count($ids);
if ($rowCount === 0 && empty($hapusIds)) {
    flash('error', 'Tidak ada data yang dikirim.');
}

// Pastikan semua array punya jumlah elemen sama
if (count($namas) !== $rowCount || count($bobots) !== $rowCount) {
    flash('error', 'Data tidak konsisten. Silakan refresh halaman dan coba lagi.');
}

// ── Identifikasi baris aktif (bukan yang dihapus) ────────
$aktifIds    = [];
$aktifBobots = [];

for ($i = 0; $i < $rowCount; $i++) {
    $id = (int)($ids[$i] ?? 0);
    if (in_array($id, $hapusIds, true) && $id > 0) {
        continue; // skip baris yang akan dihapus
    }
    $nama = trim($namas[$i] ?? '');
    if ($nama === '') {
        continue; // skip baris kosong (kemungkinan baris baru belum diisi)
    }
    $aktifIds[]    = $id;
    $aktifBobots[] = (float)($bobots[$i] ?? 0);
}

// ── Validasi Bisnis: Total Bobot Wajib = 1.00 ────────────
$totalBobot = array_sum($aktifBobots);

if (empty($aktifIds) && empty($hapusIds)) {
    flash('error', 'Tidak ada kriteria aktif. Minimal harus ada satu kriteria.');
}

if (!empty($aktifIds) && abs($totalBobot - 1.00) > 0.001) {
    flash('error', sprintf(
        'Total bobot kriteria aktif harus tepat 1.00 (100%%), bukan %.4f (%.2f%%). Periksa kembali nilai bobot Anda.',
        $totalBobot,
        $totalBobot * 100
    ));
}

// Validasi tiap bobot > 0
foreach ($aktifBobots as $b) {
    if ($b <= 0 || $b > 1) {
        flash('error', 'Setiap bobot harus bernilai antara 0.0001 dan 1.0000.');
    }
}

// ── Proses Database ───────────────────────────────────────
try {
    $db = getDB();
    $db->beginTransaction();

    // === LANGKAH 1: Proses Penghapusan ===
    foreach ($hapusIds as $hapusId) {
        if ($hapusId <= 0) continue;

        // Cek apakah kriteria ini masih digunakan di penilaian_detail
        $stmtCek = $db->prepare(
            "SELECT COUNT(*) FROM penilaian_detail WHERE kriteria_id = :id"
        );
        $stmtCek->execute([':id' => $hapusId]);
        $jumlahPakai = (int)$stmtCek->fetchColumn();

        if ($jumlahPakai > 0) {
            $db->rollBack();
            // Ambil nama kriteria untuk pesan error yang informatif
            $stmtNama = $db->prepare("SELECT kode_kriteria, nama_kriteria FROM kriteria WHERE id = :id");
            $stmtNama->execute([':id' => $hapusId]);
            $krInfo = $stmtNama->fetch();
            flash('error', sprintf(
                'Kriteria %s (%s) tidak dapat dihapus karena masih digunakan oleh %d data penilaian.',
                $krInfo['kode_kriteria'] ?? '?',
                $krInfo['nama_kriteria'] ?? '?',
                $jumlahPakai
            ));
        }

        $db->prepare("DELETE FROM kriteria WHERE id = :id")->execute([':id' => $hapusId]);
    }

    // === LANGKAH 2: UPDATE & INSERT Kriteria ===
    $stmtUpdate = $db->prepare("
        UPDATE kriteria
           SET nama_kriteria = :nama,
               atribut       = :atribut,
               bobot         = :bobot,
               nilai_1       = :v1,
               nilai_2       = :v2,
               nilai_3       = :v3
         WHERE id = :id
    ");

    $stmtInsert = $db->prepare("
        INSERT INTO kriteria (kode_kriteria, nama_kriteria, atribut, bobot, nilai_1, nilai_2, nilai_3, urutan)
        VALUES (:kode, :nama, :atribut, :bobot, :v1, :v2, :v3, :urutan)
    ");

    // Ambil urutan tertinggi saat ini untuk assign ke baris baru
    $maxUrutan = (int)($db->query("SELECT COALESCE(MAX(urutan), 0) FROM kriteria")->fetchColumn());

    for ($i = 0; $i < $rowCount; $i++) {
        $id     = (int)($ids[$i]    ?? 0);
        $nama   = trim($namas[$i]   ?? '');
        $bobot  = (float)($bobots[$i] ?? 0);
        $atribut = in_array($atributs[$i] ?? '', ['Benefit','Cost']) ? $atributs[$i] : 'Benefit';
        $v1     = trim($nilai1s[$i] ?? '') ?: '1 — Nilai Rendah';
        $v2     = trim($nilai2s[$i] ?? '') ?: '2 — Nilai Sedang';
        $v3     = trim($nilai3s[$i] ?? '') ?: '3 — Nilai Tinggi';

        // Skip baris yang dihapus atau kosong
        if (in_array($id, $hapusIds, true) && $id > 0) continue;
        if ($nama === '') continue;

        if ($id > 0) {
            // UPDATE kriteria yang sudah ada
            $stmtUpdate->execute([
                ':nama'   => $nama,
                ':atribut'=> $atribut,
                ':bobot'  => $bobot,
                ':v1'     => $v1,
                ':v2'     => $v2,
                ':v3'     => $v3,
                ':id'     => $id,
            ]);
        } else {
            // INSERT kriteria baru — auto-generate kode
            $kode = generateNextKodeKriteria();
            $maxUrutan++;
            $stmtInsert->execute([
                ':kode'   => $kode,
                ':nama'   => $nama,
                ':atribut'=> $atribut,
                ':bobot'  => $bobot,
                ':v1'     => $v1,
                ':v2'     => $v2,
                ':v3'     => $v3,
                ':urutan' => $maxUrutan,
            ]);
        }
    }

    // === LANGKAH 3: Hitung ulang semua skor SAW ===
    // Perubahan bobot → skor semua penilaian harus di-recalculate
    $engine  = new SawEngine($db);
    $updated = $engine->hitungUlangSemua();

    $db->commit();

    // Hitung jumlah perubahan untuk pesan sukses
    $jumlahHapus   = count(array_filter($hapusIds, fn($v) => $v > 0));
    $jumlahKriteria = (int)$db->query("SELECT COUNT(*) FROM kriteria")->fetchColumn();
    $totalBobotAkhir = (float)$db->query("SELECT SUM(bobot) FROM kriteria")->fetchColumn();

    flash('success', sprintf(
        'Pengaturan kriteria berhasil disimpan. %d kriteria aktif, total bobot = %.4f. %s%s skor dihitung ulang.',
        $jumlahKriteria,
        $totalBobotAkhir,
        $jumlahHapus > 0 ? "{$jumlahHapus} kriteria dihapus. " : '',
        $updated > 0 ? "{$updated} " : 'Tidak ada '
    ));

} catch (\PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Bobot Action Error] ' . $e->getMessage());
    flash('error', 'Gagal menyimpan pengaturan. Silakan coba lagi. Detail: ' . $e->getMessage());
}
