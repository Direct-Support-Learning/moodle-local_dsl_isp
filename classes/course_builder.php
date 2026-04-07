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

namespace local_dsl_isp;

use stdClass;
use moodle_exception;
use stored_file;
use context_course;
use context_module;
use context_system;
use core_external\external_api;

/**
 * Course builder for ISP Manager.
 *
 * Handles course creation from template, document replacement, and custom field management.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_builder {

    /** @var array Document slot definitions matching Oregon ISP requirements. */
    public const DOCUMENT_SLOTS = [
        1 => [
            'name' => 'One Page Profile',
            'shortname' => 'onepageprofile',
            'required' => true,
            'fieldname' => 'ispdoc1',
        ],
        2 => [
            'name' => 'Individual Support Plan',
            'shortname' => 'individualsupportplan',
            'required' => true,
            'fieldname' => 'ispdoc2',
        ],
        3 => [
            'name' => 'Person Centered Information',
            'shortname' => 'personcenteredinfo',
            'required' => true,
            'fieldname' => 'ispdoc3',
        ],
        4 => [
            'name' => 'Safety Plan / Risk Management Plan',
            'shortname' => 'safetyplan',
            'required' => true,
            'fieldname' => 'ispdoc4',
        ],
        5 => [
            'name' => 'Provider Risk Management Strategies',
            'shortname' => 'providerrms',
            'required' => true,
            'fieldname' => 'ispdoc5',
        ],
        6 => [
            'name' => 'Risk Identification Tool',
            'shortname' => 'rit',
            'required' => false, // Conditional.
            'fieldname' => 'ispdoc6',
        ],
        7 => [
            'name' => 'Action Plan',
            'shortname' => 'actionplan',
            'required' => false,
            'fieldname' => 'ispdoc7',
            'repeatable' => true,
            'maxrepeat' => 3,
        ],
        8 => [
            'name' => 'Support Document / Protocol',
            'shortname' => 'supportdoc',
            'required' => false,
            'fieldname' => 'ispdoc8',
            'repeatable' => true,
            'maxrepeat' => 5,
        ],
    ];

    /** @var int|null Cached template course ID. */
    protected ?int $templatecourseid = null;

    /**
     * Get the configured template course ID.
     *
     * @return int The template course ID.
     * @throws moodle_exception If template course is not configured.
     */
    public function get_template_course_id(): int {
        if ($this->templatecourseid !== null) {
            return $this->templatecourseid;
        }

        // Try cache first.
        $cache = \cache::make('local_dsl_isp', 'config');
        $cachedid = $cache->get('template_course_id');

        if ($cachedid !== false) {
            $this->templatecourseid = (int) $cachedid;
            return $this->templatecourseid;
        }

        // Get from config.
        $configid = get_config('local_dsl_isp', 'template_course_id');

        if (empty($configid)) {
            throw new moodle_exception('error_templatecoursenotconfigured', 'local_dsl_isp');
        }

        $this->templatecourseid = (int) $configid;
        $cache->set('template_course_id', $this->templatecourseid);

        return $this->templatecourseid;
    }

    /**
     * Create a new ISP course from the template.
     *
     * Uses Moodle's internal duplicate_course function to copy the template course.
     * Temporarily elevates to admin context for the duplication since tenant admins
     * cannot access the cross-tenant template course directly.
     *
     * @param string $coursename The name for the new course.
     * @param int $startdate The course start date (anniversary date).
     * @param int $tenantid The tenant ID.
     * @return int The new course ID.
     * @throws moodle_exception On failure.
     */
    public function create_course_from_template(string $coursename, int $startdate, int $tenantid): int {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/course/externallib.php');

        $templateid = $this->get_template_course_id();

        // Verify template course exists.
        $templatecourse = $DB->get_record('course', ['id' => $templateid], '*', MUST_EXIST);

        // Generate unique shortname.
        $shortname = $this->generate_unique_shortname($coursename);

        // Get or create the ISP category for this tenant.
        $categoryid = $this->get_or_create_isp_category($tenantid);

        // Store original user ID for restoration (not reference - $USER is global).
        $originaluserid = (int)$USER->id;

        // Get a site admin to perform the duplication.
        $admins = get_admins();
        $admin = reset($admins);

        if (!$admin) {
            throw new moodle_exception('error_coursecreationfailed', 'local_dsl_isp', '', null, 'No admin user found');
        }

        $newcourseid = null;
        $error = null;

        try {
            // Temporarily switch to admin user for the duplication.
            \core\session\manager::set_user($admin);

            // Duplicate the template course via the stable external API.
            $result = \core_course_external::duplicate_course(
                $templateid,
                $coursename,
                $shortname,
                $categoryid,
                1, // visible
                [
                    ['name' => 'users',            'value' => '0'],
                    ['name' => 'activities',       'value' => '1'],
                    ['name' => 'blocks',           'value' => '1'],
                    ['name' => 'filters',          'value' => '1'],
                    ['name' => 'role_assignments', 'value' => '0'],
                    ['name' => 'comments',         'value' => '0'],
                    ['name' => 'userscompletion',  'value' => '0'],
                    ['name' => 'logs',             'value' => '0'],
                    ['name' => 'grade_histories',  'value' => '0'],
                ]
            );

            // duplicate_course returns stdClass when called internally, array via web service.
            $resultid = is_object($result) ? ($result->id ?? null) : ($result['id'] ?? null);
            if (empty($resultid)) {
                $error = 'Course duplication returned empty result';
            } else {
                $newcourseid = (int) $resultid;
            }

        } catch (\Exception $e) {
            $error = $e->getMessage();
        } finally {
            // ALWAYS restore original user, no matter what.
            $originaluser = $DB->get_record('user', ['id' => $originaluserid]);
            if ($originaluser) {
                \core\session\manager::set_user($originaluser);
            }
        }

        // Now throw exception if there was an error (after user is restored).
        if ($error !== null) {
            throw new moodle_exception('error_coursecreationfailed', 'local_dsl_isp', '', null, $error);
        }

        // Update course start date.
        $DB->set_field('course', 'startdate', $startdate, ['id' => $newcourseid]);

        // Rebuild course cache.
        rebuild_course_cache($newcourseid, true);

        return $newcourseid;
    }

    /**
     * Update course settings.
     *
     * @param int $courseid The course ID.
     * @param array $settings Settings to update.
     */
    public function update_course_settings(int $courseid, array $settings): void {
        $courseupdate = [
            'id' => $courseid,
        ];

        foreach ($settings as $key => $value) {
            $courseupdate[$key] = $value;
        }

        $result = external_api::call_external_function(
            'core_course_update_courses',
            ['courses' => [$courseupdate]],
            false
        );

        if (!empty($result['error'])) {
            debugging('Failed to update course settings: ' . ($result['exception']->message ?? 'Unknown error'), DEBUG_DEVELOPER);
        }
    }

    /**
     * Update course name.
     *
     * @param int $courseid The course ID.
     * @param string $fullname The new course name.
     */
    public function update_course_name(int $courseid, string $fullname): void {
        $this->update_course_settings($courseid, ['fullname' => $fullname]);
    }

    /**
     * Replace a document in a course's document slot.
     *
     * Stores the PDF in the private isp_documents filearea, upserts the dsl_isp_document
     * record, and ensures the course has a URL activity pointing to the secure viewer.
     *
     * @param int $clientid The client ID (FK dsl_isp_client).
     * @param int $courseid The course ID.
     * @param int $slotindex The document slot index (1-8).
     * @param stored_file $newfile The new file to store.
     * @param string $documentname The document display name.
     * @param string $documentdate The document date string.
     * @return bool True on success.
     */
    public function replace_document(
        int $clientid,
        int $courseid,
        int $slotindex,
        stored_file $newfile,
        string $documentname,
        string $documentdate
    ): bool {
        global $DB, $CFG, $USER;

        if (!isset(self::DOCUMENT_SLOTS[$slotindex])) {
            throw new moodle_exception('error_documentreplacefailed', 'local_dsl_isp');
        }

        $slot = self::DOCUMENT_SLOTS[$slotindex];

        // Upsert the document record and get its ID (= itemid for the filearea).
        $documentid = $this->upsert_document_record(
            $clientid,
            $courseid,
            $slotindex,
            $documentname,
            $documentdate,
            $newfile->get_filename(),
            $USER->id
        );

        // Store the file in the private filearea.
        $this->store_document_file($newfile, $documentid);

        // Build the viewer URL.
        $viewurl = $CFG->wwwroot . '/local/dsl_isp/view_document.php?docid=' . $documentid;

        // Find any existing activity for this slot.
        $cm = $this->find_activity_by_slot($courseid, $slot['shortname']);

        if ($cm !== null && $cm->modulename === 'resource') {
            // Migrate: delete the old File activity, create a URL activity.
            require_once($CFG->dirroot . '/course/lib.php');
            course_delete_module($cm->id);
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $this->create_url_activity($course, $documentname, $viewurl, $slot['shortname']);
        } else if ($cm !== null && $cm->modulename === 'url') {
            // Update existing URL activity to point to the new document.
            $DB->set_field('url', 'externalurl', $viewurl, ['id' => $cm->instance]);
            $DB->set_field('url', 'name', $documentname, ['id' => $cm->instance]);
        } else {
            // No existing activity — create a new URL activity.
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $this->create_url_activity($course, $documentname, $viewurl, $slot['shortname']);
        }

        // Update the custom course field (legacy metadata, kept for compatibility).
        $fieldvalue = trim($documentname . ' ' . $documentdate);
        $this->update_course_custom_field($courseid, $slot['fieldname'], $fieldvalue);

        return true;
    }

    /**
     * Upsert a dsl_isp_document record for a given client/slot combination.
     *
     * On insert: creates the record and then sets itemid = id (self-referencing itemid).
     * On update: updates metadata, clears old file, keeps existing itemid (= document id).
     *
     * @param int $clientid Client ID.
     * @param int $courseid Course ID.
     * @param int $slot Slot index.
     * @param string $name Document display name.
     * @param string $filedate Document date string.
     * @param string $filename Original filename.
     * @param int $uploadedby User ID of uploader.
     * @return int The document record ID (also the filearea itemid).
     */
    protected function upsert_document_record(
        int $clientid,
        int $courseid,
        int $slot,
        string $name,
        string $filedate,
        string $filename,
        int $uploadedby
    ): int {
        global $DB;

        $existing = $DB->get_record('dsl_isp_document', ['clientid' => $clientid, 'slot' => $slot]);

        $now = time();

        if ($existing) {
            // Delete old stored file before overwriting.
            $fs = get_file_storage();
            $fs->delete_area_files(
                context_system::instance()->id,
                'local_dsl_isp',
                'isp_documents',
                $existing->itemid
            );

            $existing->name = $name;
            $existing->filedate = $filedate;
            $existing->filename = $filename;
            $existing->timemodified = $now;
            $existing->uploadedby = $uploadedby;
            $DB->update_record('dsl_isp_document', $existing);

            return (int) $existing->id;
        }

        // Insert new record.
        $record = new stdClass();
        $record->clientid = $clientid;
        $record->courseid = $courseid;
        $record->slot = $slot;
        $record->name = $name;
        $record->filedate = $filedate;
        $record->filename = $filename;
        $record->itemid = 0; // Placeholder — updated below.
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->uploadedby = $uploadedby;

        $id = $DB->insert_record('dsl_isp_document', $record);

        // Set itemid to equal the record's own ID.
        $DB->set_field('dsl_isp_document', 'itemid', $id, ['id' => $id]);

        return (int) $id;
    }

    /**
     * Store a PDF file in the private isp_documents filearea.
     *
     * Files are stored at context_system under local_dsl_isp/isp_documents with itemid
     * equal to the dsl_isp_document record ID. Any existing file at that itemid is
     * replaced (handled by upsert_document_record before calling this).
     *
     * @param stored_file $newfile The source file to copy.
     * @param int $documentid The dsl_isp_document record ID (used as itemid).
     * @return stored_file The newly created stored file.
     */
    protected function store_document_file(stored_file $newfile, int $documentid): stored_file {
        $fs = get_file_storage();

        $filerecord = [
            'contextid' => context_system::instance()->id,
            'component' => 'local_dsl_isp',
            'filearea'  => 'isp_documents',
            'itemid'    => $documentid,
            'filepath'  => '/',
            'filename'  => $newfile->get_filename(),
        ];

        return $fs->create_file_from_storedfile($filerecord, $newfile);
    }

    /**
     * Find a course module for a document slot by its idnumber.
     *
     * Searches both mod_resource and mod_url activities.
     *
     * @param int $courseid The course ID.
     * @param string $idnumber The slot shortname used as cm.idnumber.
     * @return stdClass|null The cm record with a 'modulename' property, or null.
     */
    protected function find_activity_by_slot(int $courseid, string $idnumber): ?stdClass {
        global $DB;

        $sql = "SELECT cm.*, m.name AS modulename
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND cm.idnumber = :idnumber
                   AND m.name IN ('resource', 'url')";

        $cm = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'idnumber' => $idnumber,
        ]);

        return $cm ?: null;
    }

    /**
     * Create a URL activity in a course pointing to the secure document viewer.
     *
     * @param stdClass $course The course record.
     * @param string $name The activity display name.
     * @param string $url The external URL to link to.
     * @param string $idnumber The cm idnumber (slot shortname) to set for future lookups.
     * @return stdClass The created course module record.
     */
    protected function create_url_activity(stdClass $course, string $name, string $url, string $idnumber): stdClass {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/modlib.php');

        $moduleid = $DB->get_field('modules', 'id', ['name' => 'url'], MUST_EXIST);

        $moduleinfo = new stdClass();
        $moduleinfo->modulename = 'url';
        $moduleinfo->module = $moduleid;
        $moduleinfo->name = $name;
        $moduleinfo->externalurl = $url;
        $moduleinfo->display = 0; // RESOURCELIB_DISPLAY_OPEN — open in same frame.
        $moduleinfo->course = $course->id;
        $moduleinfo->section = 0;
        $moduleinfo->visible = 1;
        $moduleinfo->idnumber = $idnumber;

        [$cm] = add_moduleinfo($moduleinfo, $course);

        // Verify idnumber was saved; set it explicitly if not.
        $savedidnumber = $DB->get_field('course_modules', 'idnumber', ['id' => $cm->id]);
        if ($savedidnumber !== $idnumber) {
            $DB->set_field('course_modules', 'idnumber', $idnumber, ['id' => $cm->id]);
        }

        return $cm;
    }

    /**
     * Get the dsl_isp_document record for a given client and slot.
     *
     * @param int $clientid The client ID.
     * @param int $slotindex The slot index (1-8).
     * @return stdClass|null The document record or null if not found.
     */
    public function get_document_record(int $clientid, int $slotindex): ?stdClass {
        global $DB;

        $record = $DB->get_record('dsl_isp_document', ['clientid' => $clientid, 'slot' => $slotindex]);

        return $record ?: null;
    }

    /**
     * Update a custom course field value.
     *
     * @param int $courseid The course ID.
     * @param string $fieldshortname The field shortname.
     * @param string $value The value to set.
     */
    protected function update_course_custom_field(int $courseid, string $fieldshortname, string $value): void {
        global $DB;

        // Get the field definition.
        $field = $DB->get_record('customfield_field', ['shortname' => $fieldshortname]);

        if (!$field) {
            debugging("Custom field '{$fieldshortname}' not found", DEBUG_DEVELOPER);
            return;
        }

        // Get or create the data record.
        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $courseid,
        ]);

        if ($data) {
            $data->value = $value;
            $data->valueformat = FORMAT_PLAIN;
            $data->timemodified = time();
            $DB->update_record('customfield_data', $data);
        } else {
            $data = new stdClass();
            $data->fieldid = $field->id;
            $data->instanceid = $courseid;
            $data->contextid = context_course::instance($courseid)->id;
            $data->value = $value;
            $data->valueformat = FORMAT_PLAIN;
            $data->timecreated = time();
            $data->timemodified = time();
            $DB->insert_record('customfield_data', $data);
        }
    }

    /**
     * Get the current value of a custom course field.
     *
     * @param int $courseid The course ID.
     * @param string $fieldshortname The field shortname.
     * @return string|null The field value or null.
     */
    public function get_course_custom_field(int $courseid, string $fieldshortname): ?string {
        global $DB;

        $sql = "SELECT cd.value
                  FROM {customfield_data} cd
                  JOIN {customfield_field} cf ON cf.id = cd.fieldid
                 WHERE cd.instanceid = :courseid
                   AND cf.shortname = :shortname";

        $value = $DB->get_field_sql($sql, [
            'courseid' => $courseid,
            'shortname' => $fieldshortname,
        ]);

        return $value ?: null;
    }

    /**
     * Parse PDF ModDate from file content.
     *
     * Uses lightweight byte-level parsing to extract the /ModDate value.
     *
     * @param stored_file $file The PDF file.
     * @return int|null Unix timestamp or null if parsing fails.
     */
    public function parse_pdf_mod_date(stored_file $file): ?int {
        $content = $file->get_content();

        // Search for /ModDate pattern in PDF.
        // Format: /ModDate (D:YYYYMMDDHHmmSS+HH'mm') or similar variations.
        if (preg_match('/\/ModDate\s*\(D:(\d{4})(\d{2})(\d{2})/', $content, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            // Sanity checks.
            if ($year < 1990 || $year > 2100 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
                return null;
            }

            // Check for suspicious dates.
            $timestamp = mktime(0, 0, 0, $month, $day, $year);

            // Reject dates in 1970 (Unix epoch issues) or future dates.
            if ($year === 1970 || $timestamp > time()) {
                return null;
            }

            return $timestamp;
        }

        return null;
    }

    /**
     * Get document information for a course's document slots.
     *
     * Prefers the dsl_isp_document table (new system) for file metadata.
     * Falls back to the mod_resource filearea for legacy records not yet migrated.
     *
     * @param int $courseid The course ID.
     * @param int|null $clientid Client ID for new-system lookups (optional).
     * @return array Array of document slot data.
     */
    public function get_course_documents(int $courseid, ?int $clientid = null): array {
        global $DB;

        $documents = [];
        $fs = get_file_storage();
        $syscontextid = context_system::instance()->id;

        foreach (self::DOCUMENT_SLOTS as $index => $slot) {
            $docinfo = [
                'slot' => $index,
                'name' => $slot['name'],
                'shortname' => $slot['shortname'],
                'required' => $slot['required'],
                'hasfile' => false,
                'filename' => null,
                'filesize' => null,
                'timemodified' => null,
                'fieldvalue' => null,
                'documentid' => null,
            ];

            // Check the new dsl_isp_document table first.
            if ($clientid !== null) {
                $docrec = $DB->get_record('dsl_isp_document', ['clientid' => $clientid, 'slot' => $index]);
                if ($docrec && !empty($docrec->itemid)) {
                    $files = $fs->get_area_files($syscontextid, 'local_dsl_isp', 'isp_documents', $docrec->itemid, '', false);
                    if (!empty($files)) {
                        $file = reset($files);
                        $docinfo['hasfile'] = true;
                        $docinfo['filename'] = $file->get_filename();
                        $docinfo['filesize'] = $file->get_filesize();
                        $docinfo['timemodified'] = $file->get_timemodified();
                        $docinfo['documentid'] = (int) $docrec->id;
                    }
                }
            }

            // Fall back to legacy mod_resource filearea if no new-system record.
            if (!$docinfo['hasfile']) {
                $cm = $this->find_activity_by_slot($courseid, $slot['shortname']);
                if ($cm && $cm->modulename === 'resource') {
                    $context = context_module::instance($cm->id);
                    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder', false);
                    if (!empty($files)) {
                        $file = reset($files);
                        $docinfo['hasfile'] = true;
                        $docinfo['filename'] = $file->get_filename();
                        $docinfo['filesize'] = $file->get_filesize();
                        $docinfo['timemodified'] = $file->get_timemodified();
                    }
                }
            }

            // Get custom field value.
            $docinfo['fieldvalue'] = $this->get_course_custom_field($courseid, $slot['fieldname']);

            $documents[$index] = $docinfo;
        }

        return $documents;
    }

    /**
     * Delete a course.
     *
     * @param int $courseid The course ID.
     * @return bool True on success.
     */
    public function delete_course(int $courseid): bool {
        try {
            $result = external_api::call_external_function(
                'core_course_delete_courses',
                ['courseids' => [$courseid]],
                false
            );

            return empty($result['error']);
        } catch (\Exception $e) {
            debugging('Failed to delete course: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Generate a unique course shortname.
     *
     * @param string $basename Base name to use.
     * @return string Unique shortname.
     */
    protected function generate_unique_shortname(string $basename): string {
        global $DB;

        // Clean the base name.
        $shortname = clean_param(substr($basename, 0, 200), PARAM_ALPHANUMEXT);
        $shortname = str_replace(' ', '_', $shortname);

        // Add timestamp for uniqueness.
        $shortname .= '_' . time();

        // Ensure it doesn't exist.
        $counter = 0;
        $candidate = $shortname;
        while ($DB->record_exists('course', ['shortname' => $candidate])) {
            $counter++;
            $candidate = $shortname . '_' . $counter;
        }

        return $candidate;
    }

    /** @var string The name of the ISP subcategory within tenant categories. */
    const ISP_CATEGORY_NAME = 'ISP & Supporting Documents';

    /**
     * Get or create the ISP category for a tenant.
     *
     * Looks for a subcategory named "ISP & Supporting Documents" under the tenant's
     * category. If it doesn't exist, creates it.
     *
     * @param int $tenantid The tenant ID.
     * @return int The category ID for ISP courses.
     * @throws moodle_exception If tenant category is not configured.
     */
    protected function get_or_create_isp_category(int $tenantid): int {
        global $DB;

        // Get the tenant's category ID.
        $tenantcategoryid = $DB->get_field('tool_tenant', 'categoryid', ['id' => $tenantid]);

        if (empty($tenantcategoryid)) {
            throw new moodle_exception('error_tenantcategorynotconfigured', 'local_dsl_isp');
        }

        // Look for existing ISP subcategory.
        $ispcategory = $DB->get_record('course_categories', [
            'parent' => $tenantcategoryid,
            'name' => self::ISP_CATEGORY_NAME,
        ]);

        if ($ispcategory) {
            return (int) $ispcategory->id;
        }

        // Create the ISP subcategory.
        $categorydata = (object) [
            'name' => self::ISP_CATEGORY_NAME,
            'parent' => $tenantcategoryid,
            'description' => get_string('ispcategorydescription', 'local_dsl_isp'),
            'descriptionformat' => FORMAT_HTML,
        ];

        $newcategory = \core_course_category::create($categorydata);

        return (int) $newcategory->id;
    }

    /**
     * Get the document slot definitions.
     *
     * @return array The document slot definitions.
     */
    public static function get_document_slots(): array {
        return self::DOCUMENT_SLOTS;
    }
}
