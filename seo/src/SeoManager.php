<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Seo;

use Fnlla\Runtime\RequestContext;

final class SeoManager
{
    private ?string $title = null;
    private ?string $description = null;
    private ?string $canonical = null;
    private array $meta = [];
    private array $properties = [];
    private array $jsonLd = [];

    public function __construct(array $defaults = [])
    {
        $this->applyDefaults($defaults);
    }

    public function title(string $title): self
    {
        $this->title = trim($title);
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = trim($description);
        return $this;
    }

    public function canonical(string $url): self
    {
        $this->canonical = trim($url);
        return $this;
    }

    public function meta(string $name, string $content): self
    {
        $this->meta[$name] = $content;
        return $this;
    }

    public function property(string $property, string $content): self
    {
        $this->properties[$property] = $content;
        return $this;
    }

    public function jsonLd(array $payload): self
    {
        $this->jsonLd[] = $payload;
        return $this;
    }

    public function clear(): self
    {
        $this->title = null;
        $this->description = null;
        $this->canonical = null;
        $this->meta = [];
        $this->properties = [];
        $this->jsonLd = [];
        return $this;
    }

    public function renderMeta(): string
    {
        $lines = [];

        if ($this->title !== null && $this->title !== '') {
            $lines[] = '<title>' . $this->escape($this->title) . '</title>';
            if (!isset($this->properties['og:title'])) {
                $lines[] = '<meta property="og:title" content="' . $this->escape($this->title) . '">';
            }
        }

        if ($this->description !== null && $this->description !== '') {
            $lines[] = '<meta name="description" content="' . $this->escape($this->description) . '">';
            if (!isset($this->properties['og:description'])) {
                $lines[] = '<meta property="og:description" content="' . $this->escape($this->description) . '">';
            }
        }

        if ($this->canonical !== null && $this->canonical !== '') {
            $lines[] = '<link rel="canonical" href="' . $this->escape($this->canonical) . '">';
            if (!isset($this->properties['og:url'])) {
                $lines[] = '<meta property="og:url" content="' . $this->escape($this->canonical) . '">';
            }
        }

        foreach ($this->meta as $name => $content) {
            $lines[] = '<meta name="' . $this->escape($name) . '" content="' . $this->escape($content) . '">';
        }

        foreach ($this->properties as $property => $content) {
            $lines[] = '<meta property="' . $this->escape($property) . '" content="' . $this->escape($content) . '">';
        }

        return implode("\n", $lines);
    }

    public function renderJsonLd(): string
    {
        if ($this->jsonLd === []) {
            return '';
        }

        $lines = [];
        $nonceAttr = $this->resolveScriptNonceAttribute();
        foreach ($this->jsonLd as $payload) {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) {
                continue;
            }
            $lines[] = '<script type="application/ld+json"' . $nonceAttr . '>' . $json . '</script>';
        }

        return implode("\n", $lines);
    }

    public function render(): string
    {
        $meta = $this->renderMeta();
        $jsonLd = $this->renderJsonLd();
        if ($meta !== '' && $jsonLd !== '') {
            return $meta . "\n" . $jsonLd;
        }
        return $meta . $jsonLd;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function resolveScriptNonceAttribute(): string
    {
        $context = RequestContext::current();
        if (!$context instanceof RequestContext) {
            return '';
        }

        $nonce = trim($context->cspNonce());
        if ($nonce === '') {
            return '';
        }

        return ' nonce="' . $this->escape($nonce) . '"';
    }

    private function applyDefaults(array $defaults): void
    {
        $title = $defaults['title'] ?? null;
        if (is_string($title) && $title !== '') {
            $this->title = trim($title);
        }

        $description = $defaults['description'] ?? null;
        if (is_string($description) && $description !== '') {
            $this->description = trim($description);
        }

        $canonical = $defaults['canonical'] ?? null;
        if (is_string($canonical) && $canonical !== '') {
            $this->canonical = trim($canonical);
        }

        $meta = $defaults['meta'] ?? null;
        if (is_array($meta)) {
            foreach ($meta as $name => $content) {
                if (is_string($name) && is_string($content) && $name !== '' && $content !== '') {
                    $this->meta[$name] = $content;
                }
            }
        }

        $properties = $defaults['properties'] ?? null;
        if (is_array($properties)) {
            foreach ($properties as $property => $content) {
                if (is_string($property) && is_string($content) && $property !== '' && $content !== '') {
                    $this->properties[$property] = $content;
                }
            }
        }

        $jsonLd = $defaults['json_ld'] ?? null;
        if (!is_array($jsonLd) && isset($defaults['jsonLd']) && is_array($defaults['jsonLd'])) {
            $jsonLd = $defaults['jsonLd'];
        }
        if (is_array($jsonLd) && $jsonLd !== []) {
            if (array_is_list($jsonLd)) {
                foreach ($jsonLd as $payload) {
                    if (is_array($payload)) {
                        $this->jsonLd[] = $payload;
                    }
                }
            } elseif (is_array($jsonLd)) {
                $this->jsonLd[] = $jsonLd;
            }
        }
    }
}
