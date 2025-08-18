@php
  $no = 1;
@endphp

@if(empty($sessions))
  <div style="width:100%;display:flex;align-items:center;justify-content:center;min-height:50vh;">
    <div style="font-weight:800;font-size:32px;text-align:center;opacity:.95">
      Belum ada sesi poli aktif untuk hari ini.
    </div>
  </div>
@else
  <div class="queue-grid" style="display:grid;grid-template-columns:repeat(4, minmax(0, 1fr));gap:4px;align-content:start">
  @foreach($sessions as $s)
    @php
      $current = $s['current'] ?? null;
      // Nomor & nama pasien yang sedang dipanggil
      $nowNo = $current->no_reg ?? '000';
      $nowName = $current->nm_pasien ?? '—';
    @endphp

    <div class="queue-item" >
      <div class="title" style="text-align:center; font-size:18px"><strong>{{ $s['nm_poli'] }}</strong></div>
      <div class="subtitle" style="text-align:center; font-size:16px">{{ $s['nm_dokter'] }}</div>

      <div class="now" style="text-align:center">{{ $nowNo }}</div>

      <div class="nowName" style="text-align:center"><strong>{{ $nowName }}</strong></div>
      <div class="subtitle" style="text-align:center">{{ $s['status'] ?? '-' }}</div>

    </div>
  @endforeach
  </div>
@endif
