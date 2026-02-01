<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vibe_2FA_TOTP {
	private $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	public function generate_secret( $length = 20 ) {
		$bytes  = random_bytes( $length );
		$secret = $this->base32_encode( $bytes );
		return substr( $secret, 0, $length );
	}

	public function get_otp( $secret, $timestamp = null, $digits = 6, $period = 30 ) {
		if ( null === $timestamp ) {
			$timestamp = time();
		}
		$counter = floor( $timestamp / $period );
		$bin     = $this->int_to_bytes( $counter );
		$key     = $this->base32_decode( $secret );
		$hash    = hash_hmac( 'sha1', $bin, $key, true );
		$offset  = ord( substr( $hash, -1 ) ) & 0x0F;
		$code    = substr( $hash, $offset, 4 );
		$value   = unpack( 'N', $code );
		$value   = $value[1] & 0x7FFFFFFF;
		$mod     = pow( 10, $digits );
		return str_pad( (string) ( $value % $mod ), $digits, '0', STR_PAD_LEFT );
	}

	public function verify( $secret, $code, $window = 1 ) {
		$code = preg_replace( '/\s+/', '', $code );
		if ( '' === $code ) {
			return false;
		}
		$time = time();
		for ( $i = -1 * $window; $i <= $window; $i++ ) {
			$calc = $this->get_otp( $secret, $time + ( $i * 30 ) );
			if ( hash_equals( $calc, $code ) ) {
				return true;
			}
		}
		return false;
	}

	public function get_otpauth_uri( $user_login, $secret, $issuer = 'AI NEXT 2FA' ) {
		$label = rawurlencode( $issuer . ':' . $user_login );
		$params = array(
			'secret' => $secret,
			'issuer' => $issuer,
		);
		return 'otpauth://totp/' . $label . '?' . http_build_query( $params );
	}

	private function base32_encode( $data ) {
		$binary = '';
		foreach ( str_split( $data ) as $char ) {
			$binary .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
		}
		$five_bit = str_split( $binary, 5 );
		$output   = '';
		foreach ( $five_bit as $chunk ) {
			if ( 5 !== strlen( $chunk ) ) {
				$chunk = str_pad( $chunk, 5, '0', STR_PAD_RIGHT );
			}
			$output .= $this->alphabet[ bindec( $chunk ) ];
		}
		return $output;
	}

	private function base32_decode( $data ) {
		$data   = strtoupper( $data );
		$binary = '';
		foreach ( str_split( $data ) as $char ) {
			$pos = strpos( $this->alphabet, $char );
			if ( false === $pos ) {
				continue;
			}
			$binary .= str_pad( decbin( $pos ), 5, '0', STR_PAD_LEFT );
		}
		$eight_bit = str_split( $binary, 8 );
		$output    = '';
		foreach ( $eight_bit as $chunk ) {
			if ( 8 !== strlen( $chunk ) ) {
				continue;
			}
			$output .= chr( bindec( $chunk ) );
		}
		return $output;
	}

	private function int_to_bytes( $int ) {
		$high = 0;
		$low  = $int;
		if ( $int > PHP_INT_MAX ) {
			$high = (int) ( $int / pow( 2, 32 ) );
			$low  = $int - ( $high * pow( 2, 32 ) );
		}
		return pack( 'N2', $high, $low );
	}
}
