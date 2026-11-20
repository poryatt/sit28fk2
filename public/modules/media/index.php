<?php
$currentModule='media'; $pageTitle='رسانه و عکس‌ها'; $breadcrumb='پیشخوان ‹ رسانه';
require_once __DIR__ . '/../../../includes/header.php';
requirePermission('media','view');
require_once __DIR__ . '/../../../includes/image_support.php';
$canEditMedia=hasPermission('media','edit');
$imageMode=imageProcessingMode();
$imagePolicy=imageFallbackPolicyCurrent();
$pdo=getPDO();
$filter=$_GET['type']??'all'; $search=trim($_GET['q']??'');
$page=max(1,(int)($_GET['page']??1)); $per=20; $offset=($page-1)*$per;
$where=["purpose='library'"]; $params=[];
if($filter==='image'){ $where[]="file_type='image'"; }
if($filter==='video'){ $where[]="file_type='video'"; }
if($search!==''){ $like='%'.str_replace(['|','%','_'],['||','|%','|_'],$search).'%'; $where[]="file_name LIKE ? ESCAPE '|'"; $params[]=$like; }
$whereSql=$where? 'WHERE '.implode(' AND ',$where):'';
$total=$pdo->prepare("SELECT COUNT(*) FROM media_library $whereSql"); $total->execute($params); $totalCount=(int)$total->fetchColumn();
$pages=max(1,(int)ceil($totalCount/$per)); $page=min(max(1,$page),$pages); $offset=($page-1)*$per;
$stmt=$pdo->prepare("SELECT * FROM media_library $whereSql ORDER BY created_at DESC LIMIT $per OFFSET $offset");
$stmt->execute($params); $rows=$stmt->fetchAll();
?>
<?php
$canDeleteMedia = hasPermission('media', 'delete');
$qs = static function (array $over) use ($filter, $search): string {
    return '?' . http_build_query(array_merge(['type' => $filter, 'q' => $search], $over));
};
// Only what the preview dialog needs goes to the browser (not the whole row: hashes, uploader ids, internal paths).
$jsRows = array_map(static function (array $r): array {
    return [
        'id' => (int) $r['id'],
        'file_type' => (string) $r['file_type'],
        'file_path' => (string) $r['file_path'],
        'file_name' => (string) $r['file_name'],
        'file_size_kb' => (int) $r['file_size_kb'],
        'width' => $r['width'] ?? null,
        'height' => $r['height'] ?? null,
        'created_at_fa' => toPersianDigits(toJalaliDateTime($r['created_at'])),
    ];
}, $rows);
?>
<div class="page-head">
  <p class="page-head__lead"><?= $canEditMedia ? 'تصویرها و ویدیوهای سایت را آپلود، جستجو و مدیریت کنید.' : 'تصویرها و ویدیوهای سایت را جستجو و مشاهده کنید.' ?></p>
</div>
<?php if ($canEditMedia && $imageMode === 'none'): ?>
<?php if ($imagePolicy === 'passthrough'): ?>
<div class="alert alert-warning d-flex align-items-start gap-2" role="status"><i class="ti ti-alert-triangle" aria-hidden="true"></i><span>افزونه GD در این سرور فعال نیست؛ تصاویر بدون پردازش مجدد ذخیره می‌شوند. برای کیفیت و امنیت بهتر، GD را فعال کنید.</span></div>
<?php else: ?>
<div class="alert alert-danger d-flex align-items-start gap-2" role="alert"><i class="ti ti-alert-circle" aria-hidden="true"></i><span>افزونه GD در این سرور فعال نیست و آپلود تصویر امکان‌پذیر نیست.</span></div>
<?php endif; ?>
<?php endif; ?>
<?php if ($canEditMedia): ?>
<section class="card card-pad" aria-label="آپلود فایل">
  <div class="drop-zone" id="mediaDrop" role="button" tabindex="0"><i class="ti ti-cloud-upload" aria-hidden="true"></i><span>برای آپلود چند فایل کلیک کنید یا فایل‌ها را اینجا بکشید</span><input type="file" multiple accept="image/*,video/*" class="d-none" tabindex="-1" aria-label="انتخاب فایل برای آپلود"></div>
  <div id="progressArea" class="upload-list" aria-live="polite"></div>
