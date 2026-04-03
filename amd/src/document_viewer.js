// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PHI-protected PDF viewer with tiled per-viewer watermarking.
 *
 * Loads a PDF via PDF.js using the serve_document.php endpoint, renders each page
 * to a canvas, and composites a tiled watermark containing the viewer's identity
 * and session ID directly onto the canvas pixels.
 *
 * Security design:
 * - No <object>, <embed>, or <iframe> — no native browser PDF affordances.
 * - Watermark is drawn into canvas pixels after every page render.
 * - Context menu is disabled on the viewer container.
 * - CSS user-select: none is applied to the canvas.
 *
 * @module     local_dsl_isp/document_viewer
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_dsl_isp/pdfjs_loader', 'core/notification'], function(PdfjsLib, Notification) {

    /** @type {Object|null} The loaded PDF document object. */
    var pdfDoc = null;

    /** @type {number} The current page number (1-indexed). */
    var currentPage = 1;

    /** @type {number} Current zoom scale factor. */
    var currentScale = 1.5;

    /** @type {Object} Configuration passed from view_document.php via js_call_amd. */
    var cfg = {};

    /** @type {boolean} Whether a page render is in progress. */
    var rendering = false;

    /**
     * Initialise the viewer.
     *
     * @param {Object} config Configuration object from view_document.php.
     * @param {string} config.serveUrl URL of serve_document.php (includes sesskey).
     * @param {string} config.viewerName Viewer's full name.
     * @param {string} config.viewerEmail Viewer's email address.
     * @param {string} config.tenantName Viewer's tenant/agency name.
     * @param {string} config.timestamp View session timestamp string.
     * @param {number} config.sessionId View log record ID.
     * @param {string} config.docName Document display name.
     */
    var init = function(config) {
        cfg = config;

        if (!PdfjsLib) {
            showError('PDF.js library failed to load. Please refresh the page or contact support.');
            return;
        }

        disableContextMenu();
        loadDocument();
        bindNavigation();
        bindZoom();
    };

    /**
     * Load the PDF document from serve_document.php.
     */
    var loadDocument = function() {
        showLoading(true);
        hideError();

        var loadingTask = PdfjsLib.getDocument({
            url: cfg.serveUrl,
            withCredentials: true,
            isEvalSupported: false,
        });

        loadingTask.promise.then(function(pdf) {
            pdfDoc = pdf;
            var countEl = document.getElementById('dsl-isp-page-count');
            if (countEl) {
                countEl.textContent = pdf.numPages;
            }
            updateNavButtons();
            renderPage(1);
        }).catch(function(err) {
            showLoading(false);
            showError(err.message || 'Failed to load document.');
        });
    };

    /**
     * Render a specific page onto the canvas.
     *
     * @param {number} pageNum The 1-indexed page number to render.
     */
    var renderPage = function(pageNum) {
        if (rendering) {
            return;
        }
        rendering = true;
        showLoading(true);

        pdfDoc.getPage(pageNum).then(function(page) {
            var canvas = document.getElementById('dsl-isp-pdf-canvas');
            if (!canvas) {
                rendering = false;
                return;
            }

            var viewport = page.getViewport({scale: currentScale});
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            var ctx = canvas.getContext('2d');
            var renderCtx = {
                canvasContext: ctx,
                viewport: viewport,
            };

            page.render(renderCtx).promise.then(function() {
                drawWatermark(ctx, canvas.width, canvas.height);
                currentPage = pageNum;
                var numEl = document.getElementById('dsl-isp-page-num');
                if (numEl) {
                    numEl.value = pageNum;
                }
                updateNavButtons();
                showLoading(false);
                rendering = false;
            }).catch(function(err) {
                showLoading(false);
                rendering = false;
                showError(err.message || 'Failed to render page.');
            });
        }).catch(function(err) {
            showLoading(false);
            rendering = false;
            showError(err.message || 'Failed to get page.');
        });
    };

    /**
     * Draw a tiled, rotated watermark over the full canvas.
     *
     * The watermark is baked into the canvas pixel data — it is not a separate
     * DOM layer. Any screenshot or photograph of the rendered page will contain
     * the viewer identity information on every tile.
     *
     * @param {CanvasRenderingContext2D} ctx The canvas 2D context.
     * @param {number} w Canvas width in pixels.
     * @param {number} h Canvas height in pixels.
     */
    var drawWatermark = function(ctx, w, h) {
        var lines = [
            cfg.viewerName || '',
            cfg.viewerEmail || '',
            cfg.tenantName || '',
            cfg.timestamp || '',
            'Session #' + (cfg.sessionId || ''),
        ];

        var fontSize = Math.max(11, Math.round(w * 0.018));
        var lineHeight = Math.round(fontSize * 1.6);
        var blockHeight = lines.length * lineHeight;

        // Tile spacing: watermark repeats every ~30% of the canvas in each axis.
        var tileX = Math.round(w * 0.35);
        var tileY = Math.round(h * 0.28);

        var angle = -(Math.PI / 5.5); // ~32 degrees.

        ctx.save();
        ctx.globalAlpha = 0.12;
        ctx.fillStyle = '#333333';
        ctx.font = fontSize + 'px Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        // Tile the watermark across the full canvas.
        // Start offset ensures coverage even near edges.
        var startX = -tileX;
        var startY = -tileY;

        for (var tx = startX; tx < w + tileX * 2; tx += tileX) {
            for (var ty = startY; ty < h + tileY * 2; ty += tileY) {
                ctx.save();
                ctx.translate(tx, ty);
                ctx.rotate(angle);

                lines.forEach(function(line, i) {
                    var yOffset = (i * lineHeight) - (blockHeight / 2) + (lineHeight / 2);
                    ctx.fillText(line, 0, yOffset);
                });

                ctx.restore();
            }
        }

        ctx.restore();
    };

    /**
     * Bind previous and next page button events.
     */
    var bindNavigation = function() {
        var prevBtn = document.getElementById('dsl-isp-prev-page');
        var nextBtn = document.getElementById('dsl-isp-next-page');
        var pageInput = document.getElementById('dsl-isp-page-num');

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentPage > 1 && !rendering) {
                    renderPage(currentPage - 1);
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (pdfDoc && currentPage < pdfDoc.numPages && !rendering) {
                    renderPage(currentPage + 1);
                }
            });
        }

        if (pageInput) {
            pageInput.addEventListener('change', function() {
                var requested = parseInt(pageInput.value, 10);
                if (pdfDoc && requested >= 1 && requested <= pdfDoc.numPages && !rendering) {
                    renderPage(requested);
                } else {
                    pageInput.value = currentPage;
                }
            });
        }
    };

    /**
     * Bind zoom control button events.
     */
    var bindZoom = function() {
        var zoomInBtn = document.getElementById('dsl-isp-zoom-in');
        var zoomOutBtn = document.getElementById('dsl-isp-zoom-out');
        var fitWidthBtn = document.getElementById('dsl-isp-fit-width');

        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function() {
                currentScale = Math.min(currentScale + 0.25, 4.0);
                if (pdfDoc && !rendering) {
                    renderPage(currentPage);
                }
            });
        }

        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function() {
                currentScale = Math.max(currentScale - 0.25, 0.5);
                if (pdfDoc && !rendering) {
                    renderPage(currentPage);
                }
            });
        }

        if (fitWidthBtn) {
            fitWidthBtn.addEventListener('click', function() {
                var container = document.getElementById('dsl-isp-canvas-container');
                if (container && pdfDoc) {
                    pdfDoc.getPage(currentPage).then(function(page) {
                        var naturalViewport = page.getViewport({scale: 1.0});
                        currentScale = (container.clientWidth - 20) / naturalViewport.width;
                        if (!rendering) {
                            renderPage(currentPage);
                        }
                    });
                }
            });
        }
    };

    /**
     * Update prev/next button disabled states based on current page.
     */
    var updateNavButtons = function() {
        var prevBtn = document.getElementById('dsl-isp-prev-page');
        var nextBtn = document.getElementById('dsl-isp-next-page');

        if (prevBtn) {
            prevBtn.disabled = (currentPage <= 1);
        }
        if (nextBtn) {
            nextBtn.disabled = (!pdfDoc || currentPage >= pdfDoc.numPages);
        }
    };

    /**
     * Disable right-click context menu on the viewer container.
     */
    var disableContextMenu = function() {
        var container = document.getElementById('dsl-isp-pdf-viewer');
        if (container) {
            container.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                return false;
            });
        }
    };

    /**
     * Show or hide the loading indicator.
     *
     * @param {boolean} show True to show, false to hide.
     */
    var showLoading = function(show) {
        var el = document.getElementById('dsl-isp-pdf-loading');
        if (el) {
            if (show) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        }
    };

    /**
     * Show an error message in the viewer error zone.
     *
     * @param {string} message The error message.
     */
    var showError = function(message) {
        var el = document.getElementById('dsl-isp-pdf-error');
        if (el) {
            el.textContent = message;
            el.classList.remove('d-none');
        }
        showLoading(false);
    };

    /**
     * Hide the error zone.
     */
    var hideError = function() {
        var el = document.getElementById('dsl-isp-pdf-error');
        if (el) {
            el.classList.add('d-none');
            el.textContent = '';
        }
    };

    return {
        init: init,
    };
});
