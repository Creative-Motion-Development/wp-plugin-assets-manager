<?php

defined( 'ABSPATH' ) || die( 'Cheatin’ uh?' );

/**
 * @var array     $data
 * @var WGZ_Views $this
 */
if ( empty( $data['loaded_plugins'] ) ) {
	echo 'Plugins is not found!';

	return;
}

$active_plugin = reset( $data['loaded_plugins'] );
?>
<table class="wam-table">
    <thead>
    <tr>
        <th class="wam-table__th-plugins-list">Plugins</th>
        <th class="wam-table__th-plugin-settings"><?php echo $active_plugin['plugin_data']['Title']; ?></th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>
            <ul class="wam-nav-plugins">
				<?php foreach ( (array) $data['loaded_plugins'] as $plugin_name => $plugin ): ?>
                    <li class="wam-nav-plugins__tab<?php echo( $active_plugin['plugin_name'] == $plugin_name ? ' wam-nav-plugins__tab--active' : '' ) ?>">
                        <a href="#wam-<?php echo esc_attr( $plugin_name ); ?>">
                            <strong class="wam-plugin-name"><?php echo $plugin['plugin_data']['Title']; ?></strong>
                            <span><?php _e( 'Author', 'gonzales' ) ?>: <?php echo $plugin['plugin_data']['Author']; ?></span>
                            <span><?php _e( 'Version', 'gonzales' ) ?>: <?php echo $plugin['plugin_data']['Version']; ?></span>
                        </a>
                    </li>
				<?php endforeach; ?>
            </ul>
        </td>
        <td class="wam-table__td-plugin-settings">
			<?php foreach ( (array) $data['loaded_plugins'] as $plugin_name => $plugin ): ?>
                <div id="wam-<?php echo esc_attr( $plugin_name ); ?>" class="wam-nav-plugins__tab-content<?php echo( $active_plugin['plugin_name'] == $plugin_name ? ' wam-nav-plugins__tab-content--active' : '' ) ?>">
					<?php $this->print_template( 'part-tab-content-assets-plugins-settings', [
						'plugin_name'             => $plugin_name,
						'plugin_assets'           => $plugin['plugin_assets'],
						'conditions_logic_params' => $data['conditions_logic_params'],
					] ); ?>
                </div>
			<?php endforeach; ?>
        </td>
    </tr>
    </tbody>
</table> <!-- /end .wam-table -->