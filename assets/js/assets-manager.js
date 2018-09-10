/**
 * Assets manager scripts
 * @author Webcraftic <wordpress.webraftic@gmail.com>
 * @copyright (c) 13.11.2017, Webcraftic
 * @version 1.0
 */

(function($) {
	'use strict';

	$(function() {
		$('.wbcr-gnz-disable').on('change', function(ev) {
            var class_name = 'table__loaded-super-yes';
            var handle = $(this).data('handle');
            if (handle != undefined) {
                class_name = 'table__loaded-yes';
            }

			if( $(this).prop('checked') == true ) {
                $(this).closest('label').find('input[type="hidden"]').val('disable');
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable').show();
                $(this).closest('tr').find('.wbcr-state').removeClass('table__loaded-no');
                $(this).closest('tr').find('.wbcr-state').addClass(class_name).trigger('cssClassChanged');

                if (typeof wbcrChangeHandleState == 'function') {
                    wbcrChangeHandleState(this, 1);
                }
			}
			else {
                $(this).closest('label').find('input[type="hidden"]').val('');
				$(this).closest('tr').find('.wbcr-assets-manager-enable').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').show();
                $(this).closest('tr').find('.wbcr-state').removeClass(class_name);
                $(this).closest('tr').find('.wbcr-state').addClass('table__loaded-no').trigger('cssClassChanged');

                if (typeof wbcrChangeHandleState == 'function') {
                    wbcrChangeHandleState(this, 0);
                }
			}
		});

		$('.wbcr-gnz-action-select').on('change', function(ev) {
			var selectElement = $(this).children(':selected');
            $(this).closest('span').find('.wbcr-assets-manager').hide();

			if( selectElement.val() != 'current' ) {
                $(this).closest('span').find('.wbcr-assets-manager.' + selectElement.val()).show();
			}
		});

        $('.wbcr-gnz-sided-disable').on('change', function(ev) {
            $(this).closest('label').find('input[type="hidden"]').val($(this).prop('checked') ? 1 : 0);

            var handle = $(this).data('handle');
            if (handle != undefined) {
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
                $(this).closest('input').val(0);
            });
		});

		$('.wbcr-state').bind('cssClassChanged', function() {
		    var el = $(this).parent('td').parent('tr').find('.wbcr-info-data');
		    if ($(this).hasClass('table__loaded-yes') || $(this).hasClass('table__loaded-super-yes')) {
                if (el.length > 0) {
                    el.data('off', 1);
                }
			} else {
                if (el.length > 0) {
                    el.data('off', 0);
                }
			}

            if (typeof wbcrCalculateInformation == 'function') {
                wbcrCalculateInformation();
            }
		});

        if (typeof wbcrCalculateInformation == 'function') {
            wbcrCalculateInformation();
        }

        $('ul.tabs').on('click', 'button:not(.active)', function() {
            window.location.hash = '#' + $(this).data('hash');
            $(this)
                .addClass('active').parent().siblings().find('button').removeClass('active')
                .closest('.content').find('div.tabs-content').removeClass('active').eq($(this).parent().index()).addClass('active');
        });

        var tabHash = window.location.hash.replace('#', '');
        if (tabHash) {
            $('ul.tabs button[data-hash="' + tabHash + '"]').click();
        } else {
            $('ul.tabs li').eq(0).find('button').click();
        }

        if ($('#wpadminbar').length > 0) {
            var h = $('#wpadminbar').height();
            if (h > 0) {
                $('#wbcr-gnz header.panel').css('top', h + 'px');
                var top = $('#wbcr-gnz ul.tabs').css('top');
                $('#wbcr-gnz ul.tabs').css('top', top.replace('px', '') * 1 + h + 'px');
            }
        }

        $('.wbcr-close-button').on('click', function() {
            document.location.href = $(this).data('href');
        });
	});

})(jQuery);
