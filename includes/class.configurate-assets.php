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
			echo "<div class='wbcr-info-wrap'>";
			echo "<div class='wbcr-information __info-query'>Всего запросов - 124</div>";
			echo "<div class='wbcr-information __info-all-weight'>2</div>";
			echo "<div class='wbcr-information __info-opt-weight'>3</div>";
			echo "<div class='wbcr-information __info-off-js'>4</div>";
			echo "<div class='wbcr-information __info-off-css'>5</div>";
			echo "</div>";
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

			$is_first_plugin = true;
			foreach($this->collection as $resource_type => $resources) {
				echo "<h3>" . $resource_type . "</h3>";
				foreach($resources as $resource_name => $types) {
					$plugin_state = false;

					if ( 'plugins' == $resource_type && ! empty( $resource_name ) ) {
						$plugin_data = $this->getPluginData( $resource_name );

						if ( ! empty( $plugin_data ) ) {
							$is_disabled = $this->getIsDisabled( $options, $resource_type, $resource_name );
							$disabled = $this->getDisabled( $is_disabled, $options, $resource_type, $resource_name );

							$is_enabled = $this->getIsEnabled( $options, $resource_type, $resource_name );
							$enabled = $this->getEnabled( $is_enabled, $options, $resource_type, $resource_name );

							$plugin_state = $this->getState( $is_disabled, $disabled, $current_url );

							echo "<div class='wbcr-section " . ($is_first_plugin ? "" : "wbcr-resource") . "'>";
							echo "<table class='wbcr-resource-table'>";
							echo "<thead>";
							echo "<tr>";
							echo "<th style='width: 100px;'>" . __( 'Loaded', 'gonzales' ) . "</th>";
							echo "<th style='width: 100%;'>" . __( 'Plugin info', 'gonzales' ) . "</th>";
							echo "<th style='width: 200px;'>" . __( 'State', 'gonzales' ) . "</th>";
							echo "<th style='width: 300px;' class='wbcr-enable-th'>" . __( 'Enable', 'gonzales' ) . "</th>";
							echo "</tr>";
							echo "</thead>";
							echo "<tbody>";
							echo "<tr>";
							echo '<td><div class="wbcr-state wbcr-state-' . (int)$plugin_state . '">';
							echo ( ! $plugin_state ? __( 'Yes', 'gonzales' ) : __( 'No', 'gonzales' ) ) . '</div></td>';
							echo '<td><div class="wbcr-resource-block">';
							echo '<span class="wbcr-resource-name">' . $plugin_data['Name'] . '</span><br>';
							echo "<b>Author:</b> " . $plugin_data['Author'] . "<br>";
							echo "<b>Version:</b> " . $plugin_data['Version'] . "</div></td>";
							// State Controls
							$id = '[' . $resource_type . '][' . $resource_name . ']';

							$this->getStateControrlHTML(
								$id, $plugin_state, $is_disabled, $is_enabled, $resource_type, $resource_name, $disabled, $enabled, $current_url
							);
							echo "</tr>";
							echo "</tbody>";
							echo "</table>";
							echo "</div>";

							$is_first_plugin = false;
						}
					}

					echo "<div class='wbcr-section'>";
					echo "<table>";
					echo "<thead>";
					echo "<tr>";
					echo "<th style='width: 100px;'>" . __('Loaded', 'gonzales') . "</th>";
					echo "<th style='width: 75px;'>" . __('Size', 'gonzales') . "</th>";
					echo "<th style='width: 100%;'>" . __('Script', 'gonzales') . "</th>";
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

								// State Controls
								$id = '[' . $type_name . '][' . $handle . ']';
								$this->getStateControrlHTML(
									$id, $state, $is_disabled, $is_enabled, $type_name, $handle, $disabled, $enabled, $current_url
								);

								echo "<input type='hidden' class='wbcr-info-data' data-type='{$type_name}' data-off='{$display_state}' value='{$row['size']}'>";

								echo "</tr>";

								if ( 'plugins' == $resource_type && ! empty( $resource_name ) ) {
									$name = $resource_type . '[' . $resource_name . '][' . $type_name . '][]';
									echo "<input type='hidden' name='$name' value='$handle'>";
								}
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
		private function getIsDisabled( $options, $type_name, $handle )
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
		private function getDisabled( $is_disabled, $options, $type_name, $handle )
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
				if( !isset($disabled['custom']) ) {
					$disabled['custom'] = array();
				}
				if( !isset($disabled['regex']) ) {
					$disabled['regex'] = "";
				}
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
		private function getIsEnabled( $options, $type_name, $handle )
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
		private function getEnabled( $is_enabled, $options, $type_name, $handle )
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
				if( !isset($enabled['custom']) ) {
					$enabled['custom'] = array();
				}
				if( !isset($enabled['regex']) ) {
					$enabled['regex'] = "";
				}
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
		private function getState( $is_disabled, $disabled, $current_url )
		{
			$state = 0;
			if(
				$is_disabled
				&& (
					$disabled['everywhere'] == 1
					|| in_array($current_url, $disabled['current'])
					|| ! empty($disabled['custom'])
					|| ! empty($disabled['regex'])
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
		 */
		private function getStateControrlHTML( $id, $state, $is_disabled, $is_enabled, $type_name, $handle, $disabled, $enabled, $current_url ) {
			//Disable
			echo "<td class='wbcr-assets-manager-disable'>";
			echo "<select name='disabled{$id}[state]' class='wbcr-gonzales-disable-select'";
			echo ('plugins' == $type_name ? "data-handle='{$handle}'" : "" ) . ">";
			echo "<option value='' class='wbcr-gonzales-option-enabled'>" . __('Enabled', 'gonzales') . "</option>";
			echo "<option value='disable' class='wbcr-gonzales-option-disable' ";
			if( $state ) {
				echo "selected";
			}
			echo ">" . __('Disable', 'gonzales') . "</option>";
			echo "</select>";
			echo "</td>";
			//Enable
			echo "<td>";
			echo "<span class='wbcr-assets-manager-enable-placeholder' ";
			if( $state ) {
				echo "style='display: none;'";
			}
			echo ">" . __('Disable everwhere to view enable settings.', 'gonzales') . "</span>";
			echo "<span class='wbcr-assets-manager-enable'";
			if( ! $state ) {
				echo " style='display: none;'";
			}
			echo ">";
			echo "<div>";
			echo "<select name='action{$id}' class='wbcr-gonzales-action-select'>";
			echo "<option value='current'" . selected( $is_disabled && ! empty( $disabled['current'] ) ) . ">" . __( 'Current URL', 'gonzales' ) . "</option>";
			echo "<option value='everywhere'" . selected( $is_disabled && ! empty( $disabled['everywhere'] ) ) . ">" . __( 'Everywhere', 'gonzales' ) . "</option>";
			echo "<option value='custom'" . selected( $is_disabled && ! empty( $disabled['custom'] ) ) . ">" . __( 'Custom URL', 'gonzales' ) . "</option>";
			echo "<option value='regex'" . selected( $is_disabled && ! empty( $disabled['regex'] ) ) . ">" . __( 'Regular expression', 'gonzales' ) . "</option>";
			echo "</select>";
			echo "</div>";
			echo "<div class='wbcr-assets-manager everywhere'";
			if( !$is_disabled || empty($disabled['everywhere']) ) {
				echo " style='display: none;'";
			}
			echo ">";
			echo "<input type='hidden' name='enabled{$id}[current]' value='' />";
			echo "<span><strong>" . __('Exclude', 'gonzales') . ":</strong></span><br>";
			echo "<label for='" . $type_name . "-" . $handle . "-enable-current'>";
			echo "<input type='checkbox' name='enabled{$id}[current]' id='" . $type_name . "-" . $handle . "-enable-current' value='" . $current_url . "' ";

			if( $is_enabled && in_array($current_url, $enabled['current']) ) {
				echo "checked";
			}

			echo " />" . __('Current URL', 'gonzales');
			echo "</label>";

			$post_types = get_post_types(array('public' => true), 'objects', 'and');
			if( !empty($post_types) ) {
				echo "<input type='hidden' name='enabled{$id}[post_types]' value='' />";
				foreach($post_types as $key => $value) {
					echo "<label for='" . $type_name . "-" . $handle . "-enable-" . $key . "'>";
					echo "<input type='checkbox' name='enabled{$id}[post_types][]' id='" . $type_name . "-" . $handle . "-enable-" . $key . "' value='" . $key . "' ";
					if( isset($enabled['post_types']) ) {
						if( in_array($key, $enabled['post_types']) ) {
							echo "checked";
						}
					}
					echo " />" . $value->label;
					echo "</label>";
				}
			}

			$taxonomies = get_taxonomies(array('public' => true), 'objects', 'and');
			if( !empty($taxonomies) ) {
				unset($taxonomies['category']);
				echo "<input type='hidden' name='enabled{$id}[taxonomies]' value='' />";
				foreach($taxonomies as $key => $value) {
					echo "<label for='" . $type_name . "-" . $handle . "-enable-" . $key . "'>";
					echo "<input type='checkbox' name='enabled{$id}[taxonomies][]' id='" . $type_name . "-" . $handle . "-enable-" . $key . "' value='" . $key . "' ";
					if( isset($enabled['taxonomies']) ) {
						if( in_array($key, $enabled['taxonomies']) ) {
							echo "checked";
						}
					}
					echo " />" . $value->label;
					echo "</label>";
				}
			}

			$categories = get_categories();
			if( !empty($categories) ) {
				echo "<input type='hidden' name='enabled{$id}[categories]' value='' />";
				foreach($categories as $key => $cat) {
					echo "<label for='" . $type_name . "-" . $handle . "-enable-" . $cat->term_id . "'>";
					echo "<input type='checkbox' name='enabled{$id}[categories][]' id='" . $type_name . "-" . $handle . "-enable-" . $cat->term_id . "' value='" . $cat->term_id . "' ";
					if( isset($enabled['categories']) ) {
						if( in_array($cat->term_id, $enabled['categories']) ) {
							echo "checked";
						}
					}
					echo " />" . $cat->name;
					echo "</label>";
				}
			}

			echo "</div>";
			echo "<div class='wbcr-assets-manager custom'";
			if( !$is_disabled || empty($disabled['custom']) ) {
				echo " style='display: none;'";
			}
			echo ">";
			echo "<span title='" . __('Example', 'gonzales') . ': ' . site_url() . "/post/*, " . site_url() . "/page-*'><strong>" . __('Enter URL (set * for mask)', 'gonzales') . ":</strong></span><br>";

			if ($is_disabled && ! empty($disabled['custom']) ) {
				foreach ( $disabled['custom'] as $url ) {
					echo "<input type='text' name='disabled{$id}[custom][]' class='wbcr-gonzales-text' value='" . $url . "'>";
				}
			} else {
				echo "<input type='text' name='disabled{$id}[custom][]' class='wbcr-gonzales-text' value=''>";
			}
			echo "<a href='javascript:void(0)' class='wbcr-add-custom-url' data-name='disabled{$id}[custom][]'>" . __('Add new URL', 'gonzales') . "</a>";
			echo "</div>";
			echo "<div class='wbcr-assets-manager regex'";
			if( !$is_disabled || empty($disabled['regex']) ) {
				echo " style='display: none;'";
			}
			echo ">";
			echo "<span><strong>" . __('Enter regular expression', 'gonzales') . ":</strong></span><br>";
			$regex = isset( $disabled['regex'] ) ? $disabled['regex'] : '' ;
			echo "<input type='text' name='disabled{$id}[regex]' class='wbcr-gonzales-text' value='" . $regex . "'>";
			echo "</div>";
			echo "</span>";
			echo "</td>";
		}
		
		public function formSave()
		{
			if( isset($_GET['wbcr_assets_manager']) && isset($_POST['wbcr_assets_manager_save']) ) {

				if( !current_user_can('manage_options') || !wp_verify_nonce(filter_input(INPUT_POST, 'wbcr_assets_manager_save'), 'wbcr_assets_manager_nonce') ) {
					return false;
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
										unset($disabled['current'], $disabled['custom'], $disabled['regex']);

										$disabled['everywhere'] = 1;
									} elseif( "current" == $action ) {
										unset($disabled['everywhere'], $disabled['custom'], $disabled['regex']);
										
										if( !isset($disabled['current']) || !is_array($disabled['current']) ) {
											$disabled['current'] = array();
										}
										
										if( !in_array($current_url, $disabled['current']) ) {
											array_push($disabled['current'], $current_url);
										}
									} elseif( "custom" == $action ) {
										unset($disabled['everywhere'], $disabled['current'], $disabled['regex']);

										if( !isset($disabled['custom']) || !is_array($disabled['custom']) ) {
											$disabled['custom'] = array();
										}

										$custom_urls = isset( $_POST['disabled'][$type][$handle]['custom'] )
											? $_POST['disabled'][$type][$handle]['custom']
											: [] ;
										$custom_urls = array_unique( $custom_urls );

										foreach ( $custom_urls as $key => $url ) {
											$url = untrailingslashit( $url );
											if ( $url == site_url() || empty( $url ) ) {
												unset( $custom_urls[ $key ] );
											}
										}

										if ( empty( $custom_urls ) ) {
											unset( $disabled['custom'] );
										} else {
											$disabled['custom'] = $custom_urls;
										}
									} elseif( "regex" == $action ) {
										unset($disabled['everywhere'], $disabled['current'], $disabled['custom']);

										if( !isset($disabled['regex']) ) {
											$disabled['regex'] = "";
										}

										$regex = isset( $_POST['disabled'][$type][$handle]['regex'] )
											? $_POST['disabled'][$type][$handle]['regex']
											: '' ;

										if ( empty( $regex ) ) {
											unset( $disabled['regex'] );
										} else {
											$disabled['regex'] = $regex;
										}
									}
								} else {
									unset($disabled['everywhere'], $disabled['custom'], $disabled['regex']);
									
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

				$this->updateOption('assets_manager_options', $options);
				$this->updateOption('assets_manager_plugin_scripts', $_POST['plugins']);

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
			$plugin_scripts = $this->getOption( 'assets_manager_plugin_scripts', array() );

			if ( ! empty( $plugin_scripts ) ) {
				foreach ( $plugin_scripts as $plugin_name => $scripts ) {
					if (
						isset( $scripts[ $type ] )
						&& in_array( $handle, $scripts[ $type ] )
						&& isset( $options['disabled']['plugins'][ $plugin_name ] )
					) {
						return $options['disabled']['plugins'][ $plugin_name ];
					}
				}
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
			$plugin_scripts = $this->getOption( 'assets_manager_plugin_scripts', array() );

			if ( ! empty( $plugin_scripts ) ) {
				foreach ( $plugin_scripts as $plugin_name => $scripts ) {
					if (
						isset( $scripts[ $type ] )
						&& in_array( $handle, $scripts[ $type ] )
						&& isset( $options['enabled']['plugins'][ $plugin_name ] )
					) {
						return $options['enabled']['plugins'][ $plugin_name ];
					}
				}
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

			$type = (current_filter() == 'script_loader_src')
				? 'js'
				: 'css';

			$current_url = esc_url($this->getCurrentUrl());
			$free_current_url = untrailingslashit( $current_url );

			$disabled = $this->getDisabledFromOptions( $type, $handle );
			$enabled  = $this->getEnabledFromOptions( $type, $handle );

			if(
				(isset($disabled['everywhere']) && $disabled['everywhere'] == 1)
				|| (isset($disabled['current']) && is_array($disabled['current']) && in_array($current_url, $disabled['current']))
				|| (isset($disabled['custom']) && is_array($disabled['custom']) && !empty($disabled['custom']) && !empty($free_current_url))
				|| (isset($disabled['regex']) && !empty($disabled['regex']) && !empty($free_current_url))
			) {

				if( isset($enabled['current']) && is_array($enabled['current']) && in_array($current_url, $enabled['current']) ) {
					return $src;
				}

				if(
					isset($disabled['custom'])
					&& is_array($disabled['custom'])
					&& ! empty($disabled['custom'])
				) {
					$found_match = false;

					foreach ( $disabled['custom'] as $url ) {
						// Убираем базовый url
						$free_url = str_replace( site_url(), '', $url );
						// Если есть *
						if( strpos( $free_url, '*' ) ) {
							// Получаем строку до *
							$free_url = strstr( $free_url, '*', true );
							// Если это был не пустой url (типа http://site/*) и есть вхождение с начала
							if(
								untrailingslashit( $free_url )
								&& strpos( $current_url, $free_url ) === 0
							) {
								$found_match = true;
								break;
							}
						// Если url'ы идентичны
						} else if( untrailingslashit( esc_url( $free_url ) ) === $current_url ) {
							$found_match = true;
							break;
						}
					}

					if( ! $found_match ) {
						return $src;
					}
				}

				if(
					isset( $disabled['regex'] )
					&& $disabled['regex']
					&& ! @preg_match(
						'/' . trim( str_replace( '\\\\', '\\', $disabled['regex'] ), '/' ) . '/',
						ltrim( $current_url, '/\\' )
					)
				) {
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

							$resource_name = '';
							if ( 'plugins' == $resource_type ) {
								$clean_url = str_replace( WP_PLUGIN_URL . '/', '', $url );
								$url_parts = explode( '/', $clean_url );
								$resource_name = isset( $url_parts[0] ) ? $url_parts[0] : '';
							}

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
						'yes'          => __( 'Yes', 'gonzales' ),
						'no'           => __( 'No', 'gonzales' ),
						'total_query'  => __( 'Total requests', 'gonzales' ),
						'total_weight' => __( 'Total weight', 'gonzales' ),
						'opt_weight'   => __( 'Optimized weight', 'gonzales' ),
						'off_js'       => __( 'Disabled js', 'gonzales' ),
						'off_css'      => __( 'Disabled css', 'gonzales' )
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
		 * @return int          Size in KB.
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
		 * Get plugin data from folder name
		 *
		 * @param $name
		 *
		 * @return array
		 */
		private function getPluginData( $name )
		{
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
							$data = $plugin_data;
							$data['path'] = $plugin_path;
							break;
						}
					}
				}
			}

			return $data;
		}
	}