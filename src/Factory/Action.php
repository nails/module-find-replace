<?php

namespace Nails\FindReplace\Factory;

class Action
{
    /**
     * Colour treatments. Size classes are not variants.
     *
     * @var list<string>
     */
    private const VARIANTS = [
        'btn-default',
        'btn-primary',
        'btn-secondary',
        'btn-success',
        'btn-danger',
        'btn-warning',
        'btn-info',
        'btn-link',
        'btn-brand-primary',
        'btn-brand-secondary',
    ];

    public function __construct(
        private readonly string $label,
        private readonly string $url,
        private readonly ?string $cssClass = null,
        private readonly ?string $target = null,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    /**
     * Admin button classes. A colour variant replaces the default primary treatment.
     */
    public function buttonClass(): string
    {
        $extra = trim((string) $this->cssClass);
        $tokens = $extra === '' ? [] : preg_split('/\s+/', $extra);
        $variant = false;

        foreach ($tokens ?: [] as $token) {
            if (in_array($token, self::VARIANTS, true)) {
                $variant = true;
                break;
            }
        }

        $base = $variant ? 'btn btn-sm' : 'btn btn-sm btn-primary';

        return trim($base . ' ' . $extra);
    }
}
