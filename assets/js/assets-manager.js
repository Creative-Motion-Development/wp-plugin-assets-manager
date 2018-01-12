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

			if( selectElement.val() == 'everywhere' ) {
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable').show();
			}
			else {
				$(this).closest('tr').find('.wbcr-assets-manager-enable').hide();
				$(this).closest('tr').find('.wbcr-assets-manager-enable-placeholder').show();
			}

			if( selectElement.val() == 'everywhere' || selectElement.val() == 'current' ) {
				$(this).closest('tr').find('.wbcr-state').removeClass('wbcr-state-0');
				$(this).closest('tr').find('.wbcr-state').addClass('wbcr-state-1');
			} else {
				$(this).closest('tr').find('.wbcr-state').removeClass('wbcr-state-1');
				$(this).closest('tr').find('.wbcr-state').addClass('wbcr-state-0');
			}
		});
	});

})(jQuery);
