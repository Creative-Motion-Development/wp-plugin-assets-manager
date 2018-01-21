<?php
	
	/**
	 * Assets manager base class
	 * @author Webcraftic <wordpress.webraftic@gmail.com>
	 * @copyright (c) 05.11.2017, Webcraftic
	 * @version 1.0
	 */
	class WbcrGnz_ConfigAssetsManager extends WbcrFactoryClearfy_Configurate {
		
		/**
		 * Stores list of all available assets (used in rendering panel)
		 *
		 * @var array
		 */
		private $collection = array();
		
		
		/**
		 * Initilize entire machine
		 */
		function registerActionsAndFilters()
		{
			if( $this->getOption('disable_assets_manager', false) ) {
				return;
			}
			
			$on_frontend = (bool)$this->getOption('disable_assets_manager_on_front', false);
			$on_backend = (bool)$this->getOption('disable_assets_manager_on_backend', true);
			$is_panel = (bool)$this->getOption('disable_assets_manager_panel', false);

			if( (!is_admin() && !$on_frontend) || (is_admin() && !$on_backend) ) {
				add_filter('script_loader_src', array($this, 'unloadAssets'), 10, 2);
				add_filter('style_loader_src', array($this, 'unloadAssets'), 10, 2);
			}

			if( !$is_panel && ((is_admin() && !$on_backend) || (!is_admin() && !$on_frontend)) ) {
				if( !is_admin() ) {
					add_action('wp_enqueue_scripts', array($this, 'appendAsset'));
					add_action('wp_footer', array($this, 'assetsManager'), 100001);
				} else {
					add_action('admin_enqueue_scripts', array($this, 'appendAsset'));
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
				add_action('admin_bar_menu', array($this, 'assetsManagerAdminBar'), 1000);
			}

			if( !is_admin() && !$on_frontend ) {
				add_action('init', array($this, 'formSave'));
			}

			if( is_admin() && !$on_backend ) {
				add_action('admin_init', array($this, 'formSave'));
			}
		}
		
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
			global $wbcr_gonzales_plugin;
			if( !current_user_can('manage_options') || !isset($_GET['wbcr_assets_manager']) ) {
				return;
			}

			$current_url = esc_url($this->getCurrentUrl());
			$options = $this->getOption('manager_options', array());
			
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
			
			//Form
			echo "<form method='POST'>";
			
			wp_nonce_field('wbcr_assets_manager_nonce', 'wbcr_assets_manager_save');
			echo "<div class='wbcr-float-panel'>";
			echo "<input type='submit' value='" . __('Save settings', 'gonzales') . "' />";

			echo "<a href='" . remove_query_arg('wbcr_assets_manager') . "' class='wbcr-close'></a>";
			$setting_page_url = !defined('LOADING_GONZALES_AS_ADDON')
				? 'options-general.php'
				: 'admin.php';
			$setting_page_url .= '?page=gonzales-' . $wbcr_gonzales_plugin->pluginName;
			echo "<a href='" . admin_url($setting_page_url) . "' class='wbcr-hide-panel'>" . __('Hide panel in adminbar?', 'gonzales') . "</a>";
			echo "</div>";
			
			krsort($this->collection);
			
			foreach($this->collection as $resource_type => $types) {
				echo "<h3>" . $resource_type . "</h3>";
				echo "<div class='wbcr-section'>";
				echo "<table>";
				echo "<thead>";
				echo "<tr>";
				echo "<th style='width: 100px;'>" . __('State', 'gonzales') . "</th>";
				echo "<th style='width: 75px;'>" . __('Size', 'gonzales') . "</th>";
				echo "<th style='width: 40%;'>" . __('Script', 'gonzales') . "</th>";
				echo "<th style='width: 10%;'>" . __('In use', 'gonzales') . "</th>";
				echo "<th style='width: 200px;'>" . __('Disable', 'gonzales') . "</th>";
				echo "<th>" . __('Enable', 'gonzales') . "</th>";
				echo "</tr>";
				echo "</thead>";
				echo "<tbody>";
				
				foreach($types as $type_name => $rows) {
					
					if( !empty($rows) ) {
						foreach($rows as $handle => $row) {
							
							$is_disabled = isset($options['disabled']) && isset($options['disabled'][$type_name]) && isset($options['disabled'][$type_name][$handle]);
							$disabled = array();
							if( $is_disabled ) {
								$disabled = &$options['disabled'][$type_name][$handle];
								if( !isset($disabled['everywhere']) ) {
									$disabled['everywhere'] = array();
								}
								if( !isset($disabled['current']) ) {
									$disabled['current'] = array();
								}
							}
							
							$is_enabled = isset($options['enabled']) && isset($options['enabled'][$type_name]) && isset($options['enabled'][$type_name][$handle]);
							$enabled = array();
							if( $is_enabled ) {
								$enabled = &$options['enabled'][$type_name][$handle];
								
								if( !isset($enabled['everywhere']) ) {
									$enabled['everywhere'] = array();
								}
								if( !isset($enabled['current']) ) {
									$enabled['current'] = array();
								}
							}
							/**
							 * Find dependency
							 */
							$deps = array();
							foreach($rows as $dep_key => $dep_val) {
								if( in_array($handle, $dep_val['deps']) /*&& $is_disabled*/ ) {
									$deps[] = '<a href="#' . $type_name . '-' . $dep_key . '">' . $dep_key . '</a>';
								}
							}
							
							$id = '[' . $type_name . '][' . $handle . ']';
							
							$comment = (!empty($deps)
								? '<span style="color:#fb7976;" class="wbcr-use-by-comment">' . __('In use by', 'gonzales') . ' ' . implode(', ', $deps) . '</span>'
								: '');
							
							echo "<tr>";
							
							$state = 0;
							if( $is_disabled && ($disabled['everywhere'] == 1 || in_array($current_url, $disabled['current'])) ) {
								$state = 1;
							}
							
							echo '<td><div class="wbcr-state wbcr-state-' . (int)$state . '">' . strtoupper($type_name) . '</div></td>';
							
							//Size
							echo "<td class='wbcr-assets-manager-size'>";
							echo $row['size'] . ' KB';
							echo "</td>";
							
							//Handle + Path
							echo "<td class='wbcr-script'><span>" . $handle . "</span><a href='" . $row['url_full'] . "' target='_blank'>" . str_replace(get_home_url(), '', $row['url_full']) . "</a></td>";
							
							echo "<td>";
							echo $comment;
							echo "</td>";
							
							//Disable
							echo "<td class='wbcr-assets-manager-disable'>";
							echo "<select name='disabled{$id}' class='wbcr-gonzales-disable-select'>";
							echo "<option value='' class='wbcr-gonzales-option-enabled'>" . __('Enabled', 'gonzales') . "</option>";
							echo "<option value='everywhere' class='wbcr-gonzales-option-everywhere' ";
							if( $is_disabled && $disabled['everywhere'] == 1 ) {
								echo "selected";
							}
							echo ">" . __('Everywhere', 'gonzales') . "</option>";
							echo "<option value='current' class='wbcr-gonzales-option-current' ";
							
							if( $is_disabled && in_array($current_url, $disabled['current']) ) {
								echo "selected";
							}
							
							echo ">" . __('Current URL', 'gonzales') . "</option>";
							echo "</select>";
							echo "</td>";
							//Enable
							echo "<td>";
							echo "<span class='wbcr-assets-manager-enable-placeholder' ";
							if( $is_disabled && !empty($disabled['everywhere']) ) {
								echo "style='display: none;'";
							}
							echo ">" . __('Disable everwhere to view enable settings.', 'gonzales') . "</span>";
							echo "<span class='wbcr-assets-manager-enable'";
							if( !$is_disabled || empty($disabled['everywhere']) ) {
								echo " style='display: none;'";
							}
							echo ">";
							echo "<input type='hidden' name='enabled{$id}[current]' value='' />";
							echo "<label for='" . $type_name . "-" . $handle . "-enable-current'>";
							echo "<input type='checkbox' name='enabled{$id}[current]' id='" . $type_name . "-" . $handle . "-enable-current' value='" . $current_url . "' ";
							
							if( $is_enabled && in_array($current_url, $enabled['current']) ) {
								echo "checked";
							}
							
							echo " />" . __('Current URL', 'gonzales');
							echo "</label>";
							$post_types = get_post_types(array('public' => true), 'objects', 'and');
							if( !empty($post_types) ) {
								if( isset($post_types['attachment']) ) {
									unset($post_types['attachment']);
								}
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
							echo "</span>";
							echo "</td>";
							echo "</tr>";
						}
					}
				}
				
				echo "</tbody>";
				echo "</table>";
				echo "</div>";
			}
			
			echo "</form>";
			echo "</div>";
			echo "</div>";
		}
		
		public function formSave()
		{
			global $wbcr_gonzales_plugin;
			
			if( isset($_GET['wbcr_assets_manager']) && isset($_POST['wbcr_assets_manager_save']) ) {
				
				if( !current_user_can('manage_options') || !wp_verify_nonce(filter_input(INPUT_POST, 'wbcr_assets_manager_save'), 'wbcr_assets_manager_nonce') ) {
					return false;
				}
				
				$options = $this->getOption('manager_options', array());
				$current_url = esc_url($this->getCurrentUrl());
				
				if( isset($_POST['disabled']) && !empty($_POST['disabled']) ) {
					foreach($_POST['disabled'] as $type => $assets) {
						if( !empty($assets) ) {
							foreach($assets as $handle => $where) {
								
								$handle = sanitize_text_field($handle);
								$where = sanitize_text_field($where);
								
								if( !isset($options['disabled'][$type][$handle]) ) {
									$options['disabled'][$type][$handle] = array();
								}
								$disabled = &$options['disabled'][$type][$handle];
								
								if( !empty($where) ) {
									if( $where == "everywhere" ) {
										$disabled['everywhere'] = 1;
										if( !empty($disabled['current']) ) {
											unset($disabled['current']);
										}
									} elseif( $where == "current" ) {
										if( isset($disabled['everywhere']) ) {
											unset($disabled['everywhere']);
										}
										
										if( !isset($disabled['current']) || !is_array($disabled['current']) ) {
											$disabled['current'] = array();
										}
										
										if( !in_array($current_url, $disabled['current']) ) {
											array_push($disabled['current'], $current_url);
										}
									}
								} else {
									unset($disabled['everywhere']);
									
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
								
								if( !empty($where['current']) || $where['current'] === "0" ) {
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
								
								if( !empty($where['post_types']) ) {
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
				
				update_option($wbcr_gonzales_plugin->pluginName . '_manager_options', $options);
				
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
		
		function unloadAssets($src, $handle)
		{
			$type = (current_filter() == 'script_loader_src')
				? 'js'
				: 'css';
			
			$options = $this->getOption('manager_options', array());
			$current_url = esc_url($this->getCurrentUrl());
			
			$disabled = null;
			$enabled = null;
			
			if( isset($options['disabled']) && isset($options['disabled'][$type]) && isset($options['disabled'][$type][$handle]) ) {
				$disabled = &$options['disabled'][$type][$handle];
			}
			if( isset($options['enabled']) && isset($options['enabled'][$type]) && isset($options['enabled'][$type][$handle]) ) {
				$enabled = &$options['enabled'][$type][$handle];
			}
			
			if( (isset($disabled['everywhere']) && $disabled['everywhere'] == 1) || (isset($disabled['current']) && is_array($disabled['current']) && in_array($current_url, $disabled['current'])) ) {
				
				if( isset($enabled['current']) && is_array($enabled['current']) && in_array($current_url, $enabled['current']) ) {
					return $src;
				}
				
				if( is_front_page() || is_home() ) {
					if( 'page' != get_option('show_on_front') ) {
						return false;
					} else {
						if( isset($enabled['post_types']) && in_array('page', $enabled['post_types']) ) {
							return $src;
						}
					}
				}
				
				if( isset($enabled['post_types']) && in_array(get_post_type(), $enabled['post_types']) ) {
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
								$resource_name = 'theme';
							} elseif( false !== strpos($url, plugins_url()) ) {
								$resource_name = 'plugins';
							} else {
								$resource_name = 'misc';
							}
							
							$this->collection[$resource_name][$type][$el] = array(
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
			global $wbcr_gonzales_plugin;

			if( current_user_can('manage_options') && isset($_GET['wbcr_assets_manager']) ) {
				wp_enqueue_style('wbcr-assets-manager', WBCR_GNZ_PLUGIN_URL . '/assets/css/assets-manager.css', array(), $wbcr_gonzales_plugin->version);
				wp_enqueue_script('wbcr-assets-manager', WBCR_GNZ_PLUGIN_URL . '/assets/js/assets-manager.js', array(), $wbcr_gonzales_plugin->version, true);
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
	}