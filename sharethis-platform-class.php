<?php
/**
 * Defines ShareThis Optimization Platform class and calling singlton.
 *
 * @package ShareThis_Platform
 */

namespace sharethis\platform;

/**
 * ShareThis AB class
 *
 * @category Class
 * @author   ShareThis
 */
class ShareThis_Platform {


	const ADMIN_PAGE     = 'sharethis_platform_admin';
	const SETTINGS_GROUP = 'sharethis_platform_settings';
	const PLUGIN_VERSION = '1.1.2';

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'wp_head', array( $this, 'head_meta' ) );
		add_action( 'wp_footer', array( $this, 'sharethis_platform_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_resources' ) );
		add_action( 'admin_menu', array( $this, 'create_admin' ) );
		add_action( 'rest_api_init', array( $this, 'add_test_complete_endpoint' ) );
		add_action( 'admin_notices', array( $this, 'check_for_rest_server' ) );
	}

	/**
	 * Add an endpoint to accept a payload when a test completes.
	 *
	 * @return void
	 */
	public function add_test_complete_endpoint() {
		// Make sure they have the API available.
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}
		register_rest_route( 'sharethis', '/test-complete', array(
			array(
				'methods'  => \WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'api_test_complete' ),
				'permission_callback' => array( $this, 'api_permission_check' ),
			),
		) );
	}

	/**
	 * Check API call permissions.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function api_permission_check( $request ) {
		// Make sure they have openssl_verify installed.
		if ( ! function_exists( 'openssl_verify' ) ) {
			return false;
		}

		// Get the params.
		$params = $request->get_params();

		// Parse our params and get public key.
		$message = $params['message'];
		$signature = base64_decode( $params['signature'] );
		$public_key = $this->get_public_key();

		// Verify the caller is legit.
		$verified = openssl_verify( $message, $signature, $public_key, 'sha256WithRSAEncryption' );

		return ( $verified ) ? true : false;
	}

	/**
	 * Handle the call to the endpoint. When the test completes, this endpoint
	 * is hit with the winning headline. This method checks if the publisher
	 * wants to automatically update the headline. If they do, we check if
	 * the winning headline is different from the current headline. If it is,
	 * we update it.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_Error|\WP_HTTP_ResponseInterface|\WP_REST_Response
	 */
	public function api_test_complete( $request ) {
		// Get the payload.
		$params = $request->get_json_params();

		// URL base64 decode. This is to handle em dashes and other special chars.
		$message = rawurldecode( $params['message'] );

		$params = json_decode( $message, true );
		$url = esc_url_raw( $params['url'] );
		$best_variation_index = (int) $params['best_variation_index'];

		// Sanitize.
		if ( isset( $params['variations'][ $best_variation_index ]['title'] ) ) {
			$winning_headline = sanitize_text_field( $params['variations'][ $best_variation_index ]['title'] );
		} else {
			return $this->api_build_response(
				'error',
				400,
				'Something is missing from the JSON message.'
			);
		}

		// Check that we have a headline to update.
		if ( 0 === strlen( $winning_headline ) ) {
			return $this->api_build_response(
				'error',
				400,
				'Winning headline is empty.'
			);
		}

		// Get options.
		$update_headline = $this->get_autoupdate_headline();

		// Does the publisher want to update?
		if ( 0 === $update_headline ) {
			return $this->api_build_response( 'ok', 200 );
		}

		// Get the ID of the post we want to update.
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV ) {
			// Cached version of url_to_postid.
			$postid = wpcom_vip_url_to_postid( $url );
		} else {
			// @codingStandardsIgnoreLine
			$postid = url_to_postid( $url );
		}

		// Check that we have a post.
		if ( 0 === $postid ) {
			return $this->api_build_response(
				'error',
				400,
				'No post found at that URL.'
			);
		}

		$current_headline = get_the_title( $postid );

		// Check if the winning headline is different from the current one.
		if ( $current_headline !== $winning_headline ) {

			// It's different, so update it.
			$post_updated = $this->api_update_post_headline( $postid, $winning_headline );

			// Check to see if we actually updated it.
			if ( ! is_wp_error( $post_updated ) ) {
				return $this->api_build_response(
					'ok',
					200,
					'',
					array( 'headline_updated' => true )
				);
			} else {
				$error_message = $post_updated->get_error_message();
				return $this->api_build_response(
					'error',
					400,
					'Tried to update the headline but there was an error: ' . $error_message
				);
			}
		} else {
			// Headline is the same so don't do anything.
			return $this->api_build_response( 'ok', 200 );
		}//end if
	}

	/**
	 * Build and return our response.
	 *
	 * @param string $code 'ok' or 'error'.
	 * @param int    $status the status code, like 200, 400.
	 * @param string $message the description of what happened.
	 * @param array  $actions any additional parameters to return, like updated_headline => true.
	 *
	 * @return \WP_REST_Response
	 */
	protected function api_build_response( $code, $status, $message = '', $actions = array() ) {
		$response = array(
			'code'    => $code,
			'message' => $message,
			'data'    => array(
				'status'  => $status,
				'actions' => $actions,
			),
		);

		return new \WP_REST_Response( $response, $status );
	}

	/**
	 * Update a post's headline with the winning headline.
	 *
	 * @param int    $postid the post ID.
	 * @param string $winning_headline the new headline.
	 *
	 * @return \WP_Error|void
	 */
	public function api_update_post_headline( $postid, $winning_headline ) {
		$post_data = array(
			'ID'         => $postid,
			'post_title' => $winning_headline,
		);
		return wp_update_post( $post_data, true );
	}

	/**
	 * Check if this installation has auto updating installed but doesn't have the
	 * REST API enabled. Let them know how to fix it.
	 *
	 * @return void
	 */
	public function check_for_rest_server() {
		// Make sure we're only on our settings page.
		$screen = get_current_screen();
		if ( 'settings_page_sharethis_platform_admin' !== $screen->id ) {
			return;
		}

		// Do we want to update our headline?
		$auto_update = $this->get_autoupdate_headline();

		if ( ! $auto_update ) {
			return;
		} elseif ( ! function_exists( 'register_rest_route' ) ) {
?>
			<div class="notice notice-error">
				<p>
					You've elected to auto update your headlines but it
					looks like your version of WordPress does not have
					the REST API installed. You may need to update your
					version of WordPress or ask your host to enable the
					API.
				</p>
			</div>
<?php
		}
	}

	/**
	 * Register CSS on our settings page.
	 *
	 * @param string $hook The name of the current page.
	 *
	 * @return void
	 */
	public function register_resources( $hook ) {
		if ( 'post.php' === $hook  && 'publish' === get_post_status() ) {
			// Register our JS, edit screen only.
			wp_enqueue_script( 'sop-extras', plugins_url( '/js/main.js', __FILE__ ), array( 'jquery' ), null, true );
			wp_enqueue_style( 'sop-extras-css', plugins_url( 'sop-extras.css', __FILE__ ) );

			// Pass our permalink.
			$data = array( 'permalink' => get_permalink() );
			wp_localize_script( 'sop-extras', 'data', $data );
		} else if ( 'settings_page_sharethis_platform_admin' === $hook ) {
			// Register our css, settings page only.
			wp_enqueue_style( 'sop-style', plugins_url( 'sop-style.css', __FILE__ ) );
		}
	}

	/**
	 * Add the Platform meta tag to the head, if present.
	 *
	 * @return void
	 */
	public function head_meta() {
		$property_id = $this->get_property_id();
		if ( $property_id ) {
			echo '<meta name="sop-property" content="' . esc_attr( $property_id ) . '">';
		}
	}

	/**
	 * Registers settings
	 *
	 * @return void
	 */
	public function admin_init() {
		register_setting(
			self::ADMIN_PAGE . '_group',
			self::SETTINGS_GROUP,
			array( $this, 'sanitize_options' )
		);

		add_settings_section(
			self::ADMIN_PAGE . '_section',
			null,
			null,
			self::ADMIN_PAGE
		);

		add_settings_field(
			'property_id',
			__( 'Property ID', 'sharethis' ),
			array( $this, 'display_field' ),
			self::ADMIN_PAGE,
			self::ADMIN_PAGE . '_section',
			array( 'id' => 'property_id', 'type' => 'text', 'default' => '' )
		);

		add_settings_field(
			'auto_update_headline_enabled',
			__( 'Automatically update your post\'s headline when we find a winner', 'sharethis' ),
			array( $this, 'display_field' ),
			self::ADMIN_PAGE,
			self::ADMIN_PAGE . '_section',
			array( 'id' => 'auto_update_headline_enabled', 'type' => 'checkbox', 'default' => 0 )
		);
	}

	/**
	 * Creates the admin menu page.
	 *
	 * @return void
	 */
	public function create_admin() {

		$parent_slug = apply_filters( 'sharethis_platform_admin_parent', 'options-general.php' );

		add_submenu_page(
			$parent_slug,
			__( 'ShareThis Platform Settings', 'sharethis' ),
			__( 'ShareThis Platform', 'sharethis' ),
			'manage_options',
			self::ADMIN_PAGE,
			array( $this, 'display_admin' )
		);
	}

	/**
	 * Loads the admin template.
	 *
	 * @return void
	 */
	public function display_admin() {

		$this->settings = get_option( self::SETTINGS_GROUP );
		include 'templates/admin.php';
	}

	/**
	 * Verify the nonce.
	 *
	 * @param string $action A string for which a nonce should be set.
	 */
	public function verify_nonce( $action ) {

		$key = "{$action}-nonce";
		$post_val = filter_input( INPUT_POST, $key );

		if ( ! isset( $post_val ) ) {
			return false;
		}
		return wp_verify_nonce( $post_val, $action );
	}

	/**
	 * Displays a field.
	 *
	 * @param array $args Arguments array.
	 */
	public function display_field( array $args ) {
		$options = get_option( self::SETTINGS_GROUP, array( $args['id'] => $args['default'] ) );
		$html_id = $args['id'];
		$value_in_options = $options[ $html_id ];
		$html_type = $args['type'];

		if ( 'checkbox' === $html_type ) {
			$checked_field = ' ' . checked( 1, $value_in_options, false );
			$value_field = '1';
		} else {
			$checked_field = '';
			$value_field = $value_in_options;
		}

		printf(
			'<input type="%1$s" name="%2$s" value="%3$s" %4$s />',
			esc_attr( $html_type ),
			esc_attr( self::SETTINGS_GROUP . '[' . $html_id . ']' ),
			esc_attr( $value_field ),
			checked( 1, $value_in_options, false )
		);
	}

	/**
	 * Sanitizes the options.
	 *
	 * @param  mixed $input Input data.
	 * @return array
	 */
	public function sanitize_options( $input ) {
		return wp_parse_args(
			array_map( 'sanitize_text_field', $input ),
			array(
				'property_id'     => '',
				'update_headline' => '',
			)
		);
	}

	/**
	 * Returns the property id.
	 *
	 * @return string
	 */
	public function get_property_id() {
		$settings = get_option( self::SETTINGS_GROUP, array( 'property_id' => '' ) );
		return $settings['property_id'];
	}

	/**
	 * Return if auto updating the headline is enabled.
	 *
	 * @return bool
	 */
	public function get_autoupdate_headline() {
		$settings = get_option( self::SETTINGS_GROUP, array( 'auto_update_headline_enabled' => 0 ) );
		return (int) $settings['auto_update_headline_enabled'];
	}

	/**
	 * Enqueues the Platform script.
	 */
	public function sharethis_platform_script() {
		$property_id = $this->get_property_id();

		$url_param = '#product=sop-wordpress-plugin';
		if ( $property_id ) {
			$url_param .= '&property=' . $property_id;
		}
		$url = esc_url( 'https://platform-api.sharethis.com/js/sharethis.js' . $url_param );

		// Enqueue the main metrics script.
		wp_enqueue_script( 'sop-script', $url, [], null, true );

	}

	/**
	 * Get our public key for signature verification.
	 *
	 * @return $string
	 */
	public function get_public_key() {
		$public_key = <<<EOT
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAw+mUTrVevu0pX9nR4XJC
diOYVNBLDqx5gT8Gg4cWvpzEdsasX1Xgcegn+Az+OXkUzPiuSb9FggJz0ba2S+T1
nt0i5Ic+ogNcnHsX2YSGTU4Sl/onBG7PYVezWoxYuOxEOL0+hXY4iX70d6R6sNu6
ZR6JAzDMV+cJ4z/R1Y03G8QweFfGcV1KmUteVoxTqGUqOAu0agKiXJfH5en/UM2S
Q56Pscl63UtAHA0ewU1y69rlxydovneEs7sLTZ5hKqcWAdhIqLn32NK4PVVAVxKF
/x3iQ7PQ5J+bOw7Z5CvLQgjzGCrPQtxMS3yRe1UYcgFnsBE2QJy8iKA9G4jwzCAn
8wIDAQAB
-----END PUBLIC KEY-----
EOT;
		return $public_key;
	}
}

/**
 * Singleton function
 */
function sharethis_platform() {
	global $sharethis_platform;
	if ( ! $sharethis_platform ) {
		$sharethis_platform = new ShareThis_Platform();
	}
	return $sharethis_platform;
}