</section>
<?php endif; ?>
<div class="toolbar">
  <ul class="nav nav-pills">
    <li class="nav-item"><a class="nav-link<?= $filter === 'all' ? ' active' : '' ?>"<?= $filter === 'all' ? ' aria-current="page"' : '' ?> href="<?= e($qs(['type' => 'all'])) ?>">همه</a></li>
    <li class="nav-item"><a class="nav-link<?= $filter === 'image' ? ' active' : '' ?>"<?= $filter === 'image' ? ' aria-current="page"' : '' ?> href="<?= e($qs(['type' => 'image'])) ?>">فقط تصاویر</a></li>
    <li class="nav-item"><a class="nav-link<?= $filter === 'video' ? ' active' : '' ?>"<?= $filter === 'video' ? ' aria-current="page"' : '' ?> href="<?= e($qs(['type' => 'video'])) ?>">فقط ویدیوها</a></li>
  </ul>
  <form class="toolbar__end toolbar__grow d-flex gap-2" method="get" role="search">
    <input type="hidden" name="type" value="<?= e($filter) ?>">
    <label class="input-icon flex-grow-1"><i class="ti ti-search" aria-hidden="true"></i><input type="search" name="q" value="<?= e($search) ?>" placeholder="جستجو بر اساس نام فایل..." class="form-control" aria-label="جستجو"></label>
    <button type="submit" class="btn btn-outline-secondary">جستجو</button>
  </form>
</div>
<?php if (empty($rows)): ?>
<section class="card">
  <div class="empty-state">
    <span class="empty-state__icon"><i class="ti ti-photo" aria-hidden="true"></i></span>
    <p class="empty-state__title">فایلی یافت نشد</p>
  </div>
</section>
<?php else: ?>
<ul class="media-grid">
<?php foreach ($rows as $r): ?>
  <li><button type="button" class="media-tile" data-media-id="<?= (int) $r['id'] ?>" aria-haspopup="dialog">
    <span class="media-tile__thumb"><?php if ($r['file_type'] === 'image'): ?><img src="<?= e($r['file_path']) ?>" alt="" loading="lazy"><?php else: ?><i class="ti ti-player-play" aria-hidden="true"></i><?php endif; ?></span>
    <span class="media-tile__name"><?= e($r['file_name']) ?></span>
    <span class="media-tile__meta"><?= toPersianDigits($r['file_size_kb']) ?> کیلوبایت</span>
  </button></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php if ($pages > 1): ?>
<?php $winStart = max(1, $page - 2); $winEnd = min($pages, $page + 2); ?>
<nav aria-label="صفحه‌بندی"><ul class="pagination mb-0">
  <?php if ($winStart > 1): ?><li class="page-item"><a class="page-link" href="<?= e($qs(['page' => 1])) ?>"><?= toPersianDigits(1) ?></a></li><?php endif; ?>
  <?php for ($i = $winStart; $i <= $winEnd; $i++): ?><li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($qs(['page' => $i])) ?>"<?= $i == $page ? ' aria-current="page"' : '' ?>><?= toPersianDigits($i) ?></a></li><?php endfor; ?>
  <?php if ($winEnd < $pages): ?><li class="page-item"><a class="page-link" href="<?= e($qs(['page' => $pages])) ?>"><?= toPersianDigits($pages) ?></a></li><?php endif; ?>
</ul></nav>
<?php endif; ?>

<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title fs-5" id="previewTitle">پیش‌نمایش</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button></div>
  <div class="modal-body" id="previewBody"></div>
  <?php if ($canDeleteMedia): ?><div class="modal-footer"><button type="button" class="btn btn-danger" id="btnDeleteMedia"><i class="ti ti-trash" aria-hidden="true"></i>حذف</button></div><?php endif; ?>
</div></div></div>

