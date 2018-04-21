<?php

class WPVU_Vulns_Theme {
	public $api_url = 'https://wpvulndb.com/api/v2/themes/';
	public $type    = 'theme';

	public function process_themes(){

		$themes            = $this->get_themes();
		$vulnerable_themes = array();

		foreach ( $themes as $key => $theme_meta ) {
			$themes[ $key ] = $this->get_theme_vulnerabilities( $theme_meta ); //appending vulnerable data
		}

		update_option( 'wpvu-theme-updates', json_encode( $themes ) );

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
			$theme['is_known_vulnerable'] = 'no';
			if ( null == $vulnerability->fixed_in || version_compare( $vulnerability->fixed_in, $theme['Version'] ) > 0 ) {
				$theme['is_known_vulnerable'] = 'yes';
			}

		}

		return $theme;
	}

	public function get_installed_themes_cache($only_cache = false) {

		$themes = json_decode( get_option( 'wpvu-theme-updates' ) );

		if ( !empty( $themes ) ) {
			return $themes;
		}

		if ($only_cache) {
			return array();
		}

		// this occurs only right after activation, store theme data on wpvu-theme-updates
		$themes = $this->process_themes();

		if (empty($themes)) {
			return false;
		}

		return $themes;

	}

	public function add_notice(){

		if (get_option('wpvu-theme-updates', 'not_found') === 'not_found') {
			return $this->process_themes();
		}

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


		$head = sprintf(
				__( 'Following themes has known vulnerability that may be affecting this version. Update to latest version to avoid any malicious attacks.<br>', WPVU_SLUG ) );

		// add after theme row text
		foreach ( $themes as $theme ) {

			if (empty($theme)) {
				continue;
			}

			$path = $theme->file_path;
			$added_notice = false;

			if ( empty( $theme->is_known_vulnerable ) ||  'no' == $theme->is_known_vulnerable ) {
				continue ;
			}

			$theme_message = sprintf(
					__( '<br> <strong><i> %s v%s </i></strong> - ', WPVU_SLUG ),
					$theme->Name, $theme->Version);
			$links = WPVU_Vulns_Common::after_row_text($theme, $this->type);
			$message .= __( $theme_message . $links , WPVU_SLUG );

		}

		if (empty($message)) {
			return ;
		}

		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', $head . $message );
	}

	public function get_themes(){

		if (!function_exists( 'wp_get_themes' )) {
			include_once ABSPATH . '/wp-includes/theme.php';
		}

		$installed_themes = wp_get_themes();
		$themes = array();

		foreach ( $installed_themes as $key => $theme_info ) {
			$themes[$key]['Name']       = $theme_info->get( 'Name' );
			$themes[$key]['TextDomain'] = $theme_info->get( 'TextDomain' );
			$themes[$key]['Version']    = $theme_info->get( 'Version' );
			$themes[$key]['file_path']  = $key;
		}

		return $themes;
	}

	public function remove_updates($remove_themes){

		$cached_updates = $this->get_installed_themes_cache($only_cache = true);

		if (empty($cached_updates)) {
			return ;
		}

		WPVU_Vulns_Common::remove_updates($cached_updates, $remove_themes, $this->type);
	}

}