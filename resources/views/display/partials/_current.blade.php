@php
  // Penentuan nomor antrian saat ini dengan mempertimbangkan status jadwal mulai
  $started = isset($started) ? (bool)$started : true;
  if (!$started) {
    $no = '000';
    $name = '—';
  } else {
    $no = $current->no_reg ?? null;
    $name = $current->nm_pasien ?? null;
  }
@endphp

<div class="current-wrap">
  <div class="flex-1">
    <div class="current-label">Nomor Antrian Saat Ini</div>
    @if($no && $name)
      <div class="current-row">
        <div class="current-no">{{ $no }}</div>
        <div class="current-name">{{ $name }}</div>
      </div>
    @else
      <div class="opacity-80 mt-8">Belum ada pasien menunggu.</div>
    @endif
  </div>
</div>
