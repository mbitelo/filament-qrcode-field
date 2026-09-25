<?php

namespace Fadlee\FilamentQrCodeField\Actions;

use Closure;
use Fadlee\FilamentQrCodeField\Forms\Components\QrCodeScanner;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ScanQrCodeAction extends Action
{
    protected string | Closure $resultFieldName = 'code';

    protected bool | Closure $isAutoSubmitted = true;

    protected string | Closure $facingMode = 'environment';

    protected bool | Closure $capturesImage = false;

    protected string | Closure $imageFieldName = 'image';

    protected string | Closure $imageFormat = 'image/jpeg';

    protected float | Closure $imageQuality = 0.85;

    public static function getDefaultName(): ?string
    {
        return 'scanQrCode';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Scan QR code'));
        $this->icon(Heroicon::OutlinedQrCode);
        $this->modalHeading(__('Scan QR code'));
        $this->modalDescription(__("Point your device's camera at a QR code."));
        $this->modalWidth(Width::Medium);
        $this->modalSubmitActionLabel(__('Use code'));
        $this->modalCancelActionLabel(__('Cancel'));

        // There is no point showing a submit button the user never needs to
        // click. Note this only returns a plain `bool`/`Action`, it doesn't
        // reference `Get`: the modal's submit button is a standalone footer
        // action with no schema component of its own, so a closure that
        // asked for `Get $get` here would fail the same way described below
        // for `->required()`.
        $this->modalSubmitAction(
            fn (Action $action): Action | bool => $this->isAutoSubmitted() ? false : $action,
        );

        // Note: we deliberately don't try to disable the modal's submit
        // button based on whether a code has been scanned yet. The submit
        // button is a standalone footer action with no schema component of
        // its own, so a `disabled(fn (Get $get) => ...)` closure on it
        // cannot resolve `$get` (Filament tries to build it from the
        // button's own, nonexistent, schema component and throws). The
        // `->required()` validation on the scanner field below already
        // blocks an empty manual submission; auto-submit covers the happy
        // path without the user ever touching that button.

        $this->schema(fn (): array => [
            QrCodeScanner::make($this->getResultFieldName())
                ->required()
                ->autoSubmit($this->isAutoSubmitted())
                ->facingMode($this->getFacingMode())
                ->captureImage($this->isCapturingImage())
                ->imageFieldName($this->getImageFieldName())
                ->imageFormat($this->getImageFormat())
                ->imageQuality($this->getImageQuality()),
            ...($this->isCapturingImage() ? [
                Hidden::make($this->getImageFieldName()),
            ] : []),
        ]);
    }

    /**
     * The key under which the scanned value will be available in the
     * `$data` array passed to `->action()`, e.g.:
     *
     * ScanQrCodeAction::make()
     *     ->resultFieldName('qrcode')
     *     ->action(function (array $data) {
     *         // $data['qrcode']
     *     });
     */
    public function resultFieldName(string | Closure $name): static
    {
        $this->resultFieldName = $name;

        return $this;
    }

    public function getResultFieldName(): string
    {
        return $this->evaluate($this->resultFieldName);
    }

    /**
     * When enabled (the default), the action is run automatically the
     * instant a QR code is successfully scanned - the user never has to
     * click a separate confirm button. Disable this if you want the user to
     * be able to review the scanned value before confirming.
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

    /**
     * When enabled, a snapshot of the camera at the moment the QR code was
     * read is made available as a base64 data URI (e.g.
     * `data:image/jpeg;base64,...`) under `$data[$imageFieldName]` (see
     * `imageFieldName()`, default `'imagem'`):
     *
     * ScanQrCodeAction::make()
     *     ->captureImage()
     *     ->action(function (array $data) {
     *         // $data['code'], $data['imagem']
     *         Storage::put('scans/' . Str::uuid() . '.jpg', base64_decode(
     *             Str::after($data['imagem'], ','),
     *         ));
     *     });
     *
     * Note this increases the size of the request made when the action is
     * submitted - a captured image is typically tens of KB once base64
     * encoded, depending on `imageFormat()` / `imageQuality()`.
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
     * The key under which the captured image will be available in `$data`.
     * Only relevant when `->captureImage()` is enabled.
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
     * The image format the image is captured as: `image/jpeg` (the default)
     * or `image/png`. PNG produces a larger payload with no quality loss;
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
     * JPEG compression quality between `0.0` and `1.0` (default `0.85`).
     * Lower values produce a smaller base64 payload at the cost of image
     * quality. Ignored when `imageFormat()` is `image/png`.
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
}
