<?php

/**
 * Activator for the cyrlitera
 *
 * @author        Webcraftic <wordpress.webraftic@gmail.com>
 * @copyright (c) 09.03.2018, Webcraftic
 * @see           Wbcr_Factory000_Activator
 * @version       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGNZ_Activation extends Wbcr_Factory000_Activator {

	/**
	 * Runs activation actions.
	 */
	public function activate() {
		wbcr_gnz_deploy_mu_plugin();
	}

	/**
	 * Runs deactivation actions.
	 */
	public function deactivate() {
		wbcr_gnz_remove_mu_plugin();
	}
}
