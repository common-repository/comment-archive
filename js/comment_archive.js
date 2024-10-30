(function($) {
	$(document).ready(function() {
		$('a.comment-archive').click(function() {
			var that = this;
			
			$.post(ajax.ajaxurl, {
				action : 'comment_archive',
				id : $(that).attr('rel')
			}, function(r, textStatus, jqXHR) {
				if (r){
					$(that).parents('tr.comment').fadeOut();
				}
			});

			return false;
		});
		
		$('a.comment-unarchive').click(function() {
			var that = this;
			
			$.post(ajax.ajaxurl, {
				action : 'comment_unarchive',
				id : $(that).attr('rel')
			}, function(r, textStatus, jqXHR) {
				if (r){
					$(that).parents('tr.comment').fadeOut();
				}
			});

			return false;
		});
	});
})(jQuery);