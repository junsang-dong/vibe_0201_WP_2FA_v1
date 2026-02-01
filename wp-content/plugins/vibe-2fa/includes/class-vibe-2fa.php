<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vibe_2FA {
	private static $instance = null;
	private $options = array();
	private $totp;
	private $captcha;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function run() {
		$this->load_options();
		$this->load_dependencies();
		$this->register_hooks();
	}

	public function get_options() {
		return $this->options;
	}

	public function get_totp() {
		return $this->totp;
	}

	private function load_options() {
		$defaults = array(
			'force_role'        => 'none',
			'enable_captcha'    => true,
			'recaptcha_site'    => '',
			'recaptcha_secret'  => '',
			'login_logo_url'    => '',
			'login_logo_width'  => 84,
			'login_logo_height' => 84,
			'block_xmlrpc'      => false,
			'enable_woocommerce'=> false,
			'enable_pwned'      => false,
		);
		$stored = get_option( 'vibe_2fa_options', array() );
		$this->options = wp_parse_args( $stored, $defaults );
	}

	private function load_dependencies() {
		require_once VIBE_2FA_PATH . 'includes/class-vibe-2fa-totp.php';
		require_once VIBE_2FA_PATH . 'admin/class-vibe-2fa-admin.php';
		require_once VIBE_2FA_PATH . 'public/class-vibe-2fa-profile.php';
		require_once VIBE_2FA_PATH . 'public/class-vibe-2fa-captcha.php';
		require_once VIBE_2FA_PATH . 'public/class-vibe-2fa-branding.php';

		$this->totp    = new Vibe_2FA_TOTP();
		$this->captcha = new Vibe_2FA_Captcha( $this );
	}

	private function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		if ( is_admin() ) {
			$admin = new Vibe_2FA_Admin( $this );
			$admin->hooks();
		}

		$profile = new Vibe_2FA_Profile( $this );
		$profile->hooks();

		$this->captcha->hooks();
		$branding = new Vibe_2FA_Branding( $this );
		$branding->hooks();

		add_filter( 'authenticate', array( $this, 'handle_authenticate' ), 30, 3 );
		add_action( 'login_init', array( $this, 'handle_2fa_post' ) );
		add_action( 'login_footer', array( $this, 'render_2fa_inline' ) );
		add_filter( 'xmlrpc_enabled', array( $this, 'maybe_block_xmlrpc' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'vibe-2fa', false, dirname( plugin_basename( VIBE_2FA_PATH . 'vibe-2fa.php' ) ) . '/languages' );
	}

	public function maybe_block_xmlrpc( $enabled ) {
		if ( ! empty( $this->options['block_xmlrpc'] ) ) {
			return false;
		}
		return $enabled;
	}

	private function is_xmlrpc_request() {
		return defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
	}

	public function handle_authenticate( $user, $username, $password ) {
		if ( $this->is_xmlrpc_request() && ! empty( $this->options['block_xmlrpc'] ) ) {
			return new WP_Error( 'vibe_2fa_xmlrpc_blocked', __( 'XML-RPC 로그인이 차단되었습니다.', 'vibe-2fa' ) );
		}

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( ! $this->captcha->validate_request() ) {
			return new WP_Error( 'vibe_2fa_captcha', __( '캡차 확인이 필요합니다.', 'vibe-2fa' ) );
		}

		if ( $this->options['enable_pwned'] ) {
			$is_pwned = apply_filters( 'vibe_2fa_is_password_pwned', false, $username, $password );
			if ( $is_pwned ) {
				return new WP_Error( 'vibe_2fa_pwned', __( '유출된 것으로 알려진 비밀번호입니다.', 'vibe-2fa' ) );
			}
		}

		if ( ! ( $user instanceof WP_User ) ) {
			return $user;
		}

		if ( $this->is_2fa_forced_for_user( $user ) ) {
			$enabled   = (bool) get_user_meta( $user->ID, 'vibe_2fa_enabled', true );
			$confirmed = (bool) get_user_meta( $user->ID, 'vibe_2fa_confirmed', true );
			$secret    = get_user_meta( $user->ID, 'vibe_2fa_secret', true );
			if ( ! $enabled || ! $confirmed || empty( $secret ) ) {
				return new WP_Error( 'vibe_2fa_required', __( '2단계 인증 설정이 필요합니다. 프로필에서 활성화하세요.', 'vibe-2fa' ) );
			}
		}

		if ( ! $this->is_2fa_required_for_user( $user ) ) {
			return $user;
		}

		$token = wp_generate_password( 20, false, false );
		$key   = $this->get_login_token_key( $token );
		$data  = array(
			'user_id'     => $user->ID,
			'redirect_to' => isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '',
		);
		set_transient( $key, $data, 10 * MINUTE_IN_SECONDS );

		$redirect = add_query_arg(
			array(
				'vibe_2fa_token'  => $token,
			),
			wp_login_url()
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	private function get_login_token_key( $token ) {
		return 'vibe_2fa_login_' . $token;
	}

	private function get_login_token_data( $token ) {
		return get_transient( $this->get_login_token_key( $token ) );
	}

	private function delete_login_token( $token ) {
		delete_transient( $this->get_login_token_key( $token ) );
	}

	private function get_error_key( $token ) {
		return 'vibe_2fa_error_' . $token;
	}

	private function set_error_message( $token, $message ) {
		set_transient( $this->get_error_key( $token ), $message, 5 * MINUTE_IN_SECONDS );
	}

	private function pop_error_message( $token ) {
		$message = get_transient( $this->get_error_key( $token ) );
		if ( $message ) {
			delete_transient( $this->get_error_key( $token ) );
		}
		return $message;
	}

	private function is_2fa_required_for_user( WP_User $user ) {
		$enabled   = (bool) get_user_meta( $user->ID, 'vibe_2fa_enabled', true );
		$confirmed = (bool) get_user_meta( $user->ID, 'vibe_2fa_confirmed', true );
		if ( ! $enabled || ! $confirmed ) {
			return false;
		}

		return true;
	}

	private function is_2fa_forced_for_user( WP_User $user ) {
		$force = $this->options['force_role'];
		if ( 'administrator' === $force && in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}
		if ( 'all' === $force ) {
			return true;
		}
		return false;
	}

	private function is_rate_limited( $user_id ) {
		$counter = (int) get_transient( 'vibe_2fa_fail_' . $user_id );
		return $counter >= 5;
	}

	private function record_failed_attempt( $user_id ) {
		$key     = 'vibe_2fa_fail_' . $user_id;
		$counter = (int) get_transient( $key );
		$counter++;
		set_transient( $key, $counter, 15 * MINUTE_IN_SECONDS );
	}

	public function handle_2fa_post() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( ! isset( $_POST['vibe_2fa_token'] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_POST['vibe_2fa_token'] ) );
		$code  = isset( $_POST['vibe_2fa_code'] ) ? sanitize_text_field( wp_unslash( $_POST['vibe_2fa_code'] ) ) : '';

		$data = $this->get_login_token_data( $token );
		if ( empty( $data['user_id'] ) ) {
			$this->set_error_message( $token, __( '인증 토큰이 만료되었습니다. 다시 로그인하세요.', 'vibe-2fa' ) );
			return;
		}

		$user_id = (int) $data['user_id'];
		if ( $this->is_rate_limited( $user_id ) ) {
			$this->set_error_message( $token, __( '시도 횟수가 초과되었습니다. 잠시 후 다시 시도하세요.', 'vibe-2fa' ) );
			return;
		}

		$secret = get_user_meta( $user_id, 'vibe_2fa_secret', true );
		if ( empty( $secret ) || ! $this->totp->verify( $secret, $code ) ) {
			$this->record_failed_attempt( $user_id );
			$this->set_error_message( $token, __( '인증 코드가 올바르지 않습니다.', 'vibe-2fa' ) );
			return;
		}

		$this->delete_login_token( $token );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'vibe_2fa_login_success', $user_id );

		$redirect = ! empty( $data['redirect_to'] ) ? $data['redirect_to'] : admin_url();
		wp_safe_redirect( $redirect );
		exit;
	}

	public function render_2fa_inline() {
		$token = isset( $_REQUEST['vibe_2fa_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['vibe_2fa_token'] ) ) : '';
		if ( '' === $token ) {
			return;
		}

		$data = $this->get_login_token_data( $token );
		if ( empty( $data['user_id'] ) ) {
			return;
		}

		$error = $this->pop_error_message( $token );
		?>
		<div class="vibe-2fa-inline">
			<h2><?php esc_html_e( '인증 코드', 'vibe-2fa' ); ?></h2>
			<?php if ( $error ) : ?>
				<div class="vibe-2fa-error"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>
			<form name="vibe-2fa-form" id="vibe-2fa-form" action="<?php echo esc_url( wp_login_url() ); ?>" method="post">
				<p>
					<label for="vibe_2fa_code"><?php esc_html_e( '인증 코드', 'vibe-2fa' ); ?></label>
					<input type="text" name="vibe_2fa_code" id="vibe_2fa_code" class="input" value="" size="20" autocomplete="one-time-code" />
				</p>
				<input type="hidden" name="vibe_2fa_token" value="<?php echo esc_attr( $token ); ?>" />
				<p class="submit">
					<input type="submit" class="button button-primary button-large" value="<?php esc_attr_e( '확인', 'vibe-2fa' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}
}
