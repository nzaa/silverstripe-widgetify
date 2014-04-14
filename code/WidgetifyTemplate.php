<?php

class WidgetifyTemplate extends DataObject {
	
	private static $db = array(
		'Title' => 'Varchar(100)',
		'TemplateContent' => 'Text',
		'CSSContent' => 'Text',
		'JSContent' => 'Text'
	);
	
	private static $has_one = array(
		'Folder' => 'Folder',
		'TemplateFile' => 'File',
		'CSSFile' => 'File',
		'JSFile' => 'File'
	);

	private static $searchable_fields = array(
		'Title',
		'TemplateContent',
		'CSSContent',
		'JSContent'
	);
	
	private static $summary_fields = array(
		'Title',
		'TotalPages' => 'TotalPages'
	);
	
	private static $default_sort = '"WidgetifyTemplate"."Title" ASC';	
	
	public static $templatesRoot = 'widgetify-templates';
	
	private $_templateFilename = 'template.ss';
	
	private $_cssFilename = 'style.css';
	
	private $_jsFilename = 'scripts.js';
	
	protected $_written = false;
	
	public function getCMSFields() {
		Requirements::css('widgetify/thirdparty/codemirror-3.18/lib/codemirror.css');
		Requirements::css('widgetify/css/widgetify_cms.css');
		Requirements::javascript('framework/thirdparty/jquery/jquery.js');
		Requirements::javascript('widgetify/thirdparty/codemirror-3.18/lib/codemirror.js');
		Requirements::javascript('widgetify/thirdparty/codemirror-3.18/mode/xml/xml.js');
		Requirements::javascript('widgetify/thirdparty/codemirror-3.18/mode/javascript/javascript.js');
		Requirements::javascript('widgetify/thirdparty/codemirror-3.18/mode/css/css.js');
		Requirements::javascript('widgetify/scripts/template_editor.js');
		
		$fields = FieldList::create();
		$fields->push(TextField::create('Title', 'Template Title', false, 100));
		$fields->push(HeaderField::create('WidgetifyPreviewTitle', 'Preview', 4));
		$fields->push(LiteralField::create('WidgetifyPreview', '<div id="widgetifyPreview" class="widgetifyTemplate"><p><strong>Click "Refresh &amp; validate" to load preview</strong></p></div><p><a href="javascript:;" id="refreshAndValidate" class="ss-ui-action-constructive ss-ui-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary">Refresh &amp; validate</a></p>'));
		$fields->push(LiteralField::create('WidgetifyLog', '<p><strong>Validation log:</strong></p><div id="widgetifyLog"></div>'));
		$fields->push(HeaderField::create('WidgetifyEditorTitle', 'Template editor', 4));
		$fields->push(LiteralField::create('WidgetifyEditorHelp', '<p><strong>Note:</strong> in the HTML tab, insert the tag <strong>{widget-UniqueIdentifier}</strong> where widgets should be placed. <em>Example: {widget-1} {widget-2} ...</em></p>'));
		$fields->push(LiteralField::create('Tabs', '<p class="tabs"><a href="javascript:;" id="tabTemplate" class="selected tabChange">HTML</a><a href="javascript:;" id="tabCSS" class="tabChange">Stylesheet</a><a href="javascript:;" id="tabJS" class="tabChange">Javascript</a></p>'));
		$fields->push(TextareaField::create('TemplateContent', false));
		$fields->push(TextareaField::create('CSSContent', false));
		$fields->push(TextareaField::create('JSContent', false));
		
		if ($this->ID) {
			$fields->push(HeaderField::create('WidgetifyRelatedTitle', 'Pages using this template', 4));
			$fields->push(LiteralField::create('AppliedTo', $this->_getTablePages()));
		}
		
		return $fields;
	}
	
	private function _getTablePages() {
		$tablePages = '<table class="widgetify-related">';
		$tablePages .= '<tr class="table-header"><td>Page Title</td><td>Page Link</td><td>CMS Link</td></tr>';
		$pages = $this->WidgetifyPages(); 
		if ($pages && $pages->count()) {
			foreach($pages as $page) {
				$absLink = Controller::join_links(Director::protocolAndHost(), $page->Link());
				$absCMSLink = Controller::join_links(Director::protocolAndHost(), '/admin/pages/edit/show/', $page->ID);
				$tablePages .= '<tr><td>' . $page->MenuTitle . '</td><td><a href="' . $absLink . '" target="_blank">' . $absLink . '</a></td><td><a href="' . $absCMSLink . '" target="_blank">' . $absCMSLink . '</a></td></tr>';
			}
		}
		$tablePages .= '</table>';
		return $tablePages;
	}
	
	public function WidgetifyPages() {
		return DataList::create('WidgetifyPage')->where('"WidgetifyTemplateID" = ' . $this->ID);
	}

	public function TotalPages() {
		return count($this->WidgetifyPages());
	}
	

