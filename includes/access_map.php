<?php
return [
    '/modules/dashboard/index.php'  => ['module' => 'dashboard', 'action' => 'view'],
    '/modules/pages/index.php'      => ['module' => 'pages',     'action' => 'view'],
    '/modules/articles/index.php'   => ['module' => 'articles',  'action' => 'view'],
    '/modules/templates/index.php'  => ['module' => 'templates', 'action' => 'view'],
    '/modules/media/index.php'      => ['module' => 'media',     'action' => 'view'],
    '/modules/licenses/index.php'   => ['module' => 'licenses',  'action' => 'view'],
    '/modules/settings/general.php' => ['module' => 'settings',  'action' => 'view'],
    '/modules/settings/profile.php' => ['login_only' => true],
    '/modules/settings/staff.php'   => ['owner_only' => true],
];
