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

    protected bool | Closure $capturesImage = false;

    protected string | Closure $imageFieldName = 'image';

    protected string | Closure $imageFormat = 'image/jpeg';

    protected float | Closure $imageQuality = 0.85;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
    }

    /**
     * When enabled, a snapshot of the camera at the moment the QR code was
     * read is captured and made available under a sibling field (see
     * `imageFieldName()`) as a base64 data URI, e.g. `data:image/jpeg;base64,...`.
     *
     * This only works if this field's containing schema also has a
     * (typically `Hidden`) field registered under `imageFieldName()` - when
     * used through `ScanQrCodeAction`, that field is added for you
     * automatically when you call `->captureImage()` on the action instead
     * of on this field directly.
     */
    public function captureImage(bool | Closure $condition = true): static
    {
        $this->capturesImage = $condition;

        return $this;
    }

    public function isCapturingImage(): bool
    {
        return (bool) $this->evaluate($this->capturesImage);
    }

    /**
     * The name of the sibling field the captured image is written into, so
     * it ends up under `$data[$imageFieldName]` in the action's `$data`.
     */
    public function imageFieldName(string | Closure $name): static
    {
        $this->imageFieldName = $name;

        return $this;
    }

    public function getImageFieldName(): string
    {
        return $this->evaluate($this->imageFieldName);
    }

    /**
     * The absolute state path of the sibling image field, computed relative
     * to this field's own container so it doesn't need to be passed in
     * directly - this is what the captured image actually gets entangled
     * against client-side.
     */
    public function getImageStatePath(): string
    {
        $containerStatePath = $this->getContainer()->getStatePath();

        return filled($containerStatePath)
            ? "{$containerStatePath}.{$this->getImageFieldName()}"
            : $this->getImageFieldName();
    }

    /**
     * The image format the image is captured as, as a MIME type understood
     * by `HTMLCanvasElement.toDataURL()` - `image/jpeg` (the default) or
     * `image/png`. PNG produces a larger payload with no quality loss;
     * `imageQuality()` is ignored for it.
     */
    public function imageFormat(string | Closure $format): static
    {
        $this->imageFormat = $format;

        return $this;
    }

    public function getImageFormat(): string
    {
        return $this->evaluate($this->imageFormat);
    }

    /**
     * JPEG/WebP compression quality between `0.0` and `1.0`. Lower values
     * produce a smaller base64 payload at the cost of image quality.
     */
    public function imageQuality(float | Closure $quality): static
    {
        $this->imageQuality = $quality;

        return $this;
    }

    public function getImageQuality(): float
    {
        return $this->evaluate($this->imageQuality);
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
