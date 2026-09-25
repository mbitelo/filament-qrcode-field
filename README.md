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

This package isn't published on Packagist, so point Composer at the GitHub repository directly by
adding a `vcs` repository to your **application's** `composer.json` (not this package's):

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mbitelo/filament-qrcode-field"
        }
    ]
}
```

Then require it (replace `dev-main` with whatever the default branch is called, or a tag once you
push one):

```bash
composer require mbitelo/filament-qrcode-field:dev-main
php artisan filament:assets
```

If you're actively developing the package locally instead, use a `path` repository pointing at
your local clone (add `"options": {"symlink": true}` so edits are picked up immediately, no
`composer update` needed) instead of the `vcs` one above:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../filament-qrcode-field",
            "options": {
                "symlink": true
            }
        }
    ]
}
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
check the value before it's used). When `autoSubmit` is enabled (the default), neither of those
is shown - the modal's submit button is hidden entirely (there's nothing to click), and the
scanner doesn't display the scanned value or a "Scan again" option, since the modal is expected
to close on its own the instant a code is read.

### Capturing a image of the scanned code

```php
use Fadlee\FilamentQrCodeField\Actions\ScanQrCodeAction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

ScanQrCodeAction::make()
    ->captureImage()
    ->action(function (array $data) {
        // $data['code']   -> the scanned value, as before
        // $data['image'] -> a base64 data URI, e.g. "data:image/jpeg;base64,..."

        Storage::disk('public')->put(
            'scans/' . Str::uuid() . '.jpg',
            base64_decode(Str::after($data['imagem'], ',')),
        );
    });
```

The image is a snapshot of exactly what was framed in the square viewport at the instant the code
was read (captured from the same cropped square the user saw, not the camera's full raw frame).
Customise the field name, format or compression with `->imageFieldName('foto')`,
`->imageFormat('image/png')` or `->imageQuality(0.6)` (JPEG only, `0.0`-`1.0`, default `0.85`).
Since the image is sent as base64 in the same request as the rest of the action's data, it adds
some request size (typically tens of KB depending on format/quality) - it's opt-in for that
reason, and off by default.

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
- **The JS asset is loaded as a real ES module.** Filament's `x-load` / `x-load-src` directives
  (used to lazily load the scanner's JS only when it's actually needed) are powered by the
  [Async Alpine](https://async-alpine.dev/) package, which dynamically `import()`s the file
  pointed to by `x-load-src` and looks for an export matching the function name used in
  `x-data="qrCodeScannerFormComponent(...)"`, then registers it itself via `Alpine.data(...)`.
  Because of that, `resources/js/qrcode-scanner.js` **must** `export` its component function - a
  plain `window.qrCodeScannerFormComponent = ...` assignment (which is how most other
  Filament-adjacent packages, and the original v3 field, expose Alpine components) is not enough,
  and silently breaks the component (Alpine ends up throwing `callback.bind is not a function`).
- **The camera is released via Alpine's `destroy()` lifecycle hook**, not just a window event
  listener. When a successful action submission unmounts the modal, Livewire removes the
  scanner's DOM node as part of the same update that dispatches its "modal closed" browser
  events - which can leave a plain `x-on:...window` listener race-destroyed before it ever fires
  (the camera would then keep running in the background). `destroy()` is called directly by
  Alpine whenever the component's root element is removed from the DOM, regardless of what
  triggered the removal or in what order, so that's what actually stops the camera; the window
  event listeners are kept on top of it only to turn the camera off a little earlier, while a
  modal's closing animation is still playing.
- **The visible square is a `<canvas>` we draw into ourselves, not the `<video>` element with CSS
  `object-fit: cover`.** On mobile - especially rear cameras - the stream's actual
  resolution/aspect ratio can keep changing for a moment after playback starts (autofocus,
  exposure, or the OS swapping to a higher-quality feed), and each of those changes showed up as
  a visible "flick" as `object-fit` re-applied itself, sometimes more than once. The `<video>`
  element is now only ever used as a hidden source, for both ZXing's decoding and our own
  `drawImage()` calls; every animation frame, we compute a centered square crop directly from
  `video.videoWidth` / `video.videoHeight` and draw that onto the canvas. However many times the
  underlying stream's dimensions change, there's nothing to visibly "snap" - the next frame is
  simply drawn correctly. We also hint `aspectRatio: { ideal: 1 }` in the `getUserMedia()`
  constraints so there's less to crop out in the first place, but the canvas is what actually
  guarantees a clean square with no flicker, regardless of what any given device/browser does
  with `object-fit` timing.
  `decodeFromConstraints()` / `decodeFromStream()`. We call `getUserMedia()` ourselves, keep the
  only reference to the resulting `MediaStream`, and only ask ZXing to decode frames from an
  already-attached `<video>` element (`decodeFromVideoElementContinuously()`). This means
  `stopCamera()` never depends on ZXing's own internal bookkeeping of "its" stream, which is
  where earlier iterations of this fix still occasionally left the camera running. It also
  guards against a real race condition: if the modal is closed while `getUserMedia()` is still
  pending, the stream is released the instant it resolves instead of being attached.

## Known cosmetic warning

You may see `It was not possible to play the video.` logged to the console by the ZXing library
itself. This comes from ZXing's own internal video-preparation code (it unconditionally sets the
`autoplay` attribute back onto the `<video>` element and separately calls `.play()` on it), and is
caught and merely logged by ZXing - it is not thrown, and does not affect scanning. We deliberately
do not call `video.play()` ourselves to work around it, because `decodeFromVideoElementContinuously()`
only starts decoding once it sees a `playing` event fire, and playing the video ourselves first
would mean that event never fires again, silently breaking scanning entirely. If this warning
bothers you, it's safe to ignore.

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
