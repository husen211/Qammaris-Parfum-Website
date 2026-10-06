<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class BlogHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'code', 'em', 'h2', 'h3', 'h4', 'hr', 'i', 'img',
        'li', 'ol', 'p', 'pre', 's', 'strong', 'table', 'tbody', 'td', 'th', 'thead',
        'tr', 'u', 'ul',
    ];

    private const BLOCKED_TAGS = [
        'applet', 'audio', 'base', 'button', 'embed', 'form', 'frame', 'frameset', 'iframe',
        'input', 'link', 'math', 'meta', 'noscript', 'object', 'option', 'script', 'select',
        'source', 'style', 'svg', 'template', 'textarea', 'video',
    ];

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="qammaris-blog-root">'.
                $html.
                '</div></body></html>',
                LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            if (! $loaded) {
                return '';
            }

            $root = $document->getElementById('qammaris-blog-root');

            if (! $root) {
                return '';
            }

            $this->sanitizeChildren($root);

            $safeHtml = '';
            foreach ($root->childNodes as $child) {
                $safeHtml .= $document->saveHTML($child);
            }

            return $safeHtml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::BLOCKED_TAGS, true)) {
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeChildren($node);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeAttributes($node, $tag);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowedAttributes = match ($tag) {
            'a' => ['href', 'title', 'target'],
            'img' => ['alt', 'height', 'loading', 'src', 'title', 'width'],
            default => [],
        };

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array(strtolower($attribute->name), $allowedAttributes, true)) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tag === 'a') {
            if ($element->hasAttribute('href') && ! $this->isSafeUrl($element->getAttribute('href'), true)) {
                $element->removeAttribute('href');
            }

            if ($element->getAttribute('target') !== '_blank') {
                $element->removeAttribute('target');
            } else {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($tag === 'img') {
            if (! $element->hasAttribute('src') || ! $this->isSafeUrl($element->getAttribute('src'), false)) {
                $element->removeAttribute('src');
            }

            if ($element->hasAttribute('loading') && ! in_array($element->getAttribute('loading'), ['eager', 'lazy'], true)) {
                $element->removeAttribute('loading');
            }

            foreach (['height', 'width'] as $dimension) {
                if ($element->hasAttribute($dimension) && ! ctype_digit($element->getAttribute($dimension))) {
                    $element->removeAttribute($dimension);
                }
            }
        }
    }

    private function isSafeUrl(string $url, bool $allowContactSchemes): bool
    {
        $decodedUrl = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($decodedUrl === '' || str_starts_with($decodedUrl, '//') || str_starts_with($decodedUrl, '\\')) {
            return false;
        }

        if (str_starts_with($decodedUrl, '#') || str_starts_with($decodedUrl, '/')) {
            return true;
        }

        $normalizedUrl = preg_replace('/[\x00-\x20\x7F]+/u', '', $decodedUrl) ?? '';

        if (! preg_match('/^([a-z][a-z0-9+.-]*):/i', $normalizedUrl, $matches)) {
            return true;
        }

        $allowedSchemes = $allowContactSchemes
            ? ['http', 'https', 'mailto', 'tel']
            : ['http', 'https'];

        return in_array(strtolower($matches[1]), $allowedSchemes, true);
    }
}
