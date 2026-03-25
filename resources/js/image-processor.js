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

        // Transform state
        this.zoom = 1;
        this.rotation = 0; // degrees: 0 | 90 | 180 | 270
        this.flipH = false;
        this.flipV = false;

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
        img.crossOrigin = "anonymous";
        img.onload = () => {
            this.img = img;
            this._ready = true;
            this._fitCanvas();
            this.redraw();
            this._bindEvents();
            this.canvas.dispatchEvent(new CustomEvent("ip:ready"));
        };
        img.onerror = () => {
            this.canvas.dispatchEvent(new CustomEvent("ip:error"));
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
        const { ctx, canvas, zoom, rotation, flipH, flipV, panOffset } = this;
        const w = canvas.width,
            h = canvas.height;

        ctx.clearRect(0, 0, w, h);
        ctx.save();

        // Center + pan + zoom
        ctx.translate(w / 2 + panOffset.x, h / 2 + panOffset.y);
        ctx.scale((flipH ? -1 : 1) * zoom, (flipV ? -1 : 1) * zoom);
        ctx.rotate((rotation * Math.PI) / 180);

        // Draw image centered
        ctx.drawImage(this.img, -w / 2, -h / 2, w, h);
        ctx.restore();

        // Draw crop overlay
        if (this.cropMode && this.cropRect) {
            this._drawCropOverlay();
        }
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

        const { zoom, rotation, flipH, flipV, panOffset } = this;
        ctx.translate(w / 2 + panOffset.x, h / 2 + panOffset.y);
        ctx.scale((flipH ? -1 : 1) * zoom, (flipV ? -1 : 1) * zoom);
        ctx.rotate((rotation * Math.PI) / 180);
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
        this.canvas.style.cursor = "";
        this.redraw();
    }

    /**
     * Apply crop: renders the current crop selection to a new canvas
     * and returns a data-URL (PNG).
     * @returns {string|null}
     */
    applyCrop() {
        if (!this.cropRect || this.cropRect.w < 2 || this.cropRect.h < 2)
            return null;

        const r = this.cropRect;
        const out = document.createElement("canvas");
        out.width = r.w;
        out.height = r.h;
        const octx = out.getContext("2d");

        // Render clean (tanpa overlay) dulu ke temp canvas
        const temp = document.createElement("canvas");
        temp.width = this.canvas.width;
        temp.height = this.canvas.height;
        const tctx = temp.getContext("2d");
        const { zoom, rotation, flipH, flipV, panOffset } = this;
        const w = temp.width,
            h = temp.height;

        tctx.save();
        tctx.translate(w / 2 + panOffset.x, h / 2 + panOffset.y);
        tctx.scale((flipH ? -1 : 1) * zoom, (flipV ? -1 : 1) * zoom);
        tctx.rotate((rotation * Math.PI) / 180);
        tctx.drawImage(this.img, -w / 2, -h / 2, w, h);
        tctx.restore();

        // Crop dari temp canvas yang bersih
        octx.drawImage(temp, r.x, r.y, r.w, r.h, 0, 0, r.w, r.h);

        this.disableCrop();
        return out.toDataURL("image/png");
    }

    /**
     * Export the full current view (with zoom/rotate/flip) as PNG data-URL.
     * @param {number} [scale=1]  — set >1 for higher resolution output
     * @returns {string}
     */
    exportDataURL(scale = 1) {
        const out = document.createElement("canvas");
        out.width = this.canvas.width * scale;
        out.height = this.canvas.height * scale;
        const octx = out.getContext("2d");
        const { zoom, rotation, flipH, flipV, panOffset } = this;
        const w = out.width,
            h = out.height;

        octx.save();
        octx.translate(
            w / 2 + panOffset.x * scale,
            h / 2 + panOffset.y * scale,
        );
        octx.scale(
            (flipH ? -1 : 1) * zoom * scale,
            (flipV ? -1 : 1) * zoom * scale,
        );
        octx.rotate((rotation * Math.PI) / 180);
        octx.drawImage(
            this.img,
            -this.canvas.width / 2,
            -this.canvas.height / 2,
            this.canvas.width,
            this.canvas.height,
        );
        octx.restore();

        return out.toDataURL("image/png");
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
