<?php
/**
 * Plugin Name: AI NEXT 2FA
 * Description: TOTP 기반 2단계 인증과 reCAPTCHA 로그인을 제공하는 보안 플러그인.
 * Version: 0.1.0
 * Author: Junsang Dong, AINext
 * Author URI: mailto:naebon@naver.com
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: vibe-2fa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VIBE_2FA_VERSION', '0.1.0' );
define( 'VIBE_2FA_PATH', plugin_dir_path( __FILE__ ) );
define( 'VIBE_2FA_URL', plugin_dir_url( __FILE__ ) );

require_once VIBE_2FA_PATH . 'includes/class-vibe-2fa.php';

function vibe_2fa_activate() {
	$defaults = array(
		'force_role'        => 'none',
		'enable_captcha'    => true,
		'recaptcha_site'    => '',
		'recaptcha_secret'  => '',
		'block_xmlrpc'      => false,
		'enable_woocommerce'=> false,
		'enable_pwned'      => false,
	);
	if ( ! get_option( 'vibe_2fa_options' ) ) {
		add_option( 'vibe_2fa_options', $defaults );
	}
}

function vibe_2fa_deactivate() {
	// 스켈레톤 단계에서는 데이터 보존.
}

register_activation_hook( __FILE__, 'vibe_2fa_activate' );
register_deactivation_hook( __FILE__, 'vibe_2fa_deactivate' );

function vibe_2fa() {
	return Vibe_2FA::instance();
}

vibe_2fa()->run();
