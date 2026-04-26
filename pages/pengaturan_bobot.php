<?php
// ============================================================
// pages/pengaturan_bobot.php — Halaman Pengaturan Bobot Kriteria
// ============================================================
declare(strict_types=1);

$db      = getDB();
$kriteria = getAllKriteria();

// Hitung total bobot saat ini
$totalBobot = array_sum(array_column($kriteria, 'bobot'));
$totalValid = abs($totalBobot - 1.00) < 0.001; // toleransi floating point
?>

<!-- ── Header Halaman ─────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <p class="text-slate-400 text-sm mt-1">
            Atur bobot masing-masing kriteria SAW.
            <span class="text-amber-400 font-medium">Total bobot wajib = 1.00</span>
        </p>
    </div>
    <!-- Indikator Total Bobot Saat Ini -->
    <div class="flex items-center gap-2 px-4 py-2 rounded-xl border
                <?= $totalValid
                    ? 'bg-green-900/30 border-green-600/50 text-green-400'
                    : 'bg-red-900/30 border-red-600/50 text-red-400' ?>">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?php if ($totalValid): ?>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            <?php else: ?>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            <?php endif; ?>
        </svg>
        <span class="text-sm font-semibold">
            Total: <?= number_format($totalBobot, 2) ?>
            <?= $totalValid ? '✓ Valid' : '✗ Tidak Valid' ?>
        </span>
    </div>
</div>

<!-- ── Panduan Skala Bobot ────────────────────────────── -->
<div class="bg-blue-900/20 border border-blue-700/40 rounded-xl p-4 mb-6 text-sm text-blue-300">
    <p class="font-semibold text-blue-200 mb-1">📌 Panduan Pengaturan Bobot</p>
    <ul class="list-disc list-inside space-y-0.5 text-xs text-blue-300/80">
        <li>Semua kriteria berjenis <strong>Benefit</strong>: nilai lebih besar → mengarah ke "Masuk Gudang".</li>
        <li>Isikan bobot dalam format desimal (contoh: <code class="bg-slate-700 px-1 rounded">0.30</code> untuk 30%).</li>
        <li>Pastikan jumlah kelima bobot tepat sama dengan <strong>1.00</strong> sebelum menyimpan.</li>
        <li>Perubahan bobot akan langsung mempengaruhi perhitungan SAW pada penilaian selanjutnya.</li>
    </ul>
</div>

<!-- ── Form Pengaturan Bobot ──────────────────────────── -->
<form action="actions/bobot_action.php" method="POST" id="formBobot"
      class="bg-slate-800 border border-slate-700/60 rounded-2xl overflow-hidden">

    <!-- Table Header -->
    <div class="grid grid-cols-12 gap-4 px-6 py-3 bg-slate-700/40 border-b border-slate-700/60
                text-xs font-semibold text-slate-400 uppercase tracking-wide">
        <div class="col-span-1">Kode</div>
        <div class="col-span-4">Nama Kriteria</div>
        <div class="col-span-2">Atribut</div>
        <div class="col-span-3">Bobot (Desimal)</div>
        <div class="col-span-2 text-right">Persentase</div>
    </div>

    <!-- Rows Kriteria -->
    <div id="kriteriaRows" class="divide-y divide-slate-700/40">
    <?php foreach ($kriteria as $idx => $k): ?>
    <div class="grid grid-cols-12 gap-4 px-6 py-4 items-center hover:bg-slate-700/20 transition-colors">

        <!-- Kode Kriteria -->
        <div class="col-span-1">
            <input type="hidden" name="id[]" value="<?= (int)$k['id'] ?>">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg
                         bg-brand-700/20 text-brand-400 text-xs font-bold border border-brand-700/30">
                <?= htmlspecialchars($k['kode_kriteria']) ?>
            </span>
        </div>

        <!-- Nama Kriteria -->
        <div class="col-span-4">
            <input type="text"
                   name="nama_kriteria[]"
                   value="<?= htmlspecialchars($k['nama_kriteria']) ?>"
                   class="w-full bg-slate-700/50 border border-slate-600/50 rounded-lg px-3 py-2
                          text-slate-200 text-sm placeholder-slate-500
                          focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                          transition-all"
                   required>
        </div>

        <!-- Atribut (readonly — selalu Benefit) -->
        <div class="col-span-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                         bg-green-900/40 border border-green-700/50 text-green-400 text-xs font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                <?= htmlspecialchars($k['atribut']) ?>
            </span>
        </div>

        <!-- Input Bobot -->
        <div class="col-span-3">
            <input type="number"
                   name="bobot[]"
                   id="bobot_<?= $idx ?>"
                   value="<?= htmlspecialchars($k['bobot']) ?>"
                   min="0.01" max="1.00" step="0.01"
                   class="bobot-input w-full bg-slate-700/50 border border-slate-600/50 rounded-lg px-3 py-2
                          text-slate-200 text-sm font-mono
                          focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                          transition-all"
                   required
                   oninput="hitungTotal()">
        </div>

        <!-- Preview Persentase (live) -->
        <div class="col-span-2 text-right">
            <span id="pct_<?= $idx ?>"
                  class="text-sm font-semibold text-blue-400 font-mono">
                <?= number_format((float)$k['bobot'] * 100, 0) ?>%
            </span>
            <!-- Progress mini -->
            <div class="mt-1.5 h-1 bg-slate-700 rounded-full overflow-hidden">
                <div id="bar_<?= $idx ?>"
                     class="h-full bg-blue-500 rounded-full transition-all duration-300"
                     style="width: <?= min(100, round((float)$k['bobot'] * 100)) ?>%"></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <!-- Footer Form: Total + Tombol Simpan -->
    <div class="px-6 py-4 bg-slate-700/30 border-t border-slate-700/60
                flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">

        <!-- Indikator Total Live -->
        <div class="flex items-center gap-3">
            <div class="text-slate-400 text-sm">
                Total Bobot:
                <span id="totalBobotDisplay"
                      class="font-bold font-mono text-base ml-1
                             <?= $totalValid ? 'text-green-400' : 'text-red-400' ?>">
                    <?= number_format($totalBobot, 2) ?>
                </span>
                <span id="totalBobotStatus" class="ml-2 text-xs font-medium
                      <?= $totalValid ? 'text-green-400' : 'text-red-400' ?>">
                    <?= $totalValid ? '✓ Valid' : '✗ Harus = 1.00' ?>
                </span>
            </div>
        </div>

        <!-- Tombol Simpan -->
        <div class="flex items-center gap-3">
            <button type="button"
                    onclick="resetForm()"
                    class="px-4 py-2 rounded-lg border border-slate-600 text-slate-400 text-sm
                           hover:bg-slate-700 hover:text-slate-200 transition-all">
                Reset
            </button>
            <button type="submit"
                    id="btnSimpan"
                    class="px-6 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold
                           transition-all disabled:opacity-40 disabled:cursor-not-allowed
                           flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 13l4 4L19 7"/>
                </svg>
                Simpan Bobot
            </button>
        </div>
    </div>
