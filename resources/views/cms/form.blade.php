<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>CMS - Form Konten Promosi</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#0b1020;color:#fff;margin:0}
    .container{max-width:900px;margin:0 auto;padding:24px}
    .card{background:#121936;border-radius:12px;padding:20px}
    a.btn, button.btn, input.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;border:none;border-radius:8px;padding:10px 14px;font-weight:600;cursor:pointer}
    .btn.secondary{background:#374151}
    .btn.danger{background:#dc2626}
    .btn.muted{background:#4b5563}
    .muted{opacity:.85}
    .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .field{display:flex;flex-direction:column;margin-bottom:14px}
    .field label{margin-bottom:6px;color:#a3b1e1;font-weight:600}
    .field input[type="text"],
    .field input[type="number"],
    .field input[type="datetime-local"],
    .field select,
    .field input[type="file"]{background:#0b122a;border:1px solid #1f2a44;color:#fff;border-radius:8px;padding:10px 12px}
    .help{font-size:12px;opacity:.75;margin-top:6px}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .errors{background:#7f1d1d;color:#fee2e2;border-radius:8px;padding:12px 14px;margin-bottom:14px}
    .flash{background:#065f46;color:#d1fae5;padding:10px 12px;border-radius:8px;margin-bottom:14px}
    .preview{background:#0b122a;border:1px solid #1f2a44;border-radius:10px;padding:12px;display:flex;align-items:center;justify-content:center;min-height:180px}
    img.max, video.max{max-width:100%;max-height:320px;object-fit:contain;border-radius:8px}
  </style>
</head>
<body>
  <div class="container">
    <div class="topbar">
      <h1 style="margin:0">Form Konten Promosi</h1>
      <div class="row">
        <a href="<?= e(route('cms.contents.index')) ?>" class="btn secondary">Kembali</a>
        <a href="<?= e(route('display')) ?>" class="btn muted">Lihat Display</a>
      </div>
    </div>

    <?php if(session('ok')): ?>
      <div class="flash"><?= e(session('ok')) ?></div>
    <?php endif; ?>

    <?php if($errors->any()): ?>
      <div class="errors">
        <div style="font-weight:700;margin-bottom:6px">Periksa kembali input Anda:</div>
        <ul style="margin:0;padding-left:18px">
          <?php foreach($errors->all() as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php $isEdit = $item && $item->exists; ?>
    <div class="card">
      <form method="POST" enctype="multipart/form-data" action="<?= e($isEdit ? route('cms.contents.update', $item) : route('cms.contents.store')) ?>">
        <?= csrf_field() ?>
        <?php if($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

        <div class="field">
          <label for="title">Judul</label>
          <input type="text" id="title" name="title" value="<?= e(old('title', $item->title)) ?>" required placeholder="Judul konten"/>
        </div>

        <div class="grid">
          <div class="field">
            <label for="media_type">Tipe Media</label>
            <?php $selType = old('media_type', $item->media_type ?: 'image'); ?>
            <select id="media_type" name="media_type" required>
              <option value="image" <?= $selType==='image' ? 'selected' : '' ?>>Gambar</option>
              <option value="video" <?= $selType==='video' ? 'selected' : '' ?>>Video</option>
            </select>
          </div>

          <div class="field">
            <label for="display_order">Urutan Tampil</label>
            <input type="number" id="display_order" name="display_order" value="<?= e(old('display_order', $item->display_order ?? 0)) ?>" min="0"/>
          </div>
        </div>

        <div class="field">
          <label for="file">File Media <?= $isEdit ? '(opsional saat edit)' : '(wajib saat tambah)' ?></label>
          <input type="file" id="file" name="file" <?= $isEdit ? '' : 'required' ?> accept="image/*,video/*"/>
          <div class="help">File akan disimpan ke storage/app/public/promos. Pastikan telah menjalankan perintah "php artisan storage:link".</div>
        </div>

        <?php if($isEdit && $item->file_path): ?>
          <div class="field">
            <label>Pratinjau Saat Ini</label>
            <div class="preview">
              <?php if($item->media_type === 'image'): ?>
                <img class="max" src="<?= e(\Illuminate\Support\Facades\Storage::url($item->file_path)) ?>" alt="<?= e($item->title) ?>"/>
              <?php else: ?>
                <video class="max" src="<?= e(\Illuminate\Support\Facades\Storage::url($item->file_path)) ?>" controls muted></video>
              <?php endif; ?>
            </div>
            <div class="help">Mengunggah file baru akan menggantikan media ini.</div>
          </div>
        <?php endif; ?>

        <div class="grid">
          <div class="field">
            <label for="starts_at">Mulai Tayang</label>
            <?php
              $startsVal = old('starts_at', optional($item->starts_at)->format('Y-m-d\TH:i'));
            ?>
            <input type="datetime-local" id="starts_at" name="starts_at" value="<?= e($startsVal) ?>"/>
            <div class="help">Kosongkan untuk langsung tayang.</div>
          </div>
          <div class="field">
            <label for="ends_at">Selesai Tayang</label>
            <?php
              $endsVal = old('ends_at', optional($item->ends_at)->format('Y-m-d\TH:i'));
            ?>
            <input type="datetime-local" id="ends_at" name="ends_at" value="<?= e($endsVal) ?>"/>
            <div class="help">Kosongkan bila tidak ada tanggal selesai.</div>
          </div>
        </div>

        <div class="field" style="display:flex;align-items:center;gap:10px">
          <?php $checked = old('is_active', $item->is_active) ? 'checked' : ''; ?>
          <input type="checkbox" id="is_active" name="is_active" value="1" <?= $checked ?>/>
          <label for="is_active" style="margin:0">Aktif</label>
        </div>

        <div class="row" style="margin-top:10px">
          <button type="submit" class="btn"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan' ?></button>
          <a href="<?= e(route('cms.contents.index')) ?>" class="btn secondary">Batal</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
