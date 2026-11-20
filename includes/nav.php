<?php
// includes/nav.php — the sidebar menu as DATA. Adding a menu item later = one array row.
// visible: 'always' | 'owner' | 'perm:<module_key>' (hasPermission(key,'view')).
// match:   'module' (item key equals $currentModule) | 'path' (the request script path ends with url).
return [
    [
        'title' => 'مدیریت',
        'items' => [
            ['key' => 'dashboard', 'label' => 'پیشخوان',          'icon' => 'ti-layout-dashboard', 'url' => '/modules/dashboard/index.php', 'visible' => 'perm:dashboard', 'match' => 'module'],
            ['key' => 'pages',     'label' => 'ساخت سایت',        'icon' => 'ti-layout-grid',      'url' => '/modules/pages/index.php',     'visible' => 'perm:pages',     'match' => 'module'],
            ['key' => 'articles',  'label' => 'صفحات ساخته شده',  'icon' => 'ti-files',            'url' => '/modules/articles/index.php',  'visible' => 'perm:articles',  'match' => 'module'],
            ['key' => 'templates', 'label' => 'قالب سایت',        'icon' => 'ti-palette',          'url' => '/modules/templates/index.php', 'visible' => 'perm:templates', 'match' => 'module'],
            ['key' => 'media',     'label' => 'رسانه و عکس‌ها',   'icon' => 'ti-photo',            'url' => '/modules/media/index.php',     'visible' => 'perm:media',     'match' => 'module'],
            ['key' => 'licenses',  'label' => 'مجوزات',           'icon' => 'ti-certificate',      'url' => '/modules/licenses/index.php',  'visible' => 'perm:licenses',  'match' => 'module'],
        ],
    ],
    [
        'title' => 'تنظیمات پنل',
        'items' => [
            ['key' => 'profile',  'label' => 'پروفایل من',          'icon' => 'ti-user-circle',            'url' => '/modules/settings/profile.php', 'visible' => 'always',          'match' => 'path'],
            ['key' => 'general',  'label' => 'تنظیمات عمومی',       'icon' => 'ti-adjustments-horizontal', 'url' => '/modules/settings/general.php', 'visible' => 'perm:settings',   'match' => 'path'],
            ['key' => 'staff',    'label' => 'کاربران و دسترسی‌ها', 'icon' => 'ti-users',                  'url' => '/modules/settings/staff.php',   'visible' => 'owner',           'match' => 'path'],
        ],
    ],
];
