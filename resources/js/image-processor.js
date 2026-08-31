/**
 * ImageProcessor
 * Vanilla JS canvas-based image editing engine.
 * Handles: zoom, rotate, flip H/V, free-draw crop selection.
 * No external dependencies.
 *
 * @package developerawam/wire-media
 */
class ImageProcessor {
    /**
     * @param {HTMLCanvasElement} canvas
     * @param {string} src  URL or data-URL of the image to edit
     */
    constructor(canvas, src) {
        this.canvas = canvas;
        this.ctx = canvas.getContext("2d");
        this.src = src;
        this.img = null;

        // Maximum output dimension (in pixels) for exported images.
        // Prevents memory blow-ups when editing very large photos.
        this.MAX_EXPORT = 4096;

        // Transform state
        this.zoom = 1;
        this.rotation = 0; // degrees: 0 | 90 | 180 | 270
        this.flipH = false;
        this.flipV = false;

        // Library filter state (live preview)
        this.filter = null;   // CSS filter string, e.g. 'grayscale(50%)'
        this.sharpen = 0;     // sharpen intensity 0..100

        // Crop state
        this.cropMode = false;
        this.cropStart = null; // {x, y} in canvas coords
        this.cropRect = null; // {x, y, w, h} in canvas coords
        this.isDragging = false;

        // Pan state
        this.panMode = false;
        this.panStart = null;
        this.panOffset = { x: 0, y: 0 };

        this._bound = {};
        this._ready = false;
        this._loadImage(src);
    }

    // ── Load ────────────────────────────────────────────────────────────────

    _loadImage(src) {
        const img = new Image();
        // Only request CORS access for cross-origin URLs. Setting
        // crossOrigin on same-origin URLs can poison the canvas on some setups.
        try {
            if (new URL(src, window.location.href).origin !== window.location.origin) {
                img.crossOrigin = "anonymous";
            }
        } catch (e) {
            img.crossOrigin = "anonymous";
        }
        img.onload = () => {
            this.img = img;
            this._ready = true;
            this._fitCanvas();
            this.redraw();
            this._bindEvents();
            this.canvas.dispatchEvent(new CustomEvent("ip:ready"));
        };
        img.onerror = () => {
            this.canvas.dispatchEvent(new CustomEvent("ip:error", { detail: { src } }));
        };
        img.src = src;
    }

    _fitCanvas() {
        // Fill the container, keep aspect ratio
        const maxW = this.canvas.parentElement?.clientWidth || 800;
        const maxH = this.canvas.parentElement?.clientHeight || 600;

        const ratio = Math.min(
            maxW / this.img.naturalWidth,
            maxH / this.img.naturalHeight,
            1,
        );
        this.canvas.width = Math.round(this.img.naturalWidth * ratio);
        this.canvas.height = Math.round(this.img.naturalHeight * ratio);
        this.baseW = this.canvas.width;
        this.baseH = this.canvas.height;
    }

    // ── Render ──────────────────────────────────────────────────────────────

    redraw() {
        if (!this._ready) return;
        const { ctx, canvas, zoom, rotation, flipH, flipV, panOffset, filter } = this;
        const w = canvas.width,
            h = canvas.height;

        ctx.clearRect(0, 0, w, h);
        ctx.save();

        // Live library filter preview (grayscale / blur)
        if (filter) ctx.filter = filter;

        // Center + pan + zoom
        ctx.translate(w / 2 + panOffset.x, h / 2 + panOffset.y);
        ctx.scale((flipH ? -1 : 1) * zoom, (flipV ? -1 : 1) * zoom);
        ctx.rotate((rotation * Math.PI) / 180);

        // Draw image centered
        ctx.drawImage(this.img, -w / 2, -h / 2, w, h);
        ctx.restore();

        // Sharpen live preview (convolution on displayed pixels)
        if (this.sharpen > 0) {
            this._applySharpen(this.canvas, this.sharpen / 100);
        }

        // Draw crop overlay
        if (this.cropMode && this.cropRect) {
            this._drawCropOverlay();
        }
    }

