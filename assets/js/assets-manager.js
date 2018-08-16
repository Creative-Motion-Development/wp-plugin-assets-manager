/**
 * Assets manager scripts
 * @author Webcraftic <wordpress.webraftic@gmail.com>
 * @copyright (c) 13.11.2017, Webcraftic
 * @version 1.0
 */

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
                $(this).closest('tr').find('.wbcr-state').addClass('wbcr-state-1');
			}
			else {
				$(this).closest('tr').find('.wbcr-assets-manager-enable').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').show();
                $(this).closest('tr').find('.wbcr-state').removeClass('wbcr-state-1');
                $(this).closest('tr').find('.wbcr-state').addClass('wbcr-state-0');
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
	});

})(jQuery);
