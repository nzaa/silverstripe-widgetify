(function($) {
		
	$(document).ready(function() {
		
		
		function toggleType(type) {
			if (type == 'Include') {
				$('#Content').hide();
				$('#IncludeFile').show();
			} else {
				$('#IncludeFile').hide();
				$('#Content').show();
			}
		}
		
		$('#Form_ItemEditForm_Type').chosen().entwine({
			onmatch: function() {
				toggleType($(this).val());
			},
			onchange: function() {
				toggleType($(this).val());
			}
		});
		

	});
	
})(jQuery);