<script>
const MEDIA = <?= json_encode($jsRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const fa = (v) => (window.PanelUI ? window.PanelUI.toPersianDigits(v) : String(v));
let currentMediaId = null;
let lastTile = null;

function kvRow(label, value) {
  const row = document.createElement('div'); row.className = 'kv';
  const k = document.createElement('span'); k.className = 'kv__k'; k.textContent = label;
  const v = document.createElement('span'); v.className = 'kv__v'; v.textContent = value;
  row.append(k, v);
  return row;
}
function openPreview(id, tile) {
  currentMediaId = id; lastTile = tile || null;
  const row = MEDIA.find(x => x.id === id);
  const body = document.getElementById('previewBody'); body.replaceChildren();
  if (!row) return;
  const el = document.createElement(row.file_type === 'image' ? 'img' : 'video');
  el.className = 'media-preview__el'; el.src = row.file_path;
  if (row.file_type === 'image') { el.alt = row.file_name; } else { el.controls = true; }
  body.appendChild(el);
  const info = document.createElement('div'); info.className = 'mt-3';
  info.append(
    kvRow('نام', row.file_name),
    kvRow('حجم', fa(row.file_size_kb) + ' کیلوبایت'),
    kvRow('ابعاد', (row.width ? fa(row.width) : '-') + ' × ' + (row.height ? fa(row.height) : '-')),
    kvRow('تاریخ', row.created_at_fa)
  );
  body.appendChild(info);
  bootstrap.Modal.getOrCreateInstance(document.getElementById('previewModal')).show();
}
document.querySelectorAll('.media-tile').forEach(t => t.addEventListener('click', () => openPreview(Number(t.dataset.mediaId), t)));
document.getElementById('previewModal').addEventListener('hidden.bs.modal', () => {
  document.getElementById('previewBody').replaceChildren();   // stops a playing video
  if (lastTile) lastTile.focus();
});

const drop = document.getElementById('mediaDrop');
if (drop) {
  const input = drop.querySelector('input'); const prog = document.getElementById('progressArea');
  drop.addEventListener('click', () => input.click());
  drop.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); input.click(); } });
  ['dragover', 'dragenter'].forEach(n => drop.addEventListener(n, ev => { ev.preventDefault(); drop.classList.add('dragover'); }));
  ['dragleave', 'drop'].forEach(n => drop.addEventListener(n, () => drop.classList.remove('dragover')));
  drop.addEventListener('drop', ev => { ev.preventDefault(); uploadFiles(ev.dataTransfer.files); });
  input.addEventListener('change', () => uploadFiles(input.files));
  function uploadFiles(files) {
    [...files].forEach(file => {
      const fd = new FormData(); fd.append('file', file); fd.append('csrf_token', window.CSRF_TOKEN);
      const xhr = new XMLHttpRequest(); xhr.open('POST', '/api/media/upload.php', true); xhr.setRequestHeader('X-CSRF-TOKEN', window.CSRF_TOKEN);
      const row = document.createElement('div'); row.className = 'upload-row';
      const name = document.createElement('span'); name.className = 'upload-row__name'; name.textContent = file.name;
      const bar = document.createElement('progress'); bar.max = 100; bar.value = 0; bar.setAttribute('aria-label', file.name);
      const pct = document.createElement('span'); pct.className = 'upload-row__pct'; pct.textContent = fa(0) + '٪';
      row.append(name, bar, pct); prog.appendChild(row);
      xhr.upload.onprogress = e => { if (e.lengthComputable) { const p = Math.round(e.loaded / e.total * 100); bar.value = p; pct.textContent = fa(p) + '٪'; } };
      xhr.onload = () => {
        row.remove();
        if (xhr.status === 200) {
          try {
            const r = JSON.parse(xhr.responseText);
            if (r.success && r.duplicate) { Swal.fire('توجه', r.message, 'info').then(() => location.reload()); }
            else if (r.success) location.reload();
            else Swal.fire('خطا', r.message, 'error');
          } catch (err) { Swal.fire('خطا', 'خطا در آپلود فایل', 'error'); }
        } else { Swal.fire('خطا', 'خطا در آپلود فایل', 'error'); }
      };
      xhr.onerror = () => { row.remove(); Swal.fire('خطا', 'خطا در ارتباط با سرور', 'error'); };
      xhr.send(fd);
    });
  }
}

document.getElementById('btnDeleteMedia')?.addEventListener('click', async () => {
  if (!currentMediaId) return;
  const ok = await confirmDelete();
  if (!ok.isConfirmed) return;
  const fd = new FormData(); fd.append('id', currentMediaId); fd.append('csrf_token', window.CSRF_TOKEN);
  try {
    const res = await fetch('/api/media/delete.php', { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN } });
    const j = await res.json();
    Swal.fire(j.success ? 'حذف شد' : 'خطا', j.message, j.success ? 'success' : 'error').then(() => j.success && location.reload());
  } catch (err) { Swal.fire('خطا', 'خطا در ارتباط با سرور', 'error'); }
});
</script>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
