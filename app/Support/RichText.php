<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * One formatted point typed into the in-page editor: bold, italic, underline,
 * strikethrough, line breaks and links. Every other tag is unwrapped (its words
 * kept), every attribute dropped except a safe link href, so what is stored is
 * always safe to print back onto a page or into a PDF.
 */
class RichText
{
    private const ALLOWED = ['strong', 'b', 'em', 'i', 'u', 's', 'a', 'br'];

    /** Tags whose contents go too, not just the tag itself. */
    private const DROP_WHOLE = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'head', 'title', 'noscript', 'img', 'video', 'audio'];

    public static function inline(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        // Older records and anything typed without the editor are plain text
        if (! preg_match('/<\/?[a-z][^>]*>/i', $html)) {
            return preg_replace('/\R/', '<br>', e($html));
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="rt-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('rt-root');
        if (! $root) {
            return e(trim(strip_tags($html))) ?: null;
        }

        self::scrub($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        // Editors pad with non-breaking spaces; plain ones wrap properly.
        // Never decode other entities here - &lt; must stay escaped.
        $out = str_replace(['&nbsp;', "\xC2\xA0"], ' ', $out);
        $out = trim(preg_replace('/^(\s*<br\s*\/?>\s*)+|(\s*<br\s*\/?>\s*)+$/i', '', $out));

        return trim(strip_tags($out)) === '' ? null : $out;
    }

    /** The words alone, for previews that cannot show formatting. */
    public static function plain(?string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', ' ', (string) $html);

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function scrub(DOMNode $node): void
    {
        // Walk a copy: unwrapping and removing change the live list
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DROP_WHOLE, true)) {
                $node->removeChild($child);
                continue;
            }

            self::scrub($child);

            $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
            $safeLink = $tag === 'a' && preg_match('#^(https?://|mailto:)#i', $href);

            if (! in_array($tag, self::ALLOWED, true) || ($tag === 'a' && ! $safeLink)) {
                // Keep the words, lose the wrapper; a block becomes a line break
                $isBlock = in_array($tag, ['div', 'p', 'li', 'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'tr'], true);
                if ($isBlock && $child->previousSibling) {
                    $node->insertBefore($node->ownerDocument->createElement('br'), $child);
                }
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttribute($attribute->nodeName);
            }

            if ($safeLink) {
                $child->setAttribute('href', $href);
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }
}
