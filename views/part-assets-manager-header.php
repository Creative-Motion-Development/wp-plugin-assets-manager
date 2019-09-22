<?php

defined( 'ABSPATH' ) || die( 'Cheatin’ uh?' );

/**
 * @var array     $data
 * @var WGZ_Views $this
 */
?>
<header class="wbcr-gnz-panel">
    <div class="wbcr-gnz-panel__left">
        <div class="wbcr-gnz-panel__logo"></div>
        <ul class="wbcr-gnz-panel__data  panel__data-main">
            <li class="wbcr-gnz-panel__data-item __info-query">
				<?php _e( 'Total requests', 'gonzales' ) ?>:
                <b class="wbcr-gnz-panel__item_value">--</b>
            </li>
            <li class="wbcr-gnz-panel__data-item __info-all-weight">
				<?php _e( 'Total size', 'gonzales' ) ?>:
                <b class="wbcr-gnz-panel__item_value"><span class="wbcr-gnz-panel__color-1">--</span></b>
            </li>
            <li class="wbcr-gnz-panel__data-item __info-opt-weight"><?php _e( 'Optimized size', 'gonzales' ) ?>:
                <b class="wbcr-gnz-panel__item_value">
                    <span class="wbcr-gnz-panel__color-2">--</span>
                </b>
            </li>
            <li class="wbcr-gnz-panel__data-item __info-off-js"><?php _e( 'Disabled js', 'gonzales' ) ?>:
                <b class="wbcr-gnz-panel__item_value">-- </b>
            </li>
            <li class="wbcr-gnz-panel__data-item __info-off-css"><?php _e( 'Disabled css', 'gonzales' ) ?>:
                <b class="wbcr-gnz-panel__item_value">-- </b>
            </li>
        </ul>
    </div>
    <div class="wbcr-gnz-panel__right">
        <button class="wbcr-gnz-panel__reset wbcr-reset-button" type="button">
			<?php _e( 'Reset', 'gonzales' ) ?>
        </button>
        <button id="wam-save-button" class="wbcr-gnz-panel__save js-wam-top-panel__save-button" data-nonce="<?php echo wp_create_nonce( 'wam_save_settigns' ); ?>"><?php _e( 'Save', 'gonzales' ) ?></button>

        <label class="wbcr-gnz-panel__checkbox  wam-tooltip  wam-tooltip--bottom" data-tooltip="<?php _e( 'In test mode, you can experiment with disabling unused scripts safely for your site. The resources that you disabled will be visible only to you (the administrator), and all other users will receive an unoptimized version of the site, until you remove this tick', 'gonzales' ) ?>.">
            <input class="wbcr-gnz-panel__checkbox-input visually-hidden" type="checkbox">
            <span class="wbcr-gnz-panel__checkbox-text"><?php _e( 'Safe mode', 'gonzales' ) ?></span>
        </label>
        <!--<label class="wbcr-gnz-panel__checkbox  wam-tooltip  wam-tooltip--bottom" data-tooltip="<?php _e( 'In test mode, you can experiment with disabling unused scripts safely for your site. The resources that you disabled will be visible only to you (the administrator), and all other users will receive an unoptimized version of the site, until you remove this tick', 'gonzales' ) ?>.">
            <input class="wbcr-gnz-panel__checkbox-input visually-hidden" type="checkbox">
            <span class="wbcr-gnz-panel__checkbox-text-premium"><?php _e( 'Safe mode <b>PRO</b>', 'gonzales' ) ?></span>
        </label>-->
        <button class="wbcr-gnz-panel__close wbcr-close-button" type="button" aria-label="<?php _e( 'Close', 'gonzales' ) ?>" data-href="' . remove_query_arg( 'wbcr_assets_manager' ) ?>"></button>
    </div>
</header>