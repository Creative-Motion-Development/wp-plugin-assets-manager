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

function wbcrShowInformation(query_count, all_weight, opt_weight, off_js, off_css) {
    all_weight = Math.round(all_weight * 10) / 10;
    all_weight += ' Kb';

    opt_weight = Math.round(opt_weight * 10) / 10;
    opt_weight += ' Kb';

    jQuery('.wbcr-information.__info-query').html(wbcram_data.text.total_query + ": " + query_count);
    jQuery('.wbcr-information.__info-all-weight').html(wbcram_data.text.total_weight + ": " + all_weight);
    jQuery('.wbcr-information.__info-opt-weight').html(wbcram_data.text.opt_weight + ": " + opt_weight);
    jQuery('.wbcr-information.__info-off-js').html(wbcram_data.text.off_js + ": " + off_js);
    jQuery('.wbcr-information.__info-off-css').html(wbcram_data.text.off_css + ": " + off_css);
    jQuery('.wbcr-info-wrap').css('display', 'inline-block');
}

function wbcrCalculateInformation() {
    var count_elements = jQuery('.wbcr-info-data').length;

    var query_count = 0;
    var all_weight = 0;
    var opt_weight = 0;
    var off_js = 0;
    var off_css = 0;
    jQuery('.wbcr-info-data').each(function() {
        all_weight += parseFloat(jQuery(this).val());

        if (jQuery(this).data('off') != 1) {
            query_count++;
            opt_weight += parseFloat(jQuery(this).val());
        }

        if (jQuery(this).data('off') == 1) {
            if (jQuery(this).data('type') == 'js') {
                off_js++;
            } else {
                off_css++;
            }
        }

        if (!--count_elements) {
            wbcrShowInformation(
                query_count, all_weight, opt_weight, off_js, off_css
            );
        }
    });
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

                if ($(this).data('handle') !== '') {
                    $('.wbcr-state-' + $(this).data('handle')).
                        addClass('wbcr-imp-state-1').
                        trigger('cssClassChanged');
                }
			}
			else {
				$(this).closest('tr').find('.wbcr-assets-manager-enable').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').show();
                $(this).closest('tr').find('.wbcr-state').removeClass('wbcr-state-1');
                $(this).closest('tr').find('.wbcr-state').addClass('wbcr-state-0').trigger('cssClassChanged');

                if ($(this).data('handle') !== '') {
                    $('.wbcr-state-' + $(this).data('handle')).
                        removeClass('wbcr-imp-state-1').
                        trigger('cssClassChanged');
                }
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
            })
		});

		$('input[name="wbcr_for_admin"]').on('change', function() {
		    if ($(this).prop('checked')) {
		        $(this).parent('label').addClass('wbcr-warning');
            } else {
                $(this).parent('label').removeClass('wbcr-warning');
            }
		});

		$('.wbcr-state').bind('cssClassChanged', function() {
		    var el = $(this).parent('td').parent('tr').find('.wbcr-info-data');
		    if ($(this).hasClass('wbcr-state-1') || $(this).hasClass('wbcr-imp-state-1')) {
                $(this).text(wbcram_data.text.no);
                el.data('off', 1);
			} else {
                $(this).text(wbcram_data.text.yes);
                el.data('off', 0);
			}
            wbcrCalculateInformation();
		});

        wbcrCalculateInformation();
        wbcrResizeEnableColumn();

		$(window).resize( function() {
            wbcrResizeEnableColumn();
		})
	});

})(jQuery);
