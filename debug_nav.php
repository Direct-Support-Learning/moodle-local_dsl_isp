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
 * Debug page for ISP Manager navigation troubleshooting.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$PAGE->set_url(new moodle_url('/local/dsl_isp/debug_nav.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('ISP Manager Navigation Debug');
$PAGE->set_heading('ISP Manager Navigation Debug');

echo $OUTPUT->header();

echo '<h3>Navigation Conditions Check</h3>';
echo '<table class="table table-bordered">';

// Check 1: Logged in
$loggedin = isloggedin() && !isguestuser();
echo '<tr><td>User logged in (not guest)</td><td>' . ($loggedin ? '✅ Yes' : '❌ No') . '</td></tr>';

// Check 2: User ID
echo '<tr><td>User ID</td><td>' . $USER->id . '</td></tr>';
echo '<tr><td>Username</td><td>' . $USER->username . '</td></tr>';

// Check 3: Capability at system context
$systemcontext = context_system::instance();
$hascap_system = has_capability('local/dsl_isp:view', $systemcontext);
echo '<tr><td>Has local/dsl_isp:view at CONTEXT_SYSTEM</td><td>' . ($hascap_system ? '✅ Yes' : '❌ No') . '</td></tr>';

// Check 4: Capability at user context
$usercontext = context_user::instance($USER->id);
$hascap_user = has_capability('local/dsl_isp:view', $usercontext);
echo '<tr><td>Has local/dsl_isp:view at CONTEXT_USER</td><td>' . ($hascap_user ? '✅ Yes' : '❌ No') . '</td></tr>';

// Check 5: Combined capability check (as used in navigation)
$hascap_either = $hascap_system || $hascap_user;
echo '<tr><td>Has capability (either context)</td><td>' . ($hascap_either ? '✅ Yes' : '❌ No') . '</td></tr>';

// Check 6: Tenant API exists
$tenantapiexists = class_exists('\tool_tenant\tenancy');
echo '<tr><td>Workplace Tenant API exists</td><td>' . ($tenantapiexists ? '✅ Yes' : '❌ No') . '</td></tr>';

// Check 7: Current tenant ID
$tenantid = 0;
if ($tenantapiexists) {
    $tenantid = \tool_tenant\tenancy::get_tenant_id();
}
echo '<tr><td>Current tenant ID</td><td>' . ($tenantid > 0 ? $tenantid : '❌ 0 (none)') . '</td></tr>';

// Check 8: Tenant name
if ($tenantid > 0) {
    $tenantname = $DB->get_field('tool_tenant', 'name', ['id' => $tenantid]);
    echo '<tr><td>Tenant name</td><td>' . s($tenantname) . '</td></tr>';
}

// Check 9: ISP Manager enabled for tenant
$ispenabled = false;
if ($tenantid > 0) {
    $ispenabled = \local_dsl_isp\feature_gate::is_enabled($tenantid);
}
echo '<tr><td>ISP Manager enabled for tenant</td><td>' . ($ispenabled ? '✅ Yes' : '❌ No') . '</td></tr>';

// Check 10: All conditions pass
$allpass = $loggedin && $hascap_either && ($tenantid > 0) && $ispenabled;
echo '<tr class="' . ($allpass ? 'table-success' : 'table-danger') . '"><td><strong>All navigation conditions pass</strong></td><td>' . ($allpass ? '✅ Yes - Navigation SHOULD show' : '❌ No - Navigation will NOT show') . '</td></tr>';

echo '</table>';

// Show which condition is failing
if (!$allpass) {
    echo '<h4>Failing conditions:</h4><ul>';
    if (!$loggedin) {
        echo '<li>User not logged in or is guest</li>';
    }
    if (!$hascap_either) {
        echo '<li>User does not have local/dsl_isp:view capability in either context</li>';
    }
    if ($tenantid <= 0) {
        echo '<li>No tenant ID returned from Workplace API</li>';
    }
    if ($tenantid > 0 && !$ispenabled) {
        echo '<li>ISP Manager is not enabled for tenant ID ' . $tenantid . '</li>';
    }
    echo '</ul>';
}

// Additional info
echo '<h3>Additional Debug Info</h3>';
echo '<pre>';
echo "extend_navigation function exists: " . (function_exists('local_dsl_isp_extend_navigation') ? 'Yes' : 'No') . "\n";
echo "lib.php path: " . __DIR__ . '/lib.php' . "\n";
echo "lib.php exists: " . (file_exists(__DIR__ . '/lib.php') ? 'Yes' : 'No') . "\n";
echo '</pre>';

echo $OUTPUT->footer();