	/*
	 * 	Return valid content (identifiers) for template
	 */
	static function cleanTemplate($content) {
		$content = preg_replace_callback('/{widget-+(.*?)}/i',
			function ($matches) {
				return '{widget-' . preg_replace('/[^A-Za-z0-9.]/', '', strtolower($matches[1])) . '}';
			}, $content);				
		if (preg_match_all('/{widget-+(.*?)}/i', $content, $matches)) {
			$intersectedItems = array_intersect(array_unique($matches[1]), $matches[1]);
			for($c = 0; $c < count($matches[1]); $c++) {
				if (!isset($intersectedItems[$c])) {
					$identifier = uniqid();
					$intersectedItems[$c] = substr($identifier, strlen($identifier)-4, 4);
				}
			}
			ksort($intersectedItems);
			$identCallBack = new IdentifierCallback($intersectedItems);
			$content = preg_replace_callback('/{widget-+(.*?)}/i', array($identCallBack, 'callback'), $content);				
		}
		return $content;
	}
	
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		$this->TemplateContent = self::cleanTemplate($this->TemplateContent);
	}
	
	public function onAfterWrite() {
		parent::onAfterWrite();

		$folderParts = explode('/', getcwd());
		array_pop($folderParts);
		$docRoot = implode('/', $folderParts) . '/';

		// create folder only if not set
		if (!$this->FolderID) {
			$rootFolder = Folder::find_or_make(self::$templatesRoot);
			$folder = Folder::find_or_make(self::$templatesRoot . '/' . $this->_toFilename($this->Title));
			$folder->ParentID = $rootFolder->ID;
			$folder->write();
			$this->FolderID = $folder->ID;			
		} else {
			$folder = $this->Folder();
		}
		
		if ($this->TemplateFileID) {
			$tplFile = $this->TemplateFile();
		} else {
			$tplFile = new File();
			$tplFile->set_validation_enabled(false);
			$tplFile->Filename = $folder->Filename . $this->_templateFilename;
			$tplFile->ParentID = $folder->ID;
			$this->TemplateFileID = $tplFile->write();
		}
		
		if ($this->CSSFileID) {
			$cssFile = $this->CSSFile();
		} else {
			$cssFile = new File();
			$cssFile->set_validation_enabled(false);
			$cssFile->Filename = $folder->Filename . $this->_cssFilename;
			$cssFile->ParentID = $folder->ID;
			$this->CSSFileID = $cssFile->write();
		}
		
		if ($this->JSFileID) {
			$jsFile = $this->JSFile();
		} else {
			$jsFile = new File();
			$jsFile->set_validation_enabled(false);
			$jsFile->Filename = $folder->Filename . $this->_jsFilename;
			$jsFile->ParentID = $folder->ID;
			$this->JSFileID = $jsFile->write();
		}
		
		if (!$this->_written) {
			$this->_written = true;			
			$this->write();
		}
		
		
		file_put_contents($docRoot . $tplFile->Filename, $this->TemplateContent);
		file_put_contents($docRoot . $cssFile->Filename, $this->CSSContent);
		file_put_contents($docRoot . $jsFile->Filename, $this->JSContent);
		
		
		
	}
	
	private function _toFilename($str) {
		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
		$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
		$clean = strtolower(trim($clean, '-'));
		$clean = preg_replace("/[\/_|+ -]+/", '-', $clean);
		return $clean;
	}


}

class WidgetifyTemplateController extends ContentController {
	
	private static $allowed_actions = array(
		'getValidationTemplate',
		'getPreview',
		
	);
	
	/*
	 * 	Validate content and return log entries in HTML format to be populated in #widgetifyLog
	 */
	function getValidationTemplate($request) {
		$output = '';
		$content = $request->postVar('content');
		if ($content) {
			$valid = true;
			if (preg_match_all('/{widget-+(.*?)}/i', $content, $matches)) {
				// check for repeated and invalid identifiers
				$repeatedIdentifiers = array();
				$invalidIdentifiers = array();
				foreach($matches[1] as $match) {
					if ($this->_countArrayByValue($match, $matches[1]) > 1) {
						$repeatedIdentifiers[] = $match;
					}
					if (preg_match_all('/[^A-Za-z0-9.]/', $match, $matchesInvalid)) {
						$invalidIdentifiers[] = $match;
					}
				}
				if (count($repeatedIdentifiers) || count($invalidIdentifiers)) { 
					if (count($repeatedIdentifiers)) {
						$output .= '<p class="log-error">[ERROR] Found repeated identifiers: <strong>' . implode(', ', $repeatedIdentifiers) . '</strong> (repeated identifers will automatically get replaced with unique values)</p>';
						$valid = false; 
					}
					if (count($invalidIdentifiers)) {
						$output .= '<p class="log-error">[ERROR] Found invalid identifiers: <strong>' . implode(', ', $invalidIdentifiers) . '</strong> - (invalid characters will be stripped out)</p>';
						$valid = false; 
					}
				}										
			}
		}
		if ($valid) {
			$output = '<p class="log-ok">[OK] Successfull validated.';
		}
		echo $output;
	}

	/*
	 * 	Get preview HTML for the current template
	 */
	function getPreview($request) {
		$html = $request->postVar('html');
		$css = $request->postVar('css');
		$js = $request->postVar('js');
		$output = '';
		if (trim($css) != '') $output .= '<style type="text/css">' . WidgetifyPage::prefixCSS($css) . '</style>';
		if (trim($js) != '') $output .= '<script type="text/javascript">' . $js . '</script>';
		$output .= WidgetifyTemplate::cleanTemplate($html);
		return $output;
	}

	private function _countArrayByValue($value, $array) {
		$c = 0;
		foreach($array as $each) {
			if ($each == $value) {
				$c++;
			}
		}
		return $c;
	}
	
}


class IdentifierCallback {
		
	private $_keyArray;
	
	private $_position = 0;

	function __construct($keyArray) {
        $this->_keyArray = $keyArray;
	}

    public function callback($matches) {		
		$output = '{widget-' . $this->_keyArray[$this->_position] . '}';
		$this->_position++;
		return $output;
    }
}



