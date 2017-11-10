<?php

/*
Plugin Name: Vulnerable Updates
Description: Find vulsn updates.
Version: 1.0.0
Author: Thamaraiselvam
Author URI: https://thamaraiselvam.com/
Text Domain: Vulnerable Updates
*/

defined('ABSPATH') or die('No script please!');

class WP_Vulnerable_Updates {
	public $title;
	// public $menu_title;
	public $vulns_common;
	public $vulns_plugin;
	public $vulns_theme;
	public $vulns_core;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->add_constants();
		$this->include_files();
		$this->init_variables();
		$this->add_hooks();
	}

	/**
	 * Initilialize all variables
	 */
	public function init_variables() {
		$this->title = __( 'WP Vulnerable Updates', WPVU_SLUG );
		// $this->menu_title = __( 'WPVU Settings', WPVU_SLUG );
		$this->vulns_common = new WPVU_Vulns_Common();
		$this->vulns_plugin = new WPVU_Vulns_Plugin();
		$this->vulns_theme = new WPVU_Vulns_Theme();
		$this->vulns_core = new WPVU_Vulns_Core();
	}

	/**
	 * Define all constants
	 */
	public function add_constants() {
		$this->define('WPVU_PLUGIN_DIR', wp_normalize_path(plugin_dir_path( __FILE__ )));
		$this->define('WPVU_SHORT_NAME', 'WPVU');
		$this->define('WPVU_SLUG', 'wp-vulnerable-updates');
	}


	public function define($constant, $value){
		if (defined($constant)) {
			return true;
		}

		define($constant, $value);
	}

	/**
	 * Include all necessary files to run
	 */
	public function include_files() {
		// include_once ( WPVU_PLUGIN_DIR . 'includes/class-process-vulns.php' );
		include_once ( WPVU_PLUGIN_DIR . 'includes/class-vulns-common.php' );
		include_once ( WPVU_PLUGIN_DIR . 'includes/class-vulns-core.php' );
		include_once ( WPVU_PLUGIN_DIR . 'includes/class-vulns-plugin.php' );
		include_once ( WPVU_PLUGIN_DIR . 'includes/class-vulns-theme.php' );
	}

	/**
	 * Add actions and filters here
	 */
	public function add_hooks() {
		register_activation_hook( __FILE__, array( $this, 'on_activation' ) );

		add_action( 'admin_head', array( $this, 'admin_head' ) );

		$this->add_menu();

		add_action( 'wpvu_check_ptc', array( $this, 'check_ptc' ) );
		$path = 'akismet';
	}

	public function admin_head(){
		$this->shows_alert_row();
	}

	public function shows_alert_row(){
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
		add_menu_page($this->title, $this->title, 'activate_plugins', WPVU_SLUG, array($this, 'settings_page'), 'dashicons-wptc', '80.0564');
	}

	public function settings_page(){
		$this->check_ptc();
		return false;
		include_once WPVU_PLUGIN_DIR . 'views/settings-page.php';
	}

	/**
	 * Activation todo
	 */
	public function on_activation(){
		if ( ! get_option( 'wpvu-plugin-data' ) ) {
			add_option( 'wpvu-plugin-data', '' );
		}

		//Run vulns check twice daily
		wp_schedule_event( time(), 'twicedaily', 'wpvu_check_ptc' );
	}

	/**
	 * Check Plugins, Themes and WordPress Core updates for Vulns
	 */
	public function check_ptc(){
		// $this->vulns_plugin->process_plugins();
		$this->vulns_theme->process_themes();
		// $this->vulns_core>process_core();
	}

	public function on_deactivation(){

	}
}

new WP_Vulnerable_Updates();