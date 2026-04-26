<?php
// ============================================================
// actions/perangkat_action.php — Handler CRUD Perangkat
// ============================================================
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=perangkat');
    exit;
}

$aksi = $_POST['aksi'] ?? '';
$db   = getDB();

// ── Fungsi helper redirect dengan flash ───────────────────
function redirectFlash(string $page, string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
    header("Location: ../index.php?hal={$page}");
    exit;
}

// ============================================================
// TAMBAH PERANGKAT
// ============================================================
if ($aksi === 'tambah') {
    $kode   = strtoupper(trim($_POST['kode_aset']     ?? ''));
    $jenis  = trim($_POST['jenis_perangkat']          ?? '');
    $divisi = trim($_POST['divisi_user']              ?? '');

    // Validasi input
    if ($kode === '' || $jenis === '' || $divisi === '') {
        redirectFlash('perangkat', 'error', 'Semua field wajib diisi.');
    }
    if (!in_array($jenis, JENIS_PERANGKAT, true)) {
        redirectFlash('perangkat', 'error', 'Jenis perangkat tidak valid.');
    }
    if (strlen($kode) > 50 || strlen($divisi) > 100) {
        redirectFlash('perangkat', 'error', 'Panjang input melebihi batas maksimal.');
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO perangkat (kode_aset, jenis_perangkat, divisi_user)
            VALUES (:kode, :jenis, :divisi)
        ");
        $stmt->execute([':kode' => $kode, ':jenis' => $jenis, ':divisi' => $divisi]);
        redirectFlash('perangkat', 'success', "Perangkat {$kode} berhasil ditambahkan.");

    } catch (\PDOException $e) {
        // Kode 23505 = unique_violation (kode_aset sudah ada)
        if ($e->getCode() === '23505') {
            redirectFlash('perangkat', 'error', "Kode aset '{$kode}' sudah terdaftar.");
        }
        error_log('[Perangkat Tambah] ' . $e->getMessage());
        redirectFlash('perangkat', 'error', 'Gagal menambahkan perangkat. Coba lagi.');
    }
}

// ============================================================
// EDIT PERANGKAT
// ============================================================
if ($aksi === 'edit') {
    $id     = (int)($_POST['id']            ?? 0);
    $jenis  = trim($_POST['jenis_perangkat'] ?? '');
    $divisi = trim($_POST['divisi_user']     ?? '');

    if ($id <= 0 || $jenis === '' || $divisi === '') {
        redirectFlash('perangkat', 'error', 'Data tidak lengkap untuk operasi edit.');
    }
    if (!in_array($jenis, JENIS_PERANGKAT, true)) {
        redirectFlash('perangkat', 'error', 'Jenis perangkat tidak valid.');
    }

    try {
        $stmt = $db->prepare("
            UPDATE perangkat
               SET jenis_perangkat = :jenis,
                   divisi_user     = :divisi
             WHERE id = :id
        ");
        $stmt->execute([':jenis' => $jenis, ':divisi' => $divisi, ':id' => $id]);

        if ($stmt->rowCount() === 0) {
            redirectFlash('perangkat', 'error', 'Perangkat tidak ditemukan.');
        }
        redirectFlash('perangkat', 'success', 'Data perangkat berhasil diperbarui.');

    } catch (\PDOException $e) {
        error_log('[Perangkat Edit] ' . $e->getMessage());
        redirectFlash('perangkat', 'error', 'Gagal memperbarui data perangkat.');
    }
}

// ============================================================
// HAPUS PERANGKAT
// ============================================================
if ($aksi === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        redirectFlash('perangkat', 'error', 'ID perangkat tidak valid.');
    }

    try {
        // Cascade delete otomatis menghapus penilaian_spk terkait (sesuai FK ON DELETE CASCADE)
        $stmt = $db->prepare("DELETE FROM perangkat WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            redirectFlash('perangkat', 'error', 'Perangkat tidak ditemukan.');
        }
        redirectFlash('perangkat', 'success', 'Perangkat dan data penilaiannya berhasil dihapus.');

    } catch (\PDOException $e) {
        error_log('[Perangkat Hapus] ' . $e->getMessage());
        redirectFlash('perangkat', 'error', 'Gagal menghapus perangkat.');
    }
}

// Fallback jika aksi tidak dikenali
redirectFlash('perangkat', 'error', 'Aksi tidak dikenali.');
