<?php
// ============================================================
// exports/export_pdf.php — Export Hasil Keputusan ke PDF
// Library: DomPDF (jalankan: composer install)
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/koneksi.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ── Ambil Data dari Database ──────────────────────────────
$db        = getDB();
$filterRek = $_GET['rek'] ?? 'semua';

$whereClause = '';
$params      = [];
if ($filterRek === 'belum') {
    $whereClause = 'WHERE ps.skor_akhir IS NULL';
} elseif (in_array($filterRek, ['Servis', 'Masuk Gudang'], true)) {
    $whereClause = 'WHERE ps.rekomendasi = :rek';
    $params      = [':rek' => $filterRek];
}

$sql = "
    SELECT
        ROW_NUMBER() OVER (ORDER BY ps.skor_akhir DESC NULLS LAST) AS ranking,
        p.kode_aset, p.jenis_perangkat, p.divisi_user,
        ps.c1_usia, ps.c2_kerusakan, ps.c3_part, ps.c4_kompleksitas, ps.c5_garansi,
        ps.skor_akhir, ps.rekomendasi, ps.tanggal_penilaian
    FROM (
        SELECT DISTINCT ON (perangkat_id) *
        FROM penilaian_spk
        ORDER BY perangkat_id, tanggal_penilaian DESC
    ) ps
    JOIN perangkat p ON p.id = ps.perangkat_id
    {$whereClause}
    ORDER BY ps.skor_akhir DESC NULLS LAST
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$hasilList = $stmt->fetchAll();

// Statistik
$totalData  = count($hasilList);
$totalGudang = count(array_filter($hasilList, fn($r) => $r['rekomendasi'] === 'Masuk Gudang'));
$totalServis = count(array_filter($hasilList, fn($r) => $r['rekomendasi'] === 'Servis'));

// Bobot kriteria
$kriteria = getAllKriteria();

// ── Helper: Warna Berdasarkan Nilai ──────────────────────
function nilaiColor(int $v): string
{
    return match($v) { 1 => '#16a34a', 2 => '#d97706', 3 => '#dc2626', default => '#64748b' };
}

// ── Bangun HTML untuk DomPDF ──────────────────────────────
$filterLabel = match($filterRek) {
    'semua'        => 'Semua Data',
    'Servis'       => 'Rekomendasi: Servis',
    'Masuk Gudang' => 'Rekomendasi: Masuk Gudang',
    'belum'        => 'Belum Dihitung',
    default        => $filterRek,
};

// Baris tabel data
$tableRows = '';
foreach ($hasilList as $row) {
    $skor     = $row['skor_akhir'] !== null ? number_format((float)$row['skor_akhir'], 4) : '—';
    $isGudang = $row['rekomendasi'] === 'Masuk Gudang';
    $isServis = $row['rekomendasi'] === 'Servis';

    $rowBg    = $isGudang ? '#fff1f2' : ($isServis ? '#fffbeb' : '#ffffff');
    $rekColor = $isGudang ? '#dc2626'  : ($isServis ? '#d97706'  : '#64748b');
    $rekLabel = $row['rekomendasi'] ?? 'Belum';

    $c1c = nilaiColor((int)$row['c1_usia']);
    $c2c = nilaiColor((int)$row['c2_kerusakan']);
    $c3c = nilaiColor((int)$row['c3_part']);
    $c4c = nilaiColor((int)$row['c4_kompleksitas']);
    $c5c = nilaiColor((int)$row['c5_garansi']);

    $tgl = $row['tanggal_penilaian']
        ? date('d/m/Y', strtotime($row['tanggal_penilaian']))
        : '—';

    $tableRows .= "
    <tr style='background-color:{$rowBg};'>
        <td style='text-align:center;font-weight:bold;'>{$row['ranking']}</td>
        <td style='font-weight:600;font-family:monospace;'>" . htmlspecialchars($row['kode_aset']) . "</td>
        <td>" . htmlspecialchars($row['jenis_perangkat']) . "</td>
        <td>" . htmlspecialchars($row['divisi_user']) . "</td>
        <td style='text-align:center;color:{$c1c};font-weight:700;'>{$row['c1_usia']}</td>
        <td style='text-align:center;color:{$c2c};font-weight:700;'>{$row['c2_kerusakan']}</td>
        <td style='text-align:center;color:{$c3c};font-weight:700;'>{$row['c3_part']}</td>
        <td style='text-align:center;color:{$c4c};font-weight:700;'>{$row['c4_kompleksitas']}</td>
        <td style='text-align:center;color:{$c5c};font-weight:700;'>{$row['c5_garansi']}</td>
        <td style='text-align:center;font-weight:700;font-family:monospace;
                   color:" . ($isGudang ? '#dc2626' : '#d97706') . ";'>{$skor}</td>
        <td style='text-align:center;font-weight:700;color:{$rekColor};'>{$rekLabel}</td>
        <td style='text-align:center;color:#64748b;'>{$tgl}</td>
    </tr>";
}

