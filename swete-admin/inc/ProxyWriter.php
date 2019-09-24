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
//require_once 'lib/simple_html_dom.php';
require_once 'lib/http_build_url.php';

/**
 * @brief A class that converts transforms HTML from a source webpage into HTML for a
 * target proxy.  It transforms URLs to work with the proxy, and it translates text
 * using a specified translation memory.
 *
 * <h3>Example Usage:</h3>
 *
 * @code
 * $writer = new ProxyWriter();
 * $writer->setSrcUrl('http://xataface.com');
 * $writer->setProxyUrl('http://xataface.com/fr');
 * $writer->setTranslationMemory($myTranslationMemory);
 * $html = $this->translateHtml($html);
 * $html = $this->proxifyHtml($html);
 *
 * $css = $this->proxifyCss($css);
 * @endcode
 */
class ProxyWriter
{

    public static $default_json_keys = array('text' => array(), 'html' => array('cell'));

    private $_scriptStack = array();

    public $translationParserVersion = null;

    public $generifyResourceProtocols = false;

    public $useHtml5Parser = false;

    public $useHtml5Serializer = false;

    public $snapshotsPath;

    public $snapshotPage;
    public $snapshotId = -1;
    

    /**
     * @brief The locale for parsing dates.  E.g. en_CA
     * @type string
     */
    public $sourceDateLocale = null;

    /**
     * @brief The locale for writing dates.  E.g. fr_CA
     * @type string
     */
    public $targetDateLocale = null;

    /** Whether we should try to unproxify resource paths so they are loaded
    directly from the source server.
     */
    public $unproxifyResourcePaths = true;

    /**
     * @brief The source URL to use as URL conversions.
     * @type string
     */
    private $_srcUrl;

    private $_srcLang;
    private $_proxyLang;

    /**
     * @brief The proxy URL to use for URL conversions.
     * @type string
     */
    private $_proxyUrl;

    /**
     * @brief Cached storage for the parsed source URL (using parse_url)
     */
    private $_srcParts;

    /**
     * @brief Cached storage for the parsed proxy URL (using parse_url).
     * @type array
     */
    private $_proxyParts;

    /**
     * @brief Map of aliases for path components.  This maps path components for
     * the source URL to path components for the proxy URL.
     * @type array
     */
    private $aliases = array();

    /**
     * @brief Reverse map of aliases mapping path components in the proxy to
     * corresponding path components in the source.
     * @type array
     */
    private $reverseAliases = array();

    /**
     * @brief Reference to the translation memory that should be used for
     * the translateHtml() method.
     * @type XFTranslationDictionary
     */
    private $translationMemory = null;

    /**
     * @brief The minimum status of strings in the translation memory to use
     * in applying translations.
     * @type int
     */
    private $minStatus = 3;

    /**
     * @brief The maximum status of strings in the translation memory to use in
     * applying translations.
     */
    private $maxStatus = 5;

    /**
     * @brief Container for the last extracted strings that was produced
     * by the translateHtml function.
     */
    public $lastStrings = null;
    public $lastTranslations = null;
    private $lastTranslateHtmlInput = null;

    public function __construct()
    {
        if (defined('SWETE_UNPROXIFY_RESOURCE_PATHS') and !SWETE_UNPROXIFY_RESOURCE_PATHS) {
            $this->unproxifyResourcePaths = false;
        }
    }

    /**
     * @brief Sets the source language of the proxy.
     * @param string $lang The 2-digit language code.
     * @returns void
     *
     * @see getSourceLanguage()
     * @see setProxyLanguage()
     */
    public function setSourceLanguage($lang)
    {
        $this->_srcLang = $lang;
    }

    /**
     * @brief Sets the target language of the proxy.
     * @param string $lang The 2-digit language code.
     * @returns void
     *
     * @see getProxyLanguage()
     * @see setSourceLanguage()
     */
    public function setProxyLanguage($lang)
    {
        $this->_proxyLang = $lang;
    }

    /**
     * @brief Returns the 2-digit language code of the source language of the proxy.
     * @returns string 2-digit language code
     *
     * @see setSourceLanguage()
     * @see getProxyLanguage()
     *
     */
    public function getSourceLanguage()
    {return $this->_srcLang;}

    /**
     * @brief Returns the 2-digit language code of the target language of the proxy.
     * @returns string 2-digit language code.
     *
     * @see getSourceLanguage()
     * @see setProxyLanguage()
     */
    public function getProxyLanguage()
    {return $this->_proxyLang;}

    /**
     * @brief Sets the translation memory to use when performing translations.
     * @param XFTranslationMemory $mem The translation memory to use.
     * @returns void
     *
     * @see translateHtml()
     * @see getTranslationMemory()
     *
     */
    public function setTranslationMemory(XFTranslationDictionary $mem)
    {
        $this->translationMemory = $mem;

    }

    /**
     * @brief Gets the translation memory that is to be used for performing
     * translations.
     * @return XFTranslationDictionary The translation memory object.
     * @see translateHtml()
     * @see setTranslationMemory()
     */
    public function getTranslationMemory()
    {
        return $this->translationMemory;
    }

    /**
     * @brief Sets the minimum status of strings that can be used in applying
     * translations.
     *
     * @param int $s The status id.  This should be one of:
     *        - XFTranslationMemory::TRANSLATION_REJECTED
     *        - XFTranslationMemory::TRANSLATION_SUBMITTED
     *        - XFTranslationMemory::TRANSLATION_APPROVED
     *
     * @see translateHtml()
     */
    public function setMinTranslationStatus($s)
    {
        $this->minStatus = $s;
    }

