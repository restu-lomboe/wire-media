/**
 * LightboxManager
 * Fullscreen file viewer with keyboard navigation, swipe support
 * and a split view: file preview on the left, file details panel on the right.
 * Supports images (<img>), PDFs (<iframe>) and other documents (icon placeholder).
 * Vanilla JS — no dependencies.
 *
 * @package developerawam/wire-media
 */
class LightboxManager {
    /**
     * @param {Object[]} files     Array of file metadata objects from the package
     * @param {number}   index     Initial index to open
     * @param {Function} onEdit    Callback(index) when "Edit" button is clicked
     * @param {Object}   labels    Optional translated labels for the details panel
     */
    constructor(files, index, onEdit = null, labels = {}) {
        this.files   = files;
        this.allFiles = files;
        this.onEdit  = onEdit;
        this.labels  = Object.assign({
            fileDetails: 'File Details',
            name: 'File Name',
            type: 'Type',
            size: 'Size',
            disk: 'Disk',
            path: 'Path',
            uploaded: 'Uploaded',
            status: 'Status',
            edit: 'Edit image',
            badgeNew: 'New',
            badgeExisting: 'Existing',
            noPreview: 'No preview available for this file type',
        }, labels);

        // Map original index → carousel index (all files)
        this.indexMap = [];
        files.forEach((f, i) => { this.indexMap.push(i); });

        // Find carousel index from original index
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
                        <button class="da-lb__btn" id="da-lb-edit" title="${this.labels.edit}">
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
                    <div class="da-lb__image-stage">
                        <button class="da-lb__nav da-lb__nav--prev" id="da-lb-prev" title="Previous (←)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>

                        <div class="da-lb__img-wrap" id="da-lb-imgwrap">
                            <div class="da-lb__spinner" id="da-lb-spinner">
                                <div class="da-lb__spin-ring"></div>
                            </div>
                            <div class="da-lb__preview" id="da-lb-preview"></div>
                        </div>

                        <button class="da-lb__nav da-lb__nav--next" id="da-lb-next" title="Next (→)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>

                        <!-- Zoom controls (images only) -->
                        <div class="da-lb__zoom-bar" id="da-lb-zoombar">
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

                    <aside class="da-lb__details" id="da-lb-details">
                        <div class="da-lb__details-head">
                            <span class="da-lb__details-title" id="da-lb-dname"></span>
                            <span class="da-badge da-badge--inline da-lb__details-badge" id="da-lb-dbadge"></span>
                        </div>
                        <dl class="da-lb__details-list">
                            <div class="da-lb__details-item">
                                <dt>${this.labels.type}</dt>
                                <dd id="da-lb-dtype"></dd>
                            </div>
                            <div class="da-lb__details-item">
                                <dt>${this.labels.size}</dt>
                                <dd id="da-lb-dsize"></dd>
                            </div>
                            <div class="da-lb__details-item">
                                <dt>${this.labels.disk}</dt>
                                <dd id="da-lb-ddisk"></dd>
                            </div>
                            <div class="da-lb__details-item">
                                <dt>${this.labels.path}</dt>
                                <dd id="da-lb-dpath" class="da-lb-mono"></dd>
                            </div>
                            <div class="da-lb__details-item">
                                <dt>${this.labels.uploaded}</dt>
                                <dd id="da-lb-duploaded"></dd>
                            </div>
                            <div class="da-lb__details-item">
                                <dt>${this.labels.status}</dt>
                                <dd id="da-lb-dstatus" class="da-lb-mono"></dd>
                            </div>
                        </dl>
                    </aside>
                </div>

                <!-- Thumbnail strip -->
                <div class="da-lb__strip" id="da-lb-strip"></div>
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

        // Wheel zoom on image wrap only (skipped for pdf/doc iframes via closest())
        const wrap = $('da-lb-imgwrap');
        wrap.addEventListener('wheel', e => {
            if (e.target.closest('iframe')) return;
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
            thumb.title    = f.original_name;

            if (f.is_image) {
                thumb.style.backgroundImage = `url('${f.url}')`;
            } else {
                thumb.classList.add('da-lb__thumb--doc');
                thumb.textContent = this._extLabel(f);
            }

            thumb.onclick = () => this._showSlide(i);
            strip.appendChild(thumb);
        });
    }

