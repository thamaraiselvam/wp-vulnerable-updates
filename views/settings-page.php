<?php

WPVU_Vulns_Common::save_settings($_POST);

$allow_emails_checked = get_option( 'wpvu-allow-email' ) === 'yes' ? 'checked' : '';
$wpvu_email_address = WPVU_Vulns_Common::get_admin_email();

$string  = '<div class="wrap">';
$string .=    '<h2>' . $this->title . ' Settings</h2>';
$string .=    '<form method="post" action="options-general.php?page=wp-vulnerable-updates">';

// need to echo because there is no get_settings_field
echo $string;

// restart string
$string  =       '<p>';
$string .=          sprintf(
						/* translators: %s: admin url for wp mail smtp plugin */
						__( 'Please use an SMTP plugin such as <a href="%s">WP Mail SMTP</a> to prevent dropped emails.', WPVU_SLUG ),
						admin_url( 'plugin-install.php?s=wp+mail+smtp&tab=search&type=term' )
					);
$string .=       '</p>';
$string .=       '<table class="form-table">';
$string .=          '<tr valign="top">';
$string .=             '<th scope="row">' . __( 'Email Address:', WPVU_SLUG ) . '</th>';
$string .=             '<td>';
$string .=                '<input type="text" name="wpvu-email-address"  value="' . $wpvu_email_address . '" />';
$string .=             '</td>';
$string .=          '</tr>';
$string .=          '<tr valign="top">';
$string .=             '<th scope="row">' . __( 'Allow Email Alerts:', WPVU_SLUG ) . '</th>';
$string .=             '<td>';
$string .=                '<input type="checkbox" name="wpvu-allow-email" ' . $allow_emails_checked . ' />';
$string .=             '</td>';
$string .=          '</tr>';
$string .=       '</table>';
$string .=        get_submit_button();
$string .=    '</form>';
$string .= '</div>';

echo $string;