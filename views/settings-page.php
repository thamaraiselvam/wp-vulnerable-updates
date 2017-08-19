<?php

$allow_emails_checked = get_option( 'avu_allow_emails' ) ? 'checked' : '';

$string  = '<div class="wrap">';
$string .=    '<h2>' . $this->title . ' Settings</h2>';
$string .=    '<form method="post" action="options.php">';

// need to echo because there is no get_settings_field
echo $string;

// restart string
$string  =       '<p>';
$string .=          sprintf(
						/* translators: %s: admin url for wp mail smtp plugin */
						__( 'Please use an SMTP plugin such as <a href="%s">WP Mail SMTP</a> to prevent dropped emails.', AVU_SLUG ),
						admin_url( 'plugin-install.php?s=wp+mail+smtp&tab=search&type=term' )
					);
$string .=       '</p>';
$string .=       '<table class="form-table">';
$string .=          '<tr valign="top">';
$string .=             '<th scope="row">' . __( 'Email Address:', AVU_SLUG ) . '</th>';
$string .=             '<td>';
$string .=                '<input type="text" name="avu_email_address" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" value="' . esc_attr( get_option( 'avu_email_address' ) ) . '" />';
$string .=             '</td>';
$string .=          '</tr>';
$string .=          '<tr valign="top">';
$string .=             '<th scope="row">' . __( 'Allow Email Alerts:', AVU_SLUG ) . '</th>';
$string .=             '<td>';
$string .=                '<input type="checkbox" name="avu_allow_emails" ' . $allow_emails_checked . ' />';
$string .=             '</td>';
$string .=          '</tr>';
$string .=       '</table>';
$string .=        get_submit_button();
$string .=    '</form>';
$string .= '</div>';

echo $string;