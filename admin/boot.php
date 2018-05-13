<?php
	/**
	 * Admin boot
	 * @author Webcraftic <wordpress.webraftic@gmail.com>
	 * @copyright Webcraftic 25.05.2017
	 * @version 1.0
	 */

	// Exit if accessed directly
	if( !defined('ABSPATH') ) {
		exit;
	}

	require(WGZ_PLUGIN_DIR . '/admin/pages/assets-manager.php');

	if( !defined('LOADING_GONZALES_AS_ADDON') ) {
		require(WGZ_PLUGIN_DIR . '/admin/pages/more-features.php');
	}

	function wbcr_gnz_set_plugin_meta($links, $file)
	{
		if( $file == WGZ_PLUGIN_BASE ) {

			$url = 'https://clearfy.pro';

			if( get_locale() == 'ru_RU' ) {
				$url = 'https://ru.clearfy.pro';
			}

			$url .= '?utm_source=wordpress.org&utm_campaign=' . WGZ_Plugin::app()->getPluginName();

			$links[] = '<a href="' . $url . '" style="color: #FF5722;font-weight: bold;" target="_blank">' . __('Get ultimate plugin free', 'gonzales') . '</a>';
		}

		return $links;
	}

	if( !defined('LOADING_GONZALES_AS_ADDON') ) {
		add_filter('plugin_row_meta', 'wbcr_gnz_set_plugin_meta', 10, 2);
	}

	function wbcr_gnz_rating_widget_url($page_url, $plugin_name)
	{
		if( $plugin_name == WGZ_Plugin::app()->getPluginName() ) {
			return 'https://goo.gl/zyNV6z';
		}

		return $page_url;
	}

	add_filter('wbcr_factory_pages_000_imppage_rating_widget_url', 'wbcr_gnz_rating_widget_url', 10, 2);

	function wbcr_gnz_group_options($options)
	{
		$options[] = array(
			'name' => 'disable_assets_manager',
			'title' => __('Disable assets manager', 'gonzales'),
			'tags' => array(),
			'values' => array()
		);

		$options[] = array(
			'name' => 'disable_assets_manager_panel',
			'title' => __('Disable assets manager panel', 'gonzales'),
			'tags' => array()
		);

		$options[] = array(
			'name' => 'disable_assets_manager_on_front',
			'title' => __('Disable assets manager on front', 'gonzales'),
			'tags' => array()
		);

		$options[] = array(
			'name' => 'disable_assets_manager_on_backend',
			'title' => __('Disable assets manager on back-end', 'gonzales'),
			'tags' => array()
		);

		$options[] = array(
			'name' => 'manager_options',
			'title' => __('Assets manager options', 'gonzales'),
			'tags' => array()
		);

		return $options;
	}

	add_filter("wbcr_clearfy_group_options", 'wbcr_gnz_group_options');

	function wbcr_gnz_migrate_options()
	{
		global $wpdb;

		if( WGZ_Plugin::app()->getOption('migrate_options_1_0_3') ) {
			return;
		}
		$assets = get_option('wbcr_gonzales_manager_options');

		if( !empty($assets) ) {
			WGZ_Plugin::app()->updateOption('assets_manager_options', $assets);
		}

		delete_option('wbcr_gonzales_manager_options');

		$request = $wpdb->get_results("SELECT option_id, option_name, option_value FROM {$wpdb->prefix}options WHERE option_name LIKE 'wbcr_gonzales_%'");

		if( !empty($request) ) {
			foreach($request as $option) {
				$option_new_name = str_replace('wbcr_gonzales_', WGZ_Plugin::app()->getPrefix(), $option->option_name);
				if( !get_option($option_new_name, false) ) {
					$wpdb->query("UPDATE {$wpdb->prefix}options SET option_name='$option_new_name' WHERE option_id='{$option->option_id}'");
				} else {
					delete_option($option->option_name);
				}
			}
		}

		WGZ_Plugin::app()->updateOption('migrate_options_1_0_3', 1);
		WGZ_Plugin::app()->flushOptionsCache();
	}

	wbcr_gnz_migrate_options();





