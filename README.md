# Filament QR Code Field

A QR code scanner **Action** for Filament (with an optional standalone form field), rewritten to
work with **Filament v4 / v5**.

This is a fork/rewrite of [fadlee/filament-qrcode-field](https://github.com/fadlee/filament-qrcode-field),
which only supported Filament v3. See ["What changed from the v3 version"](#what-changed-from-the-v3-version)
below for details.

## Features

- `ScanQrCodeAction`: click a button, the camera opens **immediately** in a modal, and as soon as
  a QR code is read, its value is passed straight into your `->action()` closure - no extra click
  required (this is configurable).
- Works as a page/header action, a table action (row or bulk), or a form component action.
- Also ships a standalone `QrCodeScanner` form field, if you'd rather embed the scanner directly
  in one of your own forms.
- Scoped per-instance camera handling (no global DOM IDs), so it's safe to use multiple times on
  the same page, or nested inside stacked action modals.
- Uses the [ZXing](https://github.com/zxing-js/library) library for QR/barcode detection, loaded
  lazily only when the scanner is actually opened.

## Requirements

- PHP 8.2+
- Filament 4.x or 5.x (**not** compatible with Filament v3 - see below)
- HTTPS (or `localhost`) - required by browsers for camera access

## Installation

```bash
composer require fadlee/filament-qrcode-field
php artisan filament:assets
```

## Usage: as an Action (recommended)

```php
use Fadlee\FilamentQrCodeField\Actions\ScanQrCodeAction;

ScanQrCodeAction::make()
    ->action(function (array $data) {
        // $data['code'] contains the scanned value
        $product = Product::where('sku', $data['code'])->first();

        // ...
    });
```

This works anywhere a Filament `Action` works: header/page actions, table row/bulk actions,
`Actions` form components, `Infolist` actions, etc.

### Passing the scanned value into other logic

```php
use Fadlee\FilamentQrCodeField\Actions\ScanQrCodeAction;
use Filament\Notifications\Notification;

ScanQrCodeAction::make('scanProduct')
    ->label('Scan product')
    ->action(function (array $data) {
        $sku = $data['code'];

        if (! $product = Product::where('sku', $sku)->first()) {
            Notification::make()
                ->title("No product found for \"{$sku}\"")
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title("Found: {$product->name}")
            ->success()
            ->send();
    });
```

### Customising the action

```php
ScanQrCodeAction::make()
    ->label('Scan barcode')
    ->resultFieldName('barcode')      // $data['barcode'] instead of $data['code']
    ->autoSubmit()                    // default: true - run the action as soon as a code is read
    ->facingMode('environment')       // 'environment' (rear camera) or 'user' (front camera)
    ->preferFrontCamera()             // shortcut for ->facingMode('user')
    ->modalHeading('Scan a barcode');
```

If you disable `->autoSubmit(false)`, the scanned value is shown with a "Scan again" button and
the user has to click the modal's confirm button themselves (useful if you want them to double
check the value before it's used).

## Usage: as a standalone form field

If you want the scanner embedded directly inside one of your own forms instead of a separate
action button:

```php
use Fadlee\FilamentQrCodeField\Forms\Components\QrCodeScanner;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        QrCodeScanner::make('code')
            ->label('Scan QR code')
            ->required(),
        // ... other fields
    ]);
}
```

Note that `->autoSubmit()` on the standalone field only makes sense inside an action modal (it
calls `$wire.callMountedAction()`), so it defaults to `false` here and is not meant to be enabled
in a regular, non-action form.

## How it works

1. Clicking the action's button opens a Filament modal whose schema contains the scanner field.
2. As soon as the field mounts (i.e. as soon as the modal opens), it requests camera access and
   starts scanning - no separate click is needed to "activate" the scanner.
3. When a QR code is detected, its value is written into the modal's form state. If `autoSubmit()`
   is enabled (the default for the Action), the mounted action is submitted automatically,
   invoking your `->action()` closure with the scanned value.
4. The camera is stopped as soon as the modal closes, for any reason (submit, cancel, escape key,
   backdrop click).

## What changed from the v3 version

The original package targeted **Filament v3** and doesn't work as-is on v4/v5. The main breaking
changes this rewrite deals with:

- **Actions are unified.** `Filament\Forms\Components\Actions\Action` / `Filament\Tables\Actions\Action`
  / `Filament\Pages\Actions\Action` no longer exist as separate classes - there is now a single
  `Filament\Actions\Action`. This is what made it possible (and natural) to turn this package into
  an Action instead of only a form field.
- **The default field Blade views were removed.** The v3 version worked by pointing a custom field
  at Filament's own `filament-forms::components.text-input` view
  (`protected string $view = 'filament-forms::components.text-input';`). That view file no longer
  exists in v4/v5 - built-in fields now render via `toEmbeddedHtml()` (plain PHP) instead of Blade,
  for performance. This rewrite ships its own dedicated Blade view instead of trying to reuse a core
  one.
- **The old `element._x_model.set(value)` trick is gone.** It relied on the default text input's
  Blade view wiring up `x-model` via Alpine, which no longer happens for plain inputs. State is now
  synced properly through `$wire.$entangle()`, following the same pattern Filament's own JS-heavy
  fields (like the color picker) use internally.
- **No more global render hook / page-wide modal injection.** The v3 version injected a single
  shared modal at the end of every page via a `PanelsRenderHook`, and matched clicks by a global
  `data-qrcode-field` attribute + DOM id. Since the scanner is now just a component inside an
  Action's own schema, each instance is fully self-contained and scoped - which also fixes the
  "multiple QR fields on the same page" edge case more robustly than the original.
- **Camera lookup is now constraint-based.** Instead of enumerating devices up front and guessing
  the rear camera from its label, the scanner uses the standard
  `{ video: { facingMode: { ideal: ... } } }` constraint, which is more reliable across devices/browsers.

## Security

- Camera permission is requested by the browser only when the scanner modal actually opens.
- The camera is stopped as soon as the modal closes.
- HTTPS (or `localhost`) is required for camera access - this is a browser requirement, not
  something this package can work around.

## Credits

Based on [fadlee/filament-qrcode-field](https://github.com/fadlee/filament-qrcode-field), which
was itself inspired by [Design-The-Box/barcode-field](https://github.com/Design-The-Box/barcode-field).
Uses [ZXing](https://github.com/zxing-js/library) for QR code detection.

## License

MIT.
