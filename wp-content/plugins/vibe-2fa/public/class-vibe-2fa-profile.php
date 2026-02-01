<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vibe_2FA_Profile {
	private $plugin;

	public function __construct( Vibe_2FA $plugin ) {
		$this->plugin = $plugin;
	}

	public function hooks() {
		add_action( 'show_user_profile', array( $this, 'render_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );
	}

	public function render_profile_fields( $user ) {
		$enabled   = (bool) get_user_meta( $user->ID, 'vibe_2fa_enabled', true );
		$secret    = get_user_meta( $user->ID, 'vibe_2fa_secret', true );
		$confirmed = (bool) get_user_meta( $user->ID, 'vibe_2fa_confirmed', true );
		$issuer    = get_bloginfo( 'name' );
		$totp      = $this->plugin->get_totp();
		$otpauth   = $secret ? $totp->get_otpauth_uri( $user->user_login, $secret, $issuer ) : '';
		?>
		<h2><?php esc_html_e( 'AI NEXT 2FA', 'vibe-2fa' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="vibe_2fa_enabled"><?php esc_html_e( '2FA 사용', 'vibe-2fa' ); ?></label></th>
				<td>
					<input type="checkbox" name="vibe_2fa_enabled" id="vibe_2fa_enabled" value="1" <?php checked( $enabled, true ); ?> />
					<p class="description"><?php esc_html_e( 'Google Authenticator로 2단계 인증을 활성화합니다.', 'vibe-2fa' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'TOTP 시크릿', 'vibe-2fa' ); ?></th>
				<td>
					<?php if ( $secret ) : ?>
						<code><?php echo esc_html( $secret ); ?></code>
						<p class="description">
							<?php esc_html_e( '앱에 등록 후, 로그인 시 6자리 코드를 입력하세요.', 'vibe-2fa' ); ?>
						</p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( '저장 시 자동 생성됩니다.', 'vibe-2fa' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'QR 코드 URI', 'vibe-2fa' ); ?></th>
				<td>
					<?php if ( $otpauth ) : ?>
						<code><?php echo esc_html( $otpauth ); ?></code>
						<p class="description"><?php esc_html_e( 'Google Authenticator에서 수동 입력 시 사용하세요.', 'vibe-2fa' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( '시크릿 생성 후 표시됩니다.', 'vibe-2fa' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '재설정', 'vibe-2fa' ); ?></th>
				<td>
					<input type="checkbox" name="vibe_2fa_reset_secret" value="1" />
					<p class="description"><?php esc_html_e( '시크릿을 새로 생성합니다.', 'vibe-2fa' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '상태', 'vibe-2fa' ); ?></th>
				<td>
					<?php if ( $confirmed ) : ?>
						<span><?php esc_html_e( '활성화됨', 'vibe-2fa' ); ?></span>
					<?php else : ?>
						<span><?php esc_html_e( '비활성', 'vibe-2fa' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
		wp_nonce_field( 'vibe_2fa_profile', 'vibe_2fa_nonce' );
	}

	public function save_profile_fields( $user_id ) {
		if ( ! isset( $_POST['vibe_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vibe_2fa_nonce'] ) ), 'vibe_2fa_profile' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$enabled = ! empty( $_POST['vibe_2fa_enabled'] );
		update_user_meta( $user_id, 'vibe_2fa_enabled', $enabled );

		$secret = get_user_meta( $user_id, 'vibe_2fa_secret', true );
		$reset  = ! empty( $_POST['vibe_2fa_reset_secret'] );
		if ( $enabled && ( empty( $secret ) || $reset ) ) {
			$secret = $this->plugin->get_totp()->generate_secret();
			update_user_meta( $user_id, 'vibe_2fa_secret', $secret );
		}

		if ( $enabled ) {
			update_user_meta( $user_id, 'vibe_2fa_confirmed', true );
		} else {
			update_user_meta( $user_id, 'vibe_2fa_confirmed', false );
		}
	}
}
