<?php

defined( 'ABSPATH' ) || die( 'Cheatin’ uh?' );

/**
 * @var array     $data
 * @var WGZ_Views $this
 */

$plugin_name = $data['name'];
?>
<div class="wam-plugin-settings">
    <div class="wam-plugin-settings__controls">
        <select class="wam-select<?php echo $data['select_control_classes']; ?> js-wam-select-plugin-load-mode" data-plugin-name="<?php echo esc_attr( $plugin_name ) ?>">
            <option value="enable"<?php selected( 'enable', $data['load_mode'] ) ?>>
				<?php _e( "Load plugin and its assets", 'gonzales' ) ?>
            </option>
            <option value="disable_assets"<?php selected( 'disable_assets', $data['load_mode'] ) ?>>
				<?php _e( "Don't load plugin assets", 'gonzales' ) ?>
            </option>
            <option value="disable_plugin"<?php selected( 'disable_plugin', $data['load_mode'] ) ?>>
				<?php _e( "Don't load plugin", 'gonzales' ) ?>
            </option>
        </select>
        <button class="wam-button wam-button--default wam-button__icon js-wam-button__icon--cogs js-wam-open-plugin-settings<?php echo esc_attr( $data['settings_button_classes'] ) ?>"></button>
    </div>
    <div class="js-wam-plugin-settings__conditions">
        <input type="hidden" data-plugin-name="<?php echo esc_attr( $plugin_name ) ?>" class="wam-conditions-builder__settings" value="<?php echo esc_attr( $data['visability'] ) ?>">
    </div>
</div>
<div class="wam-plugin-assets wam-plugin-<?php echo esc_attr( $plugin_name ) ?>-assets">
    <h2><?php _e( 'Loaded resourses on current page', 'gonzales' ) ?>:</h2>
    <table class="wam-assets-table wam-plugin-assets__table" style="margin:0;">
        <tr>
            <th style="width: 200px"><?php _e( 'Actions', 'gonzales' ) ?></th>
            <th style="width: 100px"><?php _e( 'Type', 'gonzales' ) ?></th>
            <th><?php _e( 'Handle/Source', 'gonzales' ) ?></th>
            <th><?php _e( 'Version', 'gonzales' ) ?></th>
            <th><?php _e( 'Size', 'gonzales' ) ?></th>
        </tr>
		<?php if ( ! empty( $data['assets'] ) ): ?>
			<?php foreach ( (array) $data['assets'] as $resource_type => $assets ): ?>
				<?php foreach ( (array) $assets as $resource_handle => $item ): ?>
                    <tr data-size="<?php echo esc_attr( $item['size'] ); ?>" class="js-wam-asset js-wam-<?php echo esc_attr( $resource_type ); ?>-asset wam-assets-table__asset-settings<?php echo $item['row_classes']; ?>" id="wam-assets-table__loaded-resourse-<?php echo md5( $resource_handle . $resource_type . $item['url_full'] ); ?>">
                        <td>
                            <select class="wam-select<?php echo $item['select_control_classes']; ?> js-wam-select-asset-load-mode">
                                <option value="enable"<?php selected( 'enable', $item['load_mode'] ) ?>>
									<?php _e( 'Enable', 'gonzales' ) ?>
                                </option>
                                <option value="disable"<?php selected( 'disable', $item['load_mode'] ) ?>>
									<?php _e( 'Disable', 'gonzales' ) ?>
                                </option>
                            </select>
                            <button class="wam-button wam-button--default wam-button__icon js-wam-button__icon--cogs js-wam-open-asset-settings<?php echo esc_attr( $item['settings_button_classes'] ); ?>"></button>
                        </td>
                        <td>
                        <span class="wam-asset-type wam-asset-type--<?php echo esc_attr( $resource_type ); ?>">
                            <?php echo esc_attr( $resource_type ); ?>
                        </span>
                        </td>
                        <td>
							<?php echo esc_html( $resource_handle ); ?><br>
                            <a href="<?php echo esc_url( $item['url_full'] ); ?>">
								<?php echo esc_html( $item['url_short'] ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $item['ver'] ); ?></td>
                        <td><?php echo esc_html( $item['size'] ); ?> KB</td>
                    </tr>
                    <tr id="wam-assets-table__loaded-resourse-<?php echo md5( $resource_handle . $resource_type . $item['url_full'] ); ?>-conditions" class="wam-assets-table__asset-settings-conditions">
                        <td colspan="5">
                            <p>
                                <input type="checkbox" class="wam-checkbox wam-assets-table__checkbox">
								<?php _e( 'Don\'t optimize file', 'gonzales' ) ?>
                                <i class="wam-help-hint wam-tooltip wam-tooltip--bottom" data-tooltip=""></i>
                            </p>
                            <p>
                                <input type="checkbox" class="wam-checkbox wam-assets-table__checkbox">
								<?php _e( 'Don\'t remove query string (version)', 'gonzales' ) ?>
                                <i class="wam-help-hint wam-tooltip wam-tooltip--bottom" data-tooltip=""></i>
                            </p>
                            <p>
								<?php _e( '<strong> You must set rules to disable the resource.</strong>
                            For example, if you select Page -> Equals -> All posts, then the script or style will not
                            loaded on all pages of type post.', 'gonzales' ) ?>
                            </p>
                            <div class="wam-asset-conditions-builder">
                                <input type="hidden" data-plugin-name="<?php echo esc_attr( $plugin_name ) ?>" data-resource-type="<?php echo esc_attr( $resource_type ) ?>" data-resource-handle="<?php echo esc_attr( $resource_handle ) ?>" class="wam-conditions-builder__settings" value="<?php echo esc_attr( $item['visability'] ) ?>">
                            </div>
                        </td>
                    </tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
		<?php endif; ?>
    </table>
</div>
