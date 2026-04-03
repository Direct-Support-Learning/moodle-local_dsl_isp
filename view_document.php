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

/**
 * PHI-protected PDF document viewer page.
 *
 * Displays an ISP document through the secure canvas-based PDF.js viewer with
 * per-session tiled watermarking. A view log record is inserted before the page
 * renders, providing the session ID used in the watermark text.
 *
 * Access requirements:
 *  - User must be logged in (require_login).
 *  - User must be enrolled in the document's associated course.
 *  - User must have local/dsl_isp:viewdocuments in that course context.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

require_login();

$docid = required_param('docid', PARAM_INT);

// Load the document and client records.
$document = $DB->get_record('dsl_isp_document', ['id' => $docid], '*', MUST_EXIST);
$client = $DB->get_record('dsl_isp_client', ['id' => $document->clientid], '*', MUST_EXIST);

// Check feature gate.
\local_dsl_isp\feature_gate::require_enabled($client->tenantid);

// Verify enrollment and capability in the client's course context.
$coursecontext = context_course::instance($client->courseid);

if (!is_enrolled($coursecontext, $USER)) {
    throw new moodle_exception('error_permissiondenied', 'local_dsl_isp');
}

require_capability('local/dsl_isp:viewdocuments', $coursecontext);

// Resolve tenant name for watermark (snapshot at time of view).
$tenantname = $DB->get_field('tool_tenant', 'name', ['id' => $client->tenantid]) ?: '';

// Insert view log record before rendering so we have the session ID for the watermark.
$now = time();
$viewrecord = new stdClass();
$viewrecord->documentid = $document->id;
$viewrecord->userid = $USER->id;
$viewrecord->courseid = $client->courseid;
$viewrecord->tenantid = $client->tenantid;
$viewrecord->viewername = fullname($USER);
$viewrecord->vieweremail = $USER->email;
$viewrecord->tenantname = $tenantname;
$viewrecord->timecreated = $now;
$viewrecord->useragent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
$viewrecord->ipaddress = getremoteaddr();
$viewid = $DB->insert_record('dsl_isp_doc_views', $viewrecord);

// Page setup.
$PAGE->set_url('/local/dsl_isp/view_document.php', ['docid' => $docid]);
$PAGE->set_context($coursecontext);
$PAGE->set_pagelayout('base');
$PAGE->set_title($document->name);
$PAGE->set_heading($document->name);

// Load the PDF.js library as a global script (must be before AMD module call).
$PAGE->requires->js('/local/dsl_isp/third_party/pdfjs/pdf.min.js');

// Build the serve URL including the sesskey for CSRF protection.
$serveurl = new moodle_url('/local/dsl_isp/serve_document.php', [
    'docid'   => $docid,
    'sesskey' => sesskey(),
]);

// Build watermark data for the AMD module.
$watermarkdata = [
    'viewerName'  => fullname($USER),
    'viewerEmail' => $USER->email,
    'tenantName'  => $tenantname,
    'timestamp'   => userdate($now, get_string('strftimedatetimeshort')),
    'sessionId'   => (int) $viewid,
    'serveUrl'    => $serveurl->out(false),
    'docName'     => $document->name,
];

// Render the page.
$renderable = new \local_dsl_isp\output\document_viewer($document);
$renderer = $PAGE->get_renderer('local_dsl_isp');

echo $OUTPUT->header();

// Back-to-course breadcrumb link.
$courseurl = new moodle_url('/course/view.php', ['id' => $client->courseid]);
echo html_writer::tag('p',
    html_writer::link($courseurl, get_string('back', 'local_dsl_isp')),
    ['class' => 'mb-2']
);

echo $renderer->render($renderable);

// Initialise the AMD viewer with watermark data.
$PAGE->requires->js_call_amd('local_dsl_isp/document_viewer', 'init', [$watermarkdata]);

echo $OUTPUT->footer();
