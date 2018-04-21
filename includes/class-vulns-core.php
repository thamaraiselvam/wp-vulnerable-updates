<?php

class WPVU_Vulns_Core{

	public $api_url = 'https://wpvulndb.com/api/v2/wordpresses/';
	public $type    = 'core';

	public function process_core() {

		$version = $this->get_core_version();

		if ( empty($version) ) {
			return ;
		}

		$core_vulns = $this->get_core_vulnerabilities( $version ); //appending vulnerable data

		update_option( 'wpvu-core-updates', json_encode( $core_vulns ) );

		return $core_vulns;
	}

	public function get_core_version(){
		@include( ABSPATH . WPINC . '/version.php' );
		return $wp_version;
	}

	public function get_core_vulnerabilities( $version ) {

		$removed_dot = str_replace('.', '', $version);

		$core_vulnerabilities = WPVU_Vulns_Common::request($this->api_url, $removed_dot );

		if (! is_object( $core_vulnerabilities ) ){
			return false;
		}

		if( !is_array( $core_vulnerabilities->$version->vulnerabilities ) || count($core_vulnerabilities->$version->vulnerabilities) < 1 ) {
			return false;
		}

		foreach ( $core_vulnerabilities->$version->vulnerabilities as $vulnerability ) {
			$core['vulnerabilities'][]   = $vulnerability;
			$core['is_known_vulnerable'] = 'yes';
			$core['Name']                = 'Wordpress';
			$core['Version']             = $version;
		}

		return $core;
	}

	public function get_installed_core_cache() {

		$core_vulns = json_decode( get_option( 'wpvu-core-updates' ) );

		if ( empty( $core_vulns ) ) {
			return array();
		}

		return $core_vulns;
	}

	public function add_notice(){

		if (get_option('wpvu-core-updates', 'not_found') === 'not_found') {
			return $this->process_core();
		}

		if ($this->can_load_admin_notices()) {
			add_action( 'admin_notices', array( $this, 'trigger_admin_notice' ) );
		}
	}

	public function trigger_admin_notice(){

		$core = $this->get_installed_core_cache();

		if (empty($core)) {
			return ;
		}

		if ( !isset( $core->is_known_vulnerable ) ||  'no' == $core->is_known_vulnerable ) {
			return ;
		}

		$notice_msg = sprintf(
				__( '<strong><i> %s v%s </i></strong> has a known vulnerability that may be affecting this version. Update to latest version to avoid any malicious attacks.', WPVU_SLUG ),
				$core->Name, $core->Version
			);

		$links  = WPVU_Vulns_Common::after_row_text($core, $this->type);
		$class   = 'notice notice-error is-dismissible';
		$message = __( '<strong>' . WPVU_SHORT_NAME .':</strong> ' . $notice_msg . $links , WPVU_SLUG );

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
	}

	private function can_load_admin_notices(){

		if (!empty($_SERVER['REQUEST_URI']) &&
			strpos($_SERVER['REQUEST_URI'], 'update-core.php') !== false
			) {
			return true;
		}

		return false;
	}
}