    /**
     * @brief Sets the minimum status of strings that can be used in applying
     * translations.
     *
     * @param int $s The status id.  This should be one of:
     *        - XFTranslationMemory::TRANSLATION_REJECTED
     *        - XFTranslationMemory::TRANSLATION_SUBMITTED
     *        - XFTranslationMemory::TRANSLATION_APPROVED
     * @see translateHtml()
     * @see getMaxTranslationStatus()
     * @see getMinTranslationStatus()
     * @see setMinTranslationStatus()
     */
    public function setMaxTranslationStatus($s)
    {
        $this->maxStatus = $s;
    }

    /**
     * @brief Gets the maximum status of strings that can be used in applying
     * translations.
     *
     * @return int The status is.  This should be one of:
     *        - XFTranslationMemory::TRANSLATION_REJECTED
     *        - XFTranslationMemory::TRANSLATION_SUBMITTED
     *        - XFTranslationMemory::TRANSLATION_APPROVED
     *
     * @see setMaxTranslationStatus()
     * @see setMinTranslationStatus()
     * @see getMinTranslationStatus()
     */
    public function getMaxTranslationStatus()
    {
        return $this->maxStatus;
    }

    /**
     * @brief Gets the minimum status of strings that can be used in applying
     * translations.
     *
     * @return int The status is.  This should be one of:
     *        - XFTranslationMemory::TRANSLATION_REJECTED
     *        - XFTranslationMemory::TRANSLATION_SUBMITTED
     *        - XFTranslationMemory::TRANSLATION_APPROVED
     *
     * @see setMaxTranslationStatus()
     * @see setMinTranslationStatus()
     * @see getMaxTranslationStatus()
     */
    public function getMinTranslationStatus()
    {
        return $this->minStatus;
    }

    /**
     * @brief Sets the base URL of the proxy (i.e. the target URL of conversions).
     * @param string $url The base URL of the proxy.
     * @see proxyifyUrl()
     * @see unproxifyUrl()
     */
    public function setProxyUrl($url)
    {
        $this->_proxyUrl = $url;
        $this->_proxyParts = parse_url($url);
    }

    /**
     * @brief Sets the source URL of the proxy (i.e. the source URL of conversions).
     * @param string $url The base URL of the proxy.
     *
     * @see setProxyUrl()
     * @see proxifyUrl()
     * @see unproxifyUrl()
     */
    public function setSrcUrl($url)
    {
        $this->_srcUrl = $url;
        $this->_srcParts = parse_url($url);
    }

    /**
     * @brief Adds an alias for a path component.  Aliases are used for using different
     * URLs in the proxy than in the source site.  E.g. We might translate all path components
     * "find" to "trouve".  E.g. /overview/find/about would be translated then to /overview/trouve/about.
     *
     * @param string $src The source path component.
     * @param string $val The target path component (i.e. the alias).
     * @see removeAlias()
     * @see proxifyPath()
     * @see unproxifyPath()
     */
    public function addAlias($src, $val)
    {
        $this->aliases[strtolower($src)] = $val;
        $this->reverseAliases[strtolower($val)] = $src;
    }

    /**
     * @brief Removes an alias for a path component.
     * @param string $src The source path component.
     * @param string $val The target path component (i.e. alias).
     * @see addAlias()
     * @see proxifyPath()
     * @see unproxifyPath()
     */
    public function removeAlias($src, $val)
    {
        unset($this->aliases[strtolower($src)]);
        unset($this->reverseAliases[strtolower($val)]);
    }

    /**
     * @brief Strips the base path from a URL.  This is useful for obtaining
     * only the portion of a URL that comes after a base URL.  This will detect
     * whether the URL is complete, absolute, or relative and work accordingly.
     *
     * @param string $url The URL that is being stripped
     * @param string $base (Optional) The base path that is being stripped
     *     from the URL.  If this is omitted, it will use the path component
     *  of the Proxy URL.
     * @return The portion of the path in $url that comes after $base.
     * @throws Exception if the URL is not under the specified base.
     */
    public function stripBasePath($url, $base = null)
    {
        if (!isset($base)) {
            $base = $this->_proxyParts['path'];
        }

        if (preg_match('#^[a-zA-Z]{3,10}://#', $url)) {

            $parts = parse_url($url);
            $path = $parts['path'];
            if (!$path) {
                $path = '/';
            }

            if (@$parts['query']) {
                $path .= '?' . $parts['query'];
            }

            if (@$parts['fragment']) {
                $path .= '#' . $parts['fragment'];
            }

        } else {
            $path = $url;
            if (!$path) {
                $path = '/';
            }

            if ($path{0} != '/') {
                $path = '/' . $path;
            }

        }
        if (preg_match('#^[a-zA-Z]{3,10}://#', $base)) {
            $bparts = parse_url($base);
            $bpath = $bparts['path'];
            if (!$bpath) {
                $bpath = '/';
            }

            if (@$bparts['query']) {
                $bpath .= '?' . $bparts['query'];
            }

            if (@$bparts['fragment']) {
                $bpath .= '#' . $parts['fragment'];
            }

            $base = $bpath;

        }
        $basePath = $base;
        if (!$basePath) {
            $basePath = '/';
        }

        if ($basePath{strlen($basePath) - 1} != '/') {
            $basePath .= '/';
        }

        // Basepath should end with a slash.

        // Let's deal with the case where the path is just the path to the basepath but
        // doesn't have a trailing slash.... we'll complete it in this case
        // to make matching easier.
        if ($path . '/' == $basePath) {
            $path .= '/';
        }

        if (strpos($path, $basePath) !== 0) {
            throw new Exception("$url Does not have the correct basepath (found $path but required $basePath).");
        }

        $path = substr($path, strlen($basePath));
        return $path;
    }

