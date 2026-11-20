<?php
// Site renderer / compiler (Part 7).
// Turns a saved block tree into live page HTML. Standalone: required directly
// by public/api/builder/render.php, never via bootstrap (no edit to Part 0 files).
// Output is XSS-safe by construction: text html is re-cleaned, everything else
// is htmlspecialchars-escaped, media URLs come only from media_library.

final class BuilderRenderer
{
    private const TEXT_TAGS = ['b', 'strong', 'i', 'em', 'u', 'a', 'br', 'p', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote'];

    /** @param array $tree nested tree from BuilderCanvasRepository::load() */
    public static function renderPage(PDO $pdo, array $canvas, array $tree): string
    {
        $media = self::mediaMap($pdo, $tree);
        $title = isset($canvas['title']) ? (string) $canvas['title'] : '';
        $body = '';
        foreach ($tree as $node) {
            if (!is_array($node) || !isset($node['type'])) {
                continue;
            }
            $type = (string) $node['type'];
            if ($type === 'header') {
                $body .= '<header class="b-header">' . self::renderChildren($node, $media) . '</header>';
            } elseif ($type === 'footer') {
                $body .= '<footer class="b-footer">' . self::renderChildren($node, $media) . '</footer>';
            } else {
                $body .= '<main class="b-main">' . self::renderChildren($node, $media) . '</main>';
            }
        }
        $css = self::css();
        $js = self::js();
        return '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . self::e($title) . '</title>'
            . '<style>' . $css . '</style></head>'
            . '<body><div class="b-page">' . $body . '</div>'
            . '<script>' . $js . '</script></body></html>';
    }

    /** @return array<int, array{path:string,type:string}> */
    private static function mediaMap(PDO $pdo, array $tree): array
    {
        $ids = [];
        $walk = function (array $nodes) use (&$walk, &$ids): void {
            foreach ($nodes as $n) {
                if (!is_array($n)) {
                    continue;
                }
                $data = $n['data'] ?? null;
                if (is_array($data)) {
                    foreach (['media_id', 'cover_media_id'] as $k) {
                        if (isset($data[$k]) && is_int($data[$k]) && $data[$k] > 0) {
                            $ids[$data[$k]] = $data[$k];
                        }
                    }
                    foreach (['slides', 'items', 'circles'] as $listKey) {
                        if (isset($data[$listKey]) && is_array($data[$listKey])) {
                            foreach ($data[$listKey] as $entry) {
                                $arr = is_array($entry) ? $entry : (array) $entry;
                                foreach (['media_id', 'cover_media_id'] as $k) {
                                    if (isset($arr[$k]) && is_int($arr[$k]) && $arr[$k] > 0) {
                                        $ids[$arr[$k]] = $arr[$k];
                                    }
                                }
                                if (isset($arr['items']) && is_array($arr['items'])) {
                                    foreach ($arr['items'] as $it) {
                                        $a = is_array($it) ? $it : (array) $it;
                                        if (isset($a['media_id']) && is_int($a['media_id']) && $a['media_id'] > 0) {
                                            $ids[$a['media_id']] = $a['media_id'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                } elseif ($data instanceof stdClass) {
                    $arr = (array) $data;
                    foreach (['media_id', 'cover_media_id'] as $k) {
                        if (isset($arr[$k]) && is_int($arr[$k]) && $arr[$k] > 0) {
                            $ids[$arr[$k]] = $arr[$k];
                        }
                    }
                    foreach (['slides', 'items', 'circles'] as $listKey) {
                        if (isset($arr[$listKey]) && is_array($arr[$listKey])) {
                            foreach ($arr[$listKey] as $entry) {
                                $a = is_array($entry) ? $entry : (array) $entry;
                                foreach (['media_id', 'cover_media_id'] as $k) {
                                    if (isset($a[$k]) && is_int($a[$k]) && $a[$k] > 0) {
                                        $ids[$a[$k]] = $a[$k];
                                    }
                                }
                                if (isset($a['items']) && is_array($a['items'])) {
                                    foreach ($a['items'] as $it) {
                                        $b = is_array($it) ? $it : (array) $it;
                                        if (isset($b['media_id']) && is_int($b['media_id']) && $b['media_id'] > 0) {
                                            $ids[$b['media_id']] = $b['media_id'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if (isset($n['children']) && is_array($n['children'])) {
                    $walk($n['children']);
                }
            }
        };
        $walk($tree);
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('SELECT id, file_path, file_type FROM media_library WHERE id IN (' . $in . ')');
        $stmt->execute(array_values($ids));
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['id']] = ['path' => (string) $row['file_path'], 'type' => (string) $row['file_type']];
        }
        return $map;
    }

    private static function d($data): array
    {
        if ($data instanceof stdClass) {
            return (array) $data;
        }
        return is_array($data) ? $data : [];
    }

    private static function renderChildren(array $node, array $media): string
    {
        $out = '';
        $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
        foreach ($children as $child) {
            $out .= self::renderNode(is_array($child) ? $child : (array) $child, $media);
        }
        return $out;
    }

    /** @param array{type:string,data:mixed,children:array} $node */
    private static function renderNode(array $node, array $media): string
    {
        $type = isset($node['type']) ? (string) $node['type'] : '';
        $data = self::d($node['data'] ?? []);
        switch ($type) {
            case 'column_container':
                return '<div class="b-cols">' . self::renderChildren($node, $media) . '</div>';
            case 'column':
                return '<div class="b-col">' . self::renderChildren($node, $media) . '</div>';
            case 'text':
                return self::renderText($data);
            case 'image_video':
                return self::renderImageVideo($data, $media);
            case 'button':
                return self::renderButton($data, $media);
            case 'slider':
                return self::renderSlider($data, $media);
            case 'story':
                return self::renderStory($data, $media);
            case 'header':
            case 'body':
            case 'footer':
                return self::renderChildren($node, $media);
            default:
                return '';
        }
    }

    private static function renderText(array $data): string
    {
        $raw = isset($data['html']) && is_string($data['html']) ? $data['html'] : '';
        $html = BuilderHtmlSanitizer::clean($raw, self::TEXT_TAGS, 20000);
        $style = '';
        $align = isset($data['align']) ? (string) $data['align'] : 'right';
        if (!in_array($align, ['right', 'center', 'left', 'justify'], true)) {
            $align = 'right';
        }
        $style .= 'text-align:' . $align . ';';
        if (isset($data['color']) && is_string($data['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
            $style .= 'color:' . $data['color'] . ';';
        }
        if (isset($data['bg']) && is_string($data['bg']) && preg_match('/^#[0-9a-fA-F]{6}$/', $data['bg'])) {
            $style .= 'background-color:' . $data['bg'] . ';';
        }
        return '<div class="b-text" style="' . self::e($style) . '">' . ($html === '' ? '' : $html) . '</div>';
    }

    private static function mediaUrl(array $media, $id): ?string
    {
        $id = (int) $id;
        if ($id <= 0 || !isset($media[$id])) {
            return null;
        }
        return $media[$id]['path'];
    }

    private static function renderImageVideo(array $data, array $media): string
    {
        $url = isset($data['media_id']) ? self::mediaUrl($media, $data['media_id']) : null;
        if ($url === null) {
            return '';
        }
        $kind = isset($data['kind']) && $data['kind'] === 'video' ? 'video' : 'image';
        $caption = isset($data['caption']) ? (string) $data['caption'] : '';
        $alt = isset($data['alt']) ? (string) $data['alt'] : '';
        $out = '<figure class="b-media">';
        if ($kind === 'video') {
            $attrs = ' controls playsinline';
            if (!empty($data['autoplay'])) {
                $attrs .= ' autoplay muted';
            } elseif (!empty($data['muted'])) {
                $attrs .= ' muted';
            }
            if (!empty($data['loop'])) {
                $attrs .= ' loop';
            }
            $out .= '<video class="b-media-el" src="' . self::e($url) . '"' . $attrs . '></video>';
        } else {
            $out .= '<img class="b-media-el" src="' . self::e($url) . '" alt="' . self::e($alt) . '" loading="lazy">';
        }
        if ($caption !== '') {
            $out .= '<figcaption>' . self::e($caption) . '</figcaption>';
        }
        return $out . '</figure>';
    }

    private static function renderButton(array $data, array $media): string
    {
        $label = isset($data['label']) ? (string) $data['label'] : 'دکمه';
        $url = isset($data['url']) ? (string) $data['url'] : '';
        $safe = $url === '' ? '#' : (BuilderHtmlSanitizer::safeUrl($url) ?? '#');
        $target = !empty($data['open_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
        $img = '';
        if (isset($data['media_id']) && (int) $data['media_id'] > 0) {
            $u = self::mediaUrl($media, $data['media_id']);
            if ($u !== null) {
                $img = '<img class="b-btn-img" src="' . self::e($u) . '" alt="' . self::e($label) . '">';
            }
        }
        return '<div class="b-btn-wrap"><a class="b-btn" href="' . self::e($safe) . '"' . $target . '>' . $img . self::e($label) . '</a></div>';
    }

    private static function renderSlider(array $data, array $media): string
    {
        $slides = isset($data['slides']) && is_array($data['slides']) ? array_slice($data['slides'], 0, 10) : [];
        $cells = '';
        foreach ($slides as $s) {
            $a = is_array($s) ? $s : (array) $s;
            if (!isset($a['media_id'])) {
                continue;
            }
            $u = self::mediaUrl($media, $a['media_id']);
            if ($u === null) {
                continue;
            }
            $cap = isset($a['caption']) ? (string) $a['caption'] : '';
            $link = isset($a['link']) ? (string) $a['link'] : '';
            $safe = $link === '' ? '' : (BuilderHtmlSanitizer::safeUrl($link) ?? '');
            $img = '<img src="' . self::e($u) . '" alt="' . self::e($cap) . '" loading="lazy">';
            if ($safe !== '') {
                $img = '<a href="' . self::e($safe) . '">' . $img . '</a>';
            }
            $cells .= '<div class="b-slide">' . $img . ($cap !== '' ? '<div class="b-slide-cap">' . self::e($cap) . '</div>' : '') . '</div>';
        }
        if ($cells === '') {
            return '';
        }
        $interval = isset($data['interval']) ? max(1000, min(15000, (int) $data['interval'])) : 4000;
        $auto = !isset($data['autoplay']) || !empty($data['autoplay']) ? '1' : '0';
        return '<div class="b-slider" data-auto="' . $auto . '" data-interval="' . $interval . '">'
            . '<div class="b-slider-track">' . $cells . '</div>'
            . '<button type="button" class="b-slider-prev" aria-label="قبلی">‹</button>'
            . '<button type="button" class="b-slider-next" aria-label="بعدی">›</button>'
            . '</div>';
    }

    private static function renderStory(array $data, array $media): string
    {
        $circles = isset($data['circles']) && is_array($data['circles']) ? array_slice($data['circles'], 0, 10) : [];
        $out = '<div class="b-story-row">';
        foreach ($circles as $ci => $c) {
            $a = is_array($c) ? $c : (array) $c;
            $title = isset($a['title']) ? (string) $a['title'] : 'استوری';
            $coverId = $a['cover_media_id'] ?? null;
            if ($coverId === null && isset($a['items']) && is_array($a['items']) && isset($a['items'][0])) {
                $first = is_array($a['items'][0]) ? $a['items'][0] : (array) $a['items'][0];
                $coverId = $first['media_id'] ?? null;
            }
            $coverUrl = $coverId !== null ? self::mediaUrl($media, $coverId) : null;
            $cover = $coverUrl !== null && !preg_match('/\.mp4($|\?)/i', $coverUrl)
                ? '<img src="' . self::e($coverUrl) . '" alt="' . self::e($title) . '">'
                : '<span>' . self::e(mb_substr($title, 0, 1, 'UTF-8')) . '</span>';
            $items = [];
            if (isset($a['items']) && is_array($a['items'])) {
                foreach (array_slice($a['items'], 0, 10) as $it) {
                    $b = is_array($it) ? $it : (array) $it;
                    if (!isset($b['media_id'])) {
                        continue;
                    }
                    $u = self::mediaUrl($media, $b['media_id']);
                    if ($u === null) {
                        continue;
                    }
                    $dur = isset($b['duration']) ? max(1, min(15, (int) $b['duration'])) : 5;
                    $items[] = ['url' => $u, 'duration' => $dur, 'video' => (bool) preg_match('/\.mp4($|\?)/i', $u)];
                }
            }
            $out .= '<button type="button" class="b-story-circle" data-items="' . self::e(json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '">'
                . '<span class="b-story-cover">' . $cover . '</span>'
                . '<span class="b-story-title">' . self::e($title) . '</span></button>';
        }
        return $out . '</div>';
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function css(): string
    {
        return '.b-page{max-width:1100px;margin:0 auto;padding:16px;font-family:inherit}'
            . '.b-cols{display:flex;gap:12px}.b-col{flex:1 1 0;min-width:0}'
            . '.b-text{margin:8px 0;line-height:2}.b-media{margin:12px 0;text-align:center}'
            . '.b-media-el{max-width:100%;height:auto;border-radius:8px}video.b-media-el{width:100%;max-height:60vh;background:#000}'
            . '.b-media figcaption{color:#666;font-size:13px;margin-top:4px}'
            . '.b-btn-wrap{text-align:center;margin:12px 0}.b-btn{display:inline-flex;align-items:center;gap:8px;background:#0d6efd;color:#fff!important;padding:10px 28px;border-radius:8px;text-decoration:none}'
            . '.b-btn-img{max-height:28px}.b-slider{position:relative;overflow:hidden;border-radius:8px;margin:12px 0}'
            . '.b-slider-track{display:flex;overflow-x:auto;scroll-snap-type:x mandatory}.b-slide{min-width:100%;scroll-snap-align:start;text-align:center}.b-slide img{width:100%;max-height:60vh;object-fit:contain}'
            . '.b-slider-prev,.b-slider-next{position:absolute;top:40%;border:0;border-radius:50%;width:32px;height:32px;background:rgba(255,255,255,.9)}'
            . '.b-slider-prev{left:8px}.b-slider-next{right:8px}'
            . '.b-story-row{display:flex;gap:12px;overflow-x:auto;padding:8px 2px}.b-story-circle{border:0;background:none;display:flex;flex-direction:column;align-items:center;gap:4px}'
            . '.b-story-cover{width:60px;height:60px;border-radius:50%;border:2px solid #ccc;overflow:hidden;display:flex;align-items:center;justify-content:center;font-weight:700;background:#fff}'
            . '.b-story-cover img{width:100%;height:100%;object-fit:cover}.b-story-title{font-size:12px;max-width:68px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}';
    }

    private static function js(): string
    {
        return '(function(){'
            . 'document.querySelectorAll(".b-slider").forEach(function(s){'
            . 'var track=s.querySelector(".b-slider-track");var slides=s.querySelectorAll(".b-slide");var i=0;'
            . 'function show(n){i=(n+slides.length)%slides.length;track.scrollTo({left:i*track.clientWidth,behavior:"smooth"});}'
            . 'var p=s.querySelector(".b-slider-prev"),nx=s.querySelector(".b-slider-next");'
            . 'if(p)p.addEventListener("click",function(){show(i-1)});if(nx)nx.addEventListener("click",function(){show(i+1)});'
            . 'if(s.dataset.auto==="1"&&slides.length>1){var t=setInterval(function(){show(i+1)},parseInt(s.dataset.interval||"4000",10));'
            . 's.addEventListener("mouseenter",function(){clearInterval(t)});'
            . 's.addEventListener("mouseleave",function(){t=setInterval(function(){show(i+1)},parseInt(s.dataset.interval||"4000",10))});}'
            . '});'
            . 'document.querySelectorAll(".b-story-circle").forEach(function(btn){btn.addEventListener("click",function(){'
            . 'var items=JSON.parse(btn.dataset.items||"[]");if(!items.length)return;'
            . 'var ov=document.createElement("div");ov.style.cssText="position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;display:flex;align-items:center;justify-content:center";'
            . 'var box=document.createElement("div");box.style.cssText="position:relative;width:min(420px,94vw);background:#111;border-radius:12px;overflow:hidden";'
            . 'var x=document.createElement("button");x.textContent="×";x.style.cssText="position:absolute;top:8px;right:8px;z-index:2;width:32px;height:32px;border-radius:50%;border:0";'
            . 'var st=document.createElement("div");st.style.cssText="width:100%;min-height:50vh;display:flex;align-items:center;justify-content:center;background:#000";'
            . 'box.appendChild(x);box.appendChild(st);ov.appendChild(box);document.body.appendChild(ov);'
            . 'var k=0,tm=null;function show(){if(k>=items.length){close();return}st.innerHTML="";var it=items[k];'
            . 'if(it.video){var v=document.createElement("video");v.src=it.url;v.autoplay=true;v.controls=false;v.style.width="100%";v.addEventListener("ended",function(){k++;show()});st.appendChild(v);v.play().catch(function(){});}'
            . 'else{var im=document.createElement("img");im.src=it.url;im.style.width="100%";st.appendChild(im);}'
            . 'tm=setTimeout(function(){k++;show()},(it.duration||5)*1000);}'
            . 'function close(){clearTimeout(tm);ov.remove();document.removeEventListener("keydown",key);}'
            . 'function key(e){if(e.key==="Escape")close();}'
            . 'x.addEventListener("click",close);ov.addEventListener("click",function(e){if(e.target===ov)close()});document.addEventListener("keydown",key);show();'
            . '})});'
            . '})();';
    }
}
