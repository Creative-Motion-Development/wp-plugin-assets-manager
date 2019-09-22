/**
 * Assets manager scripts
 * @author Webcraftic <wordpress.webraftic@gmail.com>
 * @copyright (c) 13.11.2017, Webcraftic
 * @version 1.0
 */
// [{"type":"group","conditions":[{"param":"location-some-page","operator":"equals","type":"select","value":"base_web"}]}]
// [{"type":"group","conditions":[{"param":"location-some-page","operator":"equals","type":"select","value":"base_web"}]}]

(function($) {
	'use strict';

	class AssetsManager {
		constructor() {
			var tabHash = window.location.hash.replace('#', '');

			if( tabHash ) {
				$('.js-wam-assets-type-tabs__button[data-type="' + tabHash + '"]').click();
			}

			this.initEvents();
			this.updateStat();
		}

		initEvents() {
			var self = this;

			$('.js-wam-assets-type-tabs__button').click(function() {
				self.switchCategoryTab($(this));
				return false;
			});

			$('.js-wam-nav-plugins__tab-switch').click(function() {
				self.switchPluginTab($(this));
				return false;
			});

			$('.js-wam-top-panel__save-button').click(function() {
				self.saveSettings();
				return false;
			});

			$('.js-wam-select-plugin-load-mode').change(function() {
				if( 'enable' === $(this).val() ) {
					self.enablePlugin($(this));
				} else if( 'disable_assets' === $(this).val() || 'disable_plugin' === $(this).val() ) {
					self.disablePlugin($(this));
				}

				return false;
			});

			$('.js-wam-open-plugin-settings').click(function() {
				if( $(this).hasClass('js-wam-button--opened') ) {
					self.closePluginSettings($(this));
					return false;
				}

				self.openPluginSettings($(this));

				return false;
			});

			$('.js-wam-select-asset-load-mode').change(function() {
				let selectElement = $(this);

				if( 'enable' === selectElement.val() ) {
					self.enableAsset(selectElement);
					return false;
				}
				self.disableAsset(selectElement);
				return false;
			});

			$('.js-wam-open-asset-settings').click(function() {
				if( $(this).hasClass('js-wam-button--opened') ) {
					self.closeAssetSettings($(this));
					return false;
				}

				self.openAssetSettings($(this));
				return false;
			});
		}

		switchCategoryTab(element) {
			window.location.hash = '#' + element.data('type');

			$('.js-wam-assets-type-tabs__button').removeClass('wam-assets-type-tab__active');
			element.addClass('wam-assets-type-tab__active');

			$('.wam-assets-type-tab-content').removeClass('wam-assets-type-tab-content__active');
			$('#wam-assets-type-tab-content__' + element.data('type')).addClass('wam-assets-type-tab-content__active');
		}

		switchPluginTab(element) {
			$('.js-wam-nav-plugins__tab-switch').removeClass('wam-nav-plugins__tab--active');
			element.addClass('wam-nav-plugins__tab--active');

			$('.wam-nav-plugins__tab-content').removeClass('js-wam-nav-plugins__tab-content--active');
			$(element.find('a').attr('href')).addClass('js-wam-nav-plugins__tab-content--active');

			$('.wam-table__th-plugin-settings').text(element.find('.wam-plugin-name').text());

		}

		setSettingsButtonOpenState(buttonElement) {
			buttonElement.removeClass('js-wam-button--opened');
			buttonElement.addClass('js-wam-button__icon--cogs').removeClass('js-wam-button__icon--close');
		}

		setSettingsButtonCloseState(buttonElement) {
			buttonElement.addClass('js-wam-button--opened');
			buttonElement.removeClass('js-wam-button__icon--cogs').addClass('js-wam-button__icon--close');
		}

		disablePlugin(selectElement) {
			let activeContainerElement = selectElement.closest('.js-wam-nav-plugins__tab-content--active'),
				settingsButtonElement = selectElement.closest('.wam-plugin-settings__controls').find('.js-wam-open-plugin-settings');

			/*if( currentContentTabElement.find('.js-wam-select-asset-load-mode option[value="disable"]:selected').length ) {
				var passAction = confirm("If you want to change the plugin’s load mode, all your logical settings to disable the plugins assets will be reset. Do you really want to do this?");
				if( !passAction ) {
					return;
				}
			}*/

			/*var notice = PNotify.notice({
  title: 'Confirmation Needed',
  text: 'Are you sure?',
  icon: 'fas fa-question-circle',
  hide: false,
  stack: {
    'dir1': 'down',
    'modal': true,
    'firstpos1': 25
  },
  modules: {
    Confirm: {
      confirm: true
    },
    Buttons: {
      closer: false,
      sticker: false
    },
    History: {
      history: false
    },
  }
});
notice.on('pnotify.confirm', function() {
  alert('Ok, cool.');
});
notice.on('pnotify.cancel', function() {
  alert('Oh ok. Chicken, I see.');
});*/

			settingsButtonElement.removeClass('js-wam-button--hidden');

			selectElement.removeClass('js-wam-select--enable')
				.addClass('js-wam-select--disable');

			// Disable assets table
			let assetSettingsContainer = activeContainerElement.find('.wam-assets-table__asset-settings');
			assetSettingsContainer.addClass('js-wam-assets-table__tr--disabled-section');
			//assetSettingsContainer.hide();

			let assetConditionsContainer = activeContainerElement.find('.wam-assets-table__asset-settings-conditions');
			assetConditionsContainer.hide();
			assetConditionsContainer.find(".wam-cleditor").remove();
			assetConditionsContainer.find(".wam-conditions-builder__settings").val('');

			activeContainerElement.find('.js-wam-select-asset-load-mode').val('disable')
				.removeClass('js-wam-select--enable')
				.addClass('js-wam-select--disable')
				.prop('disabled', true);

			activeContainerElement.find('.js-wam-open-asset-settings')
				.removeClass('js-wam-button--opened')
				.addClass('js-wam-button--hidden');

			this.openPluginSettings(settingsButtonElement);
			this.updateStat();
		}

		enablePlugin(selectElement) {
			let activeContainerElement = selectElement.closest('.js-wam-nav-plugins__tab-content--active'),
				settingsButtonElement = selectElement.closest('.wam-plugin-settings__controls').find('.js-wam-open-plugin-settings');

			settingsButtonElement.addClass('js-wam-button--hidden');

			selectElement.removeClass('js-wam-select--disable')
				.addClass('js-wam-select--enable');

			// Enable assets table
			activeContainerElement.find('.wam-assets-table__asset-settings').removeClass('js-wam-assets-table__tr--disabled-section');
			activeContainerElement.find('.js-wam-select-asset-load-mode').val('enable')
				.addClass('js-wam-select--enable')
				.removeClass('js-wam-select--disable')
				.prop('disabled', false);

			activeContainerElement.find('.js-wam-open-asset-settings')
				.addClass('js-wam-button--hidden');

			this.closePluginSettings(settingsButtonElement, true);
			this.updateStat();

		}

		openPluginSettings(buttonElement) {
			let containerElement = buttonElement.closest('.wam-plugin-settings'),
				editorContainerElement = containerElement.find('.js-wam-plugin-settings__conditions');

			this.setSettingsButtonCloseState(buttonElement);
			editorContainerElement.show();

			if( !editorContainerElement.find('.wam-cleditor').length ) {
				console.log('createConditionsEditor');
				this.createConditionsEditor(editorContainerElement);
			}
		}

		closePluginSettings(buttonElement, destroyEditor = false) {
			let containerElement = buttonElement.closest('.wam-plugin-settings'),
				editorContainerElement = containerElement.find('.js-wam-plugin-settings__conditions');

			this.setSettingsButtonOpenState(buttonElement);
			editorContainerElement.hide();

			if( destroyEditor ) {
				this.destroyCoditionEditor(editorContainerElement);
			}
		}

		disableAsset(selectElement) {
			let containerElement = selectElement.closest('tr'),
				settingsButtonElement = containerElement.find('.js-wam-open-asset-settings');

			settingsButtonElement.removeClass('js-wam-button--hidden');
			containerElement.addClass('js-wam-assets-table__tr--disabled-section');
			selectElement.removeClass('js-wam-select--enable').addClass('js-wam-select--disable');

			this.openAssetSettings(settingsButtonElement);
			this.updateStat();
		}

		enableAsset(selectElement) {
			let containerElement = selectElement.closest('tr'),
				settingsButtonElement = containerElement.find('.js-wam-open-asset-settings');

			settingsButtonElement.addClass('js-wam-button--hidden');
			selectElement.removeClass('js-wam-select--disable').addClass('js-wam-select--enable');
			containerElement.removeClass('js-wam-assets-table__tr--disabled-section');

			this.closeAssetSettings(settingsButtonElement, true);
			this.updateStat();
		}

		/**
		 * Toggle Asset Settings
		 * @param buttonElement Object settings button
		 * @returns {boolean}
		 */
		openAssetSettings(buttonElement) {
			var placeID = buttonElement.closest('tr').attr('id'),
				place = $('#' + placeID + '-conditions');

			if( buttonElement.hasClass('js-wam-button--opened') ) {
				return false;
			}

			this.setSettingsButtonCloseState(buttonElement);
			place.show();

			if( !place.find('.wam-cleditor').length ) {
				this.createConditionsEditor(place.find(".wam-asset-conditions-builder"));
			}

			return true;
		}

		closeAssetSettings(buttonElement, destroyEditor = false) {
			var placeID = buttonElement.closest('tr').attr('id'),
				place = $('#' + placeID + '-conditions');

			if( !buttonElement.hasClass('js-wam-button--opened') ) {
				return false;
			}

			this.setSettingsButtonOpenState(buttonElement);
			place.hide();

			if( destroyEditor ) {
				this.destroyCoditionEditor(place.find(".wam-asset-conditions-builder"));
			}

			return true;
		}

		saveSettings() {
			var settings = {
				plugins: {},
				theme: {},
				misc: {}
			};

			$('.wam-nav-plugins__tab-content').each(function() {
				let pluginGroupVisabilityConditionsElement = $(this).find('.js-wam-plugin-settings__conditions').find('.wam-conditions-builder__settings'),
					pluginName = pluginGroupVisabilityConditionsElement.data('plugin-name'),
					pluginGroupVisabilityConditionsVal = pluginGroupVisabilityConditionsElement.val(),
					pluginGroupLoadMode = $('.js-wam-select-plugin-load-mode', $(this)).val();

				if( pluginName ) {
					if( !settings['plugins'][pluginName] ) {
						settings['plugins'][pluginName] = {};
					}
					settings['plugins'][pluginName]['load_mode'] = pluginGroupLoadMode;
					settings['plugins'][pluginName]['visability'] = pluginGroupVisabilityConditionsVal;
				}

				$('.wam-table__asset-settings-conditions', $(this)).each(function() {
					let resourceVisabilityConditionsElement = $(this).find('.wam-conditions-builder__settings'),
						resourceVisabilityConditionsVal = resourceVisabilityConditionsElement.val(),
						resourceType = resourceVisabilityConditionsElement.data('resource-type'),
						resourceHandle = resourceVisabilityConditionsElement.data('resource-handle');

					if( settings['plugins'][pluginName] ) {
						if( !settings['plugins'][pluginName][resourceType] ) {
							settings['plugins'][pluginName][resourceType] = {};
						}

						if( 'enable' !== pluginGroupLoadMode ) {
							resourceVisabilityConditionsVal = "";
						}

						settings['plugins'][pluginName][resourceType][resourceHandle] = {
							visability: resourceVisabilityConditionsVal
						};
					}
				});

				if( undefined === typeof window.wam_localize_data || !wam_localize_data.ajaxurl ) {
					throw new Error("Undefined wam_localize_data, please check the var in source!");
				}
			});

			$('.wam-conditions-builder__settings', '#wam-assets-type-tab-content__theme,#wam-assets-type-tab-content__misc').each(function() {
				let groupType = $(this).data('group-type'),
					recourceType = $(this).data("resource-type"),
					resourceHandle = $(this).data("resource-handle");

				if( !settings[groupType][recourceType] ) {
					settings[groupType][recourceType] = {};
				}

				settings[groupType][recourceType][resourceHandle] = {
					visability: $(this).val()
				}

			});

			let stackBottomRight = {
				'dir1': 'up',
				'dir2': 'left',
				'firstpos1': 25,
				'firstpos2': 25
			};

			PNotify.closeAll();
			PNotify.alert({
				title: 'Saving settings!',
				text: 'Please wait, saving settings ...',
				stack: stackBottomRight,
				hide: false
			});

			$.ajax(wam_localize_data.ajaxurl, {
				type: 'post',
				dataType: 'json',
				data: {
					action: 'wam-save-settings',
					settings: settings,
					_wpnonce: $('#wam-save-button').data('nonce')
				},
				success: function(response) {
					PNotify.closeAll();

					if( !response || !response.success ) {
						if( response.data ) {
							console.log(response.data.error_message_content);
							PNotify.alert({
								title: response.data.error_message_title,
								text: response.data.error_message_content,
								stack: stackBottomRight,
								type: 'error',
								//hide: false
							});
						} else {
							console.log(response);
						}
						return;
					}
					if( response.data ) {
						PNotify.alert({
							title: response.data.save_massage_title,
							text: response.data.save_message_content,
							stack: stackBottomRight,
							type: 'success',
							//hide: false
						});
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					PNotify.alert({
						title: 'Unknown error',
						text: thrownError,
						stack: {
							'dir1': 'up',
							'dir2': 'left',
							'firstpos1': 25,
							'firstpos2': 25
						},
						type: 'error',
						//hide: false
					});
				}
			});
		}

		createConditionsEditor(element) {
			element.wamConditionsEditor({
				// where to get an editor template
				templateSelector: '#wam-conditions-builder-template',
				// where to put editor options
				saveInputSelector: '.wam-conditions-builder__settings',
				groups: [
					{
						"type": "group",
						"conditions": [
							{
								"param": "location-some-page",
								"operator": "equals",
								"type": "select",
								"value": "base_web"
							}

						]
					}

				]
			});
		}

		destroyCoditionEditor(element) {
			element.find('.wam-cleditor').remove();
			element.find('.wam-conditions-builder__settings').val('');
		}

		updateStat() {
			let total_requests = 0,
				total_size = 0,
				optimized_size = 0,
				disabled_js = 0,
				disabled_css = 0;

			$('.js-wam-asset').each(function() {
				let size = $(this).data('size');

				if( !$.isNumeric(size) ) {
					return;
				}

				total_requests++;
				total_size = total_size + size;

				if( !$(this).hasClass('js-wam-assets-table__tr--disabled-section') ) {
					optimized_size = optimized_size + size;
				} else {
					if( $(this).hasClass('js-wam-js-asset') ) {
						disabled_js++;
					}
					if( $(this).hasClass('js-wam-css-asset') ) {
						disabled_css++;
					}
				}
			});

			$('.wam-float-panel__data-item.__info-request').find('.wam-float-panel__item_value').html(total_requests);
			$('.wam-float-panel__data-item.__info-total-size').find('.wam-float-panel__item_value').html(Math.round(total_size) + ' KB');
			$('.wam-float-panel__data-item.__info-reduced-total-size').find('.wam-float-panel__item_value').html(Math.round(optimized_size) + ' KB');
			$('.wam-float-panel__data-item.__info-disabled-js').find('.wam-float-panel__item_value').html(disabled_js);
			$('.wam-float-panel__data-item.__info-disabled-css').find('.wam-float-panel__item_value').html(disabled_css);
		}
	}

	$(function() {
		new AssetsManager();
	});

})(jQuery);
