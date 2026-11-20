<?php
$currentModule='templates'; $pageTitle='قالب سایت'; $breadcrumb='پیشخوان ‹ قالب سایت';
require_once __DIR__ . '/../../../includes/header.php';
requirePermission('templates','view');
$pdo=getPDO();
$site=$pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$activeId=$site['active_template_id'];
$activeId=$site ? (int)$site['active_template_id'] : 0;
$activeTpl=null; if($activeId){ $s=$pdo->prepare("SELECT * FROM templates WHERE id=?"); $s->execute([$activeId]); $activeTpl=$s->fetch(); }
$totalInstalled=(int)$pdo->query("SELECT COUNT(*) FROM templates WHERE is_installed=1")->fetchColumn();
$lastUpdate=$pdo->query("SELECT MAX(installed_at) FROM templates WHERE is_installed=1")->fetchColumn();
$category=$_GET['category']??'all';
$page=max(1,(int)($_GET['page']??1)); $per=9;
$cats=$pdo->query("SELECT c.id, c.slug, c.name, COUNT(t.id) AS n FROM template_categories c LEFT JOIN templates t ON t.category_id=c.id GROUP BY c.id, c.slug, c.name ORDER BY c.sort_order")->fetchAll();
// counts per category (single query)
$catCounts=[]; $totalCatCount=0; foreach($cats as $c){ $catCounts[$c['slug']]=(int)$c['n']; $totalCatCount+=(int)$c['n']; }
$where=''; $params=[];
if($category!=='all'){
  $stmt=$pdo->prepare("SELECT id FROM template_categories WHERE slug=?"); $stmt->execute([$category]); $catRow=$stmt->fetch();
  if($catRow){ $where="WHERE category_id=?"; $params=[$catRow['id']]; }
}
$total=$pdo->prepare("SELECT COUNT(*) FROM templates $where"); $total->execute($params); $totalCount=(int)$total->fetchColumn();
$pages=max(1,(int)ceil($totalCount/$per)); $page=min($page,$pages); $offset=($page-1)*$per;
$per=(int)$per; $offset=(int)$offset;
$stmt=$pdo->prepare("SELECT t.*, tc.name as cat_name FROM templates t LEFT JOIN template_categories tc ON tc.id=t.category_id $where ORDER BY t.id DESC LIMIT $per OFFSET $offset");
$stmt->execute($params); $templates=$stmt->fetchAll();
?>
<?php $canEditTemplates = hasPermission('templates', 'edit'); $isOwnerUser = isOwner(); ?>
<div class="row g-3">
  <div class="col-12<?= $isOwnerUser ? ' col-lg-8' : '' ?>">
    <section class="card h-100" aria-labelledby="tpl-active-title">
      <div class="card-head"><h2 class="card-head__title" id="tpl-active-title">قالب فعال</h2></div>
      <div class="card-pad">
        <p class="template-active__name"><?= $activeTpl ? e($activeTpl['name']) : '—' ?><?php if ($activeTpl): ?> <span class="badge-soft badge-soft-info">نسخه <?= e($activeTpl['version']) ?></span><?php endif; ?></p>
        <div class="kv"><span class="kv__k">تعداد کل قالب‌های نصب‌شده</span><span class="kv__v"><?= toPersianDigits($totalInstalled) ?></span></div>
        <div class="kv"><span class="kv__k">آخرین بروزرسانی</span><span class="kv__v"><?= $lastUpdate ? toPersianDigits(toJalali($lastUpdate)) : '—' ?></span></div>
      </div>
    </section>
  </div>
  <?php if ($isOwnerUser): ?>
  <div class="col-12 col-lg-4">
    <section class="card h-100" aria-labelledby="tpl-upload-title">
      <div class="card-head"><h2 class="card-head__title" id="tpl-upload-title">بارگذاری قالب جدید</h2></div>
      <form id="uploadForm" class="card-pad d-grid gap-3" enctype="multipart/form-data">
        <div class="field">
          <label class="field__label" for="template-zip">فایل قالب (ZIP)</label>
          <input id="template-zip" type="file" name="template_zip" accept=".zip" class="form-control" required>
        </div>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="btn btn-primary"><i class="ti ti-upload" aria-hidden="true"></i>آپلود قالب</button>
      </form>
    </section>
  </div>
  <?php endif; ?>
</div>

