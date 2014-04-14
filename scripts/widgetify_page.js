(function($) {
		
	$(document).ready(function() {
		
		var widgetDynamicContentEditingIdentifier;
		
		
		$('#Form_EditForm_WidgetifyContent').entwine({
			onmatch: function() {
				if ($(this).val() == '') {
					$(this).val('[]');
				}
			}
		});
		
		function reloadTemplate(templateId) {
			loading(true);
			$.ajax({
				url: 'WidgetifyPageController/getTemplateContents',
				data: 'templateId=' + templateId,
				type: 'GET',
				success: function(response) {
					$('#widgetifyPreview').html(response);
					setDropdownsFromWidgetifyContent();
					updateWidgetsOnScreen('_ALL_');
					loading(false);
				}
			});
		}
		
		$('#Form_EditForm_WidgetifyTemplateID').chosen().entwine({
			onmatch: function() {
				reloadTemplate($(this).val());
			},
			onchange: function() {
				reloadTemplate($(this).val());
			}
		});
		
		
		function updateWidgetifyContent(obj) {
			loading(true);
			$.ajax({
				url: 'WidgetifyPageController/updateWidgetifyContentJson?',
				data: 'current=' + encodeURIComponent(nl2br($('#Form_EditForm_WidgetifyContent').val())) + '&content=' + encodeURIComponent(nl2br(JSON.stringify(obj))),
				type: 'POST',
				async: false,
				success: function(response) {
					$('#Form_EditForm_WidgetifyContent').val(response);
					updateWidgetsOnScreen(obj.Identifier);
					loading(false);
				}
			});
		}
		
		function setDropdownsFromWidgetifyContent() {
			var widgets = $.parseJSON($('#Form_EditForm_WidgetifyContent').val());
			widgets = widgets != null ? widgets : new Array(); 
			for (var i = 0; i < widgets.length; i++) {
				var widget = widgets[i];
				$('#widget-' + widget.Identifier).val(widget.WidgetId);
			}
		}
		
		/*
		 * Widget dropdowns need to be already set with a value before calling this function
		 * PS. call setDropdownsFromWidgetifyContent() first
		 */
		function updateWidgetsOnScreen(ident) {
			$('.widget-selector select').each(function() {
				var identifier = $(this).attr('data-rel');
				if (identifier == ident || ident == '_ALL_') {
					var widgetId = $(this).val();
					var $action = $('#widget-action-' + identifier);
					var $content = $('#widget-content-' + identifier);
					var widget = widgetId == '_DYNAMIC_' ? getWidgetByIdentifier(identifier) : getAjaxWidgetById(widgetId);

					// if first time selection of a dynamic content widget, this won't exist in the hidden input field array neither in the database as an object
					// in this case we need to create it on-the-fly					
					if (!widget) {
						widget = new Object();
						widget.Identifier = identifier;
						widget.WidgetId = '_DYNAMIC_';
						widget.DynamicContent = '';						
					}
					
					if (widget) {
						if (widgetId == '_DYNAMIC_') {	
							$action.html(widget.WidgetId == '_DYNAMIC_' ? '<a href="javascript:;" data-rel="' + identifier + '" class="edit-content" id="edit-content-widget-' + identifier + '">[edit]</a>' : '');
							$content.html(widget.DynamicContent);
						} else {
							$action.html('');
							$content.html(widget.length > 0 ? widget.Content : '');
						}
					} else {
						$content.html('&lt;ERROR&gt;');
						$action.html('');
					}
				}
			});
		}
		
		function getWidgetByIdentifier(identifier) {
			var widgetSettings = $.parseJSON($('#Form_EditForm_WidgetifyContent').val());
			if (widgetSettings != null) {
				for (var i = 0; i < widgetSettings.length; i++) {
					var widget = widgetSettings[i];
					if (widget.Identifier == identifier) {
						return widget;
						break;
					}
				}				
				widget = new Object();
				widget.Identifier = identifier;
				widget.WidgetId = '_DYNAMIC_';
				widget.DynamicContent = '';
				return widget;				
			} else {
				return new Object();
			}
		}
		
		function getAjaxWidgetById(widgetId) {
			var output = false;
			loading(true);
			$.ajax({
				url: 'WidgetifyPageController/getWidgetById?',
				data: 'widgetId=' + widgetId,
				type: 'GET',
				async: false,
				success: function(response) {
					output = response;
					loading(false);
				}
			});
			return $.parseJSON(output);
		}
		
		
		$('.widget-selector select').entwine({
			onchange: function() {
				var identifier = $(this).attr('data-rel');
				var obj = new Object();
				obj.Identifier = identifier;
				obj.WidgetId = $(this).val();
				
				var widgetFromInputField = getWidgetByIdentifier(identifier);
				
				if (widgetFromInputField) {
					obj.DynamicContent = widgetFromInputField.DynamicContent;
				} else {
					obj.DynamicContent = '';
				}
					
				updateWidgetifyContent(obj);
			}
		});
		
		
		$('.widget-selector-options .edit-content').entwine({
			onclick: function() {
				$('#widgetifyPreview .widgetify-loading').remove();
				if ($('#widgetifyPreview').find('.widgetify-loading').size() == 0) {
					$('#widgetifyPreview').prepend('<div class="widgetify-loading"></div>');
				}				
				var identifier = $(this).attr('data-rel');
				widgetDynamicContentEditingIdentifier = identifier;
				var widgetFromInputField = getWidgetByIdentifier(identifier);
				var content = widgetFromInputField ? widgetFromInputField.DynamicContent : '';
				tinyMCE.get('WidgetDynamicContent').setContent(content);
				var editorTop = $('.cms-content-fields').scrollTop() + 50;
				$('#WidgetDynamicContentHolder').attr('style', 'top:' + editorTop + 'px !important').show();
			}
		});
		
		$('#cancel-widget-content').entwine({
			onclick: function() {
				tinyMCE.get('WidgetDynamicContent').setContent('');				
				$('#WidgetDynamicContentHolder').hide();
				$('#widgetifyPreview .widgetify-loading').remove();
			}
		});

		$('#save-widget-content').entwine({
			onclick: function() {
				$('#widgetifyPreview .widgetify-loading').remove();
				var widgetObj = getWidgetByIdentifier(widgetDynamicContentEditingIdentifier);
				widgetObj.DynamicContent = tinyMCE.get('WidgetDynamicContent').getContent();
				updateWidgetifyContent(widgetObj);
				widgetDynamicContentEditingIdentifier = false;
				$('#WidgetDynamicContentHolder').hide();
			}
		});

		function nl2br(str, is_xhtml) {   
			var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';    
			return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1'+ breakTag +'$2');
		}
		
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