    _showSlide(idx) {
        if (!this.files.length) return;
        this.current = ((idx % this.files.length) + this.files.length) % this.files.length;
        this._lbZoomSet(1, false);

        const file    = this.files[this.current];
        this._renderPreview(file);

        // Update header
        this._el.querySelector('.da-lb__counter').textContent  =
            `${this.current + 1} / ${this.files.length}`;
        this._el.querySelector('.da-lb__filename').textContent = file.original_name;

        // Update download link
        const dl = this._el.querySelector('#da-lb-download');
        dl.href     = file.url;
        dl.download = file.original_name;

        // Nav visibility
        const showNav = this.files.length > 1;
        this._el.querySelector('#da-lb-prev').style.opacity = showNav ? '1' : '0';
        this._el.querySelector('#da-lb-next').style.opacity = showNav ? '1' : '0';

        // Edit button: images only
        this._el.querySelector('#da-lb-edit').style.display = file.is_image ? '' : 'none';

        // Zoom bar: images only
        this._el.querySelector('#da-lb-zoombar').style.display = file.is_image ? '' : 'none';

        // Thumb strip active state
        this._el.querySelectorAll('.da-lb__thumb').forEach((t, i) => {
            t.classList.toggle('da-lb__thumb--active', i === this.current);
        });

        // Scroll active thumb into view
        const activeThumb = this._el.querySelectorAll('.da-lb__thumb')[this.current];
        activeThumb?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });

        this._renderDetails(file);
    }

    _renderPreview(file) {
        const preview = this._el.querySelector('#da-lb-preview');
        const spinner = this._el.querySelector('#da-lb-spinner');

        // Clean up any previous iframe
        preview.querySelector('iframe')?.remove();

        if (file.is_image) {
            spinner.style.display = 'flex';
            preview.innerHTML = '';
            const img = document.createElement('img');
            img.className   = 'da-lb__img';
            img.alt         = file.original_name;
            img.draggable   = false;
            img.onload      = () => { spinner.style.display = 'none'; img.style.opacity = '1'; };
            img.onerror     = () => { spinner.style.display = 'none'; this._renderNoPreview(preview, file); };
            img.style.opacity = '0';
            img.src = file.url;
            preview.appendChild(img);
        } else if (file.is_pdf) {
            spinner.style.display = 'flex';
            preview.innerHTML = '';
            const frame = document.createElement('iframe');
            frame.className = 'da-lb__pdf';
            frame.src  = file.url + '#toolbar=0&navpanes=0';
            frame.title = file.original_name;
            frame.onload = () => { spinner.style.display = 'none'; };
            frame.onerror = () => { spinner.style.display = 'none'; this._renderNoPreview(preview, file); };
            preview.appendChild(frame);
        } else {
            this._renderNoPreview(preview, file);
        }
    }

    _renderNoPreview(preview, file) {
        preview.innerHTML = `
            <div class="da-lb__nopreview">
                <span class="da-lb__nopreview-icon">${this._extLabel(file)}</span>
                <span class="da-lb__nopreview-text">${this.labels.noPreview}</span>
            </div>
        `;
    }

    _extLabel(file) {
        return (file.extension || '').toUpperCase() || 'FILE';
    }

    _renderDetails(file) {
        const set = (id, val) => { this._el.querySelector('#' + id).textContent = val; };

        set('da-lb-dname', file.original_name);
        set('da-lb-dtype', (file.mime || '') + (file.extension ? ' (' + file.extension.toUpperCase() + ')' : ''));
        set('da-lb-dsize',
            file.size_human
                ? `${file.size_human} (${Number(file.size || 0).toLocaleString()} bytes)`
                : Number(file.size || 0).toLocaleString());
        set('da-lb-ddisk', file.disk || '');
        set('da-lb-dpath', file.path || '');
        set('da-lb-duploaded', file.uploaded_at ? this._formatDate(file.uploaded_at) : '');
        set('da-lb-dstatus', file.path || '');

        const badge = this._el.querySelector('#da-lb-dbadge');
        const isExisting = Boolean(file.is_existing);
        badge.textContent = isExisting ? this.labels.badgeExisting : this.labels.badgeNew;
        badge.classList.toggle('da-badge--ex', isExisting);
        badge.classList.toggle('da-badge--new', !isExisting);
    }

    _formatDate(iso) {
        const d = new Date(iso);
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    }

    _preload() {
        // Preload adjacent images
        [-1, 1].forEach(offset => {
            const idx = (this.current + offset + this.files.length) % this.files.length;
            const f = this.files[idx];
            if (f && f.is_image) {
                const preImg = new Image();
                preImg.src   = f.url;
            }
        });
    }

    // ── Lightbox-level zoom (CSS transform, not canvas) ─────────────────────

    _lbZoomSet(val, redraw = true) {
        this._lbZoomVal = Math.max(0.5, Math.min(5, val));
        const img = this._el.querySelector('#da-lb-img');
        if (img) img.style.transform = `scale(${redraw ? this._lbZoomVal : 1})`;
        this._el.querySelector('#da-lb-zval').textContent = redraw
            ? Math.round(this._lbZoomVal * 100) + '%'
            : '100%';
        if (!redraw) this._lbZoomVal = 1;
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