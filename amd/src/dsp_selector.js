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
 * DSP selector autocomplete transport for ISP Manager.
 *
 * This module provides the AJAX transport for the autocomplete form element
 * used when selecting DSPs to assign to a client.
 *
 * @module     local_dsl_isp/dsp_selector
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {

    /**
     * Process the results from the web service.
     *
     * @param {string} selector The selector for the autocomplete element.
     * @param {Array} results The results from the web service.
     * @return {Array} Processed results for the autocomplete.
     */
    var processResults = function(selector, results) {
        var processed = [];

        if (!results || !results.users) {
            return processed;
        }

        results.users.forEach(function(user) {
            processed.push({
                value: user.id,
                label: user.fullname + ' (' + user.email + ')'
            });
        });

        return processed;
    };

    /**
     * Fetch results from the server.
     *
     * @param {string} selector The selector for the autocomplete element.
     * @param {string} query The search query.
     * @param {Function} success Success callback.
     * @param {Function} failure Failure callback.
     */
    var transport = function(selector, query, success, failure) {
        var element = document.querySelector(selector);
        if (!element) {
            success([]);
            return;
        }

        var form = element.closest('form');
        var tenantId = 0;
        var clientId = 0;

        if (form) {
            var tenantInput = form.querySelector('input[name="tenantid"]');
            var clientInput = form.querySelector('input[name="clientid"]');

            if (tenantInput) {
                tenantId = parseInt(tenantInput.value, 10) || 0;
            }
            if (clientInput) {
                clientId = parseInt(clientInput.value, 10) || 0;
            }
        }

        if (!tenantId) {
            success([]);
            return;
        }

        if (!query || query.length < 2) {
            success([]);
            return;
        }

        var request = {
            methodname: 'local_dsl_isp_search_users',
            args: {
                tenantid: tenantId,
                clientid: clientId,
                search: query,
                limit: 20
            }
        };

        Ajax.call([request])[0]
            .then(function(results) {
                success(results);
            })
            .catch(function(error) {
                failure(error);
            });
    };

    return {
        processResults: processResults,
        transport: transport
    };
});