    /**
     * @brief Applies alias map to convert a path in proxy space into source space.
     *        This does not translate the path to the source base URL - it only applies
     *         aliases.  To translate the path to the source base URL, see unproxifyUrl()
     *
     *
     * @param string $path The path to convert.
     * @return string The $path as it should appear in the source site.  All path components
     *         have been replaced with their originals based on the reverse alias map.
     * @see proxifyPath()
     * @see addAlias()
     * @see removeAlias()
     * @see proxifyUrl()
     * @see unproxifyUrl()
     */
    public function unproxifyPath($path)
    {
        if ($this->reverseAliases) {
            $pp = explode('/', $path);
            foreach ($pp as $ppK => $ppV) {
                $ppVl = strtolower($ppV);
                if (isset($this->reverseAliases[$ppVl])) {
                    $pp[$ppK] = $this->reverseAliases[$ppVl];
                }
            }
            $path = implode('/', $pp);
        }
        return $path;

    }

    /**
     * @brief Converts a path from the source site to the proxy site by applying
     * aliases to each path component.  This does not translate the path to the proxy
     * base URL - it only applies aliases to the path components.  To translate
     * the path to the proxy base URl see proxifyUrl()
     *
     * @param string $path The path to translate.
     * @return string The path converted path.
     * @see unproxifyPath()
     * @see addAlias()
     * @see removeAlias()
     * @see proxifyUrl()
     * @see unproxifyUrl()
     */
    public function proxifyPath($path)
    {
        if ($this->aliases) {
            $pp = explode('/', $path);
            foreach ($pp as $ppK => $ppV) {
                $ppVl = strtolower($ppV);
                if (isset($this->aliases[$ppVl])) {
                    $pp[$ppK] = $this->aliases[$ppVl];
                }
            }
            $path = implode('/', $pp);
        }
        return $path;

    }

    /**
     * @brief Converts a URL in the proxy site to a URL in the source site.  This
     * both converts the base URL and applies aliases to the path components.  This will
     * handle all forms of URLs, absolute and relative and convert them properly.
     *
     * @param string $url The URL that needs to be converted from proxy space.
     * @return string The URL in source site space.
     *
     * @see unproxifyPath()
     * @see changeBase()
     */
    public function unproxifyUrl($url)
    {
        return self::changeBase($this->_proxyParts, $this->_srcParts, $url, $this->reverseAliases);
    }

    /**
     * @brief Changes a URL from one base to another.  It will also translate paths
     * in the remaining path based on a specified translation dictionary.  This function
     * is used by the unproxifyUrl() and proxifyUrl() methods to perform the heavy lifting.
     *
     * @param string $oldBase Base URL to convert from.
     * @param string $newBase The Base URL to convert to.
     * @param string $url The URL to convert.
     * @param array $pathDict A dictionary mapping path components from source to target.
     * @return string The translated URL.
     *
     * @see proxifyUrl()
     * @see unproxifyUrl()
     */
    public static function changeBase($oldBase, $newBase, $url, $pathDict = array())
    {
        if (!is_array($oldBase)) {
            $oldBase = self::parse_url($oldBase);
        }

        if (!is_array($newBase)) {
            $newBase = self::parse_url($newBase);
        }

        if ($oldBase['path'] and $oldBase['path']{strlen($oldBase['path']) - 1} != '/') {
            $oldBase['path'] .= '/';
        }

        if ($newBase['path'] and $newBase['path']{strlen($newBase['path']) - 1} != '/') {
            $newBase['path'] .= '/';
        }

        if (!$oldBase['path']) {
            $oldBase['path'] = '/';
        }

        if (!$newBase['path']) {
            $newBase['path'] = '/';
        }

        if (!$url) {
            return $url;
        }

        if ($url{0} == '/' and (@$url{1} != '/' or @$url{2} == '/')) {
            // absolute URL
            if (@$oldBase['path'] and strpos($url, $oldBase['path']) !== 0) {
                // The URL falls outside of the old base so we don't perform the transformation
                return $url;
            }

            $url = substr($url, strlen($oldBase['path']));
            $url = self::replacePathSegments($url, $pathDict);
            $url = $newBase['path'] . $url;
            return $url;

        } else if (strpos($url, 'http://') === 0 or strpos($url, 'https://') === 0 or strpos($url, '//') === 0) {
            // Absolute URL
            $parts = parse_url($url);
            if (@$oldBase['path'] and strpos(@$parts['path'], @$oldBase['path']) !== 0) {
                // The URL falls outside of the old base so we don't perform the transformation
                return $url;
            }
            foreach (array('host', 'port', 'user', 'pass', 'scheme') as $el) {
                if (@$parts[$el] != @$oldBase[$el]) {
                    return $url;
                }

                $parts[$el] = @$newBase[$el];
            }
            $parts['path'] = self::changeBase($oldBase, $newBase, $parts['path'], $pathDict);

            return http_build_url($parts);

        } else {
            return self::replacePathSegments($url, $pathDict);
        }
    }

    /**
     * @brief Translates the path components of the specified path using a translation
     * dictionary.
     *
     * @param string $path The path to be converted.  (Path should be delimited by a slash
     *    '/'
     * @param array $dict The translation dictionary.  Should map strings to strings.
     *
     * @example
     * <code>
     * replacePathSegments('/animals/cats/black-cats', array(
     *        'animals'=> 'animaux',
     *        'cats' => 'chats'
     * ));
     * // Outputs /animaus/chats/black-cats
     * </code>
     */
    public static function replacePathSegments($path, $dict = array())
    {
        $parts = explode('/', $path);
        foreach ($parts as $k => $v) {
            $v = strtolower($v);
            if (isset($dict[$v])) {
                $parts[$k] = $dict[$v];
            }

        }
        return implode('/', $parts);
    }

