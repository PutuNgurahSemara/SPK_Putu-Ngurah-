<?php
// ============================================================
// pages/pengaturan_bobot.php — Halaman Pengaturan Bobot Kriteria (v2 Dinamis)
// Mendukung tambah/hapus/edit kriteria secara bebas
// Total bobot WAJIB = 1.00 (100%) sebelum bisa disimpan
// ============================================================
declare(strict_types=1);

$db      = getDB();
$kriteria = getAllKriteria();

// Hitung total bobot saat ini
$totalBobot = array_sum(array_column($kriteria, 'bobot'));
$totalValid = abs($totalBobot - 1.00) < 0.001;
?>

<!-- ── Header Halaman ─────────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <p class="text-slate-400 text-sm mt-1">
            Atur bobot masing-masing kriteria SAW. Tambah atau hapus kriteria sesuai kebutuhan.
            <span class="text-amber-400 font-medium">Total bobot wajib tepat = 1.00 (100%)</span>
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

<!-- ── Panduan ─────────────────────────────────────────────── -->
<div class="bg-blue-900/20 border border-blue-700/40 rounded-xl p-4 mb-6 text-sm text-blue-300">
    <p class="font-semibold text-blue-200 mb-1">📌 Panduan Pengaturan Bobot</p>
    <ul class="list-disc list-inside space-y-0.5 text-xs text-blue-300/80">
        <li>Klik <strong>Tambah Kriteria</strong> untuk menambah kriteria baru.</li>
        <li>Isi bobot dalam format desimal (contoh: <code class="bg-slate-700 px-1 rounded">0.30</code> = 30%).</li>
        <li>Pastikan jumlah seluruh bobot tepat <strong>1.00</strong> sebelum menyimpan.</li>
        <li>Klik ikon pensil (<strong>▼</strong>) untuk mengatur label pilihan nilai (1/2/3) tiap kriteria.</li>
        <li>Kriteria yang sudah memiliki data penilaian <strong>tidak dapat dihapus</strong>.</li>
    </ul>
</div>

