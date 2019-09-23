<?php
/*
  Plugin Name: Webcraftic AM plugin load filter
  Description: Dynamically activated only plugins that you have selected in each page. [Note]  Webcraftic AM has been automatically installed/deleted by Activate/Deactivate of "load filter plugin".
  Version: 1.0.2
  Plugin URI: https://wordpress.org/plugins/gonzales/
  Author: Webcraftic <alex.kovalevv@gmail.com>
  Author URI: https://clearfy.pro/assets-manager
  Framework Version: FACTORY_000_VERSION
*/

// TODO: the plugin does not support backend
// todo: проверить, как работает кеширование
// todo: замерить, скорость работы этого решения
// todo: поддержка php 5.3

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) || is_admin() || isset( $_GET['wbcr_assets_manager'] ) ) {
	return;
}

// @formatter:off
//-------------------------------------------------------------------------------------------
// pluggable.php defined function overwrite
// pluggable.php read before the query_posts() is processed by the current user undetermined
//-------------------------------------------------------------------------------------------
if ( ! function_exists( 'wp_get_current_user' ) ) :
	/**
	 * Retrieve the current user object.
	 *
	 * @return WP_User Current user WP_User object
	 */
	function wp_get_current_user() {
		if ( ! function_exists( 'wp_set_current_user' ) ) {
			return 0;
		} else {
			return _wp_get_current_user();
		}
	}
endif;

if ( ! function_exists( 'get_userdata' ) ) :
	/**
	 * Retrieve user info by user ID.
	 *
	 * @param int $user_id   User ID
	 *
	 * @return WP_User|bool WP_User object on success, false on failure.
	 */
	function get_userdata( $user_id ) {
		return get_user_by( 'id', $user_id );
	}
endif;

if ( ! function_exists( 'get_user_by' ) ) :
	/**
	 * Retrieve user info by a given field
	 *
	 * @param string     $field   The field to retrieve the user with. id | slug | email | login
	 * @param int|string $value   A value for $field. A user ID, slug, email address, or login name.
	 *
	 * @return WP_User|bool WP_User object on success, false on failure.
	 */
	function get_user_by( $field, $value ) {
		$userdata = WP_User::get_data_by( $field, $value );

		if ( ! $userdata ) {
			return false;
		}

		$user = new WP_User;
		$user->init( $userdata );

		return $user;
	}
endif;

if ( ! function_exists( 'is_user_logged_in' ) ) :
	/**
	 * Checks if the current visitor is a logged in user.
	 *
	 * @return bool True if user is logged in, false if not logged in.
	 */
	function is_user_logged_in() {
		if ( ! function_exists( 'wp_set_current_user' ) ) {
			return false;
		}

		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return false;
		}

		return true;
	}
endif;

//-------------------------------------------------------------------------------------------
// Plugins load filter
//-------------------------------------------------------------------------------------------

class WGNZ_Plugins_Loader {

	protected $prefix = 'wbcr_gnz_';
	protected $parent_plugin_dir;
	protected $settings;
	protected $active_plugins = [];

