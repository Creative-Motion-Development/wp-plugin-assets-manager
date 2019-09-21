<?php
/**
 * Save settings ajax action
 *
 * @author        Alex Kovalev <alex.kovalevv@gmail.com>, Github: https://github.com/alexkovalevv
 * @copyright (c) 21.09.2019, Webcraftic
 * @version       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @since  1.0
 */
function wam_save_settings_action() {
	check_ajax_referer( 'wam_save_settigns' );

	if ( ! WCL_Plugin::app()->currentUserCan() ) {
		wp_send_json_error( [
			'error_message_title'   => __( 'Save settings failed!', 'gonzales' ),
			'error_message_content' => __( 'You don\'t have enough capability to edit this information.', 'gonzales' )
		] );
	}

	$save_message_title   = __( 'Settings saved successfully!', 'clearfy' );
	$save_message_content = __( 'If you use test mode, do not forget to disable it. We also recommend that you flush the cache if you use caching plugins.', 'clearfy' );
	$raw_updated_settings = WGZ_Plugin::app()->request->post( 'settings' );

	if ( ! empty( $raw_updated_settings ) ) {
		$settings = WGZ_Plugin::app()->getOption( 'settings', [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		foreach ( (array) $raw_updated_settings as $plugin_name => $plugin_group ) {
			if ( ! empty( $plugin_group['load_mode'] ) ) {
				if ( 'enable' == $plugin_group['load_mode'] ) {
					$plugin_group['visability'] = "";
				} else {
					foreach ( [ 'js', 'css' ] as $assets_type ) {
						if ( ! empty( $plugin_group[ $assets_type ] ) ) {
							foreach ( $plugin_group[ $assets_type ] as $resource_handle => $resource_params ) {
								$plugin_group[ $assets_type ][ $resource_handle ]['visability'] = "";
							}
						}
					}
				}
			}

			$settings['plugins'][ $plugin_name ] = $plugin_group;
		}

		WGZ_Plugin::app()->updateOption( 'settings', $settings );

		// If mu  plugin does not exist, install it.
		wbcr_gnz_deploy_mu_plugin();

		// Flush cache for all cache plugins
		WbcrFactoryClearfy000_Helpers::flushPageCache();
	}

	wp_send_json_success( [
		'save_massage_title'   => $save_message_title,
		'save_message_content' => $save_message_content
	] );
}

add_action( 'wp_ajax_nopriv_wam-save-settings', 'wam_save_settings_action' );
add_action( 'wp_ajax_wam-save-settings', 'wam_save_settings_action' );