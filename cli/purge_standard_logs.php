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
 * CLI script to remove entries from the standard logs table for enrol_selma.
 *
 * @package     enrol_selma
 * @author      Donald Barrett <donald.barrett@learningworks.co.nz>
 * @copyright   2021 onwards, LearningWorks ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This is a CLI script.
define('CLI_SCRIPT', true);

// Config file.
require_once(dirname(__FILE__, 4) . '/config.php');

global $DB;

// Other things to require.
require_once("{$CFG->libdir}/clilib.php");
require_once("{$CFG->libdir}/cronlib.php");

// We may need a lot of memory here.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// CLI options.
list($options, $unrecognized) = cli_get_params(
// Long names.
    [
        'help' => false,
        'no-verbose' => false,
        'no-debugging' => false,
        'print-logo' => false,
        'username' => '',
        'delete-older-than' => 7,
        'non-interactive' => false,
    ],
    // Short names.
    [
        'h' => 'help',
        'nv' => 'no-verbose',
        'nd' => 'no-debugging',
        'pl' => 'print-logo',
        'u' => 'username',
    ]
);

if (function_exists('cli_logo') && $options['print-logo']) {
    // Show a logo.
    cli_logo();
    echo PHP_EOL;
}

if ($unrecognized) {
    $unrecognized = implode("\n ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

// Show help.
if ($options['help']) {
    $help =
        "CLI script to remove entries from the standard logs table.

Please note you must execute this script with the same uid as apache!

Options:
-nv, --no-verbose       Disables output to the console.
-h, --help              Print out this help.
-nd, --no-debugging     Disables debug messages.
-pl, --print-logo       Prints a cool CLI logo if available.
-u, --username          The username of the user to purge the log entries.
--delete-older-than     Delete log entries older than (in days), default = 7 days.
--non-interactive       Just do the things.

Example:
Run script with default parameters   - \$sudo -u www-data /usr/bin/php purge_standard_logs.php\n
Purge logs for user older than 1 day - \$sudo -u www-data /usr/bin/php purge_standard_logs.php -u=theuser --delete-older-than=1
";
    echo $help;
    die;
}

// Set debugging.
if (!$options['no-debugging']) {
    @error_reporting(E_ALL | E_STRICT);
    @ini_set('display_errors', '1');
}

// Start output log.
$trace = new text_progress_trace();
$trace->output(get_string('pluginname', 'enrol_selma') . ' CLI script to remove entries from the standard logs table.');

// Say some stuff like debugging is whatever.
if (!$options['no-debugging']) {
    $trace->output("Debugging is enabled.");
} else {
    $trace->output("Debugging has been disabled.");
}

// Set verbosity and output stuff.
if ($options['no-verbose']) {
    $trace->output("Verbose output has been disabled.\n");
    $trace = new null_progress_trace();
} else {
    $trace->output("Verbose output is enabled.\n");
}

if (empty($options['username'])) {
    $prompt = "Enter username";
    $username = cli_input($prompt);
} else {
    $username = $options['username'];
}

if (!$user = \core_user::get_user_by_username($username)) {
    cli_error("Can not find user '$username'");
}

$deleteolderthan = $options['delete-older-than'];
if ((string)intval($deleteolderthan) !== $deleteolderthan) {
    cli_error("Error! --delete-older-than value must be an integer");
}

if (!$options['non-interactive']) {
    $prompt = "Are you sure you want to delete all of the standard log entries for the user '$username'? older than '$deleteolderthan' days (y = yes, n = no)";
    $continue = cli_input($prompt, '', ['y', 'n']);

    if ($continue === 'n') {
        exit(0);
    }
}

// Start timing.
$timenow = time();
$trace->output("\nServer Time: " . date('r', $timenow) . "\n");
$starttime = microtime();

$deletefromtimestamp = strtotime('today midnight') - ($deleteolderthan * DAYSECS);
$params = ['timecreated' => $deletefromtimestamp, 'userid' => $user->id];
try {
    $trace->output("Deleting the logstore entries...");
    $DB->delete_records_select('logstore_standard_log', 'timecreated < :timecreated AND userid = :userid', $params);
    $trace->output("Logstore entries deleted!");
} catch (\dml_exception $exception) {
    $trace->output("A DML exception happened - {$exception->getMessage()}");
} catch (\Exception $exception) {
    $trace->output("A different exception happened - {$exception->getMessage()}");
}

// Finish timing.
$difftime = microtime_diff($starttime, microtime());
$trace->output("\nScript execution took {$difftime} seconds\n");