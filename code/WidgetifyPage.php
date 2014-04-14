<?php

class WidgetifyPage extends Page {
	
	private static $db = array(
		'WidgetifyContent' => 'Text',
		'WidgetifyContentRaw' => 'Text',
		'JSFrontend' => 'Boolean',
		'CSSFrontend' => 'Boolean'
	);
	
	private static $has_one = array(
		'WidgetifyTemplate' => 'WidgetifyTemplate'
	);

	public function getCMSFields() {
		Requirements::css('widgetify/css/widgetify_cms.css');
		Requirements::javascript('framework/thirdparty/jquery/jquery.js');
		Requirements::javascript('widgetify/scripts/widgetify_page.js');
		
		$fields = parent::getCMSFields();
		
		$fields->push(HiddenField::create('WidgetifyContent', 'WidgetifyContent'));
		$fields->push(HiddenField::create('ThisID', 'ThisID', $this->ID));
		
		$tab = $fields->findOrMakeTab('Root.Main');
		
		$tab->insertAfter(HeaderField::create('WidgetifyTitle', 'Widgetify Template', 3), 'Metadata');
		
		if (!$this->WidgetifyTemplateID) $this->WidgetifyTemplateID = 0;
		$templatesMap = DataList::create('WidgetifyTemplate')->map();		
		$tab->insertAfter(DropdownField::create('WidgetifyTemplateID', 'Select Template', $templatesMap)->setEmptyString('- Select -'), 'WidgetifyTitle');
		
		$tab->insertAfter(CheckboxField::create('CSSFrontend', 'Apply template Stylesheet to front-end page'), 'WidgetifyTemplateID');
		$tab->insertAfter(CheckboxField::create('JSFrontend', 'Apply template Javascript to front-end page'), 'CSSFrontend');
		
		$tab->insertAfter(HeaderField::create('WidgetifyPreviewTitle', 'Widgetify Content', 3), 'JSFrontend');
		$tab->insertAfter(LiteralField::create('WidgetifyPreview', '<div id="widgetifyPreview" class="widgetifyTemplate"></div>'), 'WidgetifyPreviewTitle');
		
		$htmlField = HtmlEditorField::create('WidgetDynamicContent', false);
		$editorFieldContents = '
			<div id="WidgetDynamicContentHolder" class="WidgetDynamicContentHolder">
				<p id="edit-widget-title">Edit content</p>' .
				$htmlField->forTemplate() . '
				<p class="widget-edit-actions">
					<a href="javascript:;" id="save-widget-content" class="ss-ui-action-constructive ss-ui-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary">Update</a> &nbsp;&nbsp;
					<a href="javascript:;" id="cancel-widget-content" class="ss-ui-action-destructive ui-button ui-widget ui-state-default ui-button-text-icon-primary ui-corner-left ss-ui-button">Cancel</a>
				</p>
			</div>';
		$tab->insertAfter(LiteralField::create('WidgetDynamicContentPlaceHolder', $editorFieldContents), 'WidgetifyPreview');

		$fields->removeFieldFromTab('Root.Main', 'Content');
		return $fields;
	}
	
	static function prefixCSS($css) {
		$prefix = '.widgetifyTemplate';
		$parts = explode('}', $css);
		foreach ($parts as &$part) {
			if (empty($part) || trim($part) == '') {
				continue;
			}
			$subParts = explode(',', $part);
			foreach ($subParts as &$subPart) {
				$subPart = $prefix . ' ' . trim($subPart);
			}
			$part = implode(', ', $subParts);
		}
		return implode("}\n", $parts);
	}

	public function WidgetifyWidgets() {		
		$ids = array();
		if ($widgets = @json_decode($this->WidgetifyContent)) {
			foreach($widgets as $widget) {
				if ($widget->WidgetId != '_DYNAMIC_' && is_numeric($widget->WidgetId)) {					
					$ids[] = $widget->WidgetId;
				}
			}
		}
		if (count($ids)) {
			return DataList::create('WidgetifyWidget')->where('"WidgetifyWidget"."ID" IN (' . implode(',', array_unique($ids)) . ')');
		}
	}
	
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		if ($contentItems = @json_decode($this->WidgetifyContent)) {
			$rawContent = '';
			foreach($contentItems as $contentItem) {
				if (isset($contentItem->WidgetId)) {
					if ($contentItem->WidgetId == '_DYNAMIC_') {
						$rawContent .= $contentItem->DynamicContent . "\r\n";
					}
				}
			}
			$this->WidgetifyContentRaw = $rawContent; 
		}
	}
	
}


class WidgetifyPage_Controller extends Page_Controller {
	
	public function init() {
		parent::init();
		if ($this->WidgetifyTemplateID && $this->WidgetifyTemplate()) {
			$cssFile = $this->WidgetifyTemplate()->CSSFileID && $this->WidgetifyTemplate()->CSSFile()->Filename ? $this->WidgetifyTemplate()->CSSFile()->Filename : false;
			$jsFile = $this->WidgetifyTemplate()->JSFileID && $this->WidgetifyTemplate()->JSFile()->Filename ? $this->WidgetifyTemplate()->JSFile()->Filename : false; 
			if ($this->CSSFrontend && $cssFile) {
				Requirements::css($cssFile);
			}
			if ($this->JSFrontend && $jsFile) {
				Requirements::javascript($jsFile);
			}
		}
	}
	
