<?php

class WPVU_Vulns_Common{

	private $plugins;
	private $themes;
	const LINK_LIMIT = 3;

	public function __construct(){
		$this->plugins = new WPVU_Vulns_Plugin();
		$this->themes  = new WPVU_Vulns_Theme();
	}

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

			if ( null == $vulnerability->fixed_in || version_compare( $vulnerability->fixed_in, $plugin->Version ) > 0 ) {
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

	static public function save_settings($settings){
		if (empty($settings) || empty($settings['submit'])) {
			return ;
		}

		if (!empty($settings['wpvu-email-address'])){
			update_option('wpvu-email-address', $settings['wpvu-email-address']);
		}

		if (empty($settings['wpvu-allow-email'])){
			update_option('wpvu-allow-email', 'no');
		} else {
			update_option('wpvu-allow-email', 'yes');
		}
	}

	static public function get_admin_email(){
		return get_option( 'wpvu-email-address' ) ? esc_attr( get_option( 'wpvu-email-address' ) ) : esc_attr( get_option( 'admin_email' ) );
	}

	public function send_email(){

		$send_email = get_option( 'wpvu-allow-email' );

		if (empty($send_email) || $send_email === 'no') {
			return ;
		}

		//Avoid sending multiple emails in short time
		if( !$this->allow_this_email() ){
			return ;
		}

		$plugins  = $this->plugins->get_installed_plugins_cache();
		$themes   = $this->themes->get_installed_themes_cache();
		$message  = $this->get_email_template($plugins, $themes);

		if (!$message) {
			return ;
		}

		$to 	  = $this->get_admin_email();
		$subject  = '(WPVU) - Important! Your WordPress site is vulnerable - ' . $this->get_site_url();
		$response = wp_mail( $to, $subject, $message, $headers = array('Content-Type: text/html'));

		update_option('wpvu-last-email-sent', time());

		$this->wpvu_log($to,'---------$to-----------------');
		$this->wpvu_log($subject,'---------$subject-----------------');
		$this->wpvu_log($message,'---------$message-----------------');
		$this->wpvu_log($response,'---------$response-----------------');
	}

	private function allow_this_email(){
		$last_email_sent = get_option('wpvu-last-email-sent');

		$this->wpvu_log($last_email_sent,'---------$last_email_sent-----------------');

		if (empty($last_email_sent)) {
			return true;
		}

		$next_email_time = $last_email_sent + ( 12 * 60 * 60 );

		$this->wpvu_log($next_email_time,'---------$next_email_time-----------------');

		if (time() < $next_email_time) {
			return false;
		}

		return true;

	}

	private function get_site_url(){
		if(is_multisite()){
			return network_home_url();
		}

		return home_url();
	}

	private function get_email_template($plugins, $themes){

		$head = '<div style="text-align: justify">
					Your WordPress site is susceptible to malicious attacks.<br>
					Kindly update following updates as soon as possible.<br><br>';

		$plugin_html = $theme_html = '';

		foreach ($plugins as $slug => $plugin_data) {
			$this->process_vulnerable_for_email_template($plugin_data, $plugin_html);
		}

		foreach ($themes as $slug => $theme_data) {
			$this->process_vulnerable_for_email_template($theme_data, $theme_html);
		}

		if (!empty($plugin_html)) {
			$plugin_html = '<strong >Plugins :</strong> <br> <ul>' . $plugin_html . '</ul>';
		}

		if (!empty($theme_html)) {
			$theme_html = '<strong >Themes :</strong> <br> <ul>' . $theme_html . '</ul>';
		}

		if (empty($plugin_html) && empty($theme_html) ) {
			return false;
		}

		$footer = '<br> <br> Email has been sent by WP Vulnerable Update Plugin. <br> You can turn off emails from Settings > WP Vulnerable Updates';

		return $head . $plugin_html .$theme_html . $footer . '</div>' ;
	}

	private function process_vulnerable_for_email_template($update, &$html){

		if ( !isset( $plugin->is_known_vulnerable ) ||  'no' == $plugin->is_known_vulnerable ) {
			return ;
		}

		if (empty($update->vulnerabilities) || count($update->vulnerabilities) < 1) {
			return ;
		}

		foreach ( $update->vulnerabilities as $vulnerability ) {

			if ( empty($vulnerability->fixed_in) || $vulnerability->fixed_in >= $update->Version ) {
				continue;
			}

			$fixed_in = '';
			if ( null !== $vulnerability->fixed_in ) {
				$fixed_in = sprintf(
								__( ' Vulnerability fixed in version: %s' ),
								$vulnerability->fixed_in
							);
			}

			$html .= '<li>' . $update->Name . ' - ' . $fixed_in . '</li>';
		}
	}

	static public function wpvu_log($value, $key){
		if (!defined('WPVU_DEBUG') || !WPVU_DEBUG) {
			return ;
		}

		return @file_put_contents(WP_CONTENT_DIR . '/wpvu-logs.txt', "\n -----$key---------- --- " . microtime(true) . "  ----- " . var_export($value, true) . "\n", FILE_APPEND);
	}
}