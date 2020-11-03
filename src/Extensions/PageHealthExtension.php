<?php

namespace Jellygnite\Seo\Extensions;

use KubAT\PhpSimple\HtmlDomParser;
use Jellygnite\Seo\Forms\GoogleSearchPreview;
use Jellygnite\Seo\Forms\HealthAnalysisField;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ToggleCompositeField;

use SilverStripe\ORM\DataExtension;
use SilverStripe\VersionedAdmin\Controllers\HistoryViewerController;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;

/**
 * Class PageHealthExtension
 * @package Jellygnite\Seo\Extensions
 *
 * @property string FocusKeyword
 */
class PageHealthExtension extends DataExtension
{
    const EMPTY_HTML = '<p></p>';

    /**
     * @var string|null
     */
    protected $renderedHtml;

    private static $db = [
        'FocusKeyword' => 'Varchar(50)'
    ];

    /**
     * @return \Page|static
     */
    public function getOwner()
    {
        /** @var \Page $owner */
        $owner = parent::getOwner();
        return $owner;
    }

    /**
     * Gets the rendered html (current version, either draft or live)
     *
     * @return string|null
     */
    public function getRenderedHtml()
    {
        if (!$this->renderedHtml) {
            $controllerName = $this->owner->getControllerName();
            if ('SilverStripe\UserForms\Control\UserDefinedFormController' == $controllerName) {
                // remove the Form since it crashes
                $this->owner->Form = false;
            }
			
			$current_themes = SSViewer::get_themes();  // get current CMS theme						
            Requirements::clear(); // we only want the HTML, not any of the js or css
			SSViewer::set_themes(SSViewer::config()->uninherited('themes'));
			$this->renderedHtml = $controllerName::singleton()->render($this->owner);
            Requirements::restore(); // put the js/css requirements back when we're done
			
			SSViewer::set_themes($current_themes);  // reset current CMS theme when we're done
        }

        if ($this->renderedHtml === false) {
            $this->renderedHtml = self::EMPTY_HTML;
        }

        return $this->renderedHtml;
    }

    /**
     * Gets the DOM parser for the rendered html
     *
     * @return \simple_html_dom\simple_html_dom
     */
    public function getRenderedHtmlDomParser()
    {	
        $domParser = HtmlDomParser::str_get_html($this->getRenderedHtml());

		return $domParser;
    }

    /**
     * Override this if you have more than just `Content` (or don't have `Content` at all). Fields should
     * be in the order for which they appear for a frontend user
     *
     * @return array
     */
    public function seoContentFields()
    {
        return [
            'Content'
        ];
    }

    /** 
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);
        if (Controller::curr() instanceof HistoryViewerController) { // avoid breaking the history comparison UI
            return;
        }
        if ($this->owner instanceof \SilverStripe\ErrorPage\ErrorPage) {
            return;
        }

		
		$fields->addFieldsToTab('Root.SEO.SEOTabSet.Health', [

                GoogleSearchPreview::create(
                    'GoogleSearchPreview',
                    'Search Preview',
                    $this->getOwner(),
                    $this->getRenderedHtmlDomParser()
                ),
                TextField::create('FocusKeyword', 'Set focus keyword'),
                HealthAnalysisField::create('ContentAnalysis', 'Content Analysis', $this->getOwner()),

        ], 'Metadata');
    }
}
