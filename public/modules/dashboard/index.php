<?php
// Dashboard (Part 10B). Data comes from existing tables plus the two 10C providers; this file only arranges it.
$currentModule='dashboard'; $pageTitle='پیشخوان'; $breadcrumb='پیشخوان';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/builder/pages_list.php';
require_once __DIR__ . '/../../../includes/dashboard_data.php';
requirePermission('dashboard','view');
$pdo=getPDO();

$counts=BuilderPagesList::counts($pdo,'');
$pagesCount=(int)$counts['all'];
$publishedCount=(int)$counts['published'];
$site=$pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$tmpl=null;
if($site && $site['active_template_id']){
  $s=$pdo->prepare("SELECT name, version FROM templates WHERE id=?"); $s->execute([$site['active_template_id']]); $tmpl=$s->fetch();
}
// daily snapshot (lazy). INSERT IGNORE + UNIQUE(snapshot_date) = no race between two simultaneous first visits.
$weekAgo=null;
try{
  $pdo->prepare("INSERT IGNORE INTO dashboard_snapshots (snapshot_date, pages_count, articles_count) VALUES (CURDATE(), ?, 0)")->execute([$pagesCount]);
  // the newest snapshot that is at least 7 days old (exact "today-7" row may be missing on a quiet week)
  $stmt=$pdo->prepare("SELECT pages_count FROM dashboard_snapshots WHERE snapshot_date <= CURDATE() - INTERVAL 7 DAY ORDER BY snapshot_date DESC LIMIT 1");
  $stmt->execute(); $weekAgo=$stmt->fetch();
}catch(Throwable $e){ error_log('dashboard snapshot: '.$e->getMessage()); }
function trend($current,$prev){
  if(!$prev) return 'بدون داده هفته قبل';
  $diff=$current - $prev;
  if($diff>0) return '+' . toPersianDigits($diff) . ' عدد نسبت به هفته گذشته';
  if($diff<0) return toPersianDigits($diff) . ' عدد نسبت به هفته گذشته';
  return 'بدون تغییر نسبت به هفته گذشته';
}
$jalaliToday=toJalali(date('Y-m-d'));
$userName=e($user['full_name']);
// وضعیت سیستم
$health=true; $diskFree=@disk_free_space(PUBLIC_PATH); if($diskFree!==false && $diskFree < 100*1024*1024) $health=false;
try{ $pdo->query("SELECT 1"); }catch(Throwable $e){ $health=false; }

$latestPages=BuilderPagesList::latest($pdo,6);
$isOwner=isOwner();
$activity=$isOwner ? dashboardRecentActivity($pdo,6) : [];
$canCreatePage=hasPermission('pages','edit');
$canUploadMedia=hasPermission('media','edit');
$canViewList=hasPermission('articles','view');
$canViewPages=hasPermission('pages','view');
?>
<div class="page-head">
  <p class="page-head__lead">سلام <?= $userName ?> عزیز، خوش آمدید. امروز <?= toPersianDigits($jalaliToday) ?></p>
  <?php if($canCreatePage || $canUploadMedia): ?>
  <div class="page-head__actions">
    <?php if($canCreatePage): ?><a class="btn btn-primary" href="/modules/pages/index.php?buildable_type=page&amp;new=1"><i class="ti ti-plus" aria-hidden="true"></i>ساخت صفحه جدید</a><?php endif; ?>
    <?php if($canUploadMedia): ?><a class="btn btn-outline-secondary" href="/modules/media/index.php"><i class="ti ti-upload" aria-hidden="true"></i>آپلود رسانه</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="row g-3 stagger" id="stats">
  <div class="col-12 col-sm-6 col-xl-3"><div class="card stat hover-lift"><span class="stat__icon"><i class="ti ti-files" aria-hidden="true"></i></span><div class="stat__body"><p class="stat__label">صفحات ساخته شده</p><p class="stat__value" data-countup="<?= (int)$pagesCount ?>"><?= toPersianDigits($pagesCount) ?></p><p class="stat__hint"><?= e(trend($pagesCount,$weekAgo['pages_count']??null)) ?></p></div></div></div>
  <div class="col-12 col-sm-6 col-xl-3"><div class="card stat stat--success hover-lift"><span class="stat__icon"><i class="ti ti-circle-check" aria-hidden="true"></i></span><div class="stat__body"><p class="stat__label">صفحات منتشرشده</p><p class="stat__value" data-countup="<?= (int)$publishedCount ?>"><?= toPersianDigits($publishedCount) ?></p><p class="stat__hint">از <?= toPersianDigits($pagesCount) ?> صفحه</p></div></div></div>
  <div class="col-12 col-sm-6 col-xl-3"><div class="card stat stat--info hover-lift"><span class="stat__icon"><i class="ti ti-palette" aria-hidden="true"></i></span><div class="stat__body"><p class="stat__label">قالب فعال</p><p class="stat__value"><?= e($tmpl['name']??'—') ?></p><?php if(!empty($tmpl['version'])): ?><p class="stat__hint">نسخه <?= e($tmpl['version']) ?></p><?php endif; ?></div></div></div>
  <div class="col-12 col-sm-6 col-xl-3"><div class="card stat <?= $health?'stat--success':'stat--warning' ?> hover-lift"><span class="stat__icon"><i class="ti <?= $health?'ti-heart-rate-monitor':'ti-alert-triangle' ?>" aria-hidden="true"></i></span><div class="stat__body"><p class="stat__label">وضعیت سیستم</p><p class="stat__value"><?= $health? 'عالی' : 'نیاز به بررسی' ?></p><p class="stat__hint"><?= $health?'همه چیز مرتب است':'فضای دیسک یا اتصال را بررسی کنید' ?></p></div></div></div>
