<?php

defined( 'ABSPATH' ) || die( 'Cheatin’ uh?' );

/**
 * @var array     $data
 * @var WGZ_Views $this
 */

if ( empty( $data['assets'] ) ) {
	echo 'Assets is not found!';

	return;
}
?>
<table class="wam-table" style="margin:0;">
    <tr>
        <th style="width: 200px">Actions</th>
        <th style="width: 100px">Type</th>
        <th>Handle/Source</th>
        <th>Version</th>
        <th>Size</th>
    </tr>
	<?php if ( ! empty( $data['assets'] ) ): ?>
		<?php foreach ( (array) $data['assets'] as $type => $assets ): ?>
			<?php foreach ( (array) $assets as $name => $item ): ?>
                <tr>
                    <td>
                        <select class="wam-select" name="" id="" style="display:inline-block;">
                            <option value="enable">Загружать</option>
                            <option value="disable">Не загружать</option>
                        </select>
                        <a href="#" class="wam-open-conditions-button">
                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABtElEQVQ4jZWTMWsUURSFv7MMgwRZRMRCxEKIbCWxCTY22jmTIcVikT8gksoqhUVIYSFbRAjYioVNMAiTmSZBbQT/QIotUkXQYBFCCClkyLF4M8Mmo6K3ejzu/d49594nY85HmmR9mxnJF0Djosz3Okl19P5wPwDegjaBhQlwJ19NB2mS3bJ5LvkD6BRYs4nB6xIj0BKwB4yKMt9vAFFzsL0iaWhrHkAikgAYAvO2Y0nYVMBSR4KkbaAKhW7BoB4oDjD/kPzpjAdpkvXTJJsN3TSG6iNwD5gGvwQfgwCOgTh5mF1vATYzwAawBmpeXi7K/HNR5ru2RjY7tgHdBF6DH6dJFgH0wqi4GgxrozVJ4hA4UW2IzSVJVxr5EWgMLEu+A3pU1y2mSfYCOAKGkgb1/YGkdfB7UHV+jLeBL8BU0MwO6ISwE9eCPxoDD4oy/9aZAvgpeKpu/KKtuzb3QzGAsBkwMcIzAFv79QIdAruSqWUfAGPbSPwEvk8C2nlLrNpEQYYrSW9s9yW9A1aAZ8BX4NUkQL//THM3QE+Ay8BGUeZbnaS/AQIki+vFqopy8/S/Af8avwCRfKU+1FZmLAAAAABJRU5ErkJggg==" alt="">
                        </a>
                    </td>
                    <td>
                        <span class="wam-asset-type wam-asset-type--<?php echo esc_attr( $type ); ?>">
                            <?php echo $type; ?>
                        </span>
                    </td>
                    <td>
						<?php echo esc_html( $name ); ?><br>
                        <a href="<?php echo esc_url( $item['url_full'] ); ?>">
							<?php echo $item['url_short']; ?>
                        </a>
                    </td>
                    <td><?php echo $item['ver']; ?></td>
                    <td><?php echo $item['size']; ?> KB</td>
                </tr>
			<?php endforeach; ?>
		<?php endforeach; ?>
	<?php endif; ?>
</table>
