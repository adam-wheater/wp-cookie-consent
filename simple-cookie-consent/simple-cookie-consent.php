<?php
/**
 * Plugin Name:       Simple Cookie Consent
 * Description:       Lightweight corner cookie banner. Accept or decline, remembered for 30 days, Google Consent Mode v2 signals, no third-party requests.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Adam Wheater
 * License:           GPL-2.0-or-later
 * Text Domain:       simple-cookie-consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCC_VERSION', '1.0.0' );
define( 'SCC_FILE', __FILE__ );

/**
 * Settings, with defaults. One option row, one array.
 */
function scc_options() {
	$defaults = array(
		'message'      => 'We use cookies to see how the site is used. You can accept or decline.',
		'accept_label' => 'Accept',
		'decline_label' => 'Decline',
		'policy_url'   => get_privacy_policy_url(),
		'policy_label' => 'Privacy policy',
		'position'     => 'bottom-right',
		'days'         => 30,
		'consent_mode' => 1,
		'footer_link'  => 1,
		'footer_label' => 'Cookie settings',
	);

	$saved = get_option( 'scc_options' );

	return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
}

/**
 * Consent Mode v2 defaults, printed as early as possible in <head> so they land
 * before any analytics or ads tag. Reads the cookie in the browser, so a cached
 * page is still correct.
 */
