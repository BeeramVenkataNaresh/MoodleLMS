<?php

namespace local_nexusadminenrol;

defined('MOODLE_INTERNAL') || die();

class observer {
    /**
     * Automatically enrol the Nexus administrator
     * whenever a new course is created.
     */
    public static function course_created(
        \core\event\course_created $event
    ): void {
        global $DB;

        $courseid = (int) $event->objectid;

        if ($courseid === SITEID) {
            return;
        }

        $user = $DB->get_record(
            'user',
            [
                'username' => 'admin',
                'deleted' => 0,
            ]
        );

        if (!$user) {
            return;
        }

        $role = $DB->get_record(
            'role',
            ['shortname' => 'editingteacher']
        );

        if (!$role) {
            return;
        }

        $manual = enrol_get_plugin('manual');

        if (!$manual) {
            return;
        }

        $course = $DB->get_record(
            'course',
            ['id' => $courseid],
            '*',
            MUST_EXIST
        );

        $manualinstance = null;

        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }

        if (!$manualinstance) {
            $instanceid = $manual->add_instance($course);

            $manualinstance = $DB->get_record(
                'enrol',
                ['id' => $instanceid],
                '*',
                MUST_EXIST
            );
        }

        if (!$DB->record_exists('user_enrolments', [
            'enrolid' => $manualinstance->id,
            'userid' => $user->id,
        ])) {
            $manual->enrol_user(
                $manualinstance,
                $user->id,
                $role->id
            );
        }
    }
}
