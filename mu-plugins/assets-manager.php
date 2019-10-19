<?php
/*
  Plugin Name: Webcraftic AM plugin load filter
  Description: Dynamically activated only plugins that you have selected in each page. [Note]  Webcraftic AM has been automatically installed/deleted by Activate/Deactivate of "load filter plugin".
  Version: 1.0.6
  Plugin URI: https://wordpress.org/plugins/gonzales/
  Author: Webcraftic <alex.kovalevv@gmail.com>
  Author URI: https://clearfy.pro/assets-manager
  Framework Version: FACTORY_000_VERSION
*/

// todo: проверить, как работает кеширование
// todo: замерить, скорость работы этого решения

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) || isset( $_GET['wbcr_assets_manager'] ) ) {
	return;
}

// @formatter:off
//-------------------------------------------------------------------------------------------
// Plugins load filter
//-------------------------------------------------------------------------------------------

class WGNZ_Plugins_Loader {

	protected $prefix = 'wbcr_gnz_';
	protected $parent_plugin_dir;
	protected $settings;
	protected $active_plugins = array();

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
				$deactivate_components = get_site_option( $this->prefix . 'deactive_preinstall_components', array() );
			} else {
				$deactivate_components = get_option( $this->prefix . 'deactive_preinstall_components', array() );
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

		if( !file_exists($this->parent_plugin_dir) ) {
			return;
		}

		# Disable plugins only if Asset Manager and Clearfy are activated
		if ( $is_clearfy_active || $is_assets_manager_active ) {
			if( is_multisite() && is_network_admin() ) {
				$this->settings = get_site_option( $this->prefix . 'backend_assets_states', array() );
			} else if( is_admin() ) {
				$this->settings = get_option( $this->prefix . 'backend_assets_states', array() );
			} else {
				$this->settings = get_option( $this->prefix . 'assets_states', array() );
			}


			if ( ! empty( $this->settings ) ) {
				if ( is_multisite() ) {
					add_filter( 'site_option_active_sitewide_plugins', array($this, 'disable_network_plugins' ), 1 );
				}

				add_filter( 'option_active_plugins', array( $this, 'disable_plugins' ), 1 );
				add_filter( 'option_hack_file', array( $this, 'hack_file_filter' ), 1 );
				add_action( 'plugins_loaded', array( $this, 'remove_plugin_filters' ), 1 );
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
		remove_action( 'option_active_plugins', array( $this, 'disable_plugins' ), 1 );
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

			$new_plugin_list = array();
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

		$white_plgins_list = array(
			'clearfy', // prod
			'wp-plugin-clearfy', // dev
			'gonzales', // prod
			'wp-plugin-gonzales', // dev
			'clearfy_package' // premium package
		);

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
		$current_url = wp_parse_url( add_query_arg( array() ) );

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
