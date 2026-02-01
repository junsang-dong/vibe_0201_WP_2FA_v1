<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vibe_2FA_Captcha {
	private $plugin;

	public function __construct( Vibe_2FA $plugin ) {
		$this->plugin = $plugin;
	}

	public function hooks() {
		add_action( 'login_form', array( $this, 'render_login_field' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function render_login_field() {
		$options = $this->plugin->get_options();
		if ( empty( $options['enable_captcha'] ) ) {
			return;
		}
		$site_key   = isset( $options['recaptcha_site'] ) ? $options['recaptcha_site'] : '';
		$secret_key = isset( $options['recaptcha_secret'] ) ? $options['recaptcha_secret'] : '';
		$use_recaptcha = ( '' !== $site_key && '' !== $secret_key );
		?>
		<?php if ( $use_recaptcha ) : ?>
			<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
		<?php else : ?>
			<p>
				<label for="vibe_2fa_captcha"><?php esc_html_e( '캡차 확인', 'vibe-2fa' ); ?></label>
				<input type="text" name="vibe_2fa_captcha" id="vibe_2fa_captcha" class="input" value="" size="20" />
				<span class="description"><?php esc_html_e( 'reCAPTCHA 키가 설정되기 전에는 임의의 텍스트로 통과됩니다.', 'vibe-2fa' ); ?></span>
			</p>
		<?php endif; ?>
		<?php
	}

	public function enqueue_scripts() {
		$options = $this->plugin->get_options();
		if ( empty( $options['enable_captcha'] ) ) {
			return;
		}
		$site_key   = isset( $options['recaptcha_site'] ) ? $options['recaptcha_site'] : '';
		$secret_key = isset( $options['recaptcha_secret'] ) ? $options['recaptcha_secret'] : '';
		if ( '' === $site_key || '' === $secret_key ) {
			return;
		}
		wp_enqueue_script( 'vibe-2fa-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
	}

	public function validate_request() {
		$options = $this->plugin->get_options();
		if ( empty( $options['enable_captcha'] ) ) {
			return true;
		}

		if ( ! isset( $_POST['log'] ) || ! isset( $_POST['pwd'] ) ) {
			return true;
		}

		if ( isset( $_POST['action'] ) && 'vibe_2fa' === $_POST['action'] ) {
			return true;
		}

		$site_key   = isset( $options['recaptcha_site'] ) ? $options['recaptcha_site'] : '';
		$secret_key = isset( $options['recaptcha_secret'] ) ? $options['recaptcha_secret'] : '';
		$use_recaptcha = ( '' !== $site_key && '' !== $secret_key );

		if ( $use_recaptcha ) {
			$response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
			if ( '' === $response ) {
				return false;
			}

			$verify = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'timeout' => 8,
					'body'    => array(
						'secret'   => $secret_key,
						'response' => $response,
						'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					),
				)
			);

			if ( is_wp_error( $verify ) ) {
				return false;
			}

			$body = wp_remote_retrieve_body( $verify );
			$data = json_decode( $body, true );
			$valid = is_array( $data ) && ! empty( $data['success'] );

			return (bool) apply_filters( 'vibe_2fa_captcha_valid', $valid, $response, $data );
		}

		$value = isset( $_POST['vibe_2fa_captcha'] ) ? sanitize_text_field( wp_unslash( $_POST['vibe_2fa_captcha'] ) ) : '';
		$valid = '' !== $value;

		return (bool) apply_filters( 'vibe_2fa_captcha_valid', $valid, $value );
	}
}
