<?php
	/**
	 * Admin boot
	 * @author Webcraftic <wordpress.webraftic@gmail.com>
	 * @copyright Webcraftic 25.05.2017
	 * @version 1.0
	 */

	require(WBCR_GNZ_PLUGIN_DIR . '/admin/pages/assets-manager.php');

	if( !defined('LOADING_GONZALES_AS_ADDON') ) {
		require(WBCR_GNZ_PLUGIN_DIR . '/admin/pages/more-features.php');
	}

	function wbcr_gnz_set_plugin_meta($links, $file)
	{
		if( $file == WBCR_GNZ_PLUGIN_BASE ) {
			$links[] = '<a href="https://goo.gl/TcMcS4" style="color: #FF5722;font-weight: bold;" target="_blank">' . __('Get ultimate plugin free', 'gonzales') . '</a>';
		}

		return $links;
	}

	if( !defined('LOADING_GONZALES_AS_ADDON') ) {
		add_filter('plugin_row_meta', 'wbcr_gnz_set_plugin_meta', 10, 2);
	}

	function wbcr_gnz_rating_widget_url($page_url, $plugin_name)
	{
		if( $plugin_name == 'wbcr_gonzales' ) {
			return 'https://goo.gl/zyNV6z';
		}

		return $page_url;
	}

	add_filter('wbcr_factory_imppage_rating_widget_url', 'wbcr_gnz_rating_widget_url', 10, 2);

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



