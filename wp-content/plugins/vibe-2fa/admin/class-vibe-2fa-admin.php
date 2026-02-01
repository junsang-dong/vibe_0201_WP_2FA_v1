<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vibe_2FA_Admin {
	private $plugin;

	public function __construct( Vibe_2FA $plugin ) {
		$this->plugin = $plugin;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_menu() {
		add_options_page(
			__( 'AI NEXT 2FA 설정', 'vibe-2fa' ),
			__( 'AI NEXT 2FA', 'vibe-2fa' ),
			'manage_options',
			'vibe-2fa',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'vibe_2fa', 'vibe_2fa_options', array( $this, 'sanitize_options' ) );

		add_settings_section(
			'vibe_2fa_main',
			__( '보안 설정', 'vibe-2fa' ),
			'__return_false',
			'vibe_2fa'
		);

		add_settings_field(
			'force_role',
			__( '2FA 강제 적용', 'vibe-2fa' ),
			array( $this, 'render_force_role' ),
			'vibe_2fa',
			'vibe_2fa_main'
		);

		add_settings_field(
			'enable_captcha',
			__( '로그인 캡차', 'vibe-2fa' ),
			array( $this, 'render_checkbox' ),
			'vibe_2fa',
			'vibe_2fa_main',
			array(
				'key'   => 'enable_captcha',
				'label' => __( 'reCAPTCHA 사용(키 설정 필요)', 'vibe-2fa' ),
			)
		);

		add_settings_field(
			'recaptcha_site',
			__( 'reCAPTCHA Site Key', 'vibe-2fa' ),
			array( $this, 'render_text' ),
			'vibe_2fa',
			'vibe_2fa_main',
			array(
				'key'         => 'recaptcha_site',
				'placeholder' => __( '사이트 키', 'vibe-2fa' ),
			)
		);

		add_settings_field(
			'recaptcha_secret',
			__( 'reCAPTCHA Secret Key', 'vibe-2fa' ),
			array( $this, 'render_text' ),
			'vibe_2fa',
			'vibe_2fa_main',
			array(
				'key'         => 'recaptcha_secret',
				'placeholder' => __( '시크릿 키', 'vibe-2fa' ),
			)
		);

		add_settings_field(
			'block_xmlrpc',
			__( 'XML-RPC 차단', 'vibe-2fa' ),
			array( $this, 'render_checkbox' ),
			'vibe_2fa',
			'vibe_2fa_main',
			array(
				'key'   => 'block_xmlrpc',
				'label' => __( 'XML-RPC 로그인 비활성화', 'vibe-2fa' ),
			)
		);

		add_settings_field(
			'enable_woocommerce',
			__( 'WooCommerce 통합', 'vibe-2fa' ),
			array( $this, 'render_checkbox' ),
			'vibe_2fa',
			'vibe_2fa_main',
			array(
				'key'   => 'enable_woocommerce',
				'label' => __( 'WooCommerce 계정 페이지 통합(스켈레톤)', 'vibe-2fa' ),
			)
		);

		add_settings_field(
			'enable_pwned',
			__( '유출 암호 차단', 'vibe-2fa' ),
			array( $this, 'render_checkbox' ),
			'vibe_2fa',
			'vibe_2fa_main',
			array(
				'key'   => 'enable_pwned',
				'label' => __( '유출된 비밀번호 로그인 차단(스켈레톤)', 'vibe-2fa' ),
			)
		);
	}

	public function sanitize_options( $input ) {
		$options = $this->plugin->get_options();
		$options['force_role']         = isset( $input['force_role'] ) ? sanitize_text_field( $input['force_role'] ) : 'none';
		$options['enable_captcha']     = ! empty( $input['enable_captcha'] );
		$options['recaptcha_site']     = isset( $input['recaptcha_site'] ) ? sanitize_text_field( $input['recaptcha_site'] ) : '';
		$options['recaptcha_secret']   = isset( $input['recaptcha_secret'] ) ? sanitize_text_field( $input['recaptcha_secret'] ) : '';
		$options['block_xmlrpc']       = ! empty( $input['block_xmlrpc'] );
		$options['enable_woocommerce'] = ! empty( $input['enable_woocommerce'] );
		$options['enable_pwned']       = ! empty( $input['enable_pwned'] );
		return $options;
	}

	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI NEXT 2FA 설정', 'vibe-2fa' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'vibe_2fa' );
				do_settings_sections( 'vibe_2fa' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_force_role() {
		$options = $this->plugin->get_options();
		$value   = isset( $options['force_role'] ) ? $options['force_role'] : 'none';
		?>
		<select name="vibe_2fa_options[force_role]">
			<option value="none" <?php selected( $value, 'none' ); ?>><?php esc_html_e( '강제 없음', 'vibe-2fa' ); ?></option>
			<option value="administrator" <?php selected( $value, 'administrator' ); ?>><?php esc_html_e( '관리자만', 'vibe-2fa' ); ?></option>
			<option value="all" <?php selected( $value, 'all' ); ?>><?php esc_html_e( '모든 사용자', 'vibe-2fa' ); ?></option>
		</select>
		<?php
	}

	public function render_checkbox( $args ) {
		$options = $this->plugin->get_options();
		$key     = $args['key'];
		$label   = $args['label'];
		$checked = ! empty( $options[ $key ] );
		?>
		<label>
			<input type="checkbox" name="vibe_2fa_options[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $checked, true ); ?> />
			<?php echo esc_html( $label ); ?>
		</label>
		<?php
	}

	public function render_text( $args ) {
		$options     = $this->plugin->get_options();
		$key         = $args['key'];
		$placeholder = $args['placeholder'];
		$value       = isset( $options[ $key ] ) ? $options[ $key ] : '';
		?>
		<input type="text" class="regular-text" name="vibe_2fa_options[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
		<?php
	}
}
