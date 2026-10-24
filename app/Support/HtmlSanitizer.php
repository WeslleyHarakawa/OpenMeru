<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Keeps only the formatting the compose editor produces (text styles, lists,
 * quotes, links, alignment and colours) and drops everything else.
 */
class HtmlSanitizer
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'a', 'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'span', 'div', 'pre', 'code'];

    private const STYLES = ['color', 'background-color', 'text-align'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('root');
        if (! $root) {
            return '';
        }
        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (! in_array($tag, self::TAGS, true)) {
                    // Unknown wrappers keep their text; scripts, styles and the like vanish.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'img', 'video', 'audio', 'link', 'meta'], true)) {
                        $node->removeChild($child);
                        continue;
                    }
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                self::cleanAttributes($child, $tag);
                self::walk($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $class = $el->getAttribute('class');
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            $keep = false;

            if ($name === 'href' && $tag === 'a') {
                $keep = (bool) preg_match('#^(https?:|mailto:)#i', $value);
            } elseif ($name === 'style') {
                $value = self::cleanStyle($value);
                $keep = $value !== '';
                if ($keep) {
                    $el->setAttribute('style', $value);
                }
                if ($keep) {
                    continue;
                }
            }

            if (! $keep) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer');
        }
        // Quill marks alignment and lists with classes; turn them into inline styles mail clients understand.
        if ($class !== '' && preg_match('/ql-align-(center|right|justify)/', $class, $m)) {
            $el->setAttribute('style', trim($el->getAttribute('style').';text-align:'.$m[1], ';'));
        }
        $el->removeAttribute('class');
    }

    private static function cleanStyle(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $rule) {
            [$prop, $val] = array_pad(array_map('trim', explode(':', $rule, 2)), 2, '');
            $prop = strtolower($prop);
            if (in_array($prop, self::STYLES, true) && preg_match('/^[#a-z0-9(),.%\s-]{1,40}$/i', $val) && ! preg_match('/url|expression/i', $val)) {
                $kept[] = $prop.':'.$val;
            }
        }

        return implode(';', $kept);
    }
}
