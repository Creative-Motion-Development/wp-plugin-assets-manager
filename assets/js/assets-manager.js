/**
 * Assets manager scripts
 * @author Webcraftic <wordpress.webraftic@gmail.com>
 * @copyright (c) 13.11.2017, Webcraftic
 * @version 1.0
 */

(function($) {
	'use strict';

	$(function() {

		//var $hidden = $("#winp_visibility_groups");
		//var json_data = $.parseJSON($hidden.val());
		function createConditionEditor(element) {
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

		/**
		 * Destroy editor and clean options field
		 * @param {object} element
		 */
		function destroyCoditionEditor(element) {
			element.find('.wam-cleditor').remove();
			element.find('.wam-conditions-builder__settings').val('');
		}

		$('.js-wam-select-plugin-load-mode').change(function() {
			let isDisabled = false,
				currentContentTabElement = $(this).closest('.wam-nav-plugins__tab-content--active'),
				editorElement = currentContentTabElement.find('.js-wam-plugin-load-conditions-builder');

			if( 'enable' === $(this).val() ) {
				isDisabled = false;

				$(this).removeClass('wam-select--disable').addClass('wam-select--enable');

				// Enable assets table
				currentContentTabElement.find('.wam-assets-table__plugin-settings').removeClass('wam-select--disabled_section');
				currentContentTabElement.find('.js-wam-switch').val('enable')
					.addClass('wam-select--enable')
					.removeClass('wam-select--disable')
					.prop('disabled', false);

				currentContentTabElement.find('.js-wam-open-asset-settings').prop('disabled', false);

				destroyCoditionEditor(editorElement);

			} else if( 'disable_assets' === $(this).val() || 'disable_plugin' === $(this).val() ) {

				if( !isDisabled && currentContentTabElement.find('.js-wam-switch option[value="disable"]:selected').length ) {
					var passAction = confirm("If you want to change the plugin’s load mode, all your logical settings to disable the plugins assets will be reset. Do you really want to do this?");
					if( !passAction ) {
						return;
					}
				}

				// Баг с DIsabled и бага при переключении режимов, редактор условий перезаписывается.

				isDisabled = true;

				$(this).removeClass('wam-select--enable')
					.addClass('wam-select--disable');

				currentContentTabElement.find(".wam-cleditor").remove();
				currentContentTabElement.find(".wam-conditions-builder__settings")
					.val('');

				// Disable assets table
				currentContentTabElement.find('.wam-assets-table__plugin-settings')
					.addClass('wam-select--disabled_section');
				currentContentTabElement.find('.wam-assets-table__asset-settings')
					.hide();

				currentContentTabElement.find('.js-wam-switch').val('disable')
					.removeClass('wam-select--enable')
					.addClass('wam-select--disable')
					.prop('disabled', true);

				currentContentTabElement.find('.js-wam-open-asset-settings')
					.removeClass('.wam-openned')
					.prop('disabled', true);

				if( !editorElement.find('.wam-cleditor').length ) {
					createConditionEditor(editorElement);
				}
			}
		});

		$('.js-wam-switch').change(function() {
			var settingsButton = $(this).closest('tr').find('.js-wam-open-asset-settings'),
				placeID = $(this).closest('tr').attr('id'),
				place = $('#' + placeID + '-conditions');

			if( 'enable' === $(this).val() ) {
				settingsButton.removeClass('wam-openned');
				place.hide();
				place.find('.wam-cleditor').remove();
				$(this).removeClass('wam-select--disable').addClass('wam-select--enable');
				$(this).closest('tr').removeClass('wam-select--disabled_section');

				destroyCoditionEditor(place.find(".wam-asset-conditions-builder"));
			} else if( 'disable' === $(this).val() ) {
				$(this).closest('tr').addClass('wam-select--disabled_section');
				$(this).removeClass('wam-select--enable').addClass('wam-select--disable');

				$(this).next().addClass('wam-openned');
				place.show();

				if( !place.find('.wam-cleditor').length ) {
					createConditionEditor(place.find(".wam-asset-conditions-builder"));
				}
			}

			return false;
		})

		$('.js-wam-open-asset-settings').click(function() {
			var placeID = $(this).closest('tr').attr('id'),
				place = $('#' + placeID + '-conditions');

			if( $(this).hasClass('wam-openned') ) {
				$(this).removeClass('wam-openned');
				place.hide();
				return false;
			}

			$(this).addClass('wam-openned');
			place.show();

			if( !place.find('.wam-cleditor').length ) {
				createConditionEditor(place.find(".wam-asset-conditions-builder"));
			}

			return false;
		});

		$('.wam-nav-plugins__tab').click(function() {
			$('.wam-nav-plugins__tab').removeClass('wam-nav-plugins__tab--active');
			$(this).addClass('wam-nav-plugins__tab--active');

			$('.wam-nav-plugins__tab-content').removeClass('wam-nav-plugins__tab-content--active');
			$($(this).find('a').attr('href')).addClass('wam-nav-plugins__tab-content--active');

			$('.wam-table__th-plugin-settings').text($(this).find('.wam-plugin-name').text());

			return false;
		});

		/*$('.wbcr-gnz-disable').on('change', function(ev) {
			var class_name = 'wam-table__loaded-super-no';
			var handle = $(this).data('handle');
			if( handle != undefined ) {
				class_name = 'wam-table__loaded-no';
			}

			if( $(this).prop('checked') == true ) {
				$(this).closest('label').find('input[type="hidden"]').val('disable');
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable').show();
				$(this).closest('tr').find('.wbcr-state').removeClass('wam-table__loaded-yes');
				$(this).closest('tr').find('.wbcr-state').addClass(class_name).trigger('cssClassChanged');

				if( typeof wbcrChangeHandleState == 'function' ) {
					wbcrChangeHandleState(this, 1);
				}
			} else {
				$(this).closest('label').find('input[type="hidden"]').val('');
				$(this).closest('tr').find('.wbcr-assets-manager-enable').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').show();
				$(this).closest('tr').find('.wbcr-state').removeClass(class_name);
				$(this).closest('tr').find('.wbcr-state').addClass('wam-table__loaded-yes').trigger('cssClassChanged');

				if( typeof wbcrChangeHandleState == 'function' ) {
					wbcrChangeHandleState(this, 0);
				}
			}
		});*/

		/*$('.wbcr-gnz-action-select').on('change', function(ev) {
			var selectElement = $(this).children(':selected');
			$(this).closest('.wbcr-assets-manager-enable').find('.wbcr-assets-manager').hide();

			if( selectElement.val() != 'current' ) {
				$(this).closest('.wbcr-assets-manager-enable').find('.wbcr-assets-manager.' + selectElement.val()).show();
			}
		});

		$('.wbcr-gnz-sided-disable').on('change', function(ev) {
			$(this).closest('label').find('input[type="hidden"]').val($(this).prop('checked') ? 1 : 0);

			var handle = $(this).data('handle');
			if( handle != undefined ) {
				$('.wbcr-gnz-sided-' + handle)
					.prop('checked', $(this).prop('checked'))
					.closest('label')
					.find('input[type="hidden"]').val($(this).prop('checked') ? 1 : 0);
			}
		});

		$('.wbcr-reset-button').on('click', function() {
			$('.wbcr-gnz-disable').each(function() {
				$(this).prop('checked', false).trigger('change');
				$(this).closest('input').val('');
			});
			$('.wbcr-gnz-sided-disable').each(function() {
				$(this).prop('checked', false).trigger('change');
				$(this).closest('input').val(1);
			});
		});

		$('.wbcr-state').bind('cssClassChanged', function() {
			var el = $(this).parent('td').parent('tr').find('.wbcr-info-data');
			if( $(this).hasClass('wam-table__loaded-no') || $(this).hasClass('wam-table__loaded-super-no') ) {
				if( el.length > 0 ) {
					el.data('off', 1);
				}
			} else {
				if( el.length > 0 ) {
					el.data('off', 0);
				}
			}

			if( typeof wbcrCalculateInformation == 'function' ) {
				wbcrCalculateInformation();
			}
		});

		if( typeof wbcrCalculateInformation == 'function' ) {
			wbcrCalculateInformation();
		}*/

		$('.wam-assets-type-tabs__button').click(function() {
			window.location.hash = '#' + $(this).data('type');

			$('.wam-assets-type-tabs__button').removeClass('wam-assets-type-tab__active');
			$(this).addClass('wam-assets-type-tab__active');

			$('.wam-assets-type-tab-content').removeClass('wam-assets-type-tab-content__active');
			$('#wam-assets-type-tab-content__' + $(this).data('type')).addClass('wam-assets-type-tab-content__active');

			return false;
		});

		var tabHash = window.location.hash.replace('#', '');
		console.log(tabHash);
		if( tabHash ) {
			$('.wam-assets-type-tabs__button[data-type="' + tabHash + '"]').click();
		}

		/*if ($('#wpadminbar').length > 0) {
		 var h = $('#wpadminbar').height();
		 if (h > 0) {
		 $('#wbcr-gnz header.wbcr-gnz-panel').css('top', h + 'px');
		 var top = $('#wbcr-gnz ul.wbcr-gnz-tabs').css('top');
		 $('#wbcr-gnz ul.wbcr-gnz-tabs').css('top', top.replace('px', '') * 1 + h + 'px');
		 }
		 }*/

		$('.wbcr-close-button').on('click', function() {
			document.location.href = $(this).data('href');
		});
	});

})(jQuery);
