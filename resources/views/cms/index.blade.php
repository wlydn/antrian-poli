<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>CMS - Promo Contents</title>
  <link href="/assets/css/cms.css" rel="stylesheet" />
</head>
<body>
  <div class="container">
    <div class="topbar">
      <h1 class="m-0">Konten Promosi</h1>
      <a href="{{ route('cms.contents.create') }}" class="btn">+ Konten Baru</a>
    </div>

    @if (session('ok'))
      <div class="flash">{{ session('ok') }}</div>
    @endif

    <div class="card">
      @if ($items->count() === 0)
        <div class="muted">Belum ada konten. Klik "Konten Baru" untuk menambahkan.</div>
      @else
        <div class="muted mb-10">
          Total: {{ $items->total() }} item
        </div>
        <div class="overflow-auto">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Judul</th>
                <th>Tipe</th>
                <th>Media</th>
                <th>Aktif</th>
                <th>Urutan</th>
                <th>Jadwal</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($items as $i => $item)
                <tr>
                  <td>{{ ($items->currentPage() - 1) * $items->perPage() + $i + 1 }}</td>
                  <td>
                    <div class="fw-700">{{ $item->title }}</div>
                    <div class="muted"><code>{{ $item->file_path }}</code></div>
                  </td>
                  <td>{{ $item->media_type }}</td>
                  <td>
                    @if ($item->media_type === 'image')
                      <img class="thumb" src="{{ Storage::url($item->file_path) }}" alt="{{ $item->title }}"/>
                    @else
                      <div class="muted">Video</div>
                    @endif
                  </td>
                  <td>{{ $item->is_active ? 'Ya' : 'Tidak' }}</td>
                  <td>{{ $item->display_order }}</td>
                  <td>
                    <div>Mulai: {{ optional($item->starts_at)->format('Y-m-d H:i') ?? '-' }}</div>
                    <div>Selesai: {{ optional($item->ends_at)->format('Y-m-d H:i') ?? '-' }}</div>
                  </td>
                  <td class="row-actions">
                    <a class="btn secondary" href="{{ route('cms.contents.edit', $item) }}">Edit</a>
                    <form method="POST" action="{{ route('cms.contents.destroy', $item) }}" onsubmit="return confirm('Hapus konten ini?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn danger">Hapus</button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="mt-12">
          {{ $items->links() }}
        </div>
      @endif
    </div>

    <div class="mt-16">
      <a href="{{ route('display') }}" class="btn secondary">Lihat Halaman Display</a>
    </div>
  </div>
</body>
</html>
