<?php
// Whitelist HTML sanitizer for builder text blocks (rich text from contenteditable).
// Pure: no DB, no session. Output is re-serialised from a whitelisted DOM walk, never copied from the input.

final class BuilderHtmlSanitizer
{
    public const DEFAULT_TAGS = ['b', 'strong', 'i', 'em', 'u', 'a', 'br', 'p', 'ul', 'ol', 'li', 'h2', 'h3'];
    public const DEFAULT_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Removed together with everything inside them. */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math',
        'template', 'noscript', 'textarea', 'select', 'option', 'form', 'input', 'button',
        'link', 'meta', 'base', 'title', 'head', 'audio', 'video', 'canvas',
    ];

    private const MAX_DEPTH = 30;

    /**
     * @param string[] $allowedTags lower-case tag names
     */
    public static function clean(string $html, array $allowedTags = self::DEFAULT_TAGS, int $maxBytes = 20000): string
    {
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html);
        if ($html === null || trim($html) === '') {
            return '';
        }
        if (strlen($html) > $maxBytes) {
            $html = mb_strcut($html, 0, $maxBytes, 'UTF-8');
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="sanitizer-root">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $rootList = $xpath->query('//div[@id="sanitizer-root"]');
        if ($rootList === false || $rootList->length !== 1) {
            return '';
        }

        return trim(self::walk($rootList->item(0), array_flip($allowedTags), 0));
    }

    /** Returns the URL when it is safe for an href, otherwise null. */
    public static function safeUrl(string $url, array $schemes = self::DEFAULT_SCHEMES): ?string
    {
        // Remove every whitespace/control character so "java\tscript:" cannot hide a scheme.
        $u = preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        if ($u === null || $u === '' || strlen($u) > 2000) {
            return null;
        }
        if (strpos($u, '\\') !== false) {
            return null; // "/\evil.com" is treated as "//evil.com" by browsers
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $u, $m)) {
            return in_array(strtolower($m[1]), $schemes, true) ? $u : null;
        }
        if (strpos($u, '//') === 0) {
            return null; // protocol-relative
        }
        if ($u[0] === '/' || $u[0] === '#') {
            return $u; // site-relative path or in-page anchor
        }
        return null;
    }

    private static function walk(DOMNode $parent, array $allowed, int $depth): string
    {
        $out = '';
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMText) {
                $out .= htmlspecialchars($child->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue; // comments, CDATA, processing instructions
            }
            $name = strtolower($child->nodeName);
            if (in_array($name, self::DROP_WITH_CONTENT, true)) {
                continue;
            }
            if ($depth >= self::MAX_DEPTH) {
                $out .= htmlspecialchars($child->textContent ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if ($name === 'div' && isset($allowed['p'])) {
                $name = 'p'; // contenteditable wraps lines in <div>
            }
            if (!isset($allowed[$name])) {
                $out .= self::walk($child, $allowed, $depth + 1); // unwrap unknown tags, keep their text
                continue;
            }
            if ($name === 'br') {
                $out .= '<br>';
                continue;
            }
            $inner = self::walk($child, $allowed, $depth + 1);
            if ($name === 'a') {
                $href = self::safeUrl($child->getAttribute('href'));
                if ($href === null) {
                    $out .= $inner;
                } else {
                    $out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="noopener noreferrer">' . $inner . '</a>';
                }
                continue;
            }
            $out .= '<' . $name . '>' . $inner . '</' . $name . '>';
        }
        return $out;
    }
}
