<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';

$start = '/* NEXUS COURSE SEARCH STYLE START */';
$end = '/* NEXUS COURSE SEARCH STYLE END */';

$scss = <<<'SCSS'
/* NEXUS COURSE SEARCH STYLE START */

/*
 * My Courses search field.
 * Keeps Moodle's default toolbar layout and behaviour.
 */
#page-my-courses .block_myoverview input[type="search"],
#page-my-index .block_myoverview input[type="search"],
#page-my-courses .block_myoverview [data-region="search-input"] input,
#page-my-index .block_myoverview [data-region="search-input"] input {
    min-height: 42px;
    padding: 0.55rem 0.9rem;

    border: 1.5px solid rgba(30, 86, 180, 0.58) !important;
    border-radius: 0.65rem !important;

    background: rgba(255, 255, 255, 0.98) !important;
    color: #202936;

    box-shadow:
        0 5px 16px rgba(24, 72, 145, 0.13),
        0 1px 3px rgba(24, 72, 145, 0.08) !important;

    transition:
        border-color 150ms ease,
        box-shadow 150ms ease;
}

#page-my-courses .block_myoverview input[type="search"]::placeholder,
#page-my-index .block_myoverview input[type="search"]::placeholder,
#page-my-courses .block_myoverview [data-region="search-input"] input::placeholder,
#page-my-index .block_myoverview [data-region="search-input"] input::placeholder {
    color: #687487;
    opacity: 1;
}

#page-my-courses .block_myoverview input[type="search"]:focus,
#page-my-index .block_myoverview input[type="search"]:focus,
#page-my-courses .block_myoverview [data-region="search-input"] input:focus,
#page-my-index .block_myoverview [data-region="search-input"] input:focus {
    border-color: #1559b7 !important;
    outline: none !important;

    box-shadow:
        0 0 0 0.2rem rgba(21, 89, 183, 0.14),
        0 7px 20px rgba(24, 72, 145, 0.16) !important;
}

/*
 * No hover animation or movement.
 */
#page-my-courses .block_myoverview input[type="search"]:hover,
#page-my-index .block_myoverview input[type="search"]:hover,
#page-my-courses .block_myoverview [data-region="search-input"] input:hover,
#page-my-index .block_myoverview [data-region="search-input"] input:hover {
    transform: none !important;
}

/* NEXUS COURSE SEARCH STYLE END */
SCSS;

$existing = (string)get_config('theme_moove', 'scss');

$pattern = '/'
    . preg_quote($start, '/')
    . '.*?'
    . preg_quote($end, '/')
    . '/s';

if (preg_match($pattern, $existing)) {
    $updated = preg_replace($pattern, $scss, $existing);
    echo "Updated existing Nexus search styling." . PHP_EOL;
} else {
    $updated = rtrim($existing)
        . PHP_EOL
        . PHP_EOL
        . $scss
        . PHP_EOL;

    echo "Added Nexus search styling." . PHP_EOL;
}

set_config('scss', $updated, 'theme_moove');
theme_reset_all_caches();

echo "Moove theme caches reset." . PHP_EOL;
