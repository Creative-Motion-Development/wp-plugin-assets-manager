<?php #comp-page builds: premium

/**
 * Updates for altering the table used to store statistics data.
 * Adds new columns and renames existing ones in order to add support for the new social buttons.
 */
class WGZUpdate010108 extends Wbcr_Factory000_Update {

	public function install() {
		global $wpdb;
		wbcr_gnz_deploy_mu_plugin();
	}
}