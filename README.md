Silverstripe Widgetify Module
======================

A simple module that gives the ability to customise page layouts by adding widgets to them from a CMS level without having to create multiple page types and templates.


Maintainer
-------------

New Zealand Automobile Association

*Developer: Leandro Palmieri*

*Upgraded for SS 3.1.2 by Jean-Fabien Barrois.*


Requirements
---------------

Silverstripe 3.1.2+


Installation Instructions
-------------------------

1. Place the files in a directory called "widgetify" in the root of your Silverstripe installation
2. Visit yoursite.com/dev/build?flush=all


Usage
-----

From the CMS menu (left-hand side) click on "Widgetify" to setup templates and widgets

Create one or more templates (the following example shows a 3 columns template)

**HTML tab:**

*Note: you must add tags {widget-UniqueIdentifier} where you want widgets to be placed.*

```
<div class="col">
	{widget-1}
</div>
<div class="col">
	{widget-2}
</div>
<div class="col">
	{widget-3}
</div>
```

**CSS tab (not required):**

```
col {
	float: left;
	width: 33.333%;
}
```

**Javascript tab (not required):**

```
// any javascript code required for this template to function
```

*Note: you'll probably want to have your CSS and Javascript code included directly to your own files and for that reason you will have the ability to choose whether or not to include these in the front-end when managing the page in the CMS, otherwise these are simply for CMS preview purposes.*

*Tip: click "Refresh and validate" before you save the template. This will check whether it is valid.*

**Switch to the "Widgetify Widgets" tab (top right corner in the CMS) and create your widgets. Each widget can be a static content or an include file that you can select from your site's Includes folder.**

**Place your includes in this folder:**
```
themes/yourtheme/templates/Includes
```

*Note: it will pick up all templates within sub-folders as well.*

Once you have setup your templates and widgets, you are ready to create pages.

**Create a page of type "WidgetifyPage" and select a template for this page from the dropdown field.**

You should now see the Widgetify layout editor and all you have to do is select your widgets from the dropdowns.

You should also have the "Dynamic content" as the first option in each dropdown. This allows you to enter a custom content for that particular position.

