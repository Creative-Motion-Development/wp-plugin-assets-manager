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
class WGZ_ConfigAssetsManager extends Wbcr_FactoryClearfy000_Configurate {

	/**
	 * Stores list of all available assets (used in rendering panel)
	 *
	 * @var array
	 */
	public $collection = [];

	/**
	 * Plugins for additional columns
	 *
	 * @var array
	 */
	private $sided_plugins = [];

	/**
	 * Css and js files excluded in sided plugins
	 *
	 * @var array
	 */
	private $sided_plugin_files = [];

	/**
	 * @var bool
	 */
	private $is_user_can;

	/**
	 * @param Wbcr_Factory000_Plugin $plugin
	 */
	public function __construct( Wbcr_Factory000_Plugin $plugin ) {
		parent::__construct( $plugin );
		$this->plugin = $plugin;
	}

	protected function isUserCan() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_network' );
	}

	/**
	 * Initilize entire machine
	 */
	protected function registerActionsAndFilters() {
		if ( $this->getPopulateOption( 'disable_assets_manager', false ) ) {
			return;
		}

		$on_frontend = $this->getPopulateOption( 'disable_assets_manager_on_front' );
		$on_backend  = $this->getPopulateOption( 'disable_assets_manager_on_backend', true );
		$is_panel    = $this->getPopulateOption( 'disable_assets_manager_panel' );

		if ( ( ! is_admin() && ! $on_frontend ) || ( is_admin() && ! $on_backend ) ) {
			add_filter( 'script_loader_src', [ $this, 'unloadAssets' ], 10, 2 );
			add_filter( 'style_loader_src', [ $this, 'unloadAssets' ], 10, 2 );
		}

		if ( ! $is_panel && ( ( is_admin() && ! $on_backend ) || ( ! is_admin() && ! $on_frontend ) ) ) {
			if ( ! is_admin() ) {
				add_action( 'wp_enqueue_scripts', [ $this, 'appendAsset' ], - 100001 );
				add_action( 'wp_footer', [ $this, 'assetsManager' ], 100001 );
			} else {
				add_action( 'admin_enqueue_scripts', [ $this, 'appendAsset' ], - 100001 );
				add_action( 'admin_footer', [ $this, 'assetsManager' ], 100001 );
			}
		}

		if ( ! is_admin() && ! $on_frontend ) {
			add_action( 'template_redirect', [ $this, 'clean_source_code' ], 9999 );
			add_action( 'wp_head', [ $this, 'collectAssets' ], 10000 );
			add_action( 'wp_footer', [ $this, 'collectAssets' ], 10000 );
		}

		if ( is_admin() && ! $on_backend ) {
			add_action( 'admin_head', [ $this, 'collectAssets' ], 10000 );
			add_action( 'admin_footer', [ $this, 'collectAssets' ], 10000 );
		}

		if ( ! $is_panel && ( ( is_admin() && ! $on_backend ) || ( ! is_admin() && ! $on_frontend ) ) ) {
			if ( defined( 'LOADING_ASSETS_MANAGER_AS_ADDON' ) ) {
				add_action( 'wbcr/clearfy/adminbar_menu_items', [ $this, 'clearfyAdminBarMenu' ] );
			} else {
				add_action( 'admin_bar_menu', [ $this, 'assetsManagerAdminBar' ], 1000 );
			}
		}

		if ( ! is_admin() && ! $on_frontend ) {
			add_action( 'init', [ $this, 'formSave' ] );
		}

		if ( is_admin() && ! $on_backend ) {
			add_action( 'admin_init', [ $this, 'formSave' ] );
		}

		add_action( 'plugins_loaded', [ $this, 'pluginsLoaded' ] );
		add_action( 'wbcr_gnz_form_save', [ $this, 'actionFormSave' ] );

		add_filter( 'wbcr_gnz_unset_disabled', [ $this, 'unsetDisabled' ], 10, 2 );
		add_filter( 'wbcr_gnz_get_additional_head_columns', [ $this, 'getAdditionalHeadColumns' ] );
		add_filter( 'wbcr_gnz_get_additional_controls_columns', [ $this, 'getAdditionalControlsColumns' ], 10, 4 );

		add_filter( 'autoptimize_filter_js_exclude', [ $this, 'aoptFilterJsExclude' ], 10, 2 );
		add_filter( 'autoptimize_filter_css_exclude', [ $this, 'aoptFilterCssExclude' ], 10, 2 );
		add_filter( 'wmac_filter_js_exclude', [ $this, 'wmacFilterJsExclude' ], 10, 2 );
		add_filter( 'wmac_filter_css_exclude', [ $this, 'wmacFilterCssExclude' ], 10, 2 );
		add_filter( 'wmac_filter_js_minify_excluded', [ $this, 'wmacFilterJsMinifyExclude' ], 10, 2 );
		add_filter( 'wmac_filter_css_minify_excluded', [ $this, 'wmacFilterCssMinifyExclude' ], 10, 2 );

		// Stop optimizing scripts and caching the asset manager page.
		add_action( 'wp', [ $this, 'stop_caching_and_script_optimize' ] );
		// Disable autoptimize on Assets manager page
		add_filter( 'autoptimize_filter_noptimize', [ $this, 'autoptimize_noptimize' ], 10, 0 );
		add_filter( 'wmac_filter_noptimize', [ $this, 'autoptimize_noptimize' ], 10, 0 );

		if ( wp_doing_ajax() ) {
			require_once WGZ_PLUGIN_DIR . '/admin/ajax/save-settings.php';
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
						'jquery.js',
						'jquery-migrate.min.js',
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

	function clearfyAdminBarMenu( $menu_items ) {
		$current_url = add_query_arg( [ 'wbcr_assets_manager' => 1 ] );

		$menu_items['assetsManager'] = [
			'title' => '<span class="dashicons dashicons-list-view"></span> ' . __( 'Assets Manager', 'gonzales' ),
			'href'  => $current_url
		];

		return $menu_items;
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	function assetsManagerAdminBar( $wp_admin_bar ) {
		if ( ! $this->isUserCan() ) {
			return;
		}

		$current_url = add_query_arg( [ 'wbcr_assets_manager' => 1 ] );

		$args = [
			'id'    => 'assetsManager',
			'title' => __( 'Assets Manager', 'gonzales' ),
			'href'  => $current_url
		];
		$wp_admin_bar->add_node( $args );
	}

	/**
	 * Action plugins loaded
	 */
	public function pluginsLoaded() {
		if ( ! is_admin() ) {
			$this->sided_plugins = [
				'aopt' => 'autoptimize/autoptimize.php',
				'wmac' => 'minify-and-combine/minify-and-combine.php'
			];
		}

		if ( class_exists( 'WCL_Plugin' ) && ( WCL_Plugin::app()->getPopulateOption( 'remove_js_version', false ) || WCL_Plugin::app()->getPopulateOption( 'remove_css_version', false ) ) ) {
			$this->sided_plugins['wclp'] = 'clearfy/clearfy.php';
		}

		#comp remove
		// Пока плагины не скомпилированы, они имеют другие имена директорий.
		// После компиляции плагина этот кусок кода будет удален.
		if ( ! is_admin() ) {
			$this->sided_plugins['wmac'] = 'wp-plugin-minify-and-combine/minify-and-combine.php';
		}

		if ( class_exists( 'WCL_Plugin' ) && ( WCL_Plugin::app()->getPopulateOption( 'remove_js_version', false ) || WCL_Plugin::app()->getPopulateOption( 'remove_css_version', false ) ) ) {
			$this->sided_plugins['wclp'] = 'wp-plugin-clearfy/clearfy.php';
		}
		#endcomp

		$this->sided_plugins = apply_filters( 'wbcr_gnz_sided_plugins', $this->sided_plugins );
	}

	function assetsManager() {
		if ( ! $this->isUserCan() || ! isset( $_GET['wbcr_assets_manager'] ) ) {
			return;
		}

		$views = new WGZ_Views( WGZ_PLUGIN_DIR );
		$views->print_template( 'assets-manager', [
			'collection'              => $this->collection,
			'loaded_plugins'          => $this->get_loaded_plugins(),
			'theme_assets'            => $this->get_collected_assets( 'theme' ),
			'misc_assets'             => $this->get_collected_assets( 'misc' ),
			'conditions_logic_params' => $this->get_conditions_login_params( true ),
			'settings'                => $this->plugin->getOption( 'settings', [] )
		] );

		return;

		$current_url = esc_url( $this->getCurrentUrl() );

		// todo: вынести в метод
		if ( is_multisite() && is_network_admin() ) {
			$options = $this->getNetworkOption( 'assets_manager_options', [] );
		} else {
			$options = $this->getOption( 'assets_manager_options', [] );
		}

		echo '<div id="WBCR" class="wam-wrapper"';
		if ( isset( $_GET['wbcr_assets_manager'] ) ) {
			echo 'style="display: block;"';
		}
		echo '>';

		//Form
		echo '<form method="POST">';
		wp_nonce_field( 'wbcr_assets_manager_nonce', 'wbcr_assets_manager_save' );

		//Header
		echo '<header class="wbcr-gnz-panel">';
		echo '<div class="wbcr-gnz-panel__left">';
		echo '<div class="wbcr-gnz-panel__logo"></div>';
		echo '<ul class="wbcr-gnz-panel__data  panel__data-main">';
		echo '<li class="wbcr-gnz-panel__data-item __info-query">' . __( 'Total requests', 'gonzales' ) . ': <b class="wbcr-gnz-panel__item_value">--</b></li>';
		echo '<li class="wbcr-gnz-panel__data-item __info-all-weight">' . __( 'Total size', 'gonzales' ) . ': <b class="wbcr-gnz-panel__item_value"><span class="wbcr-gnz-panel__color-1">--</span></b></li>';
		echo '<li class="wbcr-gnz-panel__data-item __info-opt-weight">' . __( 'Optimized size', 'gonzales' ) . ': <b class="wbcr-gnz-panel__item_value"><span class="wbcr-gnz-panel__color-2">--</span></b></li>';
		echo '<li class="wbcr-gnz-panel__data-item __info-off-js">' . __( 'Disabled js', 'gonzales' ) . ': <b class="wbcr-gnz-panel__item_value">--</li></b>';
		echo '<li class="wbcr-gnz-panel__data-item __info-off-css">' . __( 'Disabled css', 'gonzales' ) . ': <b class="wbcr-gnz-panel__item_value">--</li></b>';
		echo '</ul>';
		$panel_to_premium_info = '<div class="wbcr-gnz-panel__premium"><div class="wam-tooltip wam-tooltip--bottom" data-tooltip="' . __( 'This is the general statistics to see the optimization result. Available in the paid version only.', 'gonzales' ) . '.">PRO</div></div>';
		echo apply_filters( 'wbcr_gnz_panel_premium', $panel_to_premium_info );
		echo '</div>';
		echo '<div class="wbcr-gnz-panel__right">';
		echo '<button class="wbcr-gnz-panel__reset wbcr-reset-button" type="button">' . __( 'Reset', 'gonzales' ) . '</button>';
		echo '<input class="wbcr-gnz-panel__save" type="submit" value="' . __( 'Save', 'gonzales' ) . '">';
		echo '<label class="wbcr-gnz-panel__checkbox  wam-tooltip  wam-tooltip--bottom" data-tooltip="' . __( 'In test mode, you can experiment with disabling unused scripts safely for your site. The resources that you disabled will be visible only to you (the administrator), and all other users will receive an unoptimized version of the site, until you remove this tick', 'gonzales' ) . '.">';
		echo apply_filters( 'wbcr_gnz_test_mode_checkbox', '<input class="wbcr-gnz-panel__checkbox-input visually-hidden" type="checkbox" disabled="disabled" checked/><span class="wbcr-gnz-panel__checkbox-text-premium">' . __( 'Safe mode <b>PRO</b>', 'gonzales' ) . '</span>' );
		echo '</label>';
		echo '<button class="wbcr-gnz-panel__close wbcr-close-button" type="button" aria-label="' . __( 'Close', 'gonzales' ) . '" data-href="' . remove_query_arg( 'wbcr_assets_manager' ) . '"></button>';
		echo '</div>';
		echo '</header>';

		// Main content
		echo '<main class="wam-content">';

		uksort( $this->collection, function ( $a, $b ) {
			if ( 'plugins' == $a ) {
				return - 1;
			}

			if ( 'plugins' == $b ) {
				return 1;
			}

			return strcasecmp( $a, $b );
		} );

		// Tabs
		echo '<ul class="wbcr-gnz-tabs">';
		foreach ( $this->collection as $resource_type => $resources ) {
			echo '<li class="wbcr-gnz-tabs__item">';
			echo '<div class="wbcr-gnz-tabs__button  wbcr-gnz-tabs__button--' . $resource_type . '" data-hash="' . $resource_type . '" aria-label="' . $resource_type . '"></div>';
			echo '</li>';
		}
		echo '</ul>';

		// Info
		echo '<div class="wam-info-section"><div class="wam-info-section__warning">';
		echo '<p><b>' . __( 'Important! Each page of your website has different sets of scripts and styles files.', 'gonzales' ) . '</b></p>';
		echo '<p>' . __( 'Use this feature to disable unwanted scripts and styles by setting up the logic for different types of pages. We recommend working in "Safe mode" because disabling any necessary system script file can corrupt the website. All changes done in Safe mode are available for administrator only. This way only you, as the administrator, can see the result of optimization. To enable the changes for other users, uncheck Safe mode.', 'gonzales' ) . '</p>';
		echo '<p>' . sprintf( __( 'For more details and user guides, check the plugin’s <a href="%s" target="_blank" rel="noreferrer noopener">documentation</a>.', 'gonzales' ), WbcrFactoryClearfy000_Helpers::getWebcrafticSitePageUrl( WGZ_Plugin::app()->getPluginName(), 'docs' ) ) . '</p>';
		echo '</div>';

		$premium_button = '<a class="wbcr-gnz-button__pro" href="' . WbcrFactoryClearfy000_Helpers::getWebcrafticSitePageUrl( WGZ_Plugin::app()->getPluginName(), 'assets-manager' ) . '" target="_blank" rel="noreferrer noopener">' . __( 'Upgrade to Premium', 'gonzales' ) . '</a>';

		$upgrade_to_premium_info = '<div class="wam-info-section__go-to-premium"><ul>';
		$upgrade_to_premium_info .= '<h3><span>' . __( 'MORE IN CLEARFY BUSINESS', 'gonzales' ) . '</span>' . $premium_button . '</h3><ul>';
		$upgrade_to_premium_info .= '<li>' . __( 'Disable plugins (groups of scripts)', 'gonzales' ) . '</li>';
		$upgrade_to_premium_info .= '<li>' . __( 'Conditions by the link template', 'gonzales' ) . '</li>';
		$upgrade_to_premium_info .= '<li>' . __( 'Conditions by the regular expression', 'gonzales' ) . '</li>';
		$upgrade_to_premium_info .= '<li>' . __( 'Safe mode', 'gonzales' ) . '</li>';
		$upgrade_to_premium_info .= '<li>' . __( 'Statistics and optimization results', 'gonzales' ) . '</li>';
		$upgrade_to_premium_info .= '</ul>';
		$upgrade_to_premium_info .= '</div>';
		echo apply_filters( 'wbcr_gnz_upgrade_to_premium_info', $upgrade_to_premium_info );
		echo '</div>';

		global $plugin_state;

		foreach ( $this->collection as $resource_type => $resources ) {
			// Tabs content
			echo '<div class="wbcr-gnz-tabs-content">';
			echo '<div class="wam-table">';
			echo '<table>';
			echo '<col class="wam-table__loaded"/>';
			echo '<col class="wam-table__size"/>';
			echo '<col class="wam-table__script"/>';
			echo '<col class="wam-table__state"/>';
			echo '<col class="wam-table__turn-on"/>';

			foreach ( $resources as $resource_name => $types ) {
				$plugin_state = false;

				if ( 'plugins' == $resource_type && ! empty( $resource_name ) ) {
					$plugin_data = $this->get_plugin_data( $resource_name );

					echo '<tbody>';

					if ( ! empty( $plugin_data ) ) {
						$is_disabled = $this->getIsDisabled( $options, $resource_type, $resource_name );
						$disabled    = $this->getDisabled( $is_disabled, $options, $resource_type, $resource_name );

						$is_enabled = $this->getIsEnabled( $options, $resource_type, $resource_name );
						$enabled    = $this->getEnabled( $is_enabled, $options, $resource_type, $resource_name );

						$plugin_state = $this->getState( $is_disabled, $disabled, $current_url );
						$plugin_state = apply_filters( 'wbcr_gnz_get_plugin_state', false, $plugin_state );

						echo '<tr class="wam-table__alternate">';
						echo '<th style="width:5%">' . __( 'Loaded', 'gonzales' ) . '</th>';
						echo '<th colspan="2">' . __( 'Plugin', 'gonzales' ) . '</th>';

						echo apply_filters( 'wbcr_gnz_get_additional_head_columns', '' );

						echo '<th class="wam-table__column_switch"><b>' . __( 'Load resource?', 'gonzales' ) . '</b></th>';
						echo '<th class="wam-table__column_condition">' . __( 'Conditions', 'gonzales' ) . '</th>';
						echo '</tr>';
						echo '<tr>';
						echo '<td>';
						echo '<div class="wam-table__loaded-state wam-table__loaded-' . ( $plugin_state ? 'no' : 'yes' ) . ' wbcr-state"></div>';
						echo '</td>';
						echo '<td colspan="2" class="wam-table__item">';
						echo '<div class="wam-table__item-name">' . $plugin_data['Name'] . '</div>';
						echo '<div class="wam-table__item-author"><strong>' . __( 'Author', 'gonzales' ) . ':</strong> ' . $plugin_data['Author'] . '</div>';
						echo '<div class="wam-table__item-version"><strong>' . __( 'Version', 'gonzales' ) . ':</strong> ' . $plugin_data['Version'] . '</div>';
						echo '</td>';

						echo apply_filters( 'wbcr_gnz_get_additional_controls_columns', '', $resource_type, $resource_name, $resource_name );

						// State Controls
						$id = '[' . $resource_type . '][' . $resource_name . ']';
						echo $this->getStateControrlHTML( $id, $plugin_state, $is_disabled, $is_enabled, $resource_type, $resource_name, $disabled, $enabled, $current_url );
						echo '</tr>';
					}
				}

				echo '<tr class="wam-table__alternate">';
				echo '<th style="width:5%">' . __( 'Loaded', 'gonzales' ) . '</th>';
				echo '<th style="width:5%">' . __( 'Size', 'gonzales' ) . '</th>';
				echo '<th class="wgz-th">' . __( 'Resource', 'gonzales' ) . '</th>';

				echo apply_filters( 'wbcr_gnz_get_additional_head_columns', '' );

				echo '<th class="wam-table__column_switch"><b>' . __( 'Load resource?', 'gonzales' ) . '</b></th>';
				echo '<th class="wam-table__column_condition">' . __( 'Conditions', 'gonzales' ) . '</th>';
				echo '</tr>';

				foreach ( $types as $type_name => $rows ) {

					if ( ! empty( $rows ) ) {
						foreach ( $rows as $handle => $row ) {
							$is_disabled = $this->getIsDisabled( $options, $type_name, $handle );
							$disabled    = $this->getDisabled( $is_disabled, $options, $type_name, $handle );

							$is_enabled = $this->getIsEnabled( $options, $type_name, $handle );
							$enabled    = $this->getEnabled( $is_enabled, $options, $type_name, $handle );

							/**
							 * Find dependency
							 */
							$deps = [];
							foreach ( $rows as $dep_key => $dep_val ) {
								if ( in_array( $handle, $dep_val['deps'] ) /*&& $is_disabled*/ ) {
									$deps[] = '<a href="#' . $type_name . '-' . $dep_key . '">' . $dep_key . '</a>';
								}
							}

							$comment  = ( ! empty( $deps ) ? '<span class="wbcr-use-by-comment">' . __( 'In use by', 'gonzales' ) . ' ' . implode( ', ', $deps ) . '</span>' : '' );
							$requires = '';
							if ( ! empty( $row['deps'] ) ) {
								$rdeps = [];
								foreach ( $row['deps'] as $dep_val ) {
									$rdeps[] = '<a href="#' . $type_name . '-' . $dep_val . '">' . $dep_val . '</a>';
								}
								$requires = ( $comment ? '<br>' : '' ) . '<span class="wbcr-use-by-comment">' . __( 'Requires', 'gonzales' ) . ' ' . implode( ', ', $rdeps ) . '</span>';
							}

							echo '<tr>';

							// Loaded
							$state         = $this->getState( $is_disabled, $disabled, $current_url );
							$display_state = $plugin_state === 1 ? 1 : $state;
							echo '<td>';
							echo '<div class="wam-table__loaded-state wam-table__loaded-' . ( $plugin_state ? 'no' : 'yes' );
							echo ' wbcr-state' . ( $state ? ' wam-table__loaded-super-no' : '' );
							echo ( 'plugins' == $resource_type ? ' wbcr-state-' . $resource_name : '' ) . '">';
							echo '</div>';
							echo '</td>';

							// Size
							echo '<td>';
							echo '<div class="wam-table__size-value">' . $row['size'] . ' <b>KB</b></div>';
							echo '</td>';

							// Handle + Path + In use
							echo '<td class="wgz-td">';
							echo '<div class="wam-table__script-name"><b class="wbcr-wgz-resource-type-' . $type_name . '">' . $type_name . '</b>[' . $handle . ']</div>';
							echo "<a id='" . $type_name . "-" . $handle . "' class='wbcr-anchor'></a>";
							echo '<div class="wam-table__script-path">';
							echo "<a href='" . $row['url_full'] . "' target='_blank'>";
							echo str_replace( get_home_url(), '', $row['url_full'] ) . "</a>";
							echo '</div>';
							echo '<div class="wam-table__script-version">';
							echo __( 'Version', 'gonzales' ) . ': ' . ( ! empty( $row['ver'] ) ? $row['ver'] : __( '--', 'gonzales' ) );
							echo '</div>';
							echo '<div>' . $comment . $requires . '</div>';
							echo '</td>';

							// Controls for other plugins
							echo apply_filters( 'wbcr_gnz_get_additional_controls_columns', '', $type_name, $row['url_full'], $resource_name );

							// State Controls
							$id = '[' . $type_name . '][' . $handle . ']';
							echo $this->getStateControrlHTML( $id, $state, $is_disabled, $is_enabled, $type_name, $handle, $disabled, $enabled, $current_url );

							echo "<input type='hidden' class='wbcr-info-data' data-type='{$type_name}' data-off='{$display_state}' value='{$row['size']}'>";
							echo '</tr>';

							echo apply_filters( 'wbcr_gnz_after_scripts_table_row', '', $resource_type, $resource_name, $type_name, $handle );
						}
					}
				}

				if ( 'plugins' == $resource_type && ! empty( $resource_name ) ) {
					echo '</tbody>';
				}
			}

			echo '</table>';
			echo '</div>';
			echo '</div>';
		}
		echo '</main>';
		echo '</form> <!-- /endform -->';
		echo '</div> <!-- /div2 -->';
	}

	/**
	 * Get is disabled
	 *
	 * @param $options
	 * @param $type_name
	 * @param $handle
	 *
	 * @return bool
	 */
	public function getIsDisabled( $options, $type_name, $handle ) {
		return isset( $options['disabled'] ) && isset( $options['disabled'][ $type_name ] ) && isset( $options['disabled'][ $type_name ][ $handle ] );
	}

	/**
	 * Get disabled
	 *
	 * @param $is_disabled
	 * @param $options
	 * @param $type_name
	 * @param $handle
	 *
	 * @return array
	 */
	public function getDisabled( $is_disabled, $options, $type_name, $handle ) {
		$disabled = [];

		if ( $is_disabled ) {
			$disabled = &$options['disabled'][ $type_name ][ $handle ];
			if ( ! isset( $disabled['current'] ) ) {
				$disabled['current'] = [];
			}
			if ( ! isset( $disabled['everywhere'] ) ) {
				$disabled['everywhere'] = [];
			}

			$disabled = apply_filters( 'wbcr_gnz_get_disabled', $disabled );
		}

		return $disabled;
	}

	/**
	 * Get is enabled
	 *
	 * @param $options
	 * @param $type_name
	 * @param $handle
	 *
	 * @return bool
	 */
	public function getIsEnabled( $options, $type_name, $handle ) {
		return isset( $options['enabled'] ) && isset( $options['enabled'][ $type_name ] ) && isset( $options['enabled'][ $type_name ][ $handle ] );
	}

	/**
	 * Get enabled
	 *
	 * @param $is_enabled
	 * @param $options
	 * @param $type_name
	 * @param $handle
	 *
	 * @return array
	 */
	public function getEnabled( $is_enabled, $options, $type_name, $handle ) {
		$enabled = [];

		if ( $is_enabled ) {
			$enabled = &$options['enabled'][ $type_name ][ $handle ];

			if ( ! isset( $enabled['current'] ) ) {
				$enabled['current'] = [];
			}
			if ( ! isset( $enabled['everywhere'] ) ) {
				$enabled['everywhere'] = [];
			}

			$enabled = apply_filters( 'wbcr_gnz_get_enabled', $enabled );
		}

		return $enabled;
	}

	/**
	 * Get State
	 *
	 * @param $is_disabled
	 * @param $disabled
	 * @param $current_url
	 *
	 * @return int
	 */
	public function getState( $is_disabled, $disabled, $current_url ) {
		$state = 0;
		if ( $is_disabled && ( $disabled['everywhere'] == 1 || in_array( $current_url, $disabled['current'] ) || apply_filters( 'wbcr_gnz_check_state_disabled', false, $disabled ) ) ) {
			$state = 1;
		}

		return $state;
	}

	/**
	 * Get state controrl HTML
	 *
	 * @param $id
	 * @param $state
	 * @param $is_disabled
	 * @param $is_enabled
	 * @param $type_name
	 * @param $handle
	 * @param $disabled
	 * @param $enabled
	 * @param $current_url
	 *
	 * @return string
	 */
	public function getStateControrlHTML( $id, $state, $is_disabled, $is_enabled, $type_name, $handle, $disabled, $enabled, $current_url ) {
		// Disable
		$html = '<td>';
		$html .= '<label class="wbcr-gnz-switch' . ( $type_name == 'plugins' ? apply_filters( 'wbcr_gnz_switch_premium', ' wbcr-gnz-switch-premium' ) : '' ) . '">';
		$html .= '<input class="wbcr-gnz-switch__input visually-hidden' . apply_filters( 'wbcr_gnz_switch_plugin_premium', $type_name == 'plugins' ? '' : ' wbcr-gnz-disable' ) . '" type="checkbox"' . checked( $state, true, false );
		$html .= ( 'plugins' == $type_name ? " data-handle='{$handle}'" : "" ) . '/>';
		$html .= '<input type="hidden" name="disabled' . $id . '[state]" value="' . ( $state ? 'disable' : '' ) . '"/>';
		$html .= '<span class="wbcr-gnz-switch__inner" data-off="' . __( 'No', 'gonzales' ) . '" data-on="' . __( 'Yes', 'gonzales' ) . '"></span>';
		$html .= '<span class="wbcr-gnz-switch__slider"></span>';
		$html .= '</label>';
		$html .= '</td>';

		// Enable
		$class_name = 'wam-assets-manager-enable';
		if ( 'plugins' == $type_name ) {
			$class_name = apply_filters( 'wbcr_gnz_control_classname', 'wbcr-gnz' );
		}
		$html .= '<td>';
		$html .= '<div class="wam-table__note ' . $class_name . '-placeholder"';
		if ( $state ) {
			$html .= ' style="display: none;"';
		}
		if ( 'plugins' != $type_name ) {
			$html .= '><p>' . __( 'Click the switch in the <b>Load resource?</b> column to display the conditions for loading the resource.', 'gonzales' ) . '</p>';
		} else {
			$html .= '><p>' . apply_filters( 'wbcr_gnz_conditions_note_premium', __( 'Set the plugin logic to apply it to all plugin’s resources. This feature available at the paid version.', 'gonzales' ) ) . '</p>';
		}
		$html .= '</div>';
		$html .= '<span class="' . $class_name . '"';
		if ( ! $state ) {
			$html .= ' style="display: none;"';
		}
		$html    .= '>';
		$html    .= '<select class="wam-table__select wbcr-gnz-action-select" name="wgz_action' . $id . '">';
		$html    .= '<option value="current"' . selected( $is_disabled && ! empty( $disabled['current'] ), true, false ) . '>' . __( 'Current URL', 'gonzales' ) . '</option>';
		$html    .= '<option value="everywhere"' . selected( $is_disabled && ! empty( $disabled['everywhere'] ), true, false ) . '>' . __( 'Everywhere', 'gonzales' ) . '</option>';
		$options = '<option value="custom"' . selected( $is_disabled && ! empty( $disabled['custom'] ), true, false ) . ' class="wam-table__select-pro">' . __( 'Custom URL (PRO)', 'gonzales' ) . '</option>';
		$options .= '<option value="regex"' . selected( $is_disabled && ! empty( $disabled['regex'] ), true, false ) . ' class="wam-table__select-pro">' . __( 'Regular expression (PRO)', 'gonzales' ) . '</option>';
		$html    .= apply_filters( 'wbcr_gnz_select_options', $options, $is_disabled, $disabled );
		$html    .= '</select>';

		// Everywhere
		$html .= "<div class='wam-assets-manager everywhere'";
		if ( ! $is_disabled || empty( $disabled['everywhere'] ) ) {
			$html .= " style='display: none;'";
		}
		$html .= ">";
		$html .= '<div class="wam-table__label">' . __( 'Exclude', 'gonzales' ) . ': <i class="wam-help-hint wam-tooltip  wam-tooltip--bottom" data-tooltip="' . __( 'You can disable this resource for all pages, except sections and page types listed below. Specify sections and page types with the enabled resource.', 'gonzales' ) . '"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAkAAAAJCAQAAABKmM6bAAAAUUlEQVQIHU3BsQ1AQABA0X/komIrnQHYwyhqQ1hBo9KZRKL9CBfeAwy2ri42JA4mPQ9rJ6OVt0BisFM3Po7qbEliru7m/FkY+TN64ZVxEzh4ndrMN7+Z+jXCAAAAAElFTkSuQmCC" alt=""></i></div>';
		$html .= '<div class="wam-table__wrap-options"><ul class="wam-table__options">';

		$html .= '<li class="wam-table__options-item">';
		$html .= "<input type='hidden' name='enabled{$id}[current]' value='' />";
		$html .= '<label class="wam-table__checkbox">';
		$html .= '<input class="wam-table__checkbox-input visually-hidden" type="checkbox" name="enabled' . $id . '[current]" value="' . $current_url . '"';
		if ( $is_enabled && in_array( $current_url, $enabled['current'] ) ) {
			$html .= ' checked';
		}
		$html .= '/>';
		$html .= '<span class="wam-table__checkbox-text">' . __( 'Current URL', 'gonzales' ) . '</span>';
		$html .= '</label>';
		$html .= '</li>';

		$html       .= '<li class="wam-table__options-item-group"><strong>' . __( 'Post types', 'gonzales' ) . '</strong></li>';
		$post_types = get_post_types( [ 'public' => true ], 'objects', 'and' );
		if ( ! empty( $post_types ) ) {
			$html .= "<input type='hidden' name='enabled{$id}[post_types]' value='' />";
			foreach ( $post_types as $key => $value ) {
				$html .= '<li class="wam-table__options-item">';
				$html .= '<label class="wam-table__checkbox">';
				$html .= '<input class="wam-table__checkbox-input visually-hidden" type="checkbox" name="enabled' . $id . '[post_types][]" value="' . $key . '"';
				if ( isset( $enabled['post_types'] ) ) {
					if ( in_array( $key, $enabled['post_types'] ) ) {
						$html .= ' checked';
					}
				}
				$html .= '/>';
				$html .= '<span class="wam-table__checkbox-text">' . $value->label . '</span>';
				$html .= '</label>';
				$html .= '</li>';
			}
		}

		$html       .= '<li class="wam-table__options-item-group"><strong>' . __( 'Categories, Taxonomies, Tags', 'gonzales' ) . '</strong></li>';
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects', 'and' );
		if ( ! empty( $taxonomies ) ) {
			unset( $taxonomies['category'] );
			$html .= "<input type='hidden' name='enabled{$id}[taxonomies]' value='' />";
			foreach ( $taxonomies as $key => $value ) {
				$html .= '<li class="wam-table__options-item">';
				$html .= '<label class="wam-table__checkbox">';
				$html .= '<input class="wam-table__checkbox-input visually-hidden" type="checkbox" name="enabled' . $id . '[taxonomies][]" value="' . $key . '"';
				if ( isset( $enabled['taxonomies'] ) ) {
					if ( in_array( $key, $enabled['taxonomies'] ) ) {
						$html .= ' checked';
					}
				}
				$html .= '/>';
				$html .= '<span class="wam-table__checkbox-text">' . $value->label . '</span>';
				$html .= '</label>';
				$html .= '</li>';
			}
		}

		$categories = get_categories();

		if ( ! empty( $categories ) ) {
			$html .= "<input type='hidden' name='enabled{$id}[categories]' value='' />";
			foreach ( $categories as $key => $cat ) {
				$html .= '<li class="wam-table__options-item">';
				$html .= '<label class="wam-table__checkbox">';
				$html .= '<input class="wam-table__checkbox-input visually-hidden" type="checkbox" name="enabled' . $id . '[categories][]" value="' . $cat->term_id . '"';
				if ( isset( $enabled['categories'] ) ) {
					if ( in_array( $cat->term_id, $enabled['categories'] ) ) {
						$html .= ' checked';
					}
				}
				$html .= '/>';
				$html .= '<span class="wam-table__checkbox-text">' . $cat->name . '</span>';
				$html .= '</label>';
				$html .= '</li>';
			}
		}

		$html .= '</ul></div>';
		$html .= '</div>';

		// Custom URL
		$control_html = '<div class="wam-table__field wam-assets-manager custom"';
		if ( ! $is_disabled || empty( $disabled['custom'] ) ) {
			$control_html .= ' style="display: none;"';
		}
		$control_html .= '>';
		$control_html .= '<label class="wam-table__label" for="disabled' . $id . '[custom][]" title="' . __( 'Example', 'gonzales' ) . ': ' . site_url() . '/post/*, ' . site_url() . '/page-*>">' . __( 'Enter URL (set * for mask)', 'gonzales' ) . ': <i class="wam-help-hint wam-tooltip  wam-tooltip--bottom" data-tooltip="' . __( 'You can disable the resource only for the pages with the matched to the template address. For example, if you set the template for the link as http://yoursite.test/profile/*, then the resource is disabled for the following pages: http://yoursite.test/profile/12, http://yoursite.test/profile/43, http://yoursite.test/profile/999. If you don’t use the asterisk symbol in the template then the plugin will disable the resource only for the pages with 100% match in the specified link type. This feature is available at the paid version.', 'gonzales' ) . '"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAkAAAAJCAQAAABKmM6bAAAAUUlEQVQIHU3BsQ1AQABA0X/komIrnQHYwyhqQ1hBo9KZRKL9CBfeAwy2ri42JA4mPQ9rJ6OVt0BisFM3Po7qbEliru7m/FkY+TN64ZVxEzh4ndrMN7+Z+jXCAAAAAElFTkSuQmCC" alt=""></i></label>';
		$control_html .= '<div class="wam-table__field-item">';
		$control_html .= '<input class="wam-table__field-input" name="disabled' . $id . '[custom][]" type="text" placeholder="http://yoursite.test/profile/*" value="" disabled="disabled">';
		$control_html .= '<button class="wam-table__field-add" type="button" aria-label="' . __( 'Add field', 'gonzales' ) . '" disabled></button>';
		$control_html .= '</div>';
		//$control_html .= '<em>Пример: http://yoursite.test/profile/*</em>';
		$control_html .= '</div>';
		// Regex
		$control_html .= "<div class='wam-table__field wam-assets-manager regex'";
		if ( ! $is_disabled || empty( $disabled['regex'] ) ) {
			$control_html .= " style='display: none;'";
		}
		$control_html .= ">";
		$control_html .= '<label class="wam-table__label" for="disabled' . $id . '[regex]">' . __( 'Enter regular expression', 'gonzales' ) . ': <i class="wam-help-hint wam-tooltip  wam-tooltip--bottom" data-tooltip="' . __( 'Regular expressions can be used by experts. This tool creates flexible conditions to disable the resource. For example, if you specify this expression: ^([A-z0-9]+-)?gifts? then the resource will be disabled at the following pages http://yoursite.test/get-gift/, http://yoursite.test/gift/, http://yoursite.test/get-gifts/, http://yoursite.test/gifts/. The plugin ignores the backslash at the beginning of the query string, so you can dismiss it. Check your regular expressions in here: https://regex101.com, this will prevent you from the mistakes. This feature is available at the paid version.', 'gonzales' ) . '"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAkAAAAJCAQAAABKmM6bAAAAUUlEQVQIHU3BsQ1AQABA0X/komIrnQHYwyhqQ1hBo9KZRKL9CBfeAwy2ri42JA4mPQ9rJ6OVt0BisFM3Po7qbEliru7m/FkY+TN64ZVxEzh4ndrMN7+Z+jXCAAAAAElFTkSuQmCC" alt=""></i></label>';
		$control_html .= '<textarea class="wam-table__textarea" rows="3" name="disabled' . $id . '[regex]" placeholder="^rockstar-[0-9]{2,5}" disabled="disabled"></textarea>';
		$control_html .= "</div>";
		$html         .= apply_filters( 'wbcr_gnz_control_html', $control_html, $id, $is_disabled, $disabled );

		$html .= '</span>';

		if ( isset( $disabled['current'] ) && ! empty( $disabled['current'] ) ) {
			$custom_urls = "";

			foreach ( $disabled['current'] as $item_url ) {
				if ( $current_url != $item_url ) {
					$full_url    = site_url() . $item_url;
					$custom_urls .= "<span><a href='" . $full_url . "'>" . $full_url . "</a></span>";
				}
			}

			if ( ! empty( $custom_urls ) ) {
				$html .= '<div class="wam-table__also">';
				$html .= '<div class="wam-table__label">' . __( 'Also disabled for pages', 'gonzales' ) . ':</div>';
				$html .= '<div class="wam-table__also-url">' . $custom_urls . '</div>';
				$html .= '</div>';
			}
		}
		$html .= '</td>';

		return $html;
	}

	public function formSave() {
		if ( isset( $_GET['wbcr_assets_manager'] ) && isset( $_POST['wam_save_settings'] ) ) {

			/*if ( ! $this->isUserCan() || ! wp_verify_nonce( filter_input( INPUT_POST, 'wbcr_assets_manager_save' ), 'wbcr_assets_manager_nonce' ) ) {
				wp_die( __( 'You don\'t have enough capability to edit this information.', 'gonzales' ), 403 );

				return;
			}

			// If mu  plugin does not exist, install it.
			wbcr_gnz_deploy_mu_plugin();*/

			$settings = $this->getOption( 'settings', [] );

			if ( ! is_array( $settings ) ) {
				$settings = [];
			}

			if ( ! empty( $_POST['wam_plugin'] ) ) {
				foreach ( (array) $_POST['wam_plugin'] as $plugin_name => $plugin_group ) {
					if ( ! empty( $plugin_group['load_mode'] ) ) {
						if ( 'enable' == $plugin_group['load_mode'] ) {
							$plugin_group['visability'] = "";
						} else {
							foreach ( [ 'js', 'css' ] as $assets_type ) {
								if ( ! empty( $plugin_group[ $assets_type ] ) ) {
									foreach ( $plugin_group[ $assets_type ] as $resource_handle => $resource_params ) {
										$plugin_group[ $assets_type ][ $resource_handle ]['load_mode']  = "disable";
										$plugin_group[ $assets_type ][ $resource_handle ]['visability'] = "";
									}
								}
							}
							$plugin_group['visability'] = "";
						}
					}

					$settings['plugins'][ $plugin_name ] = $plugin_group;
				}
			}

			$this->updateOption( 'settings', $settings );

			///$a = $settings;
			//WbcrFactoryClearfy000_Helpers::flushPageCache();
		}
	}

	/**
	 * Get disabled from options
	 *
	 * @param $type
	 * @param $handle
	 *
	 * @return null
	 */
	private function getDisabledFromOptions( $type, $handle ) {
		// todo: вынести в метод
		if ( is_multisite() && is_network_admin() ) {
			$options = $this->getNetworkOption( 'assets_manager_options', [] );
		} else {
			$options = $this->getOption( 'assets_manager_options', [] );
		}

		if ( isset( $options['disabled'] ) && isset( $options['disabled'][ $type ] ) && isset( $options['disabled'][ $type ][ $handle ] ) ) {
			return $options['disabled'][ $type ][ $handle ];
		}

		return null;
	}

	/**
	 * Get enabled from options
	 *
	 * @param $type
	 * @param $handle
	 *
	 * @return null
	 */
	private function getEnabledFromOptions( $type, $handle ) {
		// todo: вынести в метод
		if ( is_multisite() && is_network_admin() ) {
			$options = $this->getNetworkOption( 'assets_manager_options', [] );
		} else {
			$options = $this->getOption( 'assets_manager_options', [] );
		}

		if ( isset( $options['enabled'] ) && isset( $options['enabled'][ $type ] ) && isset( $options['enabled'][ $type ][ $handle ] ) ) {
			return $options['enabled'][ $type ][ $handle ];
		}

		return null;
	}

	function unloadAssets( $src, $handle ) {
		if ( isset( $_GET['wbcr_assets_manager'] ) ) {
			return $src;
		}

		if ( apply_filters( 'wbcr_gnz_check_unload_assets', false ) ) {
			return $src;
		}

		$type = ( current_filter() == 'script_loader_src' ) ? 'js' : 'css';

		$current_url = esc_url( $this->getCurrentUrl() );

		$disabled = $this->getDisabledFromOptions( $type, $handle );
		$enabled  = $this->getEnabledFromOptions( $type, $handle );

		$is_everywhere = isset( $disabled['everywhere'] ) && $disabled['everywhere'] == 1;
		$is_custom     = isset( $disabled['custom'] ) && is_array( $disabled['custom'] ) && ! empty( $disabled['custom'] );
		$is_current    = isset( $disabled['current'] ) && is_array( $disabled['current'] ) && in_array( $current_url, $disabled['current'] );
		$is_regex      = isset( $disabled['regex'] ) && ! empty( $disabled['regex'] );

		if ( $is_everywhere || $is_current || ( $is_custom || $is_regex ) ) {

			if ( isset( $enabled['current'] ) && is_array( $enabled['current'] ) && in_array( $current_url, $enabled['current'] ) ) {
				return $src;
			}

			if ( $is_custom ) {
				$found_match = false;

				foreach ( $disabled['custom'] as $url ) {
					// Убираем базовый url
					$free_url = str_replace( site_url(), '', $url );
					// Если есть *
					if ( strpos( $free_url, '*' ) ) {
						// Получаем строку до *
						$free_url = strstr( $free_url, '*', true );
						// Если это был не пустой url (типа http://site/*) и есть вхождение с начала
						if ( untrailingslashit( $free_url ) && strpos( untrailingslashit( $current_url ), $free_url ) === 0 ) {
							$found_match = true;
							break;
						}
						// Если url'ы идентичны
					} else if ( untrailingslashit( esc_url( $free_url ) ) === untrailingslashit( $current_url ) ) {
						$found_match = true;
						break;
					}
				}

				if ( ! $found_match ) {
					return $src;
				}
			}

			if ( $is_regex ) {
				$check_url = ltrim( $current_url, '/\\' );
				$regexp    = trim( str_replace( '\\\\', '\\', $disabled['regex'] ), '/' );

				if ( ! @preg_match( "/{$regexp}/", $check_url ) ) {
					return $src;
				}
			}

			if ( isset( $enabled['post_types'] ) && is_singular() && in_array( get_post_type(), $enabled['post_types'] ) ) {
				return $src;
			}

			if ( isset( $enabled['taxonomies'] ) ) {
				$query = get_queried_object();

				if ( ! empty( $query ) && isset( $query->taxonomy ) && in_array( $query->taxonomy, $enabled['taxonomies'] ) ) {
					return $src;
				}
			}

			if ( isset( $enabled['categories'] ) && in_array( get_query_var( 'cat' ), $enabled['categories'] ) ) {
				return $src;
			}

			return false;
		}

		return $src;
	}

	/**
	 * Get information regarding used assets
	 *
	 * @return bool
	 */
	public function collectAssets() {
		if ( ! isset( $_GET['wbcr_assets_manager'] ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return false;
		}

		$denied = [
			'js'  => [ 'wam-assets-manager', 'wam-assets-conditions', 'admin-bar' ],
			'css' => [
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
							$url       = $this->prepareCorrectUrl( $data->registered[ $el ]->src );
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
									'size'      => $this->getAssetSize( $url ),
									'ver'       => $data->registered[ $el ]->ver,
									'deps'      => ( isset( $data->registered[ $el ]->deps ) ? $data->registered[ $el ]->deps : [] ),
								];

								# Deregister scripts, styles so that they do not conflict with assets managers.
								# ------------------------------------------------
								$no_js = [
									'jquery',
									'jquery-core',
									'jquery-migrate',
									'jquery-ui-core'
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
	 * Loads functionality that allows to enable/disable js/css without site reload
	 */
	public function appendAsset() {
		if ( $this->isUserCan() && isset( $_GET['wbcr_assets_manager'] ) ) {

			wp_enqueue_style( 'wam-assets-manager', WGZ_PLUGIN_URL . '/assets/css/assets-manager.css', [], $this->plugin->getPluginVersion() );
			wp_enqueue_style( 'wam-assets-conditions', WGZ_PLUGIN_URL . '/assets/css/assets-conditions.css', [], $this->plugin->getPluginVersion() );
			wp_enqueue_style( 'wam-pnotify', WGZ_PLUGIN_URL . '/assets/css/PNotifyBrightTheme.css', [], $this->plugin->getPluginVersion() );

			wp_enqueue_script( 'wbcr-pnotify', WGZ_PLUGIN_URL . '/assets/js/PNotify.js', [], $this->plugin->getPluginVersion(), true );
			wp_enqueue_script( 'wam-assets-manager', WGZ_PLUGIN_URL . '/assets/js/assets-manager.js', [
				'jquery',
				'wam-assets-conditions'
			], $this->plugin->getPluginVersion(), true );
			wp_enqueue_script( 'wam-assets-conditions', WGZ_PLUGIN_URL . '/assets/js/assets-conditions.js', [ 'jquery' ], $this->plugin->getPluginVersion(), true );

			wp_localize_script( 'wam-assets-manager', 'wam_localize_data', [
				'ajaxurl' => admin_url( 'admin-ajax.php', is_ssl() ? 'admin' : 'http' )
			] );
		}
	}

	/**
	 * Exception for address starting from "//example.com" instead of
	 * "http://example.com". WooCommerce likes such a format
	 *
	 * @param string $url   Incorrect URL.
	 *
	 * @return string      Correct URL.
	 */
	private function prepareCorrectUrl( $url ) {
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
	private function getCurrentUrl() {
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
	private function getAssetSize( $src ) {
		$weight = 0;

		$home = get_theme_root() . '/../..';
		$src  = explode( '?', $src );

		if ( ! filter_var( $src[0], FILTER_VALIDATE_URL ) === false && strpos( $src[0], get_home_url() ) === false ) {
			return 0;
		}

		$src_relative = $home . str_replace( get_home_url(), '', $this->prepareCorrectUrl( $src[0] ) );

		if ( file_exists( $src_relative ) ) {
			$weight = round( filesize( $src_relative ) / 1024, 1 );
		}

		return $weight;
	}

	/**
	 * Unset disabled
	 *
	 * @param $disabled
	 * @param $action
	 *
	 * @return mixed
	 */
	public function unsetDisabled( $disabled, $action ) {
		if ( "everywhere" == $action ) {
			unset( $disabled['current'] );
		} else if ( "current" == $action ) {
			unset( $disabled['everywhere'] );
		}

		return $disabled;
	}


	private function get_collected_assets( $type ) {
		$assets = [];

		if ( empty( $this->collection ) ) {
			return $assets;
		}

		foreach ( (array) $this->collection as $resource_type => $resources ) {
			if ( $type == $resource_type ) {
				foreach ( $resources as $resource_name => $types ) {
					$assets = $types;
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
					$plugins[ $resource_name ]['assets']                  = $this->get_parsed_asset_settings( $types, $resource_name );
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
	 * @since  2.0.0
	 *
	 * @param string $plugin_name
	 * @param null   $setting_name
	 *
	 * @return array|mixed
	 * @throws \Exception
	 */
	private function get_parsed_plugin_settings( $plugin_name, $setting_name = null ) {
		$settings         = $this->plugin->getOption( 'settings', [] );
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

	private function get_parsed_asset_settings( $assets, $plugin_name = null ) {
		$plugin_group      = false;
		$settings_formated = [];
		$settings          = $this->plugin->getOption( 'settings', [] );

		if ( is_array( $assets ) && ( isset( $assets['js'] ) || isset( $assets['css'] ) ) ) {
			if ( ! isset( $assets['js'] ) ) {
				$assets['js'] = [];
			}
			if ( ! isset( $assets['css'] ) ) {
				$assets['css'] = [];
			}

			if ( ! empty( $plugin_name ) && ! empty( $settings['plugins'] ) && isset( $settings['plugins'][ $plugin_name ] ) ) {
				$settings     = $settings['plugins'][ $plugin_name ];
				$plugin_group = true;
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
						$s['row_classes']             = " js-wam-assets-table__tr--disabled-section";
						$s['select_control_classes']  = " js-wam-select--disable";
						$s['settings_button_classes'] = "";
					} else {
						$s['select_control_classes']  = " js-wam-select--enable";
						$s['settings_button_classes'] = " js-wam-button--hidden";
					}

					$s = array_merge( $s, $attrs );
				}
			}

			return $settings_formated;
		}

		return $assets;
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
	 * Get sided plugin name
	 *
	 * @param string $index
	 *
	 * @return string
	 */
	private function getSidedPluginName( $index ) {
		return $index;
		/*if( isset($this->sided_plugins[$index]) ) {
				$parts = explode('/', $this->sided_plugins[$index]);

				return isset($parts[0]) ? $parts[0] : $this->sided_plugins[$index];
			}
			
			return "";*/
	}

	/**
	 * Get exclude sided plugin files
	 *
	 * @param string $index
	 * @param string $type
	 * @param bool   $full
	 *
	 * @return array
	 */
	private function getSidedPluginFiles( $index, $type, $full = false ) {
		if ( isset( $this->sided_plugin_files[ $index ][ $type ] ) && ! empty( $this->sided_plugin_files[ $index ][ $type ] ) ) {
			return $this->sided_plugin_files[ $index ][ $type ];
		}

		$this->sided_plugin_files[ $index ][ $type ] = [];

		// todo: вынести в метод
		if ( is_multisite() && is_network_admin() ) {
			$options = $this->getNetworkOption( 'assets_manager_sided_plugins', [] );
		} else {
			$options = $this->getOption( 'assets_manager_sided_plugins', [] );
		}

		$plugin = $this->getSidedPluginName( $index );

		if ( $plugin && $options ) {
			if ( isset( $options[ $plugin ][ $type ] ) ) {
				$urls = $options[ $plugin ][ $type ];

				if ( is_array( $urls ) ) {
					foreach ( $urls as $url ) {

						if ( $full ) {
							$file = ( false !== strpos( $url, site_url() ) ? $url : site_url() . '/' . trim( $url, '/\\' ) );
						} else {
							$parts = explode( '/', $url );
							$file  = array_pop( $parts );
							if ( empty( $file ) ) {
								$file = $url;
							}
						}

						$this->sided_plugin_files[ $index ][ $type ][] = $file;
					}
				}
			}
		}

		return $this->sided_plugin_files[ $index ][ $type ];
	}

	/**
	 * Is component active
	 *
	 * @param $index
	 *
	 * @return bool
	 */
	private function isComponentActive( $index ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$plugin_path = isset( $this->sided_plugins[ $index ] ) ? $this->sided_plugins[ $index ] : null;

		if ( $index == 'wmac' && defined( 'LOADING_ASSETS_MANAGER_AS_ADDON' ) && class_exists( 'WCL_Plugin' ) ) {
			return WCL_Plugin::app()->isActivateComponent( 'minify_and_combine' );
		}

		return is_plugin_active( $plugin_path );
	}

	/**
	 * Get component name
	 *
	 * @param $plugin_path
	 * @param $index
	 *
	 * @return string
	 */
	private function getComponentName( $plugin_path, $index ) {
		if ( $index == 'wclp' ) {
			$name = 'Clearfy';
		} else if ( $index == 'wmac' ) {
			$name = __( 'Minify and Combine', 'gonzales' );
		} else {
			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
			$name = $data['Name'];
		}

		return $name;
	}

	/**
	 * Get head columns
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	public function getAdditionalHeadColumns( $html ) {
		if ( ! empty( $this->sided_plugins ) ) {
			foreach ( $this->sided_plugins as $index => $plugin_path ) {
				if ( $this->isComponentActive( $index ) ) {
					$title = $this->getComponentName( $plugin_path, $index );
					$text  = $index == 'wclp' ? __( 'remove version?', 'gonzales' ) : __( 'optimize?', 'gonzales' );

					$hint = '';
					if ( $index == 'wclp' ) {
						$hint = __( 'You’ve enabled &#34;Remove query strings&#34; from static resources in the &#34;Clearfy&#34; plugin. This list of settings helps you to exclude the necessary scripts and styles with remaining query strings. Press No to add a file to the excluded list.', 'gonzales' );
					} else if ( $index == 'wmac' ) {
						$hint = __( 'You’ve enabled the &#34;Optimize js scripts?&#34; and &#34;Optimize CSS options&#34; in the &#34;Minify & Combine plugin&#34;. These settings exclude scripts and styles that you don’t want to optimize. Press No to add a file to the excluded list.', 'gonzales' );
					} else if ( $index == 'aopt' ) {
						$hint = __( 'You’ve enabled the &#34;Optimize js scripts?&#34; and &#34;Optimize CSS options&#34; in the &#34;Autoptimize&#34;. These settings exclude scripts and styles that you don’t want to optimize. Press No to add a file to the excluded list.', 'gonzales' );
					}
					$html .= '<th class="wam-table__column_switch"><span class="wam-table__th-external-plugin">' . $title . ':<i class="wam-help-hint wam-tooltip  wam-tooltip--bottom" data-tooltip="' . $hint . '."><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAkAAAAJCAQAAABKmM6bAAAAUUlEQVQIHU3BsQ1AQABA0X/komIrnQHYwyhqQ1hBo9KZRKL9CBfeAwy2ri42JA4mPQ9rJ6OVt0BisFM3Po7qbEliru7m/FkY+TN64ZVxEzh4ndrMN7+Z+jXCAAAAAElFTkSuQmCC" alt=""></i></span><em>' . $text . '</em></th>';
				}
			}
		}

		return $html;
	}

	/**
	 * Get active status for sided plugin
	 *
	 * @param $index
	 * @param $options
	 * @param $plugin
	 * @param $type
	 * @param $handle
	 *
	 * @return bool
	 */
	private function getActiveStatusForSidedPlugin( $index, $options, $plugin, $type, $handle ) {
		$active = isset( $options[ $plugin ][ $type ] ) && is_array( $options[ $plugin ][ $type ] ) && in_array( $handle, $options[ $plugin ][ $type ] );

		/*if( !$active && !isset($options[$plugin]) ) {

				switch( $index ) {
					case 'wclp':
						if( class_exists('WCL_Plugin') ) {
							if( 'plugins' == $type ) {
								$active = WCL_Plugin::app()->getPopulateOption('remove_js_version', false);
								if( !$active ) {
									$active = WCL_Plugin::app()->getPopulateOption('remove_css_version', false);
								}
							} else {
								$active = WCL_Plugin::app()->getPopulateOption('remove_' . $type . '_version', false);
							}
						}
						break;
				}
			}*/

		return $active;
	}

	/**
	 * Get controls columns
	 *
	 * @param string $html
	 * @param string $type
	 * @param string $handle
	 * @param string $plugin_handle
	 *
	 * @return string
	 */
	public function getAdditionalControlsColumns( $html, $type, $handle, $plugin_handle ) {
		if ( ! empty( $this->sided_plugins ) ) {

			// todo: вынести в метод
			if ( is_multisite() && is_network_admin() ) {
				$options = $this->getNetworkOption( 'assets_manager_sided_plugins', [] );
			} else {
				$options = $this->getOption( 'assets_manager_sided_plugins', [] );
			}

			foreach ( $this->sided_plugins as $index => $plugin_path ) {
				if ( $this->isComponentActive( $index ) ) {
					$plugin = $this->getSidedPluginName( $index );

					$active = $this->getActiveStatusForSidedPlugin( $index, $options, $plugin, $type, $handle );
					$name   = "sided_plugins[{$plugin}][{$type}][{$handle}]";

					$html .= "<td>";

					if ( ! empty( $handle ) && ( 'plugins' != $type && false !== strpos( $handle, '.' . $type ) || 'plugins' == $type ) ) {
						$html .= '<label class="wbcr-gnz-switch">';
						$html .= '<input class="wbcr-gnz-switch__input visually-hidden wbcr-gnz-sided-disable';
						$html .= ( 'plugins' != $type ? ' wbcr-gnz-sided-' . $index . '-' . $plugin_handle : '' );
						$html .= '" type="checkbox"' . checked( $active, true, false );
						$html .= ( 'plugins' == $type ? ' data-handle="' . $index . '-' . $plugin_handle . '"' : '' ) . '/>';
						$html .= '<input type="hidden" name="' . $name . '" value="' . ( $active ? 1 : 0 ) . '"/>';
						$html .= '<span class="wbcr-gnz-switch__inner" data-off="' . __( 'No', 'gonzales' ) . '" data-on="' . __( 'Yes', 'gonzales' ) . '"></span>';
						$html .= '<span class="wbcr-gnz-switch__slider"></span>';
						$html .= '</label>';
					}
					$html .= "</td>";
				}
			}
		}

		return $html;
	}

	/**
	 * @param $index
	 * @param $type
	 * @param $exclude
	 *
	 * @return array
	 */
	private function filterExclusions( $index, $type, $exclude ) {
		$files = $this->getSidedPluginFiles( $index, $type );

		if ( ! empty( $files ) ) {
			if ( is_array( $exclude ) ) {
				$exclude = array_merge( $exclude, $files );
			} else {
				$dontmove = implode( ',', $files );
				$exclude  .= ! empty( $exclude ) ? ',' . $dontmove : $dontmove;
			}
		}

		return $exclude;
	}

	/**
	 * aopt filter js exclude
	 *
	 * @param $exclude
	 * @param $content
	 *
	 * @return array
	 */
	public function aoptFilterJsExclude( $exclude, $content ) {
		return $this->filterExclusions( 'aopt', 'js', $exclude );
	}

	/**
	 * aopt filter css exclude
	 *
	 * @param $exclude
	 * @param $content
	 *
	 * @return array
	 */
	public function aoptFilterCssExclude( $exclude, $content ) {
		return $this->filterExclusions( 'aopt', 'css', $exclude );
	}

	/**
	 * wmac filter js exclude
	 *
	 * @param $exclude
	 * @param $content
	 *
	 * @return array
	 */
	public function wmacFilterJsExclude( $exclude, $content ) {
		return $this->filterExclusions( 'wmac', 'js', $exclude );
	}

	/**
	 * wmac filter css exclude
	 *
	 * @param $exclude
	 * @param $content
	 *
	 * @return array
	 */
	public function wmacFilterCssExclude( $exclude, $content ) {
		return $this->filterExclusions( 'wmac', 'css', $exclude );
	}

	/**
	 * Filter js minify exclusions
	 *
	 * @param $index
	 * @param $type
	 * @param $result
	 * @param $url
	 *
	 * @return bool
	 */
	private function filterJsMinifyExclusions( $index, $type, $result, $url ) {
		$files = $this->getSidedPluginFiles( $index, $type );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( false !== strpos( $url, $file ) ) {
					return false;
				}
			}
		}

		return $result;
	}

	/**
	 * Action wmac_filter_js_minify_excluded
	 *
	 * @param $result
	 * @param $url
	 *
	 * @return mixed
	 */
	public function wmacFilterJsMinifyExclude( $result, $url ) {
		return $this->filterJsMinifyExclusions( 'wmac', 'js', $result, $url );
	}

	/**
	 * Action wmac_filter_css_minify_excluded
	 *
	 * @param $result
	 * @param $url
	 *
	 * @return mixed
	 */
	public function wmacFilterCssMinifyExclude( $result, $url ) {
		return $this->filterJsMinifyExclusions( 'wmac', 'css', $result, $url );
	}

	/**
	 * Manage excluded files
	 *
	 * @param $sided_exclude_files
	 * @param $index
	 * @param $type
	 */
	private function manageExcludeFiles( $sided_exclude_files, $index, $type ) {
		switch ( $index ) {
			case 'aopt':
				if ( get_option( 'autoptimize_js', false ) || get_option( 'autoptimize_css', false ) ) {
					$exclude_files = get_option( 'autoptimize_' . $type . '_exclude', '' );
				} else {
					return;
				}
				break;
			case 'wmac':
				if ( class_exists( 'WMAC_Plugin' ) && ( WMAC_Plugin::app()->getPopulateOption( 'js_optimize', false ) || WMAC_Plugin::app()->getPopulateOption( 'css_optimize', false ) ) ) {
					$exclude_files = WMAC_Plugin::app()->getPopulateOption( $type . '_exclude', '' );
				} else {
					return;
				}
				break;
			case 'wclp':
				if ( class_exists( 'WCL_Plugin' ) && ( WCL_Plugin::app()->getPopulateOption( 'remove_js_version', false ) || WCL_Plugin::app()->getPopulateOption( 'remove_css_version', false ) ) ) {
					$exclude_files = WCL_Plugin::app()->getPopulateOption( 'remove_version_exclude', '' );
				} else {
					return;
				}
				break;
			default:
				return;
		}

		// For clearfy need new line
		$delimeter             = $index == 'wclp' ? "\n" : ",";
		$current_exclude_files = ! empty( $exclude_files ) ? array_filter( array_map( 'trim', explode( $delimeter, $exclude_files ) ) ) : [];

		$delete_files = array_diff( $sided_exclude_files['before'][ $type ], $sided_exclude_files['after'][ $type ] );
		$new_files    = array_diff( $sided_exclude_files['after'][ $type ], $current_exclude_files );

		if ( empty( $current_exclude_files ) && ! empty( $new_files ) ) {
			$current_exclude_files = $new_files;
		} else if ( ! empty( $current_exclude_files ) ) {
			$new_exclude_files = [];
			foreach ( $current_exclude_files as $file ) {

				if ( ! in_array( $file, $delete_files ) ) {
					$new_exclude_files[] = $file;
				}
			}
			$current_exclude_files = array_merge( $new_exclude_files, $new_files );
		}

		$current_exclude_files = array_filter( array_unique( $current_exclude_files ) );

		switch ( $index ) {
			case 'aopt':
				update_option( 'autoptimize_' . $type . '_exclude', implode( ', ', $current_exclude_files ) );
				break;
			case 'wmac':
				if ( class_exists( 'WMAC_Plugin' ) ) {
					WMAC_Plugin::app()->updatePopulateOption( $type . '_exclude', implode( ', ', $current_exclude_files ) );
				}
				break;
			case 'wclp':
				if ( class_exists( 'WCL_Plugin' ) ) {
					WCL_Plugin::app()->updatePopulateOption( 'remove_version_exclude', implode( $delimeter, $current_exclude_files ) );
				}
				break;
		}
	}

	/**
	 * Action form save
	 *
	 * @param bool $empty_before
	 */
	public function actionFormSave( $empty_before = false ) {
		if ( ! empty( $this->sided_plugins ) && ! $empty_before ) {
			foreach ( $this->sided_plugins as $index => $sided_plugin ) {
				$sided_exclude_files[ $index ]['before'] = [
					'js'  => [],
					'css' => []
				];
				// For clearfy need full url
				$full = ( $index == 'wclp' ? true : false );

				$sided_exclude_files[ $index ]['before']['js']  += $this->getSidedPluginFiles( $index, 'js', $full );
				$sided_exclude_files[ $index ]['before']['css'] += $this->getSidedPluginFiles( $index, 'css', $full );
			}
		}

		if ( isset( $_POST['sided_plugins'] ) && ! empty( $_POST['sided_plugins'] ) ) {
			$sided_plugins_options = [];
			foreach ( $_POST['sided_plugins'] as $plugin => $types ) {
				foreach ( $types as $type => $urls ) {
					foreach ( $urls as $url => $active ) {

						if ( ! empty( $url ) && $active ) {
							$sided_plugins_options[ $plugin ][ $type ][] = $url;
						}
					}
				}
			}

			if ( is_multisite() && is_network_admin() ) {
				$this->updateNetworkOption( 'assets_manager_sided_plugins', $sided_plugins_options );
			} else {
				$this->updateOption( 'assets_manager_sided_plugins', $sided_plugins_options );
			}
		}

		if ( ! empty( $this->sided_plugins ) ) {
			$this->sided_plugin_files = [];
			foreach ( $this->sided_plugins as $index => $sided_plugin ) {
				$sided_exclude_files[ $index ]['after'] = [
					'js'  => [],
					'css' => []
				];
				// For clearfy need full url
				$full = ( $index == 'wclp' ? true : false );

				$sided_exclude_files[ $index ]['after']['js']  += $this->getSidedPluginFiles( $index, 'js', $full );
				$sided_exclude_files[ $index ]['after']['css'] += $this->getSidedPluginFiles( $index, 'css', $full );

				if ( ! empty( $sided_exclude_files[ $index ]['before']['js'] ) || ! empty( $sided_exclude_files[ $index ]['after']['js'] ) ) {
					$this->manageExcludeFiles( $sided_exclude_files[ $index ], $index, 'js' );
				}

				if ( ! empty( $sided_exclude_files[ $index ]['before']['css'] ) || ! empty( $sided_exclude_files[ $index ]['after']['css'] ) ) {
					$this->manageExcludeFiles( $sided_exclude_files[ $index ], $index, 'css' );
				}
			}
		}
	}

	private function get_conditions_login_params( $group = false ) {
		global $wp_roles;

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

		$grouped_filter_params = [
			[
				'id'    => 'user',
				'title' => __( 'User', 'gonzales' ),
				'items' => [
					[
						'id'          => 'user-role',
						'title'       => __( 'Role', 'gonzales' ),
						'type'        => 'select',
						'params'      => $roles_param_values,
						'description' => __( 'A role of the user who views your website. The role "guest" is applied to unregistered users.', 'gonzales' )
					],
					/*[
						'id'          => 'user-registered',
						'title'       => __( 'Registration Date', 'gonzales' ),
						'type'        => 'date',
						'description' => __( 'The date when the user who views your website was registered. For unregistered users this date always equals to 1 Jan 1970.', 'gonzales' )
					],*/
					[
						'id'          => 'user-mobile',
						'title'       => __( 'Mobile Device', 'gonzales' ),
						'type'        => 'select',
						'params'      => [
							[ 'value' => 'yes', 'title' => __( 'Yes', 'gonzales' ) ],
							[ 'value' => 'no', 'title' => __( 'No', 'gonzales' ) ]
						],
						'description' => __( 'Determines whether the user views your website from mobile device or not.', 'gonzales' )
					],
					[
						'id'          => 'user-cookie-name',
						'title'       => __( 'Cookie Name', 'gonzales' ),
						'type'        => 'text',
						'only_equals' => true,
						'description' => __( 'Determines whether the user\'s browser has a cookie with a given name.', 'gonzales' )
					]
				]
			],
			[
				'id'    => 'location',
				'title' => __( 'Location', 'gonzales' ),
				'items' => [
					[
						'id'          => 'current-url',
						'title'       => __( 'Current URL', 'gonzales' ),
						'type'        => 'select',
						'params'      => [
							[ 'value' => 'yes', 'title' => __( 'Yes', 'gonzales' ) ],
							[ 'value' => 'no', 'title' => __( 'No', 'gonzales' ) ]
						],
						'description' => __( 'Current Url', 'gonzales' )
					],
					[
						'id'          => 'location-page',
						'title'       => __( 'Custom URL', 'gonzales' ),
						'type'        => 'text',
						'description' => __( 'An URL of the current page where a user who views your website is located.', 'gonzales' )
					],
					[
						'id'          => 'regular-expression',
						'title'       => __( 'Regular Expression', 'gonzales' ),
						'type'        => 'text',
						'description' => __( 'Regular expressions can be used by experts. This tool creates flexible conditions to disable the resource. For example, if you specify this expression: ^([A-z0-9]+-)?gifts? then the resource will be disabled at the following pages http://yoursite.test/get-gift/, http://yoursite.test/gift/, http://yoursite.test/get-gifts/, http://yoursite.test/gifts/. The plugin ignores the backslash at the beginning of the query string, so you can dismiss it. Check your regular expressions in here: https://regex101.com, this will prevent you from the mistakes. This feature is available at the paid version.', 'gonzales' )
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
									'title' => __( '404 Page', 'insert-php' ),
								],
								[
									'value' => 'spec_search',
									'title' => __( 'Search Page', 'insert-php' ),
								],
								[
									'value' => 'spec_blog',
									'title' => __( 'Blog / Posts Page', 'insert-php' ),
								],
								[
									'value' => 'spec_front',
									'title' => __( 'Front Page', 'insert-php' ),
								],
								[
									'value' => 'spec_date',
									'title' => __( 'Date Archive', 'insert-php' ),
								],
								[
									'value' => 'spec_auth',
									'title' => __( 'Author Archive', 'insert-php' ),
								],
							],
							'Posts'         => [
								[
									'value' => 'post_all',
									'title' => __( 'All Posts', 'insert-php' ),
								],
								[
									'value' => 'post_arch',
									'title' => __( 'All Posts Archive', 'insert-php' ),
								],
								[
									'value' => 'post_cat',
									'title' => __( 'All Categories Archive', 'insert-php' ),
								],
								[
									'value' => 'post_tag',
									'title' => __( 'All Tags Archive', 'insert-php' ),
								],
							],
							'Pages'         => [
								[
									'value' => 'page_all',
									'title' => __( 'All Pages', 'insert-php' ),
								],
								[
									'value' => 'page_arch',
									'title' => __( 'All Pages Archive', 'insert-php' ),
								],
							],

						],
						'description' => __( 'List of specific pages.', 'gonzales' )
					],
					/*[
						'id'          => 'location-referrer',
						'title'       => __( 'Current Referrer', 'gonzales' ),
						'type'        => 'text',
						'description' => __( 'A referrer URL which has brought a user to the current page.', 'gonzales' )
					],*/
					[
						'id'          => 'location-post-type',
						'title'       => __( 'Post type', 'gonzales' ),
						'type'        => 'select',
						'params'      => $post_types_param_values,
						'description' => __( 'A post type of the current page.', 'gonzales' )
					],
					[
						'id'          => 'location-taxonomy',
						'title'       => __( 'Taxonomy', 'gonzales' ),
						'type'        => 'select',
						'params'      => $taxonomies_param_values,
						'description' => __( 'A taxonomy of the current page.', 'gonzales' )
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