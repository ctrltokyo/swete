<?php
/**
 * SWeTE Server: Simple Website Translation Engine
 * Copyright (C) 2012  Web Lite Translation Corp.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once 'inc/BackgroundProcess.php';
require_once 'inc/SweteSite.class.php';
require_once 'inc/SweteWebpage.class.php';
class BackgroundProcess_UpdateEffectiveActive extends BackgroundProcess {
	
	public $rootPageId;
	public $lang;
	public $property;
	public $inheritVal = -1;
	
	public function run(){
	
		$page = SweteWebpage::loadById($this->rootPageId, $this->lang);
		if ( !$page ){
			throw new Exception("Failed to load page :".$this->rootPageId." language ".$this->lang);
		}
		$page->getInheritableProperty($this->property, true, $this->inheritVal);
		$site = $page->getSite();
		
		SweteSite::calculateEffectivePropertyToTree($this->property, $page, $page->getInheritableProperty($this->property, false, $this->inheritVal));
		
	
	}
	
}