function scc_consent_mode_head() {
	$o = scc_options();

	if ( empty( $o['consent_mode'] ) ) {
		return;
	}
	?>
<script id="scc-consent-mode">
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});
gtag('set','ads_data_redaction',true);gtag('set','url_passthrough',true);
if(document.cookie.indexOf('scc_consent=allow')>-1){gtag('consent','update',{ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted',analytics_storage:'granted'});}
</script>
	<?php
}
add_action( 'wp_head', 'scc_consent_mode_head', 0 );

/**
 * Front-end assets. Banner markup is built in JS so page caching cannot cache
 * one visitor's banner state for everybody.
 */
function scc_enqueue() {
	$o   = scc_options();
	$dir = plugin_dir_path( SCC_FILE );

	$css_ver = file_exists( $dir . 'assets/consent.css' ) ? filemtime( $dir . 'assets/consent.css' ) : SCC_VERSION;
	$js_ver  = file_exists( $dir . 'assets/consent.js' ) ? filemtime( $dir . 'assets/consent.js' ) : SCC_VERSION;

	wp_enqueue_style( 'scc', plugins_url( 'assets/consent.css', SCC_FILE ), array(), $css_ver );
	wp_enqueue_script( 'scc', plugins_url( 'assets/consent.js', SCC_FILE ), array(), $js_ver, true );

	$data = array(
		'message'  => $o['message'],
		'accept'   => $o['accept_label'],
		'decline'  => $o['decline_label'],
		'policyUrl' => $o['policy_url'],
		'policyLabel' => $o['policy_label'],
		'position' => $o['position'],
		'days'     => (int) $o['days'],
		'consentMode' => (int) ! empty( $o['consent_mode'] ),
	);

	wp_add_inline_script( 'scc', 'window.SCC=' . wp_json_encode( $data ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'scc_enqueue' );

/**
 * A way back to the banner. Withdrawing consent has to be as easy as giving it,
 * and a theme that never got a link edited into its footer leaves no way at all.
 * Any element with class `scc-open` does the same job, so a theme that has its
 * own link can turn this off.
 */
function scc_footer_link() {
	$o = scc_options();

	if ( empty( $o['footer_link'] ) || empty( $o['footer_label'] ) ) {
		return;
	}

	echo '<div class="scc-reopen"><button type="button" class="scc-open">' . esc_html( $o['footer_label'] ) . '</button></div>';
}
add_action( 'wp_footer', 'scc_footer_link', 100 );

/* -------------------------------------------------------------------------
 * Admin
 * ---------------------------------------------------------------------- */

function scc_admin_menu() {
	add_options_page(
		'Cookie Consent',
		'Cookie Consent',
		'manage_options',
		'simple-cookie-consent',
		'scc_settings_page'
	);
}
add_action( 'admin_menu', 'scc_admin_menu' );

function scc_settings_link( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=simple-cookie-consent' ) ) . '">Settings</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( SCC_FILE ), 'scc_settings_link' );

function scc_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$o = scc_options();

	if ( isset( $_POST['scc_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['scc_nonce'] ) ), 'scc_save' ) ) {
		$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 30;

		$o = array(
			'message'       => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
			'accept_label'  => sanitize_text_field( wp_unslash( $_POST['accept_label'] ?? '' ) ),
			'decline_label' => sanitize_text_field( wp_unslash( $_POST['decline_label'] ?? '' ) ),
			'policy_url'    => esc_url_raw( wp_unslash( $_POST['policy_url'] ?? '' ) ),
			'policy_label'  => sanitize_text_field( wp_unslash( $_POST['policy_label'] ?? '' ) ),
			'position'      => in_array( $_POST['position'] ?? '', array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' ), true ) ? $_POST['position'] : 'bottom-right',
			'days'          => max( 1, min( 365, $days ) ),
			'consent_mode'  => empty( $_POST['consent_mode'] ) ? 0 : 1,
			'footer_link'   => empty( $_POST['footer_link'] ) ? 0 : 1,
			'footer_label'  => sanitize_text_field( wp_unslash( $_POST['footer_label'] ?? '' ) ),
		);

		update_option( 'scc_options', $o );
		echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
	}

	$positions = array(
		'bottom-right' => 'Bottom right',
		'bottom-left'  => 'Bottom left',
		'top-right'    => 'Top right',
		'top-left'     => 'Top left',
	);
	?>
	<div class="wrap">
		<h1>Cookie Consent</h1>
		<form method="post">
			<?php wp_nonce_field( 'scc_save', 'scc_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="scc-message">Message</label></th>
					<td><textarea id="scc-message" name="message" rows="3" class="large-text"><?php echo esc_textarea( $o['message'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-accept">Accept button</label></th>
					<td><input id="scc-accept" name="accept_label" type="text" class="regular-text" value="<?php echo esc_attr( $o['accept_label'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-decline">Decline button</label></th>
					<td><input id="scc-decline" name="decline_label" type="text" class="regular-text" value="<?php echo esc_attr( $o['decline_label'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-policy-url">Privacy policy URL</label></th>
					<td><input id="scc-policy-url" name="policy_url" type="url" class="regular-text" value="<?php echo esc_attr( $o['policy_url'] ); ?>">
					<p class="description">Leave blank to hide the link.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-policy-label">Privacy policy link text</label></th>
					<td><input id="scc-policy-label" name="policy_label" type="text" class="regular-text" value="<?php echo esc_attr( $o['policy_label'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-position">Position</label></th>
					<td><select id="scc-position" name="position">
						<?php foreach ( $positions as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $o['position'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-days">Remember for (days)</label></th>
					<td><input id="scc-days" name="days" type="number" min="1" max="365" value="<?php echo esc_attr( $o['days'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Footer link</th>
					<td><label><input name="footer_link" type="checkbox" value="1" <?php checked( ! empty( $o['footer_link'] ) ); ?>> Print a link in the footer that reopens the banner</label>
					<p class="description">Turn off if the theme has its own link. Any element with class <code>scc-open</code> works.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-footer-label">Footer link text</label></th>
					<td><input id="scc-footer-label" name="footer_label" type="text" class="regular-text" value="<?php echo esc_attr( $o['footer_label'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Google Consent Mode v2</th>
					<td><label><input name="consent_mode" type="checkbox" value="1" <?php checked( ! empty( $o['consent_mode'] ) ); ?>> Send consent signals to Google tags</label></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<h2>Styling</h2>
		<p>Override the CSS variables on <code>.scc</code> from your theme stylesheet, for example:</p>
		<pre>.scc{--scc-bg:#14161A;--scc-fg:#F1F1EF;--scc-accent:#E3A008;--scc-radius:2px;}</pre>
		<h2>Reopening the banner</h2>
		<p>Link to <code>#cookie-settings</code>, add the class <code>scc-open</code> to any element, or call <code>window.sccOpen()</code>.</p>
	</div>
	<?php
}
