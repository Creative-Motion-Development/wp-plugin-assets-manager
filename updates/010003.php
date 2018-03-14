<?php #comp-page builds: premium

	/**
	 * Updates for altering the table used to store statistics data.
	 * Adds new columns and renames existing ones in order to add support for the new social buttons.
	 */
	class WGZUpdate010003 extends Wbcr_Factory000_Update {

		public function install()
		{
			global $wpdb;

			$request = $wpdb->get_results("SELECT option_id, option_name, option_value FROM {$wpdb->prefix}options WHERE option_name LIKE 'wbcr_gonzales_%'");

			if( !empty($request) ) {
				foreach($request as $option) {
					$option_new_name = str_replace('wbcr_gonzales_', WGZ_Plugin::app()
						->getPrefix(), $option->option_name);
					if( !get_option($option_new_name, false) ) {
						$wpdb->query("UPDATE {$wpdb->prefix}options SET option_name='$option_new_name' WHERE option_id='{$option->option_id}'");
					} else {
						delete_option($option->option_name);
					}
				}
			}
		}
	}