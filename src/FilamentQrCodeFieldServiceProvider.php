<?php

namespace Fadlee\FilamentQrCodeField;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentQrCodeFieldServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-qrcode-field';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            // Registers the `qrcode-field::` view namespace used by the
            // scanner field's Blade view.
            ->hasViews('qrcode-field');
    }

    public function packageBooted(): void
    {
        // Unlike the v3 version of this package, we no longer need to inject
        // a global modal via a render hook: the scanner now lives entirely
        // inside the schema of whichever Action/field uses it, so it is only
        // ever rendered (and its JS only ever loaded) when actually needed.
        FilamentAsset::register([
            Js::make('qrcode-scanner', __DIR__ . '/../resources/js/qrcode-scanner.js')
                ->loadedOnRequest(),
        ], package: 'qrcode-field');
    }
}
