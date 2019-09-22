<?php

defined( 'ABSPATH' ) || die( 'Cheatin’ uh?' );

/**
 * @var array     $data
 * @var WGZ_Views $this
 */
?>
<header class="wam-float-panel">
    <div class="wam-float-panel__left">
        <div class="wam-float-panel__logo"></div>
        <ul class="wam-float-panel__data  panel__data-main">
            <li class="wam-float-panel__data-item __info-request">
				<?php _e( 'Total requests', 'gonzales' ) ?>:
                <b class="wam-float-panel__item_value">--</b>
            </li>
            <li class="wam-float-panel__data-item __info-total-size">
				<?php _e( 'Total size', 'gonzales' ) ?>:
                <b class="wam-float-panel__item_value">--</b>
            </li>
            <li class="wam-float-panel__data-item __info-reduced-total-size"><?php _e( 'Optimized size', 'gonzales' ) ?>:
                <b class="wam-float-panel__item_value">--</b>
            </li>
            <li class="wam-float-panel__data-item __info-disabled-js"><?php _e( 'Disabled js', 'gonzales' ) ?>:
                <b class="wam-float-panel__item_value">-- </b>
            </li>
            <li class="wam-float-panel__data-item __info-disabled-css"><?php _e( 'Disabled css', 'gonzales' ) ?>:
                <b class="wam-float-panel__item_value">-- </b>
            </li>
        </ul>
    </div>
    <div class="wam-float-panel__right">
        <a class="wam-float-panel__reset wbcr-reset-button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'wam-reset-settings' => 1 ] ) ) ); ?>">
			<?php _e( 'Reset', 'gonzales' ) ?>
        </a>
        <button id="wam-save-button" class="wam-float-panel__save js-wam-top-panel__save-button" data-nonce="<?php echo wp_create_nonce( 'wam_save_settigns' ); ?>"><?php _e( 'Save', 'gonzales' ) ?></button>
        <label class="wam-float-panel__checkbox  wam-tooltip  wam-tooltip--bottom" data-tooltip="<?php _e( 'In test mode, you can experiment with disabling unused scripts safely for your site. The resources that you disabled will be visible only to you (the administrator), and all other users will receive an unoptimized version of the site, until you remove this tick', 'gonzales' ) ?>.">
            <input class="wam-float-panel__checkbox-input visually-hidden" type="checkbox">
            <span class="wam-float-panel__checkbox-text"><?php _e( 'Safe mode', 'gonzales' ) ?></span>
        </label>
        <!--<label class="wam-float-panel__checkbox  wam-tooltip  wam-tooltip--bottom" data-tooltip="<?php _e( 'In test mode, you can experiment with disabling unused scripts safely for your site. The resources that you disabled will be visible only to you (the administrator), and all other users will receive an unoptimized version of the site, until you remove this tick', 'gonzales' ) ?>.">
            <input class="wam-float-panel__checkbox-input visually-hidden" type="checkbox">
            <span class="wam-float-panel__checkbox-text-premium"><?php _e( 'Safe mode <b>PRO</b>', 'gonzales' ) ?></span>
        </label>-->
        <button class="wam-float-panel__close wbcr-close-button" type="button" aria-label="<?php _e( 'Close', 'gonzales' ) ?>" data-href="' . remove_query_arg( 'wbcr_assets_manager' ) ?>"></button>
    </div>
</header>