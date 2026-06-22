<?php
/**
 * Install Online Widget On WordPress site - main class.
 *
 * @package WordPress_Online_Widget_JivoChat
 *
 * Plugin Name: JivoChat
 * Author: JivoChat
 * Author URI: www.jivochat.com
 * Plugin URI: https://jivochat.com/
 * Description: Omnichannel Live Chat and Help Desk plugin, optimized for WordPress. Free, fast, easy to install and to use. Turn your visitors into happy customers!
 * Version: 1.3.6.1
 *
 * Text Domain: jivochat
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'go away!' );
}

load_plugin_textdomain( 'jivochat', false, WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) );

$site_url    = get_site_url();
$paths       = explode( '.', $site_url );
$domain_name = end( $paths );
$domain_name = str_replace( '/', '', $domain_name );

if ( (string) get_bloginfo( 'language' ) === 'ru_RU' ) {
	$jivosite_addr = 'https://www.jivo.ru';
} else {
	$jivosite_addr = 'https://www.jivochat.com';
}

$jivosite_url = 'https://api.jivosite.com';
if ( ! extension_loaded( 'openssl' ) ) {
	str_replace( 'https:', 'http:', $jivosite_url );
}

define( 'JIVOSITE_DOMAIN', $domain_name );
define( 'JIVOSITE_API_URL', $jivosite_url );
define( 'JIVOSITE_WIDGET_URL', '//code.jivosite.com/widget/' );
define( 'JIVOSITE_URL', $jivosite_addr );
define( 'JIVOSITE_LANGUAGES_URL', JIVOSITE_API_URL . '/web/integration/languages' );
define( 'JIVOSITE_INTEGRATION_URL', JIVOSITE_API_URL . '/web/integration' );
define( 'JIVOSITE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JIVOSITE_IMG_URL', plugin_dir_url( __FILE__ ) . '/img/' );
define( 'JIVOSITE_PLUGIN_VERSION', '1.3.6.1' );

/** Register the settings. */
function jivosite_register_settings() {
	register_setting( 'jivosite_token', 'jivosite_token' );
	register_setting( 'jivosite_widget_id', 'jivosite_widget_id' );
	register_setting( 'jivosite_lang_code', 'jivosite_lang_code' );
	register_setting( 'jivosite_plugin_version', 'jivosite_plugin_version' );
}

/** Add plugin to options menu. */
function jivosite_catalog_admin_menu() {
	load_plugin_textdomain( 'jivochat', false, WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) );
	jivosite_register_settings();
	add_menu_page( __( 'JivoChat', 'jivochat' ), __( 'JivoChat', 'jivochat' ), 'manage_options', 'class-jivosite.php', 'jivosite_preferences', JIVOSITE_IMG_URL . 'icon.png' );
}
add_action( 'admin_menu', 'jivosite_catalog_admin_menu' );

/** Add jivosite_widget on WordPress pages. */
function jivosite_append() {
	if ( ! get_option( 'jivosite_widget_id' ) ) {
		return;
	}

	/**
	 * The script downloaded from jivosite.com, as it may change with times and user settings.
	 * Refresh of script done by code.jivosite.com due to user actions in our service.
	 */
	wp_register_script( 'jivosite_widget_code', JIVOSITE_WIDGET_URL . get_option( 'jivosite_widget_id' ), array(), JIVOSITE_PLUGIN_VERSION, true );
	wp_enqueue_script( 'jivosite_widget_code' );
}

add_action( 'wp_enqueue_scripts', 'jivosite_append', 100000 );

/** Add css/js files to plugin settings page. */
function jivosite_preferences() {
	/** Add css to page */
	wp_register_style( 'jivosite_bootstrap', plugins_url( 'css/jivosite_bootstrap.css', __FILE__ ), array(), JIVOSITE_PLUGIN_VERSION );
	wp_register_style( 'jivosite_base', plugins_url( 'css/jivosite_base.css', __FILE__ ), array(), JIVOSITE_PLUGIN_VERSION );
	wp_enqueue_style( 'jivosite_bootstrap' );
	wp_enqueue_style( 'jivosite_base' );

	/** Add js to page */
	wp_register_script( 'jivosite_popper', plugins_url( 'scripts/jivosite_popper.js', __FILE__ ), array(), JIVOSITE_PLUGIN_VERSION, true );
	wp_register_script( 'jivosite_bootstrap', plugins_url( 'scripts/jivosite_bootstrap.js', __FILE__ ), array(), JIVOSITE_PLUGIN_VERSION, true );
	wp_enqueue_script( 'jivosite_popper' );
	wp_enqueue_script( 'jivosite_bootstrap' );

	/** Add locales to page */
	load_plugin_textdomain( 'jivochat', false, WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) );
	jivosite_update();

	/** Render html-page with plugin settings  */
	echo jivosite::get_instance()->render(); // phpcs:ignore
}

/** Updage version of jivochat plugin */
function jivosite_update() {
	if ( JIVOSITE_PLUGIN_VERSION !== get_option( 'jivosite_plugin_version' ) ) {
		update_option( 'jivosite_plugin_version', JIVOSITE_PLUGIN_VERSION );
	}
}

