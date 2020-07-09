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
 * Plugin event classes are defined here.
 *
 * @package     local_systemstats
 * @copyright   2020 Chandra Kishor <developerck@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_systemstats;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/coursecatlib.php');

class storage {

    public function course_usage($courseid) {
        global $DB;

        if (empty($courseid)) {
            return 0;
        }
        $sqlc = "SELECT SUM( f.filesize ) AS coursesize
                      FROM {files} f, {context} ctx
                      INNER JOIN mdl_course c on c.id = ctx.instanceid
                     WHERE ctx.id           = f.contextid
                       AND ctx.contextlevel = :contextlevel and c.id=:courseid";


        $coursesize = $DB->get_record_sql(
                $sqlc, array('contextlevel' => CONTEXT_COURSE, 'courseid' => $courseid
        ));

        $sqlm= "SELECT SUM( f.filesize ) AS modulessize
                      FROM {course_modules} cm ,{files} f, {context} ctx
                     WHERE ctx.id = f.contextid
                       AND ctx.instanceid   = cm.id
                       AND ctx.contextlevel = :contextlevel
                       and cm.course = :courseid";


        $modulessize = $DB->get_record_sql(
                $sqlm, array('contextlevel' => CONTEXT_MODULE, 'courseid' => $courseid
        ));
        $coursesizeval = isset($coursesize->coursesize) ? $coursesize->coursesize : 0;
        $modulessizeval = isset($modulessize->modulessize) ? $modulessize->modulessize : 0;


        return $coursesizeval + $modulessizeval;
    }

    public function course_usage_table($courseid, $offset = 0, $limit = 10) {
        global $DB;

        if (empty($courseid)) {
            return array("rows" => 0, 'records' => '');
        }
        $courses [$courseid] = $courseid;
        $sql1 = "SELECT    f.id as fileid, c.id courseid,  ctx.contextlevel,f.component, f.filearea, f.filename, f.filesize, f.filepath, f.contextid, 'course' as type, 0 as cmid ,0 as modulename
                            FROM {files} f, {context} ctx
                            INNER JOIN mdl_course c on c.id = ctx.instanceid
                            WHERE ctx.id           = f.contextid
                            AND filename <> '.'
                       AND ctx.contextlevel =" . CONTEXT_COURSE;
        if (!empty($courses)) {
            $cids = implode(",", array_keys($courses));
            $sql1 .=" and c.id in ($cids)";
        }

        $sql2 = "SELECT f.id as fileid, cm.course courseid, ctx.contextlevel,f.component, f.filearea, f.filename, f.filesize,f.filepath, f.contextid, 'module' as type, cm.id as cmid, m.name as modulename
                      FROM {course_modules} cm ,{files} f, {context} ctx, {modules} m
                     WHERE ctx.id = f.contextid
                     AND cm.module = m.id
                       AND ctx.instanceid   = cm.id
                       AND filename <> '.'
                       AND ctx.contextlevel = " . CONTEXT_MODULE;
        if (!empty($courses)) {
            $cids = implode(",", array_keys($courses));
            $sql2 .=" and cm.course in ($cids)";
        }

        $sql = "select SQL_CALC_FOUND_ROWS un.* from ($sql1 UNION $sql2) un  ";
        $sql .= " order by filesize desc";
        $sql .= " limit $offset, $limit";
        $records = $DB->get_records_sql($sql);
        $countrows = $DB->get_record_sql("SELECT FOUND_ROWS() as counttotal");


        return array("rows" => $countrows->counttotal, 'records' => $records);
    }

