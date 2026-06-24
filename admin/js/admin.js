(function ($) {
    'use strict';

    const helloFigma = {
        currentFileKey: '',
        currentCanvases: [],

        init() {
            this.bindEvents();

            // If default file key exists, auto-load
            const defaultKey = $('#figma-file-key-input').val();
            if (defaultKey) {
                this.currentFileKey = defaultKey;
            }
        },

        bindEvents() {
            $('#hello-figma-load-file').on('submit', this.handleLoadFile.bind(this));
            $('#hello-figma-browse-default').on('click', this.handleBrowseDefault.bind(this));
            $('.delete-template').on('click', this.handleDelete.bind(this));
            $('.export-template').on('click', this.handleExport.bind(this));
            $('#hello-figma-sync-colors').on('click', (e) => this.syncStyles('colors', e));
            $('#hello-figma-sync-typography').on('click', (e) => this.syncStyles('typography', e));
            $('#hello-figma-sync-all').on('click', (e) => this.syncStyles('all', e));
            $('#step-3-import-another').on('click', () => this.resetWizard());
        },

        // ── Step 1: Load File ──

        handleLoadFile(e) {
            e.preventDefault();
            const raw = $('#figma-file-key-input').val().trim();
            const fileKey = this.parseFigmaUrl(raw);
            if (!fileKey) {
                this.showError('step-1', 'Please enter a valid Figma file key or URL.');
                return;
            }

            this.currentFileKey = fileKey;
            $('#step-1').hide();
            $('#step-1-loading').show();
            $('#step-1-error').hide().html('');

            $.post(ajaxurl, {
                action: 'hello_figma_fetch_structure',
                nonce: helloFigmaData.nonce,
                file_key: fileKey
            }, (response) => {
                $('#step-1-loading').hide();
                if (response.success) {
                    this.renderFrames(response.data);
                } else {
                    $('#step-1').show();
                    this.showError('step-1', response.data.message || 'Failed to load file.');
                }
            }).fail(() => {
                $('#step-1-loading').hide();
                $('#step-1').show();
                this.showError('step-1', 'Server error. Please try again.');
            });
        },

        handleBrowseDefault(e) {
            e.preventDefault();
            const key = $('#figma-file-key-input').val();
            if (key) {
                $('#figma-file-key-input').val(key);
                $('#hello-figma-load-file').trigger('submit');
            }
        },

        // ── Step 2: Render Frames ──

        renderFrames(data) {
            $('#figma-file-name').text('(' + data.file_name + ')');
            this.currentCanvases = data.canvases || [];

            console.log('[HelloFigma] File structure received:', data);

            const grid = $('#hello-figma-frame-grid');
            grid.empty();

            if (this.currentCanvases.length === 0) {
                grid.html('<p>No frames found in this file. Try selecting a different canvas or check the file has design frames.</p>');
                $('#step-2').show();
                return;
            }

            // Collect all frame node IDs for preview images
            const allNodeIds = [];
            const frameMap = [];

            $.each(this.currentCanvases, (ci, canvas) => {
                $.each(canvas.frames, (fi, frame) => {
                    allNodeIds.push(frame.id);
                    frameMap.push({
                        canvasIdx: ci,
                        frameIdx: fi,
                        id: frame.id
                    });
                });
            });

            // Render placeholder frames first
            $.each(this.currentCanvases, (ci, canvas) => {
                if (canvas.frames.length === 0) return;

                const section = $('<div class="figma-canvas-section"></div>');
                section.append('<h3 class="figma-canvas-title">📄 ' + this.escHtml(canvas.name) + '</h3>');
                section.append('<p class="figma-canvas-frames-count">' + canvas.frames.length + ' frames</p>');

                const frameRow = $('<div class="figma-frame-row"></div>');

                $.each(canvas.frames, (fi, frame) => {
                    const card = $(
                        '<div class="figma-frame-card" data-node-id="' + frame.id + '">' +
                            '<div class="figma-frame-preview">' +
                                '<div class="figma-frame-placeholder">' +
                                    '<span class="figma-frame-icon">🖼️</span>' +
                                    '<span class="figma-frame-loading">Loading...</span>' +
                                '</div>' +
                            '</div>' +
                            '<div class="figma-frame-info">' +
                                '<strong class="figma-frame-name">' + this.escHtml(frame.name) + '</strong>' +
                                '<span class="figma-frame-meta">' +
                                    frame.width + '×' + frame.height + ' · ' +
                                    frame.child_count + ' elements' +
                                '</span>' +
                            '</div>' +
                            '<button class="button button-primary figma-import-btn" data-node-id="' + frame.id + '" data-name="' + this.escAttr(frame.name) + '">' +
                                'Import' +
                            '</button>' +
                        '</div>'
                    );

                    card.find('.figma-import-btn').on('click', (e) => {
                        e.stopPropagation();
                        this.importFrame(frame.id, frame.name);
                    });

                    frameRow.append(card);
                });

                section.append(frameRow);
                grid.append(section);
            });

            $('#step-2').show();

            // Load preview images in background
            if (allNodeIds.length > 0) {
                this.loadPreviewImages(allNodeIds);
            }
        },

        loadPreviewImages(nodeIds) {
            // Limit to prevent URL length issues
            const batch = nodeIds.slice(0, 50);

            $.post(ajaxurl, {
                action: 'hello_figma_fetch_frame_images',
                nonce: helloFigmaData.nonce,
                file_key: this.currentFileKey,
                node_ids: batch.join(',')
            }, (response) => {
                if (response.success && response.data.images) {
                    $.each(response.data.images, (nodeId, imageUrl) => {
                        if (imageUrl) {
                            const card = $('.figma-frame-card[data-node-id="' + nodeId + '"]');
                            const preview = card.find('.figma-frame-preview');
                            preview.html('<img src="' + imageUrl + '" alt="" loading="lazy" style="width:100%;height:auto;">');
                        }
                    });
                }
            });
        },

        // ── Step 3: Import ──

        importFrame(nodeId, name) {
            $('#step-2').hide();
            $('#hello-figma-convert-progress').show();

            const title = name || 'Figma Import';

            $.post(ajaxurl, {
                action: 'hello_figma_convert',
                nonce: helloFigmaData.nonce,
                file_key: this.currentFileKey,
                node_id: nodeId,
                title: title
            }, (response) => {
                $('#hello-figma-convert-progress').hide();
                if (response.success) {
                    $('#step-3-message').text('"' + title + '" has been imported successfully.');
                    $('#step-3-edit-link').attr('href', response.data.edit_url);
                    $('#step-3').show();
                } else {
                    this.showError('step-1', response.data.message || 'Conversion failed.');
                    $('#step-1').show();
                }
            }).fail(() => {
                $('#hello-figma-convert-progress').hide();
                this.showError('step-1', 'Server error during conversion.');
                $('#step-1').show();
            });
        },

        resetWizard() {
            $('#step-3').hide();
            $('#hello-figma-convert-progress').hide();
            $('#step-1').show();
            $('#step-2').hide();
            $('#step-1-error').hide();
            $('#figma-file-key-input').val(this.currentFileKey);
        },

        // ── Template Management ──

        handleDelete(e) {
            if (!confirm(helloFigmaData.i18n.confirmDelete)) return;

            const btn = $(e.currentTarget);
            $.post(ajaxurl, {
                action: 'hello_figma_delete_template',
                nonce: helloFigmaData.nonce,
                post_id: btn.data('post-id')
            }, (response) => {
                if (response.success) {
                    btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
                } else {
                    alert(response.data.message);
                }
            });
        },

        handleExport(e) {
            const btn = $(e.currentTarget);
            $.post(ajaxurl, {
                action: 'hello_figma_export_template',
                nonce: helloFigmaData.nonce,
                post_id: btn.data('post-id')
            }, (response) => {
                if (response.success) {
                    const blob = new Blob([response.data.json], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    alert(response.data.message);
                }
            });
        },

        // ── Style Sync ──

        syncStyles(type, e) {
            const resultDiv = $('#hello-figma-sync-result');
            resultDiv.removeClass('success error').html('<p>Syncing styles... Please wait.</p>');

            $.post(ajaxurl, {
                action: 'hello_figma_sync_styles',
                nonce: helloFigmaData.nonce,
                file_key: $('#hello-figma-sync-all').data('file-key'),
                type: type
            }, (response) => {
                if (response.success) {
                    let html = '<p>' + response.data.message + '</p>';
                    if (response.data.colors) {
                        html += '<p>🎨 Colors: ' + response.data.colors.length + ' synced</p>';
                    }
                    if (response.data.typography) {
                        html += '<p>📝 Typography: ' + response.data.typography.length + ' synced</p>';
                    }
                    resultDiv.addClass('success').html(html);
                } else {
                    resultDiv.addClass('error').html('<p>Error: ' + response.data.message + '</p>');
                }
            }).fail(() => {
                resultDiv.addClass('error').html('<p>Server error. Please try again.</p>');
            });
        },

        // ── Utilities ──

        showError(step, message) {
            const el = $('#' + step + '-error');
            el.html('<div class="notice notice-error"><p>' + message + '</p></div>').show();
        },

        /**
         * Parse a Figma URL to extract the file key.
         * Accepts both raw keys and full URLs:
         *   https://www.figma.com/file/abc123DEF/Name
         *   https://www.figma.com/design/abc123DEF/Name
         *   abc123DEF
         */
        parseFigmaUrl(input) {
            if (!input) return '';

            // Try to match a full Figma URL
            const match = input.match(
                /figma\.com\/(?:file|design)\/([a-zA-Z0-9_-]+)/i
            );
            if (match) {
                return match[1];
            }

            // Already a raw key (alphanumeric + hyphens/underscores)
            if (/^[a-zA-Z0-9_-]+$/.test(input)) {
                return input;
            }

            return '';
        },

        escHtml(str) {
            if (!str) return '';
            return $('<div>').text(str).html();
        },

        escAttr(str) {
            if (!str) return '';
            return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
    };

    $(document).ready(function () {
        helloFigma.init();
    });
})(jQuery);
