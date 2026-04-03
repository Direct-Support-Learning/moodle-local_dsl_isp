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
 * AMD shim for the bundled PDF.js library.
 *
 * PDF.js is loaded as a plain script via $PAGE->requires->js() in view_document.php,
 * which makes pdfjsLib available as a global. This module configures the worker path
 * and returns the library object for use by document_viewer.js.
 *
 * @module     local_dsl_isp/pdfjs_loader
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var lib = window.pdfjsLib;

    if (lib) {
        lib.GlobalWorkerOptions.workerSrc =
            M.cfg.wwwroot + '/local/dsl_isp/third_party/pdfjs/pdf.worker.min.js';
    }

    return lib;
});
