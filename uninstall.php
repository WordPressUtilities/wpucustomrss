<?php
defined('ABSPATH') || die;
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/* Delete options */
$options = array(
    'wpucustomrss_options',
    'wpucustomrss_wpucustomrss_version'
);
foreach ($options as $opt) {
    delete_option($opt);
    delete_site_option($opt);
}
