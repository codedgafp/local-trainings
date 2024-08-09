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
 * PLugin library
 *
 * @package    local_trainings
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Nabil Hamdi <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/edadmin/classes/output/interface_renderer.php');
require_once($CFG->dirroot . '/local/trainings/classes/controllers/training_controller.php');
require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
require_once($CFG->dirroot . '/local/mentor_core/api/training.php');

/**
 * Moodle mandatory function to manage the plugin file permissions
 *
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @param array $options
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 * @throws require_login_exception
 */
function local_trainings_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {

    if (!in_array($filearea, local_trainings_list_filearea_user_access_without_logged_in())) {
        require_login();
    }

    // All training files must be in a course context.
    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/local_trainings/$filearea/$relativepath";

    if (!$file = $fs->get_file_by_hash(sha1($fullpath))) {
        return false;
    }

    if ($file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * List of file area user access without logged in
 *
 * @return string[]
 */
function local_trainings_list_filearea_user_access_without_logged_in() {
    return [
        'thumbnail',
        'teaserpicture',
        'producerorganizationlogo',
    ];
}

/**
 * Extend the course navigation
 *
 * @param navigation_node $settingsnav
 * @param object $context
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_trainings_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;

    // Only add this settings item on non-site course pages.
    if (!$PAGE->course || $PAGE->course->id == 1) {
        return;
    }

    // If the course is not linked to a training then return.
    if (!$training = \local_mentor_core\training_api::get_training_by_course_id($PAGE->course->id)) {
        return;
    }

    if (!$settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
        return;
    }

    $courseid = $training->get_course()->id;

    $beforekey = null;

    if ($settingnode->get('editsettings')) {
        $beforekey = 'editsettings';
    }

    // Add a link to the training sheet.
    if (has_capability('local/trainings:update', $context)) {
        $contextid = $training->get_context()->id;

        // Training sheet.
        $name = get_string('fichetraining', 'local_trainings');
        $workflownode = navigation_node::create(
            get_string('fichetraining', 'local_trainings'),
            $training->get_sheet_url($PAGE->url->out()),
            navigation_node::NODETYPE_LEAF,
            'training',
            'training',
            new pix_icon('list', $name, 'local_mentor_core')
        );
        $settingnode->add_node($workflownode, $beforekey);
    }

    if (has_capability('moodle/contentbank:access', $context)) {
        // Bank.
        $name = get_string('contentbank', 'local_trainings');
        $workflownode = navigation_node::create(
            $name,
            new moodle_url('/contentbank/index.php', ['contextid' => $contextid]),
            navigation_node::NODETYPE_LEAF,
            'content_bank',
            'content_bank',
            new pix_icon('briefcase', $name, 'local_mentor_core')
        );
        $settingnode->add_node($workflownode, $beforekey);
    }

    if (has_capability('moodle/course:viewparticipants', $context)) {
        // Enrol users.
        $name = get_string('entolusers', 'local_trainings');
        $workflownode = navigation_node::create(
            $name,
            new moodle_url('/user/index.php', ['id' => $courseid]),
            navigation_node::NODETYPE_LEAF,
            'enrol_users',
            'enrol_users',
            new pix_icon('user', $name, 'local_mentor_core')
        );
        $settingnode->add_node($workflownode, $beforekey);
    }

    if (has_capability('report/outline:view', $context)) {
        // Course activities.
        $name = get_string('courseactivities', 'local_trainings');
        $workflownode = navigation_node::create(
            $name,
            new moodle_url('/report/outline/index.php', ['id' => $courseid]),
            navigation_node::NODETYPE_LEAF,
            'course_activities',
            'course_activities',
            new pix_icon('flag', $name, 'local_mentor_core')
        );
        $settingnode->add_node($workflownode, $beforekey);
    }

    if (has_capability('report/completion:view', $context)) {
        // Training completion report.
        $name = get_string('trainingcompletionreport', 'local_trainings');
        $workflownode = navigation_node::create(
            $name,
            new moodle_url('/report/completion/index.php', ['course' => $courseid]),
            navigation_node::NODETYPE_LEAF,
            'training_completion_report',
            'training_completion_report',
            new pix_icon('check-square-o', $name, 'local_mentor_core')
        );
        $settingnode->add_node($workflownode, $beforekey);
    }

    if (has_capability('report/progress:view', $context)) {
        // Activities completion report.
        $name = get_string('activitiescompletionreport', 'local_trainings');
        $workflownode = navigation_node::create(
            $name,
            new moodle_url('/report/progress/index.php?', ['course' => $courseid]),
            navigation_node::NODETYPE_LEAF,
            'activities_completion_report',
            'activities_completion_report',
            new pix_icon('check-square', $name, 'local_mentor_core')
        );
        $settingnode->add_node($workflownode, $beforekey);
    }

    if (has_capability('moodle/course:managegroups', $context)) {
        // Group.
        $name = get_string('groups');
        $workflownode = navigation_node::create(
            $name,
            new moodle_url('/group/index.php?', ['id' => $courseid]),
            navigation_node::NODETYPE_LEAF,
            'group',
            'group',
            new pix_icon('users', $name, 'local_mentor_core')
        );
        $settingnode->add_node($workflownode, $beforekey);
    }

    if (has_capability('gradereport/grader:view', $context) && has_capability('moodle/grade:viewall', $context)) {
        $name = 'Carnet de notes';
        $workflownode = navigation_node::create(
            $name,
            new moodle_url('/grade/report/grader/index.php', ['id' => $courseid]),
            navigation_node::NODETYPE_LEAF,
            'notes',
            'notes'
        );
        $settingnode->add_node($workflownode, $beforekey);
    }

    $restrictlinklist = [
        'gradebooksetup',
    ];

    // Out of course admin page.
    if (strpos($PAGE->url, '/course/admin.php') === false) {
        $restrictlinklist = [
            'editsettings',
            'coursecompletion',
            'users',
            'filtermanagement',
            'gradebooksetup',
            'coursebadges',
            'import',
            'backup',
            'restore',
            'copy',
            'reset',
            'notes',
        ];
    }

    foreach ($restrictlinklist as $restrictlink) {
        if ($link = $settingnode->get($restrictlink)) {
            $link->hide();
        }
    }
}

