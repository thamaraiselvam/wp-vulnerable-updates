<?php

if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
	exit();
}

delete_option('wpvu-plugin-updates');
delete_option('wpvu-theme-updates');
delete_option('wpvu-allow-email');
delete_option('wpvu-email-address');
delete_option('wpvu-last-email-sent');

wp_clear_scheduled_hook('wpvu_check_vulnerable_updates');