<nav aria-label="دسته‌بندی قالب‌ها">
  <ul class="nav nav-pills">
    <li class="nav-item"><a class="nav-link<?= $category === 'all' ? ' active' : '' ?>"<?= $category === 'all' ? ' aria-current="page"' : '' ?> href="?category=all">همه (<?= toPersianDigits($totalCatCount) ?>)</a></li>
    <?php foreach ($cats as $c): if ($c['slug'] === 'all') { continue; } $count = $catCounts[$c['slug']] ?? 0; $isActive = $category === $c['slug']; ?>
    <li class="nav-item"><a class="nav-link<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?> href="?category=<?= e($c['slug']) ?>"><?= e($c['name']) ?> (<?= toPersianDigits($count) ?>)</a></li>
    <?php endforeach; ?>
  </ul>
</nav>

<?php if (!$templates): ?>
<section class="card">
  <div class="empty-state">
    <span class="empty-state__icon"><i class="ti ti-palette" aria-hidden="true"></i></span>
    <p class="empty-state__title">قالبی در این دسته پیدا نشد</p>
  </div>
</section>
<?php else: ?>
<div class="row g-3">
<?php foreach ($templates as $t): $isActiveTpl = ((int) $t['id'] === $activeId); ?>
  <div class="col-12 col-sm-6 col-xl-4">
    <article class="card template-card h-100<?= $isActiveTpl ? ' template-card--active' : '' ?>">
      <div class="template-card__thumb"><?php if ($t['thumbnail_path']): ?><img src="<?= e($t['thumbnail_path']) ?>" alt="" loading="lazy"><?php else: ?><i class="ti ti-photo" aria-hidden="true"></i><?php endif; ?></div>
      <div class="card-pad template-card__body">
        <h3 class="template-card__title"><?= e($t['name']) ?> <span class="template-card__ver"><?= e($t['version']) ?></span></h3>
        <div><span class="badge-soft badge-soft-muted"><?= e($t['cat_name'] ?? '—') ?></span></div>
        <div class="template-card__actions">
          <?php if ($t['preview_url']): ?><a href="<?= e($t['preview_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="ti ti-external-link" aria-hidden="true"></i>پیش‌نمایش</a><?php endif; ?>
          <?php if ($isActiveTpl): ?><span class="badge-soft badge-soft-success"><i class="ti ti-circle-check" aria-hidden="true"></i>قالب فعال</span>
          <?php elseif ($canEditTemplates && $t['is_installed']): ?><button type="button" class="btn btn-sm btn-primary btn-activate" data-id="<?= (int) $t['id'] ?>">فعال‌سازی</button>
          <?php elseif ($canEditTemplates): ?><button type="button" class="btn btn-sm btn-outline-primary btn-install" data-id="<?= (int) $t['id'] ?>">نصب</button><?php endif; ?>
        </div>
      </div>
    </article>
  </div>
<?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
<nav aria-label="صفحه‌بندی"><ul class="pagination mb-0">
<?php for ($i = 1; $i <= $pages; $i++): ?><li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?category=<?= e($category) ?>&amp;page=<?= $i ?>"<?= $i == $page ? ' aria-current="page"' : '' ?>><?= toPersianDigits($i) ?></a></li><?php endfor; ?>
</ul></nav>
<?php endif; ?>
<?php endif; ?>
<script>
async function postAction(url, fd, btn) {
  if (btn) btn.disabled = true;
  try {
    const res = await fetch(url, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN } });
    const j = await res.json();
    await Swal.fire(j.success ? 'موفق' : 'خطا', j.message, j.success ? 'success' : 'error');
    if (j.success) { location.reload(); return; }
  } catch (err) {
    await Swal.fire('خطا', 'خطا در ارتباط با سرور', 'error');
  }
  if (btn) btn.disabled = false;
}
document.querySelectorAll('.btn-activate, .btn-install').forEach(b => b.addEventListener('click', () => {
  const fd = new FormData(); fd.append('id', b.dataset.id); fd.append('csrf_token', window.CSRF_TOKEN);
  postAction(b.classList.contains('btn-activate') ? '/api/templates/activate.php' : '/api/templates/install.php', fd, b);
}));
document.getElementById('uploadForm')?.addEventListener('submit', (e) => {
  e.preventDefault();
  const fd = new FormData(e.target); fd.set('csrf_token', window.CSRF_TOKEN);
  postAction('/api/templates/upload.php', fd, e.target.querySelector('button[type="submit"]'));
});
</script>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