    public function cat_usage($catid = 0) {
        global $DB;
        $courses = [];
        if ($catid) {
            $options['recursive'] = true;
            $courses_list = \coursecat::get($catid)->get_courses($options);

            foreach ($courses_list as $c) {
                $courses[$c->id] = $c->fullname;
            }
        }

        if (empty($courses) && $catid) {
            return 0;
        }

        $sqlc = "SELECT SUM( f.filesize ) AS coursesize
                      FROM {files} f, {context} ctx
                      INNER JOIN mdl_course c on c.id = ctx.instanceid
                     WHERE ctx.id           = f.contextid
                       AND ctx.contextlevel = :contextlevel";
        if (!empty($courses)) {
            $cids = implode(",", array_keys($courses));
            $sqlc .=" and c.id in ($cids)";
        }


        $coursesize = $DB->get_record_sql(
                $sqlc, array('contextlevel' => CONTEXT_COURSE,
        ));

        $sqlm = "SELECT SUM( f.filesize ) AS modulessize
                      FROM {course_modules} cm ,{files} f, {context} ctx
                     WHERE ctx.id = f.contextid
                       AND ctx.instanceid   = cm.id
                       AND ctx.contextlevel = :contextlevel";
        if (!empty($courses)) {
            $cids = implode(",", array_keys($courses));
            $sqlm .=" and cm.course in ($cids)";
        }

        $modulessize = $DB->get_record_sql(
                $sqlm, array('contextlevel' => CONTEXT_MODULE,
        ));
        $coursesizeval = isset($coursesize->coursesize) ? $coursesize->coursesize : 0;
        $modulessizeval = isset($modulessize->modulessize) ? $modulessize->modulessize : 0;


        return $coursesizeval + $modulessizeval;
    }

    public function cat_usage_table($catid = 0, $offset = 0, $limit = 10) {
        global $DB;
        $courses = [];
        if ($catid) {
            $options['recursive'] = true;
            $courses_list = \coursecat::get($catid)->get_courses($options);

            foreach ($courses_list as $c) {
                $courses[$c->id] = $c->fullname;
            }
        }
        if (empty($courses) && $catid) {
            return array("rows" => 0, 'records' => '');
        }
        $sql1 = "SELECT    c.id courseid,SUM( f.filesize ) AS coursesize
                      FROM {files} f, {context} ctx
                      INNER JOIN mdl_course c on c.id = ctx.instanceid
                     WHERE ctx.id           = f.contextid
                       AND ctx.contextlevel =" . CONTEXT_COURSE;
        if (!empty($courses)) {
            $cids = implode(",", array_keys($courses));
            $sql1 .=" and c.id in ($cids)";
        }

        $sql1 .= " GROUP BY ctx.instanceid";

        $sql2 = "SELECT cm.course courseid, SUM( f.filesize ) AS coursesize
                      FROM {course_modules} cm ,{files} f, {context} ctx
                     WHERE ctx.id = f.contextid
                       AND ctx.instanceid   = cm.id
                       AND ctx.contextlevel = " . CONTEXT_MODULE;
        if (!empty($courses)) {
            $cids = implode(",", array_keys($courses));
            $sql2 .=" and cm.course in ($cids)";
        }
        $sql2 .= " GROUP BY cm.course";

        $sql = "select SQL_CALC_FOUND_ROWS un.courseid, SUM(un.coursesize) coursesize, c.fullname coursename from ($sql1 UNION $sql2) un INNER JOIN mdl_course c on c.id=un.courseid group by c.id";
        $sql .= " order by coursesize desc";
        $sql .= " limit $offset, $limit";
        $records = $DB->get_records_sql($sql);
        $countrows = $DB->get_record_sql("SELECT FOUND_ROWS() as counttotal");


        return array("rows" => $countrows->counttotal, 'records' => $records);
    }

    public function total_system_usage() {
        global $DB;
        $count = $DB->get_record_sql('SELECT SUM(filesize) as space FROM {files}');
        return $count->space;
    }

    public function all_course_usage() {

        return $this->cat_usage();
    }

    public static function all_user_usage() {
        global $DB;

        $count = $DB->get_record_sql('SELECT SUM(filesize) as space FROM {files} WHERE component=\'user\'');

        return $count->space;
    }

    public function format_size($bytes, $unit = 'mb') {
        switch (strtolower($unit)) {
            case 'gb':
                $bytes = number_format($bytes / 1073741824, 2, '.', '');
                break;
            default:
                // mb
                $bytes = round($bytes / 1048576);
                break;
        }

        return $bytes;
    }

}
