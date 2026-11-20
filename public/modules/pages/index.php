<?php
// Builder page shell (Part 1, entry resolution by Part 8): screen, entry points,
// asset tags, descriptor. The block canvas is NOT rendered here — Part 2 owns
// #builder-canvas. Entry resolution runs before header.php so the module/title
// are right for page canvases and new pages.

$shellBase = __DIR__;
while (!file_exists($shellBase . '/config/database.php') && dirname($shellBase) !== $shellBase) {
    $shellBase = dirname($shellBase);
}
require_once $shellBase . '/includes/page_boot.php';   // login + central guard; safe to require twice
require_once $shellBase . '/includes/builder/bootstrap.php';
require_once $shellBase . '/includes/builder/entry.php';

$pdo = getPDO();
$stmt = $pdo->prepare('SELECT active_template_id FROM site_settings WHERE id = 1');
$stmt->execute();
$settingsRow = $stmt->fetch(PDO::FETCH_ASSOC);
$activeTemplateId = ($settingsRow && $settingsRow['active_template_id'] !== null) ? (int) $settingsRow['active_template_id'] : null;

$canEditPages = hasPermission('pages', 'edit');
$canEditTemplates = hasPermission('templates', 'edit');
$canCreatePage = hasPermission('pages', 'edit');
$canViewList = hasPermission('articles', 'view');
$canUploadMedia = hasPermission('media', 'edit');

// ---- Entry resolution (server side; the client never parses the query) ----
$entry = builderResolveEntry($_GET, $activeTemplateId);

$canvasBuildableType = null;
if ($entry['kind'] === 'canvas') {
    $lookup = $pdo->prepare('SELECT buildable_type FROM builder_canvases WHERE id = ? LIMIT 1');
    $lookup->execute([$entry['canvas_id']]);
    $bt = $lookup->fetchColumn();
    $canvasBuildableType = ($bt === false) ? null : (string) $bt;
}

// ---- Page identity ----
$currentModule = 'pages'; $pageTitle = 'ساخت سایت'; $breadcrumb = 'ساخت سایت';
if ($entry['kind'] === 'page_new') {
    $currentModule = 'articles'; $pageTitle = 'صفحه جدید'; $breadcrumb = 'صفحات ساخته شده';
} elseif ($entry['kind'] === 'canvas' && $canvasBuildableType === 'page') {
    $currentModule = 'articles'; $pageTitle = 'ویرایش صفحه'; $breadcrumb = 'صفحات ساخته شده';
}

$mainClass = 'app-content--flat';
require_once $shellBase . '/includes/header.php';
requirePermission('pages', 'view');

if ($entry['kind'] === 'invalid') {
    ?>
    <section class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><i class="ti ti-link-off" aria-hidden="true"></i></span>
        <p class="empty-state__title">درخواست نامعتبر است</p>
        <p class="empty-state__text">نشانی صفحه‌ساز درست نیست. از منوی «ساخت سایت» دوباره وارد شوید.</p>
        <?php if (hasPermission('articles', 'view')): ?><a class="btn btn-primary" href="/modules/articles/index.php">بازگشت به صفحات ساخته شده</a><?php endif; ?>
      </div>
    </section>
    <?php require_once $shellBase . '/includes/footer.php'; ?>
    <?php return; ?>
<?php }

if ($entry['kind'] === 'none') {
    ?>
    <section class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><i class="ti ti-palette" aria-hidden="true"></i></span>
        <p class="empty-state__title">هنوز قالبی فعال نیست</p>
        <p class="empty-state__text">برای ساخت سایت ابتدا یک قالب را فعال کنید.</p>
        <a href="/modules/templates/index.php" class="btn btn-primary">رفتن به قالب سایت</a>
      </div>
    </section>
    <?php require_once $shellBase . '/includes/footer.php'; ?>
    <?php return; ?>
<?php }

