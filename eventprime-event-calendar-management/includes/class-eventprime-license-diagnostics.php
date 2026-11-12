<?php
/**
 * Privacy-safe licence connection diagnostics.
 *
 * Classifies the answer of a licence request without keeping the request, the response body,
 * cookies or the site address, keeps a few sanitised records and builds a copyable text report.
 * Nothing is sent anywhere automatically.
 *
 * @package EventPrime
 */

defined( 'ABSPATH' ) || exit;

/**
 * Licence diagnostics helper.
 */
class EventPrime_License_Diagnostics {

	const OPTION         = 'ep_license_diagnostics';
	const MAX_RECORDS    = 10;
	const RETENTION_DAYS = 30;
	const STORE_HOST     = 'theeventprime.com';
	const PROBE_KEY      = '00000000000000000000000000000000';
	const PROBE_SITE     = 'connection-test.invalid';
	const LOCK_TRANSIENT = 'ep_license_test_lock';

	/**
	 * Request types that may be recorded.
	 *
	 * @return string[]
	 */
	private static function request_types() {
		return array( 'extensions_lookup', 'activation_unified', 'activation_legacy', 'test_extensions_lookup', 'test_licence_api' );
	}

	/**
	 * Response headers that are safe to keep (lowercase names).
	 *
	 * @return string[]
	 */
	private static function header_allowlist() {
		return array( 'content-type', 'content-length', 'retry-after', 'cf-mitigated', 'sg-captcha', 'server' );
	}

