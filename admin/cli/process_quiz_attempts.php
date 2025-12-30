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
 * CLI script to process quiz attempts
 * 
 * This is a custom script developed by Catalyst.
 * 
 * CATALYST CUSTOM - WR477506
 *
 * @package    core_admin
 * @copyright  2026 Michael Kotlyar <michael.kotlyar@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

global $DB;

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'fetch-submitted' => false,
        'process-submitted' => false,
        'attemptids' => false,
    ],
    [
        'h' => 'help',
        'fs' => 'fetch-submitted',
        'ps' => 'process-submitted',
        'a' => 'attemptids',
    ],
);

if ($unrecognized) {
    $unrecognized = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized), 2);
}

if ($options['help']) {
    $help =
"Process quiz attempts

Options:
-h, --help                      Print out this help
-fs, --fetch-submitted          Print out IDs of 'Submitted' attempts
-ps, --process-submitted        Process 'Submitted' attempts
-a, --attemptids                IDs of attempts to process e.g. --attemptids=1,2,3,40,50,60

Example:
\$sudo -u www-data /usr/bin/php admin/cli/process_quiz_attempts.php --fetch-submitted
12,43,365

\$sudo -u www-data /usr/bin/php admin/cli/process_quiz_attempts.php --attemptids=12,43,365
";

    cli_writeln($help);

    exit(0);
}

if ($options['fetch-submitted']) {
    $submittedids = $DB->get_fieldset_select('quiz_attempts', 'id', 'state = ?', [\mod_quiz\quiz_attempt::SUBMITTED]);

    if ($submittedids) {
        $output = implode(',', $submittedids);
    } else {
        $output = 'No "Submitted" quiz attempts.';
    }

    cli_writeln($output);

    exit(0);
}

if ($options['process-submitted']) {
    $attempts = $DB->get_records('quiz_attempts', ['state' => \mod_quiz\quiz_attempt::SUBMITTED]);
    $attemptcount = count($attempts);

    if ($attemptcount > 0) {
        $attemptids = array_column($attempts, 'id');
        cli_writeln('"Submitted" attempts to process: ' . $attemptcount);
        cli_writeln('"Submitted" attempt IDs: ' . implode(',', $attemptids));
        process_attempts($attempts);
    } else {
        cli_writeln('0 "Submitted" quiz attempts found, exiting.');
    }

    exit(0);
}

if ($options['attemptids']) {
    $attemptids = array_map('intval', array_filter(explode(',', $options['attemptids']), 'ctype_digit'));
    [$insql, $inparams] = $DB->get_in_or_equal($attemptids);
    $attempts = $DB->get_records_select(
        'quiz_attempts',
        "state = ? AND id {$insql}",
        [\mod_quiz\quiz_attempt::SUBMITTED, ...$inparams],
    );

    $attemptcount = count($attempts);
    if ($attemptcount > 0) {
        cli_writeln("{$attemptcount} valid quiz attempts to process." . PHP_EOL);
        process_attempts($attempts);
    } else {
        cli_writeln("0 valid quiz attempts found, exiting.");
    }

    exit(0);
}

/**
 * Process attempts
 *
 * @param array $attempts
 */
function process_attempts(array $attempts): void {
    $attemptcount = count($attempts);

    $timenow = time();
    $attemptids = [];
    $unsuccessfullyprocessedattemptids = [];
    $successfullyprocessedattemptids = [];

    foreach ($attempts as $attempt) {
        cli_writeln("Attempting to process quiz attempt id: {$attempt->id}" . PHP_EOL);
        try {
            if (!($timestamp = $attempt->timemodified)) {
                $quizobj = \mod_quiz\quiz_settings::create($attempt->quiz, $attempt->userid);
                $timestamp = $quizobj->get_access_manager($timenow)->get_end_time($attempt) ?: $timenow;
            }
            $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
            $attemptobj->process_grade_submission($timestamp);
            $successfullyprocessedattemptids[] = $attempt->id;
        } catch (\Exception $e) {
            abort_all_db_transactions();
            $unsuccessfullyprocessedattemptids[] = $attempt->id;
            cli_writeln("Failed to process quiz attempt id: {$attempt->id}");
            cli_writeln("Error message: {$e->getMessage()}");
            cli_writeln("Continuing..." . PHP_EOL);
        }
        $attemptids[] = $attempt->id;
    }

    $unsuccessfullyprocessedattemptidscount = count($unsuccessfullyprocessedattemptids);
    $successfullyprocessedattemptidscount = count($successfullyprocessedattemptids);

    cli_writeln("{$attemptcount} quiz attempts processed.");
    cli_writeln("Processed IDs: " . implode(',', $attemptids) . PHP_EOL);

    cli_writeln("{$unsuccessfullyprocessedattemptidscount} quiz attempts unsuccessfully processed.");
    cli_writeln("Processed IDs: " . implode(',', $unsuccessfullyprocessedattemptids) . PHP_EOL);

    cli_writeln("{$successfullyprocessedattemptidscount} quiz attempts successfully processed.");
    cli_writeln("Processed IDs: " . implode(',', $successfullyprocessedattemptids) . PHP_EOL);
}