/**
 * Plugin main class, that do settings and add widget to WordPress site.
 */
class Jivosite {

	/**
	 * Static instance of class JivoSite.
	 *
	 * @var Jivosite $instance instance of class JivoSite.
	 */
	protected static $instance;

	/**
	 * Constructor of class
	 *
	 * @return void
	 */
	private function __construct() {
		$this->transport_enabled = $this->is_transport_enabled();
	}

	/**
	 * Clone the class
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * WakeUp class
	 *
	 * @return void
	 */
	public function __wakeup() {}

	/**
	 * Is curl is available on this hosting
	 *
	 * @var bool $transport_enabled bool value of accepting curl
	 */
	private $transport_enabled;

	/**
	 * Static instance of class JivoSite.
	 *
	 * @return mixed return instance of class.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Jivosite();
		}

		return self::$instance;
	}

	/**
	 * Response with variables for plugin setting page
	 *
	 * @return mixed[]|void|bool
	 */
	public function catch_post() {
		if ( ! empty( $_POST['email'] ) && ! empty( $_POST['userPassword'] ) && ! empty( $_POST['languageList'] ) ) {
			/** Use wp_nonce_field against XSS */
			if (
				! isset( $_POST['jivosite-signup-nonce'] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jivosite-signup-nonce'] ) ), 'jivosite_signup_form' )
			) {
				return array( 'error' => 'invalid request' );
			}

			/** Get data from jivosite.com */
			$user_site_domain = esc_url_raw( get_site_url(), null );
			$user_site_url    = empty( $user_site_domain ) ? 'wordpress.com' : $user_site_domain;

			$query['body']['partnerId']       = 'wordpress';
			$query['body']['email']           = sanitize_email( wp_unslash( $_POST['email'] ) );
			$query['body']['userPassword']    = sanitize_text_field( wp_unslash( $_POST['userPassword'] ) );
			$query['body']['userDisplayName'] = isset( $_POST['userDisplayName'] ) ? sanitize_text_field( wp_unslash( $_POST['userDisplayName'] ) ) : 'Agent';
			$query['body']['lang']            = sanitize_text_field( wp_unslash( $_POST['languageList'] ) );
			$query['body']['siteUrl']         = $user_site_url;
			$auth_token                       = md5( time() . $user_site_url );
			$query['body']['authToken']       = $auth_token;

			$response = $this->get_integration_install_response( $query );

			if ( $response ) {
				if ( strstr( $response, 'Error' ) ) {
					/** Escape done in output htmls */
					return array( 'error' => $response );
				} else {
					$this->update_jivo_option( 'widget_id', sanitize_text_field( $response ) );
					$this->update_jivo_option( 'token', $auth_token );
					$this->update_jivo_option( 'lang_code', $query['body']['lang'] );
					return true;
				}
			}
		} elseif ( ! empty( $_POST['email'] ) && ! empty( $_POST['userPassword'] ) && empty( $_POST['languageList'] ) ) {
			/** Use wp_nonce_field against XSS */
			if (
				! isset( $_POST['jivosite-login-nonce'] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jivosite-login-nonce'] ) ), 'jivosite_login_form' )
			) {
				return array( 'error' => 'invalid request' );
			}

			/** Get data from jivosite.com */
			$user_site_domain = esc_url_raw( get_site_url(), null );
			$user_site_url    = empty( $user_site_domain ) ? 'wordpress.com' : $user_site_domain;

			$query['body']['partnerId']       = 'WordPress';
			$query['body']['email']           = sanitize_email( wp_unslash( $_POST['email'] ) );
			$query['body']['userPassword']    = sanitize_text_field( wp_unslash( $_POST['userPassword'] ) );
			$query['body']['userDisplayName'] = 'userDisplayName';
			$query['body']['siteUrl']         = $user_site_url;

			$response = $this->get_integration_install_response( $query );

			if ( $response ) {
				if ( strstr( $response, 'Error' ) ) {
					/** Escape done in output htmls */
					return array(
						'error' => $response,
						'login' => true,
					);
				} else {
					$this->update_jivo_option( 'widget_id', sanitize_text_field( $response ) );

					return true;
				}
			}
		} elseif ( ! empty( $_POST['reset'] ) && 'reset' === sanitize_text_field( wp_unslash( (string) $_POST['reset'] ) ) ) {
			$this->update_jivo_option( 'widget_id', '' );
			$this->update_jivo_option( 'token', '' );
			$this->update_jivo_option( 'lang_code', '' );
		}
	}

	/**
	 * Raw page rendered for plugin settings
	 *
	 * @return mixed
	 */
	public function render() {
		if ( $this->transport_enabled ) {
			try {
				if ( ! $this->widget_id ) {
					$language_list = $this->get_language_list();
				}

				$result = $this->catch_post();
				if ( true === $result ) {
					$post_install = true;
				}

				if ( ! empty( $result['login'] ) ) {
					$login = true;
				}

				$error = '';
				if ( is_array( $result ) && isset( $result['error'] ) ) {
					$error = $result['error'];
				}

				require_once 'templates/page.php';
			} catch ( \Exception $e ) {
				require_once 'templates/error.php';
			}
		} else {
			require_once 'templates/error.php';
		}
	}

	/**
	 * Is curl is available on hosting for request.
	 *
	 * @return bool
	 */
	private function is_transport_enabled() {
		if ( ! extension_loaded( 'curl' ) && ! ini_get( 'allow_url_fopen' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get data from api.jivosite.com
	 *
	 * @param mixed[] $query query with parameters for request to api.jivosite.com.
	 *
	 * @return false|mixed|string|null
	 */
	private function get_integration_install_response( $query ) {
		if ( extension_loaded( 'curl' ) ) {
			return wp_remote_post( JIVOSITE_INTEGRATION_URL . '/install', $query )['body'];
		}

		if ( ini_get( 'allow_url_fopen' ) ) {
			foreach ( $query['body'] as $key => $value ) {
				$content[ $key ] = $value;
			}

            // phpcs:ignore
			return file_get_contents(
				JIVOSITE_INTEGRATION_URL . '/install',
				false,
				stream_context_create(
					array(
						'http' => array(
							'method'  => 'POST',
							'header'  => 'Content-Type: application/x-www-form-urlencoded',
							'content' => http_build_query( $content ),
						),
					)
				)
			);
		}

		return null;
	}

	/**
	 * Set new value for plugin setting option
	 *
	 * @param string          $option_name name of options to set for plugin.
	 * @param string|int|bool $option_value value of options to set for plugin.
	 *
	 * @return void
	 */
	private function update_jivo_option( $option_name, $option_value ) {
		if ( current_user_can( 'manage_options' ) ) {
			update_option( 'jivosite_' . $option_name, $option_value );
		}
	}

	/**
	 * Get supported language list from jivosite.com
	 *
	 * @return mixed[]
	 */
	private function get_language_list() {
		$language_list = $this->fetch_supported_languages();

		$suggested_language = $this->get_suggested_language( JIVOSITE_DOMAIN, $language_list );

		if ( $suggested_language ) {
			$language_list = $this->get_reordered_language_list(
				$suggested_language,
				$language_list
			);
		}

		return $language_list;
	}

	/**
	 * Get list of available languages or null
	 *
	 * @return mixed|null.
	 */
	private function fetch_supported_languages() {
		if ( extension_loaded( 'curl' ) ) {
			return json_decode( wp_remote_get( JIVOSITE_LANGUAGES_URL )['body'], true );
		}

		if ( ini_get( 'allow_url_fopen' ) ) {
            // phpcs:ignore
			return json_decode( file_get_contents( JIVOSITE_LANGUAGES_URL ), true );
		}

		return null;
	}

	/**
	 * Get suggested language
	 *
	 * @param string  $domain domain of WordPress site.
	 * @param mixed[] $language_list list of available languages.
	 *
	 * @return mixed
	 */
	private function get_suggested_language( $domain, $language_list ) {
		$suggested_language = $this->get_suggested_language_by_domain( $domain, $language_list );

		if ( ! $suggested_language ) {
			$suggested_language = $this->get_suggested_language_by_locale(
				str_replace( '-', '_', (string) get_bloginfo( 'language' ) ),
				$language_list
			);
		}

		return $suggested_language;
	}

	/**
	 * Get suggested list with available languages
	 *
	 * @param string  $domain domain of WordPress site.
	 * @param mixed[] $language_list list of available languages.
	 *
	 * @return mixed
	 */
	private function get_suggested_language_by_domain( $domain, $language_list ) {
		$suggested_language = null;

		foreach ( $language_list as $language ) {
			if ( in_array( (string) $domain, $language['domains'], true ) ) {
				$suggested_language = $language;
				break;
			}
		}

		return $suggested_language;
	}

	/**
	 * Get suggested list with available languages
	 *
	 * @param string  $locale locale of WordPress site.
	 * @param mixed[] $language_list list of available languages.
	 *
	 * @return mixed|null
	 */
	private function get_suggested_language_by_locale( $locale, $language_list ) {
		$suggested_language = null;

		foreach ( $language_list as $language ) {
			if ( 'Spanish (General)' === (string) $language['name'] ) {
				$codes = explode( ',', $language['code'] );
				foreach ( $codes as $code ) {
					if ( strpos( $locale, $code ) !== false ) {
						$language['code']   = $locale;
						$suggested_language = $language;
						break;
					}
				}
			} else {
				if ( strpos( $locale, $language['code'] ) !== false ) {
					$suggested_language = $language;
				}
			}
			if ( $suggested_language ) {
				break;
			}
		}

		return $suggested_language;
	}

	/**
	 * Get reordered list with available languages
	 *
	 * @param mixed[] $suggested_language language of widget.
	 * @param mixed[] $language_list list of available languages.
	 *
	 * @return mixed
	 */
	private function get_reordered_language_list( $suggested_language, $language_list ) {
		$suggested_language_index = array_search( $suggested_language, $language_list, true );
		array_splice( $language_list, $suggested_language_index, 1 );
		array_unshift( $language_list, $suggested_language );

		return $language_list;
	}
}