    /**
     * @brief Converts a URL from source site space to proxy site space.
     * @param string $url The url to be converted.
     * @return string The converted URL.
     * @see unproxifyUrl()
     * @see proxifyPath()
     * @see changeBase()
     */
    public function proxifyUrl($url)
    {
        return self::changeBase($this->_srcParts, $this->_proxyParts, $url, $this->aliases);

    }

    /**
     * @private
     *
     * @brief This is used when converting urls and css portions in html.
     *
     */
    public function _domCallback($element)
    {
        $atts = array(
            'action', 'href', 'src', 'src_' . $this->_proxyLang, 'href_' . $this->_proxyLang, 'data-href',
        );
        $convert = array(
            'src_' . $this->_proxyLang => 'src',
            'href_' . $this->_proxyLang => 'href',
            'action_' . $this->_proxyLang => 'action',
        );

        foreach ($atts as $att) {
            if ($element->hasAttribute($att) and !$element->hasAttribute('data-swete-translate')) {
                $element->setAttribute($att, $this->proxifyUrl($element->getAttribute($att)));
                if (isset($convert[$att])) {
                    $element->setAttribute($convert[$att], $element->getAttribute($att));
                }
                if ($this->unproxifyResourcePaths and !$element->hasAttribute($att . '_' . $this->_proxyLang)) {
                    $tagName = $element->tagName;
                    if (($tagName == 'img' and $att == 'src')
                        or
                        ($tagName == 'link' and $att == 'href')
                        or
                        ($tagName == 'script' and $att == 'src')
                        or
                        ($tagName == 'embed' and $att == 'src')
                    ) {
                        $element->setAttribute($att, $this->unproxifyUrl($element->getAttribute($att)));
                        $url = $element->getAttribute($att);
                        $firstChar = '';
                        if (strlen($url) > 0) {
                            $firstChar = $url{0};
                        }

                        $secondChar = '';
                        if (strlen($url) > 1) {
                            $secondChar = $url{1};
                        }

                        if ($firstChar === '/' and $secondChar !== '/') {
                            $tmpParts = $this->_srcParts;
                            $tmpParts['path'] = $url;
                            $url = http_build_url($tmpParts); //$this->_srcUrl.$url;
                            if ($this->generifyResourceProtocols) {
                                $url = preg_replace('#^https?://#', '//', $url);
                            }
                            $element->setAttribute($att, $url);
                        }

                    }

                }
            }
        }

    }

    /**
     * @private
     *
     * @brief Callback used by preg_replace when converting CSS.
     */
    public function _cssCallback($match)
    {
        return 'url(' . $match[1] . $this->proxifyUrl($match[2]) . $match[3] . ')';
    }

    private function _getElementById($id, DOMNode $root)
    {
        $doc = $root->ownerDocument ? $root->ownerDocument : $root;
        $xpath = new DOMXPath($doc);
        //echo "looking for id ".$id;
        //echo $doc->saveXML();
        $matches = $xpath->query(".//*[@id=\"" . $id . "\"]", $root);
        foreach ($matches as $match) {
            //echo "Found ".print_r($match, true);
            return $match;
        }
        return null;
    }

    /**
     * Injects dynamic content from $srcNode into the corresponding spot of
     * $targetNode.
     */
    private function injectDynamicContent(DOMNode $targetNode, DOMNode $srcNode)
    {
        $staticDom = $targetNode instanceof DOMDocument ? $targetNode : $targetNode->ownerDocument;
        $dynamicDom = $srcNode instanceof DOMDocument ? $srcNode : $srcNode->ownerDocument;
        $xpathS = new DOMXPath($staticDom);
        $matches = $xpathS->query(".//*[@data-swete-static and @id]", $targetNode);
        foreach ($matches as $match) {
            if ($match->getAttribute("data-swete-static" == "0")) {
                $node = $this->_getElementById($match->getAttribute("id"), $srcNode);
                if ($node) {
                    $importedNode = $node;
                    if ($importedNode->ownerDocument !== $match->ownerDocument) {
                        $importedNode = $staticDom->importNode($node, true);
                    }
                    $parent = $match->parentNode;
                    if ($parent) {
                        $replacedNode = $parent->replaceChild($importedNode, $match);
                        if ($replacedNode) {
                            $this->injectStaticSnapshot($importedNode, $replacedNode);
                        }
                    }
                }
            }
        }
    }

    /**
     * Injects static snapshots contained in $srcNode into the corresponding
     * nodes in $targetNode.  $srcNode is assumed to originate from the DOM of
     * a static snapshot of the page that $targetNode is from.
     *
     */
    private function injectStaticSnapshot(DOMNode $targetNode, DOMNode $srcNode)
    {
        $dynamicDom = $targetNode instanceof DOMDocument ? $targetNode : $targetNode->ownerDocument;
        $staticDom = $srcNode instanceof DOMDocument ? $srcNode : $srcNode->ownerDocument;
        $xpathD = new DOMXPath($dynamicDom);
        
        
        
        $matches = $xpathD->query(".//*[@data-swete-static and @id]", $targetNode);

        foreach ($matches as $match) {

            if ($match->getAttribute("data-swete-static") == "1") {

                $node = $this->_getElementById($match->getAttribute("id"), $srcNode);
                if ($node) {
                    $importedNode = $node;
                    if ($importedNode->ownerDocument !== $match->ownerDocument) {
                        $importedNode = $dynamicDom->importNode($node, true);
                    }
                    $parent = $match->parentNode;
                    if ($parent) {

                        $replacedNode = $parent->replaceChild($importedNode, $match);

                        if ($replacedNode) {
                            $this->injectDynamicContent($importedNode, $replacedNode);
                        }
                    }
                }

            }
        }
    }
    
