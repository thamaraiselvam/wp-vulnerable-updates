<?php

class WPVU_Vulns_Common{

	const LINK_LIMIT = 4;

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

	/**
	 * Get Security JSON
	 * gets data from the vulnerability database and returns the result in JSON
	 * @param  string $text_domain text domain
	 * @return string              json string of vulnerabilities for the given text domain
	 */
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

	/**
	 * Set Text Domain
	 * sets the text domain to the TextDomain key if it is not set
	 * @param  array $item
	 * @return array updated item
	 */
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
}