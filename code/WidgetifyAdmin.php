<?php
class WidgetifyAdmin extends ModelAdmin
{
    
    private static $url_segment = 'widgetify';
    
    private static $menu_title = 'Widgetify';
    
    private static $managed_models = array(
        'WidgetifyTemplate',
        'WidgetifyWidget'
    );
}
