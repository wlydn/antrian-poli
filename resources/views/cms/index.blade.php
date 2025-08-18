<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>CMS - Promo Contents</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#0b1020;color:#fff;margin:0}
    .container{max-width:1100px;margin:0 auto;padding:24px}
    .card{background:#121936;border-radius:12px;padding:20px}
    a.btn, button.btn, input.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;border:none;border-radius:8px;padding:10px 14px;font-weight:600;cursor:pointer}
    .btn.secondary{background:#374151}
    .btn.danger{background:#dc2626}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 12px;border-bottom:1px solid #1f2a44;vertical-align:top}
    th{color:#a3b1e1;text-align:left}
    .muted{opacity:.8}
    .row-actions{display:flex;gap:8px;flex-wrap:wrap}
    .flash{background:#065f46;color:#d1fae5;padding:10px 12px;border-radius:8px;margin-bottom:14px}
    .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
    img.thumb{max-height:64px;border-radius:6px}
    code{background:#0b122a;padding:2px 6px;border-radius:6px}
  </style>
</head>
<body>
  <div class="container">
    <div class="topbar">
      <h1 style="margin:0">Konten Promosi</h1>
      <a href="<?= e(route('cms.contents.create')) ?>" class="btn">+ Konten Baru</a>
    </div>

    <?php if(session('ok')): ?>
      <div class="flash"><?= e(session('ok')) ?></div>
    <?php endif; ?>

    <div class="card">
      <?php if($items->count() === 0): ?>
        <div class="muted">Belum ada konten. Klik "Konten Baru" untuk menambahkan.</div>
      <?php else: ?>
        <div class="muted" style="margin-bottom:10px">
          Total: <?= e($items->total()) ?> item
        </div>
        <div style="overflow:auto">
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
              <?php foreach($items as $i => $item): ?>
                <tr>
                  <td><?= e(($items->currentPage()-1)*$items->perPage() + $i + 1) ?></td>
                  <td>
                    <div style="font-weight:700"><?= e($item->title) ?></div>
                    <div class="muted"><code><?= e($item->file_path) ?></code></div>
                  </td>
                  <td><?= e($item->media_type) ?></td>
                  <td>
                    <?php if($item->media_type === 'image'): ?>
                      <img class="thumb" src="<?= e(\Illuminate\Support\Facades\Storage::url($item->file_path)) ?>" alt="<?= e($item->title) ?>"/>
                    <?php else: ?>
                      <div class="muted">Video</div>
                    <?php endif; ?>
                  </td>
                  <td><?= $item->is_active ? 'Ya' : 'Tidak' ?></td>
                  <td><?= e($item->display_order) ?></td>
                  <td>
                    <div>Mulai: <?= e(optional($item->starts_at)->format('Y-m-d H:i') ?? '-') ?></div>
                    <div>Selesai: <?= e(optional($item->ends_at)->format('Y-m-d H:i') ?? '-') ?></div>
                  </td>
                  <td class="row-actions">
                    <a class="btn secondary" href="<?= e(route('cms.contents.edit', $item)) ?>">Edit</a>
                    <form method="POST" action="<?= e(route('cms.contents.destroy', $item)) ?>" onsubmit="return confirm('Hapus konten ini?')">
                      <?= csrf_field() ?> <?= method_field('DELETE') ?>
                      <button type="submit" class="btn danger">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="margin-top:12px">
          <?= $items->links() ?>
        </div>
      <?php endif; ?>
    </div>

    <div style="margin-top:16px">
      <a href="<?= e(route('display')) ?>" class="btn secondary">Lihat Halaman Display</a>
    </div>
  </div>
</body>
</html>