// Baris bobot
$bobotRows = '';
$bobotColors = ['#3b82f6','#ef4444','#f59e0b','#a855f7','#10b981'];
$ki = 0;
foreach ($kriteria as $kr) {
    $pct   = round((float)$kr['bobot'] * 100, 0);
    $color = $bobotColors[$ki % count($bobotColors)];
    $ki++;
    $bobotRows .= "
    <tr>
        <td style='text-align:center;font-weight:700;color:{$color};'>" . htmlspecialchars($kr['kode_kriteria']) . "</td>
        <td>" . htmlspecialchars($kr['nama_kriteria']) . "</td>
        <td style='text-align:center;'>" . htmlspecialchars($kr['atribut']) . "</td>
        <td style='text-align:center;font-weight:700;font-family:monospace;'>" . number_format((float)$kr['bobot'], 2) . "</td>
        <td style='text-align:center;'>{$pct}%</td>
    </tr>";
}

// ── Template HTML Lengkap ─────────────────────────────────
$html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 8.5pt;
        color: #1e293b;
        background: #fff;
    }

    /* ── Header ──────────────────────────── */
    .header {
        background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
        color: white;
        padding: 16px 20px;
        border-radius: 0;
    }
    .header-title {
        font-size: 14pt;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .header-sub {
        font-size: 8.5pt;
        color: #bfdbfe;
        margin-bottom: 10px;
    }
    .header-meta {
        display: flex;
        gap: 20px;
        font-size: 8pt;
        color: #93c5fd;
    }
    .meta-box {
        background: rgba(255,255,255,0.1);
        padding: 4px 10px;
        border-radius: 4px;
    }
    .meta-box strong { color: white; }

    /* ── Ringkasan Statistik ─────────────── */
    .stats-bar {
        display: flex;
        gap: 10px;
        padding: 10px 20px;
        background: #f1f5f9;
        border-bottom: 2px solid #e2e8f0;
    }
    .stat-item {
        flex: 1;
        text-align: center;
        padding: 6px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: white;
    }
    .stat-val  { font-size: 14pt; font-weight: 700; display: block; }
    .stat-label { font-size: 7pt; color: #64748b; }

    /* ── Tabel Utama ─────────────────────── */
    .section-title {
        font-size: 10pt;
        font-weight: 700;
        color: #1e3a8a;
        padding: 10px 20px 6px;
        border-left: 4px solid #1d4ed8;
        margin: 10px 20px 0;
        background: #eff6ff;
    }
    .table-wrap { padding: 6px 20px 0; }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 7.5pt;
    }
    thead tr {
        background: #1d4ed8;
        color: white;
    }
    thead th {
        padding: 5px 4px;
        text-align: center;
        border: 1px solid #1e40af;
        font-size: 7pt;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    tbody td {
        padding: 4px 5px;
        border: 1px solid #e2e8f0;
        font-size: 7.5pt;
    }
    tbody tr:nth-child(even) td { background-color: #f8fafc; }

    /* ── Tabel Bobot ─────────────────────── */
    .bobot-table thead tr { background: #2563eb; }

    /* ── Footer ──────────────────────────── */
    .footer {
        margin: 10px 20px 0;
        padding: 8px;
        border-top: 1px solid #e2e8f0;
        font-size: 7pt;
        color: #94a3b8;
        text-align: center;
    }
    .legend {
        padding: 6px 20px;
        font-size: 7pt;
        color: #64748b;
    }
    .legend span { margin-right: 12px; }
</style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-title">LAPORAN HASIL KEPUTUSAN SPK MANAJEMEN ASET IT</div>
    <div class="header-sub">Kutai Refinery Nusantara &mdash; Metode Simple Additive Weighting (SAW)</div>
    <div class="header-meta">
        <span class="meta-box">Filter: <strong>{$filterLabel}</strong></span>
        <span class="meta-box">Total Data: <strong>{$totalData}</strong></span>
        <span class="meta-box">Digenerate: <strong>{$nowDate}</strong></span>
        <span class="meta-box">Threshold: <strong>{$threshold}</strong></span>
    </div>
</div>

<!-- Stats Bar -->
<div class="stats-bar">
    <div class="stat-item">
        <span class="stat-val" style="color:#1d4ed8;">{$totalData}</span>
        <span class="stat-label">Total Dinilai</span>
    </div>
    <div class="stat-item">
        <span class="stat-val" style="color:#d97706;">{$totalServis}</span>
        <span class="stat-label">Rekomendasi Servis</span>
    </div>
    <div class="stat-item">
        <span class="stat-val" style="color:#dc2626;">{$totalGudang}</span>
        <span class="stat-label">Masuk Gudang</span>
    </div>
    <div class="stat-item">
        <span class="stat-val" style="color:#16a34a;">{$pctServis}%</span>
        <span class="stat-label">Persentase Servis</span>
    </div>
</div>

<!-- Tabel Ranking -->
<div class="section-title">Tabel Ranking Hasil Keputusan</div>
<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:11%">Kode Aset</th>
            <th style="width:11%">Jenis</th>
            <th style="width:13%">Divisi</th>
            <th style="width:5%">C1</th>
            <th style="width:5%">C2</th>
            <th style="width:5%">C3</th>
            <th style="width:5%">C4</th>
            <th style="width:5%">C5</th>
            <th style="width:9%">Skor SAW</th>
            <th style="width:12%">Rekomendasi</th>
            <th style="width:10%">Tgl Penilaian</th>
        </tr>
    </thead>
    <tbody>
        {$tableRows}
    </tbody>
</table>
</div>

<!-- Legend Nilai Kriteria -->
<div class="legend">
    <strong>Keterangan Nilai:</strong>
    <span style="color:#16a34a;">&#9632; 1 = Kondisi Baik</span>
    <span style="color:#d97706;">&#9632; 2 = Kondisi Sedang</span>
    <span style="color:#dc2626;">&#9632; 3 = Kondisi Buruk</span>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>C1</strong>=Usia &nbsp; <strong>C2</strong>=Kerusakan &nbsp; <strong>C3</strong>=Suku Cadang &nbsp;
    <strong>C4</strong>=Kompleksitas &nbsp; <strong>C5</strong>=Garansi
</div>

<!-- Tabel Bobot Kriteria -->
<div class="section-title" style="margin-top:12px;">Bobot Kriteria SAW (Aktif)</div>
<div class="table-wrap">
<table class="bobot-table" style="width:50%;">
    <thead>
        <tr>
            <th>Kode</th><th>Nama Kriteria</th><th>Atribut</th><th>Bobot</th><th>%</th>
        </tr>
    </thead>
    <tbody>
        {$bobotRows}
    </tbody>
</table>
</div>

<!-- Footer -->
<div class="footer">
    Laporan ini digenerate secara otomatis oleh Sistem SPK Manajemen Aset IT — Kutai Refinery Nusantara &copy; {$year}<br>
    Perhitungan menggunakan metode Simple Additive Weighting (SAW) | Skor &ge; {$threshold} &rarr; Masuk Gudang | Skor &lt; {$threshold} &rarr; Servis
</div>

</body>
</html>
HTML;

// Inject variabel PHP ke dalam HTML string
$nowDate  = date('d F Y, H:i') . ' WIB';
$threshold = number_format(SAW_THRESHOLD, 2);
$year     = date('Y');
$pctServis = $totalData > 0 ? round(($totalServis / $totalData) * 100) : 0;

$html = str_replace(
    ['{$filterLabel}', '{$totalData}', '{$totalServis}', '{$totalGudang}',
     '{$nowDate}', '{$threshold}', '{$year}', '{$pctServis}',
     '{$tableRows}', '{$bobotRows}'],
    [$filterLabel, $totalData, $totalServis, $totalGudang,
     $nowDate, $threshold, $year, $pctServis,
     $tableRows, $bobotRows],
    $html
);

// ── Render dengan DomPDF ──────────────────────────────────
$options = new Options();
$options->set('defaultFont',          'DejaVu Sans');
$options->set('isRemoteEnabled',      false);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'SPK_Hasil_Keputusan_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
