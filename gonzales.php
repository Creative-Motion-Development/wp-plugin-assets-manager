<?php
	/**
	 * Plugin Name: Webcraftic Assets manager
	 * Plugin URI: https://wordpress.org/plugins/gonzales/
	 * Description: Increase the speed of the pages by disabling unused scripts (.JS) and styles (.CSS). Make your website REACTIVE!
	 * Author: Webcraftic <wordpress.webraftic@gmail.com>
	 * Version: 1.0.2
	 * Text Domain: gonzales
	 * Domain Path: /languages/
	 */

	// Exit if accessed directly
	if( !defined('ABSPATH') ) {
		exit;
	}

	if( defined('WGZ_PLUGIN_ACTIVE') || (defined('WCL_PLUGIN_ACTIVE') && !defined('LOADING_GONZALES_AS_ADDON')) ) {
		function wbcr_gnz_admin_notice_error()
		{
			?>
			<div class="notice notice-error">
				<p><?php _e('We found that you use the plugin "Clearfy - disable unused functions", this plugin already has the same functions as "Assets manager", so you can disable the "Assets manager" plugin!', 'gonzales'); ?></p>
			</div>
		<?php
		}

		add_action('admin_notices', 'wbcr_gnz_admin_notice_error');

		return;
	} else {

		define('WGZ_PLUGIN_ACTIVE', true);

		define('WGZ_PLUGIN_DIR', dirname(__FILE__));
		define('WGZ_PLUGIN_BASE', plugin_basename(__FILE__));
		define('WGZ_PLUGIN_URL', plugins_url(null, __FILE__));

		#comp remove
		// the following constants are used to debug features of diffrent builds
		// on developer machines before compiling the plugin

		// build: free, premium, ultimate
		if( !defined('BUILD_TYPE') ) {
			define('BUILD_TYPE', 'free');
		}
		// language: en_US, ru_RU
		if( !defined('LANG_TYPE') ) {
			define('LANG_TYPE', 'en_EN');
		}
		// license: free, paid
		if( !defined('LICENSE_TYPE') ) {
			define('LICENSE_TYPE', 'free');
		}

		// wordpress language
		if( !defined('WPLANG') ) {
			define('WPLANG', LANG_TYPE);
		}
		// the compiler library provides a set of functions like onp_build and onp_license
		// to check how the plugin work for diffrent builds on developer machines

		if( !defined('LOADING_GONZALES_AS_ADDON') ) {
			require('libs/onepress/compiler/boot.php');
			// creating a plugin via the factory
		}
		// #fix compiller bug new Factory000_Plugin
		#endcomp

		if( !defined('LOADING_GONZALES_AS_ADDON') ) {
			require_once(WGZ_PLUGIN_DIR . '/libs/factory/core/boot.php');
		}

		require_once(WGZ_PLUGIN_DIR . '/includes/class.plugin.php');

		if( !defined('LOADING_GONZALES_AS_ADDON') ) {
			//todo: обновить опции в старом плагине на новый префикс
			new WGZ_Plugin(__FILE__, array(
				'prefix' => 'wbcr_gnz_',
				'plugin_name' => 'gonzales',
				'plugin_title' => __('Webcraftic assets manager', 'gonzales'),
				'plugin_version' => '1.0.2',
				'required_php_version' => '5.2',
				'required_wp_version' => '4.2',
				'plugin_build' => BUILD_TYPE,
				'updates' => WCM_PLUGIN_DIR . '/updates/'
			));
		}
	}

