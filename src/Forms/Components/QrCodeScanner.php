<?php

namespace Fadlee\FilamentQrCodeField\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class QrCodeScanner extends Field
{
    /**
     * We ship our own Blade view rather than relying on any of Filament's
     * built-in field views: as of Filament v4/v5, most core field views were
     * rewritten as `toEmbeddedHtml()` PHP output for performance and no
     * longer exist as publishable Blade files (e.g.
     * `filament-forms::components.text-input` was removed), so the old
     * "reuse the default text input view" trick from the v3 version of this
     * package no longer works.
     */
    protected string $view = 'qrcode-field::components.qrcode-scanner';

    protected bool | Closure $isAutoSubmitted = false;

    protected string | Closure $facingMode = 'environment';

    protected string | Closure | null $noCameraMessage = null;

    protected string | Closure | null $cameraDeniedMessage = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
    }

    /**
     * When enabled, the mounted action is submitted automatically (as if the
     * user had clicked the modal's confirm button) as soon as a QR code is
     * successfully read, so the scanned value flows straight into the
     * action's `->action()` closure without any extra click.
     */
    public function autoSubmit(bool | Closure $condition = true): static
    {
        $this->isAutoSubmitted = $condition;

        return $this;
    }

    public function isAutoSubmitted(): bool
    {
        return (bool) $this->evaluate($this->isAutoSubmitted);
    }

    /**
     * Which camera to prefer, using the standard `facingMode` constraint
     * values (`environment` = rear camera, `user` = front camera).
     */
    public function facingMode(string | Closure $mode): static
    {
        $this->facingMode = $mode;

        return $this;
    }

    public function preferFrontCamera(bool | Closure $condition = true): static
    {
        $this->facingMode(fn (): string => $this->evaluate($condition) ? 'user' : 'environment');

        return $this;
    }

    public function getFacingMode(): string
    {
        return $this->evaluate($this->facingMode);
    }

    public function noCameraMessage(string | Closure | null $message): static
    {
        $this->noCameraMessage = $message;

        return $this;
    }

    public function getNoCameraMessage(): string
    {
        return $this->evaluate($this->noCameraMessage) ?? __('No camera could be found on this device.');
    }

    public function cameraDeniedMessage(string | Closure | null $message): static
    {
        $this->cameraDeniedMessage = $message;

        return $this;
    }

    public function getCameraDeniedMessage(): string
    {
        return $this->evaluate($this->cameraDeniedMessage)
            ?? __('Camera access was denied. Please allow camera access and try again.');
    }
}