    private function loadSnapshotDoc($snapshotPath) 
    {
    	$snapHtml = file_get_contents($snapshotPath);
		$snapDoc = null;
		if (stripos($snapHtml, '<body') === false and stripos($snapHtml, '<head') === false) {
			$snapFullDoc = false;
		}

		if ($this->useHtml5Parser) {
			$intro = substr($snapHtml, 0, 255);
			if (stripos($intro, '<!DOCTYPE html>') !== false) {
				// this is html5 so we'll use the html5
				require_once 'lib/HTML5.php';
				$snapDoc = HTML5::loadHTML($snapHtml);
				// noscripts contents are treated like text which causes problems when
				// filters/replacements are run on them.  Let's just remove them
				$noscripts = $snapDoc->getElementsByTagName('noscript');
				foreach ($noscripts as $noscript) {
					$noscript->parentNode->removeChild($noscript);
				}
			}
		}
		if (!isset($snapDoc)) {
			$snapDoc = new DOMDocument;
			$res = @$snapDoc->loadHtml('<?xml encoding="UTF-8">' . $snapHtml);
			// dirty fix
			foreach ($snapDoc->childNodes as $item) {
				if ($item->nodeType == XML_PI_NODE) {
					$snapDoc->removeChild($item);
				}
			}

			// remove hack
			$snapDoc->encoding = 'UTF-8'; // insert proper
			if (!$res) {
				throw new Exception("Failed to convert to HTML in snapshot.  Expecting Object by got something else.");
			}

		}
		return $snapDoc;
    }
    
    /**
     * Replaces all <swete-block> tags with their corresponding block snapshot.
     * @param $snapDoc The snapshot document in which the blocks should be replaced.
     * @param $snapshotId The snapshot ID in which to search for the blocks.
     * @return void
     */
    private function injectGlobalBlocksIntoSnapshot(DOMDocument $snapDoc, $snapshotId) 
    {
		$xpathS = new DOMXPath($snapDoc);
		$blocks = $xpathS->query(".//swete-block[@id]", $snapDoc);
		foreach ($blocks as $block) {
			$blockId = $block->getAttribute('id');
			$blockPageUrl = 'swete-block?id='.urlencode($blockId);
			$snapPath = $this->snapshotsPath . DIRECTORY_SEPARATOR . intval($snapshotId) . DIRECTORY_SEPARATOR . sha1($blockPageUrl);
			if (file_exists($snapPath)) {
				$blockSnapDoc = $this->loadSnapshotDoc($snapPath);
				if (isset($blockSnapDoc)) {
					$node = $this->_getElementById($blockId, $blockSnapDoc);
					if ($node) {
						$importedNode = $node;
						if ($importedNode->ownerDocument !== $block->ownerDocument) {
							$importedNode = $snapDoc->importNode($node, true);
						}
						$parent = $block->parentNode;
						if ($parent) {
							$replacedNode = $parent->replaceChild($importedNode, $block);
						}
					}
				}
			}
		}	
    }

