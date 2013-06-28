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
if ( @$_SERVER['REDIRECT_ENCODE_SCRIPTS'] ){
    define('SWETE_ENCODE_SCRIPTS', 1);
}
if (!function_exists('apache_request_headers')) { 
    eval(' 
        function apache_request_headers() { 
            foreach($_SERVER as $key=>$value) { 
                if (substr($key,0,5)=="HTTP_") { 
                    $key=str_replace(" ","-",ucwords(strtolower(str_replace("_"," ",substr($key,5))))); 
                    $out[$key]=$value; 
                } 
            } 
            return $out; 
        } 
    '); 
} 
require_once 'inc/LiveCache.php';
if ( @$_GET['-action'] == 'swete_handle_request' ){
	
	define('XATAFACE_NO_SESSION',1);
	define('XATAFACE_DISABLE_AUTH',1);
	error_log('[SWeTE Profiler]['.getmypid().'] start time: '.microtime());
	$liveCache = LiveCache::getCurrentPage();
	if ( intval(@$_SERVER['REDIRECT_NOSERVERCACHE']) === 1){
	    $liveCache->noServerCache = true;
	}
	if ( strtolower(@$_SERVER['REQUEST_METHOD']) == 'get'){
		try {
			$liveCache->handleRequest();
		} catch (Exception $ex){
			//  The first time a resource is requested, it will likely 
			// throw an exception because we don't yet know the unproxified
			// url
			error_log("LiveCache Warning: ".$ex->getMessage());
		}
	}
	$ORIG_POST = $_POST;
	$ORIG_GET = $_GET;
	$ORIG_REQUEST = $_REQUEST;
	$_POST = array();
	$_GET = array('-action' => 'swete_handle_request');
	$_REQUEST = $_GET;
}
ini_set('memory_limit', '256M');
require_once 'include/functions.inc.php';
require_once 'xataface/public-api.php';
$conf = array();
if ( isset($liveCache) ){
	if ( is_resource($liveCache->db) ){
		$conf['db'] = $liveCache->db;
	}
}
df_init(__FILE__, 'xataface', $conf)->display();