	public function __construct() {
		# We must always load the plugin if it is an ajax request, a cron
		# task or a rest api request. Otherwise, the user may have problems
		# with the work of plugins.
		if ( $this->doing_ajax() || $this->doing_cron() || $this->doing_rest_api() ) {
			return;
		}

		$is_assets_manager_active = false;
		$is_clearfy_active        = false;

		$active_plugins = $this->get_active_plugins();

		if ( in_array( 'clearfy/clearfy.php', $active_plugins ) || in_array( 'wp-plugin-clearfy/clearfy.php', $active_plugins ) ) {
			$this->prefix = 'wbcr_clearfy_';

			if ( is_multisite() ) {
				$deactivate_components = get_site_option( $this->prefix . 'deactive_preinstall_components', [] );
			} else {
				$deactivate_components = get_option( $this->prefix . 'deactive_preinstall_components', [] );
			}

			if ( empty( $deactivate_components ) || ! in_array( 'assets_manager', $deactivate_components ) ) {
				$is_clearfy_active = true;
			}
			if ( in_array( 'wp-plugin-clearfy/clearfy.php', $active_plugins ) ) {
				$this->parent_plugin_dir = WP_PLUGIN_DIR . '/wp-plugin-clearfy/components/assets-manager/';
			} else {
				$this->parent_plugin_dir = WP_PLUGIN_DIR . '/clearfy/components/assets-manager/';
			}
		} else if ( in_array( 'gonzales/gonzales.php', $active_plugins ) || in_array( 'wp-plugin-gonzales/gonzales.php', $active_plugins ) ) {
			$is_assets_manager_active = true;
			$this->prefix             = 'wbcr_gnz_';
			$this->parent_plugin_dir  = WP_PLUGIN_DIR . '/gonzales/';
		}

		# Disable plugins only if Asset Manager and Clearfy are activated
		if ( $is_clearfy_active || $is_assets_manager_active ) {
			$this->settings = get_option( $this->prefix . 'settings', [] );

			if ( ! empty( $this->settings ) ) {
				if ( is_multisite() ) {
					add_filter( 'site_option_active_sitewide_plugins', [ $this, 'disable_network_plugins' ], 1 );
				}

				add_filter( 'option_active_plugins', [ $this, 'disable_plugins' ], 1 );
				add_filter( 'option_hack_file', [ $this, 'hack_file_filter' ], 1 );
				add_action( 'plugins_loaded', [ $this, 'remove_plugin_filters' ], 1 );
			}
		}
	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.0
	 *
	 * @param $hackFile
	 *
	 * @return mixed
	 */
	public function hack_file_filter( $hackFile ) {
		$this->remove_plugin_filters();

		return $hackFile;
	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.0
	 */
	public function remove_plugin_filters() {
		remove_action( 'option_active_plugins', [ $this, 'disable_plugins' ], 1 );
	}

	/**
	 * We control the disabling of plugins that are activated for the network.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.0
	 */
	public function disable_network_plugins( $plugins_list ) {
		$new_plugin_list = $plugins_list;

		if ( is_array( $plugins_list ) && ! empty( $plugins_list ) ) {
			$temp_plugin_list = array_keys( $plugins_list );
			$temp_plugin_list = $this->disable_plugins( $temp_plugin_list );

			$new_plugin_list = [];
			foreach ( (array) $temp_plugin_list as $plugin_file ) {
				$new_plugin_list[ $plugin_file ] = $plugins_list[ $plugin_file ];
			}
		}

		return $new_plugin_list;
	}

	/**
	 * We control the disabling of plugins that are activated for blog.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.0
	 *
	 * @param $plugins_list
	 *
	 * @return mixed
	 */
	public function disable_plugins( $plugins_list ) {
		if ( ! is_array( $plugins_list ) || empty( $plugins_list ) ) {
			return $plugins_list;
		}

		foreach ( (array) $plugins_list as $key => $plugin_base ) {
			if ( $this->is_disabled_plugin( $plugin_base ) ) {
				unset( $plugins_list[ $key ] );
			}
		}

		return $plugins_list;
	}

	/**
	 * Get a list of active plugins.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.0
	 * @return array
	 */
	private function get_active_plugins() {
		if ( is_multisite() ) {
			$active_network_plugins = (array) get_site_option( 'active_sitewide_plugins' );
			$active_network_plugins = array_keys( $active_network_plugins );
			$active_blog_plugins    = (array) get_option( 'active_plugins' );

			return array_unique( array_merge( $active_network_plugins, $active_blog_plugins ) );
		}

		return (array) get_option( 'active_plugins' );
	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.0
	 *
	 * @param $plugin_base
	 *
	 * @return bool
	 */
	private function is_disabled_plugin( $plugin_base ) {

		$white_plgins_list = [
			'clearfy', // prod
			'wp-plugin-clearfy', // dev
			'gonzales', // prod
			'wp-plugin-gonzales', // dev
			'clearfy_package' // premium package
		];

		$plugin_base_part = explode( '/', $plugin_base );

		# If plugin base is incorrect or plugin name in the white list
		if ( 2 !== sizeof( $plugin_base_part ) || in_array( $plugin_base_part[0], $white_plgins_list ) ) {
			return false;
		}

		if ( ! empty( $this->settings['plugins'] ) && isset( $this->settings['plugins'][ $plugin_base_part[0] ] ) && 'disable_plugin' === $this->settings['plugins'][ $plugin_base_part[0] ]['load_mode'] ) {
			require_once $this->parent_plugin_dir . '/includes/classes/class-check-conditions.php';
			if ( ! empty( $this->settings['plugins'][ $plugin_base_part[0] ]['visability'] ) ) {
				$condition = new WGZ_Check_Conditions( $this->settings['plugins'][ $plugin_base_part[0] ]['visability'] );
				if ( $condition->validate() ) {
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * Get current URL
	 *
	 * @return string
	 */
	private function get_current_url() {
		$url = explode( '?', $_SERVER['REQUEST_URI'], 2 );
		if ( strlen( $url[0] ) > 1 ) {
			$out = rtrim( $url[0], '/' );
		} else {
			$out = $url[0];
		}

		return $out;
	}

	/**
	 * Checks if the current request is a WP REST API request.
	 *
	 * Case #1: After WP_REST_Request initialisation
	 * Case #2: Support "plain" permalink settings
	 * Case #3: URL Path begins with wp-json/ (your REST prefix)
	 *          Also supports WP installations in subfolders
	 *
	 * @author matzeeable https://wordpress.stackexchange.com/questions/221202/does-something-like-is-rest-exist
	 * @since  1.0.0
	 * @return boolean
	 */
	private function doing_rest_api() {
		$prefix = rest_get_url_prefix();

		$rest_route = isset( $_GET['rest_route'] ) ? $_GET['rest_route'] : null;

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST // (#1)
		     || ! is_null( $rest_route ) // (#2)
		        && strpos( trim( $rest_route, '\\/' ), $prefix, 0 ) === 0 ) {
			return true;
		}

		// (#3)
		$rest_url    = wp_parse_url( site_url( $prefix ) );
		$current_url = wp_parse_url( add_query_arg( [] ) );

		return strpos( $current_url['path'], $rest_url['path'], 0 ) === 0;
	}

	/**
	 * @since 1.0.0
	 * @return bool
	 */
	private function doing_ajax() {
		if ( function_exists( 'wp_doing_ajax' ) ) {
			return wp_doing_ajax();
		}

		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * @since 1.0.0
	 * @return bool
	 */
	private function doing_cron() {
		if ( function_exists( 'wp_doing_cron' ) ) {
			return wp_doing_cron();
		}

		return defined( 'DOING_CRON' ) && DOING_CRON;
	}
}

new WGNZ_Plugins_Loader();
// @formatter:on
