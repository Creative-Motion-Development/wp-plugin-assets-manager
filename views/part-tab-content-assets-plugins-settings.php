<?php

defined( 'ABSPATH' ) || die( 'Cheatin’ uh?' );

/**
 * @var array     $data
 * @var WGZ_Views $this
 */
?>
<p style="vertical-align: top">
    <select class="wam-select wam-select--enable js-wam-select-plugin-load-mode">
        <option value="enable"><?php _e( "Load plugin and its assets", 'gonzales' ) ?></option>
        <option value="disable_assets"><?php _e( "Don't load plugin assets", 'gonzales' ) ?></option>
        <option value="disable_plugin"><?php _e( "Don't load plugin", 'gonzales' ) ?></option>
    </select>
    <button class="wam-button wam-button--default wam-button__icon wam-button__icon--cogs js-wam-open-plugin-settings"></button>
</p>
<div class="js-wam-plugin-load-conditions-builder">
    <input type="hidden" name="wam_filter_<?php echo esc_attr( $data['plugin_name'] ) ?>" class="wam-conditions-builder__settings" value=''>
</div>
<h2>Loaded resourses on current page:</h2>
<table class="wam-assets-table" style="margin:0;">
    <tr>
        <th style="width: 200px"><?php _e( 'Actions', 'gonzales' ) ?></th>
        <th style="width: 100px"><?php _e( 'Type', 'gonzales' ) ?></th>
        <th><?php _e( 'Handle/Source', 'gonzales' ) ?></th>
        <th><?php _e( 'Version', 'gonzales' ) ?></th>
        <th><?php _e( 'Size', 'gonzales' ) ?></th>
    </tr>
	<?php if ( ! empty( $data['plugin_assets'] ) ): ?>
		<?php foreach ( (array) $data['plugin_assets'] as $type => $assets ): ?>
			<?php foreach ( (array) $assets as $name => $item ): ?>
                <tr id="wam-assets-table__loaded-resourse-<?php echo md5( $name . $type . $item['url_full'] ); ?>" class="wam-assets-table__plugin-settings">
                    <td>
                        <select class="wam-select wam-select--enable js-wam-switch">
                            <option value="enable">Enable</option>
                            <option value="disable">Disable</option>
                        </select>
                        <button class="wam-button wam-button--default wam-button__icon wam-button__icon--cogs js-wam-open-asset-settings"></button>
                    </td>
                    <td>
                        <span class="wam-asset-type wam-asset-type--<?php echo esc_attr( $type ); ?>">
                            <?php echo esc_attr( $type ); ?>
                        </span>
                    </td>
                    <td>
						<?php echo esc_html( $name ); ?><br>
                        <a href="<?php echo esc_url( $item['url_full'] ); ?>">
							<?php echo esc_html( $item['url_short'] ); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html( $item['ver'] ); ?></td>
                    <td><?php echo esc_html( $item['size'] ); ?> KB</td>
                </tr>
                <tr id="wam-assets-table__loaded-resourse-<?php echo md5( $name . $type . $item['url_full'] ); ?>-conditions" class="wam-assets-table__asset-settings">
                    <td colspan="5">
                        <p>
                            <input type="checkbox" class="wam-checkbox wam-assets-table__checkbox">
                            Don't optimize file
                            <i class="wam-help-hint wam-tooltip wam-tooltip--bottom" data-tooltip="Работает только Clearfy, Autoptimize, Wp rocket"></i>
                        </p>
                        <p>
                            <input type="checkbox" class="wam-checkbox wam-assets-table__checkbox">
                            Don't remove query string (version)
                            <i class="wam-help-hint wam-tooltip wam-tooltip--bottom" data-tooltip="Работает только Clearfy"></i>
                        </p>
                        <p>
							<?php _e( '<strong> You must set rules to disable the resource.</strong>
                            For example, if you select Page -> Equals -> All posts, then the script or style will not
                            loaded on all pages of type post.', 'gonzales' ) ?>
                        </p>
                        <div class="wam-asset-conditions-builder">
                            <input type="hidden" name="wam_filter_<?php echo $type . '_' . $name ?>" class="wam-conditions-builder__settings" value=''>
                        </div>
                    </td>
                </tr>
			<?php endforeach; ?>
		<?php endforeach; ?>
	<?php endif; ?>
</table>

