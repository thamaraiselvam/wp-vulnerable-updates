<?php

class WPVU_Vulns_Common{

	const LINK_LIMIT = 3;

	static public function add_multiple_links($urls){

		$count = 0;
		$string = '';
		foreach ($urls as $url) {
			if (self::LINK_LIMIT <= $count) {
				break;
			}
			$string .= "<a style='margin-left: 10px;' target='_blank' href='".$url."' >Reference Link ".++$count."</a>";
		}
		return $string;
	}

	static public function request($url, $text_domain ) {

		$url = $url . $text_domain;
		echo $url;
		$request = wp_remote_get( $url, array( 'sslverify' => false ) );

		if ( is_wp_error( $request ) ) {
		    return false;
		}

		$body = wp_remote_retrieve_body( $request );

		return json_decode( $body );

	}

	static public function set_text_domain( $item ) {

		// get text domain from folder if it isn't listed
		if ( empty( $item['TextDomain'] ) && isset( $item['file_path'] ) ) {
			$folder_name = explode( '/', $item['file_path'] );
			$item['TextDomain'] = $folder_name[0];
		}

		return $item;
	}

	/**
	 * Vulnerable Admin Notice
	 * prints out error message if there is/are vulnerable
	 */
	static public function vulnerable_admin_notice() {
		return false;
		$class = 'notice notice-error is-dismissible';
		$message = __( '<strong>'. WPVU_SHORT_NAME .':</strong> There are some currently installed have known vulnerabilities with their current version. I suggest updating all the available updates', WPVU_SLUG );

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
	}

	static public function after_row_text($update_data, $type) {

		$string =  sprintf(
						__( '%1$s has a known vulnerability that may be affecting this version. Please update this ' . $type . '.', 'vulnerable-theme-checker' ),
						$update_data->Name
					);

		if (empty($update_data->vulnerabilities) || count($update_data->vulnerabilities) < 1) {
			return ;
		}

		foreach ( $update_data->vulnerabilities as $vulnerability ) {

			if ( null != $vulnerability->fixed_in && $vulnerability->fixed_in <= $update_data->Version ) {
				continue;
			}

			$fixed_in = '';
			if ( null !== $vulnerability->fixed_in ) {
				$fixed_in = sprintf(
								__( ' Fixed in version: %s' ),
								$vulnerability->fixed_in
							);
			}

			$string .= $fixed_in ;
			$string .= WPVU_Vulns_Common::add_multiple_links($vulnerability->references->url);

		}

		$class = 'notice notice-error is-dismissible';
		$message = __( '<strong>' . WPVU_SHORT_NAME .':</strong> ' . $string, WPVU_SLUG );

		printf( '<div class="%1$s"><p style="color: #dc3232">%2$s</p></div>', $class, $message );
	}

	static public function remove_updates($cached_updates, $remove_updates, $type){

		foreach ($remove_updates as $update) {
			if (empty($cached_updates->$update)) {
				continue;
			}

			unset($cached_updates->$update);
		}

		update_option( 'wpvu-' . $type . '-updates' ,json_encode( $cached_updates ) );
	}
}