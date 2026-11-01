<?php
/**
 * Runnable check for the plugin's PHP. Stubs the WordPress functions it calls,
 * loads the plugin, and asserts the parts that break silently on a live site:
 * defaults, the head snippet, escaping in the settings form, and that every
 * asset the enqueue points at is actually on disk.
 *
 *   php tools/check.php
 */

$plugin_dir = __DIR__ . '/../simple-cookie-consent/';

define( 'ABSPATH', $plugin_dir );

$GLOBALS['scc_test'] = array(
	'option'  => false,
	'actions' => array(),
	'styles'  => array(),
	'scripts' => array(),
	'inline'  => array(),
);

/* --- WordPress stubs ---------------------------------------------------- */

function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['scc_test']['actions'][ $hook ][] = $cb;
}
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {}
function get_option( $name, $default = false ) {
	return 'scc_options' === $name ? $GLOBALS['scc_test']['option'] : $default;
}
function update_option( $name, $value ) {
	$GLOBALS['scc_test']['option'] = $value;
	return true;
}
function get_privacy_policy_url() {
	return 'https://example.test/privacy/';
}
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function plugins_url( $path, $file ) {
	return 'https://example.test/wp-content/plugins/simple-cookie-consent/' . $path;
}
function plugin_basename( $file ) {
	return 'simple-cookie-consent/' . basename( $file );
}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	$GLOBALS['scc_test']['styles'][ $handle ] = $src;
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['scc_test']['scripts'][ $handle ] = array( 'src' => $src, 'footer' => $footer );
}
function wp_add_inline_script( $handle, $js, $position = 'after' ) {
	$GLOBALS['scc_test']['inline'][] = $js;
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function add_options_page( $page, $menu, $cap, $slug, $cb ) {}
function admin_url( $path ) {
	return 'https://example.test/wp-admin/' . $path;
}
function current_user_can( $cap ) {
	return true;
}
function wp_verify_nonce( $nonce, $action ) {
	return 'good' === $nonce;
}
function wp_nonce_field( $action, $name ) {
	echo '<input type="hidden" name="' . $name . '" value="good">';
}
function wp_unslash( $v ) {
	return $v;
}
function sanitize_key( $v ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) );
}
function sanitize_text_field( $v ) {
	return trim( strip_tags( (string) $v ) );
}
function sanitize_textarea_field( $v ) {
	return trim( strip_tags( (string) $v ) );
}
function esc_url_raw( $v ) {
	return filter_var( $v, FILTER_VALIDATE_URL ) ? $v : '';
}
function esc_url( $v ) {
	return htmlspecialchars( $v, ENT_QUOTES );
}
function esc_attr( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES );
}
function esc_html( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES );
}
function esc_textarea( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES );
}
function selected( $a, $b ) {
	echo $a === $b ? ' selected' : '';
}
function checked( $a, $b = true ) {
	echo $a == $b ? ' checked' : '';
}
function submit_button() {
	echo '<button type="submit">Save</button>';
}

/* --- Load --------------------------------------------------------------- */

require $plugin_dir . 'simple-cookie-consent.php';

$fail = 0;
function check( $label, $ok ) {
	global $fail;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
}

/* --- Defaults ----------------------------------------------------------- */

$o = scc_options();
check( 'default consent window is 30 days', 30 === $o['days'] );
check( 'default position is a corner', 'bottom-right' === $o['position'] );
check( 'privacy URL defaults to the site policy', 'https://example.test/privacy/' === $o['policy_url'] );

/* Saved options merge over defaults rather than replacing them. */
$GLOBALS['scc_test']['option'] = array( 'days' => 7 );
$o = scc_options();
check( 'saved options merge over defaults', 7 === $o['days'] && 'Accept' === $o['accept_label'] );
$GLOBALS['scc_test']['option'] = false;

/* --- Head snippet ------------------------------------------------------- */

