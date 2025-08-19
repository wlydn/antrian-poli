@if(empty($sessions))
  <div class="queue-empty">
    <div class="fw-600 text-32 text-center opacity-95">
      Belum ada / telah berakhir sesi poli aktif untuk hari ini
    </div>
  </div>
@else
  <div class="queue-grid">
    @foreach($sessions as $s)
      @php
        $current = $s['current'] ?? null;
        $nowNo = $current->no_reg ?? '000';
        $nowName = $current->nm_pasien ?? '—';
      @endphp

      <div class="queue-item">
        <div class="title"><strong>{{ $s['nm_poli'] }}</strong></div>
        <div class="subtitle subtitle-lg">{{ $s['nm_dokter'] }}</div>

        <div class="now">{{ $nowNo }}</div>

        <div class="nowName"><strong>{{ $nowName }}</strong></div>
        <div class="subtitle">{{ $s['status'] ?? '-' }}</div>
      </div>
    @endforeach
  </div>
@endif
