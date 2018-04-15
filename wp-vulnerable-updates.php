<?php
/*
Plugin Name: WP Vulnerable Updates
Plugin URI: https://github.com/thamaraiselvam/wp-vulnerable-updates
Description: WP vulnerable updates is an automated vulnerable checking plugin in real-time and it emails you if any vulnerable updates found in your WordPress site.
Version: 1.0.0
Author: Thamaraiselvam
Author URI: https://github.com/thamaraiselvam/
Text Domain: WP Vulnerable Updates
Tested up to: 4.8.3
*/

defined('ABSPATH') or die('No scripts allowed');

class WP_Vulnerable_Updates {
	public $title;
	public $vulns_common;
	public $vulns_plugin;
	public $vulns_theme;
	public $vulns_core;

	public function __construct() {
		$this->add_constants();
		$this->include_files();
		$this->init_variables();
		$this->add_hooks();
	}

	public function init_variables() {
		$this->title = __( 'WP Vulnerable Updates', WPVU_SLUG );
		$this->vulns_common = new WPVU_Vulns_Common();
		$this->vulns_plugin = new WPVU_Vulns_Plugin();
		$this->vulns_theme = new WPVU_Vulns_Theme();
		$this->vulns_core = new WPVU_Vulns_Core();
	}

	public function add_constants() {
		$this->define('WPVU_PLUGIN_DIR', wp_normalize_path(plugin_dir_path( __FILE__ )));
		$this->define('WPVU_SHORT_NAME', 'WPVU');
		$this->define('WPVU_SLUG', 'wp-vulnerable-updates');
		$this->define('WPVU_DEBUG', true);
	}


	public function define($constant, $value){
		if (defined($constant)) {
			return true;
		}

		define($constant, $value);
	}

	public function include_files() {
		include_once ( WPVU_PLUGIN_DIR . 'includes/class-vulns-common.php' );
		include_once ( WPVU_PLUGIN_DIR . 'includes/class-vulns-core.php' );
		include_once ( WPVU_PLUGIN_DIR . 'includes/class-vulns-plugin.php' );
		include_once ( WPVU_PLUGIN_DIR . 'includes/class-vulns-theme.php' );
	}

	public function add_hooks() {
		register_activation_hook( __FILE__, array( $this, 'on_activation' ) );

		add_action('upgrader_process_complete', array($this, 'remove_update_from_cache'), 10,  2);

		add_action( 'admin_head', array( $this, 'add_admin_notice' ) );

		$this->add_menu();

		add_action( 'wpvu_check_vulnerable_updates', array( $this, 'check_ptc' ) );
		$path = 'akismet';
	}

	public function add_admin_notice(){
		$this->vulns_plugin->add_notice();
		$this->vulns_theme->add_notice();
	}

	public function add_menu(){
		if (defined('MULTISITE') && MULTISITE) {
			add_action('network_admin_menu', array($this, 'add_admin_menu'));
		} else {
			add_action('admin_menu', array($this, 'add_admin_menu'));
		}
	}

	public function add_admin_menu(){
		add_options_page($this->title, $this->title, 'activate_plugins', WPVU_SLUG, array($this, 'settings_page'));
	}

	public function settings_page(){
		include_once WPVU_PLUGIN_DIR . 'views/settings-page.php';
	}

	public function on_activation(){
		if ( ! get_option( 'wpvu-plugin-updates' ) ) {
			add_option( 'wpvu-plugin-updates', '' );
		}

		if ( ! get_option( 'wpvu-plugin-updates' ) ) {
			add_option( 'wpvu-theme-updates', '' );
		}

		//Run vulns check twice daily
		wp_schedule_event( time(), 'twicedaily', 'wpvu_check_vulnerable_updates' );
	}

	public function check_ptc(){
		$this->vulns_plugin->process_plugins();
		$this->vulns_theme->process_themes();
		// $this->vulns_core>process_core();
		$this->vulns_common->send_email();
	}

	public function remove_update_from_cache( $upgrader_object, $options ){

		if ($options['action'] != 'update'){
			return ;
		}

		if($options['type'] == 'plugin' ){
			$this->vulns_plugin->remove_updates($options['plugins']);
		}

		if($options['type'] == 'theme' ){
			$this->vulns_theme->remove_updates($options['themes']);
		}
	}

	public function on_deactivation(){

	}
}

new WP_Vulnerable_Updates();