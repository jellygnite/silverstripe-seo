<?php

namespace Jellygnite\Seo\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\Debug;
/**
 * 
 * adds the content of the entire page
 * 
 */

class PageControllerExtension extends Extension {
	
	public function MetaTitle(){
		
		if($this->owner->dbObject('MetaTitle') && $this->owner->dbObject('MetaTitle')->getValue()){
			return $this->owner->dbObject('MetaTitle')->getValue();
		}
		return ( $this->owner->hasMethod('defaultMetaTitle') && $this->owner->defaultMetaTitle() ) ? $this->owner->defaultMetaTitle() : $this->owner->Title;
    }


}