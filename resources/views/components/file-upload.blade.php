@php
    $previewFile =
        $previewIndex !== null && isset($uploadedFiles[$previewIndex]) ? $uploadedFiles[$previewIndex] : null;
    $editorFile =
        $editorOpen && $editorIndex !== null && isset($uploadedFiles[$editorIndex])
            ? $uploadedFiles[$editorIndex]
            : null;
    $imageFiles = collect($uploadedFiles)->filter(fn($f) => $f['is_image'] ?? false)->values();
    $dm = $darkMode ? 'da-dark' : '';
@endphp

<div class="da-wrap {{ $dm }}" x-data="daFileUpload(@js($uploadedFiles), @js($darkMode))" x-init="init()" wire:ignore.self>

    {{-- ══════════════════════════ DROPZONE ══════════════════════════════════ --}}
    @if ($label)
        <label class="da-label">{{ $label }}</label>
    @endif

    <div class="da-dropzone" :class="{ 'da-dropzone--over': isDragging }" @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false" @drop.prevent="handleDrop($event)" @click="$refs.fileInput.click()">
        <div class="da-dropzone__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" width="40" height="40">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
            </svg>
        </div>
        <p class="da-dropzone__primary">{{ trans('wire-media::messages.drop_zone_text') }}</p>
        <p class="da-dropzone__hint">
            @if ($acceptAttribute)
                {{ trans('wire-media::messages.allowed_types') }}: <strong>{{ $acceptAttribute }}</strong> &middot;
            @endif
            {{ trans('wire-media::messages.max_size_hint', ['max' => $maxSizeMb]) }}
            @if ($multiple && $maxFiles > 0)
                &middot; {{ trans('wire-media::messages.max_files_hint', ['max' => $maxFiles]) }}
            @endif
        </p>
        <input x-ref="fileInput" type="file" class="da-hidden-input"
            @if ($multiple) multiple @endif
            @if ($acceptAttribute) accept="{{ $acceptAttribute }}" @endif wire:model="files">
    </div>

    {{-- ── Progress ─────────────────────────────────────────────────────────── --}}
    <div wire:loading wire:target="files" class="da-progress-wrap">
        <div class="da-progress-anim"></div>
        <span class="da-progress-text">{{ trans('wire-media::messages.uploading') }}…</span>
    </div>

    {{-- ── Validation errors ───────────────────────────────────────────────── --}}
    @if (!empty($errors))
        <div class="da-error-box">
            @foreach ($errors as $err)
                <p class="da-error-line">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    {{ $err }}
                </p>
            @endforeach
        </div>
    @endif

    {{-- ══════════════════════════ FILE GRID ══════════════════════════════════ --}}
    @if ($showList && !empty($uploadedFiles))
        <div class="da-grid">
            @foreach ($uploadedFiles as $idx => $file)
                @php
                    $isImg = $file['is_image'] ?? false;
                    $isPdf = $file['is_pdf'] ?? false;
                    $isEx = $file['is_existing'] ?? false;
                @endphp
                <div class="da-card {{ $previewIndex === $idx || $editorIndex === $idx ? 'da-card--active' : '' }}">

                    {{-- Thumb --}}
                    <button type="button" class="da-card__thumb" wire:click="openPreview({{ $idx }})"
                        title="{{ trans('wire-media::messages.preview_info') }}">
                        @if ($showPreview && $isImg)
                            <img src="{{ $file['url'] }}" alt="{{ $file['original_name'] }}" class="da-card__img"
                                loading="lazy">
                            {{-- Hover overlay --}}
                            <span class="da-card__hover-icons">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="white"
                                    stroke-width="2">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                            </span>
                        @elseif($isPdf)
                            <span class="da-card__icon da-card__icon--pdf">PDF</span>
                        @elseif($file['is_document'] ?? false)
                            <span class="da-card__icon da-card__icon--doc">{{ strtoupper($file['extension']) }}</span>
                        @else
                            <span class="da-card__icon">{{ strtoupper($file['extension']) }}</span>
                        @endif
                        <span class="da-badge {{ $isEx ? 'da-badge--ex' : 'da-badge--new' }}">
                            {{ $isEx ? trans('wire-media::messages.badge_existing') : trans('wire-media::messages.badge_new') }}
                        </span>
                    </button>

                    {{-- Card body --}}
                    <div class="da-card__body">
                        <span class="da-card__name"
                            title="{{ $file['original_name'] }}">{{ Str::limit($file['original_name'], 24) }}</span>
                        <span class="da-card__meta">{{ strtoupper($file['extension']) }} &middot;
                            {{ $file['size_human'] }}</span>
                    </div>

                    {{-- Actions --}}
                    <div class="da-card__actions">
                        @if ($isImg)
                            <button type="button" class="da-card__btn da-card__btn--edit"
                                wire:click="openEditor({{ $idx }})"
                                title="{{ trans('wire-media::messages.edit_image') }}">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                </svg>
                            </button>
                            @if ($imageFiles->count() > 0)
                                <button type="button" class="da-card__btn da-card__btn--lb"
                                    @click="openLightbox({{ $idx }})"
                                    title="{{ trans('wire-media::messages.fullscreen') }}">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <polyline points="15 3 21 3 21 9" />
                                        <polyline points="9 21 3 21 3 15" />
                                        <line x1="21" y1="3" x2="14" y2="10" />
                                        <line x1="3" y1="21" x2="10" y2="14" />
                                    </svg>
                                </button>
                            @endif
                        @endif
                        <a href="{{ $file['url'] }}" download="{{ $file['original_name'] }}"
                            class="da-card__btn da-card__btn--dl"
                            title="{{ trans('wire-media::messages.download') }}" @click.stop>
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="7 10 12 15 17 10" />
                                <line x1="12" y1="15" x2="12" y2="3" />
                            </svg>
                        </a>
                        <button type="button" class="da-card__btn da-card__btn--rm"
                            wire:click="removeFile({{ $idx }})"
                            title="{{ trans('wire-media::messages.remove') }}">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                <path d="M10 11v6" />
                                <path d="M14 11v6" />
                                <path d="M9 6V4h6v2" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        @if (count($uploadedFiles) > 1)
            <button type="button" class="da-clear-all"
                wire:click="clearAll">{{ trans('wire-media::messages.clear_all') }}</button>
        @endif
    @endif

    {{-- ══════════════════════ DETAIL PANEL ════════════════════════════════════ --}}
    @if ($previewFile)
        <div class="da-panel">
            <div class="da-panel__header">
                <span class="da-panel__title">{{ trans('wire-media::messages.file_info') }}</span>
                <div class="da-panel__header-actions">
                    @if ($previewFile['is_image'] ?? false)
                        <button type="button" class="da-panel__hbtn" wire:click="openEditor({{ $previewIndex }})"
                            title="{{ trans('wire-media::messages.edit_image') }}">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                        </button>
                        <button type="button" class="da-panel__hbtn" @click="openLightbox({{ $previewIndex }})"
                            title="{{ trans('wire-media::messages.fullscreen') }}">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <polyline points="15 3 21 3 21 9" />
                                <polyline points="9 21 3 21 3 15" />
                                <line x1="21" y1="3" x2="14" y2="10" />
                                <line x1="3" y1="21" x2="10" y2="14" />
                            </svg>
                        </button>
                    @endif
                    <button type="button" class="da-panel__hbtn da-panel__hbtn--close"
                        wire:click="closePreview">&times;</button>
                </div>
            </div>

            @if ($previewFile['is_image'])
                <div class="da-panel__preview da-panel__preview--img" @click="openLightbox({{ $previewIndex }})">
                    <img src="{{ $previewFile['url'] }}" alt="{{ $previewFile['original_name'] }}"
                        class="da-panel__img">
                    <span class="da-panel__preview-hint">{{ trans('wire-media::messages.click_fullscreen') }}</span>
                </div>
            @elseif($previewFile['is_pdf'] ?? false)
                <div class="da-panel__preview da-panel__preview--pdf">
                    <iframe src="{{ $previewFile['url'] }}#toolbar=0&navpanes=0" class="da-panel__pdf"
                        title="{{ $previewFile['original_name'] }}"></iframe>
                </div>
            @else
                <div class="da-panel__no-preview">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                    </svg>
                    <p>{{ trans('wire-media::messages.no_preview') }}</p>
                </div>
            @endif

            <table class="da-info-table">
                <tr>
                    <th>{{ trans('wire-media::messages.info_name') }}</th>
                    <td>{{ $previewFile['original_name'] }}</td>
                </tr>
                <tr>
                    <th>{{ trans('wire-media::messages.info_type') }}</th>
                    <td>{{ $previewFile['mime'] }} ({{ strtoupper($previewFile['extension']) }})</td>
                </tr>
                <tr>
                    <th>{{ trans('wire-media::messages.info_size') }}</th>
                    <td>{{ $previewFile['size_human'] }} ({{ number_format($previewFile['size']) }} bytes)</td>
                </tr>
                <tr>
                    <th>{{ trans('wire-media::messages.info_disk') }}</th>
                    <td>{{ $previewFile['disk'] }}</td>
                </tr>
                <tr>
                    <th>{{ trans('wire-media::messages.info_path') }}</th>
                    <td class="da-mono">{{ $previewFile['path'] }}</td>
                </tr>
                <tr>
                    <th>{{ trans('wire-media::messages.info_uploaded') }}</th>
                    <td>{{ \Carbon\Carbon::parse($previewFile['uploaded_at'])->format('d M Y, H:i') }}</td>
                </tr>
                <tr>
                    <th>{{ trans('wire-media::messages.info_status') }}</th>
                    <td><span
                            class="da-badge da-badge--inline {{ $previewFile['is_existing'] ?? false ? 'da-badge--ex' : 'da-badge--new' }}">
                            {{ $previewFile['is_existing'] ?? false ? trans('wire-media::messages.badge_existing') : trans('wire-media::messages.badge_new') }}
                        </span></td>
                </tr>
            </table>

            <a href="{{ $previewFile['url'] }}" download="{{ $previewFile['original_name'] }}" class="da-dl-btn">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                {{ trans('wire-media::messages.download') }}
            </a>
        </div>
    @endif

    {{-- ══════════════════════ IMAGE EDITOR ════════════════════════════════════ --}}
    @if ($editorFile)
        <div class="da-editor" x-data="daImageEditor('{{ $editorFile['url'] }}', {{ $editorIndex }})" x-init="initEditor()" wire:ignore>
            {{-- Toolbar --}}
            <div class="da-editor__toolbar">
                <div class="da-editor__toolbar-group">
                    <span class="da-editor__toolbar-label">{{ trans('wire-media::messages.editor_transform') }}</span>
                    <button type="button" class="da-tool-btn" @click="proc.zoomOut()"
                        title="{{ trans('wire-media::messages.zoom_out') }} (-)">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            <line x1="8" y1="11" x2="14" y2="11" />
                        </svg>
                    </button>
                    <span class="da-tool-btn__label" x-text="zoomLabel">100%</span>
                    <button type="button" class="da-tool-btn" @click="proc.zoomIn()"
                        title="{{ trans('wire-media::messages.zoom_in') }} (+)">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            <line x1="11" y1="8" x2="11" y2="14" />
                            <line x1="8" y1="11" x2="14" y2="11" />
                        </svg>
                    </button>
                    <button type="button" class="da-tool-btn" @click="proc.zoomReset()"
                        title="{{ trans('wire-media::messages.zoom_reset') }}">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                            <path d="M3 3v5h5" />
                        </svg>
                    </button>
                </div>

                <div class="da-editor__toolbar-group">
                    <span class="da-editor__toolbar-label">{{ trans('wire-media::messages.editor_rotate') }}</span>
                    <button type="button" class="da-tool-btn" @click="proc.rotate(-90)"
                        title="{{ trans('wire-media::messages.rotate_ccw') }}">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2.5 2v6h6M2.66 15.57a10 10 0 1 0 .57-8.38" />
                        </svg>
                    </button>
                    <button type="button" class="da-tool-btn" @click="proc.rotate(90)"
                        title="{{ trans('wire-media::messages.rotate_cw') }}">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38" />
                        </svg>
                    </button>
                </div>

                <div class="da-editor__toolbar-group">
                    <span class="da-editor__toolbar-label">{{ trans('wire-media::messages.editor_flip') }}</span>
                    <button type="button" class="da-tool-btn" @click="proc.flipHorizontal()"
                        title="{{ trans('wire-media::messages.flip_h') }}">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3" />
                            <path d="M16 3h3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-3" />
                            <line x1="12" y1="20" x2="12" y2="4" />
                        </svg>
                    </button>
                    <button type="button" class="da-tool-btn" @click="proc.flipVertical()"
                        title="{{ trans('wire-media::messages.flip_v') }}">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3" />
                            <path d="M21 16v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3" />
                            <line x1="4" y1="12" x2="20" y2="12" />
                        </svg>
                    </button>
                </div>

                <div class="da-editor__toolbar-group">
                    <span class="da-editor__toolbar-label">{{ trans('wire-media::messages.editor_crop') }}</span>
                    <button type="button" class="da-tool-btn" :class="{ 'da-tool-btn--active': cropMode }"
                        @click="toggleCrop()" title="{{ trans('wire-media::messages.crop_select') }}">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2v14a2 2 0 0 0 2 2h14" />
                            <path d="M18 22V8a2 2 0 0 0-2-2H2" />
                        </svg>
                    </button>
                    <button type="button" class="da-tool-btn da-tool-btn--apply" :disabled="!hasCrop"
                        @click="applyCrop()" title="{{ trans('wire-media::messages.crop_apply') }}">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </button>
                </div>

                <div class="da-editor__toolbar-group">
                    <button type="button" class="da-tool-btn" @click="resetAll()"
                        title="{{ trans('wire-media::messages.editor_reset') }}">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="1 4 1 10 7 10" />
                            <path d="M3.51 15a9 9 0 1 0 .49-4.49" />
                        </svg>
                        <span class="da-tool-btn__text">{{ trans('wire-media::messages.editor_reset') }}</span>
                    </button>
                </div>
            </div>

            {{-- Canvas --}}
            <div class="da-editor__canvas-wrap" id="da-editor-wrap-{{ $editorIndex }}">
                <canvas id="da-editor-canvas-{{ $editorIndex }}" class="da-editor__canvas"></canvas>
                <div class="da-editor__canvas-hint" x-show="!canvasReady">
                    <div class="da-spin-ring"></div>
                </div>
            </div>

            {{-- Keyboard hint --}}
            <p class="da-editor__hint">
                <kbd>Scroll</kbd> {{ trans('wire-media::messages.hint_scroll_zoom') }} &nbsp;
                <kbd>Drag</kbd> {{ trans('wire-media::messages.hint_drag_pan') }} &nbsp;
                <kbd>Crop mode</kbd>: {{ trans('wire-media::messages.hint_crop_drag') }}
            </p>

            {{-- Save bar --}}
            <div class="da-editor__save-bar">
                <div class="da-editor__save-mode">
                    <label class="da-radio">
                        <input type="radio" x-model="saveMode" value="replace">
                        {{ trans('wire-media::messages.save_replace') }}
                    </label>
                    <label class="da-radio">
                        <input type="radio" x-model="saveMode" value="add">
                        {{ trans('wire-media::messages.save_add') }}
                    </label>
                </div>

                <div class="da-editor__save-actions">
                    <button type="button" class="da-editor__btn da-editor__btn--secondary"
                        @click="downloadCurrent()">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        {{ trans('wire-media::messages.save_download') }}
                    </button>
                    <button type="button" class="da-editor__btn da-editor__btn--primary" @click="saveToServer()"
                        :disabled="saving">
                        <span x-show="!saving" class="da-saving">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                <polyline points="17 21 17 13 7 13 7 21" />
                                <polyline points="7 3 7 8 15 8" />
                            </svg>
                            {{ trans('wire-media::messages.save_server') }}
                        </span>
                        <span x-show="saving" class="da-spin-sm"></span>
                    </button>
                    <button type="button" class="da-editor__btn da-editor__btn--ghost" wire:click="closeEditor">
                        {{ trans('wire-media::messages.cancel') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Help text --}}
    @if ($helpText)
        <p class="da-help">{{ $helpText }}</p>
    @endif

