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
    capturesImage = false,
    imageFormat = 'image/jpeg',
    imageQuality = 0.85,
    imageState = null,
}) {
    return {
        state,
        autoSubmit,
        facingMode,
        capturesImage,
        imageFormat,
        imageQuality,
        imageState,
        isInitializing: true,
        hasResult: false,
        errorMessage: null,
        codeReader: null,
        zxing: null,
        stream: null,
        isStarting: false,
        canvasCtx: null,
        rafId: null,

        init() {
            this.hasResult = Boolean(this.state);

            this.startCamera();
        },

        async startCamera() {
            // Guards against ever running two concurrent camera sessions on
            // the same component instance.
            if (this.stream || this.isStarting) {
                return;
            }

            this.isStarting = true;
            this.isInitializing = true;
            this.errorMessage = null;

            try {
                this.zxing = await loadZXing();

                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: {
                        facingMode: { ideal: this.facingMode },
                        // Hints the camera to negotiate something closer to
                        // our square viewport up front, so there's less to
                        // crop out in our own draw loop below (mobile rear
                        // cameras otherwise often start at a native
                        // 9:16-ish ratio).
                        aspectRatio: { ideal: 1 },
                    },
                });

                // `stopCamera()` may have been called (e.g. the user closed
                // the modal) while the getUserMedia() request above was
                // still in flight. If so, `isStarting` was reset to `false`
                // in the meantime - release this now-unwanted stream
                // immediately instead of attaching it, or it would keep the
                // camera on with nothing left to stop it.
                if (!this.isStarting) {
                    stream.getTracks().forEach((track) => track.stop());

                    return;
                }

                this.stream = stream;

                const video = this.$refs.video;
                video.srcObject = this.stream;

                // We call `play()` ourselves as an extra, earlier attempt at
                // starting playback, on top of whatever
                // `decodeFromVideoElementContinuously()` does internally
                // below. This isn't redundant/harmful: both calls happen
                // synchronously, before the browser has had a chance to
                // actually start rendering frames yet, so they're racing to
                // trigger the *same* eventual transition rather than
                // fighting each other - but relying on ZXing's single
                // internal attempt alone turned out to not reliably start
                // playback on every device.
                video.play().catch(() => {
                    // Ignored: ZXing's own `decodeFromVideoElementContinuously()`
                    // call below retries this via its own `canplay` listener.
                });

                // We intentionally do NOT `await` this call: it internally
                // waits for the video's `playing` event before it starts
                // decoding, and we don't want our own camera-visibility
                // logic below to be gated on that.
                this.codeReader = new this.zxing.BrowserMultiFormatReader();
                this.codeReader.decodeFromVideoElementContinuously(
                    video,
                    (result, error) => this.handleDecodeResult(result, error),
                );

                // The visible square is a <canvas> that we draw into
                // ourselves (see startDrawLoop below), not the <video>
                // element directly. Relying on the video's own reported
                // dimensions + CSS `object-fit: cover` looked fine on
                // desktop webcams, but on mobile - especially rear cameras -
                // the stream's actual resolution/aspect ratio can keep
                // changing for a bit after playback starts (autofocus,
                // exposure, or the OS swapping to a higher-quality feed),
                // and every one of those changes was visible as a "flick"
                // once `object-fit` re-applied itself. Drawing to a canvas
                // lets us compute our own centered square crop from
                // `video.videoWidth`/`videoHeight` on *every single frame*,
                // so however many times the underlying stream's dimensions
                // change, the visible square never has to visibly "snap" -
                // it's simply redrawn correctly on the next frame.
                this.startDrawLoop();
            } catch (error) {
                this.handleError(error);
            } finally {
                this.isStarting = false;
            }
        },

        startDrawLoop() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;

            this.canvasCtx ??= canvas.getContext('2d');

            let framesSincePlayRetry = 0;

            const draw = () => {
                if (!this.stream) {
                    return;
                }

                // Safety net: if playback isn't (or is no longer) actually
                // running, keep retrying every ~30 frames instead of just
                // giving up after the initial attempts in `startCamera()`.
                if (video.paused) {
                    if (framesSincePlayRetry === 0) {
                        video.play().catch(() => {});
                    }

                    framesSincePlayRetry = (framesSincePlayRetry + 1) % 30;
                }

                const videoWidth = video.videoWidth;
                const videoHeight = video.videoHeight;

                // `videoWidth`/`videoHeight` become available as soon as the
                // stream's metadata loads, which can happen *before*
                // playback actually starts - drawing (and revealing the
                // canvas) at that point risked capturing a blank frame if
                // `play()` hadn't taken effect yet. Requiring `!video.paused`
                // too means we only ever draw/reveal once there's an actual
                // picture to show.
                if (videoWidth && videoHeight && !video.paused) {
                    const displaySize = Math.round(
                        (canvas.clientWidth || canvas.parentElement.clientWidth || 320) *
                            Math.min(window.devicePixelRatio || 1, 2),
                    );

                    if (canvas.width !== displaySize) {
                        canvas.width = displaySize;
                        canvas.height = displaySize;
                    }

                    // Centered square crop of whatever the video's current
                    // dimensions happen to be, recomputed every frame.
                    const cropSize = Math.min(videoWidth, videoHeight);
                    const cropX = (videoWidth - cropSize) / 2;
                    const cropY = (videoHeight - cropSize) / 2;

                    this.canvasCtx.drawImage(
                        video,
                        cropX,
                        cropY,
                        cropSize,
                        cropSize,
                        0,
                        0,
                        canvas.width,
                        canvas.height,
                    );

                    // The first successfully drawn frame is already a
                    // correctly cropped square - only now do we reveal the
                    // canvas, so the user never sees an unstyled/uncropped
                    // frame or anything resizing into place.
                    this.isInitializing = false;
                }

                this.rafId = requestAnimationFrame(draw);
            };

            this.rafId = requestAnimationFrame(draw);
        },

        stopDrawLoop() {
            if (this.rafId) {
                cancelAnimationFrame(this.rafId);
                this.rafId = null;
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

            if (this.capturesImage) {
                this.imageState = this.captureImage();
            }

            // No reason to keep the camera running once we have a result,
            // whether or not the action is about to auto-submit.
            this.stopCamera();

            if (this.autoSubmit) {
                this.$nextTick(() => this.$wire.callMountedAction());
            }
        },

        captureImage() {
            // The canvas already holds a correctly cropped square frame from
            // the draw loop above (at most one animation frame old), so we
            // capture directly from it rather than the <video> element -
            // this guarantees the image matches exactly what the user saw
            // framed in the viewport when the code was read.
            try {
                return this.$refs.canvas.toDataURL(this.imageFormat, this.imageQuality);
            } catch (error) {
                console.error(error);

                return null;
            }
        },

        rescan() {
            this.hasResult = false;
            this.state = null;
            this.errorMessage = null;

            if (this.capturesImage) {
                this.imageState = null;
            }

            this.startCamera();
        },

        stopCamera() {
            // Cancels any `startCamera()` call that might still be in
            // flight (see the `isStarting` check above), so a stream that
            // finishes resolving *after* this point gets released instead
            // of attached.
            this.isStarting = false;

            this.stopDrawLoop();

            this.codeReader?.reset();
            this.codeReader = null;

            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }

            const video = this.$refs.video;

            if (video) {
                video.pause();
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