    /**
     * @brief Converts HTML from source space to proxy space.  This will convert
     * all URLs.
     *
     * @param string $html The source HTML.
     * @return string The proxified HTML with URLs converted.
     */
    public function proxifyHtml($html)
    {
        $fullDoc = true;
        $doc = null;

        if (is_string($html)) {
            if (stripos($html, '<body') === false and stripos($html, '<head') === false) {
                $fullDoc = false;
            }

            if ($this->useHtml5Parser) {
                $intro = substr($html, 0, 255);
                if (stripos($intro, '<!DOCTYPE html>') !== false) {
                    // this is html5 so we'll use the html5
                    require_once 'lib/HTML5.php';
                    $doc = HTML5::loadHTML($html);
                    // noscripts contents are treated like text which causes problems when
                    // filters/replacements are run on them.  Let's just remove them
                    $noscripts = $doc->getElementsByTagName('noscript');
                    foreach ($noscripts as $noscript) {
                        $noscript->parentNode->removeChild($noscript);
                    }
                }
            }
            if (!isset($doc)) {
                $doc = new DOMDocument;
                $res = @$doc->loadHtml('<?xml encoding="UTF-8">' . $html);
                // dirty fix
                foreach ($doc->childNodes as $item) {
                    if ($item->nodeType == XML_PI_NODE) {
                        $doc->removeChild($item);
                    }
                }

                // remove hack
                $doc->encoding = 'UTF-8'; // insert proper
                if (!$res) {
                    throw new Exception("Failed to convert to HTML.  Expecting Object by got something else.");
                }

            }
        } else if ($html instanceof DOMDocument) {
            $doc = $html;
        }

        $xpath = new DOMXPath($doc);
        $matches = $xpath->query('//*[@href or @src or @action or @src_' . $this->_proxyLang . ' or @href_' . $this->_proxyLang . ']');
        foreach ($matches as $match) {
            //echo "Callback for element";
            $this->_domCallback($match);
        }

        $matches = $xpath->query('//style');
        foreach ($matches as $match) {
            //echo "Found style: ".$match->textContent;
            $match->nodeValue = $this->proxifyCss($match->textContent);
        }
        $matches = $xpath->query('//*[@style]');
        foreach ($matches as $match) {
            $match->setAttribute('style', $this->proxifyCss($match->getAttribute('style')));
        }

        $body = $xpath->query('//body');
        foreach ($body as $b) {
            $class = '';
            if ($b->hasAttribute('class')) {
                $class = $b->getAttribute('class');
            }

            $class .= ' x-swete-translation-' . $this->_proxyLang;
            $b->setAttribute('class', $class);
        }

        // Now for the script tags
        $scriptTexts = $xpath->query('//script/text()');
        foreach ($scriptTexts as $txt) {
            if (!trim($txt->nodeValue)) {
                continue;
            }

            $src = json_encode($this->_srcUrl);
            $src = substr($src, 1, strlen($src) - 2);
            $dest = json_encode($this->_proxyUrl);
            $dest = substr($dest, 1, strlen($dest) - 2);
            $txt->nodeValue = preg_replace('/\b(' . preg_quote($this->_srcUrl, '/') . ')/', $this->_proxyUrl, $txt->nodeValue);
            $txt->nodeValue = preg_replace('/\b(' . preg_quote($src, '/') . ')/', $dest, $txt->nodeValue);
        }

        //$html->set_callback(array($this, '_domCallback'));
        if (isset($this->snapshotsPath) and (!@$_COOKIE['--swete-static'] or @$_COOKIE['--swete-static'] === 'true')) {
            $staticSections = $xpath->query("//*[@data-swete-static]");
            $found = false;
            foreach ($staticSections as $sec) {
                $found = true;
                break;
            }
            if (!$found) {
                $blocks = $xpath->query("//swete-block[@id]");
                foreach ($blocks as $sec) {
                    $found = true;
                    break;
                }
            }
            
            $blocksInjected = false;
            if ($found) {
                // Now check to see if

                if (file_exists($this->snapshotsPath)) {
                    $snapshotIndex = $this->snapshotsPath . DIRECTORY_SEPARATOR . 'index.txt';
                    if ($this->snapshotId >= 0 or file_exists($snapshotIndex)) {
                        $currSnapshot = $this->snapshotId >= 0 ? $this->snapshotId : trim(file_get_contents($snapshotIndex));
                        if ($currSnapshot and intval($currSnapshot) > 0) {
                            $snapshotPath = $this->snapshotsPath . DIRECTORY_SEPARATOR . intval($currSnapshot);
                            $pageId = sha1($this->snapshotPage);
                            $snapshotPath = $snapshotPath . DIRECTORY_SEPARATOR . $pageId;
                            if (file_exists($snapshotPath)) {
                                $snapDoc = $this->loadSnapshotDoc($snapshotPath);
                                if (isset($snapDoc)) {
									$this->injectGlobalBlocksIntoSnapshot($snapDoc, $currSnapshot);
									$blocksInjected = true;
                                    $this->injectStaticSnapshot($doc, $snapDoc);
                                }
                            }

                        }
                        if (!$blocksInjected) {
                            // The page didn't have a snapshot,
                            // but some of the page's blocks might have snapshots.
                            $this->injectGlobalBlocksIntoSnapshot($doc, $currSnapshot);
                            $blocksInjected = true;
                        }
                    }
                }
            }
            

        }

        if (!$fullDoc) {
            $out = $doc->saveXml($xpath->query('//body')->item(0));
            $start = strpos($out, '>') + 1;
            $end = strrpos($out, '<');
            $out = substr($out, $start, $end - $start);
        } else {
            if ($this->useHtml5Serializer) {
                require_once 'lib/HTML5.php';
                $out = HTML5::saveHTML($doc);
                $out = preg_replace('/(<script[^>]*>)' . preg_quote('<![CDATA[', '/') . '/', '$1', $out);
                $out = preg_replace('/' . preg_quote(']]></script>', '/') . '/', '</script>', $out);
            } else {
                $out = $doc->saveHtml();
            }
        }
        unset($doc);
        return $out;
    }

    private $lastTranslationsExcludeBlocks = null;
    private $lastTranslationStats = null;
    public function lastTranslationFoundBlocks() {
        return $this->lastTranslateHtmlInput and strpos($this->lastTranslateHtmlInput, '<swete-block') !== false;
    }

    /**
     * Gets array(string->translation) of all of the translations on this page
     * but excludes content of <swete-block> tags.  If the last translation was
     * run without excluding blocks, then it will run the translation again on the
     * last string input that was used to translateHtml().
     * @returns array(string->translation)
     */
    public function getLastTranslationsExcludeBlocks() {
        if (!isset($this->lastTranslationsExcludeBlocks)) {
            if (!isset($this->lastTranslateHtmlInput)) {
                throw new Exception("Cannot get last translation excluding blocks until after translateHtml() has been called once");
            }
            error_log("retranslating html input excluding blocks");
            $this->translateHtml($this->lastTranslateHtmlInput, $this->lastTranslationStats, true, true);
        }
        return $this->lastTranslationsExcludeBlocks;
    }