</div>{{-- /da-wrap --}}


{{-- ══════════════════════════ SCRIPTS ═════════════════════════════════════ --}}
@once
    <script>
        /* ════════════════════════════════════════════════════════════════════════════
                       ImageProcessor — canvas engine
                       ════════════════════════════════════════════════════════════════════════════ */
        {!! file_get_contents($wireMediaJsPath . '/image-processor.js') !!}

        /* ════════════════════════════════════════════════════════════════════════════
           LightboxManager — fullscreen carousel
           ════════════════════════════════════════════════════════════════════════════ */
        {!! file_get_contents($wireMediaJsPath . '/lightbox-manager.js') !!}

        /* ════════════════════════════════════════════════════════════════════════════
           Alpine component: daFileUpload
           ════════════════════════════════════════════════════════════════════════════ */
        document.addEventListener('alpine:init', () => {

            Alpine.data('daFileUpload', (files, darkMode) => ({
                isDragging: false,
                files: files,

                init() {
                    // Keep files in sync when Livewire re-renders
                    this.$watch('$wire.uploadedFiles', v => {
                        this.files = v;
                    });
                },

                handleDrop(e) {
                    this.isDragging = false;
                    const input = this.$refs.fileInput;
                    if (e.dataTransfer && input) {
                        input.files = e.dataTransfer.files;
                        input.dispatchEvent(new Event('change'));
                    }
                },

                openLightbox(originalIndex) {
                    if (!this.files.length) return;
                    new LightboxManager(
                        this.files,
                        originalIndex,
                        (idx) => this.$wire.openEditor(idx)
                    );
                },
            }));

            /* ── Alpine component: daImageEditor ─────────────────────────────────── */
            Alpine.data('daImageEditor', (src, index) => ({
                src: src,
                index: index,
                proc: null,
                cropMode: false,
                hasCrop: false,
                zoomLabel: '100%',
                saveMode: 'replace',
                saving: false,
                canvasReady: false,

                initEditor() {
                    this.$nextTick(() => {
                        const canvas = document.getElementById('da-editor-canvas-' + this
                            .index);
                        if (!canvas) return;

                        this.proc = new ImageProcessor(canvas, this.src);

                        canvas.addEventListener('ip:ready', () => {
                            this.canvasReady = true;
                        });

                        canvas.addEventListener('ip:zoom', (e) => {
                            this.zoomLabel = Math.round(e.detail.zoom * 100) + '%';
                        });

                        canvas.addEventListener('ip:cropselected', (e) => {
                            this.hasCrop = (e.detail.rect.w > 5 && e.detail.rect.h > 5);
                        });
                    });
                },

                toggleCrop() {
                    this.cropMode = !this.cropMode;
                    this.hasCrop = false;
                    this.cropMode ? this.proc.enableCrop() : this.proc.disableCrop();
                },

                applyCrop() {
                    const dataUrl = this.proc.applyCrop();
                    if (dataUrl) {
                        // Reload editor with cropped result
                        this.src = dataUrl;
                        this.cropMode = false;
                        this.hasCrop = false;
                        this.canvasReady = false;
                        this.proc.destroy();
                        this.$nextTick(() => this.initEditor());
                    }
                },

                resetAll() {
                    if (this.proc) {
                        this.proc.resetAll();
                        this.cropMode = false;
                        this.hasCrop = false;
                        this.zoomLabel = '100%';
                        // Reload from original src
                        this.src = this.src;
                    }
                },

                downloadCurrent() {
                    if (!this.proc) return;
                    const dataUrl = this.proc.exportDataURL(1);
                    const a = document.createElement('a');
                    a.href = dataUrl;
                    a.download = 'edited-image.png';
                    a.click();
                },

                async saveToServer() {
                    if (!this.proc) return;
                    this.saving = true;
                    try {
                        const dataUrl = this.proc.exportDataURL(1);
                        await this.$wire.saveProcessedImage(this.index, dataUrl, this.saveMode);
                    } finally {
                        this.saving = false;
                    }
                },
            }));
        });
    </script>

    <style>
        /* ── CSS variables (light) ──────────────────────────────────────────────── */
        :root {
            --da-bg: #ffffff;
            --da-bg2: #f8fafc;
            --da-bg3: #f1f5f9;
            --da-border: #e2e8f0;
            --da-border2: #cbd5e1;
            --da-text: #1e293b;
            --da-text2: #475569;
            --da-text3: #94a3b8;
            --da-accent: #6366f1;
            --da-accent-dk: #4f46e5;
            --da-accent-bg: #eef2ff;
            --da-accent-br: #e0e7ff;
            --da-danger: #dc2626;
            --da-danger-bg: #fef2f2;
            --da-danger-br: #fecaca;
            --da-success: #065f46;
            --da-success-bg: #d1fae5;
            --da-warn-bg: #fef3c7;
            --da-warn-txt: #92400e;
            --da-shadow: 0 2px 12px rgba(99, 102, 241, .14);
            --da-radius: .875rem;
            --da-radius-sm: .5rem;
            --da-font: inherit;
        }

        /* ── Dark mode override ─────────────────────────────────────────────────── */
        .da-dark,
        .da-dark * {
            --da-bg: #0f172a;
            --da-bg2: #1e293b;
            --da-bg3: #334155;
            --da-border: #334155;
            --da-border2: #475569;
            --da-text: #f1f5f9;
            --da-text2: #cbd5e1;
            --da-text3: #64748b;
            --da-accent-bg: #1e1b4b;
            --da-accent-br: #312e81;
            --da-danger-bg: #450a0a;
            --da-danger-br: #7f1d1d;
            --da-warn-bg: #451a03;
            --da-warn-txt: #fcd34d;
            --da-success-bg: #064e3b;
            --da-success: #6ee7b7;
            --da-shadow: 0 2px 16px rgba(0, 0, 0, .5);
        }

        /* Detect OS dark mode if prop not set */
        @media (prefers-color-scheme: dark) {
            .da-wrap:not(.da-dark) {
                --da-bg: #0f172a;
                --da-bg2: #1e293b;
                --da-bg3: #334155;
                --da-border: #334155;
                --da-border2: #475569;
                --da-text: #f1f5f9;
                --da-text2: #cbd5e1;
                --da-text3: #64748b;
                --da-accent-bg: #1e1b4b;
                --da-accent-br: #312e81;
                --da-danger-bg: #450a0a;
                --da-danger-br: #7f1d1d;
                --da-warn-bg: #451a03;
                --da-warn-txt: #fcd34d;
                --da-success-bg: #064e3b;
                --da-success: #6ee7b7;
                --da-shadow: 0 2px 16px rgba(0, 0, 0, .5);
            }
        }

        /* ── Base ───────────────────────────────────────────────────────────────── */
        .da-wrap {
            font-family: var(--da-font);
            color: var(--da-text);
        }

        .da-label {
            display: block;
            font-weight: 600;
            margin-bottom: .5rem;
            color: var(--da-text);
        }

        /* ── Dropzone ───────────────────────────────────────────────────────────── */
        .da-dropzone {
            border: 2px dashed var(--da-border2);
            border-radius: var(--da-radius);
            padding: 2rem 1.5rem;
            text-align: center;
            cursor: pointer;
            background: var(--da-bg2);
            transition: border-color .2s, background .2s;
            user-select: none;
        }

        .da-dropzone:hover,
        .da-dropzone--over {
            border-color: var(--da-accent);
            background: var(--da-accent-bg);
        }

        .da-dropzone__icon {
            color: var(--da-text3);
            margin-bottom: .75rem;
            display: flex;
            align-items: center;
            width: 100%;
            justify-content: center;
        }

        .da-dropzone__primary {
            margin: 0 0 .35rem;
            font-weight: 500;
            color: var(--da-text2);
        }

        .da-dropzone__hint {
            margin: 0;
            font-size: .78rem;
            color: var(--da-text3);
        }

        .da-hidden-input {
            display: none;
        }

        /* ── Progress ───────────────────────────────────────────────────────────── */
        .da-progress-wrap {
            margin-top: .75rem;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .da-progress-anim {
            flex: 1;
            height: 4px;
            background: var(--da-accent);
            border-radius: 9999px;
            animation: da-pulse 1.2s ease infinite;
        }

        @keyframes da-pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .35
            }
        }

        .da-progress-text {
            font-size: .75rem;
            color: var(--da-text3);
            white-space: nowrap;
        }

        /* ── Errors ─────────────────────────────────────────────────────────────── */
        .da-error-box {
            margin-top: .6rem;
            background: var(--da-danger-bg);
            border: 1px solid var(--da-danger-br);
            border-radius: var(--da-radius-sm);
            padding: .6rem .9rem;
        }

        .da-error-line {
            display: flex;
            align-items: center;
            gap: .4rem;
            margin: .2rem 0;
            color: var(--da-danger);
            font-size: .84rem;
        }

        /* ── Grid ───────────────────────────────────────────────────────────────── */
        .da-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: .75rem;
            margin-top: 1rem;
        }

        /* ── Card ───────────────────────────────────────────────────────────────── */
        .da-card {
            border: 1.5px solid var(--da-border);
            border-radius: var(--da-radius-sm);
            background: var(--da-bg);
            display: flex;
            flex-direction: column;
            transition: border-color .15s, box-shadow .15s;
            overflow: hidden;
        }

        .da-card:hover {
            border-color: #a5b4fc;
            box-shadow: var(--da-shadow);
        }

        .da-card--active {
            border-color: var(--da-accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .18);
        }

        .da-card__thumb {
            position: relative;
            border: none;
            background: var(--da-bg3);
            cursor: pointer;
            padding: 0;
            width: 100%;
            aspect-ratio: 4/3;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .da-card__img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .2s, opacity .2s;
            display: block;
        }

        .da-card__thumb:hover .da-card__img {
            transform: scale(1.04);
            opacity: .88;
        }

        .da-card__hover-icons {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .35);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity .18s;
        }

        .da-card__thumb:hover .da-card__hover-icons {
            opacity: 1;
        }

        .da-card__icon {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .05em;
            padding: .35rem .6rem;
            border-radius: .375rem;
            background: var(--da-bg3);
            color: var(--da-text2);
        }

        .da-card__icon--pdf {
            background: #fee2e2;
            color: #b91c1c;
        }

        .da-card__icon--doc {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .da-badge {
            font-size: .6rem;
            font-weight: 700;
            padding: .15rem .45rem;
            border-radius: 9999px;
            line-height: 1.4;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .da-card__thumb .da-badge {
            position: absolute;
            top: .4rem;
            left: .4rem;
        }

        .da-badge--inline {
            position: static;
        }

        .da-badge--ex {
            background: var(--da-warn-bg);
            color: var(--da-warn-txt);
        }

        .da-badge--new {
            background: var(--da-success-bg);
            color: var(--da-success);
        }

        .da-card__body {
            padding: .5rem .65rem .3rem;
            display: flex;
            flex-direction: column;
            gap: .1rem;
            flex: 1;
            background: var(--da-bg);
        }

        .da-card__name {
            font-size: .8rem;
            font-weight: 500;
            color: var(--da-text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .da-card__meta {
            font-size: .68rem;
            color: var(--da-text3);
        }

        .da-card__actions {
            display: flex;
            border-top: 1px solid var(--da-border);
            background: var(--da-bg);
        }

        .da-card__btn {
            flex: 1;
            border: none;
            background: none;
            cursor: pointer;
            padding: .45rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--da-text3);
            transition: background .12s, color .12s;
            text-decoration: none;
        }

        .da-card__btn:hover {
            background: var(--da-bg2);
        }

        .da-card__btn--edit:hover {
            color: var(--da-accent);
        }

        .da-card__btn--lb:hover {
            color: var(--da-accent);
        }

        .da-card__btn--dl:hover {
            color: var(--da-accent);
        }

        .da-card__btn--rm:hover {
            color: var(--da-danger);
            background: var(--da-danger-bg);
        }

        .da-card__btn+.da-card__btn {
            border-left: 1px solid var(--da-border);
        }

        .da-clear-all {
            margin-top: .6rem;
            font-size: .78rem;
            color: var(--da-danger);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
        }

        /* ── Detail Panel ───────────────────────────────────────────────────────── */
        .da-panel {
            margin-top: 1rem;
            border: 1.5px solid var(--da-accent);
            border-radius: var(--da-radius);
            background: var(--da-bg);
            overflow: hidden;
        }

        .da-panel__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .65rem 1rem;
            background: var(--da-accent-bg);
            border-bottom: 1px solid var(--da-accent-br);
        }

        .da-panel__title {
            font-weight: 600;
            font-size: .9rem;
            color: var(--da-accent);
        }

        .da-panel__header-actions {
            display: flex;
            gap: .25rem;
        }

        .da-panel__hbtn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--da-accent);
            padding: .2rem .3rem;
            border-radius: .3rem;
            display: flex;
            align-items: center;
        }

        .da-panel__hbtn:hover {
            background: var(--da-accent-br);
        }

        .da-panel__hbtn--close {
            font-size: 1.3rem;
            line-height: 1;
        }

        .da-panel__hbtn--close:hover {
            color: var(--da-danger);
        }

        .da-panel__preview {
            cursor: pointer;
            position: relative;
        }

        .da-panel__preview--img {
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            max-height: 360px;
            overflow: hidden;
        }

        .da-panel__img {
            max-width: 100%;
            max-height: 360px;
            object-fit: contain;
            display: block;
            transition: transform .2s;
        }

        .da-panel__preview:hover .da-panel__img {
            transform: scale(1.02);
        }

        .da-panel__preview-hint {
            position: absolute;
            bottom: .5rem;
            right: .75rem;
            font-size: .7rem;
            color: rgba(255, 255, 255, .6);
            pointer-events: none;
        }

        .da-panel__preview--pdf {
            background: #1e293b;
        }

        .da-panel__pdf {
            width: 100%;
            height: 460px;
            border: none;
            display: block;
        }

        .da-panel__no-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            gap: .75rem;
            background: var(--da-bg2);
            color: var(--da-text3);
            font-size: .85rem;
        }

        .da-info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .82rem;
        }

        .da-info-table tr {
            border-bottom: 1px solid var(--da-border);
        }

        .da-info-table tr:last-child {
            border-bottom: none;
        }

        .da-info-table th {
            width: 30%;
            padding: .55rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--da-text3);
            white-space: nowrap;
            background: var(--da-bg2);
        }

        .da-info-table td {
            padding: .55rem 1rem;
            color: var(--da-text);
            word-break: break-all;
        }

        .da-mono {
            font-family: ui-monospace, monospace;
            font-size: .76rem;
            color: var(--da-accent);
        }

        .da-dl-btn {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin: .9rem 1rem 1rem;
            padding: .55rem 1.1rem;
            background: var(--da-accent);
            color: #fff;
            border-radius: var(--da-radius-sm);
            font-size: .84rem;
            font-weight: 500;
            text-decoration: none;
            transition: background .15s;
        }

        .da-dl-btn:hover {
            background: var(--da-accent-dk);
        }

        /* ── Image Editor ───────────────────────────────────────────────────────── */
        .da-editor {
            margin-top: 1rem;
            border: 1.5px solid var(--da-accent);
            border-radius: var(--da-radius);
            background: var(--da-bg);
            overflow: hidden;
        }

        .da-editor__toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem 1rem;
            padding: .7rem 1rem;
            background: var(--da-bg2);
            border-bottom: 1px solid var(--da-border);
        }

        .da-editor__toolbar-group {
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .da-editor__toolbar-label {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--da-text3);
            margin-right: .1rem;
            white-space: nowrap;
        }

        .da-tool-btn {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            background: var(--da-bg);
            border: 1px solid var(--da-border);
            color: var(--da-text2);
            cursor: pointer;
            border-radius: .4rem;
            padding: .35rem .55rem;
            font-size: .78rem;
            transition: all .12s;
        }

        .da-tool-btn:hover {
            border-color: var(--da-accent);
            color: var(--da-accent);
            background: var(--da-accent-bg);
        }

        .da-tool-btn--active {
            border-color: var(--da-accent);
            background: var(--da-accent);
            color: #fff;
        }

        .da-tool-btn--active:hover {
            background: var(--da-accent-dk);
        }

        .da-tool-btn--apply {
            border-color: #16a34a;
            color: #16a34a;
        }

        .da-tool-btn--apply:hover {
            background: #f0fdf4;
        }

        .da-tool-btn--apply:disabled {
            opacity: .35;
            cursor: not-allowed;
        }

        .da-tool-btn__label {
            font-size: .78rem;
            color: var(--da-text2);
            min-width: 3rem;
            text-align: center;
        }

        .da-tool-btn__text {
            font-size: .78rem;
        }

        .da-editor__canvas-wrap {
            position: relative;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            max-height: 65vh;
            overflow: hidden;
        }

        .da-editor__canvas {
            display: block;
            max-width: 100%;
            max-height: 65vh;
            object-fit: contain;
            cursor: grab;
        }

        .da-editor__canvas:active {
            cursor: grabbing;
        }

        .da-editor__canvas-hint {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, .7);
        }

        .da-editor__hint {
            font-size: .72rem;
            color: var(--da-text3);
            margin: .4rem .9rem;
            text-align: center;
        }

        .da-editor__hint kbd {
            background: var(--da-bg3);
            border: 1px solid var(--da-border2);
            border-radius: .25rem;
            padding: .1rem .3rem;
            font-size: .68rem;
            font-family: ui-monospace, monospace;
        }

        .da-editor__save-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .8rem 1rem;
            background: var(--da-bg2);
            border-top: 1px solid var(--da-border);
        }

        .da-editor__save-mode {
            display: flex;
            gap: 1rem;
        }

        .da-radio {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .84rem;
            cursor: pointer;
            color: var(--da-text2);
        }

        .da-editor__save-actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .da-editor__btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .5rem .9rem;
            border-radius: var(--da-radius-sm);
            font-size: .84rem;
            font-weight: 500;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: all .15s;
        }

        .da-editor__btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .da-editor__btn--primary {
            background: var(--da-accent);
            color: #fff;
            border-color: var(--da-accent);
        }

        .da-editor__btn--primary:hover:not(:disabled) {
            background: var(--da-accent-dk);
        }

        .da-editor__btn--secondary {
            background: var(--da-bg);
            color: var(--da-text2);
            border-color: var(--da-border2);
        }

        .da-editor__btn--secondary:hover {
            border-color: var(--da-accent);
            color: var(--da-accent);
        }

        .da-editor__btn--ghost {
            background: none;
            color: var(--da-text3);
            border-color: transparent;
        }

        .da-editor__btn--ghost:hover {
            color: var(--da-danger);
        }

        /* ── Spinners ───────────────────────────────────────────────────────────── */
        .da-spin-ring {
            width: 32px;
            height: 32px;
            border: 3px solid rgba(255, 255, 255, .2);
            border-top-color: #fff;
            border-radius: 50%;
            animation: da-spin .7s linear infinite;
        }

        .da-spin-sm {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, .35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: da-spin .6s linear infinite;
        }

        @keyframes da-spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Lightbox ───────────────────────────────────────────────────────────── */
        .da-lb {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .da-lb__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .92);
        }

        .da-lb__shell {
            position: relative;
            z-index: 1;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .da-lb__header {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .65rem 1rem;
            background: rgba(15, 23, 42, .85);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            flex-shrink: 0;
        }

        .da-lb__counter {
            font-size: .8rem;
            color: rgba(255, 255, 255, .5);
            white-space: nowrap;
        }

        .da-lb__filename {
            flex: 1;
            font-size: .85rem;
            color: rgba(255, 255, 255, .85);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .da-lb__header-actions {
            display: flex;
            gap: .25rem;
        }

        .da-lb__btn {
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .12);
            color: rgba(255, 255, 255, .8);
            cursor: pointer;
            border-radius: .4rem;
            padding: .35rem .45rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            transition: all .15s;
            text-decoration: none;
        }

        .da-lb__btn:hover {
            background: rgba(99, 102, 241, .5);
            border-color: #6366f1;
            color: #fff;
        }

        .da-lb__btn--close:hover {
            background: rgba(220, 38, 38, .5);
            border-color: #dc2626;
        }

        .da-lb__btn svg {
            width: 17px;
            height: 17px;
        }

        .da-lb__stage {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .da-lb__nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .15);
            color: #fff;
            cursor: pointer;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .15s;
            z-index: 2;
        }

        .da-lb__nav:hover {
            background: rgba(99, 102, 241, .6);
        }

        .da-lb__nav svg {
            width: 22px;
            height: 22px;
        }

        .da-lb__nav--prev {
            left: 1rem;
        }

        .da-lb__nav--next {
            right: 1rem;
        }

        .da-lb__img-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .da-lb__spinner {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .da-lb__spin-ring {
            width: 36px;
            height: 36px;
            border: 3px solid rgba(255, 255, 255, .2);
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: da-spin .7s linear infinite;
        }

        .da-lb__img {
            max-width: 90vw;
            max-height: 75vh;
            object-fit: contain;
            border-radius: .5rem;
            transition: opacity .25s, transform .2s;
            transform-origin: center;
            cursor: zoom-in;
        }

        .da-lb__strip {
            display: flex;
            gap: .5rem;
            padding: .5rem .75rem;
            background: rgba(15, 23, 42, .85);
            backdrop-filter: blur(8px);
            overflow-x: auto;
            flex-shrink: 0;
            scrollbar-width: thin;
        }

        .da-lb__strip::-webkit-scrollbar {
            height: 4px;
        }

        .da-lb__strip::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, .5);
            border-radius: 9999px;
        }

        .da-lb__thumb {
            width: 52px;
            height: 52px;
            border-radius: .35rem;
            border: 2px solid transparent;
            background-size: cover;
            background-position: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: border-color .15s, opacity .15s;
            opacity: .55;
        }

        .da-lb__thumb:hover,
        .da-lb__thumb--active {
            border-color: #6366f1;
            opacity: 1;
        }

        .da-lb__zoom-bar {
            position: absolute;
            bottom: 4.5rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: .4rem;
            background: rgba(15, 23, 42, .8);
            backdrop-filter: blur(8px);
            padding: .4rem .6rem;
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, .1);
        }

        .da-lb__zoom-btn {
            background: none;
            border: none;
            color: rgba(255, 255, 255, .75);
            cursor: pointer;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all .12s;
        }

        .da-lb__zoom-btn:hover {
            background: rgba(99, 102, 241, .4);
            color: #fff;
        }

        .da-lb__zoom-btn svg {
            width: 16px;
            height: 16px;
        }

        .da-lb__zoom-val {
            font-size: .75rem;
            color: rgba(255, 255, 255, .75);
            min-width: 3rem;
            text-align: center;
        }

        /* ── Help ───────────────────────────────────────────────────────────────── */
        .da-help {
            font-size: .78rem;
            color: var(--da-text3);
            margin-top: .5rem;
        }

        /* ── Transitions ────────────────────────────────────────────────────────── */
        [x-cloak] {
            display: none !important;
        }

        .da-saving {
            display: flex;
            align-items: center;
            gap: 4px;
        }
    </style>
@endonce
