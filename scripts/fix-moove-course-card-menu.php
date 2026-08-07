<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';

$markerstart = '/* NEXUS COURSE CARD MENU FIX START */';
$markerend = '/* NEXUS COURSE CARD MENU FIX END */';

$css = <<<'SCSS'
/* NEXUS COURSE CARD MENU FIX START */

/*
 * Allow course-card dropdown menus to render above surrounding cards.
 */
#page-my-courses .block_myoverview,
#page-my-index .block_myoverview,
#page-my-courses .block_myoverview [data-region="course-content"],
#page-my-index .block_myoverview [data-region="course-content"],
#page-my-courses .block_myoverview .card-grid,
#page-my-index .block_myoverview .card-grid,
#page-my-courses .block_myoverview .dashboard-card-deck,
#page-my-index .block_myoverview .dashboard-card-deck {
    overflow: visible !important;
}

/*
 * Establish predictable stacking for each card.
 */
#page-my-courses .block_myoverview .dashboard-card,
#page-my-index .block_myoverview .dashboard-card {
    position: relative;
    z-index: 1;
    overflow: visible !important;
}

/*
 * Raise the active card above every surrounding card.
 * Chrome and current Safari support :has().
 */
#page-my-courses .block_myoverview .dashboard-card:has(.dropdown-menu.show),
#page-my-index .block_myoverview .dashboard-card:has(.dropdown-menu.show) {
    z-index: 1080 !important;
}

/*
 * Keep the dropdown above cards, badges and footer elements.
 */
#page-my-courses .block_myoverview .dropdown-menu,
#page-my-index .block_myoverview .dropdown-menu,
#page-my-courses .block_myoverview .dropdown-menu.show,
#page-my-index .block_myoverview .dropdown-menu.show {
    z-index: 1090 !important;
}

/*
 * Ensure card information containers do not clip the action menu.
 */
#page-my-courses .block_myoverview .course-info-container,
#page-my-index .block_myoverview .course-info-container,
#page-my-courses .block_myoverview .card-body,
#page-my-index .block_myoverview .card-body {
    overflow: visible !important;
}

/*
 * Add a little spacing between rows so open menus have room.
 */
#page-my-courses .block_myoverview .dashboard-card,
#page-my-index .block_myoverview .dashboard-card {
    margin-bottom: 0.75rem;
}

/* NEXUS COURSE CARD MENU FIX END */
SCSS;

$existing = (string)get_config('theme_moove', 'scss');

$pattern = '/'
    . preg_quote($markerstart, '/')
    . '.*?'
    . preg_quote($markerend, '/')
    . '/s';

if (preg_match($pattern, $existing)) {
    $updated = preg_replace($pattern, $css, $existing);
    echo "Updated existing Nexus course-card menu CSS." . PHP_EOL;
} else {
    $updated = rtrim($existing)
        . PHP_EOL
        . PHP_EOL
        . $css
        . PHP_EOL;

    echo "Added Nexus course-card menu CSS." . PHP_EOL;
}

set_config('scss', $updated, 'theme_moove');

theme_reset_all_caches();

echo "Moove caches reset successfully." . PHP_EOL;
