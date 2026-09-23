// Alpine component used by `resources/views/components/qrcode-scanner.blade.php`.
// Lazy-loaded as an ES module via Filament's `x-load` / `x-load-src` directives (see the note
// above the exported function below for exactly how that resolves this file's export).
//
// Unlike the Filament v3 version of this package, there is no `document.getElementById(...)`
// lookup and no reliance on Alpine's internal `_x_model` API: the scanned value is written
// directly to the Livewire-entangled `state` property that is passed in, and the camera is
// scoped to this component instance via `$refs`, so multiple scanners on the same page (or
// nested inside stacked action modals) never collide.

let zxingPromise = null;

function loadZXing() {
    if (window.ZXing) {
        return Promise.resolve(window.ZXing);
    }

    if (!zxingPromise) {
        zxingPromise = import('https://unpkg.com/@zxing/library@0.21.3').then(() => window.ZXing);
    }

    return zxingPromise;
}

// IMPORTANT: this file is loaded via Filament's `x-load` / `x-load-src` directives, which are
// powered by the "Async Alpine" package. Async Alpine dynamically `import()`s this file as a
// real ES module and looks for an export whose name matches the function referenced in
// `x-data="qrCodeScannerFormComponent(...)"` (falling back to a default export, then to the
// module's first export). It then registers that export via `Alpine.data(...)` itself - so the
// function below MUST be an actual `export`, not just a global/window assignment, or Async
// Alpine ends up registering `false` as the component and Alpine throws
// "callback.bind is not a function" while trying to use it.
export function qrCodeScannerFormComponent({
    state,
    autoSubmit = true,
    facingMode = 'environment',
    cameraDeniedMessage = 'Camera access was denied. Please allow camera access and try again.',
    noCameraMessage = 'No camera could be found on this device.',
}) {
    return {
        state,
        autoSubmit,
        facingMode,
        isInitializing: true,
        hasResult: false,
        errorMessage: null,
        codeReader: null,
        zxing: null,

        init() {
            this.hasResult = Boolean(this.state);

            this.startCamera();
        },

        async startCamera() {
            this.isInitializing = true;
            this.errorMessage = null;

            try {
                this.zxing = await loadZXing();
                this.codeReader = new this.zxing.BrowserMultiFormatReader();

                await this.codeReader.decodeFromConstraints(
                    { audio: false, video: { facingMode: { ideal: this.facingMode } } },
                    this.$refs.video,
                    (result, error) => this.handleDecodeResult(result, error),
                );

                this.isInitializing = false;
            } catch (error) {
                this.handleError(error);
            }
        },

        handleDecodeResult(result, error) {
            if (result) {
                this.onResult(result.getText());

                return;
            }

            // `NotFoundException` is thrown continuously by ZXing on every frame in
            // which no code is visible - it is not an error, just a "no match yet".
            if (error && this.zxing && !(error instanceof this.zxing.NotFoundException)) {
                console.error(error);
            }
        },

        onResult(value) {
            if (this.hasResult) {
                return;
            }

            this.hasResult = true;
            this.state = value;

            this.stopScanning();

            if (this.autoSubmit) {
                this.$nextTick(() => this.$wire.callMountedAction());
            }
        },

        rescan() {
            this.hasResult = false;
            this.state = null;
            this.errorMessage = null;

            this.startCamera();
        },

        stopScanning() {
            this.codeReader?.reset();
        },

        stopCamera() {
            this.stopScanning();

            const video = this.$refs.video;

            if (video?.srcObject) {
                video.srcObject.getTracks().forEach((track) => track.stop());
                video.srcObject = null;
            }
        },

        handleError(error) {
            console.error(error);

            this.isInitializing = false;

            if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
                this.errorMessage = this.cameraDeniedMessage;

                return;
            }

            if (error?.name === 'NotFoundError' || error?.name === 'OverconstrainedError') {
                this.errorMessage = this.noCameraMessage;

                return;
            }

            this.errorMessage = this.noCameraMessage;
        },
    };
}