</form>

<!-- ── Info Bobot Saat Ini (Visual) ──────────────────── -->
<div class="mt-5 bg-slate-800 border border-slate-700/60 rounded-2xl p-5">
    <h3 class="text-white font-semibold text-sm mb-4">Visualisasi Distribusi Bobot Saat Ini</h3>
    <div class="flex items-end gap-1 h-24">
        <?php
        $barColors = ['bg-blue-500','bg-red-500','bg-amber-500','bg-purple-500','bg-emerald-500'];
        foreach ($kriteria as $i => $k):
            $pct = min(100, round((float)$k['bobot'] * 100));
            $color = $barColors[$i % count($barColors)];
        ?>
        <div class="flex-1 flex flex-col items-center gap-1">
            <span class="text-xs font-semibold text-slate-300"><?= $pct ?>%</span>
            <div class="w-full <?= $color ?> rounded-t-md transition-all duration-500"
                 style="height: <?= max(4, $pct) ?>%"></div>
            <span class="text-slate-500 text-[10px] font-medium"><?= htmlspecialchars($k['kode_kriteria']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── JavaScript: Live Validation & Preview ─────────── -->
<script>
const bobotInputs     = document.querySelectorAll('.bobot-input');
const totalDisplay    = document.getElementById('totalBobotDisplay');
const totalStatus     = document.getElementById('totalBobotStatus');
const btnSimpan       = document.getElementById('btnSimpan');
const initialValues   = [...bobotInputs].map(i => i.value);

function hitungTotal() {
    let total = 0;
    bobotInputs.forEach((input, idx) => {
        const val = parseFloat(input.value) || 0;
        total += val;

        // Update persentase & bar
        const pct  = Math.round(val * 100);
        const pEl  = document.getElementById('pct_' + idx);
        const bEl  = document.getElementById('bar_' + idx);
        if (pEl) pEl.textContent = pct + '%';
        if (bEl) bEl.style.width = Math.min(100, pct) + '%';
    });

    total = Math.round(total * 100) / 100; // bulatkan ke 2 desimal
    const isValid = Math.abs(total - 1.00) < 0.001;

    // Update tampilan total
    totalDisplay.textContent = total.toFixed(2);
    totalDisplay.className = 'font-bold font-mono text-base ml-1 ' +
        (isValid ? 'text-green-400' : 'text-red-400');
    totalStatus.textContent = isValid ? '✓ Valid' : '✗ Harus = 1.00';
    totalStatus.className   = 'ml-2 text-xs font-medium ' +
        (isValid ? 'text-green-400' : 'text-red-400');

    // Enable/disable tombol simpan
    btnSimpan.disabled = !isValid;
}

function resetForm() {
    bobotInputs.forEach((input, idx) => {
        input.value = initialValues[idx];
    });
    hitungTotal();
}

// Jalankan saat load untuk setup awal
hitungTotal();
</script>
