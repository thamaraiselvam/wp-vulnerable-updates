<?php

class WPVU_Vulns_Theme {
	public $api_url = 'https://wpvulndb.com/api/v2/themes/';
	public $type = 'theme';

	public function process_themes(){

		$themes     = $this->get_themes();

		$vulnerable_themes = array();

		foreach ( $themes as $key => $theme_meta ) {
			$themes[ $key ] = $this->get_theme_vulnerabilities( $theme_meta ); //appending vulnerable data
		}

		update_option( 'wpvu-theme-data', json_encode( $themes ) );

		return $themes;
	}

	public function get_theme_vulnerabilities( $theme ) {

		$theme = WPVU_Vulns_Common::set_text_domain( $theme );
		$text_domain = $theme['TextDomain'];
		$theme_vulnerabilities = WPVU_Vulns_Common::request($this->api_url, $text_domain );

		if (! is_object( $theme_vulnerabilities ) ){
			return $theme;
		}

		//sometimes vulns db has lower theme name
		if ( !property_exists( $theme_vulnerabilities, $text_domain ) ){
			if( !property_exists( $theme_vulnerabilities, strtolower($text_domain) ) ) {
				return $theme;
			}

			$text_domain = strtolower($text_domain);
		}

		if( !is_array( $theme_vulnerabilities->$text_domain->vulnerabilities ) || count($theme_vulnerabilities->$text_domain->vulnerabilities) < 1 ) {
			return $theme;
		}

		foreach ( $theme_vulnerabilities->$text_domain->vulnerabilities as $vulnerability ) {

			$theme['vulnerabilities'][] = $vulnerability;

			// if theme fix is greater than current version, assume it is vulnerable
			$theme['is_known_vulnerable'] = 'false';
			if ( null == $vulnerability->fixed_in || version_compare( $vulnerability->fixed_in, $theme['Version'] ) > 0 ) {
				$theme['is_known_vulnerable'] = 'true';
			}

		}

		return $theme;
	}

	public function get_installed_themes_cache() {

		$themes = json_decode( get_option( 'wpvu-theme-data' ) );

		if ( !empty( $themes ) ) {
			return $themes;
		}

		// this occurs only right after activation, store theme data on wpvu-theme-data
		$themes = $this->process_themes();

		if (empty($themes)) {
			return false;
		}

		return $themes;

	}

	public function add_notice(){

		if (!$this->can_load_notices()) {
			return ;
		}

		add_action( 'admin_notices', array( $this, 'trigger_notice' ) );
	}

	private function can_load_notices(){

		if (!empty($_SERVER['REQUEST_URI']) &&
			strpos($_SERVER['REQUEST_URI'], 'themes.php') !== false ||
			strpos($_SERVER['REQUEST_URI'], 'update-core.php') !== false
			) {
			return true;
		}

		return false;
	}

	public function trigger_notice(){
		//get cached data with vulns info
		$themes = $this->get_installed_themes_cache();

		if (empty($themes)) {
			return ;
		}

		// add after theme row text
		foreach ( $themes as $theme ) {

			$path = $theme->file_path;
			$added_notice = false;

			if ( !empty( $theme->is_known_vulnerable ) &&  'true' == $theme->is_known_vulnerable ) {
				WPVU_Vulns_Common::after_row_text($theme, $this->type);
			}
		}
	}

	public function get_themes(){

		if (!function_exists( 'wp_get_themes' )) {
			include_once ABSPATH . '/wp-includes/theme.php';
		}

		$installed_themes = wp_get_themes();
		$themes = array();

		foreach ( $installed_themes as $key => $theme_info ) {
			$themes[$key]['Name'] = $theme_info->get( 'Name' );
			$themes[$key]['TextDomain'] = $theme_info->get( 'TextDomain' );
			$themes[$key]['Version'] = $theme_info->get( 'Version' );
			$themes[$key]['file_path'] = $key;
		}

		return $themes;
	}

}