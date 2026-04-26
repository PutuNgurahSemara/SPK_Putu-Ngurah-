<?php
// ============================================================
// pages/hasil.php — Halaman Hasil Keputusan SPK (Ranking)
// Menampilkan ranking skor tertinggi → terendah + Export
// ============================================================
declare(strict_types=1);

$db = getDB();

// ── Filter ────────────────────────────────────────────────
$filterRek = $_GET['rek'] ?? 'semua';
$validFilters = ['semua', 'Servis', 'Masuk Gudang', 'belum'];

if (!in_array($filterRek, $validFilters, true)) {
    $filterRek = 'semua';
}

// ── Query Ranking ─────────────────────────────────────────
// Ambil penilaian terbaru per perangkat (DISTINCT ON = PostgreSQL)
$whereClause = '';
$params      = [];

if ($filterRek === 'belum') {
    $whereClause = 'WHERE ps.skor_akhir IS NULL';
} elseif ($filterRek !== 'semua') {
    $whereClause = 'WHERE ps.rekomendasi = :rek';
    $params      = [':rek' => $filterRek];
}

$sql = "
    SELECT
        ROW_NUMBER() OVER (ORDER BY ps.skor_akhir DESC NULLS LAST) AS ranking,
        p.id            AS perangkat_id,
        p.kode_aset,
        p.jenis_perangkat,
        p.divisi_user,
        ps.id           AS penilaian_id,
        ps.c1_usia,
        ps.c2_kerusakan,
        ps.c3_part,
        ps.c4_kompleksitas,
        ps.c5_garansi,
        ps.skor_akhir,
        ps.rekomendasi,
        ps.tanggal_penilaian
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

