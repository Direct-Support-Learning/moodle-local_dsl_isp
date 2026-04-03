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
 * Serve ISP document bytes to the secure PDF viewer.
 *
 * This endpoint streams the raw PDF bytes to the browser for PDF.js to render.
 * It is not a Moodle page — it produces no HTML output and sends no cookies
 * in the response body. The Moodle session cookie must be present in the request
 * for sesskey validation to work (withCredentials: true in PDF.js).
 *
 * Security checks:
 *  - require_login(): user must be authenticated.
 *  - require_sesskey(): CSRF protection via sesskey GET parameter.
 *  - Enrollment check: user must be enrolled in the document's associated course.
 *  - Capability check: user must have local/dsl_isp:viewdocuments in course context.
 *
 * No Content-Disposition header is sent — this prevents the browser from offering
 * a "Save As" dialog for the PDF. The response type is application/pdf so PDF.js
 * can load it, but the browser does not treat it as a downloadable file.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();

$docid = required_param('docid', PARAM_INT);
require_sesskey();

// Load the document record.
$document = $DB->get_record('dsl_isp_document', ['id' => $docid], '*', MUST_EXIST);

// Load the client to get course context for access checks.
$client = $DB->get_record('dsl_isp_client', ['id' => $document->clientid], '*', MUST_EXIST);

// Verify enrollment and capability.
$coursecontext = context_course::instance($client->courseid);

if (!is_enrolled($coursecontext, $USER) || !has_capability('local/dsl_isp:viewdocuments', $coursecontext)) {
    send_file_not_found();
}

// Retrieve the stored file from the private filearea.
$fs = get_file_storage();
$syscontext = context_system::instance();

$file = $fs->get_file(
    $syscontext->id,
    'local_dsl_isp',
    'isp_documents',
    $document->itemid,
    '/',
    $document->filename
);

if (!$file || $file->is_directory()) {
    send_file_not_found();
}

// Stream the file with anti-caching and no download affordance.
// No Content-Disposition header = browser will not offer "Save As" for the PDF.
header('Content-Type: application/pdf');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Length: ' . $file->get_filesize());

$file->readfile();
die();
