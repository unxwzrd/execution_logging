<?php

/************************************************************************
* execution_logging.lib.php             v4.2            richard ahrens  *
* last update: 15 Nov 2012                           unxwzrd@gmail.com  *
*                                                                       *
* I will respond to questions or suggestions about the original code    *
* by email when I have time. If you have changed the code, the YOYO     *
* rule applies (You're On Your Own).                                    *
*                                                                       *
* This library contains four functions:                                 *
*                                                                       *
*   begin_log()        opens the log file. it will generate a name for  *
*                      the log file if none is specified.               *
*                                                                       *
*   end_log()          closes the log file.                             *
*                                                                       *
*   log_msg()          adds a user-specified message to the log file.   *
*                      the message is provided with a searchable tag,   *
*                      a date/time stamp, and origin information. it    *
*                      is also indented to the current nesting level.   *
*                                                                       *
*   log_trc()          adds an entry/exit message to the log file for   *
*                      a function of block of code. tracing entries     *
*                      follow the same format conventions as entries    *
*                      made by log_msg to allow for easy searching and  *
*                      debugging                                        *
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
*                                                                       *
************************************************************************/

//=======================================================================
// if the log file is not opened with a call to begin_log(), then it
// will default to STDERR

GLOBAL $log_fhndl;

$log_fhndl = STDERR;

//----------------------------------------------------------------------
// TRACE_LEVEL controls the nesting level at which tracing stops. if
// it is not defined within the program, then it is given the default
// setting of 5

if ( ! defined("TRACE_LEVEL")) {
    define("TRACE_LEVEL", 5);
}

//----------------------------------------------------------------------
// variables needed for log_trc()

GLOBAL $nesting_level;
GLOBAL $entry_times;
GLOBAL $func_names;

$nesting_level = -1;
$entry_times   = array();
$func_names    = array();

//======================================================================
// begin_log
//
//   opens the log file for log_msg() and log_trc()
//
//   if begin_log() is not called, $log_fhndl will point to STDERR
//
//   an easy way to create a log name for most purposes is:
//
//      $sn = $_SERVER["SCRIPT_NAME"];
//      $sp = explode('/', $sn);
//      $pn = $sp[count($sp) - 1];
//      $log_dir = "C:/cygwin/home/mydir/logs";
//      $log_name = $log_dir ."/". $pn ."_". $date .".log";
//      begin_log($log_name);

function begin_log($log_file_spec = NULL) {
    GLOBAL $log_fhndl;

    if (! isset($log_file_spec)) {
        $sn = $_SERVER["SCRIPT_NAME"];
        $sp = explode('/', $sn);
        //$pn = $sp[count($sp) - 1];
        $pn = str_ireplace('.php', '', $sp[count($sp) - 1]);
        $log_file_spec = $pn ."_". date('Ymd') .".log";
    }

    if (($log_fhndl = @fopen($log_file_spec, "wb")) === FALSE) {
        log_msg(STDERR, 'fatal', "could not open log file '$log_file_spec' for output");
        exit;
    }

    return $log_file_spec;              // in case anybody wants it
}

//======================================================================
// end_log
//
//  closes the log file

function end_log($log_file_handle = NULL) {
    if (isset($log_file_handle) && $log_file_handle != STDERR) {
        fclose($log_file_handle);
    }
}

//======================================================================
// log_msg
//
//   outputs a diagnostic message in a standardized format:
//
//      "prefix idate time - program::function:(line) severity - text"
//
//   "prefix" provides a searchable tag - all diagnostic messages
//   can be extracted from a log with "grep '^([EFWN])' log_file"
//   or in most cases just "grep '^([A-Z])' log_file" will do
//
//   "idate" and "time" provide a timestamp in "YYYYMMDD HH:MM:SS" format
//
//   "program", "function", and "line" identify the source of the diagnostic
//
//   "severity" can be 'fatal error', 'error', 'warning', or 'note'
//      'fatal error' means the error is so bad the program cannot continue
//      'error' means something has been lost but the program can go on without it
//      'warning' means something was wrong but the program is correcting for it
//      'note' is simply used to report on a state, condition, parameter
//
//   "text" should tell the user what happened. if there was a problem,
//   the text should describe what the program was doing at the time,
//   what input or condition was encountered, and what can be done to
//   fix it.
//
//   example call:
//
//       log_msg($log_file_handle, 'error', "could not open file '$ifile' for input");
//
//   if the diagnostic text is long, additional lines can be added to the
//   error output by making more calls to log_msg with "addendum" set to
//   TRUE. for example:
//
//       log_msg($log_file_handle, 'error', "additional text", TRUE);
//
//   the extra text provided in this call will be added to the log with
//   the timestamp, source, and severity left out so it is clear to the
//   reader that the addition is part of the same message.

