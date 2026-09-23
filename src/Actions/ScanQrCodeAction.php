<?php

namespace Fadlee\FilamentQrCodeField\Actions;

use Closure;
use Fadlee\FilamentQrCodeField\Forms\Components\QrCodeScanner;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ScanQrCodeAction extends Action
{
    protected string | Closure $resultFieldName = 'code';

    protected bool | Closure $isAutoSubmitted = true;

    protected string | Closure $facingMode = 'environment';

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
                ->facingMode($this->getFacingMode()),
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
}
