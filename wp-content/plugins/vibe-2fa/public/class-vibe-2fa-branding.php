<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vibe_2FA_Branding {
	private $plugin;

	public function __construct( Vibe_2FA $plugin ) {
		$this->plugin = $plugin;
	}

	public function hooks() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_filter( 'login_headerurl', array( $this, 'header_url' ) );
		add_filter( 'login_headertext', array( $this, 'header_text' ) );
	}

	public function enqueue_styles() {
		$options = $this->plugin->get_options();
		$logo    = isset( $options['login_logo_url'] ) ? $options['login_logo_url'] : '';
		if ( '' === $logo ) {
			return;
		}

		$width  = isset( $options['login_logo_width'] ) ? (int) $options['login_logo_width'] : 84;
		$height = isset( $options['login_logo_height'] ) ? (int) $options['login_logo_height'] : 84;
		if ( $width < 40 ) {
			$width = 84;
		}
		if ( $height < 40 ) {
			$height = 84;
		}

		$css = sprintf(
			"body.login{background:linear-gradient(180deg,#2b2f33 0%%,#1f2226 100%%);} .login h1 a{background-image:url('%s');background-size:%dpx %dpx;width:%dpx;height:%dpx;} .vibe-2fa-inline{max-width:340px;margin:24px auto 0;padding:16px 20px;background:#fff;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,0.2);} .vibe-2fa-inline h2{margin:0 0 12px;font-size:16px;text-align:center;} .vibe-2fa-inline .vibe-2fa-error{margin:0 0 12px;padding:8px 10px;background:#fbeaea;border:1px solid #e9b4b4;border-radius:4px;color:#8a1f1f;font-size:13px;} .vibe-2fa-inline label{font-weight:600;} .vibe-2fa-inline .input{width:100%%;} .vibe-2fa-inline .submit{text-align:right;margin-top:10px;}",
			esc_url_raw( $logo ),
			$width,
			$height,
			$width,
			$height
		);
		wp_add_inline_style( 'login', $css );
	}

	public function header_url() {
		return home_url( '/' );
	}

	public function header_text() {
		return get_bloginfo( 'name' );
	}
}
