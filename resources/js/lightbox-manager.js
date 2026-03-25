/**
 * LightboxManager
 * Fullscreen image carousel with keyboard navigation and swipe support.
 * Vanilla JS — no dependencies.
 *
 * @package developerawam/wire-media
 */
class LightboxManager {
    /**
     * @param {Object[]} files   Array of file metadata objects from the package
     * @param {number}   index   Initial index to open
     * @param {Function} onEdit  Callback(index) when "Edit" button is clicked
     */
    constructor(files, index, onEdit = null) {
        this.files   = files.filter(f => f.is_image);
        this.allFiles = files;
        this.onEdit  = onEdit;

        // Map original index → lightbox index (images only)
        this.indexMap = [];
        files.forEach((f, i) => { if (f.is_image) this.indexMap.push(i); });

        // Find lightbox index from original index
        const lbIdx = this.indexMap.indexOf(index);
        this.current = lbIdx >= 0 ? lbIdx : 0;

        this._swipeStartX = null;
        this._bound = {};
        this._el = null;
        this._render();
        this._bindKeys();
        this._preload();

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    // ── Build DOM ───────────────────────────────────────────────────────────

    _render() {
        const el = document.createElement('div');
        el.id        = 'da-lightbox';
        el.className = 'da-lb';
        el.innerHTML = `
            <div class="da-lb__backdrop"></div>
            <div class="da-lb__shell">

                <!-- Header -->
                <div class="da-lb__header">
                    <span class="da-lb__counter"></span>
                    <span class="da-lb__filename"></span>
                    <div class="da-lb__header-actions">
                        <button class="da-lb__btn" id="da-lb-edit" title="Edit image">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <a class="da-lb__btn" id="da-lb-download" title="Download" download>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </a>
                        <button class="da-lb__btn da-lb__btn--close" id="da-lb-close" title="Close (Esc)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Stage -->
                <div class="da-lb__stage" id="da-lb-stage">
                    <button class="da-lb__nav da-lb__nav--prev" id="da-lb-prev" title="Previous (←)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>

                    <div class="da-lb__img-wrap" id="da-lb-imgwrap">
                        <div class="da-lb__spinner" id="da-lb-spinner">
                            <div class="da-lb__spin-ring"></div>
                        </div>
                        <img class="da-lb__img" id="da-lb-img" alt="" draggable="false">
                    </div>

                    <button class="da-lb__nav da-lb__nav--next" id="da-lb-next" title="Next (→)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>

                <!-- Thumbnail strip -->
                <div class="da-lb__strip" id="da-lb-strip"></div>

                <!-- Zoom controls -->
                <div class="da-lb__zoom-bar">
                    <button class="da-lb__zoom-btn" id="da-lb-zout" title="Zoom out (-)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                    </button>
                    <span class="da-lb__zoom-val" id="da-lb-zval">100%</span>
                    <button class="da-lb__zoom-btn" id="da-lb-zin" title="Zoom in (+)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                    </button>
                    <button class="da-lb__zoom-btn" id="da-lb-zreset" title="Reset zoom (0)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(el);
        this._el = el;
        this._rebindUI();
        this._buildStrip();
        this._showSlide(this.current);
    }

    _rebindUI() {
        const $ = id => this._el.querySelector('#' + id);

        $('da-lb-close').onclick = () => this.destroy();
        $('da-lb-prev').onclick  = () => this.prev();
        $('da-lb-next').onclick  = () => this.next();
        $('da-lb-edit').onclick  = () => {
            if (this.onEdit) this.onEdit(this.indexMap[this.current]);
            this.destroy();
        };
        $('da-lb-zin').onclick    = () => this._lbZoom(0.2);
        $('da-lb-zout').onclick   = () => this._lbZoom(-0.2);
        $('da-lb-zreset').onclick = () => this._lbZoomSet(1);

        // Backdrop click closes
        this._el.querySelector('.da-lb__backdrop').onclick = () => this.destroy();

        // Swipe support
        const stage = $('da-lb-stage');
        stage.addEventListener('touchstart', e => { this._swipeStartX = e.touches[0].clientX; }, { passive: true });
        stage.addEventListener('touchend',   e => {
            if (this._swipeStartX === null) return;
            const dx = e.changedTouches[0].clientX - this._swipeStartX;
            if (Math.abs(dx) > 50) dx < 0 ? this.next() : this.prev();
            this._swipeStartX = null;
        });

        // Wheel zoom on image
        $('da-lb-imgwrap').addEventListener('wheel', e => {
            e.preventDefault();
            this._lbZoom(e.deltaY < 0 ? 0.1 : -0.1);
        }, { passive: false });
    }

    _buildStrip() {
        const strip = this._el.querySelector('#da-lb-strip');
        strip.innerHTML = '';
        this.files.forEach((f, i) => {
            const thumb = document.createElement('button');
            thumb.className = 'da-lb__thumb' + (i === this.current ? ' da-lb__thumb--active' : '');
            thumb.style.backgroundImage = `url('${f.url}')`;
            thumb.title    = f.original_name;
            thumb.onclick  = () => this._showSlide(i);
            strip.appendChild(thumb);
        });
    }

    _showSlide(idx) {
        if (!this.files.length) return;
        this.current = ((idx % this.files.length) + this.files.length) % this.files.length;
        this._lbZoomSet(1, false);

        const file    = this.files[this.current];
        const img     = this._el.querySelector('#da-lb-img');
        const spinner = this._el.querySelector('#da-lb-spinner');

        // Show spinner while loading
        img.style.opacity = '0';
        spinner.style.display = 'flex';

        img.onload = () => {
            spinner.style.display = 'none';
            img.style.opacity     = '1';
        };
        img.src = file.url;
        img.alt = file.original_name;

        // Update header
        this._el.querySelector('.da-lb__counter').textContent  =
            `${this.current + 1} / ${this.files.length}`;
        this._el.querySelector('.da-lb__filename').textContent = file.original_name;

        // Update download link
        const dl = this._el.querySelector('#da-lb-download');
        dl.href     = file.url;
        dl.download = file.original_name;

        // Nav visibility
        this._el.querySelector('#da-lb-prev').style.opacity = this.files.length > 1 ? '1' : '0';
        this._el.querySelector('#da-lb-next').style.opacity = this.files.length > 1 ? '1' : '0';

        // Thumb strip active state
        this._el.querySelectorAll('.da-lb__thumb').forEach((t, i) => {
            t.classList.toggle('da-lb__thumb--active', i === this.current);
        });

        // Scroll active thumb into view
        const activeThumb = this._el.querySelectorAll('.da-lb__thumb')[this.current];
        activeThumb?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }

    _preload() {
        // Preload adjacent images
        [-1, 1].forEach(offset => {
            const idx = (this.current + offset + this.files.length) % this.files.length;
            if (this.files[idx]) {
                const preImg = new Image();
                preImg.src   = this.files[idx].url;
            }
        });
    }

    // ── Lightbox-level zoom (CSS transform, not canvas) ─────────────────────

    _lbZoomSet(val, redraw = true) {
        this._lbZoomVal = Math.max(0.5, Math.min(5, val));
        if (redraw) {
            this._el.querySelector('#da-lb-img').style.transform = `scale(${this._lbZoomVal})`;
            this._el.querySelector('#da-lb-zval').textContent     = Math.round(this._lbZoomVal * 100) + '%';
        } else {
            this._el.querySelector('#da-lb-img').style.transform = 'scale(1)';
            this._el.querySelector('#da-lb-zval').textContent    = '100%';
            this._lbZoomVal = 1;
        }
    }

    _lbZoom(delta) {
        this._lbZoomSet((this._lbZoomVal || 1) + delta);
    }

    // ── Navigation ──────────────────────────────────────────────────────────

    next() { this._showSlide(this.current + 1); this._preload(); }
    prev() { this._showSlide(this.current - 1); this._preload(); }

    // ── Keyboard ────────────────────────────────────────────────────────────

    _bindKeys() {
        this._bound.keydown = e => {
            switch (e.key) {
                case 'ArrowRight': case 'ArrowDown':  this.next(); break;
                case 'ArrowLeft':  case 'ArrowUp':    this.prev(); break;
                case 'Escape':     this.destroy(); break;
                case '+': case '=': this._lbZoom(0.2); break;
                case '-':           this._lbZoom(-0.2); break;
                case '0':           this._lbZoomSet(1); break;
            }
        };
        document.addEventListener('keydown', this._bound.keydown);
    }

    // ── Destroy ─────────────────────────────────────────────────────────────

    destroy() {
        document.removeEventListener('keydown', this._bound.keydown);
        document.body.style.overflow = '';
        this._el?.remove();
        this._el = null;
    }
}

if (typeof window !== 'undefined') window.LightboxManager = LightboxManager;
