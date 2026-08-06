<?php
// Post-install settings that belong in Moodle's persistent configuration, not public content.
define('CLI_SCRIPT', true);

require('/var/www/moodle/config.php');

set_config('nexus_portal_name', (string) getenv('MOODLE_PORTAL_NAME'));
set_config('nexus_production_url', (string) getenv('MOODLE_PRODUCTION_URL'));
set_config('nexus_public_website', (string) getenv('MOODLE_PUBLIC_WEBSITE'));

// Configure a dedicated Redis cache instance for Moodle application and MUC session caches.
// lib/setup.php, loaded by config.php, provides the core_cache autoloader.
$storeName = 'nexus_redis';
$writer = \core_cache\config_writer::instance();
$stores = $writer->get_all_stores();

if (!array_key_exists($storeName, $stores)) {
    $writer->add_store_instance($storeName, 'redis', [
        'server' => getenv('REDIS_HOST') . ':' . getenv('REDIS_PORT'),
        'password' => getenv('REDIS_PASSWORD'),
        'prefix' => 'nexuseps_muc',
        'serializer' => Redis::SERIALIZER_PHP,
        // cachestore_redis is loaded by add_store_instance; 0 is its documented no-compression value.
        'compressor' => 0,
        'connectiontimeout' => 3,
    ]);
}

$writer = \core_cache\config_writer::instance();
$writer->set_mode_mappings([
    \core_cache\store::MODE_APPLICATION => [$storeName],
    \core_cache\store::MODE_SESSION => [$storeName],
    \core_cache\store::MODE_REQUEST => ['default_request'],
]);

echo "Configured Redis for Moodle sessions and application caching.\n";
