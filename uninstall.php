<?php

if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
	exit();
}

delete_option('wpvu-plugin-updates');
delete_option('wpvu-theme-updates');

wp_clear_scheduled_hook('wpvu_check_vulnerable_updates');
