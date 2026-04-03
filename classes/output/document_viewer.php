<?php
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

namespace local_dsl_isp\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Renderable for the PHI-protected PDF document viewer page.
 *
 * Provides template context for document_viewer.mustache. Watermark data and the
 * serve URL are passed separately to the AMD module via js_call_amd in view_document.php
 * and are not included in the template context.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_viewer implements renderable, templatable {

    /** @var stdClass The dsl_isp_document record. */
    protected stdClass $document;

    /**
     * Constructor.
     *
     * @param stdClass $document The dsl_isp_document record.
     */
    public function __construct(stdClass $document) {
        $this->document = $document;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context data.
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'docname' => $this->document->name,
            'docid'   => (int) $this->document->id,
        ];
    }
}
