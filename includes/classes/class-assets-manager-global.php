<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets manager base class
 *
 * @author        Alex Kovalev <alex.kovalevv@gmail.com>, Github: https://github.com/alexkovalevv
 * @copyright (c) 05.11.2017, Webcraftic
 * @version       1.0
 */
class WGZ_Assets_Manager_Public {

	/**
	 * Stores list of all available assets (used in rendering panel)
	 *
	 * @var array
	 */
	public $collection = [];

	/**
	 * @param Wbcr_Factory000_Plugin $plugin
	 */
	public function __construct( Wbcr_Factory000_Plugin $plugin ) {
		$this->plugin = $plugin;

		$this->register_hooks();
	}

	/**
	 * Проверяет права пользователя
	 *
	 * Пользователь должен иметь права администратора или суперадминистратора,
	 * чтобы использовать менеджер скриптов.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.1.0
	 * @return bool
	 */
	protected function is_user_can() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_network' );
	}

	/**
	 * Initilize entire machine
	 */
	protected function register_hooks() {
		if ( $this->plugin->getPopulateOption( 'disable_assets_manager', false ) ) {
			return;
		}

		$on_frontend = $this->plugin->getPopulateOption( 'disable_assets_manager_on_front' );
		$on_backend  = $this->plugin->getPopulateOption( 'disable_assets_manager_on_backend', true );
		$is_panel    = $this->plugin->getPopulateOption( 'disable_assets_manager_panel' );

		if ( ( ! is_admin() && ! $on_frontend ) || ( is_admin() && ! $on_backend ) ) {
			add_filter( 'script_loader_src', [ $this, 'filter_load_assets' ], 10, 2 );
			add_filter( 'style_loader_src', [ $this, 'filter_load_assets' ], 10, 2 );
		}

		if ( ! $is_panel && ( ( is_admin() && ! $on_backend ) || ( ! is_admin() && ! $on_frontend ) ) ) {
			if ( ! is_admin() ) {
				add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_plugin_scripts' ], - 100001 );
				add_action( 'wp_footer', [ $this, 'assets_manager_render_template' ], 100001 );
			} else {
				add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_plugin_scripts' ], - 100001 );
				add_action( 'admin_footer', [ $this, 'assets_manager_render_template' ], 100001 );
			}

			add_action( 'wam/views/safe_mode_checkbox', [ $this, 'print_save_mode_fake_checkbox' ] );
		}

		if ( ! is_admin() && ! $on_frontend ) {
			add_action( 'template_redirect', [ $this, 'clean_source_code' ], 9999 );
			add_action( 'wp_head', [ $this, 'collect_assets' ], 10000 );
			add_action( 'wp_footer', [ $this, 'collect_assets' ], 10000 );
		}

		if ( is_admin() && ! $on_backend ) {
			add_action( 'admin_head', [ $this, 'collect_assets' ], 10000 );
			add_action( 'admin_footer', [ $this, 'collect_assets' ], 10000 );
		}

		if ( ! $is_panel && ( ( is_admin() && ! $on_backend ) || ( ! is_admin() && ! $on_frontend ) ) ) {
			if ( defined( 'LOADING_ASSETS_MANAGER_AS_ADDON' ) ) {
				add_action( 'wbcr/clearfy/adminbar_menu_items', [ $this, 'clearfy_admin_bar_menu_filter' ] );
			} else {
				add_action( 'admin_bar_menu', [ $this, 'assets_manager_add_admin_bar_menu' ], 1000 );
			}
		}

		##Login/Logout
		add_action( 'wp_login', [ $this, 'user_logged_in' ], 99, 2 );
		add_action( 'wp_logout', [ $this, 'user_logged_out' ] );

		// Stop optimizing scripts and caching the asset manager page.
		add_action( 'wp', [ $this, 'stop_caching_and_script_optimize' ] );

		// Disable autoptimize on Assets manager page
		add_filter( 'autoptimize_filter_noptimize', [ $this, 'autoptimize_noptimize' ], 10, 0 );
		add_filter( 'wmac_filter_noptimize', [ $this, 'autoptimize_noptimize' ], 10, 0 );

		if ( wp_doing_ajax() ) {
			require_once WGZ_PLUGIN_DIR . '/admin/ajax/save-settings.php';
		}
	}

	public function print_save_mode_fake_checkbox( $data ) {
		if ( defined( 'WGZP_PLUGIN_ACTIVE' ) ) {
			return;
		}
		?>
        <label class="wam-float-panel__checkbox  wam-tooltip  wam-tooltip--bottom" data-tooltip="<?php _e( 'In test mode, you can experiment with disabling unused scripts safely for your site. The resources that you disabled will be visible only to you (the administrator), and all other users will receive an unoptimized version of the site, until you remove this tick', 'gonzales' ) ?>.">
            <input class="wam-float-panel__checkbox-input visually-hidden" type="checkbox">
            <span class="wam-float-panel__checkbox-text-premium"><?php _e( 'Safe mode <b>PRO</b>', 'gonzales' ) ?></span>
        </label>
		<?php
	}

	/**
	 * Записываем cookie с ролями пользователя
	 *
	 * Это нужно для идентификации в MU плагине, так как мы не можем использовать
	 * большинство функций wordpress.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 *
	 * @param string $login
	 * @param string $user
	 */
	public function user_logged_in( $login, $user = null ) {
		if ( is_null( $user ) ) {
			$user = wp_get_current_user();
		}

		foreach ( $user->roles as $key => $role ) {
			setcookie( 'wam_assigned_roles[' . $key . ']', $role, 0, "/" );
		}
	}

	/**
	 * Удаляем cookie с ролями
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 */
	public function user_logged_out() {
		if ( isset( $_COOKIE['wam_assigned_roles'] ) && is_array( $_COOKIE['wam_assigned_roles'] ) ) {
			foreach ( $_COOKIE['wam_assigned_roles'] as $key => $cookie_val ) {
				setcookie( 'wam_assigned_roles[' . $key . ']', '', time() - 999999, "/" );
			}
		}
	}

	/**
	 * Stop optimizing scripts and caching the asset manager page.
	 *
	 * For some types of pages it is imperative to not be cached. Think of an e-commerce scenario:
	 * when a customer enters checkout, they wouldn’t want to see a cached page with some previous
	 * customer’s payment data.
	 *
	 * Elaborate plugins like WooCommerce (and many others) use the DONOTCACHEPAGE constant to let
	 * caching plugins know about certain pages or endpoints that should not be cached in any case.
	 * Accordingly, all popular caching plugins, including WP Rocket, support the constant and would
	 * not cache a request for which DONOTCACHEPAGE is defined as true.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.8
	 */
	public function stop_caching_and_script_optimize() {
		if ( ! isset( $_GET['wbcr_assets_manager'] ) ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! defined( 'DONOTCACHCEOBJECT' ) ) {
			define( 'DONOTCACHCEOBJECT', true );
		}

		if ( ! defined( 'DONOTMINIFY' ) ) {
			define( 'DONOTMINIFY', true );
		}
	}

	/**
	 * Disable autoptimize on Assets manager page
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.8
	 * @return bool
	 */
	public function autoptimize_noptimize() {
		if ( ! isset( $_GET['wbcr_assets_manager'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * We remove scripts and styles of themes, plugins to avoidE
	 * unnecessary conflicts during the use of the asset manager.
	 *
	 * todo: the method requires better study. Sorry, I don't have time for this.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.0.8
	 */
	public function clean_source_code() {
		if ( ! isset( $_GET['wbcr_assets_manager'] ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return false;
		}

		ob_start( function ( $html ) {

			$raw_html = $html;

			$html = preg_replace( [
				"'<\s*style.*?<\s*/\s*style\s*>'is",
			], [
				""
			], $html );

			$html = preg_replace_callback( [
				"'<\s*link.*?>'is",
			], function ( $matches ) {
				$doc = new DOMDocument();
				$doc->loadHTML( $matches[0] );
				$imageTags = $doc->getElementsByTagName( 'link' );

				foreach ( $imageTags as $tag ) {
					$src = $tag->getAttribute( 'href' );

					$white_list_js = [
						'wp-includes/css/dashicons.min.css',
						'wp-includes/css/admin-bar.min.css',
						// --
						'assets-manager/assets/css/assets-manager.css',
						'assets-manager-premium/assets/css/assets-manager.css',
						'assets-manager-premium-premium/assets/css/assets-manager.css',
						// --
						'assets-manager/assets/css/assets-conditions.css',
						'assets-manager-premium/assets/css/assets-conditions.css',
						'assets-manager-premium-premium/assets/css/assets-conditions.css',
						// --
						'clearfy/assets/css/admin-bar.css',
						// --
						'assets-manager/assets/css/PNotifyBrightTheme.css',
						'assets-manager-premium/assets/css/PNotifyBrightTheme.css',
						'assets-manager-premium-premium/assets/css/PNotifyBrightTheme.css',

					];

					if ( ! empty( $src ) ) {
						foreach ( $white_list_js as $js ) {
							if ( false !== strpos( $src, $js ) ) {
								return $matches[0];
							}
						}
					}

					return '';
				}
			}, $html );

			$html = preg_replace_callback( [
				"'<\s*script.*?<\s*\/\s*script\s*>'is",
			], function ( $matches ) {
				if ( false !== strpos( $matches[0], 'wam_localize_data' ) ) {
					return $matches[0];
				}
				if ( false !== strpos( $matches[0], 'wam-conditions-builder-template' ) ) {
					return $matches[0];
				}

				$doc = new DOMDocument();
				$doc->loadHTML( $matches[0] );
				$imageTags = $doc->getElementsByTagName( 'script' );

				foreach ( $imageTags as $tag ) {
					$src = $tag->getAttribute( 'src' );

					$white_list_js = [
						'wam-jquery.js',
						'wam-jquery-migrate.min.js',
						'wp-includes/js/admin-bar.min.js',
						// --
						'assets-manager/assets/js/assets-manager.js',
						'assets-manager-premium/assets/js/assets-manager.js',
						'assets-manager-premium-premium/assets/js/assets-manager.js',
						// --
						'assets-manager/assets/js/assets-conditions.js',
						'assets-manager-premium/assets/js/assets-conditions.js',
						'assets-manager-premium-premium/assets/js/assets-conditions.js',
						// --
						'assets-manager/assets/js/PNotify.js',
						'assets-manager-premium/assets/js/PNotify.js',
						'assets-manager-premium-premium/assets/js/PNotify.js',

					];

					if ( ! empty( $src ) ) {
						foreach ( $white_list_js as $js ) {
							if ( false !== strpos( $src, $js ) ) {
								return $matches[0];
							}
						}
					}

					return '';
				}
				//return $matches[0];
			}, $html );

			if ( empty( $html ) ) {
				return $raw_html;
			}

			return $html;
		} );
	}

	/**
	 * Добавляем ссылку для перехода к менджеру в меню Clearfy (которое в админбаре)
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.1.0
	 *
	 * @param array $menu_items   Массив ссылок из меню Clearfy
	 *
	 * @return mixed
	 */
	function clearfy_admin_bar_menu_filter( $menu_items ) {
		//todo: Закрыть функциональность для админки
		if ( is_admin() ) {
			return $menu_items;
		}

		$current_url = add_query_arg( [ 'wbcr_assets_manager' => 1 ] );

		$menu_items['assets_manager_render_template'] = [
			'title' => '<span class="dashicons dashicons-list-view"></span> ' . __( 'Assets Manager', 'gonzales' ),
			'href'  => $current_url
		];

		return $menu_items;
	}

	/**
	 * Добавляем меню для перехода к менджеру в админбар
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  1.1.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	function assets_manager_add_admin_bar_menu( $wp_admin_bar ) {
		//todo: Закрыть функциональность для админки
		if ( ! $this->is_user_can() || is_admin() ) {
			return;
		}

		$current_url = add_query_arg( [ 'wbcr_assets_manager' => 1 ] );

		$args = [
			'id'    => 'assets_manager_render_template',
			'title' => __( 'Assets Manager', 'gonzales' ),
			'href'  => $current_url
		];
		$wp_admin_bar->add_node( $args );
	}

	/**
	 * Печатает шаблон менеджера скриптов в теле страницы
	 *
	 * Это функция обратного вызова, для хуков admin_footer,
	 * wp_footer
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 * @throws \Exception
	 */
	function assets_manager_render_template() {
		if ( ! $this->is_user_can() || ! isset( $_GET['wbcr_assets_manager'] ) ) {
			return;
		}

		// Reset settings
		if ( isset( $_GET['wam_reset_settings'] ) ) {
			check_admin_referer( 'wam_reset_settings' );
			$this->plugin->updateOption( 'assets_states', [] );
			wp_redirect( untrailingslashit( $this->get_current_url() ) . '?wbcr_assets_manager' );
			die();
		}

		$settings = $this->plugin->getOption( 'assets_states', [] );

		$views = new WGZ_Views( WGZ_PLUGIN_DIR );
		$views->print_template( 'assets-manager', [
			'current_url'             => esc_url( $this->get_current_url() ),
			'save_mode'               => isset( $settings['save_mode'] ) ? (bool) $settings['save_mode'] : false,
			'collection'              => $this->collection,
			'loaded_plugins'          => $this->get_loaded_plugins(),
			'theme_assets'            => $this->get_collected_assets( 'theme' ),
			'misc_assets'             => $this->get_collected_assets( 'misc' ),
			'conditions_logic_params' => $this->get_conditions_login_params( true ),
			'settings'                => $settings
		] );

		$this->print_plugin_scripts();
	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 *
	 * @param $src
	 * @param $handle
	 *
	 * @return mixed
	 */
	function filter_load_assets( $src, $handle ) {
		$settings = $this->plugin->getOption( 'assets_states', [] );

		if ( isset( $_GET['wbcr_assets_manager'] ) || empty( $settings ) || ( true === $settings['save_mode'] && ! $this->is_user_can() ) ) {
			return $src;
		}

		require_once WGZ_PLUGIN_DIR . '/includes/classes/class-check-conditions.php';

		$resource_type       = ( current_filter() == 'script_loader_src' ) ? 'js' : 'css';
		$resource_visability = "";

		if ( ! empty( $settings['plugins'] ) ) {
			foreach ( (array) $settings['plugins'] as $plugin_name => $plugin ) {
				if ( ! empty( $plugin[ $resource_type ] ) && isset( $plugin[ $resource_type ][ $handle ] ) ) {
					if ( 'disable_assets' === $plugin['load_mode'] ) {
						$resource_visability = $plugin['visability'];
					} else if ( 'disable_plugin' === $plugin['load_mode'] ) {
						return $src;
					} else {
						$resource_visability = $plugin[ $resource_type ][ $handle ]['visability'];
					}
					break;
				}
			}
		}

		foreach ( [ 'theme', 'misc' ] as $group_name ) {
			if ( ! empty( $settings[ $group_name ] ) && ! empty( $settings[ $group_name ][ $resource_type ] ) && isset( $settings[ $group_name ][ $resource_type ][ $handle ] ) ) {
				$resource_visability = $settings[ $group_name ][ $resource_type ][ $handle ]['visability'];
				break;
			}
		}

		if ( ! empty( $resource_visability ) ) {
			$condition = new WGZ_Check_Conditions( $resource_visability );
			if ( $condition->validate() ) {
				return false;
			}
		}

		return $src;
	}

	/**
	 * Get information regarding used assets
	 *
	 * @return bool
	 */
	public function collect_assets() {
		if ( ! isset( $_GET['wbcr_assets_manager'] ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return false;
		}

		$denied = [
			'js'  => [ 'wam-assets-manager', 'wam-assets-conditions', 'admin-bar', 'wam-pnotify' ],
			'css' => [
				'wam-pnotify',
				'wbcr-clearfy-adminbar-styles',
				'wam-assets-conditions',
				'wam-assets-manager',
				'admin-bar',
				'dashicons'
			],
		];
		$denied = apply_filters( 'wbcr_gnz_denied_assets', $denied );

		/**
		 * Imitate full untouched list without dequeued assets
		 * Appends part of original table. Safe approach.
		 */
		$data_assets = [
			'js'  => wp_scripts(),
			'css' => wp_styles(),
		];

		foreach ( $data_assets as $type => $data ) {
			foreach ( $data->done as $el ) {
				if ( isset( $data->registered[ $el ] ) ) {

					if ( ! in_array( $el, $denied[ $type ] ) ) {
						if ( isset( $data->registered[ $el ]->src ) ) {
							$url       = $this->prepare_url( $data->registered[ $el ]->src );
							$url_short = str_replace( get_home_url(), '', $url );

							if ( false !== strpos( $url, get_theme_root_uri() ) ) {
								$resource_type = 'theme';
							} else if ( false !== strpos( $url, plugins_url() ) ) {
								$resource_type = 'plugins';
							} else {
								$resource_type = 'misc';
							}

							$resource_name = '';
							if ( 'plugins' == $resource_type ) {
								$clean_url     = str_replace( WP_PLUGIN_URL . '/', '', $url );
								$url_parts     = explode( '/', $clean_url );
								$resource_name = isset( $url_parts[0] ) ? $url_parts[0] : '';
							}

							if ( ! isset( $this->collection[ $resource_type ][ $resource_name ][ $type ][ $el ] ) ) {
								$this->collection[ $resource_type ][ $resource_name ][ $type ][ $el ] = [
									'url_full'  => $url,
									'url_short' => $url_short,
									//'state' => $this->get_visibility($type, $el),
									'size'      => $this->get_asset_size( $url ),
									'ver'       => $data->registered[ $el ]->ver,
									'deps'      => ( isset( $data->registered[ $el ]->deps ) ? $data->registered[ $el ]->deps : [] ),
								];

								# Deregister scripts, styles so that they do not conflict with assets managers.
								# ------------------------------------------------
								$no_js = [
									'jquery',
									'jquery-core',
									'jquery-migrate',
									'jquery-ui-core',
									'wam-jquery-core',
									'wam-jquery-migrate'
								];

								if ( "js" == $type && ! in_array( $el, $no_js ) ) {
									wp_deregister_script( $el );
								}

								if ( "css" == $type ) {
									wp_deregister_style( $el );
								}
								#-------------------------------------------------
							}
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * Подключаем скрипты и стили плагина
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 */
	public function enqueue_plugin_scripts() {
		if ( $this->is_user_can() && isset( $_GET['wbcr_assets_manager'] ) ) {
			$plugin_ver = $this->plugin->getPluginVersion();

			wp_enqueue_style( 'wam-assets-manager', WGZ_PLUGIN_URL . '/assets/css/assets-manager.css', [], $plugin_ver );
			wp_enqueue_style( 'wam-assets-conditions', WGZ_PLUGIN_URL . '/assets/css/assets-conditions.css', [], $plugin_ver );
			wp_enqueue_style( 'wam-pnotify', WGZ_PLUGIN_URL . '/assets/css/PNotifyBrightTheme.css', [], $plugin_ver );

			// Фикс для рукожопов, которые отключают jquery из ядра
			/*if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery', '/wp-includes/js/jquery/jquery.js', [], '1.12.4-wp' );
			}*/
			/*wp_enqueue_script( 'wam-pnotify', WGZ_PLUGIN_URL . '/assets/js/PNotify.js', [], $plugin_ver, true );
			wp_enqueue_script( 'wam-assets-conditions', WGZ_PLUGIN_URL . '/assets/js/assets-conditions.js', [ 'jquery' ], $plugin_ver, true );
			wp_enqueue_script( 'wam-assets-manager', WGZ_PLUGIN_URL . '/assets/js/assets-manager.js', [
				'jquery',
				'wam-assets-conditions'
			], $plugin_ver, true );

			wp_localize_script( 'wam-assets-manager', 'wam_localize_data', [
				'ajaxurl' => admin_url( 'admin-ajax.php', is_ssl() ? 'admin' : 'http' )
			] );*/
		}
	}

	/**
	 * Hardcode? Because, other plugins disable scripts or manipulate them.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 */
	public function print_plugin_scripts() {
		?>
        <script>
			var wam_localize_data = <?php echo json_encode( [
				'ajaxurl' => admin_url( 'admin-ajax.php', is_ssl() ? 'admin' : 'http' )
			] ) ?>;
        </script>
        <script type='text/javascript' src='<?php echo WGZ_PLUGIN_URL . '/assets/js/wam-jquery.js'; ?>'></script>
        <script type='text/javascript' src='<?php echo WGZ_PLUGIN_URL . '/assets/js/wam-jquery-migrate.min.js'; ?>'></script>
        <script type='text/javascript' src='<?php echo WGZ_PLUGIN_URL . '/assets/js/PNotify.js'; ?>'></script>
        <script type='text/javascript' src='<?php echo WGZ_PLUGIN_URL . '/assets/js/assets-conditions.js'; ?>'></script>
        <script type='text/javascript' src='<?php echo WGZ_PLUGIN_URL . '/assets/js/assets-manager.js'; ?>'></script>
		<?php
	}


	private function get_collected_assets( $type ) {
		$assets = [];

		if ( empty( $this->collection ) ) {
			return $assets;
		}

		foreach ( (array) $this->collection as $resource_type => $resources ) {
			if ( $type == $resource_type ) {
				foreach ( $resources as $resource_name => $types ) {
					$assets = $this->get_parsed_asset_settings( $types, $resource_type );
				}
			}
		}

		return $assets;
	}

	/**
	 * Позволяет получить список плагинов, которые загружаются на странице
	 *
	 * Каждый элемент списка имеет собственные настройки, которые будут
	 * переданы в шаблон для печати.
	 *
	 * @since  2.0.0
	 * @return array
	 * @throws \Exception
	 */
	private function get_loaded_plugins() {
		$plugins = [];

		if ( empty( $this->collection ) ) {
			return $plugins;
		}

		foreach ( (array) $this->collection as $resource_type => $resources ) {
			foreach ( $resources as $resource_name => $types ) {
				if ( 'plugins' == $resource_type && ! empty( $resource_name ) ) {
					$plugins[ $resource_name ]['name']                    = $resource_name;
					$plugins[ $resource_name ]['info']                    = $this->get_plugin_data( $resource_name );
					$plugins[ $resource_name ]['assets']                  = $this->get_parsed_asset_settings( $types, 'plugins', $resource_name );
					$plugins[ $resource_name ]['load_mode']               = $this->get_parsed_plugin_settings( $resource_name, 'load_mode' );
					$plugins[ $resource_name ]['visability']              = $this->get_parsed_plugin_settings( $resource_name, 'visability' );
					$plugins[ $resource_name ]['select_control_classes']  = $this->get_parsed_plugin_settings( $resource_name, 'select_control_classes' );
					$plugins[ $resource_name ]['settings_button_classes'] = $this->get_parsed_plugin_settings( $resource_name, 'settings_button_classes' );
				}
			}
		}

		return $plugins;
	}

	/**
	 * Подготовка настроек плагина к выводу в шаблоне
	 *
	 * Устанавливаем ключи и значения по умолчанию или берем сохраненные
	 * значения из базы данных. Тем самым мы гарантируем, что в шаблоне
	 * всегда будет существовать используемый элемент массива из настроек
	 * плагина.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 *
	 * @param string $plugin_name    Имя плагина, для которого подготавливаются настройки
	 * @param null   $setting_name   Имя настройки, заполняется, если нужно извлечь только
	 *                               1 конкретную настройку
	 *
	 * @return array|mixed
	 * @throws \Exception
	 */
	private function get_parsed_plugin_settings( $plugin_name, $setting_name = null ) {
		$settings         = $this->plugin->getOption( 'assets_states', [] );
		$default_settings = [
			'load_mode'               => 'enable',
			'visability'              => "",
			'js'                      => [],
			'css'                     => [],
			'select_control_classes'  => " js-wam-select--enable",
			'settings_button_classes' => " js-wam-button--hidden",
		];

		$settings_formated = $default_settings;

		if ( ! empty( $settings['plugins'] ) && isset( $settings['plugins'][ $plugin_name ] ) ) {
			$plugin_settings                 = $settings['plugins'][ $plugin_name ];
			$settings_formated['load_mode']  = ! empty( $plugin_settings['load_mode'] ) ? $plugin_settings['load_mode'] : "enable";
			$settings_formated['visability'] = ! empty( $plugin_settings['visability'] ) ? stripslashes( $plugin_settings['visability'] ) : "";
			$settings_formated['js']         = ! empty( $plugin_settings['js'] ) ? $plugin_settings['js'] : "";
			$settings_formated['css']        = ! empty( $plugin_settings['css'] ) ? $plugin_settings['css'] : "";

			if ( "enable" === $settings_formated['load_mode'] ) {
				$settings_formated['select_control_classes']  = " js-wam-select--enable";
				$settings_formated['settings_button_classes'] = " js-wam-button--hidden";
			} else {
				$settings_formated['select_control_classes']  = " js-wam-select--disable";
				$settings_formated['settings_button_classes'] = "";
			}
		}

		if ( $setting_name && isset( $settings_formated[ $setting_name ] ) ) {
			return $settings_formated[ $setting_name ];
		}

		return $settings_formated;
	}

	/**
	 * Подготовка настроек ресурсов к выводу в шаблоне
	 *
	 * Устанавливаем ключи и значения по умолчанию или берем сохраненные
	 * значения из базы данных. Тем самым мы гарантируем, что в шаблоне
	 * всегда будет существовать используемый элемент массива из настроек
	 * ресурсов.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 *
	 * @param array  $assets        Массив с загружаемыми ресурсами, к которому будут
	 *                              добавлены настройки по умолчанию и сохраненные настройки
	 * @param string $plugin_name   Имя плагина, если нужно сфокусироваться на группе ресурсов,
	 *                              которые относятся к определенному плагину
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function get_parsed_asset_settings( array $assets, $group_name, $plugin_name = null ) {
		$plugin_group      = false;
		$settings_formated = [];
		$settings          = $this->plugin->getOption( 'assets_states', [] );

		if ( ! isset( $assets['js'] ) ) {
			$assets['js'] = [];
		}
		if ( ! isset( $assets['css'] ) ) {
			$assets['css'] = [];
		}

		if ( ! empty( $settings[ $group_name ] ) ) {
			if ( ! empty( $plugin_name ) ) {
				$settings     = isset( $settings[ $group_name ][ $plugin_name ] ) ? $settings[ $group_name ][ $plugin_name ] : [];
				$plugin_group = true;
			} else if ( 'plugins' !== $group_name ) {
				$settings = $settings[ $group_name ];
			}
		}

		foreach ( (array) $assets as $type => $resources ) {
			$settings_formated[ $type ] = [];

			foreach ( (array) $resources as $name => $attrs ) {
				$s = &$settings_formated[ $type ][ $name ];

				if ( isset( $settings[ $type ] ) && isset( $settings[ $type ][ $name ] ) && ! empty( $settings[ $type ][ $name ]['visability'] ) ) {
					$s['load_mode']  = "disable";
					$s['visability'] = stripslashes( $settings[ $type ][ $name ]['visability'] );
				} else {
					if ( $plugin_group ) {
						$plugin_load_mode = ! empty( $settings['load_mode'] ) ? $settings['load_mode'] : 'enable';

						$s['load_mode'] = "enable" === $plugin_load_mode ? 'enable' : 'disable';
					} else {
						$s['load_mode'] = "enable";
					}
					$s['visability'] = "";
				}

				if ( 'disable' === $s['load_mode'] ) {
					$s['row_classes']             = " js-wam-table__tr--disabled-section";
					$s['select_control_classes']  = " js-wam-select--disable";
					$s['settings_button_classes'] = "";

					if ( $plugin_load_mode && 'enable' !== $plugin_load_mode ) {
						$s['settings_button_classes'] = " js-wam-button--hidden";
					}
				} else {
					$s['row_classes']             = "";
					$s['select_control_classes']  = " js-wam-select--enable";
					$s['settings_button_classes'] = " js-wam-button--hidden";
				}

				$s = array_merge( $s, $attrs );
			}
		}

		return $settings_formated;
	}

	/**
	 * Get plugin data from folder name
	 *
	 * @param $name
	 *
	 * @return array
	 */
	private function get_plugin_data( $name ) {
		$data = [];

		if ( $name ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				// подключим файл с функцией get_plugins()
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins = get_plugins();
			if ( ! empty( $all_plugins ) ) {
				foreach ( $all_plugins as $plugin_path => $plugin_data ) {
					if ( strpos( $plugin_path, $name . '/' ) !== false ) {
						$data             = $plugin_data;
						$data['basename'] = $plugin_path;
						break;
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Exception for address starting from "//example.com" instead of
	 * "http://example.com". WooCommerce likes such a format
	 *
	 * @param string $url   Incorrect URL.
	 *
	 * @return string      Correct URL.
	 */
	private function prepare_url( $url ) {
		if ( isset( $url[0] ) && isset( $url[1] ) && '/' == $url[0] && '/' == $url[1] ) {
			$out = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		} else {
			$out = $url;
		}

		return $out;
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
	 * Checks how heavy is file
	 *
	 * @param string $src   URL.
	 *
	 * @return int    Size in KB.
	 */
	private function get_asset_size( $src ) {
		$weight = 0;

		$home = get_theme_root() . '/../..';
		$src  = explode( '?', $src );

		if ( ! filter_var( $src[0], FILTER_VALIDATE_URL ) === false && strpos( $src[0], get_home_url() ) === false ) {
			return 0;
		}

		$src_relative = $home . str_replace( get_home_url(), '', $this->prepare_url( $src[0] ) );

		if ( file_exists( $src_relative ) ) {
			$weight = round( filesize( $src_relative ) / 1024, 1 );
		}

		return $weight;
	}

	private function get_conditions_login_params( $group = false ) {
		global $wp_roles, $wp;

		# Add User Roles
		#---------------------------------------------------------------
		$all_roles          = $wp_roles->roles;
		$editable_roles     = apply_filters( 'editable_roles', $all_roles );
		$roles_param_values = [
			[
				'value' => 'guest',
				'title' => __( 'Guest', 'insert-php' ),
			]
		];

		if ( ! empty( $editable_roles ) ) {
			foreach ( $editable_roles as $role_ID => $role ) {
				$roles_param_values[] = [ 'value' => $role_ID, 'title' => $role['name'] ];
			}
		}

		# Add Post Types
		#---------------------------------------------------------------
		$post_types              = get_post_types( [
			'public' => true
		], 'objects' );
		$post_types_param_values = [];

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $type ) {
				if ( isset( $type->name ) ) {
					$post_types_param_values[] = [ 'value' => $type->name, 'title' => $type->label ];
				}
			}
		}

		# Add Taxonomies
		#---------------------------------------------------------------
		$taxonomies              = get_taxonomies( [
			'public' => true
		], 'objects' );
		$taxonomies_param_values = [];

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax ) {
				$taxonomies_param_values[] = [ 'value' => $tax->name, 'title' => $tax->label ];
			}
		}

		$pro_label = ! defined( 'WGZP_PLUGIN_ACTIVE' ) ? ' (Pro)' : '';

		$grouped_filter_params = [
			[
				'id'    => 'user',
				'title' => __( 'User', 'gonzales' ),
				'items' => [
					[
						'id'          => 'user-role',
						'title'       => __( 'Role', 'gonzales' ) . $pro_label,
						'type'        => 'select',
						'params'      => $roles_param_values,
						'description' => __( 'A role of the user who views your website. The role "guest" is applied to unregistered users.', 'gonzales' ),
						'disabled'    => ! defined( 'WGZP_PLUGIN_ACTIVE' )
					],
					/*[
						'id'          => 'user-registered',
						'title'       => __( 'Registration Date', 'gonzales' ),
						'type'        => 'date',
						'description' => __( 'The date when the user who views your website was registered. For unregistered users this date always equals to 1 Jan 1970.', 'gonzales' )
					],*/
					[
						'id'          => 'user-mobile',
						'title'       => __( 'Mobile Device', 'gonzales' ) . $pro_label,
						'type'        => 'select',
						'params'      => [
							[ 'value' => 'yes', 'title' => __( 'Yes', 'gonzales' ) ],
							[ 'value' => 'no', 'title' => __( 'No', 'gonzales' ) ]
						],
						'description' => __( 'Determines whether the user views your website from mobile device or not.', 'gonzales' ),
						'disabled'    => ! defined( 'WGZP_PLUGIN_ACTIVE' )
					],
					[
						'id'          => 'user-cookie-name',
						'title'       => __( 'Cookie Name', 'gonzales' ) . $pro_label,
						'type'        => 'text',
						'only_equals' => true,
						'description' => __( 'Determines whether the user\'s browser has a cookie with a given name.', 'gonzales' ),
						'disabled'    => ! defined( 'WGZP_PLUGIN_ACTIVE' )
					]
				]
			],
			[
				'id'    => 'location',
				'title' => __( 'Location', 'gonzales' ),
				'items' => [
					[
						'id'            => 'current-url',
						'title'         => __( 'Current URL', 'gonzales' ),
						'type'          => 'default',
						'default_value' => ( "/" === $this->get_current_url() ? "/" : trailingslashit( $this->get_current_url() ) ),
						'description'   => __( 'Current Url', 'gonzales' )
					],
					[
						'id'          => 'location-page',
						'title'       => __( 'Custom URL', 'gonzales' ) . $pro_label,
						'type'        => 'text',
						'description' => __( 'An URL of the current page where a user who views your website is located.', 'gonzales' ),
						'disabled'    => ! defined( 'WGZP_PLUGIN_ACTIVE' )
					],
					[
						'id'          => 'regular-expression',
						'title'       => __( 'Regular Expression', 'gonzales' ) . $pro_label,
						'type'        => 'regexp',
						'placeholder' => '^(about-page-[0-9]+|contacts-[0-9]{,2})',
						'description' => __( 'Regular expressions can be used by experts. This tool creates flexible conditions to disable the resource. For example, if you specify this expression: ^([A-z0-9]+-)?gifts? then the resource will be disabled at the following pages http://yoursite.test/get-gift/, http://yoursite.test/gift/, http://yoursite.test/get-gifts/, http://yoursite.test/gifts/. The plugin ignores the backslash at the beginning of the query string, so you can dismiss it. Check your regular expressions in here: https://regex101.com, this will prevent you from the mistakes. This feature is available at the paid version.', 'gonzales' ),
						'disabled'    => ! defined( 'WGZP_PLUGIN_ACTIVE' )
					],
					[
						'id'          => 'location-some-page',
						'title'       => __( 'Page', 'gonzales' ),
						'type'        => 'select',
						'params'      => [
							'Basic'         => [
								[
									'value' => 'base_web',
									'title' => __( 'Entire Website', 'insert-php' ),
								],
								[
									'value' => 'base_sing',
									'title' => __( 'All Singulars', 'insert-php' ),
								],
								[
									'value' => 'base_arch',
									'title' => __( 'All Archives', 'insert-php' ),
								],
							],
							'Special Pages' => [
								[
									'value' => 'spec_404',
									'title' => __( '404 Page', 'insert-php' )
								],
								[
									'value' => 'spec_search',
									'title' => __( 'Search Page', 'insert-php' )
								],
								[
									'value' => 'spec_blog',
									'title' => __( 'Blog / Posts Page', 'insert-php' )
								],
								[
									'value' => 'spec_front',
									'title' => __( 'Front Page', 'insert-php' )
								],
								[
									'value' => 'spec_date',
									'title' => __( 'Date Archive', 'insert-php' )
								],
								[
									'value' => 'spec_auth',
									'title' => __( 'Author Archive', 'insert-php' )
								],
							],
							'Posts'         => [
								[
									'value' => 'post_all',
									'title' => __( 'All Posts', 'insert-php' )
								],
								[
									'value' => 'post_arch',
									'title' => __( 'All Posts Archive', 'insert-php' )
								],
								[
									'value' => 'post_cat',
									'title' => __( 'All Categories Archive', 'insert-php' )
								],
								[
									'value' => 'post_tag',
									'title' => __( 'All Tags Archive', 'insert-php' )
								],
							],
							'Pages'         => [
								[
									'value' => 'page_all',
									'title' => __( 'All Pages', 'insert-php' )
								],
								[
									'value' => 'page_arch',
									'title' => __( 'All Pages Archive', 'insert-php' )
								],
							],

						],
						'description' => __( 'List of specific pages.', 'gonzales' )
					],
					[
						'id'          => 'location-post-type',
						'title'       => __( 'Post type', 'gonzales' ),
						'type'        => 'select',
						'params'      => $post_types_param_values,
						'description' => __( 'A post type of the current page.', 'gonzales' ),
					],
					[
						'id'          => 'location-taxonomy',
						'title'       => __( 'Taxonomy', 'gonzales' ),
						'type'        => 'select',
						'params'      => $taxonomies_param_values,
						'description' => __( 'A taxonomy of the current page.', 'gonzales' ),
					]
				]
			]
		];

		$filterParams = [];
		foreach ( (array) $grouped_filter_params as $filter_group ) {
			$filterParams = array_merge( $filterParams, $filter_group['items'] );
		}

		return $group ? $grouped_filter_params : $filterParams;
	}
}