function log_msg($log_file_handle,      // where to write msg
                 $severity,             // { 'fatal error' | 'error' | 'warning' | 'note' }
                 $text,                 // what happened? what can be done about it?
                 $addendum = FALSE) {   // is this a continuation of a diagnostic message?

    GLOBAL $nesting_level;
    GLOBAL $log_fhndl;

    $severity = strtolower($severity);

    if (!isset($log_file_handle)) {
        if (isset($log_fhndl)) {
            $log_file_handle = $log_fhndl;
        } else {
            fprintf(STDERR, "warning - log file not opened - directing to STDERR\n");
            show_caller();
        }
    }

    //==============================================================
    // when did this happen?

    $ts = date('Ymd h:i:s');

    //==============================================================
    // indent the message to show the current calling level

    $nesting_str = "";
    for ($a = 0; $a < $nesting_level; $a++) {
        $nesting_str .= ": ";
    }

    //==============================================================
    // get the program, function, and line number

    $sn = explode('/', $_SERVER["SCRIPT_NAME"]);
    $program = $sn[count($sn) - 1];

    $backtrace = debug_backtrace();
    if (isset($backtrace[1])) {
        $frame    = $backtrace[1];
        $function = $frame['function'];
    } else {
        $frame    = $backtrace[0];
        $function = 'main';
    }

    $line_no  = $frame['line'];

    $source = "$program::$function:($line_no)";

    //==============================================================
    // normalize the severity text and set the message prefix

    $first_letter = strtoupper(substr($severity, 0, 1));

    if ($first_letter == 'F') {                                 // bad error occurred and
        $severity = ' fatal error - ';                          // program should halt
        $prefix = '(F)';
    } else if ($first_letter == 'E') {                          // something was lost and
        $severity = ' error - ';                                // cannot be recovered
        $prefix = '(E)';
    } else if ($first_letter == 'W') {                          // something may have been lost but
        $severity = ' warning - ';                              // program is trying to recover
        $prefix = '(W)';
    } else if ($first_letter == 'N') {                          // nothing wrong, just an
        $severity = ' ';                                        // informational message
        $prefix = '(N)';
    } else {
        fprintf(STDERR, "(P) %s - %s - %s\n", $ts, $source,
                "programmer error - what does severity '$severity' mean?");
        show_caller();
        exit;
    }

    //==============================================================
    // write the message to the log file

    if ($addendum) {
        fprintf($log_file_handle, "%s . . . . . . . . . . %s%s\n", $prefix, $nesting_str, $text);
    } else {
        fprintf($log_file_handle, "%s %s - %s%s%s%s\n",
                $prefix, $ts, $nesting_str, $source, $severity, $text);
    }

    //--------------------------------------------------------------
    // if this is an error, also send a copy to STDERR

    if ($first_letter == 'E' || $first_letter == 'F') {
        if ($addendum) {
            fprintf(STDERR, "%s . . . . . . . . . . %s%s\n", $prefix, $nesting_str, $text);
        } else {
            fprintf(STDERR, "%s %s - %s %s - %s%s\n",
                    $prefix, $ts, $nesting_str, $source, $severity, $text);
        }
    }
}

