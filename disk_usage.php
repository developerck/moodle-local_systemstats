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
 * Plugin administration pages are defined here.
 *
 * @package     local_systemstats
 * @category    admin
 * @copyright   2020 Chandra Kishor <developerck@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/coursecatlib.php');
require_once($CFG->dirroot . '/local/systemstats/locallib.php');
$categoryid = optional_param("categoryid", 0, PARAM_INT);
$courseid = optional_param("courseid", 0, PARAM_INT);
$page = optional_param("page", 0, PARAM_INT);
$perpage = optional_param("perpage", 10, PARAM_INT);
$startlimit = $page * $perpage;

require_login();

if ($categoryid) {
    $category = core_course_category::get($categoryid); // This will validate access.
} else {
    if (empty($categoryid)) {
        if (is_siteadmin()) {
            $category = core_course_category::user_top();
        } else {
            $cats = core_course_category::make_categories_list('local/enrolstats:access_enrolstats');
            $categoryid = array_pop(array_keys($cats));
            if ($categoryid) {
                $category = core_course_category::get($categoryid);
            }
        }
    }
    if (!$category) {
        throw new moodle_exception('cannotviewcategory');
    }
}


if ($category->id) {
    $catcontext = context_coursecat::instance($category->id);
} else {
    $catcontext = context_system::instance();
}
if (!is_siteadmin()) {
    require_capability('local/enrolstats:access_enrolstats', $catcontext);
}

if ($courseid) {
    $course = get_course($courseid);
}

$PAGE->set_context($catcontext);
$PAGE->set_url(new moodle_url('/local/systemstats/disk_usage.php'));
$PAGE->set_pagetype('standard');
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Disk Usage');
$PAGE->navbar->add('Disk Usage', new moodle_url('/local/systemstats/disk_usage.php'));

$heading = '';
if (!$categoryid) {
    $heading = get_string('disk_usage_title', 'local_systemstats').' : System';
} else {
    $heading = get_string('disk_usage_title', 'local_systemstats').' : ' . $category->name;
}
$PAGE->set_heading($heading);

$PAGE->navbar->add($heading, new moodle_url('/local/systemstats/disk_usage.php', array("categoryid" => $categoryid)));
if ($course) {
    $PAGE->navbar->add($course->fullname, new moodle_url('/local/systemstats/disk_usage.php', array("categoryid" => $categoryid, "courseid" => $course->id)));
}
$params = array(
    'objectid' => $categoryid,
    'courseid' => '',
    'context' => $catcontext,
    'other' => array(
        'category_id' => $categoryid
    )
);
$event = \local_systemstats\event\systemstats_view::create($params);
$event->trigger();


$options['recursive'] = true;
$options['offset'] = $page * $perpage;
$options['limit'] = $perpage;

$courses = coursecat::get($categoryid)->get_courses($options);
$coursecount = coursecat::get($categoryid)->get_courses_count(array('recursive' => true));

echo $OUTPUT->header();

$output = '';
$output .= html_writer::start_tag('div', array('class' => 'categorypicker'));
$select = new single_select(new moodle_url('/local/systemstats/disk_usage.php'), 'categoryid', core_course_category::make_categories_list('local/enrolstats:access_enrolstats'), $category->id, 'Select', 'switchcategory');
$select->set_label(get_string('categories') . ':');
$output .= $OUTPUT->render($select);
$output .= html_writer::end_tag('div');
echo $output;

$statsrenderer = $PAGE->get_renderer('local_systemstats');
$storage = new \local_systemstats\storage();

