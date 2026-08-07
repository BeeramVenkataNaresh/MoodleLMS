<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Inject Nexus branding globally.
 */
function local_nexusbranding_before_standard_html_head(): string {
    global $CFG;

    $base = $CFG->wwwroot . '/local/nexusbranding';

    return '
        <link
            rel="icon"
            type="image/png"
            href="' . $base . '/pix/site-icon.png"
        >

        <link
            rel="apple-touch-icon"
            href="' . $base . '/pix/site-icon.png"
        >

        <link
            rel="stylesheet"
            href="' . $base . '/styles/nexus.css?v=2026080701"
        >

        <script
            src="' . $base . '/js/nexus.js?v=2026080701"
            defer
        ></script>
    ';
}
