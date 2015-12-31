<?php

class WidgetifyWidget extends DataObject
{
    
    private static $db = array(
        'Title' => 'Varchar(100)',
        'Type' => 'Enum("Content,Include","Content")',
        'Content' => 'HTMLText',
        'IncludeFile' => 'Varchar(500)'
    );
    
    private static $searchable_fields = array(
        'Title',
        'Type',
        'IncludeFile'
    );
    
    private static $summary_fields = array(
        'Title',
        'Type',
        'IncludeFile',
        'TotalPages' => 'TotalPages'
    );
    
    private static $default_sort = '"WidgetifyWidget"."Title" ASC';
    
    public function canView($member = false)
    {
        return true;
    }

    public function canEdit($member = false)
    {
        return Permission::check("CMS_ACCESS_LeftAndMain");
    }

    public function canDelete($member = null)
    {
        return Permission::check("CMS_ACCESS_LeftAndMain");
    }

    public function canCreate($member = null)
    {
        return Permission::check("CMS_ACCESS_LeftAndMain");
    }
    
    public function getCMSFields()
    {
        Requirements::css('widgetify/css/widgetify_cms.css');
        Requirements::javascript('framework/thirdparty/jquery/jquery.js');
        Requirements::javascript('widgetify/scripts/widgetify_widget.js');
        
        $fields = FieldList::create();
        $fields->push(TextField::create('Title', 'Title', false, 100));
        $fields->push(DropdownField::create('Type', 'Content Source', singleton('WidgetifyWidget')->dbObject('Type')->enumValues()));

        $fields->push(HTMLEditorField::create('Content', 'Content'));
        $fields->push(DropdownField::create('IncludeFile', 'Include file', $this->_getTemplateFilesInThemes())->setEmptyString('- Select -'));
        
        if ($this->ID) {
            $fields->push(HeaderField::create('WidgetifyRelatedTitle', 'Pages using this widget', 4));
            $fields->push(LiteralField::create('AppliedTo', $this->_getTablePages()));
        }
        return $fields;
    }
    
    
    private function _getTemplateFilesInThemes()
    {
        $output = array();

        $folderParts = explode('/', getcwd());
        array_pop($folderParts);
        $rootThemes = implode('/', $folderParts) . '/themes';
        
        $ritit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootThemes), RecursiveIteratorIterator::CHILD_FIRST);
        $r = array();
        foreach ($ritit as $splFileInfo) {
            if (strstr($splFileInfo->getFilename(), '.ss')) {
                $path = array();
                for ($depth = $ritit->getDepth() - 1; $depth >= 0; $depth--) {
                    $path[] = $ritit->getSubIterator($depth)->current()->getFilename();
                }
                $fullPath = implode('/', array_reverse($path)) . '/' . $splFileInfo->getFilename();
                $output[$fullPath] = $fullPath;
            }
        }
        asort($output);
        return $output;
    }
    
    public function incFile()
    {
        if ($this->Type == 'Include' && $this->IncludeFile) {
            $parts = explode('/', $this->IncludeFile);
            return str_replace('.ss', '', $parts[count($parts)-1]);
        }
    }
    
    private function _getTablePages()
    {
        $pages = $this->WidgetifyPages();
        if ($pages->count()) {
            $tablePages = '<table class="widgetify-related">';
            $tablePages .= '<tr class="table-header"><td>Page Title</td><td>Page Link</td><td>CMS Link</td></tr>';
            foreach ($pages as $page) {
                $absLink = Controller::join_links(Director::protocolAndHost(), $page->Link());
                $absCMSLink = Controller::join_links(Director::protocolAndHost(), '/admin/pages/edit/show/', $page->ID);
                $tablePages .= '<tr><td>' . $page->MenuTitle . '</td><td><a href="' . $absLink . '" target="_blank">' . $absLink . '</a></td><td><a href="' . $absCMSLink . '" target="_blank">' . $absCMSLink . '</a></td></tr>';
            }
            $tablePages .= '</table>';
        } else {
            $tablePages = '<p>There are currently no pages using this widget.</p>';
        }
        return $tablePages;
    }
    
    public function WidgetifyPages()
    {
        $output = new ArrayList();
        $pages = DataList::create('WidgetifyPage');
        if ($pages && $pages->count()) {
            foreach ($pages as $page) {
                $widgets = $page->WidgetifyWidgets();
                if ($widgets && $widgets->count()) {
                    foreach ($widgets as $widget) {
                        if ($widget->ID == $this->ID) {
                            $output->push($page);
                            break;
                        }
                    }
                }
            }
        }
        return $output;
    }
    
    public function TotalPages()
    {
        return count($this->WidgetifyPages());
    }
}
