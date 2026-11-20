<?php
// includes/layout_helpers.php — small pure/near-pure helpers for the shell partials (unit-testable without a database).

if (!function_exists('uiNavVisible')) {
    /** Applies an item's `visible` rule. $can and $owner are injected so tests need no session. */
    function uiNavVisible(array $item, callable $can, bool $owner): bool
    {
        $rule = (string) ($item['visible'] ?? 'always');
        if ($rule === 'always') { return true; }
        if ($rule === 'owner') { return $owner; }
        if (strncmp($rule, 'perm:', 5) === 0) { return (bool) $can(substr($rule, 5), 'view'); }
        return false; // unknown rule: hide (default-deny, like access_map.php)
    }

    function uiNavActive(array $item, string $currentModule, string $scriptName): bool
    {
        if (($item['match'] ?? 'module') === 'path') {
            $url = (string) $item['url'];
            return $url !== '' && substr($scriptName, -strlen($url)) === $url;
        }
        return $currentModule !== '' && $currentModule === $item['key'];
    }

    /** Filters the menu data to what the current user may see; empty groups disappear. */
    function uiNavVisibleGroups(array $groups, callable $can, bool $owner): array
    {
        $out = [];
        foreach ($groups as $group) {
            $items = array_values(array_filter($group['items'], fn($i) => uiNavVisible($i, $can, $owner)));
            if ($items) { $out[] = ['title' => $group['title'], 'items' => $items]; }
        }
        return $out;
    }

    /**
     * Breadcrumb trail for the top bar. $breadcrumb is the existing free-text variable (parts separated by ‹ › / >).
     * Rules: split and trim; append the page title unless the last part already is (or is contained in) the title;
     * fewer than two parts => no trail (it would only repeat the title).
     * @return string[]
     */
    function uiBreadcrumbParts(string $breadcrumb, string $title): array
    {
        $raw = preg_split('/\s*[‹›>\/]\s*/u', $breadcrumb) ?: [];
        $parts = array_values(array_filter(array_map('trim', $raw), fn($p) => $p !== ''));
        if (!$parts) { return []; }
        $last = $parts[count($parts) - 1];
        if ($title !== '' && mb_strpos($title, $last, 0, 'UTF-8') === false) { $parts[] = $title; }
        return count($parts) < 2 ? [] : $parts;
    }
}
