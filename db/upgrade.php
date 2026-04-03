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
 * Upgrade script for local_dsl_isp.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The old version number.
 * @return bool True on success.
 */
function xmldb_local_dsl_isp_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026040200) {

        // Define table dsl_isp_document.
        $table = new xmldb_table('dsl_isp_document');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('clientid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('slot', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filedate', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('uploadedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_clientid', XMLDB_KEY_FOREIGN, ['clientid'], 'dsl_isp_client', ['id']);
        $table->add_key('fk_courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('fk_uploadedby', XMLDB_KEY_FOREIGN, ['uploadedby'], 'user', ['id']);

        $table->add_index('ix_clientid_slot', XMLDB_INDEX_UNIQUE, ['clientid', 'slot']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table dsl_isp_doc_views.
        $viewtable = new xmldb_table('dsl_isp_doc_views');

        $viewtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $viewtable->add_field('documentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $viewtable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $viewtable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $viewtable->add_field('tenantid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $viewtable->add_field('viewername', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $viewtable->add_field('vieweremail', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $viewtable->add_field('tenantname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $viewtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $viewtable->add_field('useragent', XMLDB_TYPE_CHAR, '512', null, null, null, null);
        $viewtable->add_field('ipaddress', XMLDB_TYPE_CHAR, '45', null, null, null, null);

        $viewtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $viewtable->add_index('ix_documentid_timecreated', XMLDB_INDEX_NOTUNIQUE, ['documentid', 'timecreated']);
        $viewtable->add_index('ix_userid_timecreated', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
        $viewtable->add_index('ix_tenantid_timecreated', XMLDB_INDEX_NOTUNIQUE, ['tenantid', 'timecreated']);

        if (!$dbman->table_exists($viewtable)) {
            $dbman->create_table($viewtable);
        }

        upgrade_plugin_savepoint(true, 2026040200, 'local', 'dsl_isp');
    }

    return true;
}
