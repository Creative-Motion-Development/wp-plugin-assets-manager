/**
 * Assets manager scripts
 * @author Webcraftic <wordpress.webraftic@gmail.com>
 * @copyright (c) 13.11.2017, Webcraftic
 * @version 1.0
 */

function wbcrResizeEnableColumn() {
    var w = jQuery(window).width();
    if ( w > 1280) {
        jQuery('.wbcr-enable-th').width(450);
    } else if ( w > 1024) {
        jQuery('.wbcr-enable-th').width(300);
    } else if ( w > 800) {
        jQuery('.wbcr-enable-th').width(200);
    } else {
        jQuery('.wbcr-enable-th').width(150);
    }
}

(function($) {
	'use strict';

	$(function() {
		$('.wbcr-gonzales-disable-select').each(function() {
			$(this).addClass($(this).children(':selected').val());
		}).on('change', function(ev) {
			var selectElement = $(this).children(':selected');

			$(this).attr('class', 'wbcr-gonzales-disable-select').addClass(selectElement.val());

			if( selectElement.val() == 'disable' ) {
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable').show();
                $(this).closest('tr').find('.wbcr-state').removeClass('wbcr-state-0');
                $(this).closest('tr').find('.wbcr-state').addClass('wbcr-state-1').trigger('cssClassChanged');

                if (typeof wbcrChangeHandleState == 'function') {
                    wbcrChangeHandleState(this, 1);
                }
			}
			else {
				$(this).closest('tr').find('.wbcr-assets-manager-enable').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').show();
                $(this).closest('tr').find('.wbcr-state').removeClass('wbcr-state-1');
                $(this).closest('tr').find('.wbcr-state').addClass('wbcr-state-0').trigger('cssClassChanged');

                if (typeof wbcrChangeHandleState == 'function') {
                    wbcrChangeHandleState(this, 0);
                }
			}
		});

		$('.wbcr-gonzales-sided-select').on('change', function(ev) {
		    if ($(this).val() == 1) {
                $(this).addClass("wbcr-sided-yes");
            } else {
                $(this).removeClass("wbcr-sided-yes");
            }
        });

		$('.wbcr-gonzales-action-select').on('change', function(ev) {
			var selectElement = $(this).children(':selected');
			$(this).attr('class', 'wbcr-gonzales-action-select').addClass(selectElement.val());

            $(this).closest('span').find('.wbcr-assets-manager').hide();

			if( selectElement.val() != 'current' ) {
                $(this).closest('span').find('.wbcr-assets-manager.' + selectElement.val()).show();
			}
		});

		$('.wbcr-add-custom-url').on('click', function() {
		    var name = $(this).data('name');
            $(this).before("<input type='text' name='" + name + "' class='wbcr-gonzales-text' value=''>");
		});

		$('.wbcr-reset-button').on('click', function() {
		    $('select.wbcr-gonzales-disable-select').each(function() {
		        $(this).val("").trigger('change');
            });
		    $('select.wbcr-gonzales-sided-select').each(function() {
		        $(this).val(0).trigger('change');
            });
		});

		$('.wbcr-state').bind('cssClassChanged', function() {
		    var el = $(this).parent('td').parent('tr').find('.wbcr-info-data');
		    if ($(this).hasClass('wbcr-state-1') || $(this).hasClass('wbcr-imp-state-1')) {
                $(this).text(wbcram_data.text.no);
                if (el.length > 0) {
                    el.data('off', 1);
                }
			} else {
                $(this).text(wbcram_data.text.yes);
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
        wbcrResizeEnableColumn();

		$(window).resize( function() {
            wbcrResizeEnableColumn();
		})
	});

})(jQuery);
