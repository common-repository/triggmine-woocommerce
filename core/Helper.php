<?php

final class TriggMine_Helper {
	/**
	 * Encodes given value from or to UTF8.
	 *
	 * @param array $data Data to encode.
	 * @param string $charset [optional] Source/destination charset.
	 * @param bool $reverse [optional] True means encoding from UTF8 to the given charset.
	 */
	public static function encodeArray( &$data, $charset = 'windows-1251', $reverse = false ) {
		if ( $charset == 'UTF8' ) {
			return;
		}

		foreach ( $data as $key => &$value ) {
			if ( is_numeric( $value ) ) {
				continue;
			} else if ( is_array( $value ) ) {
				$value = self::encodeArray( $value, $charset, $reverse );
			} else if ( is_string( $value ) ) {
				if ( $reverse ) {
					$value = mb_convert_encoding( $value, $charset, 'UTF8' );
				} else {
					$value = mb_convert_encoding( $value, 'UTF8', $charset );
				}
			}
		}
	}

	/**
	 * @return bool
	 */
	public static function isUrlFopenEnabled() {
		return ini_get( 'allow_url_fopen' ) && function_exists( 'file_get_contents' );
	}

	/**
	 * @return bool
	 */
	public static function isCurlEnabled() {
		return function_exists( 'curl_version' );
	}

	public static function isRobotRequest() {
		// robots USER_AGENT in lower-case
		$robots = array(
			'Any ad bot1'      => 'adbot',
			'Any ad bot2'      => 'adsbot',
			'Alexa'            => 'ia_archiver',
			'Altavista robot1' => 'scooter',
			'Altavista robot2' => 'altavista',
			'Altavista robot3' => 'mercator',
			'Baidu'            => 'baidu',
			'Bing1'            => 'msnbot',
			'Bing2'            => 'bingbot',
			'Exalead'          => 'exabot',
			'Facebook'         => 'facebookexternalhit',
			'Gigabot'          => 'gigabot',
			'Google'           => 'googlebot',
			'Rambler'          => 'rambler',
			'Soso'             => 'sosospider',
			'Yahoo'            => 'yahoo'
		);

		$isRobot   = false;
		$userAgent = strtolower( $_SERVER['HTTP_USER_AGENT'] );

		foreach ( $robots as $robot ) {
			// Searching robot in the user agent, because user agent can be a longer string
			if ( strpos( $userAgent, $robot ) === 0 ) {
				$isRobot = true;
				break;
			}
		}

		return $isRobot;
	}
}