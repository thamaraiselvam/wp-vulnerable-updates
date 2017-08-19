<?php

/**
* Process vulnerable data
*/
class AVU_Vulns_Theme {
	public $api_url = 'https://wpvulndb.com/api/v2/themes/';

	/**
	 * Process All themes for vulnerablities
	 */
	public function process_themes(){
		$themes     = $this->get_themes();

		$vuln_themes = array();

		foreach ( $themes as $key => $theme_info ) {
			$theme = $this->get_theme_vulnerabilities( $theme, $key );
			$themes[ $key ] = $theme;

			if ( isset( $theme['is_known_vulnerable'] ) && 'true' == $theme['is_known_vulnerable'] ) {
				$name = $theme['Name'];
				$vuln_themes[] = $theme['Name'];
			}
		}

		// echo "<pre>";
		// print_r($theme);

		update_option( 'avu-theme-data', json_encode( $themes ) );
	}


	/**
	 * Get theme Vulnerabilities
	 * get vulnerabilities through API.
	 * @param  array  $theme
	 * @param  string $file_path theme file path
	 * @return array             updated theme
	 */
	public function get_theme_vulnerabilities( $theme, $file_path ) {

		$theme = AVU_Vulns_Common::set_text_domain( $theme );
		$text_domain = $theme['TextDomain'];
		$theme_vuln = AVU_Vulns_Common::request($this->api_url, $text_domain );

		if ( is_object( $theme_vuln ) && property_exists( $theme_vuln, $text_domain ) && is_array( $theme_vuln->$text_domain->vulnerabilities ) ) {

			foreach ( $theme_vuln->$text_domain->vulnerabilities as $vulnerability ) {

				$theme['vulnerabilities'][] = $vulnerability;

				// if theme fix is greater than current version, assume it could be vulnerable
				$theme['is_known_vulnerable'] = 'false';
				if ( null == $vulnerability->fixed_in || version_compare( $vulnerability->fixed_in, $theme['Version'] ) > 0 ) {
					$theme['is_known_vulnerable'] = 'true';
				}

			}

		}

		$theme['file_path'] = $file_path;

		return $theme;

	}

	/**
	 * Get Installed themes Cache
	 * gets the installed themes, checks for vulnerabilities with cached results
	 * @return array installed themes with vulnerability data
	 */
	public function get_installed_themes_cache() {

		$theme_data = json_decode( get_option( 'avu-theme-data' ) );
		if ( ! empty( $theme_data ) ) {

			$themes = json_decode( get_option( 'avu-theme-data' ), true );

			foreach ( $themes as $key => $theme ) {
				$theme = $this->get_cached_theme_vulnerabilities( $theme, $key );
				$themes[ $key ] = $theme;
			}

			return $themes;

		} else {
			// this occurs only right after activation
			$this->process_themes();
		}

	}

	public function add_notice(){

		//return false; //if its not a themes.php page

		add_action( 'admin_notices', array( $this, 'trigger_notice' ) );
	}

	public function trigger_notice(){

		$themes = $this->get_installed_themes_cache();

		$avu_theme_data = json_decode( get_option( 'avu-theme-data' ), true );

		// add after theme row text
		foreach ( $themes as $theme ) {

			$path = $theme['file_path'];
			$added_notice = false;

			if ( isset( $theme['is_known_vulnerable'] ) &&  'true' == $theme['is_known_vulnerable'] ) {
				$this->after_row_text($path, $theme, $avu_theme_data);
			}
		}
	}

	/**
	 * Get Cached theme Vulnerabilities
	 * pulls installed themes, compares version to cached vulnerabilities, adds is_known_vulnerable key to theme.
	 * @param  array  $theme
	 * @param  string $file_path theme file path
	 * @return array             updated theme array
	 */
	public function get_cached_theme_vulnerabilities( $theme, $file_path ) {

		global $installed_themes;

		// TODO: convert to cached installed themes
		if ( ! is_array( $installed_themes ) ) {

			$installed_themes = $this->get_themes();
		}

		$theme = AVU_Vulns_Common::set_text_domain( $theme );

		if ( isset( $installed_themes[ $file_path ]['Version'] ) ) {

			// updated the cached version with the one taken from the currently installed
			$theme['Version'] = $installed_themes[ $file_path ]['Version'];

			if ( isset( $theme['vulnerabilities'] ) && is_array( $theme['vulnerabilities'] ) ) {

				foreach ( $theme['vulnerabilities'] as $vulnerability ) {

					// if theme fix is greater than current version, assume it could be vulnerable
					$theme['is_known_vulnerable'] = 'false';
					if ( null == $vulnerability['fixed_in'] || version_compare( $vulnerability['fixed_in'], $theme['Version'] ) > 0 ) {
						$theme['is_known_vulnerable'] = 'true';
					}

				}

			}

		}

		$theme['file_path'] = $file_path;

		return $theme;

	}

	public function after_row_text( $theme_file, $theme_data, $avu_theme_data ) {

		$string =  sprintf(
						__( '%1$s has a known vulnerability that may be affecting this version. Please update this theme.', 'vulnerable-theme-checker' ),
						$theme_data['Name']
					);

		$vulnerabilities = $this->get_cached_theme_vulnerabilities( $avu_theme_data[ $theme_file ], $theme_file );

		echo "<pre>";
		print_r($vulnerabilities);

		foreach ( $vulnerabilities['vulnerabilities'] as $vulnerability ) {

			if ( null == $vulnerability['fixed_in'] || $vulnerability['fixed_in'] > $theme_data['Version'] ) {

				$fixed_in = '';
				if ( null !== $vulnerability['fixed_in'] ) {
					$fixed_in = sprintf(
									__( 'Fixed in version: %s' ),
									$vulnerability['fixed_in']
								);
				}

				$string .=          '' . $fixed_in ;
				$string .= AVU_Vulns_Common::add_multiple_links($vulnerability['references']['url']);
			}

		}

		$class = 'notice notice-error is-dismissible';
		$message = __( '<strong>'. AVU_SHORT_NAME .':</strong> '.$string.'', AVU_SLUG );

		printf( '<div class="%1$s"><p style="color: #dc3232">%2$s</p></div>', $class, $message );

		// echo $string;

	}

	public function get_themes(){
		if (!function_exists( 'wp_get_themes' )) {
			include_once ABSPATH . '/wp-includes/theme.php';
		}

		$themes = wp_get_themes();
		$theme = array();

		foreach ( $themes as $key => $theme_info ) {
			$theme['Name'] = $theme_info->get( 'Name' );
			$theme['TextDomain'] = $theme_info->get( 'TextDomain' );
			$theme['Version'] = $theme_info->get( 'Version' );
			$theme['file_path'] = $key;
		}

		return $theme;
	}

}