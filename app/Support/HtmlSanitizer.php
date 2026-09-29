<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Strips a Trix-editor HTML fragment down to a small, known-safe tag set
 * before it's stored, so a pasted/crafted <script>, onerror=, or
 * javascript: link can't ride along into a page every user sees (the
 * dashboard news carousel). Not a general-purpose HTML sanitizer - just
 * enough for the tags Trix actually emits.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'div', 'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'a',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href'],
    ];

    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto'];

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8"?><div id="html-sanitizer-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $document->getElementById('html-sanitizer-root');

        if (! $root) {
            return strip_tags($html);
        }

        self::sanitizeChildren($document, $root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function sanitizeChildren(DOMDocument $document, DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::sanitizeChildren($document, $child);
                self::unwrap($child);

                continue;
            }

            self::sanitizeAttributes($child, $tag);
            self::sanitizeChildren($document, $child);
        }
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            if (! in_array($attribute->name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $href = trim($element->getAttribute('href'));
            $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

            if ($scheme !== '' && ! in_array($scheme, self::ALLOWED_URL_SCHEMES, true)) {
                $element->removeAttribute('href');
            } else {
                $element->setAttribute('rel', 'noopener noreferrer');
                $element->setAttribute('target', '_blank');
            }
        }
    }

    /** Replace a disallowed element with its (already-sanitized) children, keeping the text but dropping the wrapper. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }
}