	/*
	 *	Method responsible for returning the final parsed template to be displayed in the front-end through $Widgetify
	 *	this is first processed by $this->_widgetify()
	 */
	public function Widgetify() {
		$content = $this->_widgetify();
		$content = SSViewer::fromString($content)->process($this);
		return ShortcodeParser::get()->parse($content);
	}
	
	private function _widgetify() {
		$templateContents = $this->WidgetifyTemplate()->TemplateContent; 
		$ids = array();
		if ($widgets = @json_decode($this->WidgetifyContent)) {
			foreach($widgets as $widget) {
				if ($widget->WidgetId == '_DYNAMIC_') {
					// add dynamic content
					$templateContents = str_ireplace('{widget-' . $widget->Identifier . '}', $widget->DynamicContent, $templateContents);
				} else {
					// add widget content
					if ($widgetObject = DataObject::get_by_id('WidgetifyWidget', (int)$widget->WidgetId)) {
						$replacement = '';
						if ($widgetObject->Type == 'Content') {
							$replacement = $widgetObject->Content;
						} elseif ($widgetObject->Type == 'Include' && $widgetObject->incFile()) {
							$replacement = '<% include ' . $widgetObject->incFile() . ' %>';
						}
						$templateContents = str_ireplace('{widget-' . $widget->Identifier . '}', $replacement, $templateContents);
					}
				}
			}
		}
		// wipe any {widget-xx} tags that may have been left behind
		return preg_replace('/{widget-+(.*?)}/i', '', $templateContents);
	}
	
}


class WidgetifyPageController extends ContentController {
	
	private static $allowed_actions = array(
		'getTemplateContents',
		'updateWidgetifyContentJson',
		'getWidgetById'
	);
	
	function getTemplateContents($request) {
		$id = Convert::raw2sql($request->getVar('templateId'));
		
		if ($template = DataObject::get_by_id('WidgetifyTemplate', (int)$id)) {
			$output = '';
			if (trim($template->CSSContent) != '') $output .= '<style type="text/css">' . WidgetifyPage::prefixCSS($template->CSSContent) . '</style>';
			if (trim($template->JSContent) != '') $output .= '<script type="text/javascript">' . $template->JSContent . '</script>';
			
			$templateContent = WidgetifyTemplate::cleanTemplate($template->TemplateContent);
			$output .= $this->_loadWidgets($templateContent); 
			return $output;
		}
		return '<p><strong>Preview not available - have you selected a template for this page?</strong></p>';
	}
	
	/*
	 * 	Replace {widget-xx} tags with widget dropdowns and current selection + content according to $WidgetifyPage->WidgetifyContent  
	 */
	private function _loadWidgets($content) {
		$widgetifyCallback = new WidgetifyCallback($this->WidgetifyContent);
		$content = preg_replace_callback('/{widget-+(.*?)}/i', array($widgetifyCallback, 'widgetify'), $content);				
		return $content;		
	}
	
	/*
	 * 	Update json string and return it  
	 */
	public function updateWidgetifyContentJson($request) {
		$currentArray = json_decode(nl2br($request->postVar('current')));
		$content = json_decode(nl2br($request->postVar('content')));
		if (!$currentArray) $currentArray = array();
		$output = array();
		if (count($currentArray)) {
			foreach($currentArray as $currentItem) {
				if ($currentItem->Identifier != $content->Identifier) {
					$output[] = $currentItem;
				}
			}
		}
		$output[] = $content;
		return json_encode($output);
	}
	
	public function getWidgetById($request) {
		$output = array();
		$widgetId = Convert::raw2sql($request->getVar('widgetId'));
		if ($widget = DataObject::get_by_id('WidgetifyWidget', (int)$widgetId)) {
			$output['WidgetId'] = $widget->ID;
			if ($widget->Type == 'Content') $output['Content'] = $widget->Content; 	
			if ($widget->Type == 'Include') $output['Content'] = '<% include ' . $widget->incFile() . ' %>';
		}
		return json_encode($output);
	}
	
}

/*
 *	This class takes care of widgetifying the template in the CMS
 * 	In other words, it will replace {widget-xx} tags with related dropdowns and content 
 */
 
class WidgetifyCallback {
	
	private $_widgetifyContent;

	function __construct($widgetifyContent) {
		$this->_widgetifyContent = $widgetifyContent;
	}

    public function widgetify($matches) {
    	$identifier = $matches[1];
		return $this->_getWidgetsDropdown($identifier);
    }
	
	private function _getWidgetsDropdown($identifier) {
		$output = '<div class="widget-selector">';		
		$output .= '<div class="widget-selector-options">';
		$output .= '<select id="widget-' . $identifier . '" data-rel="' . $identifier . '">';
		$output .= '<option value="">- None -</option>';
		$output .= '<option value="_DYNAMIC_">Dynamic content</option>';
		$widgets = DataList::create('WidgetifyWidget');
		foreach($widgets as $widget) {
			$output .= '<option value="' . $widget->ID . '">' . $widget->Title . '</option>';
		}
		$output .= '</select>';
		$output .= '<span class="widget-action" id="widget-action-' . $identifier . '" data-rel="' . $identifier . '"></span>';
		$output .= '</div>';
		$output .= '<div id="widget-content-' . $identifier . '" data-rel="' . $identifier . '"></div>';
		$output .= '</div>';
		return $output;
	}
	
}








