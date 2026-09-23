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
        stream: null,

        init() {
            this.hasResult = Boolean(this.state);

            this.startCamera();
        },

        async startCamera() {
            // Guards against ever running two concurrent camera sessions on
            // the same component instance (which would fight over the same
            // <video> element and cause "It was not possible to play the
            // video." warnings from ZXing).
            if (this.stream) {
                return;
            }

            this.isInitializing = true;
            this.errorMessage = null;

            try {
                this.zxing = await loadZXing();

                this.stream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: { facingMode: { ideal: this.facingMode } },
                });

                this.codeReader = new this.zxing.BrowserMultiFormatReader();

                await this.codeReader.decodeFromStream(
                    this.stream,
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

            // No reason to keep the camera running once we have a result,
            // whether or not the action is about to auto-submit.
            this.stopCamera();

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

        stopCamera() {
            this.codeReader?.reset();
            this.codeReader = null;

            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }

            const video = this.$refs.video;

            if (video) {
                video.srcObject = null;
            }
        },

        // Alpine calls this automatically once this component's root element
        // is removed from the DOM (e.g. when the action's modal unmounts
        // after a successful submission) - this is the one cleanup path
        // that's guaranteed to run regardless of *how* the modal closed, so
        // it's what we rely on primarily. The `modal-closed` /
        // `close-modal-quietly` window listeners in the Blade view exist on
        // top of this only to turn the camera off a little earlier, while
        // the closing animation is still playing.
        destroy() {
            this.stopCamera();
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
