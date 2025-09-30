<?php
/**
 * REST controller that proxies requests to the RAISE marketplace API.
 */

class Raise_Datasets_REST {
	private const REMOTE_ENDPOINT = 'https://api.portal.raise-science.eu/dataset/marketplace';
	private const DATASET_BASE_URL = 'https://portal.raise-science.eu/dataset-marketplace/';

	/**
	 * Registers REST API routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'raise/v1',
			'/datasets',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'get_datasets' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'search'   => [
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					],
					'page'     => [
						'sanitize_callback' => 'absint',
						'default'           => 1,
						'validate_callback' => [ self::class, 'validate_page' ],
					],
					'per_page' => [
						'sanitize_callback' => 'absint',
						'default'           => 10,
						'validate_callback' => [ self::class, 'validate_per_page' ],
					],
				],
			]
		);
	}

	/**
	 * Validates the `page` argument.
	 */
	public static function validate_page( $value ): bool {
		return ( is_numeric( $value ) && (int) $value >= 1 );
	}

	/**
	 * Validates the `per_page` argument.
	 */
	public static function validate_per_page( $value ): bool {
		return ( is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 50 );
	}

	/**
	 * Handles the GET /datasets request.
	 */
	public static function get_datasets( WP_REST_Request $request ) {
		$search   = trim( (string) $request->get_param( 'search' ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );

		if ( $per_page < 1 ) {
			$per_page = 10;
		}

		if ( $per_page > 50 ) {
			$per_page = 50;
		}

		$skip      = ( $page - 1 ) * $per_page;
		$cache_key = 'raise_datasets_' . md5( implode( '|', [ $search, $page, $per_page ] ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$url      = self::build_remote_url( $search, $skip, $per_page );
		$request_args = [
			'timeout'   => 15,
			'headers'   => [
				'Accept' => 'application/json',
			],
			'user-agent' => 'Raise-Datasets-Plugin/' . RAISE_DATASETS_VERSION,
		];

		$response = wp_remote_get( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'raise_datasets_http_error',
				__( 'Unable to reach the RAISE marketplace API.', 'raise-datasets' ),
				[ 'status' => 502 ]
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'raise_datasets_unexpected_status',
				__( 'Unexpected response from the RAISE marketplace API.', 'raise-datasets' ),
				[
					'body'   => $raw_body,
					'status' => $status_code,
				]
			);
		}

		$data = json_decode( $raw_body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'raise_datasets_invalid_payload',
				__( 'The data returned by the RAISE marketplace API is not valid JSON.', 'raise-datasets' ),
				[ 'status' => 502 ]
			);
		}

		$items = self::extract_items( $data );

		$response_payload = [
			'items'    => array_map( [ self::class, 'prepare_item' ], $items ),
			'page'     => $page,
			'per_page' => $per_page,
		];

		$total = self::extract_total( $data );
		if ( null !== $total ) {
			$response_payload['total'] = $total;
			$response_payload['has_more'] = ( $page * $per_page ) < $total;
		} else {
			$response_payload['has_more'] = count( $items ) === $per_page;
		}

		set_transient( $cache_key, $response_payload, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $response_payload );
	}

	/**
	 * Builds the URL for the remote API request.
	 */
	private static function build_remote_url( string $search, int $skip, int $take ): string {
		$args = [
			'skip' => max( 0, $skip ),
			'take' => max( 1, $take ),
		];

		if ( '' !== $search ) {
			$args['searchQuery'] = $search;
		}

		return add_query_arg( $args, self::REMOTE_ENDPOINT );
	}

	/**
	 * Extracts the dataset items from the API response.
	 *
	 * @param mixed $data The decoded payload.
	 */
	private static function extract_items( $data ): array {
		if ( is_array( $data ) ) {
			if ( isset( $data['successObject'] ) && is_array( $data['successObject'] ) ) {
				if ( isset( $data['successObject']['items'] ) && is_array( $data['successObject']['items'] ) ) {
					return $data['successObject']['items'];
				}

				if ( self::is_list( $data['successObject'] ) ) {
					return $data['successObject'];
				}
			}

			if ( isset( $data['items'] ) ) {
				if ( is_array( $data['items'] ) ) {
					if ( isset( $data['items']['data'] ) && is_array( $data['items']['data'] ) ) {
						return $data['items']['data'];
					}

					if ( isset( $data['items']['items'] ) && is_array( $data['items']['items'] ) ) {
						return $data['items']['items'];
					}

					if ( self::is_list( $data['items'] ) ) {
						return $data['items'];
					}
				}
			}

			if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
				if ( isset( $data['data']['items'] ) && is_array( $data['data']['items'] ) ) {
					return $data['data']['items'];
				}

				return $data['data'];
			}

			if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
				return $data['results'];
			}

			if ( 0 === count( $data ) ) {
				return [];
			}

			if ( self::is_list( $data ) ) {
				return $data;
			}
		}

		return [];
	}

	/**
	 * Attempts to extract the total number of datasets when provided by the API.
	 *
	 * @param mixed $data The decoded payload.
	 */
	private static function extract_total( $data ): ?int {
		if ( is_array( $data ) ) {
			foreach ( [ 'total', 'totalCount', 'count' ] as $key ) {
				if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) {
					return (int) $data[ $key ];
				}
			}

			foreach ( [ 'items', 'successObject', 'meta' ] as $container ) {
				if ( isset( $data[ $container ] ) && is_array( $data[ $container ] ) ) {
					foreach ( [ 'total', 'totalCount', 'count' ] as $key ) {
						if ( isset( $data[ $container ][ $key ] ) && is_numeric( $data[ $container ][ $key ] ) ) {
							return (int) $data[ $container ][ $key ];
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Determines whether an array is a list of values (numeric sequential keys).
	 */
	private static function is_list( array $array ): bool {
		return $array === array_values( $array );
	}

	/**
	 * Normalizes individual dataset items to the fields used on the frontend.
	 */
	private static function prepare_item( $item ): array {
		if ( ! is_array( $item ) ) {
			return [];
		}

		$id = isset( $item['id'] ) ? (string) $item['id'] : '';
		$title = isset( $item['title'] ) ? trim( wp_strip_all_tags( (string) $item['title'] ) ) : '';
		$description = isset( $item['description'] ) ? trim( wp_strip_all_tags( (string) $item['description'] ) ) : '';
		$organization = '';

		if ( isset( $item['organization'] ) ) {
			$organization = trim( wp_strip_all_tags( (string) $item['organization'] ) );
		} elseif ( isset( $item['creator'] ) ) {
			$organization = trim( wp_strip_all_tags( (string) $item['creator'] ) );
		} elseif ( isset( $item['userId'] ) ) {
			$organization = trim( wp_strip_all_tags( (string) $item['userId'] ) );
		}

		return [
			'id'           => $id,
			'title'        => $title,
			'description'  => $description,
			'organization' => $organization,
			'link'         => $id ? self::DATASET_BASE_URL . rawurlencode( $id ) : '',
		];
	}
}
