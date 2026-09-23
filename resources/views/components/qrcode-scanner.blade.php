@php
    $statePath = $field->getStatePath();
    $isAutoSubmitted = $field->isAutoSubmitted();
    $facingMode = $field->getFacingMode();
    $noCameraMessage = $field->getNoCameraMessage();
    $cameraDeniedMessage = $field->getCameraDeniedMessage();
@endphp

<x-filament-forms::field-wrapper :field="$field">
    @once
        <style>
            .fi-qrcode-scanner-viewport {
                position: relative;
                overflow: hidden;
                aspect-ratio: 1 / 1;
                width: 100%;
                max-width: 22rem;
                margin-inline: auto;
                border-radius: 0.75rem;
                background-color: rgb(0 0 0 / 0.9);
            }

            .fi-qrcode-scanner-video {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .fi-qrcode-scanner-frame {
                position: absolute;
                inset: 12%;
                border: 3px solid rgb(255 255 255 / 0.6);
                border-radius: 0.75rem;
                box-shadow: 0 0 0 999px rgb(0 0 0 / 0.35);
                transition: border-color 150ms ease, box-shadow 150ms ease;
                pointer-events: none;
            }

            .fi-qrcode-scanner-frame-success {
                border-color: rgb(34 197 94);
            }

            .fi-qrcode-scanner-status {
                margin-top: 0.75rem;
                text-align: center;
                font-size: 0.875rem;
                line-height: 1.25rem;
            }

            .fi-qrcode-scanner-status-error {
                color: rgb(239 68 68);
            }

            .fi-qrcode-scanner-result {
                margin-top: 0.75rem;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
                text-align: center;
            }

            .fi-qrcode-scanner-result-value {
                font-weight: 600;
                word-break: break-all;
            }
        </style>
    @endonce

    <div
        wire:ignore
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getScriptSrc('qrcode-scanner', 'qrcode-field') }}"
        x-data="qrCodeScannerFormComponent({
            state: $wire.$entangle('{{ $statePath }}'),
            autoSubmit: @js($isAutoSubmitted),
            facingMode: @js($facingMode),
            cameraDeniedMessage: @js($cameraDeniedMessage),
            noCameraMessage: @js($noCameraMessage),
        })"
        x-init="init()"
        x-on:modal-closed.window="stopCamera()"
        x-on:close-modal-quietly.window="stopCamera()"
        class="fi-qrcode-scanner"
    >
        <div class="fi-qrcode-scanner-viewport" x-show="!errorMessage">
            <video x-ref="video" muted playsinline class="fi-qrcode-scanner-video"></video>

            <div
                class="fi-qrcode-scanner-frame"
                x-bind:class="{ 'fi-qrcode-scanner-frame-success': hasResult }"
            ></div>
        </div>

        <template x-if="errorMessage">
            <p class="fi-qrcode-scanner-status fi-qrcode-scanner-status-error" x-text="errorMessage"></p>
        </template>

        <template x-if="!errorMessage && !hasResult && isInitializing">
            <p class="fi-qrcode-scanner-status">{{ __('Starting camera...') }}</p>
        </template>

        <template x-if="!errorMessage && !hasResult && !isInitializing">
            <p class="fi-qrcode-scanner-status">{{ __('Point your camera at a QR code') }}</p>
        </template>

        <template x-if="!errorMessage && hasResult">
            <div class="fi-qrcode-scanner-result">
                <p class="fi-qrcode-scanner-status">{{ __('Code detected') }}</p>
                <p class="fi-qrcode-scanner-result-value" x-text="state"></p>

                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    x-on:click="rescan()"
                >
                    {{ __('Scan again') }}
                </x-filament::button>
            </div>
        </template>
    </div>
</x-filament-forms::field-wrapper>
