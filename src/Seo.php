<?php

namespace Jellygnite\Seo;

use Jellygnite\Seo\Builders\FacebookMetaGenerator;
use Jellygnite\Seo\Builders\TwitterMetaGenerator;
use Jellygnite\Seo\Extensions\PageHealthExtension;
use Jellygnite\Seo\Extensions\PageSeoExtension;
use Jellygnite\Seo\Extensions\SiteConfigSettingsExtension;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Class Seo
 * @package Jellygnite\Seo
 */
class Seo
{
    use Injectable, Configurable;

    /**
     * Collates all content fields from {@link seoContentFields()} into a single string. Which makes it very important
     * that the seoContentFields array is in the correct order as to which they display.
     *
     * @param \Page|PageHealthExtension $owner
     *
     * @return string
     */
    public static function collateContentFields($owner)
    {
        $contentFields = $owner->seoContentFields();

        $content = [];
        foreach ($contentFields as $field) {
            $content[] = $owner->relObject($field)->forTemplate();
        }

        $content = implode(' ', $content);

        return strtolower(strip_tags($content)?? '');
    }

    /**
     * Creates article:published_time and article:modified_time tags
     *
     * @param \Page|PageSeoExtension|Object $owner
     *
     * @return array
     */
    public static function getArticleTags($owner)
    {
        /** @var DBDatetime $published */
        $published = $owner->dbObject('Created');  

        /** @var DBDatetime $modified */
        $modified = $owner->dbObject('LastEdited');

        return [
            sprintf('<meta property="article:published_time" content="%s" />', $published->Rfc3339()),
            sprintf('<meta property="article:modified_time" content="%s" />', $modified->Rfc3339()),
        ];
    }

    /**
     * Creates the canonical url link
     *
     * @param \Page|PageSeoExtension|Object $owner
     *
     * @return array
     */
    public static function getCanonicalUrlLink($owner)
    {
		$canonical_url = $owner->CanonicalURL ?: $owner->AbsoluteLink();
        return [
            sprintf('<link rel="canonical" href="%s"/>', $canonical_url)
        ];
    }

    /**
     * Creates the Facebook/OpenGraph meta tags
     *
     * @param \Page|PageSeoExtension|Object $owner
     *
     * @return array
     */
    public static function getFacebookMetaTags($owner)
    {
        $imageWidth  = $owner->FacebookPageImage()->exists() ? $owner->FacebookPageImage()->getWidth() : null;
        $imageHeight = $owner->FacebookPageImage()->exists() ? $owner->FacebookPageImage()->getHeight() : null;

        $generator = FacebookMetaGenerator::create();
        $generator->setTitle($owner->FacebookPageTitle ?: self::getMyMetaTitle($owner));
        $generator->setDescription($owner->FacebookPageDescription ?: $owner->MetaDescription ?: $owner->Content);
        $generator->setImageUrl(($owner->FacebookPageImage()->exists())
            ? $owner->FacebookPageImage()->AbsoluteLink()
            : null);
        $generator->setImageDimensions($imageWidth, $imageHeight);
        $generator->setType($owner->FacebookPageType ?: 'website');
        $generator->setUrl($owner->AbsoluteLink());

        return $generator->process();
    }

    /**
     * @return array
     */
    public static function getGoogleAnalytics()
    {
        /** @var SiteConfig|SiteConfigSettingsExtension $sc */
        $sc = SiteConfig::current_site_config();

        return ($sc->GoogleAnalytics) ? [$sc->GoogleAnalytics] : [];
    }

    /**
     * Get all PixelFields
     * @return array
     */
    public static function getPixels()
    {
        $output     = [];
        $siteConfig = SiteConfig::current_site_config();
        $ours       = array_keys(SiteConfigSettingsExtension::config()->get('db'));
        $db         = SiteConfig::config()->get('db');
        foreach ($db as $k => $v) {
            if (strstr($k, 'Pixel') && in_array($k, $ours)) {
                if (is_null($siteConfig->{$k})) {
                    continue;
                }
                $output[] = $siteConfig->{$k};
            }
        }

        return $output;
    }

	public static function getMyMetaTitle($owner) {
		$metaTitle = $owner->MetaTitle ? $owner->MetaTitle : $owner->defaultMetaTitle();
		return $metaTitle;
	}

    /**
     * Creates the twitter meta tags
     *
     * @param \Page|PageSeoExtension|Object $owner
     *
     * @return array
     */
    public static function getTwitterMetaTags($owner)
    {
		
        $generator = TwitterMetaGenerator::create();
        $generator->setTitle($owner->TwitterPageTitle ?: self::getMyMetaTitle($owner));
        $generator->setDescription($owner->TwitterPageDescription ?: $owner->MetaDescription ?: $owner->Content);
        $generator->setImageUrl(($owner->TwitterPageImage()->exists())
            ? $owner->TwitterPageImage()->AbsoluteLink()
            : null);
        if (PageSeoExtension::config()->get('enable_creator_tag') &&
            $owner->Creator()->exists() &&
            $owner->Creator()->TwitterAccountName) {
            $generator->setCreator($owner->Creator()->TwitterAccountName);
        }

        return $generator->process();
    }
}