ob_start();
scc_consent_mode_head();
$head = ob_get_clean();
check( 'head denies storage by default', false !== strpos( $head, "'consent','default'" ) && false !== strpos( $head, "ad_storage:'denied'" ) );
check( 'head grants from the cookie, so page cache is safe', false !== strpos( $head, "document.cookie.indexOf('scc_consent=allow')" ) );
check( 'head snippet runs at priority 0 on wp_head', isset( $GLOBALS['scc_test']['actions']['wp_head'] ) );

$GLOBALS['scc_test']['option'] = array( 'consent_mode' => 0 );
ob_start();
scc_consent_mode_head();
check( 'consent mode off prints nothing', '' === ob_get_clean() );
$GLOBALS['scc_test']['option'] = false;

/* --- Enqueue ------------------------------------------------------------ */

scc_enqueue();
$t = $GLOBALS['scc_test'];
check( 'stylesheet enqueued', isset( $t['styles']['scc'] ) );
check( 'script enqueued in the footer', ! empty( $t['scripts']['scc']['footer'] ) );
check( 'config passed to JS before the script', 1 === count( $t['inline'] ) && 0 === strpos( $t['inline'][0], 'window.SCC=' ) );

$json = json_decode( substr( $t['inline'][0], strlen( 'window.SCC=' ), -1 ), true );
check( 'config carries the day count as an int', isset( $json['days'] ) && 30 === $json['days'] );

foreach ( array( 'assets/consent.css', 'assets/consent.js' ) as $asset ) {
	check( 'asset exists on disk: ' . $asset, file_exists( $plugin_dir . $asset ) );
}

/* --- Footer link --------------------------------------------------------- */

ob_start();
scc_footer_link();
$footer = ob_get_clean();
check( 'footer prints a reopen control by default', false !== strpos( $footer, 'class="scc-open"' ) && false !== strpos( $footer, 'Cookie settings' ) );

$GLOBALS['scc_test']['option'] = array( 'footer_link' => 0 );
ob_start();
scc_footer_link();
check( 'footer link can be turned off', '' === ob_get_clean() );

$GLOBALS['scc_test']['option'] = array( 'footer_label' => 'Cookies & "choices"' );
ob_start();
scc_footer_link();
check( 'footer label is escaped', false === strpos( ob_get_clean(), '& "choices"' ) );
$GLOBALS['scc_test']['option'] = false;

/* --- Settings form ------------------------------------------------------ */

$_POST = array(
	'scc_nonce'     => 'good',
	'message'       => 'Hello <script>alert(1)</script>"',
	'accept_label'  => 'Yes',
	'decline_label' => 'No',
	'policy_url'    => 'javascript:alert(1)',
	'policy_label'  => 'Policy',
	'position'      => 'top-left; drop',
	'days'          => '9999',
	'consent_mode'  => '1',
);

ob_start();
scc_settings_page();
$html = ob_get_clean();
$saved = $GLOBALS['scc_test']['option'];

check( 'script tag stripped from the message', false === strpos( $saved['message'], '<script' ) );
check( 'quote in the message is escaped in the form', false === strpos( $html, 'alert(1)"</textarea>' ) );
check( 'javascript: URL rejected', '' === $saved['policy_url'] );
check( 'unknown position falls back to a corner', 'bottom-right' === $saved['position'] );
check( 'day count clamped to 365', 365 === $saved['days'] );
check( 'footer link saved from the form', 0 === $saved['footer_link'] );

/* A POST with no valid nonce must not save. */
$GLOBALS['scc_test']['option'] = false;
$_POST['scc_nonce'] = 'bad';
$_POST['days']      = '1';
ob_start();
scc_settings_page();
ob_end_clean();
check( 'bad nonce saves nothing', false === $GLOBALS['scc_test']['option'] );

echo PHP_EOL . ( $fail ? "$fail check(s) FAILED" : 'all checks passed' ) . PHP_EOL;
exit( $fail ? 1 : 0 );