//======================================================================
// log_trc
//
//   creates an execution tracing entry in the log file in the same
//   format as the entries created by log_msg()
//
//   allows tracing of selected functions or blocks of code during the
//   execution of a program
//
//   if the log file has already been opened with begin_log(), then
//   entry/exit calls can be as simple as the following examples.
//
//   at or near the start of each function you want to track, put in:
//
//      log_trc(NULL, 'enter', __FUNCTION__);
//
//   then add a "leave" call to every exit point in the function
//
//      log_trc(NULL, 'leave', __FUNCTION__);
//
//   if you are tracing blocks of code, replace "__FUNCTION__" with
//   a unique identifier for each block
//
//   it is not necessary to put entry/exit calls in every function in
//   a program; however, if you put an "entry" call in a function, be
//   sure to add a "leave" call covering every exit/return point from
//   that function
//
//   when the 'enter' call is made to log_trc(), the log entry can
//   include selected arguments to the function by passing them in via
//   the "args" argument to log_trc(), for example:
//
//      log_trc(NULL, 'enter', __FUNCTION__, array($date[$begin_x], $date[$end_x]));
//
//   although the incoming arguments to a function can be retrieved by
//   debug_trace(), I decided not to have the log_trc() function obtain
//   them this way because if arrays are being passed, the output becomes
//   unwieldy. the progammer may choose to display selected parameters
//   by passing them in via the "args" argument to log_trc().
//
//   when the 'leave' call is made to log_trc(), the log entry shows
//   the number of seconds spend in the function and its children, in
//   this way the log_trc entries provide both call tree information
//   and profiling data
//
//   trace entries are recorded in the log file with the prefix string
//   "(T)" so they can be extracted from the log file with
//
//      grep '^(T)' log_file

function log_trc($log_file_handle,      // where to write tracing information
                 $transition,           // { 'enter' | 'leave' }
                 $function,             // function name or block identifier
                 $args = NULL) {        // array of arguments to the function

    GLOBAL $log_fhndl;
    GLOBAL $nesting_level;
    GLOBAL $entry_times;
    GLOBAL $func_names;

    if (!isset($log_file_handle)) {
        if (isset($log_fhndl)) {
            $log_file_handle = $log_fhndl;
        } else {
            fprintf(STDERR, "warning - log file not opened - directing to STDERR\n");
            show_caller();
        }
    }

    if (strtoupper(substr($transition, 0, 1)) == 'E') {         // ENTER
        $nesting_level++;

        if ($nesting_level <= TRACE_LEVEL) {
            $nesting_str = "";
            for ($a = 0; $a < $nesting_level; $a++) {
                $nesting_str .= ": ";
            }

            $nesting_str .= "enter $function";

            if (count($args)) {
                for ($a = 0; $a < count($args); $a++) {
                    if ($a == 0) {
                        $nesting_str .= "(";
                    } else {
                        $nesting_str .= ", ";
                    }
                    $nesting_str .= $args[$a];
                }
                $nesting_str .= ")";
            }

            $ts = date('Ymd h:i:s');

            fprintf($log_file_handle, "(T) %s - %s\n", $ts, $nesting_str);

            $entry_times[$nesting_level] = microtime(TRUE);
            $func_names[$nesting_level]  = $function;
        }
    } else {                                                    // LEAVE
        if ($nesting_level <= TRACE_LEVEL) {
            if ($func_names[$nesting_level] != $function) {
                log_msg($log_file_handle, 'error', "arg f '$function' does not match stack f '$func_names[$nesting_level]'");
                log_msg($log_file_handle, 'error', "check log_trc() exit calls in '$func_names[$nesting_level]'", TRUE);
            }

            $end_time  = microtime(TRUE);
            $run_time  = $end_time - $entry_times[$nesting_level];

            $nesting_str = "";
            for ($a = 0; $a < $nesting_level; $a++) {
                $nesting_str .= ": ";
            }

            $nesting_str .= "leave $function (". round($run_time, 4) ." seconds)";

            $ts = date('Ymd h:i:s');

            fprintf($log_file_handle, "(T) %s - %s\n", $ts, $nesting_str);
        }

        $nesting_level--;
        if ($nesting_level < 0 && strtolower($function) != 'main') {
            $nesting_level = 0;
            log_msg($log_file_handle, 'error', "the call log_trc($log_file_handle, $transition, "
                                             . "$function) made nesting_level negative");
        }
    }
}

//======================================================================
// show_caller

function show_caller() {
    $backtrace    = debug_backtrace();
    $frame1       = $backtrace[1];
    $func_called  = $frame1['function'];
    $calling_line = $frame1['line'];

    if (isset($backtrace[2])) {
        $frame2       = $backtrace[2];
        $calling_func = $frame2['function'];
    } else {
        $calling_func = 'main';
    }

    return "$func_called() called by $calling_func at line $calling_line\n";
}

?>