<!-- ── Form Pengaturan Bobot ──────────────────────────────── -->
<form action="actions/bobot_action.php" method="POST" id="formBobot">
    <!-- Input tersembunyi untuk ID yang akan dihapus -->
    <input type="hidden" name="hapus_ids" id="hapusIds" value="">

    <!-- ── Tabel Kriteria ──────────────────────────────────── -->
    <div class="bg-slate-800 border border-slate-700/60 rounded-2xl overflow-hidden mb-4">

        <!-- Table Header -->
        <div class="grid gap-3 px-5 py-3 bg-slate-700/40 border-b border-slate-700/60
                    text-xs font-semibold text-slate-400 uppercase tracking-wide"
             style="grid-template-columns: 60px 1fr 100px 120px 80px 44px;">
            <div>Kode</div>
            <div>Nama Kriteria</div>
            <div>Atribut</div>
            <div>Bobot (Desimal)</div>
            <div class="text-right">Persen</div>
            <div></div>
        </div>

        <!-- Rows Kriteria -->
        <div id="kriteriaRows" class="divide-y divide-slate-700/40">
        <?php foreach ($kriteria as $idx => $k): ?>
        <div class="kriteria-row" id="row_ex_<?= (int)$k['id'] ?>" data-id="<?= (int)$k['id'] ?>">
            <!-- Baris Utama -->
            <div class="grid gap-3 px-5 py-4 items-center hover:bg-slate-700/20 transition-colors"
                 style="grid-template-columns: 60px 1fr 100px 120px 80px 44px;">

                <input type="hidden" name="id[]" value="<?= (int)$k['id'] ?>">

                <!-- Kode -->
                <div>
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg
                                 bg-brand-700/20 text-brand-400 text-xs font-bold border border-brand-700/30">
                        <?= htmlspecialchars($k['kode_kriteria']) ?>
                    </span>
                </div>

                <!-- Nama Kriteria -->
                <div>
                    <input type="text"
                           name="nama_kriteria[]"
                           value="<?= htmlspecialchars($k['nama_kriteria']) ?>"
                           class="w-full bg-slate-700/50 border border-slate-600/50 rounded-lg px-3 py-2
                                  text-slate-200 text-sm placeholder-slate-500
                                  focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                                  transition-all"
                           required placeholder="Nama kriteria">
                </div>

                <!-- Atribut (hanya Benefit saat ini) -->
                <div>
                    <input type="hidden" name="atribut[]" value="Benefit">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                                 bg-green-900/40 border border-green-700/50 text-green-400 text-xs font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                        Benefit
                    </span>
                </div>

                <!-- Input Bobot -->
                <div>
                    <input type="number"
                           name="bobot[]"
                           value="<?= htmlspecialchars((string)$k['bobot']) ?>"
                           min="0.0001" max="1.0000" step="0.0001"
                           class="bobot-input w-full bg-slate-700/50 border border-slate-600/50 rounded-lg px-3 py-2
                                  text-slate-200 text-sm font-mono
                                  focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                                  transition-all"
                           required oninput="hitungTotal()">
                </div>

                <!-- Preview Persentase -->
                <div class="text-right">
                    <span class="pct-label text-sm font-semibold text-blue-400 font-mono">
                        <?= number_format((float)$k['bobot'] * 100, 1) ?>%
                    </span>
                    <div class="mt-1.5 h-1 bg-slate-700 rounded-full overflow-hidden">
                        <div class="pct-bar h-full bg-blue-500 rounded-full transition-all duration-300"
                             style="width: <?= min(100, round((float)$k['bobot'] * 100)) ?>%"></div>
                    </div>
                </div>

                <!-- Tombol Hapus -->
                <div class="flex justify-center">
                    <button type="button"
                            onclick="hapusExistingRow(<?= (int)$k['id'] ?>, this)"
                            title="Hapus kriteria"
                            class="w-8 h-8 flex items-center justify-center rounded-lg
                                   text-slate-500 hover:text-red-400 hover:bg-red-900/20
                                   border border-transparent hover:border-red-700/40
                                   transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Baris Detail: Label Nilai (Collapsible) -->
            <div class="detail-panel hidden px-5 pb-4">
                <div class="bg-slate-700/30 rounded-xl p-4 border border-slate-700/50">
                    <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide mb-3">
                        🏷️ Label Pilihan Nilai
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <?php foreach ([1,2,3] as $v): ?>
                        <div>
                            <label class="block text-slate-500 text-xs mb-1">Nilai <?= $v ?></label>
                            <input type="text"
                                   name="nilai_<?= $v ?>[]"
                                   value="<?= htmlspecialchars($k['nilai_'.$v]) ?>"
                                   class="w-full bg-slate-700 border border-slate-600/50 rounded-lg px-3 py-1.5
                                          text-slate-300 text-xs
                                          focus:outline-none focus:ring-1 focus:ring-blue-500/50"
                                   placeholder="Label untuk nilai <?= $v ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Tombol Toggle tersembunyi di sini agar klik "Atur Label" bisa menutup -->
            </div>

            <!-- Toggle Label -->
            <div class="px-5 pb-3 -mt-1">
                <button type="button"
                        onclick="toggleDetail(this)"
                        class="text-slate-600 hover:text-slate-400 text-xs flex items-center gap-1 transition-colors">
                    <svg class="toggle-icon w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    Atur Label Nilai
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <!-- Footer Form -->
        <div class="px-5 py-4 bg-slate-700/30 border-t border-slate-700/60
                    flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">

            <!-- Indikator Total Live -->
            <div class="flex items-center gap-3">
                <div class="text-slate-400 text-sm">
                    Total Bobot:
                    <span id="totalBobotDisplay"
                          class="font-bold font-mono text-base ml-1
                                 <?= $totalValid ? 'text-green-400' : 'text-red-400' ?>">
                        <?= number_format($totalBobot, 4) ?>
                    </span>
                    <span id="totalBobotStatus" class="ml-2 text-xs font-medium
                          <?= $totalValid ? 'text-green-400' : 'text-red-400' ?>">
                        <?= $totalValid ? '✓ Valid (100%)' : '✗ Harus = 1.00' ?>
                    </span>
                </div>
                <!-- Progress bar total -->
                <div class="hidden sm:block w-32 h-2 bg-slate-700 rounded-full overflow-hidden">
                    <div id="totalBar" class="h-full rounded-full transition-all duration-300
                                               <?= $totalValid ? 'bg-green-500' : 'bg-red-500' ?>"
                         style="width: <?= min(100, round($totalBobot * 100)) ?>%"></div>
                </div>
            </div>

            <!-- Tombol Aksi -->
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
                    Simpan Perubahan
                </button>
            </div>
        </div>
    </div>

    <!-- Tombol Tambah Kriteria -->
    <button type="button"
            onclick="tambahKriteria()"
            id="btnTambah"
            class="w-full py-3 rounded-xl border-2 border-dashed border-slate-600 text-slate-400 text-sm
                   hover:border-blue-500/60 hover:text-blue-400 hover:bg-blue-900/10
                   transition-all flex items-center justify-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah Kriteria Baru
    </button>
</form>