// ---- Valid builder entry: build the load URL and the descriptor ----
$loadParams = [];
$entryType = '';
$entryTemplateId = '';
if ($entry['kind'] === 'canvas') {
    $loadParams = ['canvas_id' => $entry['canvas_id']];
} elseif ($entry['kind'] === 'page_new') {
    $loadParams = ['buildable_type' => 'page', 'new' => '1'];
    $entryType = 'page';
} elseif ($entry['kind'] === 'active_template') {
    $entryType = 'template';
    $entryTemplateId = (string) $entry['template_id'];
    $loadParams = ['buildable_type' => 'template', 'template_id' => $entry['template_id']];
} else {
    // 'template' (with or without new).
    $entryType = 'template';
    $entryTemplateId = (string) $entry['template_id'];
    if (!empty($entry['new'])) {
        $loadParams = ['buildable_type' => 'template', 'new' => '1', 'template_id' => $entry['template_id']];
    } else {
        $loadParams = ['buildable_type' => 'template', 'template_id' => $entry['template_id']];
    }
}
$loadUrl = '/api/builder/load.php?' . http_build_query($loadParams);

// Server-side edit gate. For canvas entries buildable_type is unknown until
// the load returns, so the client re-evaluates then (§11).
$canEdit = $canEditPages;
if ($entryType === 'template') {
    $canEdit = $canEditPages && $canEditTemplates;
}

// ---- Builder markup ----
?>
<div id="builder-root"
  data-engine="blocks"
  data-load-url="<?= e($loadUrl) ?>"
  data-save-url="<?= e('/api/builder/save.php') ?>"
  data-csrf-token="<?= e(csrf_token()) ?>"
  data-can-edit="<?= $canEdit ? '1' : '0' ?>"
  data-login-url="<?= e('/login.php') ?>"
  data-can-edit-templates="<?= $canEditTemplates ? '1' : '0' ?>"
  data-active-template-id="<?= $activeTemplateId !== null ? e((string) $activeTemplateId) : '' ?>"
  data-entry-type="<?= e($entryType) ?>"
  data-entry-template-id="<?= e($entryTemplateId) ?>"
  data-can-create-page="<?= $canCreatePage ? '1' : '0' ?>"
  data-can-view-list="<?= $canViewList ? '1' : '0' ?>"
  data-can-upload-media="<?= $canUploadMedia ? '1' : '0' ?>">
  <div class="row g-3">
    <div class="col-12 col-lg-9">
      <?php if (!$canEdit): ?>
      <div class="alert alert-info d-flex align-items-start gap-2" role="status"><i class="ti ti-lock" aria-hidden="true"></i><span>شما اجازه‌ی ویرایش این بوم را ندارید.</span></div>
      <?php endif; ?>
      <div class="card builder-title-card card-pad mb-3">
        <div class="field">
          <label class="field__label" for="builder-title">عنوان</label>
          <input type="text" id="builder-title" class="form-control" maxlength="255" placeholder="عنوان بوم را بنویسید" value=""<?= $canEdit ? '' : ' disabled' ?>>
          <p class="field__hint" id="builder-title-hint" hidden>این عنوان روی نام سایت هم اعمال می‌شود</p>
        </div>
      </div>
      <div class="card builder-canvas-card card-pad">
        <div id="builder-canvas" class="builder-canvas"></div>
        <div id="builder-canvas-state" class="builder-canvas-state"></div>
      </div>
    </div>
    <div class="col-12 col-lg-3 builder-side">
      <section class="card builder-box-publish mb-3" aria-labelledby="builder-publish-title">
        <div class="card-head"><h2 class="card-head__title" id="builder-publish-title">انتشار</h2></div>
        <div class="card-pad">
          <div class="builder-meta-row"><span class="builder-meta-label">وضعیت</span><span class="d-inline-flex flex-wrap gap-1"><span id="builder-status-badge" class="badge-soft badge-soft-muted"><i class="ti ti-pencil" aria-hidden="true"></i>پیش‌نویس</span> <span id="builder-dirty-badge" class="badge-soft badge-soft-warning" hidden><i class="ti ti-alert-circle" aria-hidden="true"></i>ذخیره نشده</span></span></div>
          <div class="builder-meta-row"><span class="builder-meta-label">آخرین به‌روزرسانی</span><span id="builder-updated">—</span></div>
          <?php if ($canEdit): ?>
          <div class="builder-actions" id="builder-actions">
            <button id="builder-btn-save" type="button" class="btn btn-primary">ذخیره</button>
            <button id="builder-btn-preview" type="button" class="btn btn-outline-secondary">پیش‌نمایش</button>
            <button id="builder-btn-publish" type="button" class="btn btn-success" data-action-status="published">انتشار</button>
          </div>
          <?php endif; ?>
          <?php if ($canCreatePage): ?>
          <a id="builder-btn-new-page" class="btn btn-outline-primary w-100 mt-2" href="/modules/pages/index.php?buildable_type=page&amp;new=1"><i class="ti ti-plus" aria-hidden="true"></i>ساخت صفحه جدید</a>
          <?php endif; ?>
          <?php if ($canViewList): ?>
          <a id="builder-btn-back-list" class="btn btn-link w-100" href="/modules/articles/index.php"><i class="ti ti-arrow-right" aria-hidden="true"></i>بازگشت به صفحات ساخته شده</a>
          <?php endif; ?>
        </div>
      </section>
      <section class="card builder-box-meta" aria-labelledby="builder-meta-title">
        <div class="card-head"><h2 class="card-head__title" id="builder-meta-title">مشخصات بوم</h2></div>
        <div class="card-pad">
          <div class="builder-meta-row"><span class="builder-meta-label">نوع</span><span id="builder-type">—</span></div>
          <div class="builder-meta-row"><span class="builder-meta-label">نشانی</span><span id="builder-slug" class="ltr">—</span></div>
          <div class="builder-meta-row"><span class="builder-meta-label">تعداد بلاک‌ها</span><span id="builder-count">—</span></div>
          <div class="builder-meta-row"><span class="builder-meta-label">شناسه</span><span id="builder-id">—</span></div>
        </div>
      </section>
    </div>
  </div>
