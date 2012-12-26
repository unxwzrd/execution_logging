<?php

/************************************************************************
* logging_demo.php                      v1.1            richard ahrens  *
* last update: 10 Dec 2012                           unxwzrd@gmail.com  *
*                                                                       *
* this program demonstrates use of the execution_logging.lib.php        *
* library by tracking entries and exits of functions and selected       *
* blocks of code. it is designed to be run from the command line        *
* rather than from a browser.                                           *
*                                                                       *
*-----------------------------------------------------------------------*
*                                                                       *
* This program is free software: you can redistribute it and/or modify  *
* it under the terms of the GNU General Public License as published by  *
* the Free Software Foundation, either version 3 of the License, or (at *
* your option) any later version.                                       *
*                                                                       *
* This program is distributed in the hope that it will be useful,       *
* but WITHOUT ANY WARRANTY; without even the implied warranty of        *
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         *
* GNU General Public License for more details.                          *
*                                                                       *
* You should have received a copy of the GNU General Public License     *
* along with this program.  If not, see <http://www.gnu.org/licenses/>. *
************************************************************************/

//=======================================================================
// setup

date_default_timezone_set('America/Los_Angeles');

error_reporting(E_ALL | E_STRICT);

//----------------------------------------------------------------------

define("TRACE_LEVEL", 15);      // see execution_logging.lib.php for use

require_once("execution_logging.lib.php");

$log_file_name = begin_log();   // let begin_log choose default log name

//=======================================================================
//================================= MAIN ================================
//=======================================================================

log_trc(NULL, 'enter', 'main');     // log the otherwise anonymous "main"

print "Number of rows to generate (between 2 and 10): ";
$answer = fgets(STDIN);
$answer = trim($answer);

if (empty($answer) || (!is_numeric($answer))) {
    log_msg(NULL, 'fatal error', "cannot interpret '$answer' as a number");
    log_trc(NULL, 'leave', 'main');
    exit;
} else if ($answer < 2 || $answer > 10) {
    log_msg(NULL, 'fatal error', "PEBKAC detected (Problem Exists Between Kayboard And Chair");
    log_trc(NULL, 'leave', 'main');
    exit;
}

$total_rows = $answer;

log_msg(NULL, 'note', "Generating first $total_rows rows of Pascal's Triangle");

for ($a = 0; $a <= $total_rows * 2; $a++) {     // initialize first row
    if ($a == $total_rows) {
        $current_row[$a] = 1;
    } else {
        $current_row[$a] = 0;
    }
}

display_row($total_rows, $current_row);

generate_next_row($total_rows, 2, $current_row);

log_trc(NULL, 'leave', 'main');

print "\nLogging information saved in '$log_file_name'\n";

exit;

//=======================================================================
//============================== FUNCTIONS ==============================
//=======================================================================

function display_row($total_rows, $curr_row) {
    log_trc(NULL, 'enter', __FUNCTION__);

    for ($d = 0; $d <= $total_rows * 2; $d++) {
        if ($curr_row[$d] == 0) {
            print "    ";
        } else {
            printf(" %3d", $curr_row[$d]);
        }
    }
    print "\n";

    log_trc(NULL, 'leave', __FUNCTION__);
}

//=======================================================================

function generate_next_row($total_rows, $curr_row_number, $curr_row) {
    log_trc(NULL, 'enter', __FUNCTION__, array("Curr Row Num: ". $curr_row_number));

    log_trc(NULL, 'enter', 'section of interest');      // tracing a block of code
    $new_row[0] = 0;
    $new_row[$total_rows * 2] = 0;
    for ($c = 1; $c < $total_rows * 2; $c++) {
        $new_row[$c] = $curr_row[$c-1] + $curr_row[$c+1];
    }
    log_trc(NULL, 'leave', 'section of interest');

    display_row($total_rows, $new_row);

    if ($curr_row_number < $total_rows) {
        generate_next_row($total_rows, $curr_row_number + 1, $new_row);
    }

    log_trc(NULL, 'leave', __FUNCTION__);
}

?>