<!-- ── Visualisasi Distribusi Bobot ──────────────────────── -->
<div class="mt-5 bg-slate-800 border border-slate-700/60 rounded-2xl p-5">
    <h3 class="text-white font-semibold text-sm mb-4">Visualisasi Distribusi Bobot Saat Ini</h3>
    <div class="flex items-end gap-1.5 h-24" id="chartBobot">
        <?php
        $barColors = ['bg-blue-500','bg-red-500','bg-amber-500','bg-purple-500','bg-emerald-500',
                      'bg-cyan-500','bg-rose-500','bg-lime-500','bg-indigo-500','bg-orange-500'];
        foreach ($kriteria as $i => $k):
            $pct   = min(100, round((float)$k['bobot'] * 100));
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

<!-- ── Template Baris Baru (hidden, diklon via JS) ─────────── -->
<template id="rowTemplate">
    <div class="kriteria-row new-row" id="row_new_IDX" data-id="0">
        <div class="grid gap-3 px-5 py-4 items-center bg-blue-900/10 border-l-2 border-blue-500/50 hover:bg-slate-700/20 transition-colors"
             style="grid-template-columns: 60px 1fr 100px 120px 80px 44px;">

            <input type="hidden" name="id[]" value="0">

            <!-- Kode (placeholder, diisi server) -->
            <div>
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg
                             bg-blue-700/20 text-blue-400 text-xs font-bold border border-blue-700/30">
                    C?
                </span>
            </div>

            <!-- Nama Kriteria -->
            <div>
                <input type="text"
                       name="nama_kriteria[]"
                       placeholder="Nama kriteria baru..."
                       class="w-full bg-slate-700/50 border border-blue-600/50 rounded-lg px-3 py-2
                              text-slate-200 text-sm placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50
                              transition-all"
                       required>
            </div>

            <!-- Atribut -->
            <div>
                <input type="hidden" name="atribut[]" value="Benefit">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                             bg-green-900/40 border border-green-700/50 text-green-400 text-xs font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                    Benefit
                </span>
            </div>

            <!-- Input Bobot -->
            <div>
                <input type="number"
                       name="bobot[]"
                       min="0.0001" max="1.0000" step="0.0001"
                       placeholder="0.00"
                       class="bobot-input w-full bg-slate-700/50 border border-blue-600/50 rounded-lg px-3 py-2
                              text-slate-200 text-sm font-mono
                              focus:outline-none focus:ring-2 focus:ring-blue-500/50
                              transition-all"
                       required oninput="hitungTotal()">
            </div>

            <!-- Persentase -->
            <div class="text-right">
                <span class="pct-label text-sm font-semibold text-blue-400 font-mono">0%</span>
                <div class="mt-1.5 h-1 bg-slate-700 rounded-full overflow-hidden">
                    <div class="pct-bar h-full bg-blue-500 rounded-full transition-all duration-300"
                         style="width: 0%"></div>
                </div>
            </div>

            <!-- Tombol Hapus Baris Baru -->
            <div class="flex justify-center">
                <button type="button"
                        onclick="hapusBaruRow(this)"
                        title="Batalkan tambah kriteria"
                        class="w-8 h-8 flex items-center justify-center rounded-lg
                               text-slate-500 hover:text-red-400 hover:bg-red-900/20
                               border border-transparent hover:border-red-700/40
                               transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Detail Label Nilai (Collapsible) -->
        <div class="detail-panel hidden px-5 pb-4">
            <div class="bg-slate-700/30 rounded-xl p-4 border border-slate-700/50">
                <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide mb-3">
                    🏷️ Label Pilihan Nilai (Opsional)
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <?php foreach ([1,2,3] as $v): ?>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1">Nilai <?= $v ?></label>
                        <input type="text"
                               name="nilai_<?= $v ?>[]"
                               placeholder="<?= $v === 1 ? '1 — Nilai Rendah' : ($v === 2 ? '2 — Nilai Sedang' : '3 — Nilai Tinggi') ?>"
                               class="w-full bg-slate-700 border border-slate-600/50 rounded-lg px-3 py-1.5
                                      text-slate-300 text-xs
                                      focus:outline-none focus:ring-1 focus:ring-blue-500/50">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="px-5 pb-3 -mt-1">
            <button type="button"
                    onclick="toggleDetail(this)"
                    class="text-slate-600 hover:text-slate-400 text-xs flex items-center gap-1 transition-colors">
                <svg class="toggle-icon w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                Atur Label Nilai
            </button>
        </div>
    </div>
</template>

<!-- ── JavaScript ─────────────────────────────────────────── -->
<script>
let newRowCounter = 0;
let hapusIds      = [];
const initialValues = [...document.querySelectorAll('.bobot-input')].map(i => i.value);

// ── Toggle detail label nilai ─────────────────────────────
function toggleDetail(btn) {
    const row    = btn.closest('.kriteria-row');
    const panel  = row.querySelector('.detail-panel');
    const icon   = btn.querySelector('.toggle-icon');
    const isOpen = !panel.classList.contains('hidden');

    if (isOpen) {
        panel.classList.add('hidden');
        icon.style.transform = '';
    } else {
        panel.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
    }
}

