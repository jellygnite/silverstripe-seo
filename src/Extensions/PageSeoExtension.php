<?php

namespace Jellygnite\Seo\Extensions;

use Jellygnite\Seo\Builders\FacebookMetaGenerator;
use Jellygnite\Seo\Seo;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\VersionedAdmin\Controllers\HistoryViewerController;
use SilverStripe\Dev\Debug;

/**
 * Class PageSeoExtension
 * @package Jellygnite\Seo\Extensions
 *
 * @property string FacebookPageType
 * @property string FacebookPageTitle
 * @property string FacebookPageDescription
 * @property int    FacebookPageImageID
 * @property int    CreatorID
 *
 * @method Image FacebookPageImage()
 * @method Member|MemberExtension Creator()
 */
class PageSeoExtension extends DataExtension
{
    use Configurable;

    private static $cascade_deletes = [
        'FacebookPageImage',
        'TwitterPageImage'
    ];

    private static $db = [
		'MetaTitle' 			  => 'Varchar(255)',
        'FacebookPageType'        => 'Varchar(50)',
        'FacebookPageTitle'       => 'Varchar(255)',
        'FacebookPageDescription' => 'Text',
        'TwitterPageTitle'        => 'Varchar(255)',
        'TwitterPageDescription'  => 'Text',		
		'CanonicalURL'			=> 'Varchar(255)'
    ];

    /**
     * The "creator tag" is the meta tag for Twitter to specify the creators Twitter account. Disabled by default
     *
     * @config
     * @var bool
     */
    private static $enable_creator_tag = false;
	
	 /**
     * The "content_holder" is the div or html tag that holds the content for the page. Default is body
     *
     * @config
     * @var string
     */
	private static $content_holder = "body";

    private static $has_one = [
        'FacebookPageImage' => Image::class,
        'TwitterPageImage'  => Image::class,
        'Creator'           => Member::class
    ];

    private static $owns = [
        'FacebookPageImage',
        'TwitterPageImage'
    ];

    /**
     * Extension point for SiteTree to merge all tags with the standard meta tags
     *
     * @param $tags
     */
    public function MetaTags(&$tags)
    {
        $tags = explode(PHP_EOL, $tags);
        $tags = array_merge(
            $tags,
            Seo::getCanonicalUrlLink($this->getOwner()),
            Seo::getFacebookMetaTags($this->getOwner()),
            Seo::getTwitterMetaTags($this->getOwner()),
            Seo::getArticleTags($this->getOwner()),
            Seo::getGoogleAnalytics(),
            Seo::getPixels()
        );

        $tags = implode(PHP_EOL, $tags);
    }




	public function defaultMetaTitle(){
        $siteConfig = SiteConfig::current_site_config();
		$defaultMetaTitle = $this->getOwner()->Title . ' | ' . $siteConfig->Title ;
		
		return $defaultMetaTitle;
	}


    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->getOwner()->ID && !$this->getOwner()->Creator()->exists() && $member = Security::getCurrentUser()) {
            $this->getOwner()->CreatorID = $member->ID;
        }
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields) 
    {
        parent::updateCMSFields($fields);
		
        $suppressMessaging = false;
        if (Controller::curr() instanceof HistoryViewerController) { // avoid cluttering the history comparison UI
            $suppressMessaging = true;
        }
		
        $tafMetaDescription = $fields->dataFieldByName('MetaDescription');
        $tafExtraMeta = $fields->dataFieldByName('ExtraMeta');
		// some pages like RedirectorPage don't use SEO
		if($tafMetaDescription){
			$fields->removeByName('MetaDescription');
			$fields->removeByName('ExtraMeta');
			
			$tabset = TabSet::create( 
				"SEOTabSet", 
				Tab::create( 'Meta',
					TextField::create("MetaTitle", "Meta Title")
						->setAttribute('placeholder', $this->defaultMetaTitle())
						->setRightTitle($suppressMessaging ? '' : 'If blank inherits "Page Title | Site Title"')
						->setTargetLength(45, 25, 70),
					$tafMetaDescription->setTitle('Meta Description'),
					$tafExtraMeta,
					TextField::create("CanonicalURL", "Canonical URL")
						->setRightTitle($suppressMessaging ? '' : 'A canonical URL is the URL of the page that search engines think is most representative from a set of duplicate pages on your site.')
				), 
				Tab::create('Health'), 
				Tab::create('Facebook',
					DropdownField::create('FacebookPageType', 'Type', FacebookMetaGenerator::getValidTypes()),
					TextField::create('FacebookPageTitle', 'Facebook Title')
						->setAttribute('placeholder', $this->defaultMetaTitle())
						->setRightTitle($suppressMessaging ? '' : 'If blank, inherits Meta Ttitle')
						->setTargetLength(45, 25, 70),
					UploadField::create('FacebookPageImage', 'Facebook Image')
						->setRightTitle($suppressMessaging
							? ''
							: 'Facebook recommends images to be 1200 x 630 pixels. ' .
							'If no image is provided, Facebook will choose the first image that appears on the page, ' .
							'which usually has bad results')
						->setFolderName('images/seo'),
					TextareaField::create('FacebookPageDescription', 'Facebook Description')
						->setAttribute('placeholder', $this->getOwner()->MetaDescription ?:
						 $this->getOwner()->dbObject('Content')->LimitCharacters(297))
						->setRightTitle($suppressMessaging
							? ''
							: 'If blank, inherits meta description if it exists ' .
							'or gets the first 297 characters from content')
						->setTargetLength(200, 160, 320)
					), 
				Tab::create('Twitter',
					TextField::create('TwitterPageTitle', 'Twitter Title')
						->setAttribute('placeholder', $this->defaultMetaTitle())
						->setRightTitle($suppressMessaging ? '' : 'If blank, inherits Meta Ttitle')
						->setTargetLength(45, 25, 70),
					UploadField::create('TwitterPageImage', 'Twitter Image')
						->setRightTitle($suppressMessaging ? '' : 'Must be at least 280x150 pixels')
						->setFolderName('images/seo'),
					TextareaField::create('TwitterPageDescription', 'Twitter Description')
						->setAttribute('placeholder', $this->getOwner()->MetaDescription ?:
							$this->getOwner()->dbObject('Content')->LimitCharacters(297))
						->setRightTitle($suppressMessaging
							? ''
							: 'If blank, inherits meta description if it exists ' .
								'or gets the first 297 characters from content')
						->setTargetLength(200, 160, 320)
				) 
			);
		
			$fields->addFieldsToTab('Root.SEO', [
				$tabset
			], 'Metadata');
		}
    }
}
