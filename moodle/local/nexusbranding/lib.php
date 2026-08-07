<?php

defined('MOODLE_INTERNAL') || die();

function local_nexusbranding_before_standard_html_head() {
    global $PAGE;

    $PAGE->requires->css(
        '/local/nexusbranding/styles/nexus.css'
    );

    $PAGE->requires->js(
        '/local/nexusbranding/js/nexus.js',
        true
    );

    return '';
}
