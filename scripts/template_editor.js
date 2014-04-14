var mirrorTemplate, mirrorCSS, mirrorJS;

(function($) {
		
	$(document).ready(function() {
		
		/*
		 *	Set up template editor fields
		 */		
		
		$('#TemplateContent').entwine({
			onmatch: function() {
				var templateContent = document.getElementById('Form_ItemEditForm_TemplateContent');
				mirrorTemplate = CodeMirror.fromTextArea(templateContent, {
					lineNumbers: true, 
					mode: 'text/html'
				});
				mirrorTemplate.on('change', function() {
					$('#Form_ItemEditForm_TemplateContent').html(mirrorTemplate.getValue());
				});
			}
		});
		$('#CSSContent').entwine({
			onmatch: function() {
				var cssContent = document.getElementById('Form_ItemEditForm_CSSContent');
				mirrorCSS = CodeMirror.fromTextArea(cssContent, {
					lineNumbers: true, 
					mode: 'text/css'
				});
				mirrorCSS.on('change', function() {
					$('#Form_ItemEditForm_CSSContent').html(mirrorCSS.getValue());
				});
				$(this).hide();
			}
		});
		$('#JSContent').entwine({
			onmatch: function() {
				var jsContent = document.getElementById('Form_ItemEditForm_JSContent');
				mirrorJS = CodeMirror.fromTextArea(jsContent, {
					lineNumbers: true, 
					mode: 'text/javascript'
				});
				mirrorJS.on('change', function() {
					$('#Form_ItemEditForm_JSContent').html(mirrorJS.getValue());
				});
				$(this).hide();
			}			
		});
		
		/*
		 * 	CMS Template tabs
		 */
		$('.tabChange').entwine({
			onclick: function() {
				$('.tabChange').removeClass('selected');
				$(this).addClass('selected');
				switch($(this).attr('id')) {
					case 'tabTemplate':
						$('#TemplateContent').show();
						$('#CSSContent, #JSContent').hide();
					break;
					case 'tabCSS':
						$('#CSSContent').show();
						$('#TemplateContent, #JSContent').hide();
					break;
					case 'tabJS':
						$('#JSContent').show();
						$('#CSSContent, #TemplateContent').hide();
					break;
				}
			}
		});
		
		
		/*
		 * 	Template validation
		 */
		function validateTemplate(html) {
			loading(true);
			$.ajax({
				url: 'WidgetifyTemplateController/getValidationTemplate?',
				data: 'content=' + html,
				type: 'POST',
				success: function(response) {
					$('#widgetifyLog').append(response);
					var objDiv = document.getElementById("widgetifyLog");
					objDiv.scrollTop = objDiv.scrollHeight;
					loading(false);
				}
			});
		}
		
		function showPreviewTemplate(html, css, js) {
			loading(true);
			$.ajax({
				url: 'WidgetifyTemplateController/getPreview?',
				data: 'html=' + html + '&css=' + css + '&js=' + js,
				type: 'POST',
				success: function(response) {
					$('#widgetifyPreview').html(response);
					loading(false);
				}
			});
		}

		$('#refreshAndValidate').entwine({
			onclick: function() {
				validateTemplate(mirrorTemplate.getValue());
				showPreviewTemplate(mirrorTemplate.getValue(), mirrorCSS.getValue(), mirrorJS.getValue());
			}
		});
		
		function loading(status) {
			$('#widgetifyPreview .widgetify-loading').remove();
			if (status) {				
				if ($('#widgetifyPreview').find('.widgetify-loading').size() == 0) {
					$('#widgetifyPreview').prepend('<div class="widgetify-loading"><p><strong>Loading...</strong></p></div>');
				}				
			}
		}
		
		

	});
	
})(jQuery);