// ── Statistik ringkas ──────────────────────────────────────
$stmtStat = $db->query("
    SELECT
        COUNT(*)                                           AS total,
        COUNT(*) FILTER (WHERE rekomendasi = 'Servis')    AS servis,
        COUNT(*) FILTER (WHERE rekomendasi = 'Masuk Gudang') AS gudang,
        ROUND(AVG(skor_akhir)::numeric, 4)                AS avg_skor,
        MAX(skor_akhir)                                    AS max_skor,
        MIN(skor_akhir)                                    AS min_skor
    FROM (
        SELECT DISTINCT ON (perangkat_id) rekomendasi, skor_akhir
        FROM penilaian_spk
        ORDER BY perangkat_id, tanggal_penilaian DESC
    ) latest
");
$stat = $stmtStat->fetch();
?>

<!-- ── Header + Tombol Export ─────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <p class="text-slate-400 text-sm mt-1">
            Ranking berdasarkan skor SAW tertinggi. Threshold rekomendasi:
            <span class="text-blue-400 font-mono font-semibold"><?= SAW_THRESHOLD ?></span>
        </p>
    </div>
    <!-- Tombol Export -->
    <div class="flex items-center gap-2 flex-shrink-0">
        <a href="exports/export_excel.php?rek=<?= urlencode($filterRek) ?>"
           target="_blank"
           class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-600
                  text-white text-sm font-semibold transition-all hover:shadow-lg hover:shadow-emerald-500/20">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z"/>
            </svg>
            Export Excel
        </a>
        <a href="exports/export_pdf.php?rek=<?= urlencode($filterRek) ?>"
           target="_blank"
           class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-700 hover:bg-red-600
                  text-white text-sm font-semibold transition-all hover:shadow-lg hover:shadow-red-500/20">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            Export PDF
        </a>
    </div>
</div>

<!-- ── Stat Cards Mini ────────────────────────────────────── -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
    <?php
    $miniStats = [
        ['label' => 'Total Dinilai',  'val' => $stat['total'] ?? 0,    'color' => 'text-white'],
        ['label' => 'Servis',         'val' => $stat['servis'] ?? 0,   'color' => 'text-amber-400'],
        ['label' => 'Masuk Gudang',   'val' => $stat['gudang'] ?? 0,   'color' => 'text-red-400'],
        ['label' => 'Rata-rata Skor', 'val' => number_format((float)($stat['avg_skor'] ?? 0), 4), 'color' => 'text-blue-400'],
        ['label' => 'Skor Tertinggi', 'val' => number_format((float)($stat['max_skor'] ?? 0), 4), 'color' => 'text-green-400'],
        ['label' => 'Skor Terendah',  'val' => number_format((float)($stat['min_skor'] ?? 0), 4), 'color' => 'text-slate-400'],
    ];
    foreach ($miniStats as $ms):
    ?>
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl px-4 py-3 text-center">
        <p class="text-slate-500 text-[10px] font-semibold uppercase tracking-wide mb-0.5"><?= $ms['label'] ?></p>
        <p class="<?= $ms['color'] ?> font-bold text-lg font-mono"><?= $ms['val'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Filter Bar ─────────────────────────────────────────── -->
<div class="flex items-center gap-2 mb-4">
    <span class="text-slate-400 text-xs font-medium">Filter:</span>
    <?php
    $filterOptions = [
        'semua'       => ['label' => 'Semua',        'class' => 'bg-slate-600 text-white'],
        'Servis'      => ['label' => 'Servis',        'class' => 'bg-amber-700 text-amber-100'],
        'Masuk Gudang'=> ['label' => 'Masuk Gudang',  'class' => 'bg-red-700 text-red-100'],
        'belum'       => ['label' => 'Belum Dihitung','class' => 'bg-slate-700 text-slate-300'],
    ];
    foreach ($filterOptions as $val => $opt):
        $isActive = $filterRek === $val;
    ?>
    <a href="index.php?hal=hasil&rek=<?= urlencode($val) ?>"
       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all
              <?= $isActive
                  ? $opt['class']
                  : 'bg-slate-800 text-slate-400 hover:bg-slate-700 border border-slate-700' ?>">
        <?= $opt['label'] ?>
    </a>
    <?php endforeach; ?>
    <span class="ml-auto text-slate-500 text-xs"><?= count($hasilList) ?> data</span>
</div>

<!-- ── Tabel Ranking ──────────────────────────────────────── -->
<div class="bg-slate-800 border border-slate-700/60 rounded-2xl overflow-hidden">

    <!-- Header -->
    <div class="overflow-x-auto">
    <table class="w-full min-w-[900px]">
        <thead>
            <tr class="bg-slate-700/40 border-b border-slate-700/60 text-xs font-semibold text-slate-400 uppercase tracking-wide">
                <th class="px-4 py-3 text-center w-12">#</th>
                <th class="px-4 py-3 text-left">Kode Aset</th>
                <th class="px-4 py-3 text-left">Jenis</th>
                <th class="px-4 py-3 text-left">Divisi</th>
                <th class="px-3 py-3 text-center w-10" title="C1: Usia">C1</th>
                <th class="px-3 py-3 text-center w-10" title="C2: Kerusakan">C2</th>
                <th class="px-3 py-3 text-center w-10" title="C3: Suku Cadang">C3</th>
                <th class="px-3 py-3 text-center w-10" title="C4: Kompleksitas">C4</th>
                <th class="px-3 py-3 text-center w-10" title="C5: Garansi">C5</th>
                <th class="px-4 py-3 text-center">Skor SAW</th>
                <th class="px-4 py-3 text-center">Rekomendasi</th>
                <th class="px-4 py-3 text-center">Tanggal</th>
                <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-700/40">

        <?php if (empty($hasilList)): ?>
            <tr>
                <td colspan="13" class="px-4 py-16 text-center text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <p class="text-sm">Belum ada data penilaian.</p>
                    <a href="index.php?hal=penilaian" class="text-blue-400 text-sm hover:underline mt-2 inline-block">
                        Mulai penilaian →
                    </a>
                </td>
            </tr>
        <?php else: ?>
        <?php foreach ($hasilList as $row):
            $isGudang = $row['rekomendasi'] === 'Masuk Gudang';
            $isServis = $row['rekomendasi'] === 'Servis';
            $rank     = (int)$row['ranking'];

            // Warna baris berdasarkan ranking tertinggi
            $rowHighlight = '';
            if ($rank === 1)     $rowHighlight = 'bg-red-900/10';
            elseif ($rank === 2) $rowHighlight = 'bg-red-900/5';

            // Badge rekomendasi
            $rekBadge = match($row['rekomendasi']) {
                'Masuk Gudang' => 'bg-red-900/50 text-red-300 border border-red-700/50',
                'Servis'       => 'bg-amber-900/50 text-amber-300 border border-amber-700/50',
                default        => 'bg-slate-700 text-slate-400 border border-slate-600',
            };

            // Warna nilai kriteria (1=green, 2=amber, 3=red)
            $nilaiColor = fn($v) => match((int)$v) {
                1 => 'text-green-400', 2 => 'text-amber-400', 3 => 'text-red-400', default => 'text-slate-400'
            };

            // Warna skor progress bar
            $skor    = (float)($row['skor_akhir'] ?? 0);
            $skorPct = round($skor * 100);
            $barColor = $skor >= SAW_THRESHOLD ? 'bg-red-500' : 'bg-amber-500';
        ?>
        <tr class="hover:bg-slate-700/20 transition-colors group <?= $rowHighlight ?>">

            <!-- Ranking -->
            <td class="px-4 py-3 text-center">
                <?php if ($rank <= 3 && $row['rekomendasi']): ?>
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                    <?= $rank === 1 ? 'bg-red-600 text-white' :
                       ($rank === 2 ? 'bg-red-800/70 text-red-200' : 'bg-slate-700 text-slate-300') ?>">
                    <?= $rank ?>
                </span>
                <?php else: ?>
                <span class="text-slate-500 text-sm font-mono"><?= $rank ?></span>
                <?php endif; ?>
            </td>

            <!-- Kode Aset -->
            <td class="px-4 py-3">
                <p class="text-slate-200 text-sm font-semibold font-mono"><?= htmlspecialchars($row['kode_aset']) ?></p>
            </td>

            <!-- Jenis -->
            <td class="px-4 py-3">
                <span class="text-slate-300 text-xs"><?= htmlspecialchars($row['jenis_perangkat']) ?></span>
            </td>

            <!-- Divisi -->
            <td class="px-4 py-3">
                <span class="text-slate-400 text-xs"><?= htmlspecialchars($row['divisi_user']) ?></span>
            </td>

            <!-- C1–C5 Nilai -->
            <?php foreach (['c1_usia','c2_kerusakan','c3_part','c4_kompleksitas','c5_garansi'] as $col): ?>
            <td class="px-3 py-3 text-center">
                <span class="text-sm font-bold font-mono <?= $nilaiColor($row[$col]) ?>">
                    <?= $row[$col] ?? '—' ?>
                </span>
            </td>
            <?php endforeach; ?>

            <!-- Skor SAW -->
            <td class="px-4 py-3 text-center">
                <?php if ($row['skor_akhir'] !== null): ?>
                <div>
                    <span class="text-sm font-bold font-mono
                                 <?= $isGudang ? 'text-red-400' : 'text-amber-400' ?>">
                        <?= number_format($skor, 4) ?>
                    </span>
                    <div class="mt-1 h-1 w-16 mx-auto bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full <?= $barColor ?> rounded-full"
                             style="width: <?= $skorPct ?>%"></div>
                    </div>
                </div>
                <?php else: ?>
                <span class="text-slate-600 text-xs">—</span>
                <?php endif; ?>
            </td>

            <!-- Rekomendasi -->
            <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $rekBadge ?>">
                    <?= $isGudang ? '🗂' : ($isServis ? '🔧' : '⏳') ?>
                    <?= htmlspecialchars($row['rekomendasi'] ?? 'Belum') ?>
                </span>
            </td>

            <!-- Tanggal -->
            <td class="px-4 py-3 text-center">
                <span class="text-slate-500 text-xs">
                    <?= $row['tanggal_penilaian']
                        ? date('d/m/Y', strtotime($row['tanggal_penilaian']))
                        : '—' ?>
                </span>
            </td>

            <!-- Aksi: Nilai Ulang -->
            <td class="px-4 py-3 text-center">
                <a href="index.php?hal=penilaian&perangkat_id=<?= $row['perangkat_id'] ?>"
                   class="opacity-0 group-hover:opacity-100 transition-opacity
                          inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs
                          bg-blue-900/40 text-blue-300 border border-blue-800/50 hover:bg-blue-800/50">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Nilai Ulang
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Legend -->
    <div class="px-5 py-3 border-t border-slate-700/50 flex flex-wrap gap-4 text-xs text-slate-500">
        <span>C1=Usia &nbsp; C2=Kerusakan &nbsp; C3=Suku Cadang &nbsp; C4=Kompleksitas &nbsp; C5=Garansi</span>
        <span class="ml-auto">
            <span class="text-green-400 font-mono">1</span>=Baik &ensp;
            <span class="text-amber-400 font-mono">2</span>=Sedang &ensp;
            <span class="text-red-400 font-mono">3</span>=Buruk
        </span>
    </div>
</div>