	/**
	 * Read response headers as a plain lowercase-keyed array, whatever container WordPress returned.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return array
	 */
	private static function read_headers( $response ) {
		$raw = is_array( $response ) && isset( $response['headers'] ) ? $response['headers'] : array();
		if ( is_object( $raw ) && method_exists( $raw, 'getAll' ) ) {
			$raw = $raw->getAll();
		} elseif ( is_object( $raw ) && $raw instanceof Traversable ) {
			$raw = iterator_to_array( $raw );
		}
		$headers = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $name => $value ) {
				if ( is_array( $value ) ) {
					$value = implode( ',', array_filter( $value, 'is_scalar' ) );
				}
				if ( is_scalar( $value ) ) {
					$headers[ strtolower( (string) $name ) ] = (string) $value;
				}
			}
		}
		return $headers;
	}

	/**
	 * Keep only safe characters of a header value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function clean_header_value( $value ) {
		$value = sanitize_text_field( $value );
		$value = preg_replace( '/[^A-Za-z0-9 ;=\/.,_+-]/', '', $value );
		return substr( (string) $value, 0, 100 );
	}

	/**
	 * Replace sensitive fragments in free text.
	 *
	 * @param string   $text           Text to clean.
	 * @param string[] $known_secrets  Extra strings to remove.
	 * @return string
	 */
	public static function redact_text( $text, $known_secrets = array() ) {
		$text = (string) $text;
		foreach ( (array) $known_secrets as $secret ) {
			if ( is_string( $secret ) && '' !== $secret ) {
				$text = str_ireplace( $secret, '[redacted]', $text );
			}
		}
		$text = preg_replace( '#\b[a-z][a-z0-9+.-]*://[^\s\'"<>)]+#i', '[url]', $text );
		$text = preg_replace( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $text );
		$text = preg_replace( '/\b\d{1,3}(?:\.\d{1,3}){3}\b/', '[ip]', $text );
		$text = preg_replace( '/(?<![A-Za-z0-9:])(?:[A-Fa-f0-9]{0,4}:){2,7}[A-Fa-f0-9]{0,4}(?![A-Za-z0-9:])/', '[ip]', $text );
		$text = preg_replace( '/[A-Za-z0-9_-]{24,}/', '[redacted]', $text );
		$text = sanitize_text_field( $text );
		return substr( $text, 0, 200 );
	}

	/**
	 * Map a cURL error number to a category.
	 *
	 * @param int $errno cURL error number.
	 * @return string
	 */
	private static function curl_category( $errno ) {
		if ( 28 === $errno ) {
			return 'timeout';
		}
		if ( 6 === $errno ) {
			return 'dns';
		}
		if ( 7 === $errno ) {
			return 'connect';
		}
		if ( in_array( $errno, array( 35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91 ), true ) ) {
			return 'tls';
		}
		return 'other';
	}

	/**
	 * Describe a licence response as a sanitised record. Pure: no I/O.
	 *
	 * @param string         $request_type Request type.
	 * @param array|WP_Error $response     Result of wp_remote_post().
	 * @param bool           $tls_verify   Whether TLS verification was on for the request.
	 * @return array
	 */
	public static function describe_response( $request_type, $response, $tls_verify = true ) {
		$record = array(
			'time_utc'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'request_type'   => in_array( $request_type, self::request_types(), true ) ? $request_type : 'unknown',
			'outcome'        => '',
			'http_status'    => null,
			'content_type'   => '',
			'body_bytes'     => 0,
			'error_category' => 'none',
			'curl_errno'     => null,
			'error_message'  => '',
			'headers'        => array(),
			'redirect_host'  => '',
			'tls_verify'     => (bool) $tls_verify,
		);

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$secrets   = array_filter( array( home_url(), site_url(), is_string( $site_host ) ? $site_host : '' ) );

		if ( is_wp_error( $response ) ) {
			$message                 = $response->get_error_message();
			$code                    = (string) $response->get_error_code();
			$record['outcome']       = 'transport_error';
			$record['error_message'] = self::redact_text( $message, $secrets );
			if ( preg_match( '/cURL error (\d+)/i', $message, $matches ) ) {
				$record['curl_errno']     = (int) $matches[1];
				$record['error_category'] = self::curl_category( (int) $matches[1] );
			} elseif ( 'http_request_not_executed' === $code || false !== stripos( $message, 'User has blocked requests' ) ) {
				$record['error_category'] = 'blocked_by_site';
			} elseif ( 'http_request_failed' === $code && false !== stripos( $message, 'Too many redirects' ) ) {
				$record['error_category'] = 'too_many_redirects';
			} else {
				$record['error_category'] = 'other';
			}
			return $record;
		}

		$status = is_array( $response ) ? wp_remote_retrieve_response_code( $response ) : '';
		$body   = is_array( $response ) ? (string) wp_remote_retrieve_body( $response ) : '';
		$status = is_numeric( $status ) ? (int) $status : null;

		$headers      = self::read_headers( $response );
		$content_type = '';
		if ( isset( $headers['content-type'] ) ) {
			$parts        = explode( ';', $headers['content-type'] );
			$content_type = strtolower( preg_replace( '/[^A-Za-z0-9.+\/-]/', '', trim( $parts[0] ) ) );
			if ( isset( $parts[1] ) && preg_match( '/charset\s*=\s*([A-Za-z0-9_-]{1,30})/i', $parts[1], $charset ) ) {
				$content_type .= '; charset=' . strtolower( $charset[1] );
			}
			$content_type = substr( $content_type, 0, 80 );
		}

		foreach ( self::header_allowlist() as $name ) {
			if ( isset( $headers[ $name ] ) ) {
				$record['headers'][ $name ] = self::clean_header_value( $headers[ $name ] );
			}
		}

		$record['http_status']  = $status;
		$record['content_type'] = $content_type;
		$record['body_bytes']   = strlen( $body );

		$trimmed = trim( $body );
		if ( null !== $status && $status >= 300 && $status < 400 ) {
			$record['outcome'] = 'redirect';
			if ( ! empty( $headers['location'] ) ) {
				$host = wp_parse_url( $headers['location'], PHP_URL_HOST );
				if ( is_string( $host ) ) {
					$record['redirect_host'] = substr( preg_replace( '/[^A-Za-z0-9.-]/', '', $host ), 0, 100 );
				}
			}
		} elseif ( '' === $trimmed ) {
			$record['outcome'] = 'empty_body';
		} elseif ( 0 === strpos( $trimmed, '<' ) || false !== stripos( $content_type, 'html' ) ) {
			$challenge         = in_array( $status, array( 202, 403, 429, 503 ), true ) || isset( $headers['cf-mitigated'] ) || isset( $headers['sg-captcha'] );
			$record['outcome'] = $challenge ? 'html_challenge' : 'html_response';
		} else {
			$decoded = json_decode( $trimmed );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$record['outcome'] = 'invalid_json';
			} elseif ( ! is_object( $decoded ) ) {
				$record['outcome'] = 'json_not_object';
			} elseif ( 200 !== $status ) {
				$record['outcome'] = 'http_error';
			} else {
				$record['outcome'] = 'ok';
			}
		}

		return $record;
	}

	/**
	 * Capture an unexpected result of a real licence request. Never throws and never alters the caller.
	 *
	 * @param string         $request_type Request type.
	 * @param array|WP_Error $response     Result of wp_remote_post().
	 * @param bool           $tls_verify   Whether TLS verification was on.
	 * @return array|null The stored record, or null.
	 */
	public static function capture( $request_type, $response, $tls_verify = true ) {
		try {
			$record = self::describe_response( $request_type, $response, $tls_verify );
			if ( 'ok' === $record['outcome'] ) {
				return null;
			}
			self::store_record( $record );
			return $record;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Stored records, newest first, without expired ones.
	 *
	 * @return array
	 */
	public static function get_records() {
		$records = get_option( self::OPTION, array() );
		if ( ! is_array( $records ) ) {
			return array();
		}
		$cutoff = time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS );
		$kept   = array();
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || empty( $record['time_utc'] ) ) {
				continue;
			}
			$when = strtotime( $record['time_utc'] );
			if ( $when && $when >= $cutoff ) {
				$kept[] = $record;
			}
		}
		if ( count( $kept ) !== count( $records ) ) {
			update_option( self::OPTION, $kept, false );
		}
		return $kept;
	}

	/**
	 * Store a record at the front, keeping at most MAX_RECORDS.
	 *
	 * @param array $record Record.
	 */
	public static function store_record( $record ) {
		$records = self::get_records();
		array_unshift( $records, $record );
		$records = array_slice( $records, 0, self::MAX_RECORDS );
		update_option( self::OPTION, $records, false );
	}

	/**
	 * Remove all records.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * Run the non-activating connection test: two read-only requests with a placeholder key.
	 *
	 * @return array|WP_Error The two records, or an error when a test ran in the last 10 seconds.
	 */
	public static function run_connection_test() {
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return new WP_Error( 'ep_license_test_busy', __( 'A connection test is already running. Please wait a few seconds and try again.', 'eventprime-event-calendar-management' ) );
		}
		set_transient( self::LOCK_TRANSIENT, 1, 10 );

		$common  = array(
			'timeout'     => 20,
			'sslverify'   => true,
			'redirection' => 0,
		);
		$records = array();

		$lookup    = wp_remote_post(
			'https://' . self::STORE_HOST . '/wp-json/custom/v1/extensions',
			array_merge(
				$common,
				array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'license_key' => self::PROBE_KEY,
							'site_url'    => self::PROBE_SITE,
						)
					),
				)
			)
		);
		$records[] = self::describe_response( 'test_extensions_lookup', $lookup, true );

		$check     = wp_remote_post(
			'https://' . self::STORE_HOST . '/',
			array_merge(
				$common,
				array(
					'body' => array(
						'edd_action' => 'check_license',
						'license'    => self::PROBE_KEY,
						'item_id'    => 867,
						'url'        => self::PROBE_SITE,
					),
				)
			)
		);
		$records[] = self::describe_response( 'test_licence_api', $check, true );

		foreach ( array_reverse( $records ) as $record ) {
			self::store_record( $record );
		}
		return $records;
	}

	/**
	 * Whether the store host is reachable under the site's WP_HTTP_BLOCK_EXTERNAL setting.
	 *
	 * @return string yes, no or n/a.
	 */
	private static function store_host_allowed() {
		if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
			return 'n/a';
		}
		$hosts = defined( 'WP_ACCESSIBLE_HOSTS' ) ? array_map( 'trim', explode( ',', (string) WP_ACCESSIBLE_HOSTS ) ) : array();
		foreach ( $hosts as $host ) {
			if ( '' === $host ) {
				continue;
			}
			if ( strtolower( $host ) === self::STORE_HOST ) {
				return 'yes';
			}
			if ( 0 === strpos( $host, '*.' ) && substr( self::STORE_HOST, - strlen( substr( $host, 1 ) ) ) === substr( $host, 1 ) ) {
				return 'yes';
			}
		}
		return 'no';
	}

	/**
	 * Plain-text, ASCII-only report. Contains no licence key, site address or bodies.
	 *
	 * @param array $records Records from get_records().
	 * @return string
	 */
	public static function format_report( $records ) {
		global $wp_version;
		$lines   = array();
		$lines[] = 'EventPrime licence connection report';
		$lines[] = 'Generated (UTC): ' . gmdate( 'Y-m-d\TH:i:s\Z' );
		$lines[] = 'EventPrime version: ' . ( defined( 'EVENTPRIME_VERSION' ) ? EVENTPRIME_VERSION : 'unknown' );
		$lines[] = 'WordPress version: ' . ( isset( $wp_version ) ? $wp_version : 'unknown' );
		$lines[] = 'PHP version: ' . PHP_VERSION;
		if ( function_exists( 'curl_version' ) ) {
			$curl    = curl_version();
			$lines[] = 'cURL version: ' . ( isset( $curl['version'] ) ? $curl['version'] : 'unknown' ) . ' / ' . ( isset( $curl['ssl_version'] ) ? $curl['ssl_version'] : 'unknown' );
		}
		$lines[] = 'WP_HTTP_BLOCK_EXTERNAL: ' . ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ? 'on' : 'off' );
		$lines[] = self::STORE_HOST . ' allowed by WP_ACCESSIBLE_HOSTS: ' . self::store_host_allowed();
		$lines[] = 'Destination host: ' . self::STORE_HOST;
		$lines[] = '';
		$lines[] = 'Not included: licence keys, site address, request or response bodies, cookies.';
		$lines[] = 'This report is not sent anywhere. The connection test does not activate or change a licence. An HTTP 200 answer only shows the server replied; it does not prove a licence is active.';
		$lines[] = '';

		if ( empty( $records ) ) {
			$lines[] = 'No records yet. Run the connection test, or retry a licence action that failed.';
		} else {
			foreach ( array_values( $records ) as $index => $record ) {
				$lines[] = 'Record ' . ( $index + 1 );
				foreach ( $record as $field => $value ) {
					if ( 'headers' === $field ) {
						$parts = array();
						foreach ( (array) $value as $name => $header_value ) {
							$parts[] = $name . ': ' . $header_value;
						}
						$value = $parts ? implode( ' | ', $parts ) : '-';
					} elseif ( is_bool( $value ) ) {
						$value = $value ? 'yes' : 'no';
					} elseif ( null === $value || '' === $value ) {
						$value = '-';
					}
					$lines[] = '  ' . $field . ': ' . ( is_scalar( $value ) ? $value : '-' );
				}
				$lines[] = '';
			}
		}

		return preg_replace( '/[^\x09\x0A\x20-\x7E]/', '', implode( "\n", $lines ) );
	}
}