if (!$categoryid && is_siteadmin()) {
    echo "</br>";
    echo "<h3>";
    echo get_string('disk_usage_total', 'local_systemstats');
    echo '<span class="badge badge-info">' . $storage->format_size($storage->total_system_usage(), 'gb') . ' GB</span>';
    echo "</h3>";

    $ac = $storage->format_size($storage->all_course_usage());
    $au = $storage->format_size($storage->all_user_usage());
    $ds = array(array("Type", "Storage"),
        array("All Course", array("v" => (int) $ac, "f" => $ac . " MB")),
        array("All Users", array("v" => (int) $au, "f" => $au . " MB")),
            )
    ?>
    <div id="piechart" style="width: 900px; height: 500px;"></div>
    <script type="text/javascript"
    src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">

        google.charts.load('current', {'packages': ['corechart']});

        google.charts.setOnLoadCallback(drawChart);
        var js = '<?php echo json_encode($ds); ?>';
        function drawChart() {

            var data = google.visualization.arrayToDataTable(JSON.parse(js));

            var options = {
                title: 'Storage Usage',
                is3D: true,
                pieSliceText: 'value',
                pieHole: 0.4,
            };

            var chart = new google.visualization.PieChart(document.getElementById('piechart'));

            chart.draw(data, options);
        }

    </script>
    <?php
} else {
    echo "</br>";
    echo "<h3>";
    echo get_string('disk_usage_total', 'local_systemstats');
    if ($course) {
        echo '<span class="badge badge-info">' . $storage->format_size($storage->course_usage($courseid)) . ' MB</span>';
    } else {
        echo '<span class="badge badge-info">' . $storage->format_size($storage->cat_usage($categoryid)) . ' MB</span>';
    }
    echo "</h3>";
}
if ($course) {
    echo $OUTPUT->heading(html_writer::link(new moodle_url("/course/view.php", array("id" => $course->id)), $course->fullname), '4');
    $data = $storage->course_usage_table($courseid, $startlimit, $perpage);
    if ($data['rows'] > 0) {
        $table = new html_table();
        $table->attributes['class'] = 'table table-bordered table-striped';
        $table->head = array('File', 'Size', 'Type', 'Component', 'File Area', 'Action');


        foreach ($data['records'] as $c) {
            $row = array();
            $url = moodle_url::make_pluginfile_url(
                            $c->contextid, $c->component, $c->filearea, $c->itemid, $c->filepath, $c->filename);
            $row[] = html_writer::link($url, $c->filename, array("target" => "_blank"));
            $row[] = $storage->format_size($c->filesize) . " MB";
            $row[] = $c->type;
            $row[] = $c->component;
            $row[] = $c->filearea;
            if ($c->cmid && $c->modulename) {
                $row[] = html_writer::link(new moodle_url("/mod/" . $c->modulename . "/view.php", array("id" => $c->cmid)), 'Go to Activity', array("target" => "_blank"));
            } else {
                $row[] = '';
            }
            $table->data[] = $row;
        }
        echo html_writer::table($table);
        $paginationurl = new moodle_url("/local/systemstats/disk_usage.php", array("categoryid" => $categoryid, "courseid" => $course->id, 'page' => $page, 'perpage' => $perpage));
        echo $OUTPUT->paging_bar($data['rows'], $page, $perpage, $paginationurl);
    }
} else {
    $data = $storage->cat_usage_table($categoryid, $startlimit, $perpage);
    if ($data['rows'] > 0) {
        $table = new html_table();
        $table->attributes['class'] = 'table table-bordered table-striped';
        $table->head = array('Course', 'Size');


        foreach ($data['records'] as $c) {
            $url = new moodle_url("/local/systemstats/disk_usage.php", array("categoryid" => $categoryid, "courseid" => $c->courseid));
            $link = html_writer::link($url, $c->coursename);
            $table->data[] = array($link, $storage->format_size($c->coursesize) . " MB");
        }
        echo html_writer::table($table);
        $paginationurl = new moodle_url("/local/systemstats/disk_usage.php", array("categoryid" => $categoryid, 'page' => $page, 'perpage' => $perpage));
        echo $OUTPUT->paging_bar($data['rows'], $page, $perpage, $paginationurl);
    }
}
echo $OUTPUT->footer();
