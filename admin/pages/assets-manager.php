<?php

	/**
	 * The page Settings.
	 *
	 * @since 1.0.0
	 */
	class WbcrGnz_AssetsManagerPage extends FactoryPages000_ImpressiveThemplate {

		/**
		 * The id of the page in the admin menu.
		 *
		 * Mainly used to navigate between pages.
		 * @see FactoryPages000_AdminPage
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $id = "gonzales";
		public $page_menu_dashicon = 'dashicons-image-filter';
		//public $show_right_sidebar_in_options = true;
		//public $show_bottom_sidebar = false;

		public $page_menu_position = 95;

		/**
		 * Stores list of all available assets (used in rendering panel)
		 *
		 * @var array
		 */
		//private $collection = array();

		public function __construct(Factory000_Plugin $plugin)
		{
			$this->menuTitle = __('Assets manager', 'gonzales');

			if( !defined('LOADING_GONZALES_AS_ADDON') ) {
				$this->internal = false;
				$this->menuTarget = 'options-general.php';
				$this->addLinkToPluginActions = true;
			}

			//add_filter('wbcr_factory_imppage_right_sidebar_widgets', array($this, 'rightSidebar'));

			parent::__construct($plugin);
		}

		public function getMenuTitle()
		{
			return defined('LOADING_GONZALES_AS_ADDON')
				? __('Assets manager', 'gonzales')
				: __('General', 'gonzales');
		}

		/**
		 * We register notifications for some actions
		 * @param $notices
		 * @return array
		 */
		/*public function rightSidebar($widgets)
		{
			//$widgets['configuration_widget'] = $this->showConfigurationWidget();

			//unset($widgets['info_widget']);
			unset($widgets['rating_widget']);
			unset($widgets['donate_widget']);

			return $widgets;
		}*/

		/*public function showConfigurationWidget()
		{
			?>
			<div class="wbcr-factory-sidebar-widget wbcr-factory-configuration wbcr-factory-warning wbcr-factory-hide">
				<p>
					<strong><?php _e('Make manager quiet after setting optimal configuration', 'factory_pages_000'); ?></strong>
				</p>
				<ul>
					<li><b>Q:</b> When I should implement this definition?</li>
					<li><b>A:</b> When you have 100% sure that everything works after saving final plugin configuration
					</li>
					<li><b>Q:</b> Why I should implement it?</li>
					<li><b>A:</b> Because a lot of HTML code is added to html body and 99% of time you won't use it.
						Other
						thing is
						you'll save space in top admin bar.
					</li>
					<li><b>Q:</b> Gonzales menu/panel renders only for administrator, I don't care.</li>
					<li><b>A:</b> It's OK but less memory is conumed and website renders a bit faster when implemented.
						Less
						it better right?
					</li>
				</ul>
			</div>
		<?php
		}*/

		/**
		 * Permalinks options.
		 *
		 * @since 1.0.0
		 * @return mixed[]
		 */
		public function getOptions()
		{
			$options = array();
			$types = get_post_types(array('public' => true), 'objects');
			$post_types = array();
			foreach($types as $type_name => $type) {
				$post_types[] = array($type_name, $type->label);
			}
			$options[] = array(
				'type' => 'checkbox',
				'way' => 'buttons',
				'name' => 'disable_assets_manager',
				'title' => __('Disable assets manager', 'gonzales'),
				'layout' => array('hint-type' => 'icon', 'hint-icon-color' => 'grey'),
				'hint' => __('Full disable of the module.', 'gonzales'),
				'eventsOn' => array(
					'hide' => '#wbcr-gnz-asset-manager-extend-options'
				),
				'eventsOff' => array(
					'show' => '#wbcr-gnz-asset-manager-extend-options'
				),
				'default' => false
			);

			$options[] = array(
				'type' => 'div',
				'id' => 'wbcr-gnz-asset-manager-extend-options',
				'items' => array(
					array(
						'type' => 'separator',
						'cssClass' => 'factory-separator-dashed'
					),
					array(
						'type' => 'checkbox',
						'way' => 'buttons',
						'name' => 'disable_assets_manager_panel',
						'title' => __('Disable assets manager panel', 'gonzales'),
						'layout' => array('hint-type' => 'icon', 'hint-icon-color' => 'green'),
						'hint' => __('By default in your admin bar there is a button for control the assets scripts and styles. With this option, you can turn off the script manager on front and back-end.', 'gonzales'),
						/*'eventsOn' => array(
							'show' => '.wbcr-factory-sidebar-widget.wbcr-factory-configuration'
						),
						'eventsOff' => array(

							'hide' => '.wbcr-factory-sidebar-widget.wbcr-factory-configuration'
						),*/
						'default' => false
					),
					array(
						'type' => 'checkbox',
						'way' => 'buttons',
						'name' => 'disable_assets_manager_on_front',
						'title' => __('Disable assets manager on front', 'gonzales'),
						'layout' => array('hint-type' => 'icon', 'hint-icon-color' => 'grey'),
						'hint' => __('Disables assets manager initialization for frontend.', 'gonzales'),
						'default' => false
					),
					array(
						'type' => 'checkbox',
						'way' => 'buttons',
						'name' => 'disable_assets_manager_on_backend',
						'title' => __('Disable assets manager on back-end', 'gonzales'),
						'layout' => array('hint-type' => 'icon', 'hint-icon-color' => 'grey'),
						'hint' => __('Disables assets manager initialization for backend.', 'gonzales'),
						'default' => true
					)
				)
			);

			$formOptions = array();

			$formOptions[] = array(
				'type' => 'form-group',
				'items' => $options,
				//'cssClass' => 'postbox'
			);

			return apply_filters('wbcr_gnz_assets_manager_options', $formOptions);
		}
	}

	FactoryPages000::register($wbcr_gonzales_plugin, 'WbcrGnz_AssetsManagerPage');
