<?php
/*
  Plugin Name: Webcraftic AM plugin load filter
  Description: Dynamically activated only plugins that you have selected in each page. [Note]  Webcraftic AM has been automatically installed/deleted by Activate/Deactivate of "load filter plugin".
  Version: 1.0.0
  Plugin URI: https://wordpress.org/plugins/gonzales/
  Author: Alexander Kovalev <alex.kovalevv@gmail.com>
  Author URI: https://clearfy.pro/assets-manager
  Framework Version: FACTORY_000_VERSION
*/

// todo: добавить поддержку мультисайтов
// todo: проверить, как работает кеширование
// todo: замерить, скорость работы этого решения

//return;

// @formatter:off
// @formatter:on
class WGNZ_Plugins_Loader {

	protected $prefix = 'wbcr_gnz_';
	protected $active_plugins = array();

	public function __construct() {

		if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) || isset( $_GET['wbcr_assets_manager'] ) ) {
			return;
		}

		add_filter( 'option_active_plugins', array( $this, 'disable_plugins' ), 1 );

		add_filter( 'option_hack_file', array( $this, 'hack_file_filter' ), 1 );
		add_action( 'plugins_loaded', array( $this, 'remove_plugin_filters' ), 1 );
		//add_action( 'wp_loaded', array( &$this, 'cache_post_type' ), 1 );

	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.7
	 *
	 * @param $hackFile
	 *
	 * @return mixed
	 */
	function hack_file_filter( $hackFile ) {
		$this->remove_plugin_filters();

		return $hackFile;
	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.7
	 */
	public function remove_plugin_filters() {
		remove_action( 'option_active_plugins', array( $this, 'disable_plugins' ), 1 );
	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.7
	 */
	public function disable_network_plugins() {

	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.7
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	public function disable_plugins( $value ) {
		if ( is_admin() || $this->doing_cron() || $this->doing_rest_api() ) {
			return $value;
		}

		$is_asmanager_active         = false;
		$is_clearfy_component_active = false;
		//$is_clearfy_premium_component_active = false;

		if ( in_array( 'clearfy/clearfy.php', (array) $value ) || in_array( 'wp-plugin-clearfy/clearfy.php', (array) $value ) ) {
			$this->prefix = 'wbcr_clearfy_';

			$deactivate_components = $this->get_option( 'deactive_preinstall_components', array() );

			if ( empty( $deactivate_components ) || ! in_array( 'assets_manager', $deactivate_components ) ) {
				$is_clearfy_component_active = true;

				# If a free component is active, then check whether the premium component is active.
				/*$freemius_activated_addons = $this->get_option( 'freemius_activated_addons', array() );

				if ( in_array( 'assets-manager-premium', $freemius_activated_addons ) ) {
					$is_clearfy_premium_component_active = true;
				}*/
			}
		} else if ( in_array( 'gonzales/gonzales.php', (array) $value ) || in_array( 'wp-plugin-gonzales/gonzales.php', (array) $value ) ) {
			$is_asmanager_active = true;
			$this->prefix        = 'wbcr_gnz_';
		}

		# Disable plugins only if Asset Manager and Clearfy are activated
		if ( $is_clearfy_component_active || $is_asmanager_active ) {
			foreach ( (array) $value as $key => $plugin_base ) {
				if ( $this->is_disabled_plugin( $plugin_base ) ) {
					unset( $value[ $key ] );
				}
			}
		}

		return $value;
	}

	/**
	 * Get option
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.7
	 *
	 * @param $option
	 * @param $default
	 *
	 * @return mixed|void
	 */
	private function get_option( $option, $default = false ) {
		if ( is_multisite() ) {
			return get_site_option( $this->prefix . $option, $default );
		} else {
			return get_option( $this->prefix . $option, $default );
		}
	}

	/**
	 * Update option
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.7
	 *
	 * @param $option
	 * @param $value
	 */
	private function update_option( $option, $value ) {
		if ( is_multisite() ) {
			update_site_option( $this->prefix . $option, $value );
		} else {
			update_option( $this->prefix . $option, $value );
		}
	}


	/**
	 * Get asset manager settings. We need to know which plugins have been disabled.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.7
	 * @return mixed|void
	 */
	private function get_settings() {
		if ( is_multisite() && is_network_admin() ) {
			return get_site_option( $this->prefix . 'assets_manager_options', array() );
		}

		return get_option( $this->prefix . 'assets_manager_options', array() );
	}

	/**
	 * Before plugins loaded, it does not use conditional branch such as is_home,
	 * to set wp_query, wp in temporary query
	 *
	 * @since 1.0.7
	 * @return bool
	 */
	private function set_wp_query() {
		if ( empty( $GLOBALS['wp_the_query'] ) ) {
			$rewrite_rules = get_option( 'rewrite_rules' );

			$data = $this->get_option( 'queryvars' );

			if ( empty( $rewrite_rules ) || empty( $data['rewrite_rules'] ) || $rewrite_rules !== $data['rewrite_rules'] ) {
				$data['rewrite_rules'] = ( empty( $rewrite_rules ) ) ? '' : $rewrite_rules;
				$this->update_option( 'queryvars', $data );

				return false;
			}

			$GLOBALS['wp_the_query'] = new WP_Query();
			$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
			$GLOBALS['wp_rewrite']   = new WP_Rewrite();
			$GLOBALS['wp']           = new WP();

			//register_taxonomy(category, post_tag, post_format) support for is_archive
			$this->force_initial_taxonomies();

			//Post Format, Custom Post Type support
			add_action( 'parse_request', array( $this, 'parse_request' ) );

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
	 * @since 1.0.7
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
	 * @since  1.0.7
	 *
	 * @param $plugin_base
	 *
	 * @return bool
	 */
	private function is_disabled_plugin( $plugin_base ) {
		$settings = $this->get_settings();

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
		if ( ! isset( $settings['disabled'] ) || ! isset( $settings['disabled']['plugins'] ) || empty( $settings['disabled']['plugins'] ) ) {
			return false;
		}

		$enabled = array();
		if ( isset( $settings['enabled'] ) && isset( $settings['enabled']['plugins'] ) && ! empty( $settings['enabled']['plugins'] ) ) {
			$enabled = $settings['enabled']['plugins'];
		}

		foreach ( (array) $settings['disabled']['plugins'] as $plugin_slug => $conditions ) {
			$enabled_conditions = isset( $enabled[ $plugin_slug ] ) ? $enabled[ $plugin_slug ] : array();

			if ( $plugin_base_part[0] == $plugin_slug && $this->is_plugin_disabled( $conditions, $enabled_conditions ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.7
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
				if ( $this->set_wp_query() && is_tax() ) {
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
		}

		return true;
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
	private function cache_post_type() {
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

		$data = $this->get_option( 'queryvars' );
		if ( ! empty( $post_type_query_vars ) && ! empty( $queryable_post_types ) ) {
			$data['public_query_vars']    = $public_query_vars;
			$data['post_type_query_vars'] = $post_type_query_vars;
			$data['queryable_post_types'] = $queryable_post_types;
			$data['wp_post_statuses']     = $wp_post_statuses;
			$this->update_option( 'queryvars', $data );
		} else if ( ! empty( $data['post_type_query_vars'] ) || ! empty( $data['queryable_post_types'] ) ) {
			//delete_option($this->prefix . 'queryvars');
			$data['public_query_vars']    = '';
			$data['post_type_query_vars'] = '';
			$data['queryable_post_types'] = '';
			$data['wp_post_statuses']     = '';
			$this->update_option( 'queryvars', $data );
		}
	}

	//parse_request Action Hook for Custom Post Type query add
	private function parse_request( &$args ) {
		if ( did_action( 'plugins_loaded' ) === 0 ) {
			$data = $this->get_option( 'queryvars' );

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
	 * @since  2.1.0
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
	 * @since 2.1.0
	 * @return bool
	 */
	private function doing_ajax() {
		if ( function_exists( 'wp_doing_ajax' ) ) {
			return wp_doing_ajax();
		}

		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * @since 2.1.0
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
