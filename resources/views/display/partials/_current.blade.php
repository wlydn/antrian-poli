@php
  // Penentuan nomor antrian saat ini dengan mempertimbangkan status jadwal mulai
  $started = isset($started) ? (bool)$started : true;
  $current = $current ?? ($pasien[0] ?? null);

  if (!$started) {
    // Sebelum praktik dimulai, paksa tampil "000"
    $no = '000';
    $name = '—';
  } else {
    // Sudah mulai: tampilkan pasien terdepan berstatus 'Belum' jika ada
    $no = $current->no_reg ?? null;
    $name = $current->nm_pasien ?? null;
  }
@endphp

<div class="current-wrap" style="display:flex;gap:16px;align-items:center;justify-content:space-between;padding:12px 16px;border:1px solid #e5e7eb;border-radius:12px;background:#ffffff;margin-bottom:12px">
  <div style="flex:1">
    <div style="font-weight:700;font-size:18px;opacity:.85">Nomor Antrian Saat Ini</div>
    @if($no && $name)
      <div style="display:flex;align-items:baseline;gap:16px;margin-top:8px">
        <div class="current-no" style="font-size:100px;font-weight:900;letter-spacing:3px;line-height:1">{{ $no }}</div>
        <div class="current-name" style="font-size:28px;font-weight:700;opacity:.95">{{ $name }}</div>
      </div>
    @else
      <div style="opacity:.8;margin-top:8px">Belum ada pasien menunggu.</div>
    @endif
  </div>
</div>
