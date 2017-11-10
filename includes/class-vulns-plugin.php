<?php

class WPVU_Vulns_plugin {
	public $api_url = 'https://wpvulndb.com/api/v2/plugins/';
	public $type = 'plugin';

	public function process_plugins() {

		$plugins = $this->get_plugins();

		if ( empty($plugins) ) {
			return ;
		}

		$vulnerable_plugins = array();

		foreach ( $plugins as $key => $plugin_meta ) {
			$plugins[ $key ] = $this->get_plugin_vulnerabilities( $plugin_meta, $key ); //appending vulnerable data
		}

		update_option( 'wpvu-plugin-data', json_encode( $plugins ) );

		return $plugins;
	}

	private function get_plugins(){
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins();
	}

	public function get_plugin_vulnerabilities( $plugin , $key) {

		$plugin = WPVU_Vulns_Common::set_text_domain( $plugin );
		$text_domain = $plugin['TextDomain'];
		$plugin_vulnerabilities = WPVU_Vulns_Common::request($this->api_url, $text_domain );

		$plugin['file_path'] = $key;

		if (! is_object( $plugin_vulnerabilities ) ){
			return $plugin;
		}

		//sometimes vulns db has lower theme name
		if ( !property_exists( $plugin_vulnerabilities, $text_domain ) ){
			if( !property_exists( $plugin_vulnerabilities, strtolower($text_domain) ) ) {
				return $plugin;
			}

			$text_domain = strtolower($text_domain);
		}

		if( !is_array( $plugin_vulnerabilities->$text_domain->vulnerabilities ) || count($plugin_vulnerabilities->$text_domain->vulnerabilities) < 1 ) {
			return $plugin;
		}

		foreach ( $plugin_vulnerabilities->$text_domain->vulnerabilities as $vulnerability ) {

			$plugin['vulnerabilities'][] = $vulnerability;

			// if plugin fix is greater than current version, assume it is vulnerable
			$plugin['is_known_vulnerable'] = 'false';
			if ( null == $vulnerability->fixed_in || version_compare( $vulnerability->fixed_in, $plugin['Version'] ) > 0 ) {
				$plugin['is_known_vulnerable'] = 'true';
			}

		}

		return $plugin;
	}

	public function get_installed_plugins_cache($only_cache = false) {

		$plugins = json_decode( get_option( 'wpvu-plugin-data' ) );

		if ( ! empty( $plugins ) ) {
			return $plugins;
		}

		if ($only_cache) {
			return array();
		}

		$plugins = $this->process_plugins();

		if (empty($plugins)) {
			return false;
		}

		return $plugins;
	}

	public function add_notice(){

		if ($this->can_load_admin_notices()) {
			add_action( 'admin_notices', array( $this, 'trigger_admin_notice' ) );
		}

		if ($this->can_plugin_row_notices()) {
			add_action( 'admin_notices', array( $this, 'trigger_plugin_row_notice' ) );
		}

	}

	public function trigger_plugin_row_notice(){

		$plugins = $this->get_installed_plugins_cache();

		if (empty($plugins)) {
			return ;
		}

		foreach ( $plugins as $plugin ) {

			if ( isset( $plugin->is_known_vulnerable ) &&  'true' == $plugin->is_known_vulnerable ) {
				add_action( 'after_plugin_row_' . $plugin->file_path, array( $this, 'after_row_text' ), 10, 3 );
			}

		}
	}

	public function trigger_admin_notice(){

		$plugins = $this->get_installed_plugins_cache();

		if (empty($plugins)) {
			return ;
		}

		foreach ( $plugins as $plugin ) {

			if ( isset( $plugin->is_known_vulnerable ) &&  'true' == $plugin->is_known_vulnerable ) {
				WPVU_Vulns_Common::after_row_text($plugin, $this->type);
			}

		}
	}

	private function can_load_admin_notices(){

		if (!empty($_SERVER['REQUEST_URI']) &&
			strpos($_SERVER['REQUEST_URI'], 'update-core.php') !== false
			) {
			return true;
		}

		return false;
	}

	private function can_plugin_row_notices(){

		if (!empty($_SERVER['REQUEST_URI']) &&
			strpos($_SERVER['REQUEST_URI'], 'plugins.php') !== false
			) {
			return true;
		}

		return false;
	}

	public function after_row_text($name, $plugin_data, $extra ) {

		$plugins_data = $this->get_installed_plugins_cache();

		if (empty($plugins_data) || empty($name)) {
			return ;
		}

		$plugin_data = $plugins_data->$name;

		$message =  sprintf(
						__( '%1$s has a known vulnerability that may be affecting this version. Update this plugin.', 'vulnerable-plugin-checker' ),
						$plugin_data->Name
					);

		$string  = '<tr class="active update">';
		$string .=    '<td style="border-left: 4px solid #dc3232; border-bottom: 1px solid #E2E2E2;">&nbsp;</td>';
		$string .=    '<td colspan="2" style="border-bottom: 1px solid #E2E2E2; color: #dc3232;">';
		$string .=       '<p style="color: #dc3232"><strong>' . $message . '</strong> ';

		if (empty($plugin_data->vulnerabilities) || count($plugin_data->vulnerabilities) < 1) {
			return ;
		}

		foreach ( $plugin_data->vulnerabilities as $vulnerability ) {

			if ( null != $vulnerability->fixed_in && $vulnerability->fixed_in <= $plugin_data->Version ) {
				continue;
			}

			$fixed_in = '';

			if ( null !== $vulnerability->fixed_in ) {
				$fixed_in = sprintf( __( ' Fixed in version: %s' ), $vulnerability->fixed_in );
			}

			$string .= $fixed_in ;
			$string .= WPVU_Vulns_Common::add_multiple_links($vulnerability->references->url);

		}

		$string .= '</p></td>';
		$string .= '</tr>';

		echo $string;
	}

	public function remove_updates($remove_plugins){

		$cached_updates = $this->get_installed_plugins_cache($only_cache = true);

		if (empty($cached_updates)) {
			return ;
		}

		WPVU_Vulns_Common::remove_updates($cached_updates, $remove_plugins, $this->type);
	}

}