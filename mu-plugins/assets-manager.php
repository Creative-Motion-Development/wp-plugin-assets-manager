<?php
/*
  Plugin Name: Webcraftic AM plugin load filter
  Description: Dynamically activated only plugins that you have selected in each page. [Note]  Webcraftic AM has been automatically installed/deleted by Activate/Deactivate of "load filter plugin".
  Version: 1.0.1
  Plugin URI: https://wordpress.org/plugins/gonzales/
  Author: Webcraftic <alex.kovalevv@gmail.com>
  Author URI: https://clearfy.pro/assets-manager
  Framework Version: FACTORY_000_VERSION
*/
// TODO: The plugin does not support backend
// todo: проверить, как работает кеширование
// todo: замерить, скорость работы этого решения

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) || is_admin() || isset( $_GET['wbcr_assets_manager'] ) ) {
	return;
}

// @formatter:off
// @formatter:on

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
		} else if ( in_array( 'gonzales/gonzales.php', $active_plugins ) || in_array( 'wp-plugin-gonzales/gonzales.php', $active_plugins ) ) {
			$is_assets_manager_active = true;
			$this->prefix             = 'wbcr_gnz_';
		}

		# Disable plugins only if Asset Manager and Clearfy are activated
		if ( $is_clearfy_active || $is_assets_manager_active ) {
			$this->settings = get_option( $this->prefix . 'assets_manager_options', array() );

			if ( ! empty( $this->settings ) ) {
				if ( is_multisite() ) {
					add_filter( 'site_option_active_sitewide_plugins', array( $this, 'disable_network_plugins' ), 1 );
				}

				add_filter( 'option_active_plugins', array( $this, 'disable_plugins' ), 1 );

				add_filter( 'option_hack_file', array( $this, 'hack_file_filter' ), 1 );
				add_action( 'plugins_loaded', array( $this, 'remove_plugin_filters' ), 1 );
				//add_action( 'wp_loaded', array( &$this, 'cache_post_type' ), 1 );
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
	 * Before plugins loaded, it does not use conditional branch such as is_home,
	 * to set wp_query, wp in temporary query
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function set_wp_query() {
		if ( empty( $GLOBALS['wp_the_query'] ) ) {
			$rewrite_rules = get_option( 'rewrite_rules' );

			$data = get_option( $this->prefix . 'queryvars' );

			if ( empty( $rewrite_rules ) || empty( $data['rewrite_rules'] ) || $rewrite_rules !== $data['rewrite_rules'] ) {
				$data['rewrite_rules'] = ( empty( $rewrite_rules ) ) ? '' : $rewrite_rules;
				update_option( $this->prefix . 'queryvars', $data );

				return false;
			}

			$GLOBALS['wp_the_query'] = new WP_Query();
			$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
			$GLOBALS['wp_rewrite']   = new WP_Rewrite();
			$GLOBALS['wp']           = new WP();

			//register_taxonomy(category, post_tag, post_format) support for is_archive
			$this->force_initial_taxonomies();

			//Post Format, Custom Post Type support
			//add_action( 'parse_request', array( $this, 'parse_request' ) );

			$GLOBALS['wp']->parse_request( '' );
			$GLOBALS['wp']->query_posts();
		}

		global $wp_query;

		if ( ! is_embed() ) {
			if ( ( is_home() || is_front_page() || is_archive() || is_search() || is_singular() ) == false || ( is_home() && ! empty( $_GET ) ) ) {
				return false;
			} else if ( is_singular() && empty( $wp_query->post ) ) {
				if ( empty( $wp_query->query_vars['post_type'] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Make taxonomies and posts available to 'plugin load filter'.
	 * force register_taxonomy (category, post_tag, post_format)
	 *
	 * @since 1.0.0
	 */
	private function force_initial_taxonomies() {
		global $wp_actions;
		$wp_actions['init'] = 1;
		create_initial_taxonomies();
		create_initial_post_types();
		unset( $wp_actions['init'] );
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

		# If there are no plugins disabled
		if ( ! isset( $this->settings['disabled'] ) || ! isset( $this->settings['disabled']['plugins'] ) || empty( $this->settings['disabled']['plugins'] ) ) {
			return false;
		}

		$enabled = array();
		if ( isset( $this->settings['enabled'] ) && isset( $this->settings['enabled']['plugins'] ) && ! empty( $this->settings['enabled']['plugins'] ) ) {
			$enabled = $this->settings['enabled']['plugins'];
		}

		foreach ( (array) $this->settings['disabled']['plugins'] as $plugin_slug => $conditions ) {
			$enabled_conditions = isset( $enabled[ $plugin_slug ] ) ? $enabled[ $plugin_slug ] : array();

			if ( $plugin_base_part[0] == $plugin_slug && $this->is_plugin_disabled( $conditions, $enabled_conditions ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.0
	 *
	 * @param $disabled
	 * @param $enabled
	 *
	 * @return bool
	 */
	private function is_plugin_disabled( $disabled, $enabled ) {
		$current_url = $this->get_current_url();

		if ( empty( $current_url ) ) {
			return false;
		}

		$is_disabled_everywhere = isset( $disabled['everywhere'] ) && $disabled['everywhere'] == 1;
		$is_disabled_current    = isset( $disabled['current'] ) && is_array( $disabled['current'] ) && in_array( $current_url, $disabled['current'] );
		$is_disabled_custom     = isset( $disabled['custom'] ) && is_array( $disabled['custom'] ) && ! empty( $disabled['custom'] );
		$is_disabled_regex      = isset( $disabled['regex'] ) && ! empty( $disabled['regex'] );

		if ( $is_disabled_everywhere || $is_disabled_current || $is_disabled_custom || $is_disabled_regex ) {
			if ( isset( $enabled['current'] ) && is_array( $enabled['current'] ) && in_array( $current_url, $enabled['current'] ) ) {
				return false;
			}

			// Exclude post types
			if ( isset( $enabled['post_types'] ) ) {
				if ( $this->set_wp_query() && is_singular() ) {
					global $wp_query;

					$type = get_post_type( $wp_query->post );

					if ( $type === false && isset( $wp_query->query_vars['post_type'] ) ) {
						$type = $wp_query->query_vars['post_type'];
					}
					if ( $type === 'post' ) {
						$fmt  = get_post_format( $wp_query->post );
						$type = ( $fmt === 'standard' || $fmt == false ) ? 'post' : "post-$fmt";
					}

					if ( in_array( $type, $enabled['post_types'] ) ) {
						return false;
					}
				}
			}

			// Exclude taxonomies
			if ( isset( $enabled['taxonomies'] ) ) {
				if ( $this->set_wp_query() && ( is_tax() || is_tag() ) ) {
					$query = get_queried_object();

					if ( ! empty( $query ) && isset( $query->taxonomy ) && in_array( $query->taxonomy, $enabled['taxonomies'] ) ) {
						return false;
					}
				}
			}

			// Exclude cats
			if ( isset( $enabled['categories'] ) ) {
				if ( $this->set_wp_query() && in_array( get_query_var( 'cat' ), $enabled['categories'] ) ) {
					return false;
				}
			}

			if ( $is_disabled_custom ) {
				$found_match = false;

				foreach ( (array) $disabled['custom'] as $url ) {
					// Убираем базовый url
					$free_url = str_replace( site_url(), '', $url );
					// Если есть *
					if ( strpos( $free_url, '*' ) ) {
						// Получаем строку до *
						$free_url = strstr( $free_url, '*', true );
						// Если это был не пустой url (типа http://site/*) и есть вхождение с начала
						if ( untrailingslashit( $free_url ) && strpos( $current_url, $free_url ) === 0 ) {
							$found_match = true;
							break;
						}
						// Если url'ы идентичны
					} else if ( untrailingslashit( esc_url( $free_url ) ) === $current_url ) {
						$found_match = true;
						break;
					}
				}

				if ( ! $found_match ) {
					return false;
				}
			}

			if ( $is_disabled_regex && ! @preg_match( '/' . trim( str_replace( '\\\\', '\\', $disabled['regex'] ), '/' ) . '/', ltrim( $current_url, '/\\' ) ) ) {
				return false;
			}

			return true;
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

	//Post Format Type, Custom Post Type Data Cache for parse request
	/*private function cache_post_type() {
		if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}

		global $wp;
		global $wp_post_statuses;
		$public_query_vars = ( ! empty( $wp->public_query_vars ) ) ? $wp->public_query_vars : array();;
		$post_type_query_vars = array();
		foreach ( get_post_types( array(), 'objects' ) as $post_type => $t ) {
			if ( $t->query_var ) {
				$post_type_query_vars[ $t->query_var ] = $post_type;
			}
		}
		$queryable_post_types = get_post_types( array( 'publicly_queryable' => true ) );

		$data = get_option( $this->prefix . 'queryvars' );
		if ( ! empty( $post_type_query_vars ) && ! empty( $queryable_post_types ) ) {
			$data['public_query_vars']    = $public_query_vars;
			$data['post_type_query_vars'] = $post_type_query_vars;
			$data['queryable_post_types'] = $queryable_post_types;
			$data['wp_post_statuses']     = $wp_post_statuses;
			update_option( $this->prefix . 'queryvars', $data );
		} else if ( ! empty( $data['post_type_query_vars'] ) || ! empty( $data['queryable_post_types'] ) ) {
			//delete_option($this->prefix . 'queryvars');
			$data['public_query_vars']    = '';
			$data['post_type_query_vars'] = '';
			$data['queryable_post_types'] = '';
			$data['wp_post_statuses']     = '';
			update_option( $this->prefix . 'queryvars', $data );
		}
	}*/

	//parse_request Action Hook for Custom Post Type query add
	/*public function parse_request( &$args ) {
		if ( did_action( 'plugins_loaded' ) === 0 ) {
			$data = get_option( $this->prefix . 'queryvars' );

			if ( ! empty( $data['post_type_query_vars'] ) && ! empty( $data['queryable_post_types'] ) ) {
				global $wp_post_statuses;
				$post_type_query_vars = $data['post_type_query_vars'];
				$queryable_post_types = $data['queryable_post_types'];
				$wp_post_statuses     = $data['wp_post_statuses'];

				$args->public_query_vars = $data['public_query_vars'];
				if ( isset( $args->matched_query ) ) {
					parse_str( $args->matched_query, $perma_query_vars );
				}
				foreach ( $args->public_query_vars as $wpvar ) {
					if ( isset( $args->extra_query_vars[ $wpvar ] ) ) {
						$args->query_vars[ $wpvar ] = $args->extra_query_vars[ $wpvar ];
					} else if ( isset( $_POST[ $wpvar ] ) ) {
						$args->query_vars[ $wpvar ] = $_POST[ $wpvar ];
					} else if ( isset( $_GET[ $wpvar ] ) ) {
						$args->query_vars[ $wpvar ] = $_GET[ $wpvar ];
					} else if ( isset( $perma_query_vars[ $wpvar ] ) ) {
						$args->query_vars[ $wpvar ] = $perma_query_vars[ $wpvar ];
					}

					if ( ! empty( $args->query_vars[ $wpvar ] ) ) {
						if ( ! is_array( $args->query_vars[ $wpvar ] ) ) {
							$args->query_vars[ $wpvar ] = (string) $args->query_vars[ $wpvar ];
						} else {
							foreach ( $args->query_vars[ $wpvar ] as $vkey => $v ) {
								if ( ! is_object( $v ) ) {
									$args->query_vars[ $wpvar ][ $vkey ] = (string) $v;
								}
							}
						}
						if ( isset( $post_type_query_vars[ $wpvar ] ) ) {
							$args->query_vars['post_type'] = $post_type_query_vars[ $wpvar ];
							$args->query_vars['name']      = $args->query_vars[ $wpvar ];
						}
					}
				}

				// Limit publicly queried post_types to those that are publicly_queryable
				if ( isset( $args->query_vars['post_type'] ) ) {
					if ( ! is_array( $args->query_vars['post_type'] ) ) {
						if ( ! in_array( $args->query_vars['post_type'], $queryable_post_types ) ) {
							unset( $args->query_vars['post_type'] );
						}
					} else {
						$args->query_vars['post_type'] = array_intersect( $args->query_vars['post_type'], $queryable_post_types );
					}
				}
			}
		}
	}*/


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
