<?php

/**
* Process vulnerable data
*/
class AVU_Vulns_plugin {
	public $api_url = 'https://wpvulndb.com/api/v2/plugins/';

	/**
	 * Process All plugins for vulnerablities
	 */
	public function process_plugins(){
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		$vuln_plugins = array();

		foreach ( $plugins as $key => $plugin ) {

			$plugin = $this->get_plugin_vulnerabilities( $plugin, $key );
			$plugins[ $key ] = $plugin;

			if ( isset( $plugin['is_known_vulnerable'] ) && 'true' == $plugin['is_known_vulnerable'] ) {
				$name = $plugin['Name'];
				$vuln_plugins[] = $plugin['Name'];
			}
		}

		echo "<pre>";
		print_r($plugins);

		update_option( 'avu-plugin-data', json_encode( $plugins ) );
	}


	/**
	 * Get Plugin Vulnerabilities
	 * get vulnerabilities through API.
	 * @param  array  $plugin
	 * @param  string $file_path plugin file path
	 * @return array             updated plugin
	 */
	public function get_plugin_vulnerabilities( $plugin, $file_path ) {

		$plugin = AVU_Vulns_Common::set_text_domain( $plugin );
		$text_domain = $plugin['TextDomain'];
		$plugin_vuln = AVU_Vulns_Common::request($this->api_url, $text_domain );

		if ( is_object( $plugin_vuln ) && property_exists( $plugin_vuln, $text_domain ) && is_array( $plugin_vuln->$text_domain->vulnerabilities ) ) {

			foreach ( $plugin_vuln->$text_domain->vulnerabilities as $vulnerability ) {

				$plugin['vulnerabilities'][] = $vulnerability;

				// if plugin fix is greater than current version, assume it could be vulnerable
				$plugin['is_known_vulnerable'] = 'false';
				if ( null == $vulnerability->fixed_in || version_compare( $vulnerability->fixed_in, $plugin['Version'] ) > 0 ) {
					$plugin['is_known_vulnerable'] = 'true';
				}

			}

		}

		$plugin['file_path'] = $file_path;

		return $plugin;

	}

	/**
	 * Get Installed Plugins Cache
	 * gets the installed plugins, checks for vulnerabilities with cached results
	 * @return array installed plugins with vulnerability data
	 */
	public function get_installed_plugins_cache() {

		$plugin_data = json_decode( get_option( 'avu-plugin-data' ) );
		if ( ! empty( $plugin_data ) ) {

			if ( ! function_exists( 'get_plugins' ) ) {
		        require_once ABSPATH . 'wp-admin/includes/plugin.php';
		    }

			$plugins = json_decode( get_option( 'avu-plugin-data' ), true );

			foreach ( $plugins as $key => $plugin ) {
				$plugin = $this->get_cached_plugin_vulnerabilities( $plugin, $key );
				$plugins[ $key ] = $plugin;
			}

			return $plugins;

		} else {
			// this occurs only right after activation
			$this->process_plugins();
		}

	}

	public function add_notice(){
		$plugins = $this->get_installed_plugins_cache();

		// add after plugin row text
		foreach ( $plugins as $plugin ) {

			$path = $plugin['file_path'];
			$added_notice = false;

			if ( isset( $plugin['is_known_vulnerable'] ) &&  'true' == $plugin['is_known_vulnerable'] ) {
				add_action( 'after_plugin_row_' . $path, array( $this, 'after_row_text' ), 10, 3 );

				if ( ! $added_notice ) {
					// add_action( 'admin_notices', array( $this, 'vulnerable_admin_notice' ) );
					$added_notice = true;
				}
			}

		}
	}

	/**
	 * Get Cached Plugin Vulnerabilities
	 * pulls installed plugins, compares version to cached vulnerabilities, adds is_known_vulnerable key to plugin.
	 * @param  array  $plugin
	 * @param  string $file_path plugin file path
	 * @return array             updated plugin array
	 */
	public function get_cached_plugin_vulnerabilities( $plugin, $file_path ) {

		global $installed_plugins;

		// TODO: convert to cached installed plugins
		if ( ! is_array( $installed_plugins ) ) {

			if ( ! function_exists( 'get_plugins' ) ) {
		        require_once ABSPATH . 'wp-admin/includes/plugin.php';
		    }

			$installed_plugins = get_plugins();
		}

		$plugin = AVU_Vulns_Common::set_text_domain( $plugin );

		if ( isset( $installed_plugins[ $file_path ]['Version'] ) ) {

			// updated the cached version with the one taken from the currently installed
			$plugin['Version'] = $installed_plugins[ $file_path ]['Version'];

			if ( isset( $plugin['vulnerabilities'] ) && is_array( $plugin['vulnerabilities'] ) ) {

				foreach ( $plugin['vulnerabilities'] as $vulnerability ) {

					// if plugin fix is greater than current version, assume it could be vulnerable
					$plugin['is_known_vulnerable'] = 'false';
					if ( null == $vulnerability['fixed_in'] || version_compare( $vulnerability['fixed_in'], $plugin['Version'] ) > 0 ) {
						$plugin['is_known_vulnerable'] = 'true';
					}

				}

			}

		}

		$plugin['file_path'] = $file_path;

		return $plugin;

	}

	public function after_row_text( $plugin_file, $plugin_data, $status ) {

		global $avu_plugin_data;

		if ( ! is_array( $avu_plugin_data ) ) {
			$avu_plugin_data = json_decode( get_option( 'avu-plugin-data' ), true );
		}

		$message =  sprintf(
						__( '%1$s has a known vulnerability that may be affecting this version. Please update this plugin.', 'vulnerable-plugin-checker' ),
						$plugin_data['Name']
					);

		$string  = '<tr class="active update">';
		$string .=    '<td style="border-left: 4px solid #dc3232; border-bottom: 1px solid #E2E2E2;">&nbsp;</td>';
		$string .=    '<td colspan="2" style="border-bottom: 1px solid #E2E2E2; color: #dc3232;">';
		$string .=       '<p style="color: #dc3232"><strong>' . $message . '</strong> ';

		$vulnerabilities = $this->get_cached_plugin_vulnerabilities( $avu_plugin_data[ $plugin_file ], $plugin_file );
		foreach ( $vulnerabilities['vulnerabilities'] as $vulnerability ) {

			if ( null == $vulnerability['fixed_in'] || $vulnerability['fixed_in'] > $plugin_data['Version'] ) {

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

		$string .=    '</p></td>';
		$string .= '</tr>';

		echo $string;

	}

}