</div>

<div class="row g-3">
  <div class="col-12 <?= $isOwner ? 'col-xl-8' : '' ?>">
    <section class="card table-card" aria-labelledby="latest-title">
      <div class="card-head"><h2 class="card-head__title" id="latest-title">آخرین صفحه‌ها</h2><?php if($canViewList): ?><a href="/modules/articles/index.php">مشاهده همه</a><?php endif; ?></div>
      <?php if(!$latestPages): ?>
      <div class="empty-state">
        <span class="empty-state__icon"><i class="ti ti-files" aria-hidden="true"></i></span>
        <p class="empty-state__title">هنوز صفحه‌ای نساخته‌اید</p>
        <p class="empty-state__text">با ساخت اولین صفحه، اینجا فهرست آخرین صفحه‌های شما نمایش داده می‌شود.</p>
        <?php if($canCreatePage): ?><a class="btn btn-primary" href="/modules/pages/index.php?buildable_type=page&amp;new=1"><i class="ti ti-plus" aria-hidden="true"></i>ساخت اولین صفحه</a><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th scope="col">عنوان</th><th scope="col">نشانی</th><th scope="col">وضعیت</th><th scope="col">آخرین به‌روزرسانی</th></tr></thead>
          <tbody>
          <?php foreach($latestPages as $row): $id=(int)$row['id']; ?>
            <tr>
              <td class="cell-title"><?php if($canViewPages): ?><a href="/modules/pages/index.php?canvas_id=<?= $id ?>"><?= ($row['title']!==null && $row['title']!=='') ? e($row['title']) : '(بدون عنوان)' ?></a><?php else: ?><?= ($row['title']!==null && $row['title']!=='') ? e($row['title']) : '(بدون عنوان)' ?><?php endif; ?></td>
              <td><span class="cell-slug"><?= ($row['slug']!==null && $row['slug']!=='') ? e($row['slug']) : '—' ?></span></td>
              <td><?php if($row['status']==='published'): ?><span class="badge-soft badge-soft-success"><i class="ti ti-circle-check" aria-hidden="true"></i>منتشر شده</span><?php else: ?><span class="badge-soft badge-soft-muted"><i class="ti ti-pencil" aria-hidden="true"></i>پیش‌نویس</span><?php endif; ?></td>
              <td><?= toPersianDigits(toJalali($row['updated_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </div>
  <?php if($isOwner): ?>
  <div class="col-12 col-xl-4">
    <section class="card" aria-labelledby="activity-title">
      <div class="card-head"><h2 class="card-head__title" id="activity-title">فعالیت‌های اخیر</h2></div>
      <?php if(!$activity): ?>
      <div class="empty-state">
        <span class="empty-state__icon"><i class="ti ti-history" aria-hidden="true"></i></span>
        <p class="empty-state__title">هنوز فعالیتی ثبت نشده است</p>
      </div>
      <?php else: ?>
      <ul class="activity-list">
        <?php foreach($activity as $a): ?>
        <li>
          <span class="activity-list__text"><?= e($a['label']) ?></span>
          <span class="activity-list__meta"><?= e($a['full_name']) ?> · <?= toPersianDigits(toJalali(substr((string)$a['created_at'],0,10))) ?> <bdi><?= toPersianDigits(substr((string)$a['created_at'],11,5)) ?></bdi></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