// ── Tambah baris kriteria baru ────────────────────────────
function tambahKriteria() {
    newRowCounter++;
    const tmpl  = document.getElementById('rowTemplate');
    const clone = tmpl.content.cloneNode(true);
    const div   = clone.querySelector('.kriteria-row');
    div.id = 'row_new_' + newRowCounter;
    document.getElementById('kriteriaRows').appendChild(div);
    // Fokus ke input nama baru
    div.querySelector('input[name="nama_kriteria[]"]').focus();
    hitungTotal();
}

// ── Hapus baris baru (batalkan) ───────────────────────────
function hapusBaruRow(btn) {
    btn.closest('.kriteria-row').remove();
    hitungTotal();
}

// ── Hapus kriteria existing ───────────────────────────────
function hapusExistingRow(id, btn) {
    if (!confirm('Hapus kriteria ini?\n\nJika ada penilaian yang menggunakan kriteria ini, penghapusan akan ditolak oleh server.')) {
        return;
    }

    hapusIds.push(id);
    document.getElementById('hapusIds').value = hapusIds.join(',');

    const row = btn.closest('.kriteria-row');
    // Visual: tampilkan sebagai terhapus
    row.style.opacity    = '0.35';
    row.style.pointerEvents = 'none';
    row.querySelectorAll('input').forEach(i => { i.disabled = true; });
    // Tandai dengan label
    const badge = document.createElement('div');
    badge.className = 'px-5 pb-2 text-xs text-red-400 font-medium';
    badge.textContent = '🗑 Ditandai untuk dihapus';
    row.appendChild(badge);

    hitungTotal();
}

// ── Hitung total bobot secara live ────────────────────────
function hitungTotal() {
    const inputs = document.querySelectorAll('.bobot-input:not(:disabled)');
    let total = 0;

    inputs.forEach(input => {
        const row = input.closest('.kriteria-row');
        // Skip baris yang sedang dalam proses hapus
        if (row && row.style.opacity === '0.35') return;

        const val  = parseFloat(input.value) || 0;
        total += val;

        // Update pct & bar per baris
        const pctLabel = row ? row.querySelector('.pct-label') : null;
        const pctBar   = row ? row.querySelector('.pct-bar')   : null;
        const pct      = Math.round(val * 1000) / 10; // 1 desimal
        if (pctLabel) pctLabel.textContent = pct.toFixed(1) + '%';
        if (pctBar)   pctBar.style.width   = Math.min(100, Math.round(val * 100)) + '%';
    });

    total = Math.round(total * 10000) / 10000; // bulatkan 4 desimal
    const isValid   = Math.abs(total - 1.00) < 0.001;
    const totalPct  = Math.min(100, Math.round(total * 100));

    // Update tampilan total
    const display = document.getElementById('totalBobotDisplay');
    const status  = document.getElementById('totalBobotStatus');
    const bar     = document.getElementById('totalBar');

    display.textContent  = total.toFixed(4);
    display.className    = 'font-bold font-mono text-base ml-1 ' + (isValid ? 'text-green-400' : 'text-red-400');
    status.textContent   = isValid ? '✓ Valid (100%)' : '✗ Harus = 1.00 (saat ini: ' + (total * 100).toFixed(1) + '%)';
    status.className     = 'ml-2 text-xs font-medium ' + (isValid ? 'text-green-400' : 'text-red-400');

    if (bar) {
        bar.style.width = totalPct + '%';
        bar.className   = 'h-full rounded-full transition-all duration-300 ' + (isValid ? 'bg-green-500' : 'bg-red-500');
    }

    // Enable/disable tombol simpan
    document.getElementById('btnSimpan').disabled = !isValid;
}

// ── Reset form ke nilai awal ──────────────────────────────
function resetForm() {
    if (!confirm('Reset semua perubahan ke nilai awal?')) return;

    // Hapus baris baru
    document.querySelectorAll('.new-row').forEach(r => r.remove());

    // Restore nilai original
    const inputs = document.querySelectorAll('.bobot-input');
    inputs.forEach((input, idx) => {
        if (idx < initialValues.length) {
            input.value = initialValues[idx];
            input.disabled = false;
        }
    });

    // Restore opacity baris yang ditandai hapus
    document.querySelectorAll('.kriteria-row').forEach(row => {
        row.style.opacity      = '';
        row.style.pointerEvents = '';
        row.querySelectorAll('input').forEach(i => { i.disabled = false; });
        row.querySelectorAll('.text-red-400.font-medium').forEach(el => el.remove());
    });

    // Reset hapusIds
    hapusIds = [];
    document.getElementById('hapusIds').value = '';

    hitungTotal();
}

// ── Jalankan saat load ────────────────────────────────────
hitungTotal();
</script>
