<?php
declare(strict_types=1);

namespace App\Services;

/**
 * एडिटर से आया HTML साफ़ करता है: सिर्फ़ allowlist वाले टैग और एट्रिब्यूट रहते हैं।
 * script, style, iframe, on* इवेंट और javascript: लिंक हट जाते हैं।
 */
final class HtmlSanitizer
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'blockquote' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href', 'title', 'target'], 'img' => ['src', 'alt', 'width', 'height'], 'figure' => [], 'figcaption' => [],
        'hr' => [], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'code' => [], 'pre' => [], 'mark' => [],
    ];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta', 'base', 'frame', 'frameset', 'template'];

    public static function clean(?string $html, string $siteUrl = ''): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__root');
        if (!$root) {
            return '';
        }
        self::walk($root, $siteUrl);
        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $doc->saveHTML($c);
        }
        return trim($out);
    }

    private static function walk(\DOMNode $node, string $siteUrl): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            self::walk($child, $siteUrl);
            if (!isset(self::ALLOWED[$tag])) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                if (!in_array(strtolower($attr->name), self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (!preg_match('~^(https?://|mailto:|tel:|/|#)~i', $href)) {
                    $child->removeAttribute('href');
                } elseif (preg_match('~^https?://~i', $href) && ($siteUrl === '' || !str_starts_with($href, $siteUrl))) {
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'noopener nofollow');
                } else {
                    $child->removeAttribute('target');
                }
            }
            if ($tag === 'img') {
                if (!preg_match('~^(https?://|/)~i', trim($child->getAttribute('src')))) {
                    $node->removeChild($child);
                    continue;
                }
                $child->setAttribute('loading', 'lazy');
            }
        }
    }
}
