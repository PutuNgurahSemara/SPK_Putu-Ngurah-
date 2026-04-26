<?php
// ============================================================
// exports/export_excel.php — Export Hasil Keputusan ke XLSX
// Library: PHPSpreadsheet (jalankan: composer install)
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

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

// Ambil bobot dari DB untuk metadata sheet
$kriteria  = getAllKriteria();

// ── Buat Spreadsheet ──────────────────────────────────────
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('SPK Aset IT - KRN')
    ->setTitle('Hasil Keputusan SPK')
    ->setDescription('Hasil penilaian SAW Manajemen Aset IT Kutai Refinery Nusantara')
    ->setKeywords('SPK SAW Aset IT KRN')
    ->setCategory('Laporan');

// ═══════════════════════════════════════════════════════════
// SHEET 1: Hasil Keputusan (Ranking)
// ═══════════════════════════════════════════════════════════
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Hasil Keputusan');

// ── Helper: Style Shorthand ───────────────────────────────
$applyStyle = function (string $range, array $style) use ($sheet) {
    $sheet->getStyle($range)->applyFromArray($style);
};

$borderAll = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']],
    ],
];
$borderThick = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E3A8A']],
    ],
];

// ── BARIS 1: Judul Utama ──────────────────────────────────
$sheet->mergeCells('A1:M1');
$sheet->setCellValue('A1', 'LAPORAN HASIL KEPUTUSAN SPK MANAJEMEN ASET IT');
$applyStyle('A1:M1', [
    'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF1E3A8A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(28);

// ── BARIS 2: Sub-judul ────────────────────────────────────
$sheet->mergeCells('A2:M2');
$sheet->setCellValue('A2', 'Kutai Refinery Nusantara — Metode Simple Additive Weighting (SAW)');
$applyStyle('A2:M2', [
    'font'      => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FFBFDBFE']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF1E3A8A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// ── BARIS 3: Info Generate ────────────────────────────────
$sheet->mergeCells('A3:M3');
$sheet->setCellValue('A3',
    'Digenerate: ' . date('d F Y, H:i') . ' WIB  |  '
    . 'Filter: ' . $filterRek . '  |  '
    . 'Total Data: ' . count($hasilList)
);
$applyStyle('A3:M3', [
    'font'      => ['size' => 9, 'color' => ['argb' => 'FF94A3B8']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF0F172A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->getRowDimension(3)->setRowHeight(16);

// ── BARIS 4: Kosong / Separator ───────────────────────────
$sheet->getRowDimension(4)->setRowHeight(6);

// ── BARIS 5: Header Tabel ─────────────────────────────────
$headers = [
    'A' => 'No.',       'B' => 'Kode Aset',   'C' => 'Jenis Perangkat',
    'D' => 'Divisi',    'E' => 'C1 Usia',     'F' => 'C2 Kerusakan',
    'G' => 'C3 Part',   'H' => 'C4 Kompleks', 'I' => 'C5 Garansi',
    'J' => 'Skor SAW',  'K' => 'Rekomendasi', 'L' => 'Tgl Penilaian',
    'M' => 'Threshold',
];
foreach ($headers as $col => $label) {
    $sheet->setCellValue("{$col}5", $label);
}
$applyStyle('A5:M5', [
    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF1D4ED8']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF3B82F6']]],
]);
$sheet->getRowDimension(5)->setRowHeight(22);

// ── BARIS 6+: Data ────────────────────────────────────────
$row = 6;
foreach ($hasilList as $data) {
    $skor  = $data['skor_akhir'] !== null ? round((float)$data['skor_akhir'], 4) : null;
    $isGud = $data['rekomendasi'] === 'Masuk Gudang';
    $isServ = $data['rekomendasi'] === 'Servis';

    $sheet->setCellValue("A{$row}", $data['ranking']);
    $sheet->setCellValue("B{$row}", $data['kode_aset']);
    $sheet->setCellValue("C{$row}", $data['jenis_perangkat']);
    $sheet->setCellValue("D{$row}", $data['divisi_user']);
    $sheet->setCellValue("E{$row}", (int)$data['c1_usia']);
    $sheet->setCellValue("F{$row}", (int)$data['c2_kerusakan']);
    $sheet->setCellValue("G{$row}", (int)$data['c3_part']);
    $sheet->setCellValue("H{$row}", (int)$data['c4_kompleksitas']);
    $sheet->setCellValue("I{$row}", (int)$data['c5_garansi']);
    $sheet->setCellValue("J{$row}", $skor);
    $sheet->setCellValue("K{$row}", $data['rekomendasi'] ?? 'Belum Dihitung');
    $sheet->setCellValue("L{$row}", $data['tanggal_penilaian']
        ? date('d/m/Y', strtotime($data['tanggal_penilaian'])) : '—');
    $sheet->setCellValue("M{$row}", SAW_THRESHOLD);

    // Format angka desimal kolom J & M
    $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('0.0000');
    $sheet->getStyle("M{$row}")->getNumberFormat()->setFormatCode('0.00');

    // Alignment
    foreach (['A','E','F','G','H','I','J','K','L','M'] as $col) {
        $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Warna baris: Gudang = merah muda, Servis = kuning muda, genap = sedikit abu
    if ($isGud) {
        $fillColor = 'FFFFF1F2'; // red-50
    } elseif ($isServ) {
        $fillColor = 'FFFFFBEB'; // amber-50
    } else {
        $fillColor = ($row % 2 === 0) ? 'FFF8FAFC' : 'FFFFFFFF';
    }
    $applyStyle("A{$row}:M{$row}", [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => $fillColor]],
        ...$borderAll,
    ]);

    // Font warna rekomendasi
    if ($isGud) {
        $sheet->getStyle("K{$row}")->getFont()->setColor(new Color('FFDC2626'))->setBold(true);
    } elseif ($isServ) {
        $sheet->getStyle("K{$row}")->getFont()->setColor(new Color('FFD97706'))->setBold(true);
    }

    $row++;
}

// ── Lebar Kolom ──────────────────────────────────────────
$colWidths = [
    'A'=>6, 'B'=>15, 'C'=>16, 'D'=>18, 'E'=>10,
    'F'=>12, 'G'=>10, 'H'=>12, 'I'=>10, 'J'=>12, 'K'=>16, 'L'=>13, 'M'=>11,
];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// ── Freeze baris header ───────────────────────────────────
$sheet->freezePane('A6');

// ── Auto filter ───────────────────────────────────────────
$lastDataRow = $row - 1;
if ($lastDataRow >= 6) {
    $sheet->setAutoFilter("A5:M{$lastDataRow}");
}

// ═══════════════════════════════════════════════════════════
// SHEET 2: Bobot Kriteria
// ═══════════════════════════════════════════════════════════
$sheetBobot = $spreadsheet->createSheet();
$sheetBobot->setTitle('Bobot Kriteria');

$sheetBobot->mergeCells('A1:E1');
$sheetBobot->setCellValue('A1', 'BOBOT KRITERIA SAW — AKTIF');
$sheetBobot->getStyle('A1:E1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF1E3A8A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$headerBobot = ['A' => 'Kode', 'B' => 'Nama Kriteria', 'C' => 'Atribut', 'D' => 'Bobot', 'E' => 'Persentase'];
foreach ($headerBobot as $col => $h) {
    $sheetBobot->setCellValue("{$col}2", $h);
}
$sheetBobot->getStyle('A2:E2')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$bRow = 3;
foreach ($kriteria as $kr) {
    $sheetBobot->setCellValue("A{$bRow}", $kr['kode_kriteria']);
    $sheetBobot->setCellValue("B{$bRow}", $kr['nama_kriteria']);
    $sheetBobot->setCellValue("C{$bRow}", $kr['atribut']);
    $sheetBobot->setCellValue("D{$bRow}", (float)$kr['bobot']);
    $sheetBobot->setCellValue("E{$bRow}", round((float)$kr['bobot'] * 100, 0) . '%');
    $sheetBobot->getStyle("D{$bRow}")->getNumberFormat()->setFormatCode('0.00');
    $sheetBobot->getStyle("A{$bRow}:E{$bRow}")->applyFromArray($borderAll);
    $bRow++;
}

// Total bobot
$sheetBobot->setCellValue("C{$bRow}", 'TOTAL');
$sheetBobot->setCellValue("D{$bRow}", "=SUM(D3:D" . ($bRow - 1) . ")");
$sheetBobot->getStyle("C{$bRow}:E{$bRow}")->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFDBEAFE']],
]);
$sheetBobot->getStyle("D{$bRow}")->getNumberFormat()->setFormatCode('0.00');

foreach (['A' => 8, 'B' => 30, 'C' => 12, 'D' => 10, 'E' => 12] as $col => $w) {
    $sheetBobot->getColumnDimension($col)->setWidth($w);
}

// ── Aktifkan Sheet 1 saat dibuka ─────────────────────────
$spreadsheet->setActiveSheetIndex(0);

// ── Output ke Browser ─────────────────────────────────────
$filename = 'SPK_Hasil_Keputusan_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
