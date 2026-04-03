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

namespace local_dsl_isp\task;

/**
 * Scheduled task to prune old document view log records.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_view_logs extends \core\task\scheduled_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanupviewlogs', 'local_dsl_isp');
    }

    /**
     * Execute the task.
     *
     * Deletes dsl_isp_doc_views records older than the configured retention period.
     */
    public function execute(): void {
        global $DB;

        $days = (int) get_config('local_dsl_isp', 'log_retention_days');

        if ($days <= 0) {
            $days = 365;
        }

        $cutoff = time() - ($days * DAYSECS);

        $deleted = $DB->count_records_select('dsl_isp_doc_views', 'timecreated < :cutoff', ['cutoff' => $cutoff]);

        $DB->delete_records_select('dsl_isp_doc_views', 'timecreated < :cutoff', ['cutoff' => $cutoff]);

        mtrace("Deleted {$deleted} document view log record(s) older than {$days} days.");
    }
}