    /**
     * @brief Translates HTML using the specified translation memory and settings.
     *
     * @param string $html The HTML to translate.
     * @param array &$stats An out parameter that passes out the following info:
     *        - misses : The number of strings that did not find a match in the TM.
     *         - matches : The number of strings that found a match in the TM.
     * @param boolean $logMisses Whether to log missed translations.
     * @param boolean $excludeBlocks  Whether to strip <swete-block> tags out for the translation.
     *   This is useful for calculating translations that are on this page only - excluding blocks
     *   because blocks are handled independently.
     *
     * @return string The translated HTML.
     */
    public function translateHtml($html, &$stats = null, $logMisses = false, $excludeBlocks = false)
    {
        $this->lastTranslateHtmlInput = $html; // so we can retranslate without blocks later
        $this->lastTranslationStats =& $stats;
        $this->lastTranslationsExcludeBlocks = null;
        
        $mem = $this->translationMemory;
        $minStatus = $this->minStatus;
        $maxStatus = $this->maxStatus;

        if (isset($this->translationParserVersion)) {
            $v = intval($this->translationParserVersion);
            require_once 'inc/WebLite_Translate_v' . $v . '.class.php';
            $cls = 'WebLite_HTML_Translator_v' . $v;
            $translator = new $cls();
        } else {
            require_once 'inc/WebLite_Translate.class.php';
            $translator = new Weblite_HTML_Translator();
        }
        $translator->useHtml5Parser = $this->useHtml5Parser;
        $translator->sourceDateLocale = $this->sourceDateLocale;
        $translator->targetDateLocale = $this->targetDateLocale;
        $translator->excludeBlocks = $excludeBlocks;
        $html2 = $translator->extractStrings($html);
        $strings = $translator->strings;
        if (isset($this->lastStrings)) {
            unset($this->lastStrings);
        }

        if (isset($this->lastTranslations)) {
            unset($this->lastTranslations);
        }

        // Store the string output so that we can use it from outside
        // after the function returns
        $this->lastStrings = $strings;
        $this->lastTranslations = array();
        $paramsArr = array();
        foreach ($strings as $k => $v) {
            unset($params);
            $strings[$k] = TMTools::encode($v, $params);
            $paramsArr[$k] = $params;
        }

        $translations = $mem->getTranslations($strings, $minStatus, $maxStatus);

        $matches = 0;
        $misses = 0;

        $log = array();
        if (!$logMisses) {
            foreach ($translations as $k => $v) {
                if (isset($v)) {
                    $strings[$k] = $v;
                    $matches++;
                } else {
                    $misses++;
                }
            }
        } else {
            foreach ($translations as $k => $v) {
                $this->lastTranslations[$strings[$k]] = $v;
                if (isset($v)) {
                    $strings[$k] = $v;
                    $matches++;
                } else {
                    $log[$k] = $strings[$k];
                    $misses++;
                }
            }

        }
        if ($excludeBlocks) {
            $this->lastTranslationsExcludeBlocks = $this->lastTranslations;
        }

        $stats = array(
            'matches' => $matches,
            'misses' => $misses,
        );

        //$out = $this->getTranslation($mem->getDestinationLanguage());
        if ($logMisses) {
            foreach ($log as $k => $v) {
                try {
                    //print_r($paramsArr[$k]);
                    $log[$k] = TMTools::decode($v, $paramsArr[$k]);
                } catch (Exception $ex) {
                    //echo $ex->getMessage();
                    //exit;
                }
            }
            $stats['log'] = $log;
        }

        foreach ($strings as $k => $v) {
            $translator->strings[$k] = TMTools::decode($v, $paramsArr[$k]);

        }

        $html = $translator->replaceStrings($html2);
        return $html;

    }

    /**
     * @brief Converts URLs in CSS.
     * @param string $css The CSS to convert.
     * @return string $css The converted CSS.
     */
    public function proxifyCss($css)
    {
        return preg_replace_callback('/url\((["\']?)(.*?)(["\']?)\)/i', array($this, '_cssCallback'), $css);
    }

    public function proxifyHeaders($headers, $doCharset = false)
    {
        require_once 'inc/SweteTools.php';
        $out = array();
        foreach ($headers as $header) {

            if (preg_match('/^(?:Content-Type|Content-Language|Set-Cookie|Location|Etag|Pragma|Cache-Control|Last-Modified|Accept-Ranges|Date|Server):/i', $header)) {

                if (preg_match('/^Location:(.*)$/i', $header, $matches)) {
                    if (strpos($header, '&SWETE_NO_PROXIFY=1') !== false) {
                        $header = str_replace('&SWETE_NO_PROXIFY=1', '', $header);
                    } else {
                        $header = 'Location: ' . $this->proxifyUrl(trim($matches[1]));
                    }

                } else if (preg_match('/^(Set-Cookie:)(.*)$/i', $header, $matches)) {
                    $cookieStr = $matches[2];

                    $domainMatch = '';
                    if (preg_match('/domain=([^;]+)/i', $cookieStr, $matches2)) {
                        $domainMatch = $matches2[1];
                    }
                    $proxyHost = $this->_proxyParts['host'];
                    $domainReplacement = 'domain=.' . $proxyHost;
                    if (substr($proxyHost, 0, 4) === 'www.') {
                        $domainReplacement = 'domain=.' . substr($proxyHost, 4);
                    }
                    $replaceDomain = false;
                    if ($domainMatch === $proxyHost) {
                        // keep default domain replacement
                        $replaceDomain = true;
                    } else if ($domainMatch) {
                        if ($domainMatch{0} === '*') {
                            $domainMatch = substr($domainMatch, 1);
                        }
                        if ($domainMatch) {
                            if ($domainMatch{0} !== '.') {
                                $domainMatch = '.' . $domainMatch;
                            }

                            if (SweteTools::endsWith($proxyHost, $domainMatch)) {
                                // The cookie is already valid for the proxy domain
                                // leave it alone

                            } else {
                                $replaceDomain = true;
                            }
                        }
                    }
                    $domainCount = 0;
                    if ($replaceDomain) {
                        $domainPattern = '/domain=[^;]+/i';
                        if (count(explode('.', $proxyHost)) < 2) {
                            $domainReplacement = '';
                            $domainPattern = '/domain=[^;]+;?/i';
                        }
                        $cookieStr = preg_replace($domainPattern, $domainReplacement, $cookieStr, -1, $domainCount);
                    }
                    $cookieStr = preg_replace('/Path=[^;]+/i', 'path=' . $this->_proxyParts['path'], $cookieStr);
                    if ($domainCount > 0) {
                        $out[] = 'Set-Cookie:' . $cookieStr;
                    } else {
                        $header = 'Set-Cookie:' . $cookieStr;
                    }
                    // Yes, we are adding the cookie twice.  We want the cookie to be valid
                    // for both the source domain and for our own domain, so we are making a copy
                    // of it.
                }
                if ($doCharset) {
                    $header = preg_replace('/^(Content-Type:.*charset=)(.*)$/i', '$1UTF-8', $header);
                }

                $out[] = $header;
            }

        }
        //print_r($out);exit;
        return $out;
    }

