<?php
	
	/**
	 * Assets manager base class
	 * @author Webcraftic <wordpress.webraftic@gmail.com>
	 * @copyright (c) 05.11.2017, Webcraftic
	 * @version 1.0
	 */

	// Exit if accessed directly
	if( !defined('ABSPATH') ) {
		exit;
	}

	class WbcrGnz_ConfigAssetsManager extends Wbcr_FactoryClearfy000_Configurate {
		
		/**
		 * Stores list of all available assets (used in rendering panel)
		 *
		 * @var array
		 */
		private $collection = array();

		/**
		 * Plugins for additional columns
		 *
		 * @var array
		 */
		private $sided_plugins = array();

		/**
		 * Css and js files excluded in sided plugins
		 *
		 * @var array
		 */
		private $sided_plugin_files = array();

		/**
		 * @param Wbcr_Factory000_Plugin $plugin
		 */
		public function __construct(Wbcr_Factory000_Plugin $plugin)
		{
			parent::__construct($plugin);
			$this->plugin = $plugin;
		}

		/**
		 * Initilize entire machine
		 */
		function registerActionsAndFilters()
		{
			if( $this->getOption('disable_assets_manager', false) ) {
				return;
			}
			
			$on_frontend = $this->getOption('disable_assets_manager_on_front');
			$on_backend = $this->getOption('disable_assets_manager_on_backend', true);
			$is_panel = $this->getOption('disable_assets_manager_panel');

			if( (!is_admin() && !$on_frontend) || (is_admin() && !$on_backend) ) {
				add_filter('script_loader_src', array($this, 'unloadAssets'), 10, 2);
				add_filter('style_loader_src', array($this, 'unloadAssets'), 10, 2);
			}

			if( !$is_panel && ((is_admin() && !$on_backend) || (!is_admin() && !$on_frontend)) ) {
				if( !is_admin() ) {
					add_action('wp_enqueue_scripts', array($this, 'appendAsset'), -100001);
					add_action('wp_footer', array($this, 'assetsManager'), 100001);
				} else {
					add_action('admin_enqueue_scripts', array($this, 'appendAsset'), -100001);
					add_action('admin_footer', array($this, 'assetsManager'), 100001);
				}
			}

			if( !is_admin() && !$on_frontend ) {
				add_action('wp_head', array($this, 'collectAssets'), 10000);
				add_action('wp_footer', array($this, 'collectAssets'), 10000);
			}

			if( is_admin() && !$on_backend ) {
				add_action('admin_head', array($this, 'collectAssets'), 10000);
				add_action('admin_footer', array($this, 'collectAssets'), 10000);
			}

			if( !$is_panel && ((is_admin() && !$on_backend) || (!is_admin() && !$on_frontend)) ) {
				if( defined('LOADING_GONZALES_AS_ADDON') ) {
					add_action('wbcr_clearfy_admin_bar_menu_items', array($this, 'clearfyAdminBarMenu'));
				} else {
					add_action('admin_bar_menu', array($this, 'assetsManagerAdminBar'), 1000);
				}
			}

			if( !is_admin() && !$on_frontend ) {
				add_action('init', array($this, 'formSave'));
			}

			if( is_admin() && !$on_backend ) {
				add_action('admin_init', array($this, 'formSave'));
			}

			add_action( 'plugins_loaded', array( $this, 'pluginsLoaded' ) );
			add_action( 'wbcr_gnz_form_save', array( $this, 'actionFormSave' ) );

			add_filter( 'wbcr_gnz_show_float_panel', array( $this, 'showFloatPanel' ) );
			add_filter( 'wbcr_gnz_control_html', array( $this, 'showControlHtml' ), 10, 4 );
			add_filter( 'wbcr_gnz_unset_disabled', array( $this, 'unsetDisabled' ), 10, 2 );
			add_filter( 'wbcr_gnz_get_additional_head_columns', array( $this, 'getAdditionalHeadColumns' ) );
			add_filter( 'wbcr_gnz_get_additional_controls_columns', array( $this, 'getAdditionalControlsColumns' ), 10, 3 );

			add_filter( 'autoptimize_filter_js_exclude', array( $this, 'aoptFilterJsExclude' ), 10, 2 );
			add_filter( 'autoptimize_filter_css_exclude', array( $this, 'aoptFilterCssExclude' ), 10, 2 );
			add_filter( 'wmac_filter_js_exclude', array( $this, 'wmacFilterJsExclude' ), 10, 2 );
			add_filter( 'wmac_filter_css_exclude', array( $this, 'wmacFilterCssExclude' ), 10, 2 );
			add_filter( 'wmac_filter_js_minify_excluded', array( $this, 'wmacFilterJsMinifyExclude' ), 10, 2 );
			add_filter( 'wmac_filter_css_minify_excluded', array( $this, 'wmacFilterCssMinifyExclude' ), 10, 2 );
		}

		function clearfyAdminBarMenu($menu_items)
		{
			$current_url = add_query_arg(array('wbcr_assets_manager' => 1));

			$menu_items['assetsManager'] = array(
				'title' => __('Script Manager', 'gonzales') . ' (Beta)',
				'href' => $current_url
			);

			return $menu_items;
		}

		/**
		 * @param WP_Admin_Bar $wp_admin_bar
		 */
		function assetsManagerAdminBar($wp_admin_bar)
		{
			if( !current_user_can('manage_options') ) {
				return;
			}

			$current_url = add_query_arg(array('wbcr_assets_manager' => 1));

			$args = array(
				'id' => 'assetsManager',
				'title' => __('Script Manager', 'gonzales') . ' (Beta)',
				'href' => $current_url
			);
			$wp_admin_bar->add_node($args);
		}

		/**
		 * Action plugins loaded
		 */
		public function pluginsLoaded() {
			$this->sided_plugins = array(
				'aopt' => 'autoptimize/autoptimize.php',
				'wmac' => 'wp-plugin-minify-and-combine/minify-and-combine.php'
			);
			$this->sided_plugins = apply_filters( 'wbcr_gnz_sided_plugins', $this->sided_plugins );
		}

		function assetsManager()
		{
			if( !current_user_can('manage_options') || !isset($_GET['wbcr_assets_manager']) ) {
				return;
			}

			$current_url = esc_url($this->getCurrentUrl());
			$options = $this->getOption('assets_manager_options', array());

			echo "<div id='wbcr-assets-manager-wrapper' ";
			if( isset($_GET['wbcr_assets_manager']) ) {
				echo "style='display: block;'";
			}
			echo ">";
			
			echo "<div id='wbcr-assets-manager'>";
			
			//Header
			echo "<div class='wbcr-header'>";
			echo "<h2>Webcraftic " . __('Assets manager', 'gonzales') . " (Beta)</h2>";
			echo "<div class='wbcr-description'>";
			echo "<p>" . __('Below you can disable/enable CSS and JS files on a per page/post basis, as well as by custom post types. We recommend testing this locally or on a staging site first, as you could break the appearance of your live site. If you aren\'t sure about a certain script, you can try clicking on it, as a lot of authors will mention their plugin or theme in the header of the source code.', 'gonzales') . "</p>
						<p>" . __('If for some reason you run into trouble, you can always enable everything again to reset the settings.', 'gonzales') . "</p>";
			echo "</div>";
			echo "</div>";

			// Information
			echo "<table>";
			echo "<tr>";
			echo "<td width='100%'>";
			echo apply_filters( 'wbcr_gnz_show_information', '' );
			echo "</td>";
			echo "<td width='180px' class='wbcr-reset-column'>";
			echo "<button class='wbcr-reset-button'>" . __('Reset settings', 'gonzales') . "</button>";
			echo "</td>";
			echo "</tr>";
			echo "</table>";

			//Form
			echo "<form method='POST'>";
			wp_nonce_field('wbcr_assets_manager_nonce', 'wbcr_assets_manager_save');
			echo "<div class='wbcr-float-panel'>";
			echo "<input type='submit' value='" . __('Save settings', 'gonzales') . "' />";
			echo "<a href='" . remove_query_arg('wbcr_assets_manager') . "' class='wbcr-close'></a>";

			echo apply_filters( 'wbcr_gnz_show_float_panel', '' );

			$setting_page_url = !defined('LOADING_GONZALES_AS_ADDON')
				? 'options-general.php'
				: 'admin.php';
			$setting_page_url .= '?page=gonzales-' . $this->plugin->getPluginName();
			echo "<a href='" . admin_url($setting_page_url) . "' class='wbcr-hide-panel'>" . __('Hide panel in adminbar?', 'gonzales') . "</a>";
			echo "</div>";

			uksort($this->collection, function($a, $b) {
				if ( 'plugins' == $a ) {
					return -1;
				}

				if ( 'plugins' == $b ) {
					return 1;
				}

				return strcasecmp($a, $b);
			});

			global $plugin_state;

			foreach($this->collection as $resource_type => $resources) {
				echo "<h3>" . $resource_type . "</h3>";
				foreach($resources as $resource_name => $types) {
					$plugin_state = false;

					echo apply_filters(
						'wbcr_gnz_before_scripts_table', '', $this, $options, $resource_type, $resource_name, $current_url
					);

					echo "<div class='wbcr-section'>";
					echo "<table>";
					echo "<thead>";
					echo "<tr>";
					echo "<th style='width: 100px;'>" . __('Loaded', 'gonzales') . "</th>";
					echo "<th style='width: 75px;'>" . __('Size', 'gonzales') . "</th>";
					echo "<th style='width: 100%;'>" . __('Script', 'gonzales') . "</th>";
					echo apply_filters( 'wbcr_gnz_get_additional_head_columns', '' );
					echo "<th style='width: 200px;'>" . __('State', 'gonzales') . "</th>";
					echo "<th style='width: 300px;' class='wbcr-enable-th'>" . __('Enable', 'gonzales') . "</th>";
					echo "</tr>";
					echo "</thead>";
					echo "<tbody>";

					foreach($types as $type_name => $rows) {

						if( !empty($rows) ) {
							foreach($rows as $handle => $row) {
								$is_disabled = $this->getIsDisabled( $options, $type_name, $handle );
								$disabled = $this->getDisabled( $is_disabled, $options, $type_name, $handle );

								$is_enabled = $this->getIsEnabled( $options, $type_name, $handle );
								$enabled = $this->getEnabled( $is_enabled, $options, $type_name, $handle );

								/**
								 * Find dependency
								 */
								$deps = array();
								foreach($rows as $dep_key => $dep_val) {
									if( in_array($handle, $dep_val['deps']) /*&& $is_disabled*/ ) {
										$deps[] = '<a href="#' . $type_name . '-' . $dep_key . '">' . $dep_key . '</a>';
									}
								}

								$comment = (!empty($deps)
									? '<span style="color:#fb7976;" class="wbcr-use-by-comment">' . __('In use by', 'gonzales') . ' ' . implode(', ', $deps) . '</span>'
									: '');

								echo "<tr>";

								// Loaded
								$state = $this->getState( $is_disabled, $disabled, $current_url );
								$display_state = $plugin_state === 1 ? 1 : $state;

								echo '<td><div class="wbcr-state wbcr-state-' . (int)$state . ( $plugin_state == 1 ? ' wbcr-imp-state-1' : '');
								echo ( 'plugins' == $resource_type ? ' wbcr-state-' . $resource_name : '' ) . '">';
								echo ( ! $display_state ? __( 'Yes', 'gonzales' ) : __( 'No', 'gonzales' ) ) . '</div></td>';

								//Size
								echo "<td class='wbcr-assets-manager-size'>";
								echo $row['size'] . ' KB';
								echo "</td>";

								// Handle + Path + In use
								echo "<td class='wbcr-script'><span>" . $handle . "</span>";
								echo "<a id='" . $type_name . "-" . $handle . "' class='wbcr-anchor'></a>";
								echo "<a href='" . $row['url_full'] . "' target='_blank'>";
								echo str_replace(get_home_url(), '', $row['url_full']) . "</a>";

								echo "<br>";
								echo $comment;
								echo "</td>";

								// Controls for other plugins
								echo apply_filters( 'wbcr_gnz_get_additional_controls_columns', '', $type_name, $row['url_short'] );

								// State Controls
								$id = '[' . $type_name . '][' . $handle . ']';
								echo $this->getStateControrlHTML(
									$id, $state, $is_disabled, $is_enabled, $type_name, $handle, $disabled, $enabled, $current_url
								);

								echo "<input type='hidden' class='wbcr-info-data' data-type='{$type_name}' data-off='{$display_state}' value='{$row['size']}'>";
								echo "</tr>";

								echo apply_filters(
									'wbcr_gnz_after_scripts_table_row',
									'', $resource_type, $resource_name, $type_name, $handle
								);
							}
						}
					}

					echo "</tbody>";
					echo "</table>";
					echo "</div>";
				}
			}
			
			echo "</form>";
			echo "</div>";
			echo "</div>";
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
		public function getIsDisabled( $options, $type_name, $handle )
		{
			return isset($options['disabled']) && isset($options['disabled'][$type_name]) && isset($options['disabled'][$type_name][$handle]);
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
		public function getDisabled( $is_disabled, $options, $type_name, $handle )
		{
			$disabled = array();

			if( $is_disabled ) {
				$disabled = &$options['disabled'][$type_name][$handle];
				if( !isset($disabled['current']) ) {
					$disabled['current'] = array();
				}
				if( !isset($disabled['everywhere']) ) {
					$disabled['everywhere'] = array();
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
		public function getIsEnabled( $options, $type_name, $handle )
		{
			return isset($options['enabled']) && isset($options['enabled'][$type_name]) && isset($options['enabled'][$type_name][$handle]);
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
		public function getEnabled( $is_enabled, $options, $type_name, $handle )
		{
			$enabled = array();

			if( $is_enabled ) {
				$enabled = &$options['enabled'][$type_name][$handle];

				if( !isset($enabled['current']) ) {
					$enabled['current'] = array();
				}
				if( !isset($enabled['everywhere']) ) {
					$enabled['everywhere'] = array();
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
		public function getState( $is_disabled, $disabled, $current_url )
		{
			$state = 0;
			if(
				$is_disabled
				&& (
					$disabled['everywhere'] == 1
					|| in_array($current_url, $disabled['current'])
					|| apply_filters( 'wbcr_gnz_check_state_disabled', false, $disabled )
				)
			) {
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
			//Disable
			$html = "<td class='wbcr-assets-manager-disable'>";
			$html .= "<select name='disabled{$id}[state]' class='wbcr-gonzales-disable-select'";
			$html .= ('plugins' == $type_name ? "data-handle='{$handle}'" : "" ) . ">";
			$html .= "<option value='' class='wbcr-gonzales-option-enabled'>" . __('Enabled', 'gonzales') . "</option>";
			$html .= "<option value='disable' class='wbcr-gonzales-option-disable' ";
			if( $state ) {
				$html .= "selected";
			}
			$html .= ">" . __('Disable', 'gonzales') . "</option>";
			$html .= "</select>";
			$html .= "</td>";
			//Enable
			$html .= "<td>";
			$html .= "<span class='wbcr-assets-manager-enable-placeholder' ";
			if( $state ) {
				$html .= "style='display: none;'";
			}
			$html .= ">" . __('Disable everwhere to view enable settings.', 'gonzales') . "</span>";
			$html .= "<span class='wbcr-assets-manager-enable'";
			if( ! $state ) {
				$html .= " style='display: none;'";
			}
			$html .= ">";
			$html .= "<div>";
			$html .= "<select name='action{$id}' class='wbcr-gonzales-action-select'>";
			$html .= "<option value='current'" . selected( $is_disabled && ! empty( $disabled['current'] ), true, false ) . ">" . __( 'Current URL', 'gonzales' ) . "</option>";
			$html .= "<option value='everywhere'" . selected( $is_disabled && ! empty( $disabled['everywhere'] ), true, false ) . ">" . __( 'Everywhere', 'gonzales' ) . "</option>";
			$html .= "<option value='custom'" . selected( $is_disabled && ! empty( $disabled['custom'] ), true, false ) . ">" . __( 'Custom URL', 'gonzales' ) . "</option>";
			$html .= "<option value='regex'" . selected( $is_disabled && ! empty( $disabled['regex'] ), true, false ) . ">" . __( 'Regular expression', 'gonzales' ) . "</option>";
			$html .= "</select>";
			$html .= "</div>";
			$html .= "<div class='wbcr-assets-manager everywhere'";
			if( !$is_disabled || empty($disabled['everywhere']) ) {
				$html .= " style='display: none;'";
			}
			$html .= ">";
			$html .= "<input type='hidden' name='enabled{$id}[current]' value='' />";
			$html .= "<span><strong>" . __('Exclude', 'gonzales') . ":</strong></span><br>";
			$html .= "<label for='" . $type_name . "-" . $handle . "-enable-current'>";
			$html .= "<input type='checkbox' name='enabled{$id}[current]' id='" . $type_name . "-" . $handle . "-enable-current' value='" . $current_url . "' ";

			if( $is_enabled && in_array($current_url, $enabled['current']) ) {
				$html .= "checked";
			}

			$html .= " />" . __('Current URL', 'gonzales');
			$html .= "</label>";

			$post_types = get_post_types(array('public' => true), 'objects', 'and');
			if( !empty($post_types) ) {
				$html .= "<input type='hidden' name='enabled{$id}[post_types]' value='' />";
				foreach($post_types as $key => $value) {
					$html .= "<label for='" . $type_name . "-" . $handle . "-enable-" . $key . "'>";
					$html .= "<input type='checkbox' name='enabled{$id}[post_types][]' id='" . $type_name . "-" . $handle . "-enable-" . $key . "' value='" . $key . "' ";
					if( isset($enabled['post_types']) ) {
						if( in_array($key, $enabled['post_types']) ) {
							$html .= "checked";
						}
					}
					$html .= " />" . $value->label;
					$html .= "</label>";
				}
			}

			$taxonomies = get_taxonomies(array('public' => true), 'objects', 'and');
			if( !empty($taxonomies) ) {
				unset($taxonomies['category']);
				$html .= "<input type='hidden' name='enabled{$id}[taxonomies]' value='' />";
				foreach($taxonomies as $key => $value) {
					$html .= "<label for='" . $type_name . "-" . $handle . "-enable-" . $key . "'>";
					$html .= "<input type='checkbox' name='enabled{$id}[taxonomies][]' id='" . $type_name . "-" . $handle . "-enable-" . $key . "' value='" . $key . "' ";
					if( isset($enabled['taxonomies']) ) {
						if( in_array($key, $enabled['taxonomies']) ) {
							$html .= "checked";
						}
					}
					$html .= " />" . $value->label;
					$html .= "</label>";
				}
			}

			$categories = get_categories();
			if( !empty($categories) ) {
				$html .= "<input type='hidden' name='enabled{$id}[categories]' value='' />";
				foreach($categories as $key => $cat) {
					$html .= "<label for='" . $type_name . "-" . $handle . "-enable-" . $cat->term_id . "'>";
					$html .= "<input type='checkbox' name='enabled{$id}[categories][]' id='" . $type_name . "-" . $handle . "-enable-" . $cat->term_id . "' value='" . $cat->term_id . "' ";
					if( isset($enabled['categories']) ) {
						if( in_array($cat->term_id, $enabled['categories']) ) {
							$html .= "checked";
						}
					}
					$html .= " />" . $cat->name;
					$html .= "</label>";
				}
			}

			$html .= "</div>";

			$html .= apply_filters('wbcr_gnz_control_html', '', $id, $is_disabled, $disabled );

			$html .= "</span>";

			if (
				isset( $disabled['current'] )
				&& ! empty( $disabled['current'] )
			) {
				$custom_urls = "";

				foreach ( $disabled['current'] as $item_url ) {
					if ( $current_url != $item_url ) {
						$full_url    = site_url() . $item_url;
						$custom_urls .= "<span><a href='" . $full_url . "'>" . $full_url . "</a></span>";
					}
				}

				if ( ! empty( $custom_urls ) ) {
					$html .= "<div class='wbcr-disabled-info'>" . __('Also disabled for', 'gonzales') . ":" . $custom_urls . "</div>";
				}
			}
			$html .= "</td>";

			return $html;
		}
		
		public function formSave()
		{
			if( isset($_GET['wbcr_assets_manager']) && isset($_POST['wbcr_assets_manager_save']) ) {

				if( !current_user_can('manage_options') || !wp_verify_nonce(filter_input(INPUT_POST, 'wbcr_assets_manager_save'), 'wbcr_assets_manager_nonce') ) {
					return;
				}

				$options = $this->getOption('assets_manager_options', array());
				$current_url = esc_url($this->getCurrentUrl());

				if( isset($_POST['disabled']) && !empty($_POST['disabled']) ) {
					foreach($_POST['disabled'] as $type => $assets) {
						if( !empty($assets) ) {
							foreach($assets as $handle => $where) {
								$handle = sanitize_text_field($handle);
								$where = sanitize_text_field($where['state']);
								
								if( !isset($options['disabled'][$type][$handle]) ) {
									$options['disabled'][$type][$handle] = array();
								}
								$disabled = &$options['disabled'][$type][$handle];
								
								if( !empty($where) && 'disable' == $where ) {
									$action = isset( $_POST['action'][ $type ][ $handle ] )
										? $_POST['action'][ $type ][ $handle ]
										: '';

									if( "everywhere" == $action ) {
										$disabled = apply_filters( 'wbcr_gnz_unset_disabled', $disabled, $action );

										$disabled['everywhere'] = 1;
									} elseif( "current" == $action ) {
										$disabled = apply_filters( 'wbcr_gnz_unset_disabled', $disabled, $action );
										
										if( !isset($disabled['current']) || !is_array($disabled['current']) ) {
											$disabled['current'] = array();
										}
										
										if( !in_array($current_url, $disabled['current']) ) {
											array_push($disabled['current'], $current_url);
										}
									} else {
										$post_value = isset( $_POST['disabled'][$type][$handle] )
											? $_POST['disabled'][$type][$handle]
											: null ;
										$disabled = apply_filters( 'wbcr_gnz_pre_save_disabled', $disabled, $action, $post_value );
									}
								} else {
									$disabled = apply_filters( 'wbcr_gnz_unset_disabled', $disabled, 'current' );
									
									if( isset($disabled['current']) ) {
										$current_key = array_search($current_url, $disabled['current']);
										
										if( !empty($current_key) || $current_key === 0 ) {
											unset($disabled['current'][$current_key]);
											if( empty($disabled['current']) ) {
												unset($disabled['current']);
											}
										}
									}
								}
								
								if( empty($disabled) ) {
									unset($options['disabled'][$type][$handle]);
									if( empty($options['disabled'][$type]) ) {
										unset($options['disabled'][$type]);
										if( empty($options['disabled']) ) {
											unset($options['disabled']);
										}
									}
								}
							}
						}
					}
				}
				
				if( isset($_POST['enabled']) && !empty($_POST['enabled']) ) {
					foreach($_POST['enabled'] as $type => $assets) {
						if( !empty($assets) ) {
							foreach($assets as $handle => $where) {

								if( !isset($options['enabled'][$type][$handle]) ) {
									$options['enabled'][$type][$handle] = array();
								}
								$enabled = &$options['enabled'][$type][$handle];

								$action = isset( $_POST['action'][ $type ][ $handle ] )
									? $_POST['action'][ $type ][ $handle ]
									: '';
								
								if(
									"everywhere" == $action
									&& (!empty($where['current']) || $where['current'] === "0")
								) {
									if( !isset($enabled['current']) || !is_array($enabled['current']) ) {
										$enabled['current'] = array();
									}
									if( !in_array($where['current'], $enabled['current']) ) {
										array_push($enabled['current'], $where['current']);
									}
								} else {
									if( isset($enabled['current']) ) {
										$current_key = array_search($current_url, $enabled['current']);
										if( !empty($current_key) || $current_key === 0 ) {
											unset($enabled['current'][$current_key]);
											if( empty($enabled['current']) ) {
												unset($options['enabled'][$type][$handle]['current']);
											}
										}
									}
								}
								
								if( "everywhere" == $action && !empty($where['post_types']) ) {
									$enabled['post_types'] = array();
									foreach($where['post_types'] as $key => $post_type) {
										if( isset($enabled['post_types']) ) {
											if( !in_array($post_type, $enabled['post_types']) ) {
												array_push($enabled['post_types'], $post_type);
											}
										}
									}
								} else {
									unset($enabled['post_types']);
								}

								if( "everywhere" == $action && !empty($where['taxonomies']) ) {
									$enabled['taxonomies'] = array();
									foreach($where['taxonomies'] as $key => $taxonomy) {
										if( isset($enabled['taxonomies']) ) {
											if( !in_array($taxonomy, $enabled['taxonomies']) ) {
												array_push($enabled['taxonomies'], $taxonomy);
											}
										}
									}
								} else {
									unset($enabled['taxonomies']);
								}

								if( "everywhere" == $action && !empty($where['categories']) ) {
									$enabled['categories'] = array();
									foreach($where['categories'] as $key => $category) {
										if( isset($enabled['categories']) ) {
											if( !in_array($category, $enabled['categories']) ) {
												array_push($enabled['categories'], $category);
											}
										}
									}
								} else {
									unset($enabled['categories']);
								}
								
								if( empty($enabled) ) {
									unset($options['enabled'][$type][$handle]);
									if( empty($options['enabled'][$type]) ) {
										unset($options['enabled'][$type]);
										if( empty($options['enabled']) ) {
											unset($options['enabled']);
										}
									}
								}
							}
						}
					}
				}

				do_action('wbcr_gnz_form_save');

				$this->updateOption( 'assets_manager_options', $options );

				// todo: test cache control
				if( function_exists('w3tc_pgcache_flush') ) {
					w3tc_pgcache_flush();
				} elseif( function_exists('wp_cache_clear_cache') ) {
					wp_cache_clear_cache();
				} elseif( function_exists('rocket_clean_files') ) {
					rocket_clean_files(esc_url($_SERVER['HTTP_REFERER']));
				} else if( isset($GLOBALS['wp_fastest_cache']) && method_exists($GLOBALS['wp_fastest_cache'], 'deleteCache') ) {
					$GLOBALS['wp_fastest_cache']->deleteCache();
				}
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
			$options = $this->getOption( 'assets_manager_options', array() );

			$results = apply_filters( 'wbcr_gnz_get_disabled_from_options', false, $options, $type, $handle );
			if ( false !== $results ) {
				return $results;
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
			$options = $this->getOption( 'assets_manager_options', array() );

			$results = apply_filters( 'wbcr_gnz_get_enabled_from_options', false, $options, $type, $handle );
			if ( false !== $results ) {
				return $results;
			}

			if ( isset( $options['enabled'] ) && isset( $options['enabled'][ $type ] ) && isset( $options['enabled'][ $type ][ $handle ] ) ) {
				return $options['enabled'][ $type ][ $handle ];
			}

			return null;
		}
		
		function unloadAssets($src, $handle)
		{
			if( isset($_GET['wbcr_assets_manager']) ) {
				return $src;
			}

			if( apply_filters( 'wbcr_gnz_check_unload_assets', false ) ) {
				return $src;
			}

			$type = (current_filter() == 'script_loader_src')
				? 'js'
				: 'css';

			$current_url = esc_url($this->getCurrentUrl());

			$disabled = $this->getDisabledFromOptions( $type, $handle );
			$enabled  = $this->getEnabledFromOptions( $type, $handle );

			if(
				(isset($disabled['everywhere']) && $disabled['everywhere'] == 1)
				|| (isset($disabled['current']) && is_array($disabled['current']) && in_array($current_url, $disabled['current']))
				|| apply_filters( 'wbcr_gnz_check_disabled_is_set', false, $disabled, $current_url )
			) {

				if( isset($enabled['current']) && is_array($enabled['current']) && in_array($current_url, $enabled['current']) ) {
					return $src;
				}

				if ( apply_filters( 'wbcr_gnz_check_unload_disabled', false, $disabled, $current_url ) ) {
					return $src;
				}

				if( isset($enabled['post_types']) && in_array(get_post_type(), $enabled['post_types']) ) {
					return $src;
				}

				if( isset($enabled['taxonomies']) && in_array(get_queried_object()->taxonomy, $enabled['taxonomies']) ) {
					return $src;
				}

				if( isset($enabled['categories']) && in_array(get_query_var('cat'), $enabled['categories']) ) {
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
		public function collectAssets()
		{
			$denied = array(
				'js' => array('wbcr-assets-manager', 'wbcr-comments-plus-url-span', 'admin-bar'),
				'css' => array('wbcr-assets-manager', 'wbcr-comments-plus-url-span', 'admin-bar', 'dashicons'),
			);
			$denied = apply_filters( 'wbcr_gnz_denied_assets', $denied );

			/**
			 * Imitate full untouched list without dequeued assets
			 * Appends part of original table. Safe approach.
			 */
			$data_assets = array(
				'js' => wp_scripts(),
				'css' => wp_styles(),
			);

			foreach($data_assets as $type => $data) {
				foreach($data->done as $el) {
					if( !in_array($el, $denied[$type]) ) {
						if( isset($data->registered[$el]->src) ) {
							$url = $this->prepareCorrectUrl($data->registered[$el]->src);
							$url_short = str_replace(get_home_url(), '', $url);

							if( false !== strpos($url, get_theme_root_uri()) ) {
								$resource_type = 'theme';
							} elseif( false !== strpos($url, plugins_url()) ) {
								$resource_type = 'plugins';
							} else {
								$resource_type = 'misc';
							}

							$resource_name = apply_filters( 'wbcr_gnz_get_resource_name', '', $resource_type, $url );

							$this->collection[$resource_type][$resource_name][$type][$el] = array(
								'url_full' => $url,
								'url_short' => $url_short,
								//'state' => $this->get_visibility($type, $el),
								'size' => $this->getAssetSize($url),
								'deps' => (isset($data->registered[$el]->deps)
									? $data->registered[$el]->deps
									: array()),
							);
						}
					}
				}
			}

			return false;
		}

		/**
		 * Loads functionality that allows to enable/disable js/css without site reload
		 */
		public function appendAsset()
		{
			if( current_user_can('manage_options') && isset($_GET['wbcr_assets_manager']) ) {
				wp_enqueue_style('wbcr-assets-manager', WGZ_PLUGIN_URL . '/assets/css/assets-manager.css', array(), $this->plugin->getPluginVersion());
				wp_enqueue_script('wbcr-assets-manager', WGZ_PLUGIN_URL . '/assets/js/assets-manager.js', array(), $this->plugin->getPluginVersion(), true);

				$translations = [
					'text' => [
						'yes' => __( 'Yes', 'gonzales' ),
						'no'  => __( 'No', 'gonzales' )
					]
				];
				wp_localize_script( 'wbcr-assets-manager', 'wbcram_data', $translations );
			}
		}

		/**
		 * Exception for address starting from "//example.com" instead of
		 * "http://example.com". WooCommerce likes such a format
		 *
		 * @param  string $url Incorrect URL.
		 * @return string      Correct URL.
		 */
		private function prepareCorrectUrl($url)
		{
			if( isset($url[0]) && isset($url[1]) && '/' == $url[0] && '/' == $url[1] ) {
				$out = (is_ssl()
						? 'https:'
						: 'http:') . $url;
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
		private function getCurrentUrl()
		{
			$url = explode('?', $_SERVER['REQUEST_URI'], 2);
			if( strlen($url[0]) > 1 ) {
				$out = rtrim($url[0], '/');
			} else {
				$out = $url[0];
			}
			
			return $out;
		}

		/**
		 * Checks how heavy is file
		 *
		 * @param  string $src URL.
		 * @return int    Size in KB.
		 */
		private function getAssetSize($src)
		{
			$weight = 0;
			
			$home = get_theme_root() . '/../..';
			$src = explode('?', $src);

			if( !filter_var($src[0], FILTER_VALIDATE_URL) === false && strpos($src[0], get_home_url()) === false ) {
				return 0;
			}
			
			$src_relative = $home . str_replace(get_home_url(), '', $this->prepareCorrectUrl($src[0]));

			if( file_exists($src_relative) ) {
				$weight = round(filesize($src_relative) / 1024, 1);
			}
			
			return $weight;
		}

		/**
		 * Show for admin only form
		 *
		 * @param string $html
		 *
		 * @return string
		 */
		public function showFloatPanel( $html ) {
			$html = "<label class='wbcr-label'>";
			$html .= "<input type='checkbox' value='1' name='wbcr_for_admin' disabled='disabled' checked>" . __('For administrator only', 'gonzales') . "</label>";
			$html .= "</label>";

			return $html;
		}

		/**
		 * Show control html
		 *
		 * @param string $html
		 * @param string $id
		 * @param bool $is_disabled
		 * @param array $disabled
		 *
		 * @return string
		 */
		public function showControlHtml( $html, $id, $is_disabled, $disabled ) {
			$html = "<div class='wbcr-assets-manager custom'";
			if( !$is_disabled || empty($disabled['custom']) ) {
				$html .= " style='display: none;'";
			}
			$html .= ">";
			$html .= "<span title='" . __('Example', 'gonzales') . ': ' . site_url() . "/post/*, " . site_url() . "/page-*'><strong>" . __('Enter URL (set * for mask)', 'gonzales') . ":</strong></span><br>";
			$html .= "<input type='text' name='disabled{$id}[custom][]' class='wbcr-gonzales-text' disabled='disabled' value='' style='background-color: #f3f3f3'>";
			$html .= "</div>";

			$html .= "<div class='wbcr-assets-manager regex'";
			if( !$is_disabled || empty($disabled['regex']) ) {
				$html .= " style='display: none;'";
			}
			$html .= ">";
			$html .= "<span><strong>" . __('Enter regular expression', 'gonzales') . ":</strong></span><br>";
			$html .= "<input type='text' name='disabled{$id}[regex]' class='wbcr-gonzales-text' disabled='disabled' value='' style='background-color: #f3f3f3'>";
			$html .= "</div>";

			return $html;
		}

		/**
		 * Unset disabled
		 *
		 * @param $disabled
		 * @param $action
		 *
		 * @return mixed
		 */
		public function unsetDisabled( $disabled, $action )
		{
			if ( "everywhere" == $action ) {
				unset( $disabled['current'] );
			} elseif ( "current" == $action ) {
				unset( $disabled['everywhere'] );
			}

			return $disabled;
		}

		/**
		 * Get sided plugin name
		 * 
		 * @param string $index
		 *
		 * @return string
		 */
		private function getSidedPluginName( $index ) {
			if ( isset( $this->sided_plugins[ $index ] ) ) {
				$parts  = explode( '/', $this->sided_plugins[ $index ] );
				return isset( $parts[0] ) ? $parts[0] : $this->sided_plugins[ $index ];
			}
			
			return "";
		}

		/**
		 * Get exclude sided plugin files
		 *
		 * @param $index
		 * @param $type
		 *
		 * @return array
		 */
		private function getSidedPluginFiles( $index, $type ) {
			if (
				isset( $this->sided_plugin_files[ $index ][ $type ] )
				&& ! empty( $this->sided_plugin_files[ $index ][ $type ] )
			) {
				return $this->sided_plugin_files[ $index ][ $type ];
			}

			$this->sided_plugin_files[ $index ][ $type ] = [];

			$options = $this->getOption( 'assets_manager_sided_plugins', [] );
			$plugin  = $this->getSidedPluginName( $index );

			if ( $plugin && $options ) {
				if ( isset( $options[ $plugin ][ $type ] ) ) {
					$urls = $options[ $plugin ][ $type ];

					if ( is_array( $urls ) ) {
						foreach ( $urls as $url ) {
							$parts = explode( '/', $url );
							$file  = array_pop( $parts );
							if ( empty( $file ) ) {
								$file = $url;
							}
							$this->sided_plugin_files[ $index ][ $type ][] = $file;
						}
					}
				}
			}

			return $this->sided_plugin_files[ $index ][ $type ];
		}

		/**
		 * Get head columns
		 *
		 * @param $html
		 *
		 * @return string
		 */
		public function getAdditionalHeadColumns( $html )
		{
			if ( ! empty( $this->sided_plugins ) ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

				foreach ( $this->sided_plugins as $plugin_path ) {
					if ( is_plugin_active( $plugin_path ) ) {
						$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
						$html .= "<th style='width: 130px;'>" . $data['Name'] . " " . __( 'exclude', 'gonzales' ) . "</th>";
					}
				}
			}

			return $html;
		}

		/**
		 * Get controls columns
		 *
		 * @param $html
		 * @param $type
		 * @param $handle
		 *
		 * @return string
		 */
		public function getAdditionalControlsColumns( $html, $type, $handle )
		{
			if ( ! empty( $this->sided_plugins ) ) {
				$options = $this->getOption( 'assets_manager_sided_plugins', [] );
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

				foreach ( $this->sided_plugins as $plugin_path ) {
					if ( is_plugin_active( $plugin_path ) ) {
						$parts  = explode( '/', $plugin_path );
						$plugin = isset( $parts[0] ) ? $parts[0] : $plugin_path;

						$active = isset( $options[ $plugin ][ $type ] )
						          && is_array( $options[ $plugin ][ $type ] )
						          && in_array( $handle, $options[ $plugin ][ $type ] );
						$name = "sided_plugins[{$plugin}][{$type}][{$handle}]";

						$html .= "<td>";

						if ( ! empty( $handle ) ) {
							$html .= "<select name='$name' class='wbcr-gonzales-sided-select";
							$html .= ($active ? " wbcr-sided-yes" : "") . "'>";
							$html .= "<option value='0'>" . __( 'No', 'gonzales' ) . "</option>";
							$html .= "<option value='1'" . selected( $active, true, false ) . ">";
							$html .= __( 'Yes', 'gonzales' ) . "</option>";
							$html .= "</select>";
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
					$exclude .= ! empty( $exclude ) ? ',' . $dontmove : $dontmove;
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
			$exclude_files = [];

			switch ( $index ) {
				case 'wmac':
					if ( class_exists( 'WMAC_Plugin' ) ) {
						$exclude_files = WMAC_Plugin::app()->getOption( $type . '_exclude', '' );
					}
					break;
				case 'aopt':
					$exclude_files = get_option( 'autoptimize_' . $type . '_exclude', '' );
					break;
			}

			$current_exclude_files = ! empty( $exclude_files )
				? array_filter( array_map( 'trim', explode( ',', $exclude_files ) ) )
				: [];

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
				case 'wmac':
					if ( class_exists( 'WMAC_Plugin' ) ) {
						WMAC_Plugin::app()->updateOption( $type . '_exclude', implode( ', ', $current_exclude_files ) );
					}
					break;
				case 'aopt':
					update_option( 'autoptimize_' . $type . '_exclude', implode( ', ', $current_exclude_files ) );
					break;
			}
		}

		/**
		 * Action form save
		 *
		 * @param bool $empty_before
		 */
		public function actionFormSave( $empty_before = false ) {
			$sided_exclude_files = [
				'before' => [
					'js' => [], 'css' => []
				],
				'after' => [
					'js' => [], 'css' => []
				]
			];

			if ( ! empty( $this->sided_plugins ) && ! $empty_before ) {
				foreach ( $this->sided_plugins as $index => $sided_plugin ) {
					$sided_exclude_files['before']['js']  += $this->getSidedPluginFiles( $index, 'js' );
					$sided_exclude_files['before']['css'] += $this->getSidedPluginFiles( $index, 'css' );
				}
			}

			if (
				isset( $_POST['sided_plugins'] )
				&& ! empty( $_POST['sided_plugins'] )
			) {
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

				$this->updateOption( 'assets_manager_sided_plugins', $sided_plugins_options );
			}

			if ( ! empty( $this->sided_plugins ) ) {
				$this->sided_plugin_files = [];
				foreach ( $this->sided_plugins as $index => $sided_plugin ) {
					$sided_exclude_files['after']['js']  += $this->getSidedPluginFiles( $index, 'js' );
					$sided_exclude_files['after']['css'] += $this->getSidedPluginFiles( $index, 'css' );

					if (
						! empty( $sided_exclude_files['before']['js'] )
						|| ! empty( $sided_exclude_files['after']['js'] )
					) {
						$this->manageExcludeFiles( $sided_exclude_files, $index, 'js' );
					}

					if (
						! empty( $sided_exclude_files['before']['css'] )
						|| ! empty( $sided_exclude_files['after']['css'] )
					) {
						$this->manageExcludeFiles( $sided_exclude_files, $index, 'css' );
					}
				}
			}
		}

	}