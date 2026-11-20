<?php
$currentModule = 'articles';
$pageTitle = 'صفحات ساخته شده';
$breadcrumb = 'صفحات ساخته شده';
require_once __DIR__ . '/../../../includes/header.php';
requirePermission('articles', 'view');
require_once __DIR__ . '/../../../includes/builder/bootstrap.php';
require_once __DIR__ . '/../../../includes/builder/pages_list.php';

$pdo = getPDO();
$filters = BuilderPagesList::normalizeFilters($_GET);
$counts = BuilderPagesList::counts($pdo, $filters['q']);
$totalPages = BuilderPagesList::totalPages($pdo);
$data = BuilderPagesList::fetchPage($pdo, $filters, 20);

$canEditPages = hasPermission('pages', 'edit');
$canViewPages = hasPermission('pages', 'view');
$canDelete = hasPermission('articles', 'delete');

$tabs = [
    ['key' => 'all', 'label' => 'همه', 'count' => $counts['all']],
    ['key' => 'published', 'label' => 'منتشر شده', 'count' => $counts['published']],
    ['key' => 'draft', 'label' => 'پیش‌نویس', 'count' => $counts['draft']],
];
?>
<?php if ($totalPages === 0): ?>
<section class="card">
    <div class="empty-state">
        <span class="empty-state__icon"><i class="ti ti-files" aria-hidden="true"></i></span>
        <p class="empty-state__title">هنوز صفحه‌ای ساخته نشده است</p>
        <p class="empty-state__text">با ساخت اولین صفحه، سایت شما شکل می‌گیرد.</p>
        <?php if ($canEditPages): ?><a class="btn btn-primary" href="/modules/pages/index.php?buildable_type=page&amp;new=1"><i class="ti ti-plus" aria-hidden="true"></i>ساخت اولین صفحه</a><?php endif; ?>
    </div>
</section>
<?php else: ?>
<div class="page-head">
    <p class="page-head__lead">صفحه‌های سایت را جستجو، ویرایش، پیش‌نمایش یا حذف کنید.</p>
    <?php if ($canEditPages): ?>
    <div class="page-head__actions">
        <a class="btn btn-primary" href="/modules/pages/index.php?buildable_type=page&amp;new=1"><i class="ti ti-plus" aria-hidden="true"></i>ساخت صفحه جدید</a>
    </div>
    <?php endif; ?>
</div>
<section class="card table-card" aria-label="فهرست صفحه‌ها">
    <div class="toolbar card-pad">
        <form class="toolbar__grow d-flex gap-2" method="get" action="/modules/articles/index.php" role="search">
            <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
            <label class="input-icon flex-grow-1"><i class="ti ti-search" aria-hidden="true"></i><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="جستجو بر اساس عنوان یا نشانی..." class="form-control" aria-label="جستجو"></label>
            <button type="submit" class="btn btn-outline-secondary">جستجو</button>
        </form>
        <ul class="nav nav-pills">
            <?php foreach ($tabs as $tab): $isActive = $filters['status'] === $tab['key']; ?>
            <li class="nav-item"><a class="nav-link<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?> href="<?= e(BuilderPagesList::url($filters, ['status' => $tab['key']])) ?>"><?= e($tab['label']) ?> (<?= toPersianDigits($tab['count']) ?>)</a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if ($data['total'] === 0): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><i class="ti ti-search" aria-hidden="true"></i></span>
        <p class="empty-state__title">صفحه‌ای با این مشخصات پیدا نشد</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">عنوان</th>
                    <th scope="col">نشانی</th>
                    <th scope="col">وضعیت</th>
                    <th scope="col">بلاک‌ها</th>
                    <th scope="col">آخرین به‌روزرسانی</th>
                    <th scope="col">سازنده</th>
                    <th scope="col">عملیات</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data['rows'] as $row): $id = (int) $row['id']; ?>
                <tr>
                    <td class="cell-title"><a href="/modules/pages/index.php?canvas_id=<?= $id ?>"><?= ($row['title'] !== null && $row['title'] !== '') ? e($row['title']) : '(بدون عنوان)' ?></a></td>
                    <td><span class="cell-slug"><?= ($row['slug'] !== null && $row['slug'] !== '') ? e($row['slug']) : '—' ?></span></td>
                    <td><?php if ($row['status'] === 'published'): ?><span class="badge-soft badge-soft-success"><i class="ti ti-circle-check" aria-hidden="true"></i>منتشر شده</span><?php else: ?><span class="badge-soft badge-soft-muted"><i class="ti ti-pencil" aria-hidden="true"></i>پیش‌نویس</span><?php endif; ?></td>
                    <td><?= toPersianDigits((int) $row['block_count']) ?></td>
                    <td><?= toPersianDigits(toJalaliDateTime($row['updated_at'])) ?></td>
                    <td><?= ($row['creator_name'] !== null && $row['creator_name'] !== '') ? e($row['creator_name']) : '—' ?></td>
                    <td>
                        <div class="cell-actions">
                            <?php if ($canEditPages): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="/modules/pages/index.php?canvas_id=<?= $id ?>" aria-label="ویرایش" data-tip="ویرایش"><i class="ti ti-edit" aria-hidden="true"></i></a>
                            <?php elseif ($canViewPages): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="/modules/pages/index.php?canvas_id=<?= $id ?>" aria-label="مشاهده" data-tip="مشاهده"><i class="ti ti-eye" aria-hidden="true"></i></a>
                            <?php endif; ?>
                            <?php if ($canViewPages): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="/api/builder/render.php?canvas_id=<?= $id ?>" target="_blank" rel="noopener" aria-label="پیش‌نمایش" data-tip="پیش‌نمایش"><i class="ti ti-external-link" aria-hidden="true"></i></a>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-act="delete-page" data-id="<?= $id ?>" data-title="<?= e($row['title']) ?>" aria-label="حذف" data-tip="حذف"><i class="ti ti-trash" aria-hidden="true"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($data['pages'] > 1): ?>
    <?php
    $pages = $data['pages'];
    $page = $data['page'];
    $winStart = max(1, $page - 2);
    $winEnd = min($pages, $page + 2);
    ?>
    <div class="card-foot">
        <nav aria-label="صفحه‌بندی"><ul class="pagination mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(BuilderPagesList::url($filters, ['page' => max(1, $page - 1)])) ?>" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>قبلی</a></li>
            <?php if ($winStart > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= e(BuilderPagesList::url($filters, ['page' => 1])) ?>"><?= toPersianDigits(1) ?></a></li>
            <?php endif; ?>
            <?php for ($i = $winStart; $i <= $winEnd; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(BuilderPagesList::url($filters, ['page' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= toPersianDigits($i) ?></a></li>
            <?php endfor; ?>
            <?php if ($winEnd < $pages): ?>
            <li class="page-item"><a class="page-link" href="<?= e(BuilderPagesList::url($filters, ['page' => $pages])) ?>"><?= toPersianDigits($pages) ?></a></li>
            <?php endif; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(BuilderPagesList::url($filters, ['page' => min($pages, $page + 1)])) ?>" <?= $page >= $pages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>بعدی</a></li>
        </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<script src="/assets/js/pages-manager.js?v=<?= (int) filemtime(PUBLIC_PATH . '/assets/js/pages-manager.js') ?>"></script>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