    private static $json_div_id_prefix = 'jsondiv_';
    private static $json_script_id_prefix = 'jsonscript_';
    private function _jsonToHtml(array &$stream, array &$json, array $textkeys, array $htmlkeys, $forceTranslateType = null)
    {
        $scriptPrefix = self::$json_script_id_prefix;
        foreach ($json as $k => $v) {
            if ($forceTranslateType === 'text' or (is_string($k) and in_array($k, $textkeys))) {
                if (is_array($v)) {
                    $this->_jsonToHtml($stream, $json[$k], $textkeys, $htmlkeys, 'text');
                } else {
                    $i = count($stream);
                    $stream[] = '<div id="' . self::$json_div_id_prefix . $i . '">' . htmlspecialchars($v) . '</div>';
                    $json[$k] = 'sweteplaceholder://' . $i;
                }
            } else if ($forceTranslateType === 'html' or (is_string($k) and in_array($k, $htmlkeys))) {
                if (is_array($v)) {
                    $this->_jsonToHtml($stream, $json[$k], $textkeys, $htmlkeys, 'html');
                } else {
                    // Preserve scripts
                    $scriptStack = &$this->_scriptStack;
                    $v = preg_replace_callback('/<script[^>]*>[\s\S]*?<\/script>/', function ($matches) use (&$scriptStack, $scriptPrefix) {
                        $id = count($scriptStack);
                        $scriptStack[] = $matches[0];
                        return '<script id="' . $scriptPrefix . $id . '"></script>';
                    },
                        $v
                    );

                    $i = count($stream);
                    $stream[] = '<div id="' . self::$json_div_id_prefix . $i . '">' . $v . '</div>';
                    $json[$k] = 'sweteplaceholder://' . $i;
                }
            } else if (is_array($v)) {
                $this->_jsonToHtml($stream, $json[$k], $textkeys, $htmlkeys);
            }
        }
    }

    private function _getJsonKeys(&$json)
    {
        $textkeys = self::$default_json_keys['text']; //array();
        $htmlkeys = self::$default_json_keys['html']; //array('cell');
        if (isset($json['swete:text_keys'])) {
            $textkeys = $json['swete:text_keys'];
        }
        if (isset($json['swete:html_keys'])) {
            $htmlkeys = $json['swete:html_keys'];
        }
        return array(
            'html' => $htmlkeys,
            'text' => $textkeys,
        );
    }

    public function jsonToHtml(&$json)
    {
        $this->_scriptStack = array();
        $stream = array();
        $keys = $this->_getJsonKeys($json);
        $this->_jsonToHtml($stream, $json, $keys['text'], $keys['html']);

        $out = '<!doctype html><html><head></head><body>' . implode("\n", $stream) . '</body></html>';
        return $out;

    }

    public function htmlToJson(array &$json, $html)
    {

        $doc = SweteTools::loadHtml($html);
        $keys = $this->_getJsonKeys($json);
        $this->_htmlToJson($doc, $json, $keys['text'], $keys['html']);
        $this->_scriptStack = array();
        return json_encode($json);
    }

    private function getElementById(DOMDocument $doc, $id)
    {
        $xpath = new DOMXPath($doc);
        return $xpath->query("//*[@id='$id']")->item(0);
    }

    private function _htmlToJson(DOMDocument $doc, array &$json, array $textkeys, array $htmlkeys, $translateType = null)
    {
        foreach ($json as $k => $v) {
            if (is_array($v)) {
                if (in_array($k, $textkeys)) {
                    $translateType = 'text';
                } else if (in_array($k, $htmlkeys)) {
                    $translateType = 'html';
                }

                $this->_htmlToJson($doc, $json[$k], $textkeys, $htmlkeys, $translateType);
            } else {
                if (!isset($translateType) and !in_array($k, $textkeys) and !in_array($k, $htmlkeys)) {
                    continue;
                }

                if (preg_match('#^sweteplaceholder://(\d+)$#', $v, $matches)) {
                    $el = $this->getElementById($doc, self::$json_div_id_prefix . $matches[1]);
                    if ($el) {
                        $v = $doc->saveXml($el);
                        $start = strpos($v, '>') + 1;
                        $end = strrpos($v, '<');
                        $v = substr($v, $start, $end - $start);
                        $json[$k] = $v;
                        if (in_array($k, $textkeys) or $translateType === 'text') {
                            $json[$k] = htmlspecialchars_decode($json[$k]);
                        } else {
                            $scriptStack = &$this->_scriptStack;
                            $json[$k] = preg_replace_callback('/<script id="' . preg_quote(self::$json_script_id_prefix, '/') . '(\d+)"><\/script>/', function ($matches) use (&$scriptStack) {
                                return $scriptStack[intval($matches[1])];
                            },
                                $json[$k]
                            );
                        }
                    } else {
                    }
                }
            }
        }
    }
}
