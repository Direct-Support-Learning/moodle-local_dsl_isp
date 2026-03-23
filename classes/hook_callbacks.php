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

/**
 * Hook callbacks for ISP Manager.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Extend the primary navigation with the ISP Manager link.
     *
     * @param \core\hook\navigation\primary_extend $hook The hook instance.
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $systemcontext = \context_system::instance();
        $usercontext = \context_user::instance($USER->id);
        if (!has_capability('local/dsl_isp:view', $systemcontext) &&
                !has_capability('local/dsl_isp:view', $usercontext)) {
            return;
        }

        $tenantid = feature_gate::get_current_tenant_id();
        if (empty($tenantid) || !feature_gate::is_enabled($tenantid)) {
            return;
        }

        $hook->get_primarynav()->add(
            get_string('ispmanager', 'local_dsl_isp'),
            new \moodle_url('/local/dsl_isp/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_dsl_isp'
        );
    }
}