    /**
     * Apply a 3×3 convolution sharpen kernel to the given canvas pixels,
     * blended with the original by `strength` (0..1).
     * @param {HTMLCanvasElement} target
     * @param {number} strength
     */
    _applySharpen(target, strength) {
        const ctx = target.getContext("2d");
        const w = target.width,
            h = target.height;
        const imgData = ctx.getImageData(0, 0, w, h);
        const src = imgData.data;
        const out = ctx.createImageData(w, h);
        const dst = out.data;

        const kernel = [0, -1, 0, -1, 5, -1, 0, -1, 0];
        const t = Math.max(0, Math.min(1, strength));

        for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
                const centerIdx = (y * w + x) * 4;

                for (let c = 0; c < 3; c++) {
                    let acc = 0;
                    for (let ky = -1; ky <= 1; ky++) {
                        for (let kx = -1; kx <= 1; kx++) {
                            const px = Math.min(w - 1, Math.max(0, x + kx));
                            const py = Math.min(h - 1, Math.max(0, y + ky));
                            const idx = (py * w + px) * 4 + c;
                            acc += src[idx] * kernel[(ky + 1) * 3 + (kx + 1)];
                        }
                    }
                    const oIdx = centerIdx + c;
                    dst[oIdx] = Math.round(src[oIdx] * (1 - t) + acc * t);
                }
                dst[centerIdx + 3] = src[centerIdx + 3];
            }
        }

        ctx.putImageData(out, 0, 0);
    }

    _drawCropOverlay() {
        const { ctx, canvas, cropRect: r } = this;
        const w = canvas.width,
            h = canvas.height;

        // Dark mask
        ctx.save();
        ctx.fillStyle = "rgba(0,0,0,0.52)";
        ctx.fillRect(0, 0, w, h);

        // Clear crop area (show original)
        ctx.clearRect(r.x, r.y, r.w, r.h);

        // Re-draw image only inside crop (so it shows through)
        ctx.save();
        ctx.beginPath();
        ctx.rect(r.x, r.y, r.w, r.h);
        ctx.clip();

        const { zoom, rotation, flipH, flipV, panOffset, filter } = this;
        ctx.translate(w / 2 + panOffset.x, h / 2 + panOffset.y);
        ctx.scale((flipH ? -1 : 1) * zoom, (flipV ? -1 : 1) * zoom);
        ctx.rotate((rotation * Math.PI) / 180);

        if (filter) ctx.filter = filter;

        ctx.drawImage(this.img, -w / 2, -h / 2, w, h);
        ctx.restore();

        // Border + handles
        ctx.strokeStyle = "#6366f1";
        ctx.lineWidth = 2;
        ctx.strokeRect(r.x, r.y, r.w, r.h);

        // Rule-of-thirds grid
        ctx.strokeStyle = "rgba(99,102,241,0.4)";
        ctx.lineWidth = 1;
        for (let i = 1; i <= 2; i++) {
            ctx.beginPath();
            ctx.moveTo(r.x + (r.w / 3) * i, r.y);
            ctx.lineTo(r.x + (r.w / 3) * i, r.y + r.h);
            ctx.stroke();

            ctx.beginPath();
            ctx.moveTo(r.x, r.y + (r.h / 3) * i);
            ctx.lineTo(r.x + r.w, r.y + (r.h / 3) * i);
            ctx.stroke();
        }

        // Corner handles
        const hs = 8;
        ctx.fillStyle = "#6366f1";
        [
            [r.x, r.y],
            [r.x + r.w, r.y],
            [r.x, r.y + r.h],
            [r.x + r.w, r.y + r.h],
        ].forEach(([cx, cy]) => {
            ctx.fillRect(cx - hs / 2, cy - hs / 2, hs, hs);
        });

        ctx.restore();
    }

    // ── Mouse / Touch events ────────────────────────────────────────────────

    _bindEvents() {
        const c = this.canvas;

        this._bound.mousedown = (e) => this._onPointerDown(this._pos(e));
        this._bound.mousemove = (e) => this._onPointerMove(this._pos(e));
        this._bound.mouseup = (e) => this._onPointerUp();

        this._bound.touchstart = (e) => {
            e.preventDefault();
            this._onPointerDown(this._pos(e.touches[0]));
        };
        this._bound.touchmove = (e) => {
            e.preventDefault();
            this._onPointerMove(this._pos(e.touches[0]));
        };
        this._bound.touchend = (e) => this._onPointerUp();

        this._bound.wheel = (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            this.setZoom(Math.max(0.1, Math.min(5, this.zoom + delta)));
        };

        c.addEventListener("mousedown", this._bound.mousedown);
        c.addEventListener("mousemove", this._bound.mousemove);
        c.addEventListener("mouseup", this._bound.mouseup);
        c.addEventListener("mouseleave", this._bound.mouseup);
        c.addEventListener("touchstart", this._bound.touchstart, {
            passive: false,
        });
        c.addEventListener("touchmove", this._bound.touchmove, {
            passive: false,
        });
        c.addEventListener("touchend", this._bound.touchend);
        c.addEventListener("wheel", this._bound.wheel, { passive: false });
    }

    _unbindEvents() {
        const c = this.canvas;
        Object.entries(this._bound).forEach(([ev, fn]) =>
            c.removeEventListener(ev, fn),
        );
    }

    _pos(e) {
        const rect = this.canvas.getBoundingClientRect();
        return {
            x: (e.clientX - rect.left) * (this.canvas.width / rect.width),
            y: (e.clientY - rect.top) * (this.canvas.height / rect.height),
        };
    }

    _onPointerDown(pos) {
        if (this.cropMode) {
            this.cropStart = pos;
            this.cropRect = { x: pos.x, y: pos.y, w: 0, h: 0 };
            this.isDragging = true;
        } else {
            this.panStart = pos;
            this.isDragging = true;
        }
    }

    _onPointerMove(pos) {
        if (!this.isDragging) return;

        if (this.cropMode && this.cropStart) {
            this.cropRect = {
                x: Math.min(pos.x, this.cropStart.x),
                y: Math.min(pos.y, this.cropStart.y),
                w: Math.abs(pos.x - this.cropStart.x),
                h: Math.abs(pos.y - this.cropStart.y),
            };
        } else if (this.panStart) {
            this.panOffset.x += pos.x - this.panStart.x;
            this.panOffset.y += pos.y - this.panStart.y;
            this.panStart = pos;
        }
        this.redraw();
    }

    _onPointerUp() {
        this.isDragging = false;
        this.panStart = null;
        if (
            this.cropMode &&
            this.cropRect &&
            this.cropRect.w > 5 &&
            this.cropRect.h > 5
        ) {
            this.canvas.dispatchEvent(
                new CustomEvent("ip:cropselected", {
                    detail: { rect: this.cropRect },
                }),
            );
        }
    }

    // ── Public API ──────────────────────────────────────────────────────────

    setZoom(value) {
        this.zoom = Math.max(0.1, Math.min(5, value));
        this.redraw();
        this.canvas.dispatchEvent(
            new CustomEvent("ip:zoom", { detail: { zoom: this.zoom } }),
        );
    }

    zoomIn() {
        this.setZoom(this.zoom + 0.15);
    }
    zoomOut() {
        this.setZoom(this.zoom - 0.15);
    }
    zoomReset() {
        this.zoom = 1;
        this.panOffset = { x: 0, y: 0 };
        this.redraw();
    }

    rotate(deg = 90) {
        this.rotation = (this.rotation + deg + 360) % 360;
        this.redraw();
    }

    flipHorizontal() {
        this.flipH = !this.flipH;
        this.redraw();
    }
    flipVertical() {
        this.flipV = !this.flipV;
        this.redraw();
    }

    enableCrop() {
        this.cropMode = true;
        this.cropRect = null;
        this.canvas.style.cursor = "crosshair";
        this.redraw();
    }

    disableCrop() {
        this.cropMode = false;
        this.cropRect = null;
        this.canvas.style.cursor = "";
        this.redraw();
    }

    resetAll() {
        this.zoom = 1;
        this.rotation = 0;
        this.flipH = false;
        this.flipV = false;
        this.panOffset = { x: 0, y: 0 };
        this.cropMode = false;
        this.cropRect = null;
        this.filter = null;
        this.sharpen = 0;
        this.canvas.style.cursor = "";
        this.redraw();
    }

    /**
     * Set a live CSS filter preview (e.g. `grayscale(50%)`, `blur(3px)`).
     * @param {string|null} cssFilter
     */
    setFilter(cssFilter) {
        this.filter = cssFilter || null;
        this.redraw();
    }

    /**
     * Set live sharpen preview intensity (0..100).
     * @param {number} value
     */
    setSharpen(value) {
        this.sharpen = Math.max(0, Math.min(100, value));
        this.redraw();
    }

    /**
     * Effective scale factor that maps the fitted canvas coordinates back to
     * the image's natural resolution, capped so the longest export side never
     * exceeds MAX_EXPORT (prevents memory blow-ups).
     * @returns {number}
     */
    _getScale() {
        if (!this.img || !this.img.naturalWidth) return 1;
        const maxDim = Math.max(this.img.naturalWidth, this.img.naturalHeight);
        const cap = Math.min(1, this.MAX_EXPORT / maxDim);
        const fitScale = this.canvas.width / this.img.naturalWidth;
        return (1 / fitScale) * cap;
    }

    /**
     * Clamp dimensions so the longest side never exceeds MAX_EXPORT.
     * @param {number} w
     * @param {number} h
     * @returns {{w: number, h: number}}
     */
    _exportDims(w, h) {
        const ratio = Math.min(1, this.MAX_EXPORT / Math.max(w, h));
        return {
            w: Math.max(1, Math.round(w * ratio)),
            h: Math.max(1, Math.round(h * ratio)),
        };
    }

    /**
     * Apply crop: renders the current crop selection to a new canvas at
     * (up to) natural resolution and returns a data-URL (PNG).
     * @returns {string|null}
     */
    applyCrop() {
        if (!this.cropRect || this.cropRect.w < 2 || this.cropRect.h < 2)
            return null;

        const r = this.cropRect;
        const effScale = this._getScale();
        const nr = {
            x: r.x * effScale,
            y: r.y * effScale,
            w: r.w * effScale,
            h: r.h * effScale,
        };
        const dims = this._exportDims(nr.w, nr.h);
        const outScale = dims.w / nr.w;

        const out = document.createElement("canvas");
        out.width = dims.w;
        out.height = dims.h;
        const octx = out.getContext("2d");

        // Render clean (tanpa overlay) ke temp canvas pada resolusi natural
        const temp = document.createElement("canvas");
        temp.width = Math.round(this.canvas.width * effScale);
        temp.height = Math.round(this.canvas.height * effScale);
        const tctx = temp.getContext("2d");
        const { zoom, rotation, flipH, flipV, panOffset } = this;
        const tw = temp.width,
            th = temp.height;

        tctx.save();
        tctx.translate(tw / 2 + panOffset.x * effScale, th / 2 + panOffset.y * effScale);
        tctx.scale((flipH ? -1 : 1) * zoom * effScale, (flipV ? -1 : 1) * zoom * effScale);
        tctx.rotate((rotation * Math.PI) / 180);
        tctx.drawImage(this.img, -this.canvas.width / 2, -this.canvas.height / 2, this.canvas.width, this.canvas.height);
        tctx.restore();

        // Crop dari temp canvas yang bersih
        octx.drawImage(
            temp,
            nr.x * outScale, nr.y * outScale, nr.w * outScale, nr.h * outScale,
            0, 0, out.width, out.height,
        );

        this.disableCrop();
        return out.toDataURL("image/png");
    }

    /**
     * Export the full current view (with zoom/rotate/flip) as a data-URL at
     * (up to) natural resolution.
     * @param {number} [scale=1]  — additional output multiplier (>1 = bigger)
     * @param {string} [mime]     — output MIME (jpeg/webp/gif kept, else PNG)
     * @param {number|null} [quality=null] — 0..1 for JPEG/WebP (ignored for PNG)
     * @returns {string|null}
     */
    exportDataURL(scale = 1, mime, quality = null) {
        if (!this.img || !this.img.naturalWidth) return null;

        const exportMime =
            mime === "image/jpeg" || mime === "image/webp" || mime === "image/gif"
                ? mime
                : "image/png";
        const effScale = this._getScale() * (scale || 1);

        let baseW = this.canvas.width * effScale * this.zoom;
        let baseH = this.canvas.height * effScale * this.zoom;
        if (this.rotation % 180 !== 0) {
            const t = baseW;
            baseW = baseH;
            baseH = t;
        }
        const dims = this._exportDims(baseW, baseH);
        const outScale = dims.w / baseW;

        const out = document.createElement("canvas");
        out.width = dims.w;
        out.height = dims.h;
        const octx = out.getContext("2d");
        const { zoom, rotation, flipH, flipV, panOffset } = this;

        octx.save();
        octx.translate(
            out.width / 2 + panOffset.x * effScale * outScale,
            out.height / 2 + panOffset.y * effScale * outScale,
        );
        octx.scale(
            (flipH ? -1 : 1) * zoom * effScale * outScale,
            (flipV ? -1 : 1) * zoom * effScale * outScale,
        );
        octx.rotate((rotation * Math.PI) / 180);

        if (this.filter) octx.filter = this.filter;

        octx.drawImage(
            this.img,
            -this.canvas.width / 2,
            -this.canvas.height / 2,
            this.canvas.width,
            this.canvas.height,
        );
        octx.restore();

        if (this.sharpen > 0) {
            this._applySharpen(out, this.sharpen / 100);
        }

        if (quality !== null && (exportMime === 'image/jpeg' || exportMime === 'image/webp')) {
            return out.toDataURL(exportMime, Math.max(0, Math.min(1, quality)));
        }

        return out.toDataURL(exportMime);
    }

    /**
     * Convert a data-URL to a File object ready for upload.
     * @param {string} dataURL
     * @param {string} filename
     * @returns {File}
     */
    static dataURLtoFile(dataURL, filename) {
        const [header, data] = dataURL.split(",");
        const mime = header.match(/:(.*?);/)[1];
        const bytes = atob(data);
        const arr = new Uint8Array(bytes.length);
        for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
        return new File([arr], filename, { type: mime });
    }

    destroy() {
        this._unbindEvents();
        this.img = null;
        this._ready = false;
    }
}

// Export for Alpine.js / module usage
if (typeof window !== "undefined") window.ImageProcessor = ImageProcessor;