</div>
<?php
// Builder styles: alphabetical glob over PUBLIC_PATH/assets/css/builder/*.css.
$builderCssFiles = glob(PUBLIC_PATH . '/assets/css/builder/*.css');
if (is_array($builderCssFiles)) {
    sort($builderCssFiles, SORT_STRING);
    foreach ($builderCssFiles as $cssFile) {
        $cssWeb = '/assets/css/builder/' . basename($cssFile);
        echo '<link rel="stylesheet" href="' . e($cssWeb) . '?v=' . (int) filemtime($cssFile) . '">' . "\n";
    }
}
// Builder scripts in BuilderAssets order, then builder-shell.js last.
foreach (BuilderAssets::clientScripts() as $scriptPath) {
    $scriptFs = PUBLIC_PATH . $scriptPath;
    $version = is_file($scriptFs) ? (int) filemtime($scriptFs) : 0;
    echo '<script src="' . e($scriptPath) . '?v=' . $version . '"></script>' . "\n";
}
$shellFs = PUBLIC_PATH . '/assets/js/builder/builder-shell.js';
echo '<script src="' . e('/assets/js/builder/builder-shell.js') . '?v=' . (int) filemtime($shellFs) . '"></script>' . "\n";
// Builder UI scripts: alphabetical glob over PUBLIC_PATH/assets/js/builder/ui/*.js.
// Later parts drop NN-name.js files here (00-sortable.js, 10-canvas.js,
// 20-settings-panel.js, ...) so they load after window.BuilderShell exists
// without any edit to this screen. An empty or missing folder emits nothing.
$builderUiFiles = glob(PUBLIC_PATH . '/assets/js/builder/ui/*.js');
if (is_array($builderUiFiles)) {
    sort($builderUiFiles, SORT_STRING);
    foreach ($builderUiFiles as $uiFile) {
        $uiWeb = '/assets/js/builder/ui/' . basename($uiFile);
        echo '<script src="' . e($uiWeb) . '?v=' . (int) filemtime($uiFile) . '"></script>' . "\n";
    }
}
?>
<?php require_once $shellBase . '/includes/footer.php'; ?>