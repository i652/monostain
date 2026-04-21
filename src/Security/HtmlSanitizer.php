<?php
declare(strict_types=1);

namespace Stain\Security;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3',
        'a',
        'strong', 'b',
        'em', 'i',
        'u',
        'p',
        'br',
        'ul', 'ol', 'li',
        'img',
    ];

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        // Ensure <body> exists across browsers/encodings.
        $doc->loadHTML('<?xml encoding="utf-8" ?><!doctype html><html><body>' . $html . '</body></html>', LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMNode) {
            $body = $doc->documentElement;
            if (!$body instanceof \DOMNode) {
                return '';
            }
        }

        // Do not sanitize wrapper nodes (<html>/<body>) themselves.
        $children = [];
        foreach ($body->childNodes as $c) {
            $children[] = $c;
        }
        foreach ($children as $child) {
            $this->walkAndClean($child);
        }

        $out = '';
        foreach ($body->childNodes as $child) {
            // If we fell back to documentElement, only return body contents.
            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'body') {
                foreach ($child->childNodes as $bodyChild) {
                    $out .= $doc->saveHTML($bodyChild);
                }
                return $out;
            }
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    private function walkAndClean(\DOMNode $node): void
    {
        if ($node->hasChildNodes()) {
            // copy nodes to avoid live list issues
            $children = [];
            foreach ($node->childNodes as $c) {
                $children[] = $c;
            }
            foreach ($children as $child) {
                $this->walkAndClean($child);
            }
        }

        if ($node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);
            if ($tag === 'script' || $tag === 'style') {
                $node->parentNode?->removeChild($node);
                return;
            }
            if ($tag === 'div') {
                $renamed = $this->renameTag($node, 'p');
                if ($renamed !== null) {
                    $node = $renamed;
                }
                $tag = 'p';
            }
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrap($node);
                return;
            }

            // normalize tags
            if ($tag === 'b') {
                $renamed = $this->renameTag($node, 'strong');
                if ($renamed !== null) {
                    $node = $renamed;
                }
                $tag = 'strong';
            }
            if ($tag === 'i') {
                $renamed = $this->renameTag($node, 'em');
                if ($renamed !== null) {
                    $node = $renamed;
                }
                $tag = 'em';
            }

            // strip attributes
            $allowedAttrs = [];
            if ($tag === 'a') {
                $allowedAttrs = ['href'];
            }
            if ($tag === 'img') {
                $allowedAttrs = ['src', 'alt'];
            }
            $this->filterAttributes($node, $allowedAttrs);

            if ($tag === 'a') {
                $href = (string) $node->getAttribute('href');
                if (!$this->isSafeUrl($href)) {
                    $node->removeAttribute('href');
                }
            }
            if ($tag === 'img') {
                $src = (string) $node->getAttribute('src');
                if (!$this->isSafeMediaSrc($src)) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }
    }

    private function filterAttributes(\DOMElement $el, array $allowed): void
    {
        $toRemove = [];
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->name);
            if (!in_array($name, $allowed, true)) {
                $toRemove[] = $attr->name;
            }
        }
        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }
    }

    private function unwrap(\DOMElement $el): void
    {
        $parent = $el->parentNode;
        if (!$parent) {
            return;
        }
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    /**
     * @return \DOMElement|null The new in-document element, or null if replace failed
     */
    private function renameTag(\DOMElement $el, string $newTag): ?\DOMElement
    {
        $doc = $el->ownerDocument;
        if (!$doc) {
            return null;
        }
        $replacement = $doc->createElement($newTag);
        while ($el->firstChild) {
            $replacement->appendChild($el->firstChild);
        }
        // keep allowed attributes only; will be filtered later anyway
        foreach ($el->attributes as $attr) {
            $replacement->setAttribute($attr->name, $attr->value);
        }
        $parent = $el->parentNode;
        if (!$parent) {
            return null;
        }
        $parent->replaceChild($replacement, $el);

        return $replacement;
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return true;
        }
        return (bool) preg_match('#^https?://#i', $url);
    }

    private function isSafeMediaSrc(string $src): bool
    {
        $src = trim($src);
        if ($src === '') {
            return false;
        }
        // only allow serving from our media endpoint
        return (bool) preg_match('#^/media/[0-9]+$#', $src);
    }
}

