<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>CMS - Form Konten Promosi</title>
  <link href="/assets/css/cms.css" rel="stylesheet" />
</head>
<body>
  <div class="container">
    <div class="topbar">
      <h1 class="m-0">Form Konten Promosi</h1>
      <div class="row">
        <a href="{{ route('cms.contents.index') }}" class="btn secondary">Kembali</a>
        <a href="{{ route('display') }}" class="btn muted">Lihat Display</a>
      </div>
    </div>

    @if (session('ok'))
      <div class="flash">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
      <div class="errors">
        <div class="fw-700 mb-6">Periksa kembali input Anda:</div>
        <ul class="m-0 pl-18">
          @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card">
      <form method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('cms.contents.update', $item) : route('cms.contents.store') }}">
        @csrf
        @if ($item->exists)
          @method('PUT')
        @endif

        <div class="field">
          <label for="title">Judul</label>
          <input type="text" id="title" name="title" value="{{ old('title', $item->title) }}" required placeholder="Judul konten"/>
        </div>

        <div class="grid">
          <div class="field">
            <label for="media_type">Tipe Media</label>
            <select id="media_type" name="media_type" required>
              <option value="image" @selected(old('media_type', $item->media_type ?: 'image') === 'image')>Gambar</option>
              <option value="video" @selected(old('media_type', $item->media_type ?: 'image') === 'video')>Video</option>
            </select>
          </div>

          <div class="field">
            <label for="display_order">Urutan Tampil</label>
            <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $item->display_order ?? 0) }}" min="0"/>
          </div>
        </div>

        <div class="field">
          <label for="file">File Media {{ $item->exists ? '(opsional saat edit)' : '(wajib saat tambah)' }}</label>
          <input type="file" id="file" name="file" @if(!$item->exists) required @endif accept="image/*,video/*"/>
          <div class="help">File akan disimpan ke storage/app/public/promos. Pastikan telah menjalankan perintah "php artisan storage:link".</div>
        </div>

        @if ($item->exists && $item->file_path)
          <div class="field">
            <label>Pratinjau Saat Ini</label>
            <div class="preview">
              @if ($item->media_type === 'image')
                <img class="max" src="{{ Storage::url($item->file_path) }}" alt="{{ $item->title }}"/>
              @else
                <video class="max" src="{{ Storage::url($item->file_path) }}" controls muted></video>
              @endif
            </div>
            <div class="help">Mengunggah file baru akan menggantikan media ini.</div>
          </div>
        @endif

        <div class="grid">
          <div class="field">
            <label for="starts_at">Mulai Tayang</label>
            <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at', optional($item->starts_at)->format('Y-m-d\TH:i')) }}"/>
            <div class="help">Kosongkan untuk langsung tayang.</div>
          </div>
          <div class="field">
            <label for="ends_at">Selesai Tayang</label>
            <input type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at', optional($item->ends_at)->format('Y-m-d\TH:i')) }}"/>
            <div class="help">Kosongkan bila tidak ada tanggal selesai.</div>
          </div>
        </div>

        <div class="field">
          <div class="row align-center">
            <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $item->is_active))/>
            <label for="is_active" class="m-0">Aktif</label>
          </div>
        </div>

        <div class="row mt-10">
          <button type="submit" class="btn">{{ $item->exists ? 'Simpan Perubahan' : 'Simpan' }}</button>
          <a href="{{ route('cms.contents.index') }}" class="btn secondary">Batal</a>
        </div>
      </form>
    </div>

    <div class="mt-16">
      <a href="{{ route('display') }}" class="btn muted">Lihat Display</a>
    </div>
  </div>
</body>
</html>
