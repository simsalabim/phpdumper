<?php
/**
 * phpQuery is a server-side, chainable, CSS3 selector driven
 * Document Object Model (DOM) API based on jQuery JavaScript Library.
 *
 * @version 0.9.5
 * @link http://code.google.com/p/phpquery/
 * @link http://phpquery-library.blogspot.com/
 * @link http://jquery.com/
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @package phpQuery
 */

// class names for instanceof
// TODO move them as class constants into phpQuery
define('DOMDOCUMENT', 'DOMDocument');
define('DOMELEMENT', 'DOMElement');
define('DOMNODELIST', 'DOMNodeList');
define('DOMNODE', 'DOMNode');

/**
 * DOMEvent class.
 *
 * Based on
 * @link http://developer.mozilla.org/En/DOM:event
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 * @todo implement ArrayAccess ?
 */
class DOMEvent {
	/**
	 * Returns a boolean indicating whether the event bubbles up through the DOM or not.
	 *
	 * @var unknown_type
	 */
	public $bubbles = true;
	/**
	 * Returns a boolean indicating whether the event is cancelable.
	 *
	 * @var unknown_type
	 */
	public $cancelable = true;
	/**
	 * Returns a reference to the currently registered target for the event.
	 *
	 * @var unknown_type
	 */
	public $currentTarget;
	/**
	 * Returns detail about the event, depending on the type of event.
	 *
	 * @var unknown_type
	 * @link http://developer.mozilla.org/en/DOM/event.detail
	 */
	public $detail;	// ???
	/**
	 * Used to indicate which phase of the event flow is currently being evaluated.
	 *
	 * NOT IMPLEMENTED
	 *
	 * @var unknown_type
	 * @link http://developer.mozilla.org/en/DOM/event.eventPhase
	 */
	public $eventPhase;	// ???
	/**
	 * The explicit original target of the event (Mozilla-specific).
	 *
	 * NOT IMPLEMENTED
	 *
	 * @var unknown_type
	 */
	public $explicitOriginalTarget; // moz only
	/**
	 * The original target of the event, before any retargetings (Mozilla-specific).
	 *
	 * NOT IMPLEMENTED
	 *
	 * @var unknown_type
	 */
	public $originalTarget;	// moz only
	/**
	 * Identifies a secondary target for the event.
	 *
	 * @var unknown_type
	 */
	public $relatedTarget;
	/**
	 * Returns a reference to the target to which the event was originally dispatched.
	 *
	 * @var unknown_type
	 */
	public $target;
	/**
	 * Returns the time that the event was created.
	 *
	 * @var unknown_type
	 */
	public $timeStamp;
	/**
	 * Returns the name of the event (case-insensitive).
	 */
	public $type;
	public $runDefault = true;
	public $data = null;
	public function __construct($data) {
		foreach($data as $k => $v) {
			$this->$k = $v;
		}
		if (! $this->timeStamp)
			$this->timeStamp = time();
	}
	/**
	 * Cancels the event (if it is cancelable).
	 *
	 */
	public function preventDefault() {
		$this->runDefault = false;
	}
	/**
	 * Stops the propagation of events further along in the DOM.
	 *
	 */
	public function stopPropagation() {
		$this->bubbles = false;
	}
}


/**
 * DOMDocumentWrapper class simplifies work with DOMDocument.
 *
 * Know bug:
 * - in XHTML fragments, <br /> changes to <br clear="none" />
 *
 * @todo check XML catalogs compatibility
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 */
class DOMDocumentWrapper {
	/**
	 * @var DOMDocument
	 */
	public $document;
	public $id;
	/**
	 * @todo Rewrite as method and quess if null.
	 * @var unknown_type
	 */
	public $contentType = '';
	public $xpath;
	public $uuid = 0;
	public $data = array();
	public $dataNodes = array();
	public $events = array();
	public $eventsNodes = array();
	public $eventsGlobal = array();
	/**
	 * @TODO iframes support http://code.google.com/p/phpquery/issues/detail?id=28
	 * @var unknown_type
	 */
	public $frames = array();
	/**
	 * Document root, by default equals to document itself.
	 * Used by documentFragments.
	 *
	 * @var DOMNode
	 */
	public $root;
	public $isDocumentFragment;
	public $isXML = false;
	public $isXHTML = false;
	public $isHTML = false;
	public $charset;
	public function __construct($markup = null, $contentType = null, $newDocumentID = null) {
		if (isset($markup))
			$this->load($markup, $contentType, $newDocumentID);
		$this->id = $newDocumentID
			? $newDocumentID
			: md5(microtime());
	}
	public function load($markup, $contentType = null, $newDocumentID = null) {
//		phpQuery::$documents[$id] = $this;
		$this->contentType = strtolower($contentType);
		if ($markup instanceof DOMDOCUMENT) {
			$this->document = $markup;
			$this->root = $this->document;
			$this->charset = $this->document->encoding;
			// TODO isDocumentFragment
		} else {
			$loaded = $this->loadMarkup($markup);
		}
		if ($loaded) {
//			$this->document->formatOutput = true;
			$this->document->preserveWhiteSpace = true;
			$this->xpath = new DOMXPath($this->document);
			$this->afterMarkupLoad();
			return true;
			// remember last loaded document
//			return phpQuery::selectDocument($id);
		}
		return false;
	}
	protected function afterMarkupLoad() {
		if ($this->isXHTML) {
			$this->xpath->registerNamespace("html", "http://www.w3.org/1999/xhtml");
		}
	}
	protected function loadMarkup($markup) {
		$loaded = false;
		if ($this->contentType) {
			self::debug("Load markup for content type {$this->contentType}");
			// content determined by contentType
			list($contentType, $charset) = $this->contentTypeToArray($this->contentType);
			switch($contentType) {
				case 'text/html':
					phpQuery::debug("Loading HTML, content type '{$this->contentType}'");
					$loaded = $this->loadMarkupHTML($markup, $charset);
				break;
				case 'text/xml':
				case 'application/xhtml+xml':
					phpQuery::debug("Loading XML, content type '{$this->contentType}'");
					$loaded = $this->loadMarkupXML($markup, $charset);
				break;
				default:
					// for feeds or anything that sometimes doesn't use text/xml
					if (strpos('xml', $this->contentType) !== false) {
						phpQuery::debug("Loading XML, content type '{$this->contentType}'");
						$loaded = $this->loadMarkupXML($markup, $charset);
					} else
						phpQuery::debug("Could not determine document type from content type '{$this->contentType}'");
			}
		} else {
			// content type autodetection
			if ($this->isXML($markup)) {
				phpQuery::debug("Loading XML, isXML() == true");
				$loaded = $this->loadMarkupXML($markup);
				if (! $loaded && $this->isXHTML) {
					phpQuery::debug('Loading as XML failed, trying to load as HTML, isXHTML == true');
					$loaded = $this->loadMarkupHTML($markup);
				}
			} else {
				phpQuery::debug("Loading HTML, isXML() == false");
				$loaded = $this->loadMarkupHTML($markup);
			}
		}
		return $loaded;
	}
	protected function loadMarkupReset() {
		$this->isXML = $this->isXHTML = $this->isHTML = false;
	}
	protected function documentCreate($charset, $version = '1.0') {
		if (! $version)
			$version = '1.0';
		$this->document = new DOMDocument($version, $charset);
		$this->charset = $this->document->encoding;
//		$this->document->encoding = $charset;
		$this->document->formatOutput = true;
		$this->document->preserveWhiteSpace = true;
	}
	protected function loadMarkupHTML($markup, $requestedCharset = null) {
		if (phpQuery::$debug)
			phpQuery::debug('Full markup load (HTML): '.substr($markup, 0, 250));
		$this->loadMarkupReset();
		$this->isHTML = true;
		if (!isset($this->isDocumentFragment))
			$this->isDocumentFragment = self::isDocumentFragmentHTML($markup);
		$charset = null;
		$documentCharset = $this->charsetFromHTML($markup);
		$addDocumentCharset = false;
		if ($documentCharset) {
			$charset = $documentCharset;
			$markup = $this->charsetFixHTML($markup);
		} else if ($requestedCharset) {
			$charset = $requestedCharset;
		}
		if (! $charset)
			$charset = phpQuery::$defaultCharset;
		// HTTP 1.1 says that the default charset is ISO-8859-1
		// @see http://www.w3.org/International/O-HTTP-charset
		if (! $documentCharset) {
			$documentCharset = 'ISO-8859-1';
			$addDocumentCharset = true;	
		}
		// Should be careful here, still need 'magic encoding detection' since lots of pages have other 'default encoding'
		// Worse, some pages can have mixed encodings... we'll try not to worry about that
		$requestedCharset = strtoupper($requestedCharset);
		$documentCharset = strtoupper($documentCharset);
		phpQuery::debug("DOC: $documentCharset REQ: $requestedCharset");
		if ($requestedCharset && $documentCharset && $requestedCharset !== $documentCharset) {
			phpQuery::debug("CHARSET CONVERT");
			// Document Encoding Conversion
			// http://code.google.com/p/phpquery/issues/detail?id=86
			if (function_exists('mb_detect_encoding')) {
				$possibleCharsets = array($documentCharset, $requestedCharset, 'AUTO');
				$docEncoding = mb_detect_encoding($markup, implode(', ', $possibleCharsets));
				if (! $docEncoding)
					$docEncoding = $documentCharset; // ok trust the document
				phpQuery::debug("DETECTED '$docEncoding'");
				// Detected does not match what document says...
				if ($docEncoding !== $documentCharset) {
					// Tricky..
				}
				if ($docEncoding !== $requestedCharset) {
					phpQuery::debug("CONVERT $docEncoding => $requestedCharset");
					$markup = mb_convert_encoding($markup, $requestedCharset, $docEncoding);
					$markup = $this->charsetAppendToHTML($markup, $requestedCharset);
					$charset = $requestedCharset;
				}
			} else {
				phpQuery::debug("TODO: charset conversion without mbstring...");
			}
		}
		$return = false;
		if ($this->isDocumentFragment) {
			phpQuery::debug("Full markup load (HTML), DocumentFragment detected, using charset '$charset'");
			$return = $this->documentFragmentLoadMarkup($this, $charset, $markup);
		} else {
			if ($addDocumentCharset) {
				phpQuery::debug("Full markup load (HTML), appending charset: '$charset'");
				$markup = $this->charsetAppendToHTML($markup, $charset);
			}
			phpQuery::debug("Full markup load (HTML), documentCreate('$charset')");
			$this->documentCreate($charset);
			$return = phpQuery::$debug === 2
				? $this->document->loadHTML($markup)
				: @$this->document->loadHTML($markup);
			if ($return)
				$this->root = $this->document;
		}
		if ($return && ! $this->contentType)
			$this->contentType = 'text/html';
		return $return;
	}
	protected function loadMarkupXML($markup, $requestedCharset = null) {
		if (phpQuery::$debug)
			phpQuery::debug('Full markup load (XML): '.substr($markup, 0, 250));
		$this->loadMarkupReset();
		$this->isXML = true;
		// check agains XHTML in contentType or markup
		$isContentTypeXHTML = $this->isXHTML();
		$isMarkupXHTML = $this->isXHTML($markup);
		if ($isContentTypeXHTML || $isMarkupXHTML) {
			self::debug('Full markup load (XML), XHTML detected');
			$this->isXHTML = true;
		}
		// determine document fragment
		if (! isset($this->isDocumentFragment))
			$this->isDocumentFragment = $this->isXHTML
				? self::isDocumentFragmentXHTML($markup)
				: self::isDocumentFragmentXML($markup);
		// this charset will be used
		$charset = null;
		// charset from XML declaration @var string
		$documentCharset = $this->charsetFromXML($markup);
		if (! $documentCharset) {
			if ($this->isXHTML) {
				// this is XHTML, try to get charset from content-type meta header
				$documentCharset = $this->charsetFromHTML($markup);
				if ($documentCharset) {
					phpQuery::debug("Full markup load (XML), appending XHTML charset '$documentCharset'");
					$this->charsetAppendToXML($markup, $documentCharset);
					$charset = $documentCharset;
				}
			}
			if (! $documentCharset) {
				// if still no document charset...
				$charset = $requestedCharset;
			}
		} else if ($requestedCharset) {
			$charset = $requestedCharset;
		}
		if (! $charset) {
			$charset = phpQuery::$defaultCharset;
		}
		if ($requestedCharset && $documentCharset && $requestedCharset != $documentCharset) {
			// TODO place for charset conversion
//			$charset = $requestedCharset;
		}
		$return = false;
		if ($this->isDocumentFragment) {
			phpQuery::debug("Full markup load (XML), DocumentFragment detected, using charset '$charset'");
			$return = $this->documentFragmentLoadMarkup($this, $charset, $markup);
		} else {
			// FIXME ???
			if ($isContentTypeXHTML && ! $isMarkupXHTML)
			if (! $documentCharset) {
				phpQuery::debug("Full markup load (XML), appending charset '$charset'");
				$markup = $this->charsetAppendToXML($markup, $charset);
			}
			// see http://pl2.php.net/manual/en/book.dom.php#78929
			// LIBXML_DTDLOAD (>= PHP 5.1)
			// does XML ctalogues works with LIBXML_NONET
	//		$this->document->resolveExternals = true;
			// TODO test LIBXML_COMPACT for performance improvement
			// create document
			$this->documentCreate($charset);
			if (phpversion() < 5.1) {
				$this->document->resolveExternals = true;
				$return = phpQuery::$debug === 2
					? $this->document->loadXML($markup)
					: @$this->document->loadXML($markup);
			} else {
				/** @link http://pl2.php.net/manual/en/libxml.constants.php */
				$libxmlStatic = phpQuery::$debug === 2
					? LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NONET
					: LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NONET|LIBXML_NOWARNING|LIBXML_NOERROR;
				$return = $this->document->loadXML($markup, $libxmlStatic);
// 				if (! $return)
// 					$return = $this->document->loadHTML($markup);
			}
			if ($return)
				$this->root = $this->document;
		}
		if ($return) {
			if (! $this->contentType) {
				if ($this->isXHTML)
					$this->contentType = 'application/xhtml+xml';
				else
					$this->contentType = 'text/xml';
			}
			return $return;
		} else {
			throw new Exception("Error loading XML markup");
		}
	}
	protected function isXHTML($markup = null) {
		if (! isset($markup)) {
			return strpos($this->contentType, 'xhtml') !== false;
		}
		// XXX ok ?
		return strpos($markup, "<!DOCTYPE html") !== false;
//		return stripos($doctype, 'xhtml') !== false;
//		$doctype = isset($dom->doctype) && is_object($dom->doctype)
//			? $dom->doctype->publicId
//			: self::$defaultDoctype;
	}
	protected function isXML($markup) {
//		return strpos($markup, '<?xml') !== false && stripos($markup, 'xhtml') === false;
		return strpos(substr($markup, 0, 100), '<'.'?xml') !== false;
	}
	protected function contentTypeToArray($contentType) {
		$matches = explode(';', trim(strtolower($contentType)));
		if (isset($matches[1])) {
			$matches[1] = explode('=', $matches[1]);
			// strip 'charset='
			$matches[1] = isset($matches[1][1]) && trim($matches[1][1])
				? $matches[1][1]
				: $matches[1][0];
		} else
			$matches[1] = null;
		return $matches;
	}
	/**
	 *
	 * @param $markup
	 * @return array contentType, charset
	 */
	protected function contentTypeFromHTML($markup) {
		$matches = array();
		// find meta tag
		preg_match('@<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i',
			$markup, $matches
		);
		if (! isset($matches[0]))
			return array(null, null);
		// get attr 'content'
		preg_match('@content\\s*=\\s*(["|\'])(.+?)\\1@', $matches[0], $matches);
		if (! isset($matches[0]))
			return array(null, null);
		return $this->contentTypeToArray($matches[2]);
	}
	protected function charsetFromHTML($markup) {
		$contentType = $this->contentTypeFromHTML($markup);
		return $contentType[1];
	}
	protected function charsetFromXML($markup) {
		$matches;
		// find declaration
		preg_match('@<'.'?xml[^>]+encoding\\s*=\\s*(["|\'])(.*?)\\1@i',
			$markup, $matches
		);
		return isset($matches[2])
			? strtolower($matches[2])
			: null;
	}
	/**
	 * Repositions meta[type=charset] at the start of head. Bypasses DOMDocument bug.
	 *
	 * @link http://code.google.com/p/phpquery/issues/detail?id=80
	 * @param $html
	 */
	protected function charsetFixHTML($markup) {
		$matches = array();
		// find meta tag
		preg_match('@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i',
			$markup, $matches, PREG_OFFSET_CAPTURE
		);
		if (! isset($matches[0]))
			return;
		$metaContentType = $matches[0][0];
		$markup = substr($markup, 0, $matches[0][1])
			.substr($markup, $matches[0][1]+strlen($metaContentType));
		$headStart = stripos($markup, '<head>');
		$markup = substr($markup, 0, $headStart+6).$metaContentType
			.substr($markup, $headStart+6);
		return $markup;
	}
	protected function charsetAppendToHTML($html, $charset, $xhtml = false) {
		// remove existing meta[type=content-type]
		$html = preg_replace('@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i', '', $html);
		$meta = '<meta http-equiv="Content-Type" content="text/html;charset='
			.$charset.'" '
			.($xhtml ? '/' : '')
			.'>';
		if (strpos($html, '<head') === false) {
			if (strpos($hltml, '<html') === false) {
				return $meta.$html;
			} else {
				return preg_replace(
					'@<html(.*?)(?(?<!\?)>)@s',
					"<html\\1><head>{$meta}</head>",
					$html
				);
			}
		} else {
			return preg_replace(
				'@<head(.*?)(?(?<!\?)>)@s',
				'<head\\1>'.$meta,
				$html
			);
		}
	}
	protected function charsetAppendToXML($markup, $charset) {
		$declaration = '<'.'?xml version="1.0" encoding="'.$charset.'"?'.'>';
		return $declaration.$markup;
	}
	public static function isDocumentFragmentHTML($markup) {
		return stripos($markup, '<html') === false && stripos($markup, '<!doctype') === false;
	}
	public static function isDocumentFragmentXML($markup) {
		return stripos($markup, '<'.'?xml') === false;
	}
	public static function isDocumentFragmentXHTML($markup) {
		return self::isDocumentFragmentHTML($markup);
	}
	public function importAttr($value) {
		// TODO
	}
	/**
	 *
	 * @param $source
	 * @param $target
	 * @param $sourceCharset
	 * @return array Array of imported nodes.
	 */
	public function import($source, $sourceCharset = null) {
		// TODO charset conversions
		$return = array();
		if ($source instanceof DOMNODE && !($source instanceof DOMNODELIST))
			$source = array($source);
//		if (is_array($source)) {
//			foreach($source as $node) {
//				if (is_string($node)) {
//					// string markup
//					$fake = $this->documentFragmentCreate($node, $sourceCharset);
//					if ($fake === false)
//						throw new Exception("Error loading documentFragment markup");
//					else
//						$return = array_merge($return, 
//							$this->import($fake->root->childNodes)
//						);
//				} else {
//					$return[] = $this->document->importNode($node, true);
//				}
//			}
//			return $return;
//		} else {
//			// string markup
//			$fake = $this->documentFragmentCreate($source, $sourceCharset);
//			if ($fake === false)
//				throw new Exception("Error loading documentFragment markup");
//			else
//				return $this->import($fake->root->childNodes);
//		}
		if (is_array($source) || $source instanceof DOMNODELIST) {
			// dom nodes
			self::debug('Importing nodes to document');
			foreach($source as $node)
				$return[] = $this->document->importNode($node, true);
		} else {
			// string markup
			$fake = $this->documentFragmentCreate($source, $sourceCharset);
			if ($fake === false)
				throw new Exception("Error loading documentFragment markup");
			else
				return $this->import($fake->root->childNodes);
		}
		return $return;
	}
	/**
	 * Creates new document fragment.
	 *
	 * @param $source
	 * @return DOMDocumentWrapper
	 */
	protected function documentFragmentCreate($source, $charset = null) {
		$fake = new DOMDocumentWrapper();
		$fake->contentType = $this->contentType;
		$fake->isXML = $this->isXML;
		$fake->isHTML = $this->isHTML;
		$fake->isXHTML = $this->isXHTML;
		$fake->root = $fake->document;
		if (! $charset)
			$charset = $this->charset;
//	$fake->documentCreate($this->charset);
		if ($source instanceof DOMNODE && !($source instanceof DOMNODELIST))
			$source = array($source);
		if (is_array($source) || $source instanceof DOMNODELIST) {
			// dom nodes
			// load fake document
			if (! $this->documentFragmentLoadMarkup($fake, $charset))
				return false;
			$nodes = $fake->import($source);
			foreach($nodes as $node)
				$fake->root->appendChild($node);
		} else {
			// string markup
			$this->documentFragmentLoadMarkup($fake, $charset, $source);
		}
		return $fake;
	}
	/**
	 *
	 * @param $document DOMDocumentWrapper
	 * @param $markup
	 * @return $document
	 */
	private function documentFragmentLoadMarkup($fragment, $charset, $markup = null) {
		// TODO error handling
		// TODO copy doctype
		// tempolary turn off
		$fragment->isDocumentFragment = false;
		if ($fragment->isXML) {
			if ($fragment->isXHTML) {
				// add FAKE element to set default namespace
				$fragment->loadMarkupXML('<?xml version="1.0" encoding="'.$charset.'"?>'
					.'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" '
					.'"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
					.'<fake xmlns="http://www.w3.org/1999/xhtml">'.$markup.'</fake>');
				$fragment->root = $fragment->document->firstChild->nextSibling;
			} else {
				$fragment->loadMarkupXML('<?xml version="1.0" encoding="'.$charset.'"?><fake>'.$markup.'</fake>');
				$fragment->root = $fragment->document->firstChild;
			}
		} else {
			$markup2 = phpQuery::$defaultDoctype.'<html><head><meta http-equiv="Content-Type" content="text/html;charset='
				.$charset.'"></head>';
			$noBody = strpos($markup, '<body') === false;
			if ($noBody)
				$markup2 .= '<body>';
			$markup2 .= $markup;
			if ($noBody)
				$markup2 .= '</body>';
			$markup2 .= '</html>';
			$fragment->loadMarkupHTML($markup2);
			// TODO resolv body tag merging issue
			$fragment->root = $noBody
				? $fragment->document->firstChild->nextSibling->firstChild->nextSibling
				: $fragment->document->firstChild->nextSibling->firstChild->nextSibling;
		}
		if (! $fragment->root)
			return false;
		$fragment->isDocumentFragment = true;
		return true;
	}
	protected function documentFragmentToMarkup($fragment) {
		phpQuery::debug('documentFragmentToMarkup');
		$tmp = $fragment->isDocumentFragment;
		$fragment->isDocumentFragment = false;
		$markup = $fragment->markup();
		if ($fragment->isXML) {
			$markup = substr($markup, 0, strrpos($markup, '</fake>'));
			if ($fragment->isXHTML) {
				$markup = substr($markup, strpos($markup, '<fake')+43);
			} else {
				$markup = substr($markup, strpos($markup, '<fake>')+6);
			}
		} else {
				$markup = substr($markup, strpos($markup, '<body>')+6);
				$markup = substr($markup, 0, strrpos($markup, '</body>'));
		}
		$fragment->isDocumentFragment = $tmp;
		if (phpQuery::$debug)
			phpQuery::debug('documentFragmentToMarkup: '.substr($markup, 0, 150));
		return $markup;
	}
	/**
	 * Return document markup, starting with optional $nodes as root.
	 *
	 * @param $nodes	DOMNode|DOMNodeList
	 * @return string
	 */
	public function markup($nodes = null, $innerMarkup = false) {
		if (isset($nodes) && count($nodes) == 1 && $nodes[0] instanceof DOMDOCUMENT)
			$nodes = null;
		if (isset($nodes)) {
			$markup = '';
			if (!is_array($nodes) && !($nodes instanceof DOMNODELIST) )
				$nodes = array($nodes);
			if ($this->isDocumentFragment && ! $innerMarkup)
				foreach($nodes as $i => $node)
					if ($node->isSameNode($this->root)) {
					//	var_dump($node);
						$nodes = array_slice($nodes, 0, $i)
							+ phpQuery::DOMNodeListToArray($node->childNodes)
							+ array_slice($nodes, $i+1);
						}
			if ($this->isXML && ! $innerMarkup) {
				self::debug("Getting outerXML with charset '{$this->charset}'");
				// we need outerXML, so we can benefit from
				// $node param support in saveXML()
				foreach($nodes as $node)
					$markup .= $this->document->saveXML($node);
			} else {
				$loop = array();
				if ($innerMarkup)
					foreach($nodes as $node) {
						if ($node->childNodes)
							foreach($node->childNodes as $child)
								$loop[] = $child;
						else
							$loop[] = $node;
					}
				else
					$loop = $nodes;
				self::debug("Getting markup, moving selected nodes (".count($loop).") to new DocumentFragment");
				$fake = $this->documentFragmentCreate($loop);
				$markup = $this->documentFragmentToMarkup($fake);
			}
			if ($this->isXHTML) {
				self::debug("Fixing XHTML");
				$markup = self::markupFixXHTML($markup);
			}
			self::debug("Markup: ".substr($markup, 0, 250));
			return $markup;
		} else {
			if ($this->isDocumentFragment) {
				// documentFragment, html only...
				self::debug("Getting markup, DocumentFragment detected");
//				return $this->markup(
////					$this->document->getElementsByTagName('body')->item(0)
//					$this->document->root, true
//				);
				$markup = $this->documentFragmentToMarkup($this);
				// no need for markupFixXHTML, as it's done thought markup($nodes) method
				return $markup;
			} else {
				self::debug("Getting markup (".($this->isXML?'XML':'HTML')."), final with charset '{$this->charset}'");
				$markup = $this->isXML
					? $this->document->saveXML()
					: $this->document->saveHTML();
				if ($this->isXHTML) {
					self::debug("Fixing XHTML");
					$markup = self::markupFixXHTML($markup);
				}
				self::debug("Markup: ".substr($markup, 0, 250));
				return $markup;
			}
		}
	}
	protected static function markupFixXHTML($markup) {
		$markup = self::expandEmptyTag('script', $markup);
		$markup = self::expandEmptyTag('select', $markup);
		$markup = self::expandEmptyTag('textarea', $markup);
		return $markup;
	}
	public static function debug($text) {
		phpQuery::debug($text);
	}
	/**
	 * expandEmptyTag
	 *
	 * @param $tag
	 * @param $xml
	 * @return unknown_type
	 * @author mjaque at ilkebenson dot com
	 * @link http://php.net/manual/en/domdocument.savehtml.php#81256
	 */
	public static function expandEmptyTag($tag, $xml){
        $indice = 0;
        while ($indice< strlen($xml)){
            $pos = strpos($xml, "<$tag ", $indice);
            if ($pos){
                $posCierre = strpos($xml, ">", $pos);
                if ($xml[$posCierre-1] == "/"){
                    $xml = substr_replace($xml, "></$tag>", $posCierre-1, 2);
                }
                $indice = $posCierre;
            }
            else break;
        }
        return $xml;
	}
}

/**
 * Event handling class.
 *
 * @author Tobiasz Cudnik
 * @package phpQuery
 * @static
 */
abstract class phpQueryEvents {
	/**
	 * Trigger a type of event on every matched element.
	 *
	 * @param DOMNode|phpQueryObject|string $document
	 * @param unknown_type $type
	 * @param unknown_type $data
	 *
	 * @TODO exclusive events (with !)
	 * @TODO global events (test)
	 * @TODO support more than event in $type (space-separated)
	 */
	public static function trigger($document, $type, $data = array(), $node = null) {
		// trigger: function(type, data, elem, donative, extra) {
		$documentID = phpQuery::getDocumentID($document);
		$namespace = null;
		if (strpos($type, '.') !== false)
			list($name, $namespace) = explode('.', $type);
		else
			$name = $type;
		if (! $node) {
			if (self::issetGlobal($documentID, $type)) {
				$pq = phpQuery::getDocument($documentID);
				// TODO check add($pq->document)
				$pq->find('*')->add($pq->document)
					->trigger($type, $data);
			}
		} else {
			if (isset($data[0]) && $data[0] instanceof DOMEvent) {
				$event = $data[0];
				$event->relatedTarget = $event->target;
				$event->target = $node;
				$data = array_slice($data, 1);
			} else {
				$event = new DOMEvent(array(
					'type' => $type,
					'target' => $node,
					'timeStamp' => time(),
				));
			}
			$i = 0;
			while($node) {
				// TODO whois
				phpQuery::debug("Triggering ".($i?"bubbled ":'')."event '{$type}' on "
					."node \n");//.phpQueryObject::whois($node)."\n");
				$event->currentTarget = $node;
				$eventNode = self::getNode($documentID, $node);
				if (isset($eventNode->eventHandlers)) {
					foreach($eventNode->eventHandlers as $eventType => $handlers) {
						$eventNamespace = null;
						if (strpos($type, '.') !== false)
							list($eventName, $eventNamespace) = explode('.', $eventType);
						else
							$eventName = $eventType;
						if ($name != $eventName)
							continue;
						if ($namespace && $eventNamespace && $namespace != $eventNamespace)
							continue;
						foreach($handlers as $handler) {
							phpQuery::debug("Calling event handler\n");
							$event->data = $handler['data']
								? $handler['data']
								: null;
							$params = array_merge(array($event), $data);
							$return = phpQuery::callbackRun($handler['callback'], $params);
							if ($return === false) {
								$event->bubbles = false;
							}
						}
					}
				}
				// to bubble or not to bubble...
				if (! $event->bubbles)
					break;
				$node = $node->parentNode;
				$i++;
			}
		}
	}
	/**
	 * Binds a handler to one or more events (like click) for each matched element.
	 * Can also bind custom events.
	 *
	 * @param DOMNode|phpQueryObject|string $document
	 * @param unknown_type $type
	 * @param unknown_type $data Optional
	 * @param unknown_type $callback
	 *
	 * @TODO support '!' (exclusive) events
	 * @TODO support more than event in $type (space-separated)
	 * @TODO support binding to global events
	 */
	public static function add($document, $node, $type, $data, $callback = null) {
		phpQuery::debug("Binding '$type' event");
		$documentID = phpQuery::getDocumentID($document);
//		if (is_null($callback) && is_callable($data)) {
//			$callback = $data;
//			$data = null;
//		}
		$eventNode = self::getNode($documentID, $node);
		if (! $eventNode)
			$eventNode = self::setNode($documentID, $node);
		if (!isset($eventNode->eventHandlers[$type]))
			$eventNode->eventHandlers[$type] = array();
		$eventNode->eventHandlers[$type][] = array(
			'callback' => $callback,
			'data' => $data,
		);
	}
	/**
	 * Enter description here...
	 *
	 * @param DOMNode|phpQueryObject|string $document
	 * @param unknown_type $type
	 * @param unknown_type $callback
	 *
	 * @TODO namespace events
	 * @TODO support more than event in $type (space-separated)
	 */
	public static function remove($document, $node, $type = null, $callback = null) {
		$documentID = phpQuery::getDocumentID($document);
		$eventNode = self::getNode($documentID, $node);
		if (is_object($eventNode) && isset($eventNode->eventHandlers[$type])) {
			if ($callback) {
				foreach($eventNode->eventHandlers[$type] as $k => $handler)
					if ($handler['callback'] == $callback)
						unset($eventNode->eventHandlers[$type][$k]);
			} else {
				unset($eventNode->eventHandlers[$type]);
			}
		}
	}
	protected static function getNode($documentID, $node) {
		foreach(phpQuery::$documents[$documentID]->eventsNodes as $eventNode) {
			if ($node->isSameNode($eventNode))
				return $eventNode;
		}
	}
	protected static function setNode($documentID, $node) {
		phpQuery::$documents[$documentID]->eventsNodes[] = $node;
		return phpQuery::$documents[$documentID]->eventsNodes[
			count(phpQuery::$documents[$documentID]->eventsNodes)-1
		];
	}
	protected static function issetGlobal($documentID, $type) {
		return isset(phpQuery::$documents[$documentID])
			? in_array($type, phpQuery::$documents[$documentID]->eventsGlobal)
			: false;
	}
}


interface ICallbackNamed {
	function hasName();
	function getName();
}
/**
 * Callback class introduces currying-like pattern.
 * 
 * Example:
 * function foo($param1, $param2, $param3) {
 *   var_dump($param1, $param2, $param3);
 * }
 * $fooCurried = new Callback('foo', 
 *   'param1 is now statically set', 
 *   new CallbackParam, new CallbackParam
 * );
 * phpQuery::callbackRun($fooCurried,
 * 	array('param2 value', 'param3 value'
 * );
 * 
 * Callback class is supported in all phpQuery methods which accepts callbacks. 
 *
 * @link http://code.google.com/p/phpquery/wiki/Callbacks#Param_Structures
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * 
 * @TODO??? return fake forwarding function created via create_function
 * @TODO honor paramStructure
 */
class Callback
	implements ICallbackNamed {
	public $callback = null;
	public $params = null;
	protected $name;
	public function __construct($callback, $param1 = null, $param2 = null, 
			$param3 = null) {
		$params = func_get_args();
		$params = array_slice($params, 1);
		if ($callback instanceof Callback) {
			// TODO implement recurention
		} else {
			$this->callback = $callback;
			$this->params = $params;
		}
	}
	public function getName() {
		return 'Callback: '.$this->name;
	}
	public function hasName() {
		return isset($this->name) && $this->name;
	}
	public function setName($name) {
		$this->name = $name;
		return $this;
	}
	// TODO test me
//	public function addParams() {
//		$params = func_get_args();
//		return new Callback($this->callback, $this->params+$params);
//	}
}
/**
 * Shorthand for new Callback(create_function(...), ...);
 * 
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 */
class CallbackBody extends Callback {
	public function __construct($paramList, $code, $param1 = null, $param2 = null, 
			$param3 = null) {
		$params = func_get_args();
		$params = array_slice($params, 2);
		$this->callback = create_function($paramList, $code);
		$this->params = $params;
	}
}
/**
 * Callback type which on execution returns reference passed during creation.
 * 
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 */
class CallbackReturnReference extends Callback
	implements ICallbackNamed {
	protected $reference;
	public function __construct(&$reference, $name = null){
		$this->reference =& $reference;
		$this->callback = array($this, 'callback');
	}
	public function callback() {
		return $this->reference;
	}
	public function getName() {
		return 'Callback: '.$this->name;
	}
	public function hasName() {
		return isset($this->name) && $this->name;
	}
}
/**
 * Callback type which on execution returns value passed during creation.
 * 
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 */
class CallbackReturnValue extends Callback
	implements ICallbackNamed {
	protected $value;
	protected $name;
	public function __construct($value, $name = null){
		$this->value =& $value;
		$this->name = $name;
		$this->callback = array($this, 'callback');
	}
	public function callback() {
		return $this->value;
	}
	public function __toString() {
		return $this->getName();
	}
	public function getName() {
		return 'Callback: '.$this->name;
	}
	public function hasName() {
		return isset($this->name) && $this->name;
	}
}
/**
 * CallbackParameterToReference can be used when we don't really want a callback,
 * only parameter passed to it. CallbackParameterToReference takes first 
 * parameter's value and passes it to reference.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 */
class CallbackParameterToReference extends Callback {
	/**
	 * @param $reference
	 * @TODO implement $paramIndex; 
	 * param index choose which callback param will be passed to reference
	 */
	public function __construct(&$reference){
		$this->callback =& $reference;
	}
}
//class CallbackReference extends Callback {
//	/**
//	 *
//	 * @param $reference
//	 * @param $paramIndex
//	 * @todo implement $paramIndex; param index choose which callback param will be passed to reference
//	 */
//	public function __construct(&$reference, $name = null){
//		$this->callback =& $reference;
//	}
//}
class CallbackParam {}

/**
 * Class representing phpQuery objects.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 * @method phpQueryObject clone() clone()
 * @method phpQueryObject empty() empty()
 * @method phpQueryObject next() next($selector = null)
 * @method phpQueryObject prev() prev($selector = null)
 * @property Int $length
 */
class phpQueryObject
	implements Iterator, Countable, ArrayAccess {
	public $documentID = null;
	/**
	 * DOMDocument class.
	 *
	 * @var DOMDocument
	 */
	public $document = null;
	public $charset = null;
	/**
	 *
	 * @var DOMDocumentWrapper
	 */
	public $documentWrapper = null;
	/**
	 * XPath interface.
	 *
	 * @var DOMXPath
	 */
	public $xpath = null;
	/**
	 * Stack of selected elements.
	 * @TODO refactor to ->nodes
	 * @var array
	 */
	public $elements = array();
	/**
	 * @access private
	 */
	protected $elementsBackup = array();
	/**
	 * @access private
	 */
	protected $previous = null;
	/**
	 * @access private
	 * @TODO deprecate
	 */
	protected $root = array();
	/**
	 * Indicated if doument is just a fragment (no <html> tag).
	 *
	 * Every document is realy a full document, so even documentFragments can
	 * be queried against <html>, but getDocument(id)->htmlOuter() will return
	 * only contents of <body>.
	 *
	 * @var bool
	 */
	public $documentFragment = true;
	/**
	 * Iterator interface helper
	 * @access private
	 */
	protected $elementsInterator = array();
	/**
	 * Iterator interface helper
	 * @access private
	 */
	protected $valid = false;
	/**
	 * Iterator interface helper
	 * @access private
	 */
	protected $current = null;
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function __construct($documentID) {
//		if ($documentID instanceof self)
//			var_dump($documentID->getDocumentID());
		$id = $documentID instanceof self
			? $documentID->getDocumentID()
			: $documentID;
//		var_dump($id);
		if (! isset(phpQuery::$documents[$id] )) {
//			var_dump(phpQuery::$documents);
			throw new Exception("Document with ID '{$id}' isn't loaded. Use phpQuery::newDocument(\$html) or phpQuery::newDocumentFile(\$file) first.");
		}
		$this->documentID = $id;
		$this->documentWrapper =& phpQuery::$documents[$id];
		$this->document =& $this->documentWrapper->document;
		$this->xpath =& $this->documentWrapper->xpath;
		$this->charset =& $this->documentWrapper->charset;
		$this->documentFragment =& $this->documentWrapper->isDocumentFragment;
		// TODO check $this->DOM->documentElement;
//		$this->root = $this->document->documentElement;
		$this->root =& $this->documentWrapper->root;
//		$this->toRoot();
		$this->elements = array($this->root);
	}
	/**
	 *
	 * @access private
	 * @param $attr
	 * @return unknown_type
	 */
	public function __get($attr) {
		switch($attr) {
			// FIXME doesnt work at all ?
			case 'length':
				return $this->size();
			break;
			default:
				return $this->$attr;
		}
	}
	/**
	 * Saves actual object to $var by reference.
	 * Useful when need to break chain.
	 * @param phpQueryObject $var
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function toReference(&$var) {
		return $var = $this;
	}
	public function documentFragment($state = null) {
		if ($state) {
			phpQuery::$documents[$this->getDocumentID()]['documentFragment'] = $state;
			return $this;
		}
		return $this->documentFragment;
	}
	/**
   * @access private
   * @TODO documentWrapper
	 */
	protected function isRoot( $node) {
//		return $node instanceof DOMDOCUMENT || $node->tagName == 'html';
		return $node instanceof DOMDOCUMENT
			|| ($node instanceof DOMELEMENT && $node->tagName == 'html')
			|| $this->root->isSameNode($node);
	}
	/**
   * @access private
	 */
	protected function stackIsRoot() {
		return $this->size() == 1 && $this->isRoot($this->elements[0]);
	}
	/**
	 * Enter description here...
	 * NON JQUERY METHOD
	 *
	 * Watch out, it doesn't creates new instance, can be reverted with end().
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function toRoot() {
		$this->elements = array($this->root);
		return $this;
//		return $this->newInstance(array($this->root));
	}
	/**
	 * Saves object's DocumentID to $var by reference.
	 * <code>
	 * $myDocumentId;
	 * phpQuery::newDocument('<div/>')
	 *     ->getDocumentIDRef($myDocumentId)
	 *     ->find('div')->...
	 * </code>
	 *
	 * @param unknown_type $domId
	 * @see phpQuery::newDocument
	 * @see phpQuery::newDocumentFile
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function getDocumentIDRef(&$documentID) {
		$documentID = $this->getDocumentID();
		return $this;
	}
	/**
	 * Returns object with stack set to document root.
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function getDocument() {
		return phpQuery::getDocument($this->getDocumentID());
	}
	/**
	 *
	 * @return DOMDocument
	 */
	public function getDOMDocument() {
		return $this->document;
	}
	/**
	 * Get object's Document ID.
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function getDocumentID() {
		return $this->documentID;
	}
	/**
	 * Unloads whole document from memory.
	 * CAUTION! None further operations will be possible on this document.
	 * All objects refering to it will be useless.
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function unloadDocument() {
		phpQuery::unloadDocuments($this->getDocumentID());
	}
	public function isHTML() {
		return $this->documentWrapper->isHTML;
	}
	public function isXHTML() {
		return $this->documentWrapper->isXHTML;
	}
	public function isXML() {
		return $this->documentWrapper->isXML;
	}
	/**
	 * Enter description here...
	 *
	 * @link http://docs.jquery.com/Ajax/serialize
	 * @return string
	 */
	public function serialize() {
		return phpQuery::param($this->serializeArray());
	}
	/**
	 * Enter description here...
	 *
	 * @link http://docs.jquery.com/Ajax/serializeArray
	 * @return array
	 */
	public function serializeArray($submit = null) {
		$source = $this->filter('form, input, select, textarea')
			->find('input, select, textarea')
			->andSelf()
			->not('form');
		$return = array();
//		$source->dumpDie();
		foreach($source as $input) {
			$input = phpQuery::pq($input);
			if ($input->is('[disabled]'))
				continue;
			if (!$input->is('[name]'))
				continue;
			if ($input->is('[type=checkbox]') && !$input->is('[checked]'))
				continue;
			// jquery diff
			if ($submit && $input->is('[type=submit]')) {
				if ($submit instanceof DOMELEMENT && ! $input->elements[0]->isSameNode($submit))
					continue;
				else if (is_string($submit) && $input->attr('name') != $submit)
					continue;
			}
			$return[] = array(
				'name' => $input->attr('name'),
				'value' => $input->val(),
			);
		}
		return $return;
	}
	/**
	 * @access private
	 */
	protected function debug($in) {
		if (! phpQuery::$debug )
			return;
		print('<pre>');
		print_r($in);
		// file debug
//		file_put_contents(dirname(__FILE__).'/phpQuery.log', print_r($in, true)."\n", FILE_APPEND);
		// quite handy debug trace
//		if ( is_array($in))
//			print_r(array_slice(debug_backtrace(), 3));
		print("</pre>\n");
	}
	/**
	 * @access private
	 */
	protected function isRegexp($pattern) {
		return in_array(
			$pattern[ mb_strlen($pattern)-1 ],
			array('^','*','$')
		);
	}
	/**
	 * Determines if $char is really a char.
	 *
	 * @param string $char
	 * @return bool
	 * @todo rewrite me to charcode range ! ;)
	 * @access private
	 */
	protected function isChar($char) {
		return extension_loaded('mbstring') && phpQuery::$mbstringSupport
			? mb_eregi('\w', $char)
			: preg_match('@\w@', $char);
	}
	/**
	 * @access private
	 */
	protected function parseSelector($query) {
		// clean spaces
		// TODO include this inside parsing ?
		$query = trim(
			preg_replace('@\s+@', ' ',
				preg_replace('@\s*(>|\\+|~)\s*@', '\\1', $query)
			)
		);
		$queries = array(array());
		if (! $query)
			return $queries;
		$return =& $queries[0];
		$specialChars = array('>',' ');
//		$specialCharsMapping = array('/' => '>');
		$specialCharsMapping = array();
		$strlen = mb_strlen($query);
		$classChars = array('.', '-');
		$pseudoChars = array('-');
		$tagChars = array('*', '|', '-');
		// split multibyte string
		// http://code.google.com/p/phpquery/issues/detail?id=76
		$_query = array();
		for ($i=0; $i<$strlen; $i++)
			$_query[] = mb_substr($query, $i, 1);
		$query = $_query;
		// it works, but i dont like it...
		$i = 0;
		while( $i < $strlen) {
			$c = $query[$i];
			$tmp = '';
			// TAG
			if ($this->isChar($c) || in_array($c, $tagChars)) {
				while(isset($query[$i])
					&& ($this->isChar($query[$i]) || in_array($query[$i], $tagChars))) {
					$tmp .= $query[$i];
					$i++;
				}
				$return[] = $tmp;
			// IDs
			} else if ( $c == '#') {
				$i++;
				while( isset($query[$i]) && ($this->isChar($query[$i]) || $query[$i] == '-')) {
					$tmp .= $query[$i];
					$i++;
				}
				$return[] = '#'.$tmp;
			// SPECIAL CHARS
			} else if (in_array($c, $specialChars)) {
				$return[] = $c;
				$i++;
			// MAPPED SPECIAL MULTICHARS
//			} else if ( $c.$query[$i+1] == '//') {
//				$return[] = ' ';
//				$i = $i+2;
			// MAPPED SPECIAL CHARS
			} else if ( isset($specialCharsMapping[$c])) {
				$return[] = $specialCharsMapping[$c];
				$i++;
			// COMMA
			} else if ( $c == ',') {
				$queries[] = array();
				$return =& $queries[ count($queries)-1 ];
				$i++;
				while( isset($query[$i]) && $query[$i] == ' ')
					$i++;
			// CLASSES
			} else if ($c == '.') {
				while( isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $classChars))) {
					$tmp .= $query[$i];
					$i++;
				}
				$return[] = $tmp;
			// ~ General Sibling Selector
			} else if ($c == '~') {
				$spaceAllowed = true;
				$tmp .= $query[$i++];
				while( isset($query[$i])
					&& ($this->isChar($query[$i])
						|| in_array($query[$i], $classChars)
						|| $query[$i] == '*'
						|| ($query[$i] == ' ' && $spaceAllowed)
					)) {
					if ($query[$i] != ' ')
						$spaceAllowed = false;
					$tmp .= $query[$i];
					$i++;
				}
				$return[] = $tmp;
			// + Adjacent sibling selectors
			} else if ($c == '+') {
				$spaceAllowed = true;
				$tmp .= $query[$i++];
				while( isset($query[$i])
					&& ($this->isChar($query[$i])
						|| in_array($query[$i], $classChars)
						|| $query[$i] == '*'
						|| ($spaceAllowed && $query[$i] == ' ')
					)) {
					if ($query[$i] != ' ')
						$spaceAllowed = false;
					$tmp .= $query[$i];
					$i++;
				}
				$return[] = $tmp;
			// ATTRS
			} else if ($c == '[') {
				$stack = 1;
				$tmp .= $c;
				while( isset($query[++$i])) {
					$tmp .= $query[$i];
					if ( $query[$i] == '[') {
						$stack++;
					} else if ( $query[$i] == ']') {
						$stack--;
						if (! $stack )
							break;
					}
				}
				$return[] = $tmp;
				$i++;
			// PSEUDO CLASSES
			} else if ($c == ':') {
				$stack = 1;
				$tmp .= $query[$i++];
				while( isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $pseudoChars))) {
					$tmp .= $query[$i];
					$i++;
				}
				// with arguments ?
				if ( isset($query[$i]) && $query[$i] == '(') {
					$tmp .= $query[$i];
					$stack = 1;
					while( isset($query[++$i])) {
						$tmp .= $query[$i];
						if ( $query[$i] == '(') {
							$stack++;
						} else if ( $query[$i] == ')') {
							$stack--;
							if (! $stack )
								break;
						}
					}
					$return[] = $tmp;
					$i++;
				} else {
					$return[] = $tmp;
				}
			} else {
				$i++;
			}
		}
		foreach($queries as $k => $q) {
			if (isset($q[0])) {
				if (isset($q[0][0]) && $q[0][0] == ':')
					array_unshift($queries[$k], '*');
				if ($q[0] != '>')
					array_unshift($queries[$k], ' ');
			}
		}
		return $queries;
	}
	/**
	 * Return matched DOM nodes.
	 *
	 * @param int $index
	 * @return array|DOMElement Single DOMElement or array of DOMElement.
	 */
	public function get($index = null, $callback1 = null, $callback2 = null, $callback3 = null) {
		$return = isset($index)
			? (isset($this->elements[$index]) ? $this->elements[$index] : null)
			: $this->elements;
		// pass thou callbacks
		$args = func_get_args();
		$args = array_slice($args, 1);
		foreach($args as $callback) {
			if (is_array($return))
				foreach($return as $k => $v)
					$return[$k] = phpQuery::callbackRun($callback, array($v));
			else
				$return = phpQuery::callbackRun($callback, array($return));
		}
		return $return;
	}
	/**
	 * Return matched DOM nodes.
	 * jQuery difference.
	 *
	 * @param int $index
	 * @return array|string Returns string if $index != null
	 * @todo implement callbacks
	 * @todo return only arrays ?
	 * @todo maybe other name...
	 */
	public function getString($index = null, $callback1 = null, $callback2 = null, $callback3 = null) {
		if ($index)
			$return = $this->eq($index)->text();
		else {
			$return = array();
			for($i = 0; $i < $this->size(); $i++) {
				$return[] = $this->eq($i)->text();
			}
		}
		// pass thou callbacks
		$args = func_get_args();
		$args = array_slice($args, 1);
		foreach($args as $callback) {
			$return = phpQuery::callbackRun($callback, array($return));
		}
		return $return;
	}
	/**
	 * Return matched DOM nodes.
	 * jQuery difference.
	 *
	 * @param int $index
	 * @return array|string Returns string if $index != null
	 * @todo implement callbacks
	 * @todo return only arrays ?
	 * @todo maybe other name...
	 */
	public function getStrings($index = null, $callback1 = null, $callback2 = null, $callback3 = null) {
		if ($index)
			$return = $this->eq($index)->text();
		else {
			$return = array();
			for($i = 0; $i < $this->size(); $i++) {
				$return[] = $this->eq($i)->text();
			}
			// pass thou callbacks
			$args = func_get_args();
			$args = array_slice($args, 1);
		}
		foreach($args as $callback) {
			if (is_array($return))
				foreach($return as $k => $v)
					$return[$k] = phpQuery::callbackRun($callback, array($v));
			else
				$return = phpQuery::callbackRun($callback, array($return));
		}
		return $return;
	}
	/**
	 * Returns new instance of actual class.
	 *
	 * @param array $newStack Optional. Will replace old stack with new and move old one to history.c
	 */
	public function newInstance($newStack = null) {
		$class = get_class($this);
		// support inheritance by passing old object to overloaded constructor
		$new = $class != 'phpQuery'
			? new $class($this, $this->getDocumentID())
			: new phpQueryObject($this->getDocumentID());
		$new->previous = $this;
		if (is_null($newStack)) {
			$new->elements = $this->elements;
			if ($this->elementsBackup)
				$this->elements = $this->elementsBackup;
		} else if (is_string($newStack)) {
			$new->elements = phpQuery::pq($newStack, $this->getDocumentID())->stack();
		} else {
			$new->elements = $newStack;
		}
		return $new;
	}
	/**
	 * Enter description here...
	 *
	 * In the future, when PHP will support XLS 2.0, then we would do that this way:
	 * contains(tokenize(@class, '\s'), "something")
	 * @param unknown_type $class
	 * @param unknown_type $node
	 * @return boolean
	 * @access private
	 */
	protected function matchClasses($class, $node) {
		// multi-class
		if ( mb_strpos($class, '.', 1)) {
			$classes = explode('.', substr($class, 1));
			$classesCount = count( $classes );
			$nodeClasses = explode(' ', $node->getAttribute('class') );
			$nodeClassesCount = count( $nodeClasses );
			if ( $classesCount > $nodeClassesCount )
				return false;
			$diff = count(
				array_diff(
					$classes,
					$nodeClasses
				)
			);
			if (! $diff )
				return true;
		// single-class
		} else {
			return in_array(
				// strip leading dot from class name
				substr($class, 1),
				// get classes for element as array
				explode(' ', $node->getAttribute('class') )
			);
		}
	}
	/**
	 * @access private
	 */
	protected function runQuery($XQuery, $selector = null, $compare = null) {
		if ($compare && ! method_exists($this, $compare))
			return false;
		$stack = array();
		if (! $this->elements)
			$this->debug('Stack empty, skipping...');
//		var_dump($this->elements[0]->nodeType);
		// element, document
		foreach($this->stack(array(1, 9, 13)) as $k => $stackNode) {
			$detachAfter = false;
			// to work on detached nodes we need temporary place them somewhere
			// thats because context xpath queries sucks ;]
			$testNode = $stackNode;
			while ($testNode) {
				if (! $testNode->parentNode && ! $this->isRoot($testNode)) {
					$this->root->appendChild($testNode);
					$detachAfter = $testNode;
					break;
				}
				$testNode = isset($testNode->parentNode)
					? $testNode->parentNode
					: null;
			}
			// XXX tmp ?
			$xpath = $this->documentWrapper->isXHTML
				? $this->getNodeXpath($stackNode, 'html')
				: $this->getNodeXpath($stackNode);
			// FIXME pseudoclasses-only query, support XML
			$query = $XQuery == '//' && $xpath == '/html[1]'
				? '//*'
				: $xpath.$XQuery;
			$this->debug("XPATH: {$query}");
			// run query, get elements
			$nodes = $this->xpath->query($query);
			$this->debug("QUERY FETCHED");
			if (! $nodes->length )
				$this->debug('Nothing found');
			$debug = array();
			foreach($nodes as $node) {
				$matched = false;
				if ( $compare) {
					phpQuery::$debug ?
						$this->debug("Found: ".$this->whois( $node ).", comparing with {$compare}()")
						: null;
					$phpQueryDebug = phpQuery::$debug;
					phpQuery::$debug = false;
					// TODO ??? use phpQuery::callbackRun()
					if (call_user_func_array(array($this, $compare), array($selector, $node)))
						$matched = true;
					phpQuery::$debug = $phpQueryDebug;
				} else {
					$matched = true;
				}
				if ( $matched) {
					if (phpQuery::$debug)
						$debug[] = $this->whois( $node );
					$stack[] = $node;
				}
			}
			if (phpQuery::$debug) {
				$this->debug("Matched ".count($debug).": ".implode(', ', $debug));
			}
			if ($detachAfter)
				$this->root->removeChild($detachAfter);
		}
		$this->elements = $stack;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function find($selectors, $context = null, $noHistory = false) {
		if (!$noHistory)
			// backup last stack /for end()/
			$this->elementsBackup = $this->elements;
		// allow to define context
		// TODO combine code below with phpQuery::pq() context guessing code
		//   as generic function
		if ($context) {
			if (! is_array($context) && $context instanceof DOMELEMENT)
				$this->elements = array($context);
			else if (is_array($context)) {
				$this->elements = array();
				foreach ($context as $c)
					if ($c instanceof DOMELEMENT)
						$this->elements[] = $c;
			} else if ( $context instanceof self )
				$this->elements = $context->elements;
		}
		$queries = $this->parseSelector($selectors);
		$this->debug(array('FIND', $selectors, $queries));
		$XQuery = '';
		// remember stack state because of multi-queries
		$oldStack = $this->elements;
		// here we will be keeping found elements
		$stack = array();
		foreach($queries as $selector) {
			$this->elements = $oldStack;
			$delimiterBefore = false;
			foreach($selector as $s) {
				// TAG
				$isTag = extension_loaded('mbstring') && phpQuery::$mbstringSupport
					? mb_ereg_match('^[\w|\||-]+$', $s) || $s == '*'
					: preg_match('@^[\w|\||-]+$@', $s) || $s == '*';
				if ($isTag) {
					if ($this->isXML()) {
						// namespace support
						if (mb_strpos($s, '|') !== false) {
							$ns = $tag = null;
							list($ns, $tag) = explode('|', $s);
							$XQuery .= "$ns:$tag";
						} else if ($s == '*') {
							$XQuery .= "*";
						} else {
							$XQuery .= "*[local-name()='$s']";
						}
					} else {
						$XQuery .= $s;
					}
				// ID
				} else if ($s[0] == '#') {
					if ($delimiterBefore)
						$XQuery .= '*';
					$XQuery .= "[@id='".substr($s, 1)."']";
				// ATTRIBUTES
				} else if ($s[0] == '[') {
					if ($delimiterBefore)
						$XQuery .= '*';
					// strip side brackets
					$attr = trim($s, '][');
					$execute = false;
					// attr with specifed value
					if (mb_strpos($s, '=')) {
						$value = null;
						list($attr, $value) = explode('=', $attr);
						$value = trim($value, "'\"");
						if ($this->isRegexp($attr)) {
							// cut regexp character
							$attr = substr($attr, 0, -1);
							$execute = true;
							$XQuery .= "[@{$attr}]";
						} else {
							$XQuery .= "[@{$attr}='{$value}']";
						}
					// attr without specified value
					} else {
						$XQuery .= "[@{$attr}]";
					}
					if ($execute) {
						$this->runQuery($XQuery, $s, 'is');
						$XQuery = '';
						if (! $this->length())
							break;
					}
				// CLASSES
				} else if ($s[0] == '.') {
					// TODO use return $this->find("./self::*[contains(concat(\" \",@class,\" \"), \" $class \")]");
					// thx wizDom ;)
					if ($delimiterBefore)
						$XQuery .= '*';
					$XQuery .= '[@class]';
					$this->runQuery($XQuery, $s, 'matchClasses');
					$XQuery = '';
					if (! $this->length() )
						break;
				// ~ General Sibling Selector
				} else if ($s[0] == '~') {
					$this->runQuery($XQuery);
					$XQuery = '';
					$this->elements = $this
						->siblings(
							substr($s, 1)
						)->elements;
					if (! $this->length() )
						break;
				// + Adjacent sibling selectors
				} else if ($s[0] == '+') {
					// TODO /following-sibling::
					$this->runQuery($XQuery);
					$XQuery = '';
					$subSelector = substr($s, 1);
					$subElements = $this->elements;
					$this->elements = array();
					foreach($subElements as $node) {
						// search first DOMElement sibling
						$test = $node->nextSibling;
						while($test && ! ($test instanceof DOMELEMENT))
							$test = $test->nextSibling;
						if ($test && $this->is($subSelector, $test))
							$this->elements[] = $test;
					}
					if (! $this->length() )
						break;
				// PSEUDO CLASSES
				} else if ($s[0] == ':') {
					// TODO optimization for :first :last
					if ($XQuery) {
						$this->runQuery($XQuery);
						$XQuery = '';
					}
					if (! $this->length())
						break;
					$this->pseudoClasses($s);
					if (! $this->length())
						break;
				// DIRECT DESCENDANDS
				} else if ($s == '>') {
					$XQuery .= '/';
					$delimiterBefore = 2;
				// ALL DESCENDANDS
				} else if ($s == ' ') {
					$XQuery .= '//';
					$delimiterBefore = 2;
				// ERRORS
				} else {
					phpQuery::debug("Unrecognized token '$s'");
				}
				$delimiterBefore = $delimiterBefore === 2;
			}
			// run query if any
			if ($XQuery && $XQuery != '//') {
				$this->runQuery($XQuery);
				$XQuery = '';
			}
			foreach($this->elements as $node)
				if (! $this->elementsContainsNode($node, $stack))
					$stack[] = $node;
		}
		$this->elements = $stack;
		return $this->newInstance();
	}
	/**
	 * @todo create API for classes with pseudoselectors
	 * @access private
	 */
	protected function pseudoClasses($class) {
		// TODO clean args parsing ?
		$class = ltrim($class, ':');
		$haveArgs = mb_strpos($class, '(');
		if ($haveArgs !== false) {
			$args = substr($class, $haveArgs+1, -1);
			$class = substr($class, 0, $haveArgs);
		}
		switch($class) {
			case 'even':
			case 'odd':
				$stack = array();
				foreach($this->elements as $i => $node) {
					if ($class == 'even' && ($i%2) == 0)
						$stack[] = $node;
					else if ( $class == 'odd' && $i % 2 )
						$stack[] = $node;
				}
				$this->elements = $stack;
				break;
			case 'eq':
				$k = intval($args);
				$this->elements = isset( $this->elements[$k] )
					? array( $this->elements[$k] )
					: array();
				break;
			case 'gt':
				$this->elements = array_slice($this->elements, $args+1);
				break;
			case 'lt':
				$this->elements = array_slice($this->elements, 0, $args+1);
				break;
			case 'first':
				if (isset($this->elements[0]))
					$this->elements = array($this->elements[0]);
				break;
			case 'last':
				if ($this->elements)
					$this->elements = array($this->elements[count($this->elements)-1]);
				break;
			/*case 'parent':
				$stack = array();
				foreach($this->elements as $node) {
					if ( $node->childNodes->length )
						$stack[] = $node;
				}
				$this->elements = $stack;
				break;*/
			case 'contains':
				$text = trim($args, "\"'");
				$stack = array();
				foreach($this->elements as $node) {
					if (mb_stripos($node->textContent, $text) === false)
						continue;
					$stack[] = $node;
				}
				$this->elements = $stack;
				break;
			case 'not':
				$selector = self::unQuote($args);
				$this->elements = $this->not($selector)->stack();
				break;
			case 'slice':
				// TODO jQuery difference ?
				$args = explode(',',
					str_replace(', ', ',', trim($args, "\"'"))
				);
				$start = $args[0];
				$end = isset($args[1])
					? $args[1]
					: null;
				if ($end > 0)
					$end = $end-$start;
				$this->elements = array_slice($this->elements, $start, $end);
				break;
			case 'has':
				$selector = trim($args, "\"'");
				$stack = array();
				foreach($this->stack(1) as $el) {
					if ($this->find($selector, $el, true)->length)
						$stack[] = $el;
				}
				$this->elements = $stack;
				break;
			case 'submit':
			case 'reset':
				$this->elements = phpQuery::merge(
					$this->map(array($this, 'is'),
						"input[type=$class]", new CallbackParam()
					),
					$this->map(array($this, 'is'),
						"button[type=$class]", new CallbackParam()
					)
				);
			break;
//				$stack = array();
//				foreach($this->elements as $node)
//					if ($node->is('input[type=submit]') || $node->is('button[type=submit]'))
//						$stack[] = $el;
//				$this->elements = $stack;
			case 'input':
				$this->elements = $this->map(
					array($this, 'is'),
					'input', new CallbackParam()
				)->elements;
			break;
			case 'password':
			case 'checkbox':
			case 'radio':
			case 'hidden':
			case 'image':
			case 'file':
				$this->elements = $this->map(
					array($this, 'is'),
					"input[type=$class]", new CallbackParam()
				)->elements;
			break;
			case 'parent':
				$this->elements = $this->map(
					create_function('$node', '
						return $node instanceof DOMELEMENT && $node->childNodes->length
							? $node : null;')
				)->elements;
			break;
			case 'empty':
				$this->elements = $this->map(
					create_function('$node', '
						return $node instanceof DOMELEMENT && $node->childNodes->length
							? null : $node;')
				)->elements;
			break;
			case 'disabled':
			case 'selected':
			case 'checked':
				$this->elements = $this->map(
					array($this, 'is'),
					"[$class]", new CallbackParam()
				)->elements;
			break;
			case 'enabled':
				$this->elements = $this->map(
					create_function('$node', '
						return pq($node)->not(":disabled") ? $node : null;')
				)->elements;
			break;
			case 'header':
				$this->elements = $this->map(
					create_function('$node',
						'$isHeader = isset($node->tagName) && in_array($node->tagName, array(
							"h1", "h2", "h3", "h4", "h5", "h6", "h7"
						));
						return $isHeader
							? $node
							: null;')
				)->elements;
//				$this->elements = $this->map(
//					create_function('$node', '$node = pq($node);
//						return $node->is("h1")
//							|| $node->is("h2")
//							|| $node->is("h3")
//							|| $node->is("h4")
//							|| $node->is("h5")
//							|| $node->is("h6")
//							|| $node->is("h7")
//							? $node
//							: null;')
//				)->elements;
			break;
			case 'only-child':
				$this->elements = $this->map(
					create_function('$node',
						'return pq($node)->siblings()->size() == 0 ? $node : null;')
				)->elements;
			break;
			case 'first-child':
				$this->elements = $this->map(
					create_function('$node', 'return pq($node)->prevAll()->size() == 0 ? $node : null;')
				)->elements;
			break;
			case 'last-child':
				$this->elements = $this->map(
					create_function('$node', 'return pq($node)->nextAll()->size() == 0 ? $node : null;')
				)->elements;
			break;
			case 'nth-child':
				$param = trim($args, "\"'");
				if (! $param)
					break;
					// nth-child(n+b) to nth-child(1n+b)
				if ($param{0} == 'n')
					$param = '1'.$param;
				// :nth-child(index/even/odd/equation)
				if ($param == 'even' || $param == 'odd')
					$mapped = $this->map(
						create_function('$node, $param',
							'$index = pq($node)->prevAll()->size()+1;
							if ($param == "even" && ($index%2) == 0)
								return $node;
							else if ($param == "odd" && $index%2 == 1)
								return $node;
							else
								return null;'),
						new CallbackParam(), $param
					);
				else if (mb_strlen($param) > 1 && $param{1} == 'n')
					// an+b
					$mapped = $this->map(
						create_function('$node, $param',
							'$prevs = pq($node)->prevAll()->size();
							$index = 1+$prevs;
							$b = mb_strlen($param) > 3
								? $param{3}
								: 0;
							$a = $param{0};
							if ($b && $param{2} == "-")
								$b = -$b;
							if ($a > 0) {
								return ($index-$b)%$a == 0
									? $node
									: null;
								phpQuery::debug($a."*".floor($index/$a)."+$b-1 == ".($a*floor($index/$a)+$b-1)." ?= $prevs");
								return $a*floor($index/$a)+$b-1 == $prevs
										? $node
										: null;
							} else if ($a == 0)
								return $index == $b
										? $node
										: null;
							else
								// negative value
								return $index <= $b
										? $node
										: null;
//							if (! $b)
//								return $index%$a == 0
//									? $node
//									: null;
//							else
//								return ($index-$b)%$a == 0
//									? $node
//									: null;
							'),
						new CallbackParam(), $param
					);
				else
					// index
					$mapped = $this->map(
						create_function('$node, $index',
							'$prevs = pq($node)->prevAll()->size();
							if ($prevs && $prevs == $index-1)
								return $node;
							else if (! $prevs && $index == 1)
								return $node;
							else
								return null;'),
						new CallbackParam(), $param
					);
				$this->elements = $mapped->elements;
			break;
			default:
				$this->debug("Unknown pseudoclass '{$class}', skipping...");
		}
	}
	/**
	 * @access private
	 */
	protected function __pseudoClassParam($paramsString) {
		// TODO;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function is($selector, $nodes = null) {
		phpQuery::debug(array("Is:", $selector));
		if (! $selector)
			return false;
		$oldStack = $this->elements;
		$returnArray = false;
		if ($nodes && is_array($nodes)) {
			$this->elements = $nodes;
		} else if ($nodes)
			$this->elements = array($nodes);
		$this->filter($selector, true);
		$stack = $this->elements;
		$this->elements = $oldStack;
		if ($nodes)
			return $stack ? $stack : null;
		return (bool)count($stack);
	}
	/**
	 * Enter description here...
	 * jQuery difference.
	 *
	 * Callback:
	 * - $index int
	 * - $node DOMNode
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @link http://docs.jquery.com/Traversing/filter
	 */
	public function filterCallback($callback, $_skipHistory = false) {
		if (! $_skipHistory) {
			$this->elementsBackup = $this->elements;
			$this->debug("Filtering by callback");
		}
		$newStack = array();
		foreach($this->elements as $index => $node) {
			$result = phpQuery::callbackRun($callback, array($index, $node));
			if (is_null($result) || (! is_null($result) && $result))
				$newStack[] = $node;
		}
		$this->elements = $newStack;
		return $_skipHistory
			? $this
			: $this->newInstance();
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @link http://docs.jquery.com/Traversing/filter
	 */
	public function filter($selectors, $_skipHistory = false) {
		if ($selectors instanceof Callback OR $selectors instanceof Closure)
			return $this->filterCallback($selectors, $_skipHistory);
		if (! $_skipHistory)
			$this->elementsBackup = $this->elements;
		$notSimpleSelector = array(' ', '>', '~', '+', '/');
		if (! is_array($selectors))
			$selectors = $this->parseSelector($selectors);
		if (! $_skipHistory)
			$this->debug(array("Filtering:", $selectors));
		$finalStack = array();
		foreach($selectors as $selector) {
			$stack = array();
			if (! $selector)
				break;
			// avoid first space or /
			if (in_array($selector[0], $notSimpleSelector))
				$selector = array_slice($selector, 1);
			// PER NODE selector chunks
			foreach($this->stack() as $node) {
				$break = false;
				foreach($selector as $s) {
					if (!($node instanceof DOMELEMENT)) {
						// all besides DOMElement
						if ( $s[0] == '[') {
							$attr = trim($s, '[]');
							if ( mb_strpos($attr, '=')) {
								list( $attr, $val ) = explode('=', $attr);
								if ($attr == 'nodeType' && $node->nodeType != $val)
									$break = true;
							}
						} else
							$break = true;
					} else {
						// DOMElement only
						// ID
						if ( $s[0] == '#') {
							if ( $node->getAttribute('id') != substr($s, 1) )
								$break = true;
						// CLASSES
						} else if ( $s[0] == '.') {
							if (! $this->matchClasses( $s, $node ) )
								$break = true;
						// ATTRS
						} else if ( $s[0] == '[') {
							// strip side brackets
							$attr = trim($s, '[]');
							if (mb_strpos($attr, '=')) {
								list($attr, $val) = explode('=', $attr);
								$val = self::unQuote($val);
								if ($attr == 'nodeType') {
									if ($val != $node->nodeType)
										$break = true;
								} else if ($this->isRegexp($attr)) {
									$val = extension_loaded('mbstring') && phpQuery::$mbstringSupport
										? quotemeta(trim($val, '"\''))
										: preg_quote(trim($val, '"\''), '@');
									// switch last character
									switch( substr($attr, -1)) {
										// quotemeta used insted of preg_quote
										// http://code.google.com/p/phpquery/issues/detail?id=76
										case '^':
											$pattern = '^'.$val;
											break;
										case '*':
											$pattern = '.*'.$val.'.*';
											break;
										case '$':
											$pattern = '.*'.$val.'$';
											break;
									}
									// cut last character
									$attr = substr($attr, 0, -1);
									$isMatch = extension_loaded('mbstring') && phpQuery::$mbstringSupport
										? mb_ereg_match($pattern, $node->getAttribute($attr))
										: preg_match("@{$pattern}@", $node->getAttribute($attr));
									if (! $isMatch)
										$break = true;
								} else if ($node->getAttribute($attr) != $val)
									$break = true;
							} else if (! $node->hasAttribute($attr))
								$break = true;
						// PSEUDO CLASSES
						} else if ( $s[0] == ':') {
							// skip
						// TAG
						} else if (trim($s)) {
							if ($s != '*') {
								// TODO namespaces
								if (isset($node->tagName)) {
									if ($node->tagName != $s)
										$break = true;
								} else if ($s == 'html' && ! $this->isRoot($node))
									$break = true;
							}
						// AVOID NON-SIMPLE SELECTORS
						} else if (in_array($s, $notSimpleSelector)) {
							$break = true;
							$this->debug(array('Skipping non simple selector', $selector));
						}
					}
					if ($break)
						break;
				}
				// if element passed all chunks of selector - add it to new stack
				if (! $break )
					$stack[] = $node;
			}
			$tmpStack = $this->elements;
			$this->elements = $stack;
			// PER ALL NODES selector chunks
			foreach($selector as $s)
				// PSEUDO CLASSES
				if ($s[0] == ':')
					$this->pseudoClasses($s);
			foreach($this->elements as $node)
				// XXX it should be merged without duplicates
				// but jQuery doesnt do that
				$finalStack[] = $node;
			$this->elements = $tmpStack;
		}
		$this->elements = $finalStack;
		if ($_skipHistory) {
			return $this;
		} else {
			$this->debug("Stack length after filter(): ".count($finalStack));
			return $this->newInstance();
		}
	}
	/**
	 *
	 * @param $value
	 * @return unknown_type
	 * @TODO implement in all methods using passed parameters
	 */
	protected static function unQuote($value) {
		return $value[0] == '\'' || $value[0] == '"'
			? substr($value, 1, -1)
			: $value;
	}
	/**
	 * Enter description here...
	 *
	 * @link http://docs.jquery.com/Ajax/load
	 * @return phpQuery|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @todo Support $selector
	 */
	public function load($url, $data = null, $callback = null) {
		if ($data && ! is_array($data)) {
			$callback = $data;
			$data = null;
		}
		if (mb_strpos($url, ' ') !== false) {
			$matches = null;
			if (extension_loaded('mbstring') && phpQuery::$mbstringSupport)
				mb_ereg('^([^ ]+) (.*)$', $url, $matches);
			else
				preg_match('^([^ ]+) (.*)$', $url, $matches);
			$url = $matches[1];
			$selector = $matches[2];
			// FIXME this sucks, pass as callback param
			$this->_loadSelector = $selector;
		}
		$ajax = array(
			'url' => $url,
			'type' => $data ? 'POST' : 'GET',
			'data' => $data,
			'complete' => $callback,
			'success' => array($this, '__loadSuccess')
		);
		phpQuery::ajax($ajax);
		return $this;
	}
	/**
	 * @access private
	 * @param $html
	 * @return unknown_type
	 */
	public function __loadSuccess($html) {
		if ($this->_loadSelector) {
			$html = phpQuery::newDocument($html)->find($this->_loadSelector);
			unset($this->_loadSelector);
		}
		foreach($this->stack(1) as $node) {
			phpQuery::pq($node, $this->getDocumentID())
				->markup($html);
		}
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQuery|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @todo
	 */
	public function css() {
		// TODO
		return $this;
	}
	/**
	 * @todo
	 *
	 */
	public function show(){
		// TODO
		return $this;
	}
	/**
	 * @todo
	 *
	 */
	public function hide(){
		// TODO
		return $this;
	}
	/**
	 * Trigger a type of event on every matched element.
	 *
	 * @param unknown_type $type
	 * @param unknown_type $data
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @TODO support more than event in $type (space-separated)
	 */
	public function trigger($type, $data = array()) {
		foreach($this->elements as $node)
			phpQueryEvents::trigger($this->getDocumentID(), $type, $data, $node);
		return $this;
	}
	/**
	 * This particular method triggers all bound event handlers on an element (for a specific event type) WITHOUT executing the browsers default actions.
	 *
	 * @param unknown_type $type
	 * @param unknown_type $data
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @TODO
	 */
	public function triggerHandler($type, $data = array()) {
		// TODO;
	}
	/**
	 * Binds a handler to one or more events (like click) for each matched element.
	 * Can also bind custom events.
	 *
	 * @param unknown_type $type
	 * @param unknown_type $data Optional
	 * @param unknown_type $callback
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @TODO support '!' (exclusive) events
	 * @TODO support more than event in $type (space-separated)
	 */
	public function bind($type, $data, $callback = null) {
		// TODO check if $data is callable, not using is_callable
		if (! isset($callback)) {
			$callback = $data;
			$data = null;
		}
		foreach($this->elements as $node)
			phpQueryEvents::add($this->getDocumentID(), $node, $type, $data, $callback);
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $type
	 * @param unknown_type $callback
	 * @return unknown
	 * @TODO namespace events
	 * @TODO support more than event in $type (space-separated)
	 */
	public function unbind($type = null, $callback = null) {
		foreach($this->elements as $node)
			phpQueryEvents::remove($this->getDocumentID(), $node, $type, $callback);
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function change($callback = null) {
		if ($callback)
			return $this->bind('change', $callback);
		return $this->trigger('change');
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function submit($callback = null) {
		if ($callback)
			return $this->bind('submit', $callback);
		return $this->trigger('submit');
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function click($callback = null) {
		if ($callback)
			return $this->bind('click', $callback);
		return $this->trigger('click');
	}
	/**
	 * Enter description here...
	 *
	 * @param String|phpQuery
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function wrapAllOld($wrapper) {
		$wrapper = pq($wrapper)->_clone();
		if (! $wrapper->length() || ! $this->length() )
			return $this;
		$wrapper->insertBefore($this->elements[0]);
		$deepest = $wrapper->elements[0];
		while($deepest->firstChild && $deepest->firstChild instanceof DOMELEMENT)
			$deepest = $deepest->firstChild;
		pq($deepest)->append($this);
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * TODO testme...
	 * @param String|phpQuery
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function wrapAll($wrapper) {
		if (! $this->length())
			return $this;
		return phpQuery::pq($wrapper, $this->getDocumentID())
			->clone()
			->insertBefore($this->get(0))
			->map(array($this, '___wrapAllCallback'))
			->append($this);
	}
  /**
   *
	 * @param $node
	 * @return unknown_type
	 * @access private
   */
	public function ___wrapAllCallback($node) {
		$deepest = $node;
		while($deepest->firstChild && $deepest->firstChild instanceof DOMELEMENT)
			$deepest = $deepest->firstChild;
		return $deepest;
	}
	/**
	 * Enter description here...
	 * NON JQUERY METHOD
	 *
	 * @param String|phpQuery
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function wrapAllPHP($codeBefore, $codeAfter) {
		return $this
			->slice(0, 1)
				->beforePHP($codeBefore)
			->end()
			->slice(-1)
				->afterPHP($codeAfter)
			->end();
	}
	/**
	 * Enter description here...
	 *
	 * @param String|phpQuery
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function wrap($wrapper) {
		foreach($this->stack() as $node)
			phpQuery::pq($node, $this->getDocumentID())->wrapAll($wrapper);
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @param String|phpQuery
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function wrapPHP($codeBefore, $codeAfter) {
		foreach($this->stack() as $node)
			phpQuery::pq($node, $this->getDocumentID())->wrapAllPHP($codeBefore, $codeAfter);
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @param String|phpQuery
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function wrapInner($wrapper) {
		foreach($this->stack() as $node)
			phpQuery::pq($node, $this->getDocumentID())->contents()->wrapAll($wrapper);
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @param String|phpQuery
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function wrapInnerPHP($codeBefore, $codeAfter) {
		foreach($this->stack(1) as $node)
			phpQuery::pq($node, $this->getDocumentID())->contents()
				->wrapAllPHP($codeBefore, $codeAfter);
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @testme Support for text nodes
	 */
	public function contents() {
		$stack = array();
		foreach($this->stack(1) as $el) {
			// FIXME (fixed) http://code.google.com/p/phpquery/issues/detail?id=56
//			if (! isset($el->childNodes))
//				continue;
			foreach($el->childNodes as $node) {
				$stack[] = $node;
			}
		}
		return $this->newInstance($stack);
	}
	/**
	 * Enter description here...
	 *
	 * jQuery difference.
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function contentsUnwrap() {
		foreach($this->stack(1) as $node) {
			if (! $node->parentNode )
				continue;
			$childNodes = array();
			// any modification in DOM tree breaks childNodes iteration, so cache them first
			foreach($node->childNodes as $chNode )
				$childNodes[] = $chNode;
			foreach($childNodes as $chNode )
//				$node->parentNode->appendChild($chNode);
				$node->parentNode->insertBefore($chNode, $node);
			$node->parentNode->removeChild($node);
		}
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * jQuery difference.
	 */
	public function switchWith($markup) {
		$markup = pq($markup, $this->getDocumentID());
		$content = null;
		foreach($this->stack(1) as $node) {
			pq($node)
				->contents()->toReference($content)->end()
				->replaceWith($markup->clone()->append($content));
		}
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function eq($num) {
		$oldStack = $this->elements;
		$this->elementsBackup = $this->elements;
		$this->elements = array();
		if ( isset($oldStack[$num]) )
			$this->elements[] = $oldStack[$num];
		return $this->newInstance();
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function size() {
		return count($this->elements);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @deprecated Use length as attribute
	 */
	public function length() {
		return $this->size();
	}
	public function count() {
		return $this->size();
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @todo $level
	 */
	public function end($level = 1) {
//		$this->elements = array_pop( $this->history );
//		return $this;
//		$this->previous->DOM = $this->DOM;
//		$this->previous->XPath = $this->XPath;
		return $this->previous
			? $this->previous
			: $this;
	}
	/**
	 * Enter description here...
	 * Normal use ->clone() .
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @access private
	 */
	public function _clone() {
		$newStack = array();
		//pr(array('copy... ', $this->whois()));
		//$this->dumpHistory('copy');
		$this->elementsBackup = $this->elements;
		foreach($this->elements as $node) {
			$newStack[] = $node->cloneNode(true);
		}
		$this->elements = $newStack;
		return $this->newInstance();
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function replaceWithPHP($code) {
		return $this->replaceWith(phpQuery::php($code));
	}
	/**
	 * Enter description here...
	 *
	 * @param String|phpQuery $content
	 * @link http://docs.jquery.com/Manipulation/replaceWith#content
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function replaceWith($content) {
		return $this->after($content)->remove();
	}
	/**
	 * Enter description here...
	 *
	 * @param String $selector
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @todo this works ?
	 */
	public function replaceAll($selector) {
		foreach(phpQuery::pq($selector, $this->getDocumentID()) as $node)
			phpQuery::pq($node, $this->getDocumentID())
				->after($this->_clone())
				->remove();
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function remove($selector = null) {
		$loop = $selector
			? $this->filter($selector)->elements
			: $this->elements;
		foreach($loop as $node) {
			if (! $node->parentNode )
				continue;
			if (isset($node->tagName))
				$this->debug("Removing '{$node->tagName}'");
			$node->parentNode->removeChild($node);
			// Mutation event
			$event = new DOMEvent(array(
				'target' => $node,
				'type' => 'DOMNodeRemoved'
			));
			phpQueryEvents::trigger($this->getDocumentID(),
				$event->type, array($event), $node
			);
		}
		return $this;
	}
	protected function markupEvents($newMarkup, $oldMarkup, $node) {
		if ($node->tagName == 'textarea' && $newMarkup != $oldMarkup) {
			$event = new DOMEvent(array(
				'target' => $node,
				'type' => 'change'
			));
			phpQueryEvents::trigger($this->getDocumentID(),
				$event->type, array($event), $node
			);
		}
	}
	/**
	 * jQuey difference
	 *
	 * @param $markup
	 * @return unknown_type
	 * @TODO trigger change event for textarea
	 */
	public function markup($markup = null, $callback1 = null, $callback2 = null, $callback3 = null) {
		$args = func_get_args();
		if ($this->documentWrapper->isXML)
			return call_user_func_array(array($this, 'xml'), $args);
		else
			return call_user_func_array(array($this, 'html'), $args);
	}
	/**
	 * jQuey difference
	 *
	 * @param $markup
	 * @return unknown_type
	 */
	public function markupOuter($callback1 = null, $callback2 = null, $callback3 = null) {
		$args = func_get_args();
		if ($this->documentWrapper->isXML)
			return call_user_func_array(array($this, 'xmlOuter'), $args);
		else
			return call_user_func_array(array($this, 'htmlOuter'), $args);
	}
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $html
	 * @return string|phpQuery|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @TODO force html result
	 */
	public function html($html = null, $callback1 = null, $callback2 = null, $callback3 = null) {
		if (isset($html)) {
			// INSERT
			$nodes = $this->documentWrapper->import($html);
			$this->empty();
			foreach($this->stack(1) as $alreadyAdded => $node) {
				// for now, limit events for textarea
				if (($this->isXHTML() || $this->isHTML()) && $node->tagName == 'textarea')
					$oldHtml = pq($node, $this->getDocumentID())->markup();
				foreach($nodes as $newNode) {
					$node->appendChild($alreadyAdded
						? $newNode->cloneNode(true)
						: $newNode
					);
				}
				// for now, limit events for textarea
				if (($this->isXHTML() || $this->isHTML()) && $node->tagName == 'textarea')
					$this->markupEvents($html, $oldHtml, $node);
			}
			return $this;
		} else {
			// FETCH
			$return = $this->documentWrapper->markup($this->elements, true);
			$args = func_get_args();
			foreach(array_slice($args, 1) as $callback) {
				$return = phpQuery::callbackRun($callback, array($return));
			}
			return $return;
		}
	}
	/**
	 * @TODO force xml result
	 */
	public function xml($xml = null, $callback1 = null, $callback2 = null, $callback3 = null) {
		$args = func_get_args();
		return call_user_func_array(array($this, 'html'), $args);
	}
	/**
	 * Enter description here...
	 * @TODO force html result
	 *
	 * @return String
	 */
	public function htmlOuter($callback1 = null, $callback2 = null, $callback3 = null) {
		$markup = $this->documentWrapper->markup($this->elements);
		// pass thou callbacks
		$args = func_get_args();
		foreach($args as $callback) {
			$markup = phpQuery::callbackRun($callback, array($markup));
		}
		return $markup;
	}
	/**
	 * @TODO force xml result
	 */
	public function xmlOuter($callback1 = null, $callback2 = null, $callback3 = null) {
		$args = func_get_args();
		return call_user_func_array(array($this, 'htmlOuter'), $args);
	}
	public function __toString() {
		return $this->markupOuter();
	}
	/**
	 * Just like html(), but returns markup with VALID (dangerous) PHP tags.
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @todo support returning markup with PHP tags when called without param
	 */
	public function php($code = null) {
		return $this->markupPHP($code);
	}
	/**
	 * Enter description here...
	 * 
	 * @param $code
	 * @return unknown_type
	 */
	public function markupPHP($code = null) {
		return isset($code)
			? $this->markup(phpQuery::php($code))
			: phpQuery::markupToPHP($this->markup());
	}
	/**
	 * Enter description here...
	 * 
	 * @param $code
	 * @return unknown_type
	 */
	public function markupOuterPHP() {
		return phpQuery::markupToPHP($this->markupOuter());
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function children($selector = null) {
		$stack = array();
		foreach($this->stack(1) as $node) {
//			foreach($node->getElementsByTagName('*') as $newNode) {
			foreach($node->childNodes as $newNode) {
				if ($newNode->nodeType != 1)
					continue;
				if ($selector && ! $this->is($selector, $newNode))
					continue;
				if ($this->elementsContainsNode($newNode, $stack))
					continue;
				$stack[] = $newNode;
			}
		}
		$this->elementsBackup = $this->elements;
		$this->elements = $stack;
		return $this->newInstance();
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function ancestors($selector = null) {
		return $this->children( $selector );
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function append( $content) {
		return $this->insert($content, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function appendPHP( $content) {
		return $this->insert("<php><!-- {$content} --></php>", 'append');
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function appendTo( $seletor) {
		return $this->insert($seletor, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function prepend( $content) {
		return $this->insert($content, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @todo accept many arguments, which are joined, arrays maybe also
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function prependPHP( $content) {
		return $this->insert("<php><!-- {$content} --></php>", 'prepend');
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function prependTo( $seletor) {
		return $this->insert($seletor, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function before($content) {
		return $this->insert($content, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function beforePHP( $content) {
		return $this->insert("<php><!-- {$content} --></php>", 'before');
	}
	/**
	 * Enter description here...
	 *
	 * @param String|phpQuery
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function insertBefore( $seletor) {
		return $this->insert($seletor, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function after( $content) {
		return $this->insert($content, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function afterPHP( $content) {
		return $this->insert("<php><!-- {$content} --></php>", 'after');
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function insertAfter( $seletor) {
		return $this->insert($seletor, __FUNCTION__);
	}
	/**
	 * Internal insert method. Don't use it.
	 *
	 * @param unknown_type $target
	 * @param unknown_type $type
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @access private
	 */
	public function insert($target, $type) {
		$this->debug("Inserting data with '{$type}'");
		$to = false;
		switch( $type) {
			case 'appendTo':
			case 'prependTo':
			case 'insertBefore':
			case 'insertAfter':
				$to = true;
		}
		switch(gettype($target)) {
			case 'string':
				$insertFrom = $insertTo = array();
				if ($to) {
					// INSERT TO
					$insertFrom = $this->elements;
					if (phpQuery::isMarkup($target)) {
						// $target is new markup, import it
						$insertTo = $this->documentWrapper->import($target);
					// insert into selected element
					} else {
						// $tagret is a selector
						$thisStack = $this->elements;
						$this->toRoot();
						$insertTo = $this->find($target)->elements;
						$this->elements = $thisStack;
					}
				} else {
					// INSERT FROM
					$insertTo = $this->elements;
					$insertFrom = $this->documentWrapper->import($target);
				}
				break;
			case 'object':
				$insertFrom = $insertTo = array();
				// phpQuery
				if ($target instanceof self) {
					if ($to) {
						$insertTo = $target->elements;
						if ($this->documentFragment && $this->stackIsRoot())
							// get all body children
//							$loop = $this->find('body > *')->elements;
							// TODO test it, test it hard...
//							$loop = $this->newInstance($this->root)->find('> *')->elements;
							$loop = $this->root->childNodes;
						else
							$loop = $this->elements;
						// import nodes if needed
						$insertFrom = $this->getDocumentID() == $target->getDocumentID()
							? $loop
							: $target->documentWrapper->import($loop);
					} else {
						$insertTo = $this->elements;
						if ( $target->documentFragment && $target->stackIsRoot() )
							// get all body children
//							$loop = $target->find('body > *')->elements;
							$loop = $target->root->childNodes;
						else
							$loop = $target->elements;
						// import nodes if needed
						$insertFrom = $this->getDocumentID() == $target->getDocumentID()
							? $loop
							: $this->documentWrapper->import($loop);
					}
				// DOMNODE
				} elseif ($target instanceof DOMNODE) {
					// import node if needed
//					if ( $target->ownerDocument != $this->DOM )
//						$target = $this->DOM->importNode($target, true);
					if ( $to) {
						$insertTo = array($target);
						if ($this->documentFragment && $this->stackIsRoot())
							// get all body children
							$loop = $this->root->childNodes;
//							$loop = $this->find('body > *')->elements;
						else
							$loop = $this->elements;
						foreach($loop as $fromNode)
							// import nodes if needed
							$insertFrom[] = ! $fromNode->ownerDocument->isSameNode($target->ownerDocument)
								? $target->ownerDocument->importNode($fromNode, true)
								: $fromNode;
					} else {
						// import node if needed
						if (! $target->ownerDocument->isSameNode($this->document))
							$target = $this->document->importNode($target, true);
						$insertTo = $this->elements;
						$insertFrom[] = $target;
					}
				}
				break;
		}
		phpQuery::debug("From ".count($insertFrom)."; To ".count($insertTo)." nodes");
		foreach($insertTo as $insertNumber => $toNode) {
			// we need static relative elements in some cases
			switch( $type) {
				case 'prependTo':
				case 'prepend':
					$firstChild = $toNode->firstChild;
					break;
				case 'insertAfter':
				case 'after':
					$nextSibling = $toNode->nextSibling;
					break;
			}
			foreach($insertFrom as $fromNode) {
				// clone if inserted already before
				$insert = $insertNumber
					? $fromNode->cloneNode(true)
					: $fromNode;
				switch($type) {
					case 'appendTo':
					case 'append':
//						$toNode->insertBefore(
//							$fromNode,
//							$toNode->lastChild->nextSibling
//						);
						$toNode->appendChild($insert);
						$eventTarget = $insert;
						break;
					case 'prependTo':
					case 'prepend':
						$toNode->insertBefore(
							$insert,
							$firstChild
						);
						break;
					case 'insertBefore':
					case 'before':
						if (! $toNode->parentNode)
							throw new Exception("No parentNode, can't do {$type}()");
						else
							$toNode->parentNode->insertBefore(
								$insert,
								$toNode
							);
						break;
					case 'insertAfter':
					case 'after':
						if (! $toNode->parentNode)
							throw new Exception("No parentNode, can't do {$type}()");
						else
							$toNode->parentNode->insertBefore(
								$insert,
								$nextSibling
							);
						break;
				}
				// Mutation event
				$event = new DOMEvent(array(
					'target' => $insert,
					'type' => 'DOMNodeInserted'
				));
				phpQueryEvents::trigger($this->getDocumentID(),
					$event->type, array($event), $insert
				);
			}
		}
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return Int
	 */
	public function index($subject) {
		$index = -1;
		$subject = $subject instanceof phpQueryObject
			? $subject->elements[0]
			: $subject;
		foreach($this->newInstance() as $k => $node) {
			if ($node->isSameNode($subject))
				$index = $k;
		}
		return $index;
	}
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $start
	 * @param unknown_type $end
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @testme
	 */
	public function slice($start, $end = null) {
//		$last = count($this->elements)-1;
//		$end = $end
//			? min($end, $last)
//			: $last;
//		if ($start < 0)
//			$start = $last+$start;
//		if ($start > $last)
//			return array();
		if ($end > 0)
			$end = $end-$start;
		return $this->newInstance(
			array_slice($this->elements, $start, $end)
		);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function reverse() {
		$this->elementsBackup = $this->elements;
		$this->elements = array_reverse($this->elements);
		return $this->newInstance();
	}
	/**
	 * Return joined text content.
	 * @return String
	 */
	public function text($text = null, $callback1 = null, $callback2 = null, $callback3 = null) {
		if (isset($text))
			return $this->html(htmlspecialchars($text));
		$args = func_get_args();
		$args = array_slice($args, 1);
		$return = '';
		foreach($this->elements as $node) {
			$text = $node->textContent;
			if (count($this->elements) > 1 && $text)
				$text .= "\n";
			foreach($args as $callback) {
				$text = phpQuery::callbackRun($callback, array($text));
			}
			$return .= $text;
		}
		return $return;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function plugin($class, $file = null) {
		phpQuery::plugin($class, $file);
		return $this;
	}
	/**
	 * Deprecated, use $pq->plugin() instead.
	 *
	 * @deprecated
	 * @param $class
	 * @param $file
	 * @return unknown_type
	 */
	public static function extend($class, $file = null) {
		return $this->plugin($class, $file);
	}
	/**
	 *
	 * @access private
	 * @param $method
	 * @param $args
	 * @return unknown_type
	 */
	public function __call($method, $args) {
		$aliasMethods = array('clone', 'empty');
		if (isset(phpQuery::$extendMethods[$method])) {
			array_unshift($args, $this);
			return phpQuery::callbackRun(
				phpQuery::$extendMethods[$method], $args
			);
		} else if (isset(phpQuery::$pluginsMethods[$method])) {
			array_unshift($args, $this);
			$class = phpQuery::$pluginsMethods[$method];
			$realClass = "phpQueryObjectPlugin_$class";
			$return = call_user_func_array(
				array($realClass, $method),
				$args
			);
			// XXX deprecate ?
			return is_null($return)
				? $this
				: $return;
		} else if (in_array($method, $aliasMethods)) {
			return call_user_func_array(array($this, '_'.$method), $args);
		} else
			throw new Exception("Method '{$method}' doesnt exist");
	}
	/**
	 * Safe rename of next().
	 *
	 * Use it ONLY when need to call next() on an iterated object (in same time).
	 * Normaly there is no need to do such thing ;)
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @access private
	 */
	public function _next($selector = null) {
		return $this->newInstance(
			$this->getElementSiblings('nextSibling', $selector, true)
		);
	}
	/**
	 * Use prev() and next().
	 *
	 * @deprecated
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @access private
	 */
	public function _prev($selector = null) {
		return $this->prev($selector);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function prev($selector = null) {
		return $this->newInstance(
			$this->getElementSiblings('previousSibling', $selector, true)
		);
	}
	/**
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @todo
	 */
	public function prevAll($selector = null) {
		return $this->newInstance(
			$this->getElementSiblings('previousSibling', $selector)
		);
	}
	/**
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @todo FIXME: returns source elements insted of next siblings
	 */
	public function nextAll($selector = null) {
		return $this->newInstance(
			$this->getElementSiblings('nextSibling', $selector)
		);
	}
	/**
	 * @access private
	 */
	protected function getElementSiblings($direction, $selector = null, $limitToOne = false) {
		$stack = array();
		$count = 0;
		foreach($this->stack() as $node) {
			$test = $node;
			while( isset($test->{$direction}) && $test->{$direction}) {
				$test = $test->{$direction};
				if (! $test instanceof DOMELEMENT)
					continue;
				$stack[] = $test;
				if ($limitToOne)
					break;
			}
		}
		if ($selector) {
			$stackOld = $this->elements;
			$this->elements = $stack;
			$stack = $this->filter($selector, true)->stack();
			$this->elements = $stackOld;
		}
		return $stack;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function siblings($selector = null) {
		$stack = array();
		$siblings = array_merge(
			$this->getElementSiblings('previousSibling', $selector),
			$this->getElementSiblings('nextSibling', $selector)
		);
		foreach($siblings as $node) {
			if (! $this->elementsContainsNode($node, $stack))
				$stack[] = $node;
		}
		return $this->newInstance($stack);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function not($selector = null) {
		if (is_string($selector))
			phpQuery::debug(array('not', $selector));
		else
			phpQuery::debug('not');
		$stack = array();
		if ($selector instanceof self || $selector instanceof DOMNODE) {
			foreach($this->stack() as $node) {
				if ($selector instanceof self) {
					$matchFound = false;
					foreach($selector->stack() as $notNode) {
						if ($notNode->isSameNode($node))
							$matchFound = true;
					}
					if (! $matchFound)
						$stack[] = $node;
				} else if ($selector instanceof DOMNODE) {
					if (! $selector->isSameNode($node))
						$stack[] = $node;
				} else {
					if (! $this->is($selector))
						$stack[] = $node;
				}
			}
		} else {
			$orgStack = $this->stack();
			$matched = $this->filter($selector, true)->stack();
//			$matched = array();
//			// simulate OR in filter() instead of AND 5y
//			foreach($this->parseSelector($selector) as $s) {
//				$matched = array_merge($matched,
//					$this->filter(array($s))->stack()
//				);
//			}
			foreach($orgStack as $node)
				if (! $this->elementsContainsNode($node, $matched))
					$stack[] = $node;
		}
		return $this->newInstance($stack);
	}
	/**
	 * Enter description here...
	 *
	 * @param string|phpQueryObject
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function add($selector = null) {
		if (! $selector)
			return $this;
		$stack = array();
		$this->elementsBackup = $this->elements;
		$found = phpQuery::pq($selector, $this->getDocumentID());
		$this->merge($found->elements);
		return $this->newInstance();
	}
	/**
	 * @access private
	 */
	protected function merge() {
		foreach(func_get_args() as $nodes)
			foreach($nodes as $newNode )
				if (! $this->elementsContainsNode($newNode) )
					$this->elements[] = $newNode;
	}
	/**
	 * @access private
	 * TODO refactor to stackContainsNode
	 */
	protected function elementsContainsNode($nodeToCheck, $elementsStack = null) {
		$loop = ! is_null($elementsStack)
			? $elementsStack
			: $this->elements;
		foreach($loop as $node) {
			if ( $node->isSameNode( $nodeToCheck ) )
				return true;
		}
		return false;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function parent($selector = null) {
		$stack = array();
		foreach($this->elements as $node )
			if ( $node->parentNode && ! $this->elementsContainsNode($node->parentNode, $stack) )
				$stack[] = $node->parentNode;
		$this->elementsBackup = $this->elements;
		$this->elements = $stack;
		if ( $selector )
			$this->filter($selector, true);
		return $this->newInstance();
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function parents($selector = null) {
		$stack = array();
		if (! $this->elements )
			$this->debug('parents() - stack empty');
		foreach($this->elements as $node) {
			$test = $node;
			while( $test->parentNode) {
				$test = $test->parentNode;
				if ($this->isRoot($test))
					break;
				if (! $this->elementsContainsNode($test, $stack)) {
					$stack[] = $test;
					continue;
				}
			}
		}
		$this->elementsBackup = $this->elements;
		$this->elements = $stack;
		if ( $selector )
			$this->filter($selector, true);
		return $this->newInstance();
	}
	/**
	 * Internal stack iterator.
	 *
	 * @access private
	 */
	public function stack($nodeTypes = null) {
		if (!isset($nodeTypes))
			return $this->elements;
		if (!is_array($nodeTypes))
			$nodeTypes = array($nodeTypes);
		$return = array();
		foreach($this->elements as $node) {
			if (in_array($node->nodeType, $nodeTypes))
				$return[] = $node;
		}
		return $return;
	}
	// TODO phpdoc; $oldAttr is result of hasAttribute, before any changes
	protected function attrEvents($attr, $oldAttr, $oldValue, $node) {
		// skip events for XML documents
		if (! $this->isXHTML() && ! $this->isHTML())
			return;
		$event = null;
		// identify
		$isInputValue = $node->tagName == 'input'
			&& (
				in_array($node->getAttribute('type'),
					array('text', 'password', 'hidden'))
				|| !$node->getAttribute('type')
				 );
		$isRadio = $node->tagName == 'input'
			&& $node->getAttribute('type') == 'radio';
		$isCheckbox = $node->tagName == 'input'
			&& $node->getAttribute('type') == 'checkbox';
		$isOption = $node->tagName == 'option';
		if ($isInputValue && $attr == 'value' && $oldValue != $node->getAttribute($attr)) {
			$event = new DOMEvent(array(
				'target' => $node,
				'type' => 'change'
			));
		} else if (($isRadio || $isCheckbox) && $attr == 'checked' && (
				// check
				(! $oldAttr && $node->hasAttribute($attr))
				// un-check
				|| (! $node->hasAttribute($attr) && $oldAttr)
			)) {
			$event = new DOMEvent(array(
				'target' => $node,
				'type' => 'change'
			));
		} else if ($isOption && $node->parentNode && $attr == 'selected' && (
				// select
				(! $oldAttr && $node->hasAttribute($attr))
				// un-select
				|| (! $node->hasAttribute($attr) && $oldAttr)
			)) {
			$event = new DOMEvent(array(
				'target' => $node->parentNode,
				'type' => 'change'
			));
		}
		if ($event) {
			phpQueryEvents::trigger($this->getDocumentID(),
				$event->type, array($event), $node
			);
		}
	}
	public function attr($attr = null, $value = null) {
		foreach($this->stack(1) as $node) {
			if (! is_null($value)) {
				$loop = $attr == '*'
					? $this->getNodeAttrs($node)
					: array($attr);
				foreach($loop as $a) {
					$oldValue = $node->getAttribute($a);
					$oldAttr = $node->hasAttribute($a);
					// TODO raises an error when charset other than UTF-8
					// while document's charset is also not UTF-8
					@$node->setAttribute($a, $value);
					$this->attrEvents($a, $oldAttr, $oldValue, $node);
				}
			} else if ($attr == '*') {
				// jQuery difference
				$return = array();
				foreach($node->attributes as $n => $v)
					$return[$n] = $v->value;
				return $return;
			} else
				return $node->hasAttribute($attr)
					? $node->getAttribute($attr)
					: null;
		}
		return is_null($value)
			? '' : $this;
	}
	/**
	 * @access private
	 */
	protected function getNodeAttrs($node) {
		$return = array();
		foreach($node->attributes as $n => $o)
			$return[] = $n;
		return $return;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @todo check CDATA ???
	 */
	public function attrPHP($attr, $code) {
		if (! is_null($code)) {
			$value = '<'.'?php '.$code.' ?'.'>';
			// TODO tempolary solution
			// http://code.google.com/p/phpquery/issues/detail?id=17
//			if (function_exists('mb_detect_encoding') && mb_detect_encoding($value) == 'ASCII')
//				$value	= mb_convert_encoding($value, 'UTF-8', 'HTML-ENTITIES');
		}
		foreach($this->stack(1) as $node) {
			if (! is_null($code)) {
//				$attrNode = $this->DOM->createAttribute($attr);
				$node->setAttribute($attr, $value);
//				$attrNode->value = $value;
//				$node->appendChild($attrNode);
			} else if ( $attr == '*') {
				// jQuery diff
				$return = array();
				foreach($node->attributes as $n => $v)
					$return[$n] = $v->value;
				return $return;
			} else
				return $node->getAttribute($attr);
		}
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function removeAttr($attr) {
		foreach($this->stack(1) as $node) {
			$loop = $attr == '*'
				? $this->getNodeAttrs($node)
				: array($attr);
			foreach($loop as $a) {
				$oldValue = $node->getAttribute($a);
				$node->removeAttribute($a);
				$this->attrEvents($a, $oldValue, null, $node);
			}
		}
		return $this;
	}
	/**
	 * Return form element value.
	 *
	 * @return String Fields value.
	 */
	public function val($val = null) {
		if (! isset($val)) {
			if ($this->eq(0)->is('select')) {
					$selected = $this->eq(0)->find('option[selected=selected]');
					if ($selected->is('[value]'))
						return $selected->attr('value');
					else
						return $selected->text();
			} else if ($this->eq(0)->is('textarea'))
					return $this->eq(0)->markup();
				else
					return $this->eq(0)->attr('value');
		} else {
			$_val = null;
			foreach($this->stack(1) as $node) {
				$node = pq($node, $this->getDocumentID());
				if (is_array($val) && in_array($node->attr('type'), array('checkbox', 'radio'))) {
					$isChecked = in_array($node->attr('value'), $val)
							|| in_array($node->attr('name'), $val);
					if ($isChecked)
						$node->attr('checked', 'checked');
					else
						$node->removeAttr('checked');
				} else if ($node->get(0)->tagName == 'select') {
					if (! isset($_val)) {
						$_val = array();
						if (! is_array($val))
							$_val = array((string)$val);
						else
							foreach($val as $v)
								$_val[] = $v;
					}
					foreach($node['option']->stack(1) as $option) {
						$option = pq($option, $this->getDocumentID());
						$selected = false;
						// XXX: workaround for string comparsion, see issue #96
						// http://code.google.com/p/phpquery/issues/detail?id=96
						$selected = is_null($option->attr('value'))
							? in_array($option->markup(), $_val)
							: in_array($option->attr('value'), $_val);
//						$optionValue = $option->attr('value');
//						$optionText = $option->text();
//						$optionTextLenght = mb_strlen($optionText);
//						foreach($_val as $v)
//							if ($optionValue == $v)
//								$selected = true;
//							else if ($optionText == $v && $optionTextLenght == mb_strlen($v))
//								$selected = true;
						if ($selected)
							$option->attr('selected', 'selected');
						else
							$option->removeAttr('selected');
					}
				} else if ($node->get(0)->tagName == 'textarea')
					$node->markup($val);
				else
					$node->attr('value', $val);
			}
		}
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function andSelf() {
		if ( $this->previous )
			$this->elements = array_merge($this->elements, $this->previous->elements);
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function addClass( $className) {
		if (! $className)
			return $this;
		foreach($this->stack(1) as $node) {
			if (! $this->is(".$className", $node))
				$node->setAttribute(
					'class',
					trim($node->getAttribute('class').' '.$className)
				);
		}
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function addClassPHP( $className) {
		foreach($this->stack(1) as $node) {
				$classes = $node->getAttribute('class');
				$newValue = $classes
					? $classes.' <'.'?php '.$className.' ?'.'>'
					: '<'.'?php '.$className.' ?'.'>';
				$node->setAttribute('class', $newValue);
		}
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @param	string	$className
	 * @return	bool
	 */
	public function hasClass($className) {
		foreach($this->stack(1) as $node) {
			if ( $this->is(".$className", $node))
				return true;
		}
		return false;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function removeClass($className) {
		foreach($this->stack(1) as $node) {
			$classes = explode( ' ', $node->getAttribute('class'));
			if ( in_array($className, $classes)) {
				$classes = array_diff($classes, array($className));
				if ( $classes )
					$node->setAttribute('class', implode(' ', $classes));
				else
					$node->removeAttribute('class');
			}
		}
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function toggleClass($className) {
		foreach($this->stack(1) as $node) {
			if ( $this->is( $node, '.'.$className ))
				$this->removeClass($className);
			else
				$this->addClass($className);
		}
		return $this;
	}
	/**
	 * Proper name without underscore (just ->empty()) also works.
	 *
	 * Removes all child nodes from the set of matched elements.
	 *
	 * Example:
	 * pq("p")._empty()
	 *
	 * HTML:
	 * <p>Hello, <span>Person</span> <a href="#">and person</a></p>
	 *
	 * Result:
	 * [ <p></p> ]
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @access private
	 */
	public function _empty() {
		foreach($this->stack(1) as $node) {
			// thx to 'dave at dgx dot cz'
			$node->nodeValue = '';
		}
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @param array|string $callback Expects $node as first param, $index as second
	 * @param array $scope External variables passed to callback. Use compact('varName1', 'varName2'...) and extract($scope)
	 * @param array $arg1 Will ba passed as third and futher args to callback.
	 * @param array $arg2 Will ba passed as fourth and futher args to callback, and so on...
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function each($callback, $param1 = null, $param2 = null, $param3 = null) {
		$paramStructure = null;
		if (func_num_args() > 1) {
			$paramStructure = func_get_args();
			$paramStructure = array_slice($paramStructure, 1);
		}
		foreach($this->elements as $v)
			phpQuery::callbackRun($callback, array($v), $paramStructure);
		return $this;
	}
	/**
	 * Run callback on actual object.
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function callback($callback, $param1 = null, $param2 = null, $param3 = null) {
		$params = func_get_args();
		$params[0] = $this;
		phpQuery::callbackRun($callback, $params);
		return $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @todo add $scope and $args as in each() ???
	 */
	public function map($callback, $param1 = null, $param2 = null, $param3 = null) {
//		$stack = array();
////		foreach($this->newInstance() as $node) {
//		foreach($this->newInstance() as $node) {
//			$result = call_user_func($callback, $node);
//			if ($result)
//				$stack[] = $result;
//		}
		$params = func_get_args();
		array_unshift($params, $this->elements);
		return $this->newInstance(
			call_user_func_array(array('phpQuery', 'map'), $params)
//			phpQuery::map($this->elements, $callback)
		);
	}
	/**
	 * Enter description here...
	 * 
	 * @param <type> $key
	 * @param <type> $value
	 */
	public function data($key, $value = null) {
		if (! isset($value)) {
			// TODO? implement specific jQuery behavior od returning parent values
			// is child which we look up doesn't exist
			return phpQuery::data($this->get(0), $key, $value, $this->getDocumentID());
		} else {
			foreach($this as $node)
				phpQuery::data($node, $key, $value, $this->getDocumentID());
			return $this;
		}
	}
	/**
	 * Enter description here...
	 * 
	 * @param <type> $key
	 */
	public function removeData($key) {
		foreach($this as $node)
			phpQuery::removeData($node, $key, $this->getDocumentID());
		return $this;
	}
	// INTERFACE IMPLEMENTATIONS

	// ITERATOR INTERFACE
	/**
   * @access private
	 */
	public function rewind(){
		$this->debug('iterating foreach');
//		phpQuery::selectDocument($this->getDocumentID());
		$this->elementsBackup = $this->elements;
		$this->elementsInterator = $this->elements;
		$this->valid = isset( $this->elements[0] )
			? 1 : 0;
// 		$this->elements = $this->valid
// 			? array($this->elements[0])
// 			: array();
		$this->current = 0;
	}
	/**
   * @access private
	 */
	public function current(){
		return $this->elementsInterator[ $this->current ];
	}
	/**
   * @access private
	 */
	public function key(){
		return $this->current;
	}
	/**
	 * Double-function method.
	 *
	 * First: main iterator interface method.
	 * Second: Returning next sibling, alias for _next().
	 *
	 * Proper functionality is choosed automagicaly.
	 *
	 * @see phpQueryObject::_next()
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public function next($cssSelector = null){
//		if ($cssSelector || $this->valid)
//			return $this->_next($cssSelector);
		$this->valid = isset( $this->elementsInterator[ $this->current+1 ] )
			? true
			: false;
		if (! $this->valid && $this->elementsInterator) {
			$this->elementsInterator = null;
		} else if ($this->valid) {
			$this->current++;
		} else {
			return $this->_next($cssSelector);
		}
	}
	/**
   * @access private
	 */
	public function valid(){
		return $this->valid;
	}
	// ITERATOR INTERFACE END
	// ARRAYACCESS INTERFACE
	/**
   * @access private
	 */
	public function offsetExists($offset) {
		return $this->find($offset)->size() > 0;
	}
	/**
   * @access private
	 */
	public function offsetGet($offset) {
		return $this->find($offset);
	}
	/**
   * @access private
	 */
	public function offsetSet($offset, $value) {
//		$this->find($offset)->replaceWith($value);
		$this->find($offset)->html($value);
	}
	/**
   * @access private
	 */
	public function offsetUnset($offset) {
		// empty
		throw new Exception("Can't do unset, use array interface only for calling queries and replacing HTML.");
	}
	// ARRAYACCESS INTERFACE END
	/**
	 * Returns node's XPath.
	 *
	 * @param unknown_type $oneNode
	 * @return string
	 * @TODO use native getNodePath is avaible
	 * @access private
	 */
	protected function getNodeXpath($oneNode = null, $namespace = null) {
		$return = array();
		$loop = $oneNode
			? array($oneNode)
			: $this->elements;
//		if ($namespace)
//			$namespace .= ':';
		foreach($loop as $node) {
			if ($node instanceof DOMDOCUMENT) {
				$return[] = '';
				continue;
			}
			$xpath = array();
			while(! ($node instanceof DOMDOCUMENT)) {
				$i = 1;
				$sibling = $node;
				while($sibling->previousSibling) {
					$sibling = $sibling->previousSibling;
					$isElement = $sibling instanceof DOMELEMENT;
					if ($isElement && $sibling->tagName == $node->tagName)
						$i++;
				}
				$xpath[] = $this->isXML()
					? "*[local-name()='{$node->tagName}'][{$i}]"
					: "{$node->tagName}[{$i}]";
				$node = $node->parentNode;
			}
			$xpath = join('/', array_reverse($xpath));
			$return[] = '/'.$xpath;
		}
		return $oneNode
			? $return[0]
			: $return;
	}
	// HELPERS
	public function whois($oneNode = null) {
		$return = array();
		$loop = $oneNode
			? array( $oneNode )
			: $this->elements;
		foreach($loop as $node) {
			if (isset($node->tagName)) {
				$tag = in_array($node->tagName, array('php', 'js'))
					? strtoupper($node->tagName)
					: $node->tagName;
				$return[] = $tag
					.($node->getAttribute('id')
						? '#'.$node->getAttribute('id'):'')
					.($node->getAttribute('class')
						? '.'.join('.', split(' ', $node->getAttribute('class'))):'')
					.($node->getAttribute('name')
						? '[name="'.$node->getAttribute('name').'"]':'')
					.($node->getAttribute('value') && strpos($node->getAttribute('value'), '<'.'?php') === false
						? '[value="'.substr(str_replace("\n", '', $node->getAttribute('value')), 0, 15).'"]':'')
					.($node->getAttribute('value') && strpos($node->getAttribute('value'), '<'.'?php') !== false
						? '[value=PHP]':'')
					.($node->getAttribute('selected')
						? '[selected]':'')
					.($node->getAttribute('checked')
						? '[checked]':'')
				;
			} else if ($node instanceof DOMTEXT) {
				if (trim($node->textContent))
					$return[] = 'Text:'.substr(str_replace("\n", ' ', $node->textContent), 0, 15);
			} else {

			}
		}
		return $oneNode && isset($return[0])
			? $return[0]
			: $return;
	}
	/**
	 * Dump htmlOuter and preserve chain. Usefull for debugging.
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 *
	 */
	public function dump() {
		print 'DUMP #'.(phpQuery::$dumpCount++).' ';
		$debug = phpQuery::$debug;
		phpQuery::$debug = false;
//		print __FILE__.':'.__LINE__."\n";
		var_dump($this->htmlOuter());
		return $this;
	}
	public function dumpWhois() {
		print 'DUMP #'.(phpQuery::$dumpCount++).' ';
		$debug = phpQuery::$debug;
		phpQuery::$debug = false;
//		print __FILE__.':'.__LINE__."\n";
		var_dump('whois', $this->whois());
		phpQuery::$debug = $debug;
		return $this;
	}
	public function dumpLength() {
		print 'DUMP #'.(phpQuery::$dumpCount++).' ';
		$debug = phpQuery::$debug;
		phpQuery::$debug = false;
//		print __FILE__.':'.__LINE__."\n";
		var_dump('length', $this->length());
		phpQuery::$debug = $debug;
		return $this;
	}
	public function dumpTree($html = true, $title = true) {
		$output = $title
			? 'DUMP #'.(phpQuery::$dumpCount++)." \n" : '';
		$debug = phpQuery::$debug;
		phpQuery::$debug = false;
		foreach($this->stack() as $node)
			$output .= $this->__dumpTree($node);
		phpQuery::$debug = $debug;
		print $html
			? nl2br(str_replace(' ', '&nbsp;', $output))
			: $output;
		return $this;
	}
	private function __dumpTree($node, $intend = 0) {
		$whois = $this->whois($node);
		$return = '';
		if ($whois)
			$return .= str_repeat(' - ', $intend).$whois."\n";
		if (isset($node->childNodes))
			foreach($node->childNodes as $chNode)
				$return .= $this->__dumpTree($chNode, $intend+1);
		return $return;
	}
	/**
	 * Dump htmlOuter and stop script execution. Usefull for debugging.
	 *
	 */
	public function dumpDie() {
		print __FILE__.':'.__LINE__;
		var_dump($this->htmlOuter());
		die();
	}
}


// -- Multibyte Compatibility functions ---------------------------------------
// http://svn.iphonewebdev.com/lace/lib/mb_compat.php

/**
 *  mb_internal_encoding()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_internal_encoding'))
{
	function mb_internal_encoding($enc) {return true; }
}

/**
 *  mb_regex_encoding()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_regex_encoding'))
{
	function mb_regex_encoding($enc) {return true; }
}

/**
 *  mb_strlen()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_strlen'))
{
	function mb_strlen($str)
	{
		return strlen($str);
	}
}

/**
 *  mb_strpos()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_strpos'))
{
	function mb_strpos($haystack, $needle, $offset=0)
	{
		return strpos($haystack, $needle, $offset);
	}
}
/**
 *  mb_stripos()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_stripos'))
{
	function mb_stripos($haystack, $needle, $offset=0)
	{
		return stripos($haystack, $needle, $offset);
	}
}

/**
 *  mb_substr()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_substr'))
{
	function mb_substr($str, $start, $length=0)
	{
		return substr($str, $start, $length);
	}
}

/**
 *  mb_substr_count()
 *
 *  Included for mbstring pseudo-compatability.
 */
if (!function_exists('mb_substr_count'))
{
	function mb_substr_count($haystack, $needle)
	{
		return substr_count($haystack, $needle);
	}
}


/**
 * Static namespace for phpQuery functions.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 */
abstract class phpQuery {
	/**
	 * XXX: Workaround for mbstring problems 
	 * 
	 * @var bool
	 */
	public static $mbstringSupport = true;
	public static $debug = false;
	public static $documents = array();
	public static $defaultDocumentID = null;
//	public static $defaultDoctype = 'html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"';
	/**
	 * Applies only to HTML.
	 *
	 * @var unknown_type
	 */
	public static $defaultDoctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">';
	public static $defaultCharset = 'UTF-8';
	/**
	 * Static namespace for plugins.
	 *
	 * @var object
	 */
	public static $plugins = array();
	/**
	 * List of loaded plugins.
	 *
	 * @var unknown_type
	 */
	public static $pluginsLoaded = array();
	public static $pluginsMethods = array();
	public static $pluginsStaticMethods = array();
	public static $extendMethods = array();
	/**
	 * @TODO implement
	 */
	public static $extendStaticMethods = array();
	/**
	 * Hosts allowed for AJAX connections.
	 * Dot '.' means $_SERVER['HTTP_HOST'] (if any).
	 *
	 * @var array
	 */
	public static $ajaxAllowedHosts = array(
		'.'
	);
	/**
	 * AJAX settings.
	 *
	 * @var array
	 * XXX should it be static or not ?
	 */
	public static $ajaxSettings = array(
		'url' => '',//TODO
		'global' => true,
		'type' => "GET",
		'timeout' => null,
		'contentType' => "application/x-www-form-urlencoded",
		'processData' => true,
//		'async' => true,
		'data' => null,
		'username' => null,
		'password' => null,
		'accepts' => array(
			'xml' => "application/xml, text/xml",
			'html' => "text/html",
			'script' => "text/javascript, application/javascript",
			'json' => "application/json, text/javascript",
			'text' => "text/plain",
			'_default' => "*/*"
		)
	);
	public static $lastModified = null;
	public static $active = 0;
	public static $dumpCount = 0;
	/**
	 * Multi-purpose function.
	 * Use pq() as shortcut.
	 *
	 * In below examples, $pq is any result of pq(); function.
	 *
	 * 1. Import markup into existing document (without any attaching):
	 * - Import into selected document:
	 *   pq('<div/>')				// DOESNT accept text nodes at beginning of input string !
	 * - Import into document with ID from $pq->getDocumentID():
	 *   pq('<div/>', $pq->getDocumentID())
	 * - Import into same document as DOMNode belongs to:
	 *   pq('<div/>', DOMNode)
	 * - Import into document from phpQuery object:
	 *   pq('<div/>', $pq)
	 *
	 * 2. Run query:
	 * - Run query on last selected document:
	 *   pq('div.myClass')
	 * - Run query on document with ID from $pq->getDocumentID():
	 *   pq('div.myClass', $pq->getDocumentID())
	 * - Run query on same document as DOMNode belongs to and use node(s)as root for query:
	 *   pq('div.myClass', DOMNode)
	 * - Run query on document from phpQuery object
	 *   and use object's stack as root node(s) for query:
	 *   pq('div.myClass', $pq)
	 *
	 * @param string|DOMNode|DOMNodeList|array	$arg1	HTML markup, CSS Selector, DOMNode or array of DOMNodes
	 * @param string|phpQueryObject|DOMNode	$context	DOM ID from $pq->getDocumentID(), phpQuery object (determines also query root) or DOMNode (determines also query root)
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery|QueryTemplatesPhpQuery|false
   * phpQuery object or false in case of error.
	 */
	public static function pq($arg1, $context = null) {
		if ($arg1 instanceof DOMNODE && ! isset($context)) {
			foreach(phpQuery::$documents as $documentWrapper) {
				$compare = $arg1 instanceof DOMDocument
					? $arg1 : $arg1->ownerDocument;
				if ($documentWrapper->document->isSameNode($compare))
					$context = $documentWrapper->id;
			}
		}
		if (! $context) {
			$domId = self::$defaultDocumentID;
			if (! $domId)
				throw new Exception("Can't use last created DOM, because there isn't any. Use phpQuery::newDocument() first.");
//		} else if (is_object($context) && ($context instanceof PHPQUERY || is_subclass_of($context, 'phpQueryObject')))
		} else if (is_object($context) && $context instanceof phpQueryObject)
			$domId = $context->getDocumentID();
		else if ($context instanceof DOMDOCUMENT) {
			$domId = self::getDocumentID($context);
			if (! $domId) {
				//throw new Exception('Orphaned DOMDocument');
				$domId = self::newDocument($context)->getDocumentID();
			}
		} else if ($context instanceof DOMNODE) {
			$domId = self::getDocumentID($context);
			if (! $domId) {
				throw new Exception('Orphaned DOMNode');
//				$domId = self::newDocument($context->ownerDocument);
			}
		} else
			$domId = $context;
		if ($arg1 instanceof phpQueryObject) {
//		if (is_object($arg1) && (get_class($arg1) == 'phpQueryObject' || $arg1 instanceof PHPQUERY || is_subclass_of($arg1, 'phpQueryObject'))) {
			/**
			 * Return $arg1 or import $arg1 stack if document differs:
			 * pq(pq('<div/>'))
			 */
			if ($arg1->getDocumentID() == $domId)
				return $arg1;
			$class = get_class($arg1);
			// support inheritance by passing old object to overloaded constructor
			$phpQuery = $class != 'phpQuery'
				? new $class($arg1, $domId)
				: new phpQueryObject($domId);
			$phpQuery->elements = array();
			foreach($arg1->elements as $node)
				$phpQuery->elements[] = $phpQuery->document->importNode($node, true);
			return $phpQuery;
		} else if ($arg1 instanceof DOMNODE || (is_array($arg1) && isset($arg1[0]) && $arg1[0] instanceof DOMNODE)) {
			/*
			 * Wrap DOM nodes with phpQuery object, import into document when needed:
			 * pq(array($domNode1, $domNode2))
			 */
			$phpQuery = new phpQueryObject($domId);
			if (!($arg1 instanceof DOMNODELIST) && ! is_array($arg1))
				$arg1 = array($arg1);
			$phpQuery->elements = array();
			foreach($arg1 as $node) {
				$sameDocument = $node->ownerDocument instanceof DOMDOCUMENT
					&& ! $node->ownerDocument->isSameNode($phpQuery->document);
				$phpQuery->elements[] = $sameDocument
					? $phpQuery->document->importNode($node, true)
					: $node;
			}
			return $phpQuery;
		} else if (self::isMarkup($arg1)) {
			/**
			 * Import HTML:
			 * pq('<div/>')
			 */
			$phpQuery = new phpQueryObject($domId);
			return $phpQuery->newInstance(
				$phpQuery->documentWrapper->import($arg1)
			);
		} else {
			/**
			 * Run CSS query:
			 * pq('div.myClass')
			 */
			$phpQuery = new phpQueryObject($domId);
//			if ($context && ($context instanceof PHPQUERY || is_subclass_of($context, 'phpQueryObject')))
			if ($context && $context instanceof phpQueryObject)
				$phpQuery->elements = $context->elements;
			else if ($context && $context instanceof DOMNODELIST) {
				$phpQuery->elements = array();
				foreach($context as $node)
					$phpQuery->elements[] = $node;
			} else if ($context && $context instanceof DOMNODE)
				$phpQuery->elements = array($context);
			return $phpQuery->find($arg1);
		}
	}
	/**
	 * Sets default document to $id. Document has to be loaded prior
	 * to using this method.
	 * $id can be retrived via getDocumentID() or getDocumentIDRef().
	 *
	 * @param unknown_type $id
	 */
	public static function selectDocument($id) {
		$id = self::getDocumentID($id);
		self::debug("Selecting document '$id' as default one");
		self::$defaultDocumentID = self::getDocumentID($id);
	}
	/**
	 * Returns document with id $id or last used as phpQueryObject.
	 * $id can be retrived via getDocumentID() or getDocumentIDRef().
	 * Chainable.
	 *
	 * @see phpQuery::selectDocument()
	 * @param unknown_type $id
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function getDocument($id = null) {
		if ($id)
			phpQuery::selectDocument($id);
		else
			$id = phpQuery::$defaultDocumentID;
		return new phpQueryObject($id);
	}
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocument($markup = null, $contentType = null) {
		if (! $markup)
			$markup = '';
		$documentID = phpQuery::createDocumentWrapper($markup, $contentType);
		return new phpQueryObject($documentID);
	}
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentHTML($markup = null, $charset = null) {
		$contentType = $charset
			? ";charset=$charset"
			: '';
		return self::newDocument($markup, "text/html{$contentType}");
	}
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentXML($markup = null, $charset = null) {
		$contentType = $charset
			? ";charset=$charset"
			: '';
		return self::newDocument($markup, "text/xml{$contentType}");
	}
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentXHTML($markup = null, $charset = null) {
		$contentType = $charset
			? ";charset=$charset"
			: '';
		return self::newDocument($markup, "application/xhtml+xml{$contentType}");
	}
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentPHP($markup = null, $contentType = "text/html") {
		// TODO pass charset to phpToMarkup if possible (use DOMDocumentWrapper function)
		$markup = phpQuery::phpToMarkup($markup, self::$defaultCharset);
		return self::newDocument($markup, $contentType);
	}
	public static function phpToMarkup($php, $charset = 'utf-8') {
		$regexes = array(
			'@(<(?!\\?)(?:[^>]|\\?>)+\\w+\\s*=\\s*)(\')([^\']*)<'.'?php?(.*?)(?:\\?>)([^\']*)\'@s',
			'@(<(?!\\?)(?:[^>]|\\?>)+\\w+\\s*=\\s*)(")([^"]*)<'.'?php?(.*?)(?:\\?>)([^"]*)"@s',
		);
		foreach($regexes as $regex)
			while (preg_match($regex, $php, $matches)) {
				$php = preg_replace_callback(
					$regex,
//					create_function('$m, $charset = "'.$charset.'"',
//						'return $m[1].$m[2]
//							.htmlspecialchars("<"."?php".$m[4]."?".">", ENT_QUOTES|ENT_NOQUOTES, $charset)
//							.$m[5].$m[2];'
//					),
					array('phpQuery', '_phpToMarkupCallback'),
					$php
				);
			}
		$regex = '@(^|>[^<]*)+?(<\?php(.*?)(\?>))@s';
//preg_match_all($regex, $php, $matches);
//var_dump($matches);
		$php = preg_replace($regex, '\\1<php><!-- \\3 --></php>', $php);
		return $php;
	}
	public static function _phpToMarkupCallback($php, $charset = 'utf-8') {
		return $m[1].$m[2]
			.htmlspecialchars("<"."?php".$m[4]."?".">", ENT_QUOTES|ENT_NOQUOTES, $charset)
			.$m[5].$m[2];
	}
	public static function _markupToPHPCallback($m) {
		return "<"."?php ".htmlspecialchars_decode($m[1])." ?".">";
	}
	/**
	 * Converts document markup containing PHP code generated by phpQuery::php()
	 * into valid (executable) PHP code syntax.
	 *
	 * @param string|phpQueryObject $content
	 * @return string PHP code.
	 */
	public static function markupToPHP($content) {
		if ($content instanceof phpQueryObject)
			$content = $content->markupOuter();
		/* <php>...</php> to <?php...? > */
		$content = preg_replace_callback(
			'@<php>\s*<!--(.*?)-->\s*</php>@s',
//			create_function('$m',
//				'return "<'.'?php ".htmlspecialchars_decode($m[1])." ?'.'>";'
//			),
			array('phpQuery', '_markupToPHPCallback'),
			$content
		);
		/* <node attr='< ?php ? >'> extra space added to save highlighters */
		$regexes = array(
			'@(<(?!\\?)(?:[^>]|\\?>)+\\w+\\s*=\\s*)(\')([^\']*)(?:&lt;|%3C)\\?(?:php)?(.*?)(?:\\?(?:&gt;|%3E))([^\']*)\'@s',
			'@(<(?!\\?)(?:[^>]|\\?>)+\\w+\\s*=\\s*)(")([^"]*)(?:&lt;|%3C)\\?(?:php)?(.*?)(?:\\?(?:&gt;|%3E))([^"]*)"@s',
		);
		foreach($regexes as $regex)
			while (preg_match($regex, $content))
				$content = preg_replace_callback(
					$regex,
					create_function('$m',
						'return $m[1].$m[2].$m[3]."<?php "
							.str_replace(
								array("%20", "%3E", "%09", "&#10;", "&#9;", "%7B", "%24", "%7D", "%22", "%5B", "%5D"),
								array(" ", ">", "	", "\n", "	", "{", "$", "}", \'"\', "[", "]"),
								htmlspecialchars_decode($m[4])
							)
							." ?>".$m[5].$m[2];'
					),
					$content
				);
		return $content;
	}
	/**
	 * Creates new document from file $file.
	 * Chainable.
	 *
	 * @param string $file URLs allowed. See File wrapper page at php.net for more supported sources.
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentFile($file, $contentType = null) {
		$documentID = self::createDocumentWrapper(
			file_get_contents($file), $contentType
		);
		return new phpQueryObject($documentID);
	}
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentFileHTML($file, $charset = null) {
		$contentType = $charset
			? ";charset=$charset"
			: '';
		return self::newDocumentFile($file, "text/html{$contentType}");
	}
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentFileXML($file, $charset = null) {
		$contentType = $charset
			? ";charset=$charset"
			: '';
		return self::newDocumentFile($file, "text/xml{$contentType}");
	}
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentFileXHTML($file, $charset = null) {
		$contentType = $charset
			? ";charset=$charset"
			: '';
		return self::newDocumentFile($file, "application/xhtml+xml{$contentType}");
	}
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentFilePHP($file, $contentType = null) {
		return self::newDocumentPHP(file_get_contents($file), $contentType);
	}
	/**
	 * Reuses existing DOMDocument object.
	 * Chainable.
	 *
	 * @param $document DOMDocument
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @TODO support DOMDocument
	 */
	public static function loadDocument($document) {
		// TODO
		die('TODO loadDocument');
	}
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $html
	 * @param unknown_type $domId
	 * @return unknown New DOM ID
	 * @todo support PHP tags in input
	 * @todo support passing DOMDocument object from self::loadDocument
	 */
	protected static function createDocumentWrapper($html, $contentType = null, $documentID = null) {
		if (function_exists('domxml_open_mem'))
			throw new Exception("Old PHP4 DOM XML extension detected. phpQuery won't work until this extension is enabled.");
//		$id = $documentID
//			? $documentID
//			: md5(microtime());
		$document = null;
		if ($html instanceof DOMDOCUMENT) {
			if (self::getDocumentID($html)) {
				// document already exists in phpQuery::$documents, make a copy
				$document = clone $html;
			} else {
				// new document, add it to phpQuery::$documents
				$wrapper = new DOMDocumentWrapper($html, $contentType, $documentID);
			}
		} else {
			$wrapper = new DOMDocumentWrapper($html, $contentType, $documentID);
		}
//		$wrapper->id = $id;
		// bind document
		phpQuery::$documents[$wrapper->id] = $wrapper;
		// remember last loaded document
		phpQuery::selectDocument($wrapper->id);
		return $wrapper->id;
	}
	/**
	 * Extend class namespace.
	 *
	 * @param string|array $target
	 * @param array $source
	 * @TODO support string $source
	 * @return unknown_type
	 */
	public static function extend($target, $source) {
		switch($target) {
			case 'phpQueryObject':
				$targetRef = &self::$extendMethods;
				$targetRef2 = &self::$pluginsMethods;
				break;
			case 'phpQuery':
				$targetRef = &self::$extendStaticMethods;
				$targetRef2 = &self::$pluginsStaticMethods;
				break;
			default:
				throw new Exception("Unsupported \$target type");
		}
		if (is_string($source))
			$source = array($source => $source);
		foreach($source as $method => $callback) {
			if (isset($targetRef[$method])) {
//				throw new Exception
				self::debug("Duplicate method '{$method}', can\'t extend '{$target}'");
				continue;
			}
			if (isset($targetRef2[$method])) {
//				throw new Exception
				self::debug("Duplicate method '{$method}' from plugin '{$targetRef2[$method]}',"
					." can\'t extend '{$target}'");
				continue;
			}
			$targetRef[$method] = $callback;
		}
		return true;
	}
	/**
	 * Extend phpQuery with $class from $file.
	 *
	 * @param string $class Extending class name. Real class name can be prepended phpQuery_.
	 * @param string $file Filename to include. Defaults to "{$class}.php".
	 */
	public static function plugin($class, $file = null) {
		// TODO $class checked agains phpQuery_$class
//		if (strpos($class, 'phpQuery') === 0)
//			$class = substr($class, 8);
		if (in_array($class, self::$pluginsLoaded))
			return true;
		if (! $file)
			$file = $class.'.php';
		$objectClassExists = class_exists('phpQueryObjectPlugin_'.$class);
		$staticClassExists = class_exists('phpQueryPlugin_'.$class);
		if (! $objectClassExists && ! $staticClassExists)
			require_once($file);
		self::$pluginsLoaded[] = $class;
		// static methods
		if (class_exists('phpQueryPlugin_'.$class)) {
			$realClass = 'phpQueryPlugin_'.$class;
			$vars = get_class_vars($realClass);
			$loop = isset($vars['phpQueryMethods'])
				&& ! is_null($vars['phpQueryMethods'])
				? $vars['phpQueryMethods']
				: get_class_methods($realClass);
			foreach($loop as $method) {
				if ($method == '__initialize')
					continue;
				if (! is_callable(array($realClass, $method)))
					continue;
				if (isset(self::$pluginsStaticMethods[$method])) {
					throw new Exception("Duplicate method '{$method}' from plugin '{$c}' conflicts with same method from plugin '".self::$pluginsStaticMethods[$method]."'");
					return;
				}
				self::$pluginsStaticMethods[$method] = $class;
			}
			if (method_exists($realClass, '__initialize'))
				call_user_func_array(array($realClass, '__initialize'), array());
		}
		// object methods
		if (class_exists('phpQueryObjectPlugin_'.$class)) {
			$realClass = 'phpQueryObjectPlugin_'.$class;
			$vars = get_class_vars($realClass);
			$loop = isset($vars['phpQueryMethods'])
				&& ! is_null($vars['phpQueryMethods'])
				? $vars['phpQueryMethods']
				: get_class_methods($realClass);
			foreach($loop as $method) {
				if (! is_callable(array($realClass, $method)))
					continue;
				if (isset(self::$pluginsMethods[$method])) {
					throw new Exception("Duplicate method '{$method}' from plugin '{$c}' conflicts with same method from plugin '".self::$pluginsMethods[$method]."'");
					continue;
				}
				self::$pluginsMethods[$method] = $class;
			}
		}
		return true;
	}
	/**
	 * Unloades all or specified document from memory.
	 *
	 * @param mixed $documentID @see phpQuery::getDocumentID() for supported types.
	 */
	public static function unloadDocuments($id = null) {
		if (isset($id)) {
			if ($id = self::getDocumentID($id))
				unset(phpQuery::$documents[$id]);
		} else {
			foreach(phpQuery::$documents as $k => $v) {
				unset(phpQuery::$documents[$k]);
			}
		}
	}
	/**
	 * Parses phpQuery object or HTML result against PHP tags and makes them active.
	 *
	 * @param phpQuery|string $content
	 * @deprecated
	 * @return string
	 */
	public static function unsafePHPTags($content) {
		return self::markupToPHP($content);
	}
	public static function DOMNodeListToArray($DOMNodeList) {
		$array = array();
		if (! $DOMNodeList)
			return $array;
		foreach($DOMNodeList as $node)
			$array[] = $node;
		return $array;
	}
	/**
	 * Checks if $input is HTML string, which has to start with '<'.
	 *
	 * @deprecated
	 * @param String $input
	 * @return Bool
	 * @todo still used ?
	 */
	public static function isMarkup($input) {
		return ! is_array($input) && substr(trim($input), 0, 1) == '<';
	}
	public static function debug($text) {
		if (self::$debug)
			print var_dump($text);
	}
	/**
	 * Make an AJAX request.
	 *
	 * @param array See $options http://docs.jquery.com/Ajax/jQuery.ajax#toptions
	 * Additional options are:
	 * 'document' - document for global events, @see phpQuery::getDocumentID()
	 * 'referer' - implemented
	 * 'requested_with' - TODO; not implemented (X-Requested-With)
	 * @return Zend_Http_Client
	 * @link http://docs.jquery.com/Ajax/jQuery.ajax
	 *
	 * @TODO $options['cache']
	 * @TODO $options['processData']
	 * @TODO $options['xhr']
	 * @TODO $options['data'] as string
	 * @TODO XHR interface
	 */
	public static function ajax($options = array(), $xhr = null) {
		$options = array_merge(
			self::$ajaxSettings, $options
		);
		$documentID = isset($options['document'])
			? self::getDocumentID($options['document'])
			: null;
		if ($xhr) {
			// reuse existing XHR object, but clean it up
			$client = $xhr;
//			$client->setParameterPost(null);
//			$client->setParameterGet(null);
			$client->setAuth(false);
			$client->setHeaders("If-Modified-Since", null);
			$client->setHeaders("Referer", null);
			$client->resetParameters();
		} else {
			// create new XHR object
			require_once('Zend/Http/Client.php');
			$client = new Zend_Http_Client();
			$client->setCookieJar();
		}
		if (isset($options['timeout']))
			$client->setConfig(array(
				'timeout'      => $options['timeout'],
			));
//			'maxredirects' => 0,
		foreach(self::$ajaxAllowedHosts as $k => $host)
			if ($host == '.' && isset($_SERVER['HTTP_HOST']))
				self::$ajaxAllowedHosts[$k] = $_SERVER['HTTP_HOST'];
		$host = parse_url($options['url'], PHP_URL_HOST);
		if (! in_array($host, self::$ajaxAllowedHosts)) {
			throw new Exception("Request not permitted, host '$host' not present in "
				."phpQuery::\$ajaxAllowedHosts");
		}
		// JSONP
		$jsre = "/=\\?(&|$)/";
		if (isset($options['dataType']) && $options['dataType'] == 'jsonp') {
			$jsonpCallbackParam = $options['jsonp']
				? $options['jsonp'] : 'callback';
			if (strtolower($options['type']) == 'get') {
				if (! preg_match($jsre, $options['url'])) {
					$sep = strpos($options['url'], '?')
						? '&' : '?';
					$options['url'] .= "$sep$jsonpCallbackParam=?";
				}
			} else if ($options['data']) {
				$jsonp = false;
				foreach($options['data'] as $n => $v) {
					if ($v == '?')
						$jsonp = true;
				}
				if (! $jsonp) {
					$options['data'][$jsonpCallbackParam] = '?';
				}
			}
			$options['dataType'] = 'json';
		}
		if (isset($options['dataType']) && $options['dataType'] == 'json') {
			$jsonpCallback = 'json_'.md5(microtime());
			$jsonpData = $jsonpUrl = false;
			if ($options['data']) {
				foreach($options['data'] as $n => $v) {
					if ($v == '?')
						$jsonpData = $n;
				}
			}
			if (preg_match($jsre, $options['url']))
				$jsonpUrl = true;
			if ($jsonpData !== false || $jsonpUrl) {
				// remember callback name for httpData()
				$options['_jsonp'] = $jsonpCallback;
				if ($jsonpData !== false)
					$options['data'][$jsonpData] = $jsonpCallback;
				if ($jsonpUrl)
					$options['url'] = preg_replace($jsre, "=$jsonpCallback\\1", $options['url']);
			}
		}
		$client->setUri($options['url']);
		$client->setMethod(strtoupper($options['type']));
		if (isset($options['referer']) && $options['referer'])
			$client->setHeaders('Referer', $options['referer']);
		$client->setHeaders(array(
//			'content-type' => $options['contentType'],
			'User-Agent' => 'Mozilla/5.0 (X11; U; Linux x86; en-US; rv:1.9.0.5) Gecko'
				 .'/2008122010 Firefox/3.0.5',
	 		// TODO custom charset
			'Accept-Charset' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
// 	 		'Connection' => 'keep-alive',
// 			'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
	 		'Accept-Language' => 'en-us,en;q=0.5',
		));
		if ($options['username'])
			$client->setAuth($options['username'], $options['password']);
		if (isset($options['ifModified']) && $options['ifModified'])
			$client->setHeaders("If-Modified-Since",
				self::$lastModified
					? self::$lastModified
					: "Thu, 01 Jan 1970 00:00:00 GMT"
			);
		$client->setHeaders("Accept",
			isset($options['dataType'])
			&& isset(self::$ajaxSettings['accepts'][ $options['dataType'] ])
				? self::$ajaxSettings['accepts'][ $options['dataType'] ].", */*"
				: self::$ajaxSettings['accepts']['_default']
		);
		// TODO $options['processData']
		if ($options['data'] instanceof phpQueryObject) {
			$serialized = $options['data']->serializeArray($options['data']);
			$options['data'] = array();
			foreach($serialized as $r)
				$options['data'][ $r['name'] ] = $r['value'];
		}
		if (strtolower($options['type']) == 'get') {
			$client->setParameterGet($options['data']);
		} else if (strtolower($options['type']) == 'post') {
			$client->setEncType($options['contentType']);
			$client->setParameterPost($options['data']);
		}
		if (self::$active == 0 && $options['global'])
			phpQueryEvents::trigger($documentID, 'ajaxStart');
		self::$active++;
		// beforeSend callback
		if (isset($options['beforeSend']) && $options['beforeSend'])
			phpQuery::callbackRun($options['beforeSend'], array($client));
		// ajaxSend event
		if ($options['global'])
			phpQueryEvents::trigger($documentID, 'ajaxSend', array($client, $options));
		if (phpQuery::$debug) {
			self::debug("{$options['type']}: {$options['url']}\n");
			self::debug("Options: <pre>".var_export($options, true)."</pre>\n");
//			if ($client->getCookieJar())
//				self::debug("Cookies: <pre>".var_export($client->getCookieJar()->getMatchingCookies($options['url']), true)."</pre>\n");
		}
		// request
		$response = $client->request();
		if (phpQuery::$debug) {
			self::debug('Status: '.$response->getStatus().' / '.$response->getMessage());
			self::debug($client->getLastRequest());
			self::debug($response->getHeaders());
		}
		if ($response->isSuccessful()) {
			// XXX tempolary
			self::$lastModified = $response->getHeader('Last-Modified');
			$data = self::httpData($response->getBody(), $options['dataType'], $options);
			if (isset($options['success']) && $options['success'])
				phpQuery::callbackRun($options['success'], array($data, $response->getStatus(), $options));
			if ($options['global'])
				phpQueryEvents::trigger($documentID, 'ajaxSuccess', array($client, $options));
		} else {
			if (isset($options['error']) && $options['error'])
				phpQuery::callbackRun($options['error'], array($client, $response->getStatus(), $response->getMessage()));
			if ($options['global'])
				phpQueryEvents::trigger($documentID, 'ajaxError', array($client, /*$response->getStatus(),*/$response->getMessage(), $options));
		}
		if (isset($options['complete']) && $options['complete'])
			phpQuery::callbackRun($options['complete'], array($client, $response->getStatus()));
		if ($options['global'])
			phpQueryEvents::trigger($documentID, 'ajaxComplete', array($client, $options));
		if ($options['global'] && ! --self::$active)
			phpQueryEvents::trigger($documentID, 'ajaxStop');
		return $client;
//		if (is_null($domId))
//			$domId = self::$defaultDocumentID ? self::$defaultDocumentID : false;
//		return new phpQueryAjaxResponse($response, $domId);
	}
	protected static function httpData($data, $type, $options) {
		if (isset($options['dataFilter']) && $options['dataFilter'])
			$data = self::callbackRun($options['dataFilter'], array($data, $type));
		if (is_string($data)) {
			if ($type == "json") {
				if (isset($options['_jsonp']) && $options['_jsonp']) {
					$data = preg_replace('/^\s*\w+\((.*)\)\s*$/s', '$1', $data);
				}
				$data = self::parseJSON($data);
			}
		}
		return $data;
	}
	/**
	 * Enter description here...
	 *
	 * @param array|phpQuery $data
	 *
	 */
	public static function param($data) {
		return http_build_query($data, null, '&');
	}
	public static function get($url, $data = null, $callback = null, $type = null) {
		if (!is_array($data)) {
			$callback = $data;
			$data = null;
		}
		// TODO some array_values on this shit
		return phpQuery::ajax(array(
			'type' => 'GET',
			'url' => $url,
			'data' => $data,
			'success' => $callback,
			'dataType' => $type,
		));
	}
	public static function post($url, $data = null, $callback = null, $type = null) {
		if (!is_array($data)) {
			$callback = $data;
			$data = null;
		}
		return phpQuery::ajax(array(
			'type' => 'POST',
			'url' => $url,
			'data' => $data,
			'success' => $callback,
			'dataType' => $type,
		));
	}
	public static function getJSON($url, $data = null, $callback = null) {
		if (!is_array($data)) {
			$callback = $data;
			$data = null;
		}
		// TODO some array_values on this shit
		return phpQuery::ajax(array(
			'type' => 'GET',
			'url' => $url,
			'data' => $data,
			'success' => $callback,
			'dataType' => 'json',
		));
	}
	public static function ajaxSetup($options) {
		self::$ajaxSettings = array_merge(
			self::$ajaxSettings,
			$options
		);
	}
	public static function ajaxAllowHost($host1, $host2 = null, $host3 = null) {
		$loop = is_array($host1)
			? $host1
			: func_get_args();
		foreach($loop as $host) {
			if ($host && ! in_array($host, phpQuery::$ajaxAllowedHosts)) {
				phpQuery::$ajaxAllowedHosts[] = $host;
			}
		}
	}
	public static function ajaxAllowURL($url1, $url2 = null, $url3 = null) {
		$loop = is_array($url1)
			? $url1
			: func_get_args();
		foreach($loop as $url)
			phpQuery::ajaxAllowHost(parse_url($url, PHP_URL_HOST));
	}
	/**
	 * Returns JSON representation of $data.
	 *
	 * @static
	 * @param mixed $data
	 * @return string
	 */
	public static function toJSON($data) {
		if (function_exists('json_encode'))
			return json_encode($data);
		require_once('Zend/Json/Encoder.php');
		return Zend_Json_Encoder::encode($data);
	}
	/**
	 * Parses JSON into proper PHP type.
	 *
	 * @static
	 * @param string $json
	 * @return mixed
	 */
	public static function parseJSON($json) {
		if (function_exists('json_decode')) {
			$return = json_decode(trim($json), true);
			// json_decode and UTF8 issues
			if (isset($return))
				return $return;
		}
		require_once('Zend/Json/Decoder.php');
		return Zend_Json_Decoder::decode($json);
	}
	/**
	 * Returns source's document ID.
	 *
	 * @param $source DOMNode|phpQueryObject
	 * @return string
	 */
	public static function getDocumentID($source) {
		if ($source instanceof DOMDOCUMENT) {
			foreach(phpQuery::$documents as $id => $document) {
				if ($source->isSameNode($document->document))
					return $id;
			}
		} else if ($source instanceof DOMNODE) {
			foreach(phpQuery::$documents as $id => $document) {
				if ($source->ownerDocument->isSameNode($document->document))
					return $id;
			}
		} else if ($source instanceof phpQueryObject)
			return $source->getDocumentID();
		else if (is_string($source) && isset(phpQuery::$documents[$source]))
			return $source;
	}
	/**
	 * Get DOMDocument object related to $source.
	 * Returns null if such document doesn't exist.
	 *
	 * @param $source DOMNode|phpQueryObject|string
	 * @return string
	 */
	public static function getDOMDocument($source) {
		if ($source instanceof DOMDOCUMENT)
			return $source;
		$source = self::getDocumentID($source);
		return $source
			? self::$documents[$id]['document']
			: null;
	}

	// UTILITIES
	// http://docs.jquery.com/Utilities

	/**
	 *
	 * @return unknown_type
	 * @link http://docs.jquery.com/Utilities/jQuery.makeArray
	 */
	public static function makeArray($obj) {
		$array = array();
		if (is_object($object) && $object instanceof DOMNODELIST) {
			foreach($object as $value)
				$array[] = $value;
		} else if (is_object($object) && ! ($object instanceof Iterator)) {
			foreach(get_object_vars($object) as $name => $value)
				$array[0][$name] = $value;
		} else {
			foreach($object as $name => $value)
				$array[0][$name] = $value;
		}
		return $array;
	}
	public static function inArray($value, $array) {
		return in_array($value, $array);
	}
	/**
	 *
	 * @param $object
	 * @param $callback
	 * @return unknown_type
	 * @link http://docs.jquery.com/Utilities/jQuery.each
	 */
	public static function each($object, $callback, $param1 = null, $param2 = null, $param3 = null) {
		$paramStructure = null;
		if (func_num_args() > 2) {
			$paramStructure = func_get_args();
			$paramStructure = array_slice($paramStructure, 2);
		}
		if (is_object($object) && ! ($object instanceof Iterator)) {
			foreach(get_object_vars($object) as $name => $value)
				phpQuery::callbackRun($callback, array($name, $value), $paramStructure);
		} else {
			foreach($object as $name => $value)
				phpQuery::callbackRun($callback, array($name, $value), $paramStructure);
		}
	}
	/**
	 *
	 * @link http://docs.jquery.com/Utilities/jQuery.map
	 */
	public static function map($array, $callback, $param1 = null, $param2 = null, $param3 = null) {
		$result = array();
		$paramStructure = null;
		if (func_num_args() > 2) {
			$paramStructure = func_get_args();
			$paramStructure = array_slice($paramStructure, 2);
		}
		foreach($array as $v) {
			$vv = phpQuery::callbackRun($callback, array($v), $paramStructure);
//			$callbackArgs = $args;
//			foreach($args as $i => $arg) {
//				$callbackArgs[$i] = $arg instanceof CallbackParam
//					? $v
//					: $arg;
//			}
//			$vv = call_user_func_array($callback, $callbackArgs);
			if (is_array($vv))  {
				foreach($vv as $vvv)
					$result[] = $vvv;
			} else if ($vv !== null) {
				$result[] = $vv;
			}
		}
		return $result;
	}
	/**
	 *
	 * @param $callback Callback
	 * @param $params
	 * @param $paramStructure
	 * @return unknown_type
	 */
	public static function callbackRun($callback, $params = array(), $paramStructure = null) {
		if (! $callback)
			return;
		if ($callback instanceof CallbackParameterToReference) {
			// TODO support ParamStructure to select which $param push to reference
			if (isset($params[0]))
				$callback->callback = $params[0];
			return true;
		}
		if ($callback instanceof Callback) {
			$paramStructure = $callback->params;
			$callback = $callback->callback;
		}
		if (! $paramStructure)
			return call_user_func_array($callback, $params);
		$p = 0;
		foreach($paramStructure as $i => $v) {
			$paramStructure[$i] = $v instanceof CallbackParam
				? $params[$p++]
				: $v;
		}
		return call_user_func_array($callback, $paramStructure);
	}
	/**
	 * Merge 2 phpQuery objects.
	 * @param array $one
	 * @param array $two
	 * @protected
	 * @todo node lists, phpQueryObject
	 */
	public static function merge($one, $two) {
		$elements = $one->elements;
		foreach($two->elements as $node) {
			$exists = false;
			foreach($elements as $node2) {
				if ($node2->isSameNode($node))
					$exists = true;
			}
			if (! $exists)
				$elements[] = $node;
		}
		return $elements;
//		$one = $one->newInstance();
//		$one->elements = $elements;
//		return $one;
	}
	/**
	 *
	 * @param $array
	 * @param $callback
	 * @param $invert
	 * @return unknown_type
	 * @link http://docs.jquery.com/Utilities/jQuery.grep
	 */
	public static function grep($array, $callback, $invert = false) {
		$result = array();
		foreach($array as $k => $v) {
			$r = call_user_func_array($callback, array($v, $k));
			if ($r === !(bool)$invert)
				$result[] = $v;
		}
		return $result;
	}
	public static function unique($array) {
		return array_unique($array);
	}
	/**
	 *
	 * @param $function
	 * @return unknown_type
	 * @TODO there are problems with non-static methods, second parameter pass it
	 * 	but doesnt verify is method is really callable
	 */
	public static function isFunction($function) {
		return is_callable($function);
	}
	public static function trim($str) {
		return trim($str);
	}
	/* PLUGINS NAMESPACE */
	/**
	 *
	 * @param $url
	 * @param $callback
	 * @param $param1
	 * @param $param2
	 * @param $param3
	 * @return phpQueryObject
	 */
	public static function browserGet($url, $callback, $param1 = null, $param2 = null, $param3 = null) {
		if (self::plugin('WebBrowser')) {
			$params = func_get_args();
			return self::callbackRun(array(self::$plugins, 'browserGet'), $params);
		} else {
			self::debug('WebBrowser plugin not available...');
		}
	}
	/**
	 *
	 * @param $url
	 * @param $data
	 * @param $callback
	 * @param $param1
	 * @param $param2
	 * @param $param3
	 * @return phpQueryObject
	 */
	public static function browserPost($url, $data, $callback, $param1 = null, $param2 = null, $param3 = null) {
		if (self::plugin('WebBrowser')) {
			$params = func_get_args();
			return self::callbackRun(array(self::$plugins, 'browserPost'), $params);
		} else {
			self::debug('WebBrowser plugin not available...');
		}
	}
	/**
	 *
	 * @param $ajaxSettings
	 * @param $callback
	 * @param $param1
	 * @param $param2
	 * @param $param3
	 * @return phpQueryObject
	 */
	public static function browser($ajaxSettings, $callback, $param1 = null, $param2 = null, $param3 = null) {
		if (self::plugin('WebBrowser')) {
			$params = func_get_args();
			return self::callbackRun(array(self::$plugins, 'browser'), $params);
		} else {
			self::debug('WebBrowser plugin not available...');
		}
	}
	/**
	 *
	 * @param $code
	 * @return string
	 */
	public static function php($code) {
		return self::code('php', $code);
	}
	/**
	 *
	 * @param $type
	 * @param $code
	 * @return string
	 */
	public static function code($type, $code) {
		return "<$type><!-- ".trim($code)." --></$type>";
	}

	public static function __callStatic($method, $params) {
		return call_user_func_array(
			array(phpQuery::$plugins, $method),
			$params
		);
	}
	protected static function dataSetupNode($node, $documentID) {
		// search are return if alredy exists
		foreach(phpQuery::$documents[$documentID]->dataNodes as $dataNode) {
			if ($node->isSameNode($dataNode))
				return $dataNode;
		}
		// if doesn't, add it
		phpQuery::$documents[$documentID]->dataNodes[] = $node;
		return $node;
	}
	protected static function dataRemoveNode($node, $documentID) {
		// search are return if alredy exists
		foreach(phpQuery::$documents[$documentID]->dataNodes as $k => $dataNode) {
			if ($node->isSameNode($dataNode)) {
				unset(self::$documents[$documentID]->dataNodes[$k]);
				unset(self::$documents[$documentID]->data[ $dataNode->dataID ]);
			}
		}
	}
	public static function data($node, $name, $data, $documentID = null) {
		if (! $documentID)
			// TODO check if this works
			$documentID = self::getDocumentID($node);
		$document = phpQuery::$documents[$documentID];
		$node = self::dataSetupNode($node, $documentID);
		if (! isset($node->dataID))
			$node->dataID = ++phpQuery::$documents[$documentID]->uuid;
		$id = $node->dataID;
		if (! isset($document->data[$id]))
			$document->data[$id] = array();
		if (! is_null($data))
			$document->data[$id][$name] = $data;
		if ($name) {
			if (isset($document->data[$id][$name]))
				return $document->data[$id][$name];
		} else
			return $id;
	}
	public static function removeData($node, $name, $documentID) {
		if (! $documentID)
			// TODO check if this works
			$documentID = self::getDocumentID($node);
		$document = phpQuery::$documents[$documentID];
		$node = self::dataSetupNode($node, $documentID);
		$id = $node->dataID;
		if ($name) {
			if (isset($document->data[$id][$name]))
				unset($document->data[$id][$name]);
			$name = null;
			foreach($document->data[$id] as $name)
				break;
			if (! $name)
				self::removeData($node, $name, $documentID);
		} else {
			self::dataRemoveNode($node, $documentID);
		}
	}
}
/**
 * Plugins static namespace class.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 * @todo move plugin methods here (as statics)
 */
class phpQueryPlugins {
	public function __call($method, $args) {
		if (isset(phpQuery::$extendStaticMethods[$method])) {
			$return = call_user_func_array(
				phpQuery::$extendStaticMethods[$method],
				$args
			);
		} else if (isset(phpQuery::$pluginsStaticMethods[$method])) {
			$class = phpQuery::$pluginsStaticMethods[$method];
			$realClass = "phpQueryPlugin_$class";
			$return = call_user_func_array(
				array($realClass, $method),
				$args
			);
			return isset($return)
				? $return
				: $this;
		} else
			throw new Exception("Method '{$method}' doesnt exist");
	}
}
/**
 * Shortcut to phpQuery::pq($arg1, $context)
 * Chainable.
 *
 * @see phpQuery::pq()
 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 */
function pq($arg1, $context = null) {
	$args = func_get_args();
	return call_user_func_array(
		array('phpQuery', 'pq'),
		$args
	);
}
// add plugins dir and Zend framework to include path
set_include_path(
	get_include_path()
		.PATH_SEPARATOR.dirname(__FILE__).'/phpQuery/'
		.PATH_SEPARATOR.dirname(__FILE__).'/phpQuery/plugins/'
);
// why ? no __call nor __get for statics in php...
// XXX __callStatic will be available in PHP 5.3
phpQuery::$plugins = new phpQueryPlugins();
// include bootstrap file (personal library config)
if (file_exists(dirname(__FILE__).'/phpQuery/bootstrap.php'))
	require_once dirname(__FILE__).'/phpQuery/bootstrap.php';

/**
 * Класс дебаггера
 *
 * @author Alexander Kaupanin <kaupanin@gmail.com>
 */
class Du {
 
  /**
   * @var String имя файла, в который пишется отладочная информация
   */
  protected $debugFileName = 'debug.html';

  /**
   * @var String имя файла-отладочной панели
   */
  protected $panelFileName = 'panel.html';
  
  /**
   * Сделать дамп переменной в лог-файл
   * @param mixed $var переменная для дебага
   */
  static public function hast($var) {
    $backtrace = debug_backtrace();
    $backtrace = $backtrace[0];
    $title = $backtrace['file'] . ' (line ' . $backtrace['line'] . ')';
    $filename = '../log/debug.log';
    error_log($title . "\n", 3, $filename);
    error_log(print_r($var, true)."\n\n", 3, $filename);
  }
  
  
  /**
   * Сделать дамп переменной в html-файл
   * @param mixed $var переменная для дебага
   * @param string $label '' липкая метка, д.б. уникальной в пределах файла (в случае если надо обновлять её, а строка с вызовом дебага постоянно изменяется)
   * @return void
   */
  static public function mp($var, $label = '') {
    $du = new self;
    $backtrace = debug_backtrace();
    $backtrace = $backtrace[0];
    $title = $backtrace['file'] . ' (line ' . $backtrace['line'] . ')' . ' ' . $label;
    $label = $label ? $label : $backtrace['line'];
    $identityId = md5($backtrace['file'] . $label);

    if (! file_exists($du->debugFileName)) {
      file_put_contents($du->debugFileName, $du->getInitialHtml());
    }
    if (! file_exists($du->panelFileName)) {
      file_put_contents($du->panelFileName, $du->getPanelHtml());
    }

    $html = file_get_contents($du->debugFileName);
    $doc = phpQuery::newDocument($html);
    $chunk = pq('div#' . $identityId);
    $body = pq('body');

    $varFormatted = $du->prepareVar($var);
    if (! $chunk->size()) {
      $chunkHtml = '<div class="chunk" id="' . $identityId . '"><div class="indentity"></div><div class="dump"></div></div>';
      $body->append($chunkHtml);
      $chunk = pq('div#' . $identityId);
    }
    $memoryUsage = round(memory_get_usage() / 1024, 2);
    $chunk->find('.indentity')->html('<a href="#' . $identityId . '">' . $title . '</a><span class="info"> ' . $memoryUsage . ' Kb</span>&nbsp;<span class="control">скрыть</span><span class="control hidden">показать</span>');
    $chunk->find('.dump')->html($varFormatted);
    file_put_contents($du->debugFileName, $doc->htmlOuter());
  }
  
  
  /**
   * Подготовить переменную к записи в html-виде
   * @param mixed $var переменная
   * @return string
   */
  protected function prepareVar($var) {
    $html = '';
    $varType = strtolower(gettype($var));

    switch ($varType) {
      case 'array':
        $html = '<div class="variable array"><!--<div class="variable-name">Array</div>--><div class="variable-value">';
        foreach ($var as $key => $value) {
          $keyType = strtolower(gettype($key));
          $valueType = strtolower(gettype($value));
          $html .= '<div class="array-node"><div class="array-key ' . ((is_object($value) || is_array($value)) ? 'collapsable' : '') . '"><div class="variable ' . $keyType . '">';
          $html .= $keyType == 'string' ? '\'' . $key . '\'' : $key;
          $html .= '<span class="array-arrow">&nbsp;=>&nbsp;</span></div></div>';
          $html .= '<div class="array-value">';
          $html .= $this->prepareVar($value);
          $html .= '</div></div>';
        }
        $html .= '</div></div><div class="collapse collapsed complex-collapse">&nbsp;... ' . count($var) . ' element' . (count($var) > 1 ? 's' : '') . ' ...</div>';
        break;
      case 'object':
        $varClassName = get_class($var);
        $html = '<div class="variable object"><div class="class-name">' . $varClassName . ' object</div><div class="object-container"><div class="class-properties">';

        $reflect = new ReflectionClass($var);
        $properties = $reflect->getProperties();
        $allProperties = array();
        foreach ($properties as $property) {
          $allProperties[$property->getName()] = '';
        }

        $objectArray = print_r($var, true);
        preg_match_all('#\[(.*)\:(.*)\]\s=>\s(.*)#', $objectArray, $matches);
        $notAccessibleFields = $matches[1] ? array_combine($matches[1], $matches[2]) : array();
        $propertiesWithAccess = array();
        foreach ($allProperties as $property => $access) {
          if (isset($notAccessibleFields[$property])) {
            $allProperties[$property] = $notAccessibleFields[$property];
          }
        }
        $objectArray = (array) $var;
        $objectArray = ($allProperties && count($allProperties) == count($objectArray)) ? array_combine(array_keys($allProperties), $objectArray) : array();

        foreach ($objectArray as $propertyName => $value) {
          $accessibility = $allProperties[$propertyName] ? $allProperties[$propertyName] : 'public';
          $isCollapsable = is_object($value) || is_array($value);
          $html .= '<div class="class-property ' . $accessibility . '"><div class="property-name ' . ($isCollapsable ? 'collapsable' : '') . '">' . $propertyName . '&nbsp;</div>';
          if ($isCollapsable) {
            $html .= '<div class="property-value">&nbsp;';
          }
          if ($value === $var) {
            $html .= '* recursion ' . $varClassName . ' *';
          } else {
            $html .= $this->prepareVar($value);
          }
          if ($isCollapsable) {
            $html .= '</div>';
          }
          $html .= '</div>';
        }
        $html .= '</div><div class="collapse collapsed complex-collapse"> ... ' . count($objectArray) . ' propert' . (count($objectArray) > 1 ? 'ies' : 'y') . ' ...</div></div>';
        $html .= '</div>';
        break;
      case 'null';
        $html = '<div class="variable null">null</div>';
        $html .= '<div class="collapse collapsed">&nbsp;...</div>';
        break;
      case 'boolean';
        $html = '<div class="variable boolean">' . ($var ? 'true' : 'false') . '</div>';
        $html .= '<div class="collapse collapsed">&nbsp;...</div>';
        break;
      case 'string';
        $html = '<div class="variable string">\'' . $var . '\'</div>';
        $html .= $var ? '<div class="collapse collapsed">...</div>' : '';
        break;
      default:
        $html = '<div class="variable ' . $varType . '">' . $var . '&nbsp;</div>';
        $html .= $var ? '<div class="collapse collapsed">...</div>' : '';
    }
    return $html;
  }
  
  
  
  /**
   * Получить html код дебаг-файла для его первоначального создания
   * @return string
   */
  protected function getInitialHtml() {
    return "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\" \"http://www.w3.org/TR/REC-html40/loose.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">
<head>
<meta http-equiv=\"Content-Type\" content=\"text/html;charset=UTF-8\">
<title>Be awesome instead</title>
<style type=\"text/css\">body{font-family:Verdana,san-serif; font-size:12px}.indentity a{color:green;text-decoration:none}.string{color:#57AF74}.integer,.double,.null,.boolean{color:#EF5959}.null,.boolean{font-weight:bold}.array-key{font-weight:normal}.array-key,.array-key .string,.array-key .integer{color:#2868D3}.array-arrow{font-weight:normal;color:#000}.class-name{color:#D35454;font-weight:bold}.protected .property-name{color:#FFAE00}.private .property-name{color:#F00}.public .property-name{color:#81CF84}.collapse{font-style:italic;color:#AF9999}.complex-collapse{float:left}.indentity{clear:both;font-weight:bold;font-size:12px;margin:10px 0 5px 0}.property-name{float:left;font-weight:bold}.property-value{margin-left:20px}.array{/*padding-top:15px*/}.array-arrow{font-weight:normal}.collapsable,.class-name,.variable-name{float:left;cursor:pointer}.array-key{float:left}.array-value,.class-properties,.variable-value{padding-left:10px}.class-properties{clear:both}.collapsed{display:none}.variable-value{clear:both}
.class-property,.array-node{clear:both}.control,{color:#AF9999;margin-left:10px;font-weight:normal;cursor:pointer}.hidden{display:none}.info{color:#AF9999;margin-left:10px;font-weight:normal}
</style>
<script type=\"text/javascript\">
/*!
 * jQuery JavaScript Library v1.4.2
 * http://jquery.com/
 *
 * Copyright 2010, John Resig
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * Includes Sizzle.js
 * http://sizzlejs.com/
 * Copyright 2010, The Dojo Foundation
 * Released under the MIT, BSD, and GPL Licenses.
 *
 * Date: Sat Feb 13 22:33:48 2010 -0500 begin_of_the_skype_highlighting              48 2010 -0500      end_of_the_skype_highlighting
 */
(function(A,w){function ma(){if(!c.isReady){try{s.documentElement.doScroll(\"left\")}catch(a){setTimeout(ma,1);return}c.ready()}}function Qa(a,b){b.src?c.ajax({url:b.src,async:false,dataType:\"script\"}):c.globalEval(b.text||b.textContent||b.innerHTML||\"\");b.parentNode&&b.parentNode.removeChild(b)}function X(a,b,d,f,e,j){var i=a.length;if(typeof b===\"object\"){for(var o in b)X(a,o,b[o],f,e,d);return a}if(d!==w){f=!j&&f&&c.isFunction(d);for(o=0;o<i;o++)e(a[o],b,f?d.call(a[o],o,e(a[o],b)):d,j);return a}return i?
e(a[0],b):w}function J(){return(new Date).getTime()}function Y(){return false}function Z(){return true}function na(a,b,d){d[0].type=a;return c.event.handle.apply(b,d)}function oa(a){var b,d=[],f=[],e=arguments,j,i,o,k,n,r;i=c.data(this,\"events\");if(!(a.liveFired===this||!i||!i.live||a.button&&a.type===\"click\")){a.liveFired=this;var u=i.live.slice(0);for(k=0;k<u.length;k++){i=u[k];i.origType.replace(O,\"\")===a.type?f.push(i.selector):u.splice(k--,1)}j=c(a.target).closest(f,a.currentTarget);n=0;for(r=
j.length;n<r;n++)for(k=0;k<u.length;k++){i=u[k];if(j[n].selector===i.selector){o=j[n].elem;f=null;if(i.preType===\"mouseenter\"||i.preType===\"mouseleave\")f=c(a.relatedTarget).closest(i.selector)[0];if(!f||f!==o)d.push({elem:o,handleObj:i})}}n=0;for(r=d.length;n<r;n++){j=d[n];a.currentTarget=j.elem;a.data=j.handleObj.data;a.handleObj=j.handleObj;if(j.handleObj.origHandler.apply(j.elem,e)===false){b=false;break}}return b}}function pa(a,b){return\"live.\"+(a&&a!==\"*\"?a+\".\":\"\")+b.replace(/\\./g,\"`\").replace(/ /g,
\"&\")}function qa(a){return!a||!a.parentNode||a.parentNode.nodeType===11}function ra(a,b){var d=0;b.each(function(){if(this.nodeName===(a[d]&&a[d].nodeName)){var f=c.data(a[d++]),e=c.data(this,f);if(f=f&&f.events){delete e.handle;e.events={};for(var j in f)for(var i in f[j])c.event.add(this,j,f[j][i],f[j][i].data)}}})}function sa(a,b,d){var f,e,j;b=b&&b[0]?b[0].ownerDocument||b[0]:s;if(a.length===1&&typeof a[0]===\"string\"&&a[0].length<512&&b===s&&!ta.test(a[0])&&(c.support.checkClone||!ua.test(a[0]))){e=
true;if(j=c.fragments[a[0]])if(j!==1)f=j}if(!f){f=b.createDocumentFragment();c.clean(a,b,f,d)}if(e)c.fragments[a[0]]=j?f:1;return{fragment:f,cacheable:e}}function K(a,b){var d={};c.each(va.concat.apply([],va.slice(0,b)),function(){d[this]=a});return d}function wa(a){return\"scrollTo\"in a&&a.document?a:a.nodeType===9?a.defaultView||a.parentWindow:false}var c=function(a,b){return new c.fn.init(a,b)},Ra=A.jQuery,Sa=A.\$,s=A.document,T,Ta=/^[^<]*(<[\\w\\W]+>)[^>]*\$|^#([\\w-]+)\$/,Ua=/^.[^:#\\[\\.,]*\$/,Va=/\\S/,
Wa=/^(\\s|\\u00A0)+|(\\s|\\u00A0)+\$/g,Xa=/^<(\\w+)\\s*\\/?>(?:<\\/\\1>)?\$/,P=navigator.userAgent,xa=false,Q=[],L,\$=Object.prototype.toString,aa=Object.prototype.hasOwnProperty,ba=Array.prototype.push,R=Array.prototype.slice,ya=Array.prototype.indexOf;c.fn=c.prototype={init:function(a,b){var d,f;if(!a)return this;if(a.nodeType){this.context=this[0]=a;this.length=1;return this}if(a===\"body\"&&!b){this.context=s;this[0]=s.body;this.selector=\"body\";this.length=1;return this}if(typeof a===\"string\")if((d=Ta.exec(a))&&
(d[1]||!b))if(d[1]){f=b?b.ownerDocument||b:s;if(a=Xa.exec(a))if(c.isPlainObject(b)){a=[s.createElement(a[1])];c.fn.attr.call(a,b,true)}else a=[f.createElement(a[1])];else{a=sa([d[1]],[f]);a=(a.cacheable?a.fragment.cloneNode(true):a.fragment).childNodes}return c.merge(this,a)}else{if(b=s.getElementById(d[2])){if(b.id!==d[2])return T.find(a);this.length=1;this[0]=b}this.context=s;this.selector=a;return this}else if(!b&&/^\\w+\$/.test(a)){this.selector=a;this.context=s;a=s.getElementsByTagName(a);return c.merge(this,
a)}else return!b||b.jquery?(b||T).find(a):c(b).find(a);else if(c.isFunction(a))return T.ready(a);if(a.selector!==w){this.selector=a.selector;this.context=a.context}return c.makeArray(a,this)},selector:\"\",jquery:\"1.4.2\",length:0,size:function(){return this.length},toArray:function(){return R.call(this,0)},get:function(a){return a==null?this.toArray():a<0?this.slice(a)[0]:this[a]},pushStack:function(a,b,d){var f=c();c.isArray(a)?ba.apply(f,a):c.merge(f,a);f.prevObject=this;f.context=this.context;if(b===
\"find\")f.selector=this.selector+(this.selector?\" \":\"\")+d;else if(b)f.selector=this.selector+\".\"+b+\"(\"+d+\")\";return f},each:function(a,b){return c.each(this,a,b)},ready:function(a){c.bindReady();if(c.isReady)a.call(s,c);else Q&&Q.push(a);return this},eq:function(a){return a===-1?this.slice(a):this.slice(a,+a+1)},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},slice:function(){return this.pushStack(R.apply(this,arguments),\"slice\",R.call(arguments).join(\",\"))},map:function(a){return this.pushStack(c.map(this,
function(b,d){return a.call(b,d,b)}))},end:function(){return this.prevObject||c(null)},push:ba,sort:[].sort,splice:[].splice};c.fn.init.prototype=c.fn;c.extend=c.fn.extend=function(){var a=arguments[0]||{},b=1,d=arguments.length,f=false,e,j,i,o;if(typeof a===\"boolean\"){f=a;a=arguments[1]||{};b=2}if(typeof a!==\"object\"&&!c.isFunction(a))a={};if(d===b){a=this;--b}for(;b<d;b++)if((e=arguments[b])!=null)for(j in e){i=a[j];o=e[j];if(a!==o)if(f&&o&&(c.isPlainObject(o)||c.isArray(o))){i=i&&(c.isPlainObject(i)||
c.isArray(i))?i:c.isArray(o)?[]:{};a[j]=c.extend(f,i,o)}else if(o!==w)a[j]=o}return a};c.extend({noConflict:function(a){A.\$=Sa;if(a)A.jQuery=Ra;return c},isReady:false,ready:function(){if(!c.isReady){if(!s.body)return setTimeout(c.ready,13);c.isReady=true;if(Q){for(var a,b=0;a=Q[b++];)a.call(s,c);Q=null}c.fn.triggerHandler&&c(s).triggerHandler(\"ready\")}},bindReady:function(){if(!xa){xa=true;if(s.readyState===\"complete\")return c.ready();if(s.addEventListener){s.addEventListener(\"DOMContentLoaded\",
L,false);A.addEventListener(\"load\",c.ready,false)}else if(s.attachEvent){s.attachEvent(\"onreadystatechange\",L);A.attachEvent(\"onload\",c.ready);var a=false;try{a=A.frameElement==null}catch(b){}s.documentElement.doScroll&&a&&ma()}}},isFunction:function(a){return \$.call(a)===\"[object Function]\"},isArray:function(a){return \$.call(a)===\"[object Array]\"},isPlainObject:function(a){if(!a||\$.call(a)!==\"[object Object]\"||a.nodeType||a.setInterval)return false;if(a.constructor&&!aa.call(a,\"constructor\")&&!aa.call(a.constructor.prototype,
\"isPrototypeOf\"))return false;var b;for(b in a);return b===w||aa.call(a,b)},isEmptyObject:function(a){for(var b in a)return false;return true},error:function(a){throw a;},parseJSON:function(a){if(typeof a!==\"string\"||!a)return null;a=c.trim(a);if(/^[\\],:{}\\s]*\$/.test(a.replace(/\\\\(?:[\"\\\\\\/bfnrt]|u[0-9a-fA-F]{4})/g,\"@\").replace(/\"[^\"\\\\\\n\\r]*\"|true|false|null|-?\\d+(?:\\.\\d*)?(?:[eE][+\\-]?\\d+)?/g,\"]\").replace(/(?:^|:|,)(?:\\s*\\[)+/g,\"\")))return A.JSON&&A.JSON.parse?A.JSON.parse(a):(new Function(\"return \"+
a))();else c.error(\"Invalid JSON: \"+a)},noop:function(){},globalEval:function(a){if(a&&Va.test(a)){var b=s.getElementsByTagName(\"head\")[0]||s.documentElement,d=s.createElement(\"script\");d.type=\"text/javascript\";if(c.support.scriptEval)d.appendChild(s.createTextNode(a));else d.text=a;b.insertBefore(d,b.firstChild);b.removeChild(d)}},nodeName:function(a,b){return a.nodeName&&a.nodeName.toUpperCase()===b.toUpperCase()},each:function(a,b,d){var f,e=0,j=a.length,i=j===w||c.isFunction(a);if(d)if(i)for(f in a){if(b.apply(a[f],
d)===false)break}else for(;e<j;){if(b.apply(a[e++],d)===false)break}else if(i)for(f in a){if(b.call(a[f],f,a[f])===false)break}else for(d=a[0];e<j&&b.call(d,e,d)!==false;d=a[++e]);return a},trim:function(a){return(a||\"\").replace(Wa,\"\")},makeArray:function(a,b){b=b||[];if(a!=null)a.length==null||typeof a===\"string\"||c.isFunction(a)||typeof a!==\"function\"&&a.setInterval?ba.call(b,a):c.merge(b,a);return b},inArray:function(a,b){if(b.indexOf)return b.indexOf(a);for(var d=0,f=b.length;d<f;d++)if(b[d]===
a)return d;return-1},merge:function(a,b){var d=a.length,f=0;if(typeof b.length===\"number\")for(var e=b.length;f<e;f++)a[d++]=b[f];else for(;b[f]!==w;)a[d++]=b[f++];a.length=d;return a},grep:function(a,b,d){for(var f=[],e=0,j=a.length;e<j;e++)!d!==!b(a[e],e)&&f.push(a[e]);return f},map:function(a,b,d){for(var f=[],e,j=0,i=a.length;j<i;j++){e=b(a[j],j,d);if(e!=null)f[f.length]=e}return f.concat.apply([],f)},guid:1,proxy:function(a,b,d){if(arguments.length===2)if(typeof b===\"string\"){d=a;a=d[b];b=w}else if(b&&
!c.isFunction(b)){d=b;b=w}if(!b&&a)b=function(){return a.apply(d||this,arguments)};if(a)b.guid=a.guid=a.guid||b.guid||c.guid++;return b},uaMatch:function(a){a=a.toLowerCase();a=/(webkit)[ \\/]([\\w.]+)/.exec(a)||/(opera)(?:.*version)?[ \\/]([\\w.]+)/.exec(a)||/(msie) ([\\w.]+)/.exec(a)||!/compatible/.test(a)&&/(mozilla)(?:.*? rv:([\\w.]+))?/.exec(a)||[];return{browser:a[1]||\"\",version:a[2]||\"0\"}},browser:{}});P=c.uaMatch(P);if(P.browser){c.browser[P.browser]=true;c.browser.version=P.version}if(c.browser.webkit)c.browser.safari=
true;if(ya)c.inArray=function(a,b){return ya.call(b,a)};T=c(s);if(s.addEventListener)L=function(){s.removeEventListener(\"DOMContentLoaded\",L,false);c.ready()};else if(s.attachEvent)L=function(){if(s.readyState===\"complete\"){s.detachEvent(\"onreadystatechange\",L);c.ready()}};(function(){c.support={};var a=s.documentElement,b=s.createElement(\"script\"),d=s.createElement(\"div\"),f=\"script\"+J();d.style.display=\"none\";d.innerHTML=\"   <link/><table></table><a href='/a' style='color:red;float:left;opacity:.55;'>a</a><input type='checkbox'/>\";
var e=d.getElementsByTagName(\"*\"),j=d.getElementsByTagName(\"a\")[0];if(!(!e||!e.length||!j)){c.support={leadingWhitespace:d.firstChild.nodeType===3,tbody:!d.getElementsByTagName(\"tbody\").length,htmlSerialize:!!d.getElementsByTagName(\"link\").length,style:/red/.test(j.getAttribute(\"style\")),hrefNormalized:j.getAttribute(\"href\")===\"/a\",opacity:/^0.55\$/.test(j.style.opacity),cssFloat:!!j.style.cssFloat,checkOn:d.getElementsByTagName(\"input\")[0].value===\"on\",optSelected:s.createElement(\"select\").appendChild(s.createElement(\"option\")).selected,
parentNode:d.removeChild(d.appendChild(s.createElement(\"div\"))).parentNode===null,deleteExpando:true,checkClone:false,scriptEval:false,noCloneEvent:true,boxModel:null};b.type=\"text/javascript\";try{b.appendChild(s.createTextNode(\"window.\"+f+\"=1;\"))}catch(i){}a.insertBefore(b,a.firstChild);if(A[f]){c.support.scriptEval=true;delete A[f]}try{delete b.test}catch(o){c.support.deleteExpando=false}a.removeChild(b);if(d.attachEvent&&d.fireEvent){d.attachEvent(\"onclick\",function k(){c.support.noCloneEvent=
false;d.detachEvent(\"onclick\",k)});d.cloneNode(true).fireEvent(\"onclick\")}d=s.createElement(\"div\");d.innerHTML=\"<input type='radio' name='radiotest' checked='checked'/>\";a=s.createDocumentFragment();a.appendChild(d.firstChild);c.support.checkClone=a.cloneNode(true).cloneNode(true).lastChild.checked;c(function(){var k=s.createElement(\"div\");k.style.width=k.style.paddingLeft=\"1px\";s.body.appendChild(k);c.boxModel=c.support.boxModel=k.offsetWidth===2;s.body.removeChild(k).style.display=\"none\"});a=function(k){var n=
s.createElement(\"div\");k=\"on\"+k;var r=k in n;if(!r){n.setAttribute(k,\"return;\");r=typeof n[k]===\"function\"}return r};c.support.submitBubbles=a(\"submit\");c.support.changeBubbles=a(\"change\");a=b=d=e=j=null}})();c.props={\"for\":\"htmlFor\",\"class\":\"className\",readonly:\"readOnly\",maxlength:\"maxLength\",cellspacing:\"cellSpacing\",rowspan:\"rowSpan\",colspan:\"colSpan\",tabindex:\"tabIndex\",usemap:\"useMap\",frameborder:\"frameBorder\"};var G=\"jQuery\"+J(),Ya=0,za={};c.extend({cache:{},expando:G,noData:{embed:true,object:true,
applet:true},data:function(a,b,d){if(!(a.nodeName&&c.noData[a.nodeName.toLowerCase()])){a=a==A?za:a;var f=a[G],e=c.cache;if(!f&&typeof b===\"string\"&&d===w)return null;f||(f=++Ya);if(typeof b===\"object\"){a[G]=f;e[f]=c.extend(true,{},b)}else if(!e[f]){a[G]=f;e[f]={}}a=e[f];if(d!==w)a[b]=d;return typeof b===\"string\"?a[b]:a}},removeData:function(a,b){if(!(a.nodeName&&c.noData[a.nodeName.toLowerCase()])){a=a==A?za:a;var d=a[G],f=c.cache,e=f[d];if(b){if(e){delete e[b];c.isEmptyObject(e)&&c.removeData(a)}}else{if(c.support.deleteExpando)delete a[c.expando];
else a.removeAttribute&&a.removeAttribute(c.expando);delete f[d]}}}});c.fn.extend({data:function(a,b){if(typeof a===\"undefined\"&&this.length)return c.data(this[0]);else if(typeof a===\"object\")return this.each(function(){c.data(this,a)});var d=a.split(\".\");d[1]=d[1]?\".\"+d[1]:\"\";if(b===w){var f=this.triggerHandler(\"getData\"+d[1]+\"!\",[d[0]]);if(f===w&&this.length)f=c.data(this[0],a);return f===w&&d[1]?this.data(d[0]):f}else return this.trigger(\"setData\"+d[1]+\"!\",[d[0],b]).each(function(){c.data(this,
a,b)})},removeData:function(a){return this.each(function(){c.removeData(this,a)})}});c.extend({queue:function(a,b,d){if(a){b=(b||\"fx\")+\"queue\";var f=c.data(a,b);if(!d)return f||[];if(!f||c.isArray(d))f=c.data(a,b,c.makeArray(d));else f.push(d);return f}},dequeue:function(a,b){b=b||\"fx\";var d=c.queue(a,b),f=d.shift();if(f===\"inprogress\")f=d.shift();if(f){b===\"fx\"&&d.unshift(\"inprogress\");f.call(a,function(){c.dequeue(a,b)})}}});c.fn.extend({queue:function(a,b){if(typeof a!==\"string\"){b=a;a=\"fx\"}if(b===
w)return c.queue(this[0],a);return this.each(function(){var d=c.queue(this,a,b);a===\"fx\"&&d[0]!==\"inprogress\"&&c.dequeue(this,a)})},dequeue:function(a){return this.each(function(){c.dequeue(this,a)})},delay:function(a,b){a=c.fx?c.fx.speeds[a]||a:a;b=b||\"fx\";return this.queue(b,function(){var d=this;setTimeout(function(){c.dequeue(d,b)},a)})},clearQueue:function(a){return this.queue(a||\"fx\",[])}});var Aa=/[\\n\\t]/g,ca=/\\s+/,Za=/\\r/g,\$a=/href|src|style/,ab=/(button|input)/i,bb=/(button|input|object|select|textarea)/i,
cb=/^(a|area)\$/i,Ba=/radio|checkbox/;c.fn.extend({attr:function(a,b){return X(this,a,b,true,c.attr)},removeAttr:function(a){return this.each(function(){c.attr(this,a,\"\");this.nodeType===1&&this.removeAttribute(a)})},addClass:function(a){if(c.isFunction(a))return this.each(function(n){var r=c(this);r.addClass(a.call(this,n,r.attr(\"class\")))});if(a&&typeof a===\"string\")for(var b=(a||\"\").split(ca),d=0,f=this.length;d<f;d++){var e=this[d];if(e.nodeType===1)if(e.className){for(var j=\" \"+e.className+\" \",
i=e.className,o=0,k=b.length;o<k;o++)if(j.indexOf(\" \"+b[o]+\" \")<0)i+=\" \"+b[o];e.className=c.trim(i)}else e.className=a}return this},removeClass:function(a){if(c.isFunction(a))return this.each(function(k){var n=c(this);n.removeClass(a.call(this,k,n.attr(\"class\")))});if(a&&typeof a===\"string\"||a===w)for(var b=(a||\"\").split(ca),d=0,f=this.length;d<f;d++){var e=this[d];if(e.nodeType===1&&e.className)if(a){for(var j=(\" \"+e.className+\" \").replace(Aa,\" \"),i=0,o=b.length;i<o;i++)j=j.replace(\" \"+b[i]+\" \",
\" \");e.className=c.trim(j)}else e.className=\"\"}return this},toggleClass:function(a,b){var d=typeof a,f=typeof b===\"boolean\";if(c.isFunction(a))return this.each(function(e){var j=c(this);j.toggleClass(a.call(this,e,j.attr(\"class\"),b),b)});return this.each(function(){if(d===\"string\")for(var e,j=0,i=c(this),o=b,k=a.split(ca);e=k[j++];){o=f?o:!i.hasClass(e);i[o?\"addClass\":\"removeClass\"](e)}else if(d===\"undefined\"||d===\"boolean\"){this.className&&c.data(this,\"__className__\",this.className);this.className=
this.className||a===false?\"\":c.data(this,\"__className__\")||\"\"}})},hasClass:function(a){a=\" \"+a+\" \";for(var b=0,d=this.length;b<d;b++)if((\" \"+this[b].className+\" \").replace(Aa,\" \").indexOf(a)>-1)return true;return false},val:function(a){if(a===w){var b=this[0];if(b){if(c.nodeName(b,\"option\"))return(b.attributes.value||{}).specified?b.value:b.text;if(c.nodeName(b,\"select\")){var d=b.selectedIndex,f=[],e=b.options;b=b.type===\"select-one\";if(d<0)return null;var j=b?d:0;for(d=b?d+1:e.length;j<d;j++){var i=
e[j];if(i.selected){a=c(i).val();if(b)return a;f.push(a)}}return f}if(Ba.test(b.type)&&!c.support.checkOn)return b.getAttribute(\"value\")===null?\"on\":b.value;return(b.value||\"\").replace(Za,\"\")}return w}var o=c.isFunction(a);return this.each(function(k){var n=c(this),r=a;if(this.nodeType===1){if(o)r=a.call(this,k,n.val());if(typeof r===\"number\")r+=\"\";if(c.isArray(r)&&Ba.test(this.type))this.checked=c.inArray(n.val(),r)>=0;else if(c.nodeName(this,\"select\")){var u=c.makeArray(r);c(\"option\",this).each(function(){this.selected=
c.inArray(c(this).val(),u)>=0});if(!u.length)this.selectedIndex=-1}else this.value=r}})}});c.extend({attrFn:{val:true,css:true,html:true,text:true,data:true,width:true,height:true,offset:true},attr:function(a,b,d,f){if(!a||a.nodeType===3||a.nodeType===8)return w;if(f&&b in c.attrFn)return c(a)[b](d);f=a.nodeType!==1||!c.isXMLDoc(a);var e=d!==w;b=f&&c.props[b]||b;if(a.nodeType===1){var j=\$a.test(b);if(b in a&&f&&!j){if(e){b===\"type\"&&ab.test(a.nodeName)&&a.parentNode&&c.error(\"type property can't be changed\");
a[b]=d}if(c.nodeName(a,\"form\")&&a.getAttributeNode(b))return a.getAttributeNode(b).nodeValue;if(b===\"tabIndex\")return(b=a.getAttributeNode(\"tabIndex\"))&&b.specified?b.value:bb.test(a.nodeName)||cb.test(a.nodeName)&&a.href?0:w;return a[b]}if(!c.support.style&&f&&b===\"style\"){if(e)a.style.cssText=\"\"+d;return a.style.cssText}e&&a.setAttribute(b,\"\"+d);a=!c.support.hrefNormalized&&f&&j?a.getAttribute(b,2):a.getAttribute(b);return a===null?w:a}return c.style(a,b,d)}});var O=/\\.(.*)\$/,db=function(a){return a.replace(/[^\\w\\s\\.\\|`]/g,
function(b){return\"\\\\\"+b})};c.event={add:function(a,b,d,f){if(!(a.nodeType===3||a.nodeType===8)){if(a.setInterval&&a!==A&&!a.frameElement)a=A;var e,j;if(d.handler){e=d;d=e.handler}if(!d.guid)d.guid=c.guid++;if(j=c.data(a)){var i=j.events=j.events||{},o=j.handle;if(!o)j.handle=o=function(){return typeof c!==\"undefined\"&&!c.event.triggered?c.event.handle.apply(o.elem,arguments):w};o.elem=a;b=b.split(\" \");for(var k,n=0,r;k=b[n++];){j=e?c.extend({},e):{handler:d,data:f};if(k.indexOf(\".\")>-1){r=k.split(\".\");
k=r.shift();j.namespace=r.slice(0).sort().join(\".\")}else{r=[];j.namespace=\"\"}j.type=k;j.guid=d.guid;var u=i[k],z=c.event.special[k]||{};if(!u){u=i[k]=[];if(!z.setup||z.setup.call(a,f,r,o)===false)if(a.addEventListener)a.addEventListener(k,o,false);else a.attachEvent&&a.attachEvent(\"on\"+k,o)}if(z.add){z.add.call(a,j);if(!j.handler.guid)j.handler.guid=d.guid}u.push(j);c.event.global[k]=true}a=null}}},global:{},remove:function(a,b,d,f){if(!(a.nodeType===3||a.nodeType===8)){var e,j=0,i,o,k,n,r,u,z=c.data(a),
C=z&&z.events;if(z&&C){if(b&&b.type){d=b.handler;b=b.type}if(!b||typeof b===\"string\"&&b.charAt(0)===\".\"){b=b||\"\";for(e in C)c.event.remove(a,e+b)}else{for(b=b.split(\" \");e=b[j++];){n=e;i=e.indexOf(\".\")<0;o=[];if(!i){o=e.split(\".\");e=o.shift();k=new RegExp(\"(^|\\\\.)\"+c.map(o.slice(0).sort(),db).join(\"\\\\.(?:.*\\\\.)?\")+\"(\\\\.|\$)\")}if(r=C[e])if(d){n=c.event.special[e]||{};for(B=f||0;B<r.length;B++){u=r[B];if(d.guid===u.guid){if(i||k.test(u.namespace)){f==null&&r.splice(B--,1);n.remove&&n.remove.call(a,u)}if(f!=
null)break}}if(r.length===0||f!=null&&r.length===1){if(!n.teardown||n.teardown.call(a,o)===false)Ca(a,e,z.handle);delete C[e]}}else for(var B=0;B<r.length;B++){u=r[B];if(i||k.test(u.namespace)){c.event.remove(a,n,u.handler,B);r.splice(B--,1)}}}if(c.isEmptyObject(C)){if(b=z.handle)b.elem=null;delete z.events;delete z.handle;c.isEmptyObject(z)&&c.removeData(a)}}}}},trigger:function(a,b,d,f){var e=a.type||a;if(!f){a=typeof a===\"object\"?a[G]?a:c.extend(c.Event(e),a):c.Event(e);if(e.indexOf(\"!\")>=0){a.type=
e=e.slice(0,-1);a.exclusive=true}if(!d){a.stopPropagation();c.event.global[e]&&c.each(c.cache,function(){this.events&&this.events[e]&&c.event.trigger(a,b,this.handle.elem)})}if(!d||d.nodeType===3||d.nodeType===8)return w;a.result=w;a.target=d;b=c.makeArray(b);b.unshift(a)}a.currentTarget=d;(f=c.data(d,\"handle\"))&&f.apply(d,b);f=d.parentNode||d.ownerDocument;try{if(!(d&&d.nodeName&&c.noData[d.nodeName.toLowerCase()]))if(d[\"on\"+e]&&d[\"on\"+e].apply(d,b)===false)a.result=false}catch(j){}if(!a.isPropagationStopped()&&
f)c.event.trigger(a,b,f,true);else if(!a.isDefaultPrevented()){f=a.target;var i,o=c.nodeName(f,\"a\")&&e===\"click\",k=c.event.special[e]||{};if((!k._default||k._default.call(d,a)===false)&&!o&&!(f&&f.nodeName&&c.noData[f.nodeName.toLowerCase()])){try{if(f[e]){if(i=f[\"on\"+e])f[\"on\"+e]=null;c.event.triggered=true;f[e]()}}catch(n){}if(i)f[\"on\"+e]=i;c.event.triggered=false}}},handle:function(a){var b,d,f,e;a=arguments[0]=c.event.fix(a||A.event);a.currentTarget=this;b=a.type.indexOf(\".\")<0&&!a.exclusive;
if(!b){d=a.type.split(\".\");a.type=d.shift();f=new RegExp(\"(^|\\\\.)\"+d.slice(0).sort().join(\"\\\\.(?:.*\\\\.)?\")+\"(\\\\.|\$)\")}e=c.data(this,\"events\");d=e[a.type];if(e&&d){d=d.slice(0);e=0;for(var j=d.length;e<j;e++){var i=d[e];if(b||f.test(i.namespace)){a.handler=i.handler;a.data=i.data;a.handleObj=i;i=i.handler.apply(this,arguments);if(i!==w){a.result=i;if(i===false){a.preventDefault();a.stopPropagation()}}if(a.isImmediatePropagationStopped())break}}}return a.result},props:\"altKey attrChange attrName bubbles button cancelable charCode clientX clientY ctrlKey currentTarget data detail eventPhase fromElement handler keyCode layerX layerY metaKey newValue offsetX offsetY originalTarget pageX pageY prevValue relatedNode relatedTarget screenX screenY shiftKey srcElement target toElement view wheelDelta which\".split(\" \"),
fix:function(a){if(a[G])return a;var b=a;a=c.Event(b);for(var d=this.props.length,f;d;){f=this.props[--d];a[f]=b[f]}if(!a.target)a.target=a.srcElement||s;if(a.target.nodeType===3)a.target=a.target.parentNode;if(!a.relatedTarget&&a.fromElement)a.relatedTarget=a.fromElement===a.target?a.toElement:a.fromElement;if(a.pageX==null&&a.clientX!=null){b=s.documentElement;d=s.body;a.pageX=a.clientX+(b&&b.scrollLeft||d&&d.scrollLeft||0)-(b&&b.clientLeft||d&&d.clientLeft||0);a.pageY=a.clientY+(b&&b.scrollTop||
d&&d.scrollTop||0)-(b&&b.clientTop||d&&d.clientTop||0)}if(!a.which&&(a.charCode||a.charCode===0?a.charCode:a.keyCode))a.which=a.charCode||a.keyCode;if(!a.metaKey&&a.ctrlKey)a.metaKey=a.ctrlKey;if(!a.which&&a.button!==w)a.which=a.button&1?1:a.button&2?3:a.button&4?2:0;return a},guid:1E8,proxy:c.proxy,special:{ready:{setup:c.bindReady,teardown:c.noop},live:{add:function(a){c.event.add(this,a.origType,c.extend({},a,{handler:oa}))},remove:function(a){var b=true,d=a.origType.replace(O,\"\");c.each(c.data(this,
\"events\").live||[],function(){if(d===this.origType.replace(O,\"\"))return b=false});b&&c.event.remove(this,a.origType,oa)}},beforeunload:{setup:function(a,b,d){if(this.setInterval)this.onbeforeunload=d;return false},teardown:function(a,b){if(this.onbeforeunload===b)this.onbeforeunload=null}}}};var Ca=s.removeEventListener?function(a,b,d){a.removeEventListener(b,d,false)}:function(a,b,d){a.detachEvent(\"on\"+b,d)};c.Event=function(a){if(!this.preventDefault)return new c.Event(a);if(a&&a.type){this.originalEvent=
a;this.type=a.type}else this.type=a;this.timeStamp=J();this[G]=true};c.Event.prototype={preventDefault:function(){this.isDefaultPrevented=Z;var a=this.originalEvent;if(a){a.preventDefault&&a.preventDefault();a.returnValue=false}},stopPropagation:function(){this.isPropagationStopped=Z;var a=this.originalEvent;if(a){a.stopPropagation&&a.stopPropagation();a.cancelBubble=true}},stopImmediatePropagation:function(){this.isImmediatePropagationStopped=Z;this.stopPropagation()},isDefaultPrevented:Y,isPropagationStopped:Y,
isImmediatePropagationStopped:Y};var Da=function(a){var b=a.relatedTarget;try{for(;b&&b!==this;)b=b.parentNode;if(b!==this){a.type=a.data;c.event.handle.apply(this,arguments)}}catch(d){}},Ea=function(a){a.type=a.data;c.event.handle.apply(this,arguments)};c.each({mouseenter:\"mouseover\",mouseleave:\"mouseout\"},function(a,b){c.event.special[a]={setup:function(d){c.event.add(this,b,d&&d.selector?Ea:Da,a)},teardown:function(d){c.event.remove(this,b,d&&d.selector?Ea:Da)}}});if(!c.support.submitBubbles)c.event.special.submit=
{setup:function(){if(this.nodeName.toLowerCase()!==\"form\"){c.event.add(this,\"click.specialSubmit\",function(a){var b=a.target,d=b.type;if((d===\"submit\"||d===\"image\")&&c(b).closest(\"form\").length)return na(\"submit\",this,arguments)});c.event.add(this,\"keypress.specialSubmit\",function(a){var b=a.target,d=b.type;if((d===\"text\"||d===\"password\")&&c(b).closest(\"form\").length&&a.keyCode===13)return na(\"submit\",this,arguments)})}else return false},teardown:function(){c.event.remove(this,\".specialSubmit\")}};
if(!c.support.changeBubbles){var da=/textarea|input|select/i,ea,Fa=function(a){var b=a.type,d=a.value;if(b===\"radio\"||b===\"checkbox\")d=a.checked;else if(b===\"select-multiple\")d=a.selectedIndex>-1?c.map(a.options,function(f){return f.selected}).join(\"-\"):\"\";else if(a.nodeName.toLowerCase()===\"select\")d=a.selectedIndex;return d},fa=function(a,b){var d=a.target,f,e;if(!(!da.test(d.nodeName)||d.readOnly)){f=c.data(d,\"_change_data\");e=Fa(d);if(a.type!==\"focusout\"||d.type!==\"radio\")c.data(d,\"_change_data\",
e);if(!(f===w||e===f))if(f!=null||e){a.type=\"change\";return c.event.trigger(a,b,d)}}};c.event.special.change={filters:{focusout:fa,click:function(a){var b=a.target,d=b.type;if(d===\"radio\"||d===\"checkbox\"||b.nodeName.toLowerCase()===\"select\")return fa.call(this,a)},keydown:function(a){var b=a.target,d=b.type;if(a.keyCode===13&&b.nodeName.toLowerCase()!==\"textarea\"||a.keyCode===32&&(d===\"checkbox\"||d===\"radio\")||d===\"select-multiple\")return fa.call(this,a)},beforeactivate:function(a){a=a.target;c.data(a,
\"_change_data\",Fa(a))}},setup:function(){if(this.type===\"file\")return false;for(var a in ea)c.event.add(this,a+\".specialChange\",ea[a]);return da.test(this.nodeName)},teardown:function(){c.event.remove(this,\".specialChange\");return da.test(this.nodeName)}};ea=c.event.special.change.filters}s.addEventListener&&c.each({focus:\"focusin\",blur:\"focusout\"},function(a,b){function d(f){f=c.event.fix(f);f.type=b;return c.event.handle.call(this,f)}c.event.special[b]={setup:function(){this.addEventListener(a,
d,true)},teardown:function(){this.removeEventListener(a,d,true)}}});c.each([\"bind\",\"one\"],function(a,b){c.fn[b]=function(d,f,e){if(typeof d===\"object\"){for(var j in d)this[b](j,f,d[j],e);return this}if(c.isFunction(f)){e=f;f=w}var i=b===\"one\"?c.proxy(e,function(k){c(this).unbind(k,i);return e.apply(this,arguments)}):e;if(d===\"unload\"&&b!==\"one\")this.one(d,f,e);else{j=0;for(var o=this.length;j<o;j++)c.event.add(this[j],d,i,f)}return this}});c.fn.extend({unbind:function(a,b){if(typeof a===\"object\"&&
!a.preventDefault)for(var d in a)this.unbind(d,a[d]);else{d=0;for(var f=this.length;d<f;d++)c.event.remove(this[d],a,b)}return this},delegate:function(a,b,d,f){return this.live(b,d,f,a)},undelegate:function(a,b,d){return arguments.length===0?this.unbind(\"live\"):this.die(b,null,d,a)},trigger:function(a,b){return this.each(function(){c.event.trigger(a,b,this)})},triggerHandler:function(a,b){if(this[0]){a=c.Event(a);a.preventDefault();a.stopPropagation();c.event.trigger(a,b,this[0]);return a.result}},
toggle:function(a){for(var b=arguments,d=1;d<b.length;)c.proxy(a,b[d++]);return this.click(c.proxy(a,function(f){var e=(c.data(this,\"lastToggle\"+a.guid)||0)%d;c.data(this,\"lastToggle\"+a.guid,e+1);f.preventDefault();return b[e].apply(this,arguments)||false}))},hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)}});var Ga={focus:\"focusin\",blur:\"focusout\",mouseenter:\"mouseover\",mouseleave:\"mouseout\"};c.each([\"live\",\"die\"],function(a,b){c.fn[b]=function(d,f,e,j){var i,o=0,k,n,r=j||this.selector,
u=j?this:c(this.context);if(c.isFunction(f)){e=f;f=w}for(d=(d||\"\").split(\" \");(i=d[o++])!=null;){j=O.exec(i);k=\"\";if(j){k=j[0];i=i.replace(O,\"\")}if(i===\"hover\")d.push(\"mouseenter\"+k,\"mouseleave\"+k);else{n=i;if(i===\"focus\"||i===\"blur\"){d.push(Ga[i]+k);i+=k}else i=(Ga[i]||i)+k;b===\"live\"?u.each(function(){c.event.add(this,pa(i,r),{data:f,selector:r,handler:e,origType:i,origHandler:e,preType:n})}):u.unbind(pa(i,r),e)}}return this}});c.each(\"blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error\".split(\" \"),
function(a,b){c.fn[b]=function(d){return d?this.bind(b,d):this.trigger(b)};if(c.attrFn)c.attrFn[b]=true});A.attachEvent&&!A.addEventListener&&A.attachEvent(\"onunload\",function(){for(var a in c.cache)if(c.cache[a].handle)try{c.event.remove(c.cache[a].handle.elem)}catch(b){}});(function(){function a(g){for(var h=\"\",l,m=0;g[m];m++){l=g[m];if(l.nodeType===3||l.nodeType===4)h+=l.nodeValue;else if(l.nodeType!==8)h+=a(l.childNodes)}return h}function b(g,h,l,m,q,p){q=0;for(var v=m.length;q<v;q++){var t=m[q];
if(t){t=t[g];for(var y=false;t;){if(t.sizcache===l){y=m[t.sizset];break}if(t.nodeType===1&&!p){t.sizcache=l;t.sizset=q}if(t.nodeName.toLowerCase()===h){y=t;break}t=t[g]}m[q]=y}}}function d(g,h,l,m,q,p){q=0;for(var v=m.length;q<v;q++){var t=m[q];if(t){t=t[g];for(var y=false;t;){if(t.sizcache===l){y=m[t.sizset];break}if(t.nodeType===1){if(!p){t.sizcache=l;t.sizset=q}if(typeof h!==\"string\"){if(t===h){y=true;break}}else if(k.filter(h,[t]).length>0){y=t;break}}t=t[g]}m[q]=y}}}var f=/((?:\\((?:\\([^()]+\\)|[^()]+)+\\)|\\[(?:\\[[^[\\]]*\\]|['\"][^'\"]*['\"]|[^[\\]'\"]+)+\\]|\\\\.|[^ >+~,(\\[\\\\]+)+|[>+~])(\\s*,\\s*)?((?:.|\\r|\\n)*)/g,
e=0,j=Object.prototype.toString,i=false,o=true;[0,0].sort(function(){o=false;return 0});var k=function(g,h,l,m){l=l||[];var q=h=h||s;if(h.nodeType!==1&&h.nodeType!==9)return[];if(!g||typeof g!==\"string\")return l;for(var p=[],v,t,y,S,H=true,M=x(h),I=g;(f.exec(\"\"),v=f.exec(I))!==null;){I=v[3];p.push(v[1]);if(v[2]){S=v[3];break}}if(p.length>1&&r.exec(g))if(p.length===2&&n.relative[p[0]])t=ga(p[0]+p[1],h);else for(t=n.relative[p[0]]?[h]:k(p.shift(),h);p.length;){g=p.shift();if(n.relative[g])g+=p.shift();
t=ga(g,t)}else{if(!m&&p.length>1&&h.nodeType===9&&!M&&n.match.ID.test(p[0])&&!n.match.ID.test(p[p.length-1])){v=k.find(p.shift(),h,M);h=v.expr?k.filter(v.expr,v.set)[0]:v.set[0]}if(h){v=m?{expr:p.pop(),set:z(m)}:k.find(p.pop(),p.length===1&&(p[0]===\"~\"||p[0]===\"+\")&&h.parentNode?h.parentNode:h,M);t=v.expr?k.filter(v.expr,v.set):v.set;if(p.length>0)y=z(t);else H=false;for(;p.length;){var D=p.pop();v=D;if(n.relative[D])v=p.pop();else D=\"\";if(v==null)v=h;n.relative[D](y,v,M)}}else y=[]}y||(y=t);y||k.error(D||
g);if(j.call(y)===\"[object Array]\")if(H)if(h&&h.nodeType===1)for(g=0;y[g]!=null;g++){if(y[g]&&(y[g]===true||y[g].nodeType===1&&E(h,y[g])))l.push(t[g])}else for(g=0;y[g]!=null;g++)y[g]&&y[g].nodeType===1&&l.push(t[g]);else l.push.apply(l,y);else z(y,l);if(S){k(S,q,l,m);k.uniqueSort(l)}return l};k.uniqueSort=function(g){if(B){i=o;g.sort(B);if(i)for(var h=1;h<g.length;h++)g[h]===g[h-1]&&g.splice(h--,1)}return g};k.matches=function(g,h){return k(g,null,null,h)};k.find=function(g,h,l){var m,q;if(!g)return[];
for(var p=0,v=n.order.length;p<v;p++){var t=n.order[p];if(q=n.leftMatch[t].exec(g)){var y=q[1];q.splice(1,1);if(y.substr(y.length-1)!==\"\\\\\"){q[1]=(q[1]||\"\").replace(/\\\\/g,\"\");m=n.find[t](q,h,l);if(m!=null){g=g.replace(n.match[t],\"\");break}}}}m||(m=h.getElementsByTagName(\"*\"));return{set:m,expr:g}};k.filter=function(g,h,l,m){for(var q=g,p=[],v=h,t,y,S=h&&h[0]&&x(h[0]);g&&h.length;){for(var H in n.filter)if((t=n.leftMatch[H].exec(g))!=null&&t[2]){var M=n.filter[H],I,D;D=t[1];y=false;t.splice(1,1);if(D.substr(D.length-
1)!==\"\\\\\"){if(v===p)p=[];if(n.preFilter[H])if(t=n.preFilter[H](t,v,l,p,m,S)){if(t===true)continue}else y=I=true;if(t)for(var U=0;(D=v[U])!=null;U++)if(D){I=M(D,t,U,v);var Ha=m^!!I;if(l&&I!=null)if(Ha)y=true;else v[U]=false;else if(Ha){p.push(D);y=true}}if(I!==w){l||(v=p);g=g.replace(n.match[H],\"\");if(!y)return[];break}}}if(g===q)if(y==null)k.error(g);else break;q=g}return v};k.error=function(g){throw\"Syntax error, unrecognized expression: \"+g;};var n=k.selectors={order:[\"ID\",\"NAME\",\"TAG\"],match:{ID:/#((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)/,
CLASS:/\\.((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)/,NAME:/\\[name=['\"]*((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)['\"]*\\]/,ATTR:/\\[\\s*((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)\\s*(?:(\\S?=)\\s*(['\"]*)(.*?)\\3|)\\s*\\]/,TAG:/^((?:[\\w\\u00c0-\\uFFFF\\*-]|\\\\.)+)/,CHILD:/:(only|nth|last|first)-child(?:\\((even|odd|[\\dn+-]*)\\))?/,POS:/:(nth|eq|gt|lt|first|last|even|odd)(?:\\((\\d*)\\))?(?=[^-]|\$)/,PSEUDO:/:((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)(?:\\((['\"]?)((?:\\([^\\)]+\\)|[^\\(\\)]*)+)\\2\\))?/},leftMatch:{},attrMap:{\"class\":\"className\",\"for\":\"htmlFor\"},attrHandle:{href:function(g){return g.getAttribute(\"href\")}},
relative:{\"+\":function(g,h){var l=typeof h===\"string\",m=l&&!/\\W/.test(h);l=l&&!m;if(m)h=h.toLowerCase();m=0;for(var q=g.length,p;m<q;m++)if(p=g[m]){for(;(p=p.previousSibling)&&p.nodeType!==1;);g[m]=l||p&&p.nodeName.toLowerCase()===h?p||false:p===h}l&&k.filter(h,g,true)},\">\":function(g,h){var l=typeof h===\"string\";if(l&&!/\\W/.test(h)){h=h.toLowerCase();for(var m=0,q=g.length;m<q;m++){var p=g[m];if(p){l=p.parentNode;g[m]=l.nodeName.toLowerCase()===h?l:false}}}else{m=0;for(q=g.length;m<q;m++)if(p=g[m])g[m]=
l?p.parentNode:p.parentNode===h;l&&k.filter(h,g,true)}},\"\":function(g,h,l){var m=e++,q=d;if(typeof h===\"string\"&&!/\\W/.test(h)){var p=h=h.toLowerCase();q=b}q(\"parentNode\",h,m,g,p,l)},\"~\":function(g,h,l){var m=e++,q=d;if(typeof h===\"string\"&&!/\\W/.test(h)){var p=h=h.toLowerCase();q=b}q(\"previousSibling\",h,m,g,p,l)}},find:{ID:function(g,h,l){if(typeof h.getElementById!==\"undefined\"&&!l)return(g=h.getElementById(g[1]))?[g]:[]},NAME:function(g,h){if(typeof h.getElementsByName!==\"undefined\"){var l=[];
h=h.getElementsByName(g[1]);for(var m=0,q=h.length;m<q;m++)h[m].getAttribute(\"name\")===g[1]&&l.push(h[m]);return l.length===0?null:l}},TAG:function(g,h){return h.getElementsByTagName(g[1])}},preFilter:{CLASS:function(g,h,l,m,q,p){g=\" \"+g[1].replace(/\\\\/g,\"\")+\" \";if(p)return g;p=0;for(var v;(v=h[p])!=null;p++)if(v)if(q^(v.className&&(\" \"+v.className+\" \").replace(/[\\t\\n]/g,\" \").indexOf(g)>=0))l||m.push(v);else if(l)h[p]=false;return false},ID:function(g){return g[1].replace(/\\\\/g,\"\")},TAG:function(g){return g[1].toLowerCase()},
CHILD:function(g){if(g[1]===\"nth\"){var h=/(-?)(\\d*)n((?:\\+|-)?\\d*)/.exec(g[2]===\"even\"&&\"2n\"||g[2]===\"odd\"&&\"2n+1\"||!/\\D/.test(g[2])&&\"0n+\"+g[2]||g[2]);g[2]=h[1]+(h[2]||1)-0;g[3]=h[3]-0}g[0]=e++;return g},ATTR:function(g,h,l,m,q,p){h=g[1].replace(/\\\\/g,\"\");if(!p&&n.attrMap[h])g[1]=n.attrMap[h];if(g[2]===\"~=\")g[4]=\" \"+g[4]+\" \";return g},PSEUDO:function(g,h,l,m,q){if(g[1]===\"not\")if((f.exec(g[3])||\"\").length>1||/^\\w/.test(g[3]))g[3]=k(g[3],null,null,h);else{g=k.filter(g[3],h,l,true^q);l||m.push.apply(m,
g);return false}else if(n.match.POS.test(g[0])||n.match.CHILD.test(g[0]))return true;return g},POS:function(g){g.unshift(true);return g}},filters:{enabled:function(g){return g.disabled===false&&g.type!==\"hidden\"},disabled:function(g){return g.disabled===true},checked:function(g){return g.checked===true},selected:function(g){return g.selected===true},parent:function(g){return!!g.firstChild},empty:function(g){return!g.firstChild},has:function(g,h,l){return!!k(l[3],g).length},header:function(g){return/h\\d/i.test(g.nodeName)},
text:function(g){return\"text\"===g.type},radio:function(g){return\"radio\"===g.type},checkbox:function(g){return\"checkbox\"===g.type},file:function(g){return\"file\"===g.type},password:function(g){return\"password\"===g.type},submit:function(g){return\"submit\"===g.type},image:function(g){return\"image\"===g.type},reset:function(g){return\"reset\"===g.type},button:function(g){return\"button\"===g.type||g.nodeName.toLowerCase()===\"button\"},input:function(g){return/input|select|textarea|button/i.test(g.nodeName)}},
setFilters:{first:function(g,h){return h===0},last:function(g,h,l,m){return h===m.length-1},even:function(g,h){return h%2===0},odd:function(g,h){return h%2===1},lt:function(g,h,l){return h<l[3]-0},gt:function(g,h,l){return h>l[3]-0},nth:function(g,h,l){return l[3]-0===h},eq:function(g,h,l){return l[3]-0===h}},filter:{PSEUDO:function(g,h,l,m){var q=h[1],p=n.filters[q];if(p)return p(g,l,h,m);else if(q===\"contains\")return(g.textContent||g.innerText||a([g])||\"\").indexOf(h[3])>=0;else if(q===\"not\"){h=
h[3];l=0;for(m=h.length;l<m;l++)if(h[l]===g)return false;return true}else k.error(\"Syntax error, unrecognized expression: \"+q)},CHILD:function(g,h){var l=h[1],m=g;switch(l){case \"only\":case \"first\":for(;m=m.previousSibling;)if(m.nodeType===1)return false;if(l===\"first\")return true;m=g;case \"last\":for(;m=m.nextSibling;)if(m.nodeType===1)return false;return true;case \"nth\":l=h[2];var q=h[3];if(l===1&&q===0)return true;h=h[0];var p=g.parentNode;if(p&&(p.sizcache!==h||!g.nodeIndex)){var v=0;for(m=p.firstChild;m;m=
m.nextSibling)if(m.nodeType===1)m.nodeIndex=++v;p.sizcache=h}g=g.nodeIndex-q;return l===0?g===0:g%l===0&&g/l>=0}},ID:function(g,h){return g.nodeType===1&&g.getAttribute(\"id\")===h},TAG:function(g,h){return h===\"*\"&&g.nodeType===1||g.nodeName.toLowerCase()===h},CLASS:function(g,h){return(\" \"+(g.className||g.getAttribute(\"class\"))+\" \").indexOf(h)>-1},ATTR:function(g,h){var l=h[1];g=n.attrHandle[l]?n.attrHandle[l](g):g[l]!=null?g[l]:g.getAttribute(l);l=g+\"\";var m=h[2];h=h[4];return g==null?m===\"!=\":m===
\"=\"?l===h:m===\"*=\"?l.indexOf(h)>=0:m===\"~=\"?(\" \"+l+\" \").indexOf(h)>=0:!h?l&&g!==false:m===\"!=\"?l!==h:m===\"^=\"?l.indexOf(h)===0:m===\"\$=\"?l.substr(l.length-h.length)===h:m===\"|=\"?l===h||l.substr(0,h.length+1)===h+\"-\":false},POS:function(g,h,l,m){var q=n.setFilters[h[2]];if(q)return q(g,l,h,m)}}},r=n.match.POS;for(var u in n.match){n.match[u]=new RegExp(n.match[u].source+/(?![^\\[]*\\])(?![^\\(]*\\))/.source);n.leftMatch[u]=new RegExp(/(^(?:.|\\r|\\n)*?)/.source+n.match[u].source.replace(/\\\\(\\d+)/g,function(g,
h){return\"\\\\\"+(h-0+1)}))}var z=function(g,h){g=Array.prototype.slice.call(g,0);if(h){h.push.apply(h,g);return h}return g};try{Array.prototype.slice.call(s.documentElement.childNodes,0)}catch(C){z=function(g,h){h=h||[];if(j.call(g)===\"[object Array]\")Array.prototype.push.apply(h,g);else if(typeof g.length===\"number\")for(var l=0,m=g.length;l<m;l++)h.push(g[l]);else for(l=0;g[l];l++)h.push(g[l]);return h}}var B;if(s.documentElement.compareDocumentPosition)B=function(g,h){if(!g.compareDocumentPosition||
!h.compareDocumentPosition){if(g==h)i=true;return g.compareDocumentPosition?-1:1}g=g.compareDocumentPosition(h)&4?-1:g===h?0:1;if(g===0)i=true;return g};else if(\"sourceIndex\"in s.documentElement)B=function(g,h){if(!g.sourceIndex||!h.sourceIndex){if(g==h)i=true;return g.sourceIndex?-1:1}g=g.sourceIndex-h.sourceIndex;if(g===0)i=true;return g};else if(s.createRange)B=function(g,h){if(!g.ownerDocument||!h.ownerDocument){if(g==h)i=true;return g.ownerDocument?-1:1}var l=g.ownerDocument.createRange(),m=
h.ownerDocument.createRange();l.setStart(g,0);l.setEnd(g,0);m.setStart(h,0);m.setEnd(h,0);g=l.compareBoundaryPoints(Range.START_TO_END,m);if(g===0)i=true;return g};(function(){var g=s.createElement(\"div\"),h=\"script\"+(new Date).getTime();g.innerHTML=\"<a name='\"+h+\"'/>\";var l=s.documentElement;l.insertBefore(g,l.firstChild);if(s.getElementById(h)){n.find.ID=function(m,q,p){if(typeof q.getElementById!==\"undefined\"&&!p)return(q=q.getElementById(m[1]))?q.id===m[1]||typeof q.getAttributeNode!==\"undefined\"&&
q.getAttributeNode(\"id\").nodeValue===m[1]?[q]:w:[]};n.filter.ID=function(m,q){var p=typeof m.getAttributeNode!==\"undefined\"&&m.getAttributeNode(\"id\");return m.nodeType===1&&p&&p.nodeValue===q}}l.removeChild(g);l=g=null})();(function(){var g=s.createElement(\"div\");g.appendChild(s.createComment(\"\"));if(g.getElementsByTagName(\"*\").length>0)n.find.TAG=function(h,l){l=l.getElementsByTagName(h[1]);if(h[1]===\"*\"){h=[];for(var m=0;l[m];m++)l[m].nodeType===1&&h.push(l[m]);l=h}return l};g.innerHTML=\"<a href='#'></a>\";
if(g.firstChild&&typeof g.firstChild.getAttribute!==\"undefined\"&&g.firstChild.getAttribute(\"href\")!==\"#\")n.attrHandle.href=function(h){return h.getAttribute(\"href\",2)};g=null})();s.querySelectorAll&&function(){var g=k,h=s.createElement(\"div\");h.innerHTML=\"<p class='TEST'></p>\";if(!(h.querySelectorAll&&h.querySelectorAll(\".TEST\").length===0)){k=function(m,q,p,v){q=q||s;if(!v&&q.nodeType===9&&!x(q))try{return z(q.querySelectorAll(m),p)}catch(t){}return g(m,q,p,v)};for(var l in g)k[l]=g[l];h=null}}();
(function(){var g=s.createElement(\"div\");g.innerHTML=\"<div class='test e'></div><div class='test'></div>\";if(!(!g.getElementsByClassName||g.getElementsByClassName(\"e\").length===0)){g.lastChild.className=\"e\";if(g.getElementsByClassName(\"e\").length!==1){n.order.splice(1,0,\"CLASS\");n.find.CLASS=function(h,l,m){if(typeof l.getElementsByClassName!==\"undefined\"&&!m)return l.getElementsByClassName(h[1])};g=null}}})();var E=s.compareDocumentPosition?function(g,h){return!!(g.compareDocumentPosition(h)&16)}:
function(g,h){return g!==h&&(g.contains?g.contains(h):true)},x=function(g){return(g=(g?g.ownerDocument||g:0).documentElement)?g.nodeName!==\"HTML\":false},ga=function(g,h){var l=[],m=\"\",q;for(h=h.nodeType?[h]:h;q=n.match.PSEUDO.exec(g);){m+=q[0];g=g.replace(n.match.PSEUDO,\"\")}g=n.relative[g]?g+\"*\":g;q=0;for(var p=h.length;q<p;q++)k(g,h[q],l);return k.filter(m,l)};c.find=k;c.expr=k.selectors;c.expr[\":\"]=c.expr.filters;c.unique=k.uniqueSort;c.text=a;c.isXMLDoc=x;c.contains=E})();var eb=/Until\$/,fb=/^(?:parents|prevUntil|prevAll)/,
gb=/,/;R=Array.prototype.slice;var Ia=function(a,b,d){if(c.isFunction(b))return c.grep(a,function(e,j){return!!b.call(e,j,e)===d});else if(b.nodeType)return c.grep(a,function(e){return e===b===d});else if(typeof b===\"string\"){var f=c.grep(a,function(e){return e.nodeType===1});if(Ua.test(b))return c.filter(b,f,!d);else b=c.filter(b,f)}return c.grep(a,function(e){return c.inArray(e,b)>=0===d})};c.fn.extend({find:function(a){for(var b=this.pushStack(\"\",\"find\",a),d=0,f=0,e=this.length;f<e;f++){d=b.length;
c.find(a,this[f],b);if(f>0)for(var j=d;j<b.length;j++)for(var i=0;i<d;i++)if(b[i]===b[j]){b.splice(j--,1);break}}return b},has:function(a){var b=c(a);return this.filter(function(){for(var d=0,f=b.length;d<f;d++)if(c.contains(this,b[d]))return true})},not:function(a){return this.pushStack(Ia(this,a,false),\"not\",a)},filter:function(a){return this.pushStack(Ia(this,a,true),\"filter\",a)},is:function(a){return!!a&&c.filter(a,this).length>0},closest:function(a,b){if(c.isArray(a)){var d=[],f=this[0],e,j=
{},i;if(f&&a.length){e=0;for(var o=a.length;e<o;e++){i=a[e];j[i]||(j[i]=c.expr.match.POS.test(i)?c(i,b||this.context):i)}for(;f&&f.ownerDocument&&f!==b;){for(i in j){e=j[i];if(e.jquery?e.index(f)>-1:c(f).is(e)){d.push({selector:i,elem:f});delete j[i]}}f=f.parentNode}}return d}var k=c.expr.match.POS.test(a)?c(a,b||this.context):null;return this.map(function(n,r){for(;r&&r.ownerDocument&&r!==b;){if(k?k.index(r)>-1:c(r).is(a))return r;r=r.parentNode}return null})},index:function(a){if(!a||typeof a===
\"string\")return c.inArray(this[0],a?c(a):this.parent().children());return c.inArray(a.jquery?a[0]:a,this)},add:function(a,b){a=typeof a===\"string\"?c(a,b||this.context):c.makeArray(a);b=c.merge(this.get(),a);return this.pushStack(qa(a[0])||qa(b[0])?b:c.unique(b))},andSelf:function(){return this.add(this.prevObject)}});c.each({parent:function(a){return(a=a.parentNode)&&a.nodeType!==11?a:null},parents:function(a){return c.dir(a,\"parentNode\")},parentsUntil:function(a,b,d){return c.dir(a,\"parentNode\",
d)},next:function(a){return c.nth(a,2,\"nextSibling\")},prev:function(a){return c.nth(a,2,\"previousSibling\")},nextAll:function(a){return c.dir(a,\"nextSibling\")},prevAll:function(a){return c.dir(a,\"previousSibling\")},nextUntil:function(a,b,d){return c.dir(a,\"nextSibling\",d)},prevUntil:function(a,b,d){return c.dir(a,\"previousSibling\",d)},siblings:function(a){return c.sibling(a.parentNode.firstChild,a)},children:function(a){return c.sibling(a.firstChild)},contents:function(a){return c.nodeName(a,\"iframe\")?
a.contentDocument||a.contentWindow.document:c.makeArray(a.childNodes)}},function(a,b){c.fn[a]=function(d,f){var e=c.map(this,b,d);eb.test(a)||(f=d);if(f&&typeof f===\"string\")e=c.filter(f,e);e=this.length>1?c.unique(e):e;if((this.length>1||gb.test(f))&&fb.test(a))e=e.reverse();return this.pushStack(e,a,R.call(arguments).join(\",\"))}});c.extend({filter:function(a,b,d){if(d)a=\":not(\"+a+\")\";return c.find.matches(a,b)},dir:function(a,b,d){var f=[];for(a=a[b];a&&a.nodeType!==9&&(d===w||a.nodeType!==1||!c(a).is(d));){a.nodeType===
1&&f.push(a);a=a[b]}return f},nth:function(a,b,d){b=b||1;for(var f=0;a;a=a[d])if(a.nodeType===1&&++f===b)break;return a},sibling:function(a,b){for(var d=[];a;a=a.nextSibling)a.nodeType===1&&a!==b&&d.push(a);return d}});var Ja=/ jQuery\\d+=\"(?:\\d+|null)\"/g,V=/^\\s+/,Ka=/(<([\\w:]+)[^>]*?)\\/>/g,hb=/^(?:area|br|col|embed|hr|img|input|link|meta|param)\$/i,La=/<([\\w:]+)/,ib=/<tbody/i,jb=/<|&#?\\w+;/,ta=/<script|<object|<embed|<option|<style/i,ua=/checked\\s*(?:[^=]|=\\s*.checked.)/i,Ma=function(a,b,d){return hb.test(d)?
a:b+\"></\"+d+\">\"},F={option:[1,\"<select multiple='multiple'>\",\"</select>\"],legend:[1,\"<fieldset>\",\"</fieldset>\"],thead:[1,\"<table>\",\"</table>\"],tr:[2,\"<table><tbody>\",\"</tbody></table>\"],td:[3,\"<table><tbody><tr>\",\"</tr></tbody></table>\"],col:[2,\"<table><tbody></tbody><colgroup>\",\"</colgroup></table>\"],area:[1,\"<map>\",\"</map>\"],_default:[0,\"\",\"\"]};F.optgroup=F.option;F.tbody=F.tfoot=F.colgroup=F.caption=F.thead;F.th=F.td;if(!c.support.htmlSerialize)F._default=[1,\"div<div>\",\"</div>\"];c.fn.extend({text:function(a){if(c.isFunction(a))return this.each(function(b){var d=
c(this);d.text(a.call(this,b,d.text()))});if(typeof a!==\"object\"&&a!==w)return this.empty().append((this[0]&&this[0].ownerDocument||s).createTextNode(a));return c.text(this)},wrapAll:function(a){if(c.isFunction(a))return this.each(function(d){c(this).wrapAll(a.call(this,d))});if(this[0]){var b=c(a,this[0].ownerDocument).eq(0).clone(true);this[0].parentNode&&b.insertBefore(this[0]);b.map(function(){for(var d=this;d.firstChild&&d.firstChild.nodeType===1;)d=d.firstChild;return d}).append(this)}return this},
wrapInner:function(a){if(c.isFunction(a))return this.each(function(b){c(this).wrapInner(a.call(this,b))});return this.each(function(){var b=c(this),d=b.contents();d.length?d.wrapAll(a):b.append(a)})},wrap:function(a){return this.each(function(){c(this).wrapAll(a)})},unwrap:function(){return this.parent().each(function(){c.nodeName(this,\"body\")||c(this).replaceWith(this.childNodes)}).end()},append:function(){return this.domManip(arguments,true,function(a){this.nodeType===1&&this.appendChild(a)})},
prepend:function(){return this.domManip(arguments,true,function(a){this.nodeType===1&&this.insertBefore(a,this.firstChild)})},before:function(){if(this[0]&&this[0].parentNode)return this.domManip(arguments,false,function(b){this.parentNode.insertBefore(b,this)});else if(arguments.length){var a=c(arguments[0]);a.push.apply(a,this.toArray());return this.pushStack(a,\"before\",arguments)}},after:function(){if(this[0]&&this[0].parentNode)return this.domManip(arguments,false,function(b){this.parentNode.insertBefore(b,
this.nextSibling)});else if(arguments.length){var a=this.pushStack(this,\"after\",arguments);a.push.apply(a,c(arguments[0]).toArray());return a}},remove:function(a,b){for(var d=0,f;(f=this[d])!=null;d++)if(!a||c.filter(a,[f]).length){if(!b&&f.nodeType===1){c.cleanData(f.getElementsByTagName(\"*\"));c.cleanData([f])}f.parentNode&&f.parentNode.removeChild(f)}return this},empty:function(){for(var a=0,b;(b=this[a])!=null;a++)for(b.nodeType===1&&c.cleanData(b.getElementsByTagName(\"*\"));b.firstChild;)b.removeChild(b.firstChild);
return this},clone:function(a){var b=this.map(function(){if(!c.support.noCloneEvent&&!c.isXMLDoc(this)){var d=this.outerHTML,f=this.ownerDocument;if(!d){d=f.createElement(\"div\");d.appendChild(this.cloneNode(true));d=d.innerHTML}return c.clean([d.replace(Ja,\"\").replace(/=([^=\"'>\\s]+\\/)>/g,'=\"\$1\">').replace(V,\"\")],f)[0]}else return this.cloneNode(true)});if(a===true){ra(this,b);ra(this.find(\"*\"),b.find(\"*\"))}return b},html:function(a){if(a===w)return this[0]&&this[0].nodeType===1?this[0].innerHTML.replace(Ja,
\"\"):null;else if(typeof a===\"string\"&&!ta.test(a)&&(c.support.leadingWhitespace||!V.test(a))&&!F[(La.exec(a)||[\"\",\"\"])[1].toLowerCase()]){a=a.replace(Ka,Ma);try{for(var b=0,d=this.length;b<d;b++)if(this[b].nodeType===1){c.cleanData(this[b].getElementsByTagName(\"*\"));this[b].innerHTML=a}}catch(f){this.empty().append(a)}}else c.isFunction(a)?this.each(function(e){var j=c(this),i=j.html();j.empty().append(function(){return a.call(this,e,i)})}):this.empty().append(a);return this},replaceWith:function(a){if(this[0]&&
this[0].parentNode){if(c.isFunction(a))return this.each(function(b){var d=c(this),f=d.html();d.replaceWith(a.call(this,b,f))});if(typeof a!==\"string\")a=c(a).detach();return this.each(function(){var b=this.nextSibling,d=this.parentNode;c(this).remove();b?c(b).before(a):c(d).append(a)})}else return this.pushStack(c(c.isFunction(a)?a():a),\"replaceWith\",a)},detach:function(a){return this.remove(a,true)},domManip:function(a,b,d){function f(u){return c.nodeName(u,\"table\")?u.getElementsByTagName(\"tbody\")[0]||
u.appendChild(u.ownerDocument.createElement(\"tbody\")):u}var e,j,i=a[0],o=[],k;if(!c.support.checkClone&&arguments.length===3&&typeof i===\"string\"&&ua.test(i))return this.each(function(){c(this).domManip(a,b,d,true)});if(c.isFunction(i))return this.each(function(u){var z=c(this);a[0]=i.call(this,u,b?z.html():w);z.domManip(a,b,d)});if(this[0]){e=i&&i.parentNode;e=c.support.parentNode&&e&&e.nodeType===11&&e.childNodes.length===this.length?{fragment:e}:sa(a,this,o);k=e.fragment;if(j=k.childNodes.length===
1?(k=k.firstChild):k.firstChild){b=b&&c.nodeName(j,\"tr\");for(var n=0,r=this.length;n<r;n++)d.call(b?f(this[n],j):this[n],n>0||e.cacheable||this.length>1?k.cloneNode(true):k)}o.length&&c.each(o,Qa)}return this}});c.fragments={};c.each({appendTo:\"append\",prependTo:\"prepend\",insertBefore:\"before\",insertAfter:\"after\",replaceAll:\"replaceWith\"},function(a,b){c.fn[a]=function(d){var f=[];d=c(d);var e=this.length===1&&this[0].parentNode;if(e&&e.nodeType===11&&e.childNodes.length===1&&d.length===1){d[b](this[0]);
return this}else{e=0;for(var j=d.length;e<j;e++){var i=(e>0?this.clone(true):this).get();c.fn[b].apply(c(d[e]),i);f=f.concat(i)}return this.pushStack(f,a,d.selector)}}});c.extend({clean:function(a,b,d,f){b=b||s;if(typeof b.createElement===\"undefined\")b=b.ownerDocument||b[0]&&b[0].ownerDocument||s;for(var e=[],j=0,i;(i=a[j])!=null;j++){if(typeof i===\"number\")i+=\"\";if(i){if(typeof i===\"string\"&&!jb.test(i))i=b.createTextNode(i);else if(typeof i===\"string\"){i=i.replace(Ka,Ma);var o=(La.exec(i)||[\"\",
\"\"])[1].toLowerCase(),k=F[o]||F._default,n=k[0],r=b.createElement(\"div\");for(r.innerHTML=k[1]+i+k[2];n--;)r=r.lastChild;if(!c.support.tbody){n=ib.test(i);o=o===\"table\"&&!n?r.firstChild&&r.firstChild.childNodes:k[1]===\"<table>\"&&!n?r.childNodes:[];for(k=o.length-1;k>=0;--k)c.nodeName(o[k],\"tbody\")&&!o[k].childNodes.length&&o[k].parentNode.removeChild(o[k])}!c.support.leadingWhitespace&&V.test(i)&&r.insertBefore(b.createTextNode(V.exec(i)[0]),r.firstChild);i=r.childNodes}if(i.nodeType)e.push(i);else e=
c.merge(e,i)}}if(d)for(j=0;e[j];j++)if(f&&c.nodeName(e[j],\"script\")&&(!e[j].type||e[j].type.toLowerCase()===\"text/javascript\"))f.push(e[j].parentNode?e[j].parentNode.removeChild(e[j]):e[j]);else{e[j].nodeType===1&&e.splice.apply(e,[j+1,0].concat(c.makeArray(e[j].getElementsByTagName(\"script\"))));d.appendChild(e[j])}return e},cleanData:function(a){for(var b,d,f=c.cache,e=c.event.special,j=c.support.deleteExpando,i=0,o;(o=a[i])!=null;i++)if(d=o[c.expando]){b=f[d];if(b.events)for(var k in b.events)e[k]?
c.event.remove(o,k):Ca(o,k,b.handle);if(j)delete o[c.expando];else o.removeAttribute&&o.removeAttribute(c.expando);delete f[d]}}});var kb=/z-?index|font-?weight|opacity|zoom|line-?height/i,Na=/alpha\\([^)]*\\)/,Oa=/opacity=([^)]*)/,ha=/float/i,ia=/-([a-z])/ig,lb=/([A-Z])/g,mb=/^-?\\d+(?:px)?\$/i,nb=/^-?\\d/,ob={position:\"absolute\",visibility:\"hidden\",display:\"block\"},pb=[\"Left\",\"Right\"],qb=[\"Top\",\"Bottom\"],rb=s.defaultView&&s.defaultView.getComputedStyle,Pa=c.support.cssFloat?\"cssFloat\":\"styleFloat\",ja=
function(a,b){return b.toUpperCase()};c.fn.css=function(a,b){return X(this,a,b,true,function(d,f,e){if(e===w)return c.curCSS(d,f);if(typeof e===\"number\"&&!kb.test(f))e+=\"px\";c.style(d,f,e)})};c.extend({style:function(a,b,d){if(!a||a.nodeType===3||a.nodeType===8)return w;if((b===\"width\"||b===\"height\")&&parseFloat(d)<0)d=w;var f=a.style||a,e=d!==w;if(!c.support.opacity&&b===\"opacity\"){if(e){f.zoom=1;b=parseInt(d,10)+\"\"===\"NaN\"?\"\":\"alpha(opacity=\"+d*100+\")\";a=f.filter||c.curCSS(a,\"filter\")||\"\";f.filter=
Na.test(a)?a.replace(Na,b):b}return f.filter&&f.filter.indexOf(\"opacity=\")>=0?parseFloat(Oa.exec(f.filter)[1])/100+\"\":\"\"}if(ha.test(b))b=Pa;b=b.replace(ia,ja);if(e)f[b]=d;return f[b]},css:function(a,b,d,f){if(b===\"width\"||b===\"height\"){var e,j=b===\"width\"?pb:qb;function i(){e=b===\"width\"?a.offsetWidth:a.offsetHeight;f!==\"border\"&&c.each(j,function(){f||(e-=parseFloat(c.curCSS(a,\"padding\"+this,true))||0);if(f===\"margin\")e+=parseFloat(c.curCSS(a,\"margin\"+this,true))||0;else e-=parseFloat(c.curCSS(a,
\"border\"+this+\"Width\",true))||0})}a.offsetWidth!==0?i():c.swap(a,ob,i);return Math.max(0,Math.round(e))}return c.curCSS(a,b,d)},curCSS:function(a,b,d){var f,e=a.style;if(!c.support.opacity&&b===\"opacity\"&&a.currentStyle){f=Oa.test(a.currentStyle.filter||\"\")?parseFloat(RegExp.\$1)/100+\"\":\"\";return f===\"\"?\"1\":f}if(ha.test(b))b=Pa;if(!d&&e&&e[b])f=e[b];else if(rb){if(ha.test(b))b=\"float\";b=b.replace(lb,\"-\$1\").toLowerCase();e=a.ownerDocument.defaultView;if(!e)return null;if(a=e.getComputedStyle(a,null))f=
a.getPropertyValue(b);if(b===\"opacity\"&&f===\"\")f=\"1\"}else if(a.currentStyle){d=b.replace(ia,ja);f=a.currentStyle[b]||a.currentStyle[d];if(!mb.test(f)&&nb.test(f)){b=e.left;var j=a.runtimeStyle.left;a.runtimeStyle.left=a.currentStyle.left;e.left=d===\"fontSize\"?\"1em\":f||0;f=e.pixelLeft+\"px\";e.left=b;a.runtimeStyle.left=j}}return f},swap:function(a,b,d){var f={};for(var e in b){f[e]=a.style[e];a.style[e]=b[e]}d.call(a);for(e in b)a.style[e]=f[e]}});if(c.expr&&c.expr.filters){c.expr.filters.hidden=function(a){var b=
a.offsetWidth,d=a.offsetHeight,f=a.nodeName.toLowerCase()===\"tr\";return b===0&&d===0&&!f?true:b>0&&d>0&&!f?false:c.curCSS(a,\"display\")===\"none\"};c.expr.filters.visible=function(a){return!c.expr.filters.hidden(a)}}var sb=J(),tb=/<script(.|\\s)*?\\/script>/gi,ub=/select|textarea/i,vb=/color|date|datetime|email|hidden|month|number|password|range|search|tel|text|time|url|week/i,N=/=\\?(&|\$)/,ka=/\\?/,wb=/(\\?|&)_=.*?(&|\$)/,xb=/^(\\w+:)?\\/\\/([^\\/?#]+)/,yb=/%20/g,zb=c.fn.load;c.fn.extend({load:function(a,b,d){if(typeof a!==
\"string\")return zb.call(this,a);else if(!this.length)return this;var f=a.indexOf(\" \");if(f>=0){var e=a.slice(f,a.length);a=a.slice(0,f)}f=\"GET\";if(b)if(c.isFunction(b)){d=b;b=null}else if(typeof b===\"object\"){b=c.param(b,c.ajaxSettings.traditional);f=\"POST\"}var j=this;c.ajax({url:a,type:f,dataType:\"html\",data:b,complete:function(i,o){if(o===\"success\"||o===\"notmodified\")j.html(e?c(\"<div />\").append(i.responseText.replace(tb,\"\")).find(e):i.responseText);d&&j.each(d,[i.responseText,o,i])}});return this},
serialize:function(){return c.param(this.serializeArray())},serializeArray:function(){return this.map(function(){return this.elements?c.makeArray(this.elements):this}).filter(function(){return this.name&&!this.disabled&&(this.checked||ub.test(this.nodeName)||vb.test(this.type))}).map(function(a,b){a=c(this).val();return a==null?null:c.isArray(a)?c.map(a,function(d){return{name:b.name,value:d}}):{name:b.name,value:a}}).get()}});c.each(\"ajaxStart ajaxStop ajaxComplete ajaxError ajaxSuccess ajaxSend\".split(\" \"),
function(a,b){c.fn[b]=function(d){return this.bind(b,d)}});c.extend({get:function(a,b,d,f){if(c.isFunction(b)){f=f||d;d=b;b=null}return c.ajax({type:\"GET\",url:a,data:b,success:d,dataType:f})},getScript:function(a,b){return c.get(a,null,b,\"script\")},getJSON:function(a,b,d){return c.get(a,b,d,\"json\")},post:function(a,b,d,f){if(c.isFunction(b)){f=f||d;d=b;b={}}return c.ajax({type:\"POST\",url:a,data:b,success:d,dataType:f})},ajaxSetup:function(a){c.extend(c.ajaxSettings,a)},ajaxSettings:{url:location.href,
global:true,type:\"GET\",contentType:\"application/x-www-form-urlencoded\",processData:true,async:true,xhr:A.XMLHttpRequest&&(A.location.protocol!==\"file:\"||!A.ActiveXObject)?function(){return new A.XMLHttpRequest}:function(){try{return new A.ActiveXObject(\"Microsoft.XMLHTTP\")}catch(a){}},accepts:{xml:\"application/xml, text/xml\",html:\"text/html\",script:\"text/javascript, application/javascript\",json:\"application/json, text/javascript\",text:\"text/plain\",_default:\"*/*\"}},lastModified:{},etag:{},ajax:function(a){function b(){e.success&&
e.success.call(k,o,i,x);e.global&&f(\"ajaxSuccess\",[x,e])}function d(){e.complete&&e.complete.call(k,x,i);e.global&&f(\"ajaxComplete\",[x,e]);e.global&&!--c.active&&c.event.trigger(\"ajaxStop\")}function f(q,p){(e.context?c(e.context):c.event).trigger(q,p)}var e=c.extend(true,{},c.ajaxSettings,a),j,i,o,k=a&&a.context||e,n=e.type.toUpperCase();if(e.data&&e.processData&&typeof e.data!==\"string\")e.data=c.param(e.data,e.traditional);if(e.dataType===\"jsonp\"){if(n===\"GET\")N.test(e.url)||(e.url+=(ka.test(e.url)?
\"&\":\"?\")+(e.jsonp||\"callback\")+\"=?\");else if(!e.data||!N.test(e.data))e.data=(e.data?e.data+\"&\":\"\")+(e.jsonp||\"callback\")+\"=?\";e.dataType=\"json\"}if(e.dataType===\"json\"&&(e.data&&N.test(e.data)||N.test(e.url))){j=e.jsonpCallback||\"jsonp\"+sb++;if(e.data)e.data=(e.data+\"\").replace(N,\"=\"+j+\"\$1\");e.url=e.url.replace(N,\"=\"+j+\"\$1\");e.dataType=\"script\";A[j]=A[j]||function(q){o=q;b();d();A[j]=w;try{delete A[j]}catch(p){}z&&z.removeChild(C)}}if(e.dataType===\"script\"&&e.cache===null)e.cache=false;if(e.cache===
false&&n===\"GET\"){var r=J(),u=e.url.replace(wb,\"\$1_=\"+r+\"\$2\");e.url=u+(u===e.url?(ka.test(e.url)?\"&\":\"?\")+\"_=\"+r:\"\")}if(e.data&&n===\"GET\")e.url+=(ka.test(e.url)?\"&\":\"?\")+e.data;e.global&&!c.active++&&c.event.trigger(\"ajaxStart\");r=(r=xb.exec(e.url))&&(r[1]&&r[1]!==location.protocol||r[2]!==location.host);if(e.dataType===\"script\"&&n===\"GET\"&&r){var z=s.getElementsByTagName(\"head\")[0]||s.documentElement,C=s.createElement(\"script\");C.src=e.url;if(e.scriptCharset)C.charset=e.scriptCharset;if(!j){var B=
false;C.onload=C.onreadystatechange=function(){if(!B&&(!this.readyState||this.readyState===\"loaded\"||this.readyState===\"complete\")){B=true;b();d();C.onload=C.onreadystatechange=null;z&&C.parentNode&&z.removeChild(C)}}}z.insertBefore(C,z.firstChild);return w}var E=false,x=e.xhr();if(x){e.username?x.open(n,e.url,e.async,e.username,e.password):x.open(n,e.url,e.async);try{if(e.data||a&&a.contentType)x.setRequestHeader(\"Content-Type\",e.contentType);if(e.ifModified){c.lastModified[e.url]&&x.setRequestHeader(\"If-Modified-Since\",
c.lastModified[e.url]);c.etag[e.url]&&x.setRequestHeader(\"If-None-Match\",c.etag[e.url])}r||x.setRequestHeader(\"X-Requested-With\",\"XMLHttpRequest\");x.setRequestHeader(\"Accept\",e.dataType&&e.accepts[e.dataType]?e.accepts[e.dataType]+\", */*\":e.accepts._default)}catch(ga){}if(e.beforeSend&&e.beforeSend.call(k,x,e)===false){e.global&&!--c.active&&c.event.trigger(\"ajaxStop\");x.abort();return false}e.global&&f(\"ajaxSend\",[x,e]);var g=x.onreadystatechange=function(q){if(!x||x.readyState===0||q===\"abort\"){E||
d();E=true;if(x)x.onreadystatechange=c.noop}else if(!E&&x&&(x.readyState===4||q===\"timeout\")){E=true;x.onreadystatechange=c.noop;i=q===\"timeout\"?\"timeout\":!c.httpSuccess(x)?\"error\":e.ifModified&&c.httpNotModified(x,e.url)?\"notmodified\":\"success\";var p;if(i===\"success\")try{o=c.httpData(x,e.dataType,e)}catch(v){i=\"parsererror\";p=v}if(i===\"success\"||i===\"notmodified\")j||b();else c.handleError(e,x,i,p);d();q===\"timeout\"&&x.abort();if(e.async)x=null}};try{var h=x.abort;x.abort=function(){x&&h.call(x);
g(\"abort\")}}catch(l){}e.async&&e.timeout>0&&setTimeout(function(){x&&!E&&g(\"timeout\")},e.timeout);try{x.send(n===\"POST\"||n===\"PUT\"||n===\"DELETE\"?e.data:null)}catch(m){c.handleError(e,x,null,m);d()}e.async||g();return x}},handleError:function(a,b,d,f){if(a.error)a.error.call(a.context||a,b,d,f);if(a.global)(a.context?c(a.context):c.event).trigger(\"ajaxError\",[b,a,f])},active:0,httpSuccess:function(a){try{return!a.status&&location.protocol===\"file:\"||a.status>=200&&a.status<300||a.status===304||a.status===
1223||a.status===0}catch(b){}return false},httpNotModified:function(a,b){var d=a.getResponseHeader(\"Last-Modified\"),f=a.getResponseHeader(\"Etag\");if(d)c.lastModified[b]=d;if(f)c.etag[b]=f;return a.status===304||a.status===0},httpData:function(a,b,d){var f=a.getResponseHeader(\"content-type\")||\"\",e=b===\"xml\"||!b&&f.indexOf(\"xml\")>=0;a=e?a.responseXML:a.responseText;e&&a.documentElement.nodeName===\"parsererror\"&&c.error(\"parsererror\");if(d&&d.dataFilter)a=d.dataFilter(a,b);if(typeof a===\"string\")if(b===
\"json\"||!b&&f.indexOf(\"json\")>=0)a=c.parseJSON(a);else if(b===\"script\"||!b&&f.indexOf(\"javascript\")>=0)c.globalEval(a);return a},param:function(a,b){function d(i,o){if(c.isArray(o))c.each(o,function(k,n){b||/\\[\\]\$/.test(i)?f(i,n):d(i+\"[\"+(typeof n===\"object\"||c.isArray(n)?k:\"\")+\"]\",n)});else!b&&o!=null&&typeof o===\"object\"?c.each(o,function(k,n){d(i+\"[\"+k+\"]\",n)}):f(i,o)}function f(i,o){o=c.isFunction(o)?o():o;e[e.length]=encodeURIComponent(i)+\"=\"+encodeURIComponent(o)}var e=[];if(b===w)b=c.ajaxSettings.traditional;
if(c.isArray(a)||a.jquery)c.each(a,function(){f(this.name,this.value)});else for(var j in a)d(j,a[j]);return e.join(\"&\").replace(yb,\"+\")}});var la={},Ab=/toggle|show|hide/,Bb=/^([+-]=)?([\\d+-.]+)(.*)\$/,W,va=[[\"height\",\"marginTop\",\"marginBottom\",\"paddingTop\",\"paddingBottom\"],[\"width\",\"marginLeft\",\"marginRight\",\"paddingLeft\",\"paddingRight\"],[\"opacity\"]];c.fn.extend({show:function(a,b){if(a||a===0)return this.animate(K(\"show\",3),a,b);else{a=0;for(b=this.length;a<b;a++){var d=c.data(this[a],\"olddisplay\");
this[a].style.display=d||\"\";if(c.css(this[a],\"display\")===\"none\"){d=this[a].nodeName;var f;if(la[d])f=la[d];else{var e=c(\"<\"+d+\" />\").appendTo(\"body\");f=e.css(\"display\");if(f===\"none\")f=\"block\";e.remove();la[d]=f}c.data(this[a],\"olddisplay\",f)}}a=0;for(b=this.length;a<b;a++)this[a].style.display=c.data(this[a],\"olddisplay\")||\"\";return this}},hide:function(a,b){if(a||a===0)return this.animate(K(\"hide\",3),a,b);else{a=0;for(b=this.length;a<b;a++){var d=c.data(this[a],\"olddisplay\");!d&&d!==\"none\"&&c.data(this[a],
\"olddisplay\",c.css(this[a],\"display\"))}a=0;for(b=this.length;a<b;a++)this[a].style.display=\"none\";return this}},_toggle:c.fn.toggle,toggle:function(a,b){var d=typeof a===\"boolean\";if(c.isFunction(a)&&c.isFunction(b))this._toggle.apply(this,arguments);else a==null||d?this.each(function(){var f=d?a:c(this).is(\":hidden\");c(this)[f?\"show\":\"hide\"]()}):this.animate(K(\"toggle\",3),a,b);return this},fadeTo:function(a,b,d){return this.filter(\":hidden\").css(\"opacity\",0).show().end().animate({opacity:b},a,d)},
animate:function(a,b,d,f){var e=c.speed(b,d,f);if(c.isEmptyObject(a))return this.each(e.complete);return this[e.queue===false?\"each\":\"queue\"](function(){var j=c.extend({},e),i,o=this.nodeType===1&&c(this).is(\":hidden\"),k=this;for(i in a){var n=i.replace(ia,ja);if(i!==n){a[n]=a[i];delete a[i];i=n}if(a[i]===\"hide\"&&o||a[i]===\"show\"&&!o)return j.complete.call(this);if((i===\"height\"||i===\"width\")&&this.style){j.display=c.css(this,\"display\");j.overflow=this.style.overflow}if(c.isArray(a[i])){(j.specialEasing=
j.specialEasing||{})[i]=a[i][1];a[i]=a[i][0]}}if(j.overflow!=null)this.style.overflow=\"hidden\";j.curAnim=c.extend({},a);c.each(a,function(r,u){var z=new c.fx(k,j,r);if(Ab.test(u))z[u===\"toggle\"?o?\"show\":\"hide\":u](a);else{var C=Bb.exec(u),B=z.cur(true)||0;if(C){u=parseFloat(C[2]);var E=C[3]||\"px\";if(E!==\"px\"){k.style[r]=(u||1)+E;B=(u||1)/z.cur(true)*B;k.style[r]=B+E}if(C[1])u=(C[1]===\"-=\"?-1:1)*u+B;z.custom(B,u,E)}else z.custom(B,u,\"\")}});return true})},stop:function(a,b){var d=c.timers;a&&this.queue([]);
this.each(function(){for(var f=d.length-1;f>=0;f--)if(d[f].elem===this){b&&d[f](true);d.splice(f,1)}});b||this.dequeue();return this}});c.each({slideDown:K(\"show\",1),slideUp:K(\"hide\",1),slideToggle:K(\"toggle\",1),fadeIn:{opacity:\"show\"},fadeOut:{opacity:\"hide\"}},function(a,b){c.fn[a]=function(d,f){return this.animate(b,d,f)}});c.extend({speed:function(a,b,d){var f=a&&typeof a===\"object\"?a:{complete:d||!d&&b||c.isFunction(a)&&a,duration:a,easing:d&&b||b&&!c.isFunction(b)&&b};f.duration=c.fx.off?0:typeof f.duration===
\"number\"?f.duration:c.fx.speeds[f.duration]||c.fx.speeds._default;f.old=f.complete;f.complete=function(){f.queue!==false&&c(this).dequeue();c.isFunction(f.old)&&f.old.call(this)};return f},easing:{linear:function(a,b,d,f){return d+f*a},swing:function(a,b,d,f){return(-Math.cos(a*Math.PI)/2+0.5)*f+d}},timers:[],fx:function(a,b,d){this.options=b;this.elem=a;this.prop=d;if(!b.orig)b.orig={}}});c.fx.prototype={update:function(){this.options.step&&this.options.step.call(this.elem,this.now,this);(c.fx.step[this.prop]||
c.fx.step._default)(this);if((this.prop===\"height\"||this.prop===\"width\")&&this.elem.style)this.elem.style.display=\"block\"},cur:function(a){if(this.elem[this.prop]!=null&&(!this.elem.style||this.elem.style[this.prop]==null))return this.elem[this.prop];return(a=parseFloat(c.css(this.elem,this.prop,a)))&&a>-10000?a:parseFloat(c.curCSS(this.elem,this.prop))||0},custom:function(a,b,d){function f(j){return e.step(j)}this.startTime=J();this.start=a;this.end=b;this.unit=d||this.unit||\"px\";this.now=this.start;
this.pos=this.state=0;var e=this;f.elem=this.elem;if(f()&&c.timers.push(f)&&!W)W=setInterval(c.fx.tick,13)},show:function(){this.options.orig[this.prop]=c.style(this.elem,this.prop);this.options.show=true;this.custom(this.prop===\"width\"||this.prop===\"height\"?1:0,this.cur());c(this.elem).show()},hide:function(){this.options.orig[this.prop]=c.style(this.elem,this.prop);this.options.hide=true;this.custom(this.cur(),0)},step:function(a){var b=J(),d=true;if(a||b>=this.options.duration+this.startTime){this.now=
this.end;this.pos=this.state=1;this.update();this.options.curAnim[this.prop]=true;for(var f in this.options.curAnim)if(this.options.curAnim[f]!==true)d=false;if(d){if(this.options.display!=null){this.elem.style.overflow=this.options.overflow;a=c.data(this.elem,\"olddisplay\");this.elem.style.display=a?a:this.options.display;if(c.css(this.elem,\"display\")===\"none\")this.elem.style.display=\"block\"}this.options.hide&&c(this.elem).hide();if(this.options.hide||this.options.show)for(var e in this.options.curAnim)c.style(this.elem,
e,this.options.orig[e]);this.options.complete.call(this.elem)}return false}else{e=b-this.startTime;this.state=e/this.options.duration;a=this.options.easing||(c.easing.swing?\"swing\":\"linear\");this.pos=c.easing[this.options.specialEasing&&this.options.specialEasing[this.prop]||a](this.state,e,0,1,this.options.duration);this.now=this.start+(this.end-this.start)*this.pos;this.update()}return true}};c.extend(c.fx,{tick:function(){for(var a=c.timers,b=0;b<a.length;b++)a[b]()||a.splice(b--,1);a.length||
c.fx.stop()},stop:function(){clearInterval(W);W=null},speeds:{slow:600,fast:200,_default:400},step:{opacity:function(a){c.style(a.elem,\"opacity\",a.now)},_default:function(a){if(a.elem.style&&a.elem.style[a.prop]!=null)a.elem.style[a.prop]=(a.prop===\"width\"||a.prop===\"height\"?Math.max(0,a.now):a.now)+a.unit;else a.elem[a.prop]=a.now}}});if(c.expr&&c.expr.filters)c.expr.filters.animated=function(a){return c.grep(c.timers,function(b){return a===b.elem}).length};c.fn.offset=\"getBoundingClientRect\"in s.documentElement?
function(a){var b=this[0];if(a)return this.each(function(e){c.offset.setOffset(this,a,e)});if(!b||!b.ownerDocument)return null;if(b===b.ownerDocument.body)return c.offset.bodyOffset(b);var d=b.getBoundingClientRect(),f=b.ownerDocument;b=f.body;f=f.documentElement;return{top:d.top+(self.pageYOffset||c.support.boxModel&&f.scrollTop||b.scrollTop)-(f.clientTop||b.clientTop||0),left:d.left+(self.pageXOffset||c.support.boxModel&&f.scrollLeft||b.scrollLeft)-(f.clientLeft||b.clientLeft||0)}}:function(a){var b=
this[0];if(a)return this.each(function(r){c.offset.setOffset(this,a,r)});if(!b||!b.ownerDocument)return null;if(b===b.ownerDocument.body)return c.offset.bodyOffset(b);c.offset.initialize();var d=b.offsetParent,f=b,e=b.ownerDocument,j,i=e.documentElement,o=e.body;f=(e=e.defaultView)?e.getComputedStyle(b,null):b.currentStyle;for(var k=b.offsetTop,n=b.offsetLeft;(b=b.parentNode)&&b!==o&&b!==i;){if(c.offset.supportsFixedPosition&&f.position===\"fixed\")break;j=e?e.getComputedStyle(b,null):b.currentStyle;
k-=b.scrollTop;n-=b.scrollLeft;if(b===d){k+=b.offsetTop;n+=b.offsetLeft;if(c.offset.doesNotAddBorder&&!(c.offset.doesAddBorderForTableAndCells&&/^t(able|d|h)\$/i.test(b.nodeName))){k+=parseFloat(j.borderTopWidth)||0;n+=parseFloat(j.borderLeftWidth)||0}f=d;d=b.offsetParent}if(c.offset.subtractsBorderForOverflowNotVisible&&j.overflow!==\"visible\"){k+=parseFloat(j.borderTopWidth)||0;n+=parseFloat(j.borderLeftWidth)||0}f=j}if(f.position===\"relative\"||f.position===\"static\"){k+=o.offsetTop;n+=o.offsetLeft}if(c.offset.supportsFixedPosition&&
f.position===\"fixed\"){k+=Math.max(i.scrollTop,o.scrollTop);n+=Math.max(i.scrollLeft,o.scrollLeft)}return{top:k,left:n}};c.offset={initialize:function(){var a=s.body,b=s.createElement(\"div\"),d,f,e,j=parseFloat(c.curCSS(a,\"marginTop\",true))||0;c.extend(b.style,{position:\"absolute\",top:0,left:0,margin:0,border:0,width:\"1px\",height:\"1px\",visibility:\"hidden\"});b.innerHTML=\"<div style='position:absolute;top:0;left:0;margin:0;border:5px solid #000;padding:0;width:1px;height:1px;'><div></div></div><table style='position:absolute;top:0;left:0;margin:0;border:5px solid #000;padding:0;width:1px;height:1px;' cellpadding='0' cellspacing='0'><tr><td></td></tr></table>\";
a.insertBefore(b,a.firstChild);d=b.firstChild;f=d.firstChild;e=d.nextSibling.firstChild.firstChild;this.doesNotAddBorder=f.offsetTop!==5;this.doesAddBorderForTableAndCells=e.offsetTop===5;f.style.position=\"fixed\";f.style.top=\"20px\";this.supportsFixedPosition=f.offsetTop===20||f.offsetTop===15;f.style.position=f.style.top=\"\";d.style.overflow=\"hidden\";d.style.position=\"relative\";this.subtractsBorderForOverflowNotVisible=f.offsetTop===-5;this.doesNotIncludeMarginInBodyOffset=a.offsetTop!==j;a.removeChild(b);
c.offset.initialize=c.noop},bodyOffset:function(a){var b=a.offsetTop,d=a.offsetLeft;c.offset.initialize();if(c.offset.doesNotIncludeMarginInBodyOffset){b+=parseFloat(c.curCSS(a,\"marginTop\",true))||0;d+=parseFloat(c.curCSS(a,\"marginLeft\",true))||0}return{top:b,left:d}},setOffset:function(a,b,d){if(/static/.test(c.curCSS(a,\"position\")))a.style.position=\"relative\";var f=c(a),e=f.offset(),j=parseInt(c.curCSS(a,\"top\",true),10)||0,i=parseInt(c.curCSS(a,\"left\",true),10)||0;if(c.isFunction(b))b=b.call(a,
d,e);d={top:b.top-e.top+j,left:b.left-e.left+i};\"using\"in b?b.using.call(a,d):f.css(d)}};c.fn.extend({position:function(){if(!this[0])return null;var a=this[0],b=this.offsetParent(),d=this.offset(),f=/^body|html\$/i.test(b[0].nodeName)?{top:0,left:0}:b.offset();d.top-=parseFloat(c.curCSS(a,\"marginTop\",true))||0;d.left-=parseFloat(c.curCSS(a,\"marginLeft\",true))||0;f.top+=parseFloat(c.curCSS(b[0],\"borderTopWidth\",true))||0;f.left+=parseFloat(c.curCSS(b[0],\"borderLeftWidth\",true))||0;return{top:d.top-
f.top,left:d.left-f.left}},offsetParent:function(){return this.map(function(){for(var a=this.offsetParent||s.body;a&&!/^body|html\$/i.test(a.nodeName)&&c.css(a,\"position\")===\"static\";)a=a.offsetParent;return a})}});c.each([\"Left\",\"Top\"],function(a,b){var d=\"scroll\"+b;c.fn[d]=function(f){var e=this[0],j;if(!e)return null;if(f!==w)return this.each(function(){if(j=wa(this))j.scrollTo(!a?f:c(j).scrollLeft(),a?f:c(j).scrollTop());else this[d]=f});else return(j=wa(e))?\"pageXOffset\"in j?j[a?\"pageYOffset\":
\"pageXOffset\"]:c.support.boxModel&&j.document.documentElement[d]||j.document.body[d]:e[d]}});c.each([\"Height\",\"Width\"],function(a,b){var d=b.toLowerCase();c.fn[\"inner\"+b]=function(){return this[0]?c.css(this[0],d,false,\"padding\"):null};c.fn[\"outer\"+b]=function(f){return this[0]?c.css(this[0],d,false,f?\"margin\":\"border\"):null};c.fn[d]=function(f){var e=this[0];if(!e)return f==null?null:this;if(c.isFunction(f))return this.each(function(j){var i=c(this);i[d](f.call(this,j,i[d]()))});return\"scrollTo\"in
e&&e.document?e.document.compatMode===\"CSS1Compat\"&&e.document.documentElement[\"client\"+b]||e.document.body[\"client\"+b]:e.nodeType===9?Math.max(e.documentElement[\"client\"+b],e.body[\"scroll\"+b],e.documentElement[\"scroll\"+b],e.body[\"offset\"+b],e.documentElement[\"offset\"+b]):f===w?c.css(e,d):this.css(d,typeof f===\"string\"?f:f+\"px\")}});A.jQuery=A.\$=c})(window);
</script>
<script>
      \$(function() {
        \$(\".class-name\").click(function(obj) {
          var collapseTarget = jQuery(obj.target).parents(\".object:first\");
          jQuery(collapseTarget).find(\".class-properties\").toggleClass(\"collapsed\");
          jQuery(collapseTarget).find(\".collapse\").toggleClass(\"collapsed\");
        });
        \$(\".property-name\").click(function(obj) {
          var collapseTarget = jQuery(obj.target).next(\".property-value\");
          jQuery(collapseTarget).find(\".variable\").toggleClass(\"collapsed\");
          jQuery(collapseTarget).find(\".collapse\").toggleClass(\"collapsed\");
        });
        \$(\".array-key.collapsable\").click(function(obj) {
          var collapseTarget = jQuery(obj.target).parents(\".array-node:first\").find(\".array-value\");
          jQuery(collapseTarget).find(\".variable\").toggleClass(\"collapsed\");
          jQuery(collapseTarget).find(\".collapse\").toggleClass(\"collapsed\");
        });
        \$(\".variable-name\").click(function(obj) {
          var collapseTarget = jQuery(obj.target).parents(\".variable:first\");
          jQuery(collapseTarget).find(\".variable-value\").toggleClass(\"collapsed\");
          jQuery(collapseTarget).next(\".collapse\").toggleClass(\"collapsed\");
        });
        \$(\".control\").click(function(obj) {
          var collapseTarget = jQuery(obj.target).parents(\".chunk:first\");
          jQuery(collapseTarget).find(\".dump\").toggleClass(\"collapsed\");
          jQuery(collapseTarget).find(\".control\").toggleClass(\"hidden\");
        });
      });
  </script>
</head>
<body>
</body>
</html>";
  }


  protected function getPanelHtml() {
    return "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\" \"http://www.w3.org/TR/REC-html40/loose.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">
<head>
<meta http-equiv=\"Content-Type\" content=\"text/html;charset=UTF-8\">
<title>Be awesome instead</title>
<script type=\"text/javascript\">
/*!
 * jQuery JavaScript Library v1.4.2
 * http://jquery.com/
 *
 * Copyright 2010, John Resig
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * Includes Sizzle.js
 * http://sizzlejs.com/
 * Copyright 2010, The Dojo Foundation
 * Released under the MIT, BSD, and GPL Licenses.
 *
 * Date: Sat Feb 13 22:33:48 2010 -0500 begin_of_the_skype_highlighting              48 2010 -0500      end_of_the_skype_highlighting
 */
(function(A,w){function ma(){if(!c.isReady){try{s.documentElement.doScroll(\"left\")}catch(a){setTimeout(ma,1);return}c.ready()}}function Qa(a,b){b.src?c.ajax({url:b.src,async:false,dataType:\"script\"}):c.globalEval(b.text||b.textContent||b.innerHTML||\"\");b.parentNode&&b.parentNode.removeChild(b)}function X(a,b,d,f,e,j){var i=a.length;if(typeof b===\"object\"){for(var o in b)X(a,o,b[o],f,e,d);return a}if(d!==w){f=!j&&f&&c.isFunction(d);for(o=0;o<i;o++)e(a[o],b,f?d.call(a[o],o,e(a[o],b)):d,j);return a}return i?
e(a[0],b):w}function J(){return(new Date).getTime()}function Y(){return false}function Z(){return true}function na(a,b,d){d[0].type=a;return c.event.handle.apply(b,d)}function oa(a){var b,d=[],f=[],e=arguments,j,i,o,k,n,r;i=c.data(this,\"events\");if(!(a.liveFired===this||!i||!i.live||a.button&&a.type===\"click\")){a.liveFired=this;var u=i.live.slice(0);for(k=0;k<u.length;k++){i=u[k];i.origType.replace(O,\"\")===a.type?f.push(i.selector):u.splice(k--,1)}j=c(a.target).closest(f,a.currentTarget);n=0;for(r=
j.length;n<r;n++)for(k=0;k<u.length;k++){i=u[k];if(j[n].selector===i.selector){o=j[n].elem;f=null;if(i.preType===\"mouseenter\"||i.preType===\"mouseleave\")f=c(a.relatedTarget).closest(i.selector)[0];if(!f||f!==o)d.push({elem:o,handleObj:i})}}n=0;for(r=d.length;n<r;n++){j=d[n];a.currentTarget=j.elem;a.data=j.handleObj.data;a.handleObj=j.handleObj;if(j.handleObj.origHandler.apply(j.elem,e)===false){b=false;break}}return b}}function pa(a,b){return\"live.\"+(a&&a!==\"*\"?a+\".\":\"\")+b.replace(/\\./g,\"`\").replace(/ /g,
\"&\")}function qa(a){return!a||!a.parentNode||a.parentNode.nodeType===11}function ra(a,b){var d=0;b.each(function(){if(this.nodeName===(a[d]&&a[d].nodeName)){var f=c.data(a[d++]),e=c.data(this,f);if(f=f&&f.events){delete e.handle;e.events={};for(var j in f)for(var i in f[j])c.event.add(this,j,f[j][i],f[j][i].data)}}})}function sa(a,b,d){var f,e,j;b=b&&b[0]?b[0].ownerDocument||b[0]:s;if(a.length===1&&typeof a[0]===\"string\"&&a[0].length<512&&b===s&&!ta.test(a[0])&&(c.support.checkClone||!ua.test(a[0]))){e=
true;if(j=c.fragments[a[0]])if(j!==1)f=j}if(!f){f=b.createDocumentFragment();c.clean(a,b,f,d)}if(e)c.fragments[a[0]]=j?f:1;return{fragment:f,cacheable:e}}function K(a,b){var d={};c.each(va.concat.apply([],va.slice(0,b)),function(){d[this]=a});return d}function wa(a){return\"scrollTo\"in a&&a.document?a:a.nodeType===9?a.defaultView||a.parentWindow:false}var c=function(a,b){return new c.fn.init(a,b)},Ra=A.jQuery,Sa=A.\$,s=A.document,T,Ta=/^[^<]*(<[\\w\\W]+>)[^>]*\$|^#([\\w-]+)\$/,Ua=/^.[^:#\\[\\.,]*\$/,Va=/\\S/,
Wa=/^(\\s|\\u00A0)+|(\\s|\\u00A0)+\$/g,Xa=/^<(\\w+)\\s*\\/?>(?:<\\/\\1>)?\$/,P=navigator.userAgent,xa=false,Q=[],L,\$=Object.prototype.toString,aa=Object.prototype.hasOwnProperty,ba=Array.prototype.push,R=Array.prototype.slice,ya=Array.prototype.indexOf;c.fn=c.prototype={init:function(a,b){var d,f;if(!a)return this;if(a.nodeType){this.context=this[0]=a;this.length=1;return this}if(a===\"body\"&&!b){this.context=s;this[0]=s.body;this.selector=\"body\";this.length=1;return this}if(typeof a===\"string\")if((d=Ta.exec(a))&&
(d[1]||!b))if(d[1]){f=b?b.ownerDocument||b:s;if(a=Xa.exec(a))if(c.isPlainObject(b)){a=[s.createElement(a[1])];c.fn.attr.call(a,b,true)}else a=[f.createElement(a[1])];else{a=sa([d[1]],[f]);a=(a.cacheable?a.fragment.cloneNode(true):a.fragment).childNodes}return c.merge(this,a)}else{if(b=s.getElementById(d[2])){if(b.id!==d[2])return T.find(a);this.length=1;this[0]=b}this.context=s;this.selector=a;return this}else if(!b&&/^\\w+\$/.test(a)){this.selector=a;this.context=s;a=s.getElementsByTagName(a);return c.merge(this,
a)}else return!b||b.jquery?(b||T).find(a):c(b).find(a);else if(c.isFunction(a))return T.ready(a);if(a.selector!==w){this.selector=a.selector;this.context=a.context}return c.makeArray(a,this)},selector:\"\",jquery:\"1.4.2\",length:0,size:function(){return this.length},toArray:function(){return R.call(this,0)},get:function(a){return a==null?this.toArray():a<0?this.slice(a)[0]:this[a]},pushStack:function(a,b,d){var f=c();c.isArray(a)?ba.apply(f,a):c.merge(f,a);f.prevObject=this;f.context=this.context;if(b===
\"find\")f.selector=this.selector+(this.selector?\" \":\"\")+d;else if(b)f.selector=this.selector+\".\"+b+\"(\"+d+\")\";return f},each:function(a,b){return c.each(this,a,b)},ready:function(a){c.bindReady();if(c.isReady)a.call(s,c);else Q&&Q.push(a);return this},eq:function(a){return a===-1?this.slice(a):this.slice(a,+a+1)},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},slice:function(){return this.pushStack(R.apply(this,arguments),\"slice\",R.call(arguments).join(\",\"))},map:function(a){return this.pushStack(c.map(this,
function(b,d){return a.call(b,d,b)}))},end:function(){return this.prevObject||c(null)},push:ba,sort:[].sort,splice:[].splice};c.fn.init.prototype=c.fn;c.extend=c.fn.extend=function(){var a=arguments[0]||{},b=1,d=arguments.length,f=false,e,j,i,o;if(typeof a===\"boolean\"){f=a;a=arguments[1]||{};b=2}if(typeof a!==\"object\"&&!c.isFunction(a))a={};if(d===b){a=this;--b}for(;b<d;b++)if((e=arguments[b])!=null)for(j in e){i=a[j];o=e[j];if(a!==o)if(f&&o&&(c.isPlainObject(o)||c.isArray(o))){i=i&&(c.isPlainObject(i)||
c.isArray(i))?i:c.isArray(o)?[]:{};a[j]=c.extend(f,i,o)}else if(o!==w)a[j]=o}return a};c.extend({noConflict:function(a){A.\$=Sa;if(a)A.jQuery=Ra;return c},isReady:false,ready:function(){if(!c.isReady){if(!s.body)return setTimeout(c.ready,13);c.isReady=true;if(Q){for(var a,b=0;a=Q[b++];)a.call(s,c);Q=null}c.fn.triggerHandler&&c(s).triggerHandler(\"ready\")}},bindReady:function(){if(!xa){xa=true;if(s.readyState===\"complete\")return c.ready();if(s.addEventListener){s.addEventListener(\"DOMContentLoaded\",
L,false);A.addEventListener(\"load\",c.ready,false)}else if(s.attachEvent){s.attachEvent(\"onreadystatechange\",L);A.attachEvent(\"onload\",c.ready);var a=false;try{a=A.frameElement==null}catch(b){}s.documentElement.doScroll&&a&&ma()}}},isFunction:function(a){return \$.call(a)===\"[object Function]\"},isArray:function(a){return \$.call(a)===\"[object Array]\"},isPlainObject:function(a){if(!a||\$.call(a)!==\"[object Object]\"||a.nodeType||a.setInterval)return false;if(a.constructor&&!aa.call(a,\"constructor\")&&!aa.call(a.constructor.prototype,
\"isPrototypeOf\"))return false;var b;for(b in a);return b===w||aa.call(a,b)},isEmptyObject:function(a){for(var b in a)return false;return true},error:function(a){throw a;},parseJSON:function(a){if(typeof a!==\"string\"||!a)return null;a=c.trim(a);if(/^[\\],:{}\\s]*\$/.test(a.replace(/\\\\(?:[\"\\\\\\/bfnrt]|u[0-9a-fA-F]{4})/g,\"@\").replace(/\"[^\"\\\\\\n\\r]*\"|true|false|null|-?\\d+(?:\\.\\d*)?(?:[eE][+\\-]?\\d+)?/g,\"]\").replace(/(?:^|:|,)(?:\\s*\\[)+/g,\"\")))return A.JSON&&A.JSON.parse?A.JSON.parse(a):(new Function(\"return \"+
a))();else c.error(\"Invalid JSON: \"+a)},noop:function(){},globalEval:function(a){if(a&&Va.test(a)){var b=s.getElementsByTagName(\"head\")[0]||s.documentElement,d=s.createElement(\"script\");d.type=\"text/javascript\";if(c.support.scriptEval)d.appendChild(s.createTextNode(a));else d.text=a;b.insertBefore(d,b.firstChild);b.removeChild(d)}},nodeName:function(a,b){return a.nodeName&&a.nodeName.toUpperCase()===b.toUpperCase()},each:function(a,b,d){var f,e=0,j=a.length,i=j===w||c.isFunction(a);if(d)if(i)for(f in a){if(b.apply(a[f],
d)===false)break}else for(;e<j;){if(b.apply(a[e++],d)===false)break}else if(i)for(f in a){if(b.call(a[f],f,a[f])===false)break}else for(d=a[0];e<j&&b.call(d,e,d)!==false;d=a[++e]);return a},trim:function(a){return(a||\"\").replace(Wa,\"\")},makeArray:function(a,b){b=b||[];if(a!=null)a.length==null||typeof a===\"string\"||c.isFunction(a)||typeof a!==\"function\"&&a.setInterval?ba.call(b,a):c.merge(b,a);return b},inArray:function(a,b){if(b.indexOf)return b.indexOf(a);for(var d=0,f=b.length;d<f;d++)if(b[d]===
a)return d;return-1},merge:function(a,b){var d=a.length,f=0;if(typeof b.length===\"number\")for(var e=b.length;f<e;f++)a[d++]=b[f];else for(;b[f]!==w;)a[d++]=b[f++];a.length=d;return a},grep:function(a,b,d){for(var f=[],e=0,j=a.length;e<j;e++)!d!==!b(a[e],e)&&f.push(a[e]);return f},map:function(a,b,d){for(var f=[],e,j=0,i=a.length;j<i;j++){e=b(a[j],j,d);if(e!=null)f[f.length]=e}return f.concat.apply([],f)},guid:1,proxy:function(a,b,d){if(arguments.length===2)if(typeof b===\"string\"){d=a;a=d[b];b=w}else if(b&&
!c.isFunction(b)){d=b;b=w}if(!b&&a)b=function(){return a.apply(d||this,arguments)};if(a)b.guid=a.guid=a.guid||b.guid||c.guid++;return b},uaMatch:function(a){a=a.toLowerCase();a=/(webkit)[ \\/]([\\w.]+)/.exec(a)||/(opera)(?:.*version)?[ \\/]([\\w.]+)/.exec(a)||/(msie) ([\\w.]+)/.exec(a)||!/compatible/.test(a)&&/(mozilla)(?:.*? rv:([\\w.]+))?/.exec(a)||[];return{browser:a[1]||\"\",version:a[2]||\"0\"}},browser:{}});P=c.uaMatch(P);if(P.browser){c.browser[P.browser]=true;c.browser.version=P.version}if(c.browser.webkit)c.browser.safari=
true;if(ya)c.inArray=function(a,b){return ya.call(b,a)};T=c(s);if(s.addEventListener)L=function(){s.removeEventListener(\"DOMContentLoaded\",L,false);c.ready()};else if(s.attachEvent)L=function(){if(s.readyState===\"complete\"){s.detachEvent(\"onreadystatechange\",L);c.ready()}};(function(){c.support={};var a=s.documentElement,b=s.createElement(\"script\"),d=s.createElement(\"div\"),f=\"script\"+J();d.style.display=\"none\";d.innerHTML=\"   <link/><table></table><a href='/a' style='color:red;float:left;opacity:.55;'>a</a><input type='checkbox'/>\";
var e=d.getElementsByTagName(\"*\"),j=d.getElementsByTagName(\"a\")[0];if(!(!e||!e.length||!j)){c.support={leadingWhitespace:d.firstChild.nodeType===3,tbody:!d.getElementsByTagName(\"tbody\").length,htmlSerialize:!!d.getElementsByTagName(\"link\").length,style:/red/.test(j.getAttribute(\"style\")),hrefNormalized:j.getAttribute(\"href\")===\"/a\",opacity:/^0.55\$/.test(j.style.opacity),cssFloat:!!j.style.cssFloat,checkOn:d.getElementsByTagName(\"input\")[0].value===\"on\",optSelected:s.createElement(\"select\").appendChild(s.createElement(\"option\")).selected,
parentNode:d.removeChild(d.appendChild(s.createElement(\"div\"))).parentNode===null,deleteExpando:true,checkClone:false,scriptEval:false,noCloneEvent:true,boxModel:null};b.type=\"text/javascript\";try{b.appendChild(s.createTextNode(\"window.\"+f+\"=1;\"))}catch(i){}a.insertBefore(b,a.firstChild);if(A[f]){c.support.scriptEval=true;delete A[f]}try{delete b.test}catch(o){c.support.deleteExpando=false}a.removeChild(b);if(d.attachEvent&&d.fireEvent){d.attachEvent(\"onclick\",function k(){c.support.noCloneEvent=
false;d.detachEvent(\"onclick\",k)});d.cloneNode(true).fireEvent(\"onclick\")}d=s.createElement(\"div\");d.innerHTML=\"<input type='radio' name='radiotest' checked='checked'/>\";a=s.createDocumentFragment();a.appendChild(d.firstChild);c.support.checkClone=a.cloneNode(true).cloneNode(true).lastChild.checked;c(function(){var k=s.createElement(\"div\");k.style.width=k.style.paddingLeft=\"1px\";s.body.appendChild(k);c.boxModel=c.support.boxModel=k.offsetWidth===2;s.body.removeChild(k).style.display=\"none\"});a=function(k){var n=
s.createElement(\"div\");k=\"on\"+k;var r=k in n;if(!r){n.setAttribute(k,\"return;\");r=typeof n[k]===\"function\"}return r};c.support.submitBubbles=a(\"submit\");c.support.changeBubbles=a(\"change\");a=b=d=e=j=null}})();c.props={\"for\":\"htmlFor\",\"class\":\"className\",readonly:\"readOnly\",maxlength:\"maxLength\",cellspacing:\"cellSpacing\",rowspan:\"rowSpan\",colspan:\"colSpan\",tabindex:\"tabIndex\",usemap:\"useMap\",frameborder:\"frameBorder\"};var G=\"jQuery\"+J(),Ya=0,za={};c.extend({cache:{},expando:G,noData:{embed:true,object:true,
applet:true},data:function(a,b,d){if(!(a.nodeName&&c.noData[a.nodeName.toLowerCase()])){a=a==A?za:a;var f=a[G],e=c.cache;if(!f&&typeof b===\"string\"&&d===w)return null;f||(f=++Ya);if(typeof b===\"object\"){a[G]=f;e[f]=c.extend(true,{},b)}else if(!e[f]){a[G]=f;e[f]={}}a=e[f];if(d!==w)a[b]=d;return typeof b===\"string\"?a[b]:a}},removeData:function(a,b){if(!(a.nodeName&&c.noData[a.nodeName.toLowerCase()])){a=a==A?za:a;var d=a[G],f=c.cache,e=f[d];if(b){if(e){delete e[b];c.isEmptyObject(e)&&c.removeData(a)}}else{if(c.support.deleteExpando)delete a[c.expando];
else a.removeAttribute&&a.removeAttribute(c.expando);delete f[d]}}}});c.fn.extend({data:function(a,b){if(typeof a===\"undefined\"&&this.length)return c.data(this[0]);else if(typeof a===\"object\")return this.each(function(){c.data(this,a)});var d=a.split(\".\");d[1]=d[1]?\".\"+d[1]:\"\";if(b===w){var f=this.triggerHandler(\"getData\"+d[1]+\"!\",[d[0]]);if(f===w&&this.length)f=c.data(this[0],a);return f===w&&d[1]?this.data(d[0]):f}else return this.trigger(\"setData\"+d[1]+\"!\",[d[0],b]).each(function(){c.data(this,
a,b)})},removeData:function(a){return this.each(function(){c.removeData(this,a)})}});c.extend({queue:function(a,b,d){if(a){b=(b||\"fx\")+\"queue\";var f=c.data(a,b);if(!d)return f||[];if(!f||c.isArray(d))f=c.data(a,b,c.makeArray(d));else f.push(d);return f}},dequeue:function(a,b){b=b||\"fx\";var d=c.queue(a,b),f=d.shift();if(f===\"inprogress\")f=d.shift();if(f){b===\"fx\"&&d.unshift(\"inprogress\");f.call(a,function(){c.dequeue(a,b)})}}});c.fn.extend({queue:function(a,b){if(typeof a!==\"string\"){b=a;a=\"fx\"}if(b===
w)return c.queue(this[0],a);return this.each(function(){var d=c.queue(this,a,b);a===\"fx\"&&d[0]!==\"inprogress\"&&c.dequeue(this,a)})},dequeue:function(a){return this.each(function(){c.dequeue(this,a)})},delay:function(a,b){a=c.fx?c.fx.speeds[a]||a:a;b=b||\"fx\";return this.queue(b,function(){var d=this;setTimeout(function(){c.dequeue(d,b)},a)})},clearQueue:function(a){return this.queue(a||\"fx\",[])}});var Aa=/[\\n\\t]/g,ca=/\\s+/,Za=/\\r/g,\$a=/href|src|style/,ab=/(button|input)/i,bb=/(button|input|object|select|textarea)/i,
cb=/^(a|area)\$/i,Ba=/radio|checkbox/;c.fn.extend({attr:function(a,b){return X(this,a,b,true,c.attr)},removeAttr:function(a){return this.each(function(){c.attr(this,a,\"\");this.nodeType===1&&this.removeAttribute(a)})},addClass:function(a){if(c.isFunction(a))return this.each(function(n){var r=c(this);r.addClass(a.call(this,n,r.attr(\"class\")))});if(a&&typeof a===\"string\")for(var b=(a||\"\").split(ca),d=0,f=this.length;d<f;d++){var e=this[d];if(e.nodeType===1)if(e.className){for(var j=\" \"+e.className+\" \",
i=e.className,o=0,k=b.length;o<k;o++)if(j.indexOf(\" \"+b[o]+\" \")<0)i+=\" \"+b[o];e.className=c.trim(i)}else e.className=a}return this},removeClass:function(a){if(c.isFunction(a))return this.each(function(k){var n=c(this);n.removeClass(a.call(this,k,n.attr(\"class\")))});if(a&&typeof a===\"string\"||a===w)for(var b=(a||\"\").split(ca),d=0,f=this.length;d<f;d++){var e=this[d];if(e.nodeType===1&&e.className)if(a){for(var j=(\" \"+e.className+\" \").replace(Aa,\" \"),i=0,o=b.length;i<o;i++)j=j.replace(\" \"+b[i]+\" \",
\" \");e.className=c.trim(j)}else e.className=\"\"}return this},toggleClass:function(a,b){var d=typeof a,f=typeof b===\"boolean\";if(c.isFunction(a))return this.each(function(e){var j=c(this);j.toggleClass(a.call(this,e,j.attr(\"class\"),b),b)});return this.each(function(){if(d===\"string\")for(var e,j=0,i=c(this),o=b,k=a.split(ca);e=k[j++];){o=f?o:!i.hasClass(e);i[o?\"addClass\":\"removeClass\"](e)}else if(d===\"undefined\"||d===\"boolean\"){this.className&&c.data(this,\"__className__\",this.className);this.className=
this.className||a===false?\"\":c.data(this,\"__className__\")||\"\"}})},hasClass:function(a){a=\" \"+a+\" \";for(var b=0,d=this.length;b<d;b++)if((\" \"+this[b].className+\" \").replace(Aa,\" \").indexOf(a)>-1)return true;return false},val:function(a){if(a===w){var b=this[0];if(b){if(c.nodeName(b,\"option\"))return(b.attributes.value||{}).specified?b.value:b.text;if(c.nodeName(b,\"select\")){var d=b.selectedIndex,f=[],e=b.options;b=b.type===\"select-one\";if(d<0)return null;var j=b?d:0;for(d=b?d+1:e.length;j<d;j++){var i=
e[j];if(i.selected){a=c(i).val();if(b)return a;f.push(a)}}return f}if(Ba.test(b.type)&&!c.support.checkOn)return b.getAttribute(\"value\")===null?\"on\":b.value;return(b.value||\"\").replace(Za,\"\")}return w}var o=c.isFunction(a);return this.each(function(k){var n=c(this),r=a;if(this.nodeType===1){if(o)r=a.call(this,k,n.val());if(typeof r===\"number\")r+=\"\";if(c.isArray(r)&&Ba.test(this.type))this.checked=c.inArray(n.val(),r)>=0;else if(c.nodeName(this,\"select\")){var u=c.makeArray(r);c(\"option\",this).each(function(){this.selected=
c.inArray(c(this).val(),u)>=0});if(!u.length)this.selectedIndex=-1}else this.value=r}})}});c.extend({attrFn:{val:true,css:true,html:true,text:true,data:true,width:true,height:true,offset:true},attr:function(a,b,d,f){if(!a||a.nodeType===3||a.nodeType===8)return w;if(f&&b in c.attrFn)return c(a)[b](d);f=a.nodeType!==1||!c.isXMLDoc(a);var e=d!==w;b=f&&c.props[b]||b;if(a.nodeType===1){var j=\$a.test(b);if(b in a&&f&&!j){if(e){b===\"type\"&&ab.test(a.nodeName)&&a.parentNode&&c.error(\"type property can't be changed\");
a[b]=d}if(c.nodeName(a,\"form\")&&a.getAttributeNode(b))return a.getAttributeNode(b).nodeValue;if(b===\"tabIndex\")return(b=a.getAttributeNode(\"tabIndex\"))&&b.specified?b.value:bb.test(a.nodeName)||cb.test(a.nodeName)&&a.href?0:w;return a[b]}if(!c.support.style&&f&&b===\"style\"){if(e)a.style.cssText=\"\"+d;return a.style.cssText}e&&a.setAttribute(b,\"\"+d);a=!c.support.hrefNormalized&&f&&j?a.getAttribute(b,2):a.getAttribute(b);return a===null?w:a}return c.style(a,b,d)}});var O=/\\.(.*)\$/,db=function(a){return a.replace(/[^\\w\\s\\.\\|`]/g,
function(b){return\"\\\\\"+b})};c.event={add:function(a,b,d,f){if(!(a.nodeType===3||a.nodeType===8)){if(a.setInterval&&a!==A&&!a.frameElement)a=A;var e,j;if(d.handler){e=d;d=e.handler}if(!d.guid)d.guid=c.guid++;if(j=c.data(a)){var i=j.events=j.events||{},o=j.handle;if(!o)j.handle=o=function(){return typeof c!==\"undefined\"&&!c.event.triggered?c.event.handle.apply(o.elem,arguments):w};o.elem=a;b=b.split(\" \");for(var k,n=0,r;k=b[n++];){j=e?c.extend({},e):{handler:d,data:f};if(k.indexOf(\".\")>-1){r=k.split(\".\");
k=r.shift();j.namespace=r.slice(0).sort().join(\".\")}else{r=[];j.namespace=\"\"}j.type=k;j.guid=d.guid;var u=i[k],z=c.event.special[k]||{};if(!u){u=i[k]=[];if(!z.setup||z.setup.call(a,f,r,o)===false)if(a.addEventListener)a.addEventListener(k,o,false);else a.attachEvent&&a.attachEvent(\"on\"+k,o)}if(z.add){z.add.call(a,j);if(!j.handler.guid)j.handler.guid=d.guid}u.push(j);c.event.global[k]=true}a=null}}},global:{},remove:function(a,b,d,f){if(!(a.nodeType===3||a.nodeType===8)){var e,j=0,i,o,k,n,r,u,z=c.data(a),
C=z&&z.events;if(z&&C){if(b&&b.type){d=b.handler;b=b.type}if(!b||typeof b===\"string\"&&b.charAt(0)===\".\"){b=b||\"\";for(e in C)c.event.remove(a,e+b)}else{for(b=b.split(\" \");e=b[j++];){n=e;i=e.indexOf(\".\")<0;o=[];if(!i){o=e.split(\".\");e=o.shift();k=new RegExp(\"(^|\\\\.)\"+c.map(o.slice(0).sort(),db).join(\"\\\\.(?:.*\\\\.)?\")+\"(\\\\.|\$)\")}if(r=C[e])if(d){n=c.event.special[e]||{};for(B=f||0;B<r.length;B++){u=r[B];if(d.guid===u.guid){if(i||k.test(u.namespace)){f==null&&r.splice(B--,1);n.remove&&n.remove.call(a,u)}if(f!=
null)break}}if(r.length===0||f!=null&&r.length===1){if(!n.teardown||n.teardown.call(a,o)===false)Ca(a,e,z.handle);delete C[e]}}else for(var B=0;B<r.length;B++){u=r[B];if(i||k.test(u.namespace)){c.event.remove(a,n,u.handler,B);r.splice(B--,1)}}}if(c.isEmptyObject(C)){if(b=z.handle)b.elem=null;delete z.events;delete z.handle;c.isEmptyObject(z)&&c.removeData(a)}}}}},trigger:function(a,b,d,f){var e=a.type||a;if(!f){a=typeof a===\"object\"?a[G]?a:c.extend(c.Event(e),a):c.Event(e);if(e.indexOf(\"!\")>=0){a.type=
e=e.slice(0,-1);a.exclusive=true}if(!d){a.stopPropagation();c.event.global[e]&&c.each(c.cache,function(){this.events&&this.events[e]&&c.event.trigger(a,b,this.handle.elem)})}if(!d||d.nodeType===3||d.nodeType===8)return w;a.result=w;a.target=d;b=c.makeArray(b);b.unshift(a)}a.currentTarget=d;(f=c.data(d,\"handle\"))&&f.apply(d,b);f=d.parentNode||d.ownerDocument;try{if(!(d&&d.nodeName&&c.noData[d.nodeName.toLowerCase()]))if(d[\"on\"+e]&&d[\"on\"+e].apply(d,b)===false)a.result=false}catch(j){}if(!a.isPropagationStopped()&&
f)c.event.trigger(a,b,f,true);else if(!a.isDefaultPrevented()){f=a.target;var i,o=c.nodeName(f,\"a\")&&e===\"click\",k=c.event.special[e]||{};if((!k._default||k._default.call(d,a)===false)&&!o&&!(f&&f.nodeName&&c.noData[f.nodeName.toLowerCase()])){try{if(f[e]){if(i=f[\"on\"+e])f[\"on\"+e]=null;c.event.triggered=true;f[e]()}}catch(n){}if(i)f[\"on\"+e]=i;c.event.triggered=false}}},handle:function(a){var b,d,f,e;a=arguments[0]=c.event.fix(a||A.event);a.currentTarget=this;b=a.type.indexOf(\".\")<0&&!a.exclusive;
if(!b){d=a.type.split(\".\");a.type=d.shift();f=new RegExp(\"(^|\\\\.)\"+d.slice(0).sort().join(\"\\\\.(?:.*\\\\.)?\")+\"(\\\\.|\$)\")}e=c.data(this,\"events\");d=e[a.type];if(e&&d){d=d.slice(0);e=0;for(var j=d.length;e<j;e++){var i=d[e];if(b||f.test(i.namespace)){a.handler=i.handler;a.data=i.data;a.handleObj=i;i=i.handler.apply(this,arguments);if(i!==w){a.result=i;if(i===false){a.preventDefault();a.stopPropagation()}}if(a.isImmediatePropagationStopped())break}}}return a.result},props:\"altKey attrChange attrName bubbles button cancelable charCode clientX clientY ctrlKey currentTarget data detail eventPhase fromElement handler keyCode layerX layerY metaKey newValue offsetX offsetY originalTarget pageX pageY prevValue relatedNode relatedTarget screenX screenY shiftKey srcElement target toElement view wheelDelta which\".split(\" \"),
fix:function(a){if(a[G])return a;var b=a;a=c.Event(b);for(var d=this.props.length,f;d;){f=this.props[--d];a[f]=b[f]}if(!a.target)a.target=a.srcElement||s;if(a.target.nodeType===3)a.target=a.target.parentNode;if(!a.relatedTarget&&a.fromElement)a.relatedTarget=a.fromElement===a.target?a.toElement:a.fromElement;if(a.pageX==null&&a.clientX!=null){b=s.documentElement;d=s.body;a.pageX=a.clientX+(b&&b.scrollLeft||d&&d.scrollLeft||0)-(b&&b.clientLeft||d&&d.clientLeft||0);a.pageY=a.clientY+(b&&b.scrollTop||
d&&d.scrollTop||0)-(b&&b.clientTop||d&&d.clientTop||0)}if(!a.which&&(a.charCode||a.charCode===0?a.charCode:a.keyCode))a.which=a.charCode||a.keyCode;if(!a.metaKey&&a.ctrlKey)a.metaKey=a.ctrlKey;if(!a.which&&a.button!==w)a.which=a.button&1?1:a.button&2?3:a.button&4?2:0;return a},guid:1E8,proxy:c.proxy,special:{ready:{setup:c.bindReady,teardown:c.noop},live:{add:function(a){c.event.add(this,a.origType,c.extend({},a,{handler:oa}))},remove:function(a){var b=true,d=a.origType.replace(O,\"\");c.each(c.data(this,
\"events\").live||[],function(){if(d===this.origType.replace(O,\"\"))return b=false});b&&c.event.remove(this,a.origType,oa)}},beforeunload:{setup:function(a,b,d){if(this.setInterval)this.onbeforeunload=d;return false},teardown:function(a,b){if(this.onbeforeunload===b)this.onbeforeunload=null}}}};var Ca=s.removeEventListener?function(a,b,d){a.removeEventListener(b,d,false)}:function(a,b,d){a.detachEvent(\"on\"+b,d)};c.Event=function(a){if(!this.preventDefault)return new c.Event(a);if(a&&a.type){this.originalEvent=
a;this.type=a.type}else this.type=a;this.timeStamp=J();this[G]=true};c.Event.prototype={preventDefault:function(){this.isDefaultPrevented=Z;var a=this.originalEvent;if(a){a.preventDefault&&a.preventDefault();a.returnValue=false}},stopPropagation:function(){this.isPropagationStopped=Z;var a=this.originalEvent;if(a){a.stopPropagation&&a.stopPropagation();a.cancelBubble=true}},stopImmediatePropagation:function(){this.isImmediatePropagationStopped=Z;this.stopPropagation()},isDefaultPrevented:Y,isPropagationStopped:Y,
isImmediatePropagationStopped:Y};var Da=function(a){var b=a.relatedTarget;try{for(;b&&b!==this;)b=b.parentNode;if(b!==this){a.type=a.data;c.event.handle.apply(this,arguments)}}catch(d){}},Ea=function(a){a.type=a.data;c.event.handle.apply(this,arguments)};c.each({mouseenter:\"mouseover\",mouseleave:\"mouseout\"},function(a,b){c.event.special[a]={setup:function(d){c.event.add(this,b,d&&d.selector?Ea:Da,a)},teardown:function(d){c.event.remove(this,b,d&&d.selector?Ea:Da)}}});if(!c.support.submitBubbles)c.event.special.submit=
{setup:function(){if(this.nodeName.toLowerCase()!==\"form\"){c.event.add(this,\"click.specialSubmit\",function(a){var b=a.target,d=b.type;if((d===\"submit\"||d===\"image\")&&c(b).closest(\"form\").length)return na(\"submit\",this,arguments)});c.event.add(this,\"keypress.specialSubmit\",function(a){var b=a.target,d=b.type;if((d===\"text\"||d===\"password\")&&c(b).closest(\"form\").length&&a.keyCode===13)return na(\"submit\",this,arguments)})}else return false},teardown:function(){c.event.remove(this,\".specialSubmit\")}};
if(!c.support.changeBubbles){var da=/textarea|input|select/i,ea,Fa=function(a){var b=a.type,d=a.value;if(b===\"radio\"||b===\"checkbox\")d=a.checked;else if(b===\"select-multiple\")d=a.selectedIndex>-1?c.map(a.options,function(f){return f.selected}).join(\"-\"):\"\";else if(a.nodeName.toLowerCase()===\"select\")d=a.selectedIndex;return d},fa=function(a,b){var d=a.target,f,e;if(!(!da.test(d.nodeName)||d.readOnly)){f=c.data(d,\"_change_data\");e=Fa(d);if(a.type!==\"focusout\"||d.type!==\"radio\")c.data(d,\"_change_data\",
e);if(!(f===w||e===f))if(f!=null||e){a.type=\"change\";return c.event.trigger(a,b,d)}}};c.event.special.change={filters:{focusout:fa,click:function(a){var b=a.target,d=b.type;if(d===\"radio\"||d===\"checkbox\"||b.nodeName.toLowerCase()===\"select\")return fa.call(this,a)},keydown:function(a){var b=a.target,d=b.type;if(a.keyCode===13&&b.nodeName.toLowerCase()!==\"textarea\"||a.keyCode===32&&(d===\"checkbox\"||d===\"radio\")||d===\"select-multiple\")return fa.call(this,a)},beforeactivate:function(a){a=a.target;c.data(a,
\"_change_data\",Fa(a))}},setup:function(){if(this.type===\"file\")return false;for(var a in ea)c.event.add(this,a+\".specialChange\",ea[a]);return da.test(this.nodeName)},teardown:function(){c.event.remove(this,\".specialChange\");return da.test(this.nodeName)}};ea=c.event.special.change.filters}s.addEventListener&&c.each({focus:\"focusin\",blur:\"focusout\"},function(a,b){function d(f){f=c.event.fix(f);f.type=b;return c.event.handle.call(this,f)}c.event.special[b]={setup:function(){this.addEventListener(a,
d,true)},teardown:function(){this.removeEventListener(a,d,true)}}});c.each([\"bind\",\"one\"],function(a,b){c.fn[b]=function(d,f,e){if(typeof d===\"object\"){for(var j in d)this[b](j,f,d[j],e);return this}if(c.isFunction(f)){e=f;f=w}var i=b===\"one\"?c.proxy(e,function(k){c(this).unbind(k,i);return e.apply(this,arguments)}):e;if(d===\"unload\"&&b!==\"one\")this.one(d,f,e);else{j=0;for(var o=this.length;j<o;j++)c.event.add(this[j],d,i,f)}return this}});c.fn.extend({unbind:function(a,b){if(typeof a===\"object\"&&
!a.preventDefault)for(var d in a)this.unbind(d,a[d]);else{d=0;for(var f=this.length;d<f;d++)c.event.remove(this[d],a,b)}return this},delegate:function(a,b,d,f){return this.live(b,d,f,a)},undelegate:function(a,b,d){return arguments.length===0?this.unbind(\"live\"):this.die(b,null,d,a)},trigger:function(a,b){return this.each(function(){c.event.trigger(a,b,this)})},triggerHandler:function(a,b){if(this[0]){a=c.Event(a);a.preventDefault();a.stopPropagation();c.event.trigger(a,b,this[0]);return a.result}},
toggle:function(a){for(var b=arguments,d=1;d<b.length;)c.proxy(a,b[d++]);return this.click(c.proxy(a,function(f){var e=(c.data(this,\"lastToggle\"+a.guid)||0)%d;c.data(this,\"lastToggle\"+a.guid,e+1);f.preventDefault();return b[e].apply(this,arguments)||false}))},hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)}});var Ga={focus:\"focusin\",blur:\"focusout\",mouseenter:\"mouseover\",mouseleave:\"mouseout\"};c.each([\"live\",\"die\"],function(a,b){c.fn[b]=function(d,f,e,j){var i,o=0,k,n,r=j||this.selector,
u=j?this:c(this.context);if(c.isFunction(f)){e=f;f=w}for(d=(d||\"\").split(\" \");(i=d[o++])!=null;){j=O.exec(i);k=\"\";if(j){k=j[0];i=i.replace(O,\"\")}if(i===\"hover\")d.push(\"mouseenter\"+k,\"mouseleave\"+k);else{n=i;if(i===\"focus\"||i===\"blur\"){d.push(Ga[i]+k);i+=k}else i=(Ga[i]||i)+k;b===\"live\"?u.each(function(){c.event.add(this,pa(i,r),{data:f,selector:r,handler:e,origType:i,origHandler:e,preType:n})}):u.unbind(pa(i,r),e)}}return this}});c.each(\"blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error\".split(\" \"),
function(a,b){c.fn[b]=function(d){return d?this.bind(b,d):this.trigger(b)};if(c.attrFn)c.attrFn[b]=true});A.attachEvent&&!A.addEventListener&&A.attachEvent(\"onunload\",function(){for(var a in c.cache)if(c.cache[a].handle)try{c.event.remove(c.cache[a].handle.elem)}catch(b){}});(function(){function a(g){for(var h=\"\",l,m=0;g[m];m++){l=g[m];if(l.nodeType===3||l.nodeType===4)h+=l.nodeValue;else if(l.nodeType!==8)h+=a(l.childNodes)}return h}function b(g,h,l,m,q,p){q=0;for(var v=m.length;q<v;q++){var t=m[q];
if(t){t=t[g];for(var y=false;t;){if(t.sizcache===l){y=m[t.sizset];break}if(t.nodeType===1&&!p){t.sizcache=l;t.sizset=q}if(t.nodeName.toLowerCase()===h){y=t;break}t=t[g]}m[q]=y}}}function d(g,h,l,m,q,p){q=0;for(var v=m.length;q<v;q++){var t=m[q];if(t){t=t[g];for(var y=false;t;){if(t.sizcache===l){y=m[t.sizset];break}if(t.nodeType===1){if(!p){t.sizcache=l;t.sizset=q}if(typeof h!==\"string\"){if(t===h){y=true;break}}else if(k.filter(h,[t]).length>0){y=t;break}}t=t[g]}m[q]=y}}}var f=/((?:\\((?:\\([^()]+\\)|[^()]+)+\\)|\\[(?:\\[[^[\\]]*\\]|['\"][^'\"]*['\"]|[^[\\]'\"]+)+\\]|\\\\.|[^ >+~,(\\[\\\\]+)+|[>+~])(\\s*,\\s*)?((?:.|\\r|\\n)*)/g,
e=0,j=Object.prototype.toString,i=false,o=true;[0,0].sort(function(){o=false;return 0});var k=function(g,h,l,m){l=l||[];var q=h=h||s;if(h.nodeType!==1&&h.nodeType!==9)return[];if(!g||typeof g!==\"string\")return l;for(var p=[],v,t,y,S,H=true,M=x(h),I=g;(f.exec(\"\"),v=f.exec(I))!==null;){I=v[3];p.push(v[1]);if(v[2]){S=v[3];break}}if(p.length>1&&r.exec(g))if(p.length===2&&n.relative[p[0]])t=ga(p[0]+p[1],h);else for(t=n.relative[p[0]]?[h]:k(p.shift(),h);p.length;){g=p.shift();if(n.relative[g])g+=p.shift();
t=ga(g,t)}else{if(!m&&p.length>1&&h.nodeType===9&&!M&&n.match.ID.test(p[0])&&!n.match.ID.test(p[p.length-1])){v=k.find(p.shift(),h,M);h=v.expr?k.filter(v.expr,v.set)[0]:v.set[0]}if(h){v=m?{expr:p.pop(),set:z(m)}:k.find(p.pop(),p.length===1&&(p[0]===\"~\"||p[0]===\"+\")&&h.parentNode?h.parentNode:h,M);t=v.expr?k.filter(v.expr,v.set):v.set;if(p.length>0)y=z(t);else H=false;for(;p.length;){var D=p.pop();v=D;if(n.relative[D])v=p.pop();else D=\"\";if(v==null)v=h;n.relative[D](y,v,M)}}else y=[]}y||(y=t);y||k.error(D||
g);if(j.call(y)===\"[object Array]\")if(H)if(h&&h.nodeType===1)for(g=0;y[g]!=null;g++){if(y[g]&&(y[g]===true||y[g].nodeType===1&&E(h,y[g])))l.push(t[g])}else for(g=0;y[g]!=null;g++)y[g]&&y[g].nodeType===1&&l.push(t[g]);else l.push.apply(l,y);else z(y,l);if(S){k(S,q,l,m);k.uniqueSort(l)}return l};k.uniqueSort=function(g){if(B){i=o;g.sort(B);if(i)for(var h=1;h<g.length;h++)g[h]===g[h-1]&&g.splice(h--,1)}return g};k.matches=function(g,h){return k(g,null,null,h)};k.find=function(g,h,l){var m,q;if(!g)return[];
for(var p=0,v=n.order.length;p<v;p++){var t=n.order[p];if(q=n.leftMatch[t].exec(g)){var y=q[1];q.splice(1,1);if(y.substr(y.length-1)!==\"\\\\\"){q[1]=(q[1]||\"\").replace(/\\\\/g,\"\");m=n.find[t](q,h,l);if(m!=null){g=g.replace(n.match[t],\"\");break}}}}m||(m=h.getElementsByTagName(\"*\"));return{set:m,expr:g}};k.filter=function(g,h,l,m){for(var q=g,p=[],v=h,t,y,S=h&&h[0]&&x(h[0]);g&&h.length;){for(var H in n.filter)if((t=n.leftMatch[H].exec(g))!=null&&t[2]){var M=n.filter[H],I,D;D=t[1];y=false;t.splice(1,1);if(D.substr(D.length-
1)!==\"\\\\\"){if(v===p)p=[];if(n.preFilter[H])if(t=n.preFilter[H](t,v,l,p,m,S)){if(t===true)continue}else y=I=true;if(t)for(var U=0;(D=v[U])!=null;U++)if(D){I=M(D,t,U,v);var Ha=m^!!I;if(l&&I!=null)if(Ha)y=true;else v[U]=false;else if(Ha){p.push(D);y=true}}if(I!==w){l||(v=p);g=g.replace(n.match[H],\"\");if(!y)return[];break}}}if(g===q)if(y==null)k.error(g);else break;q=g}return v};k.error=function(g){throw\"Syntax error, unrecognized expression: \"+g;};var n=k.selectors={order:[\"ID\",\"NAME\",\"TAG\"],match:{ID:/#((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)/,
CLASS:/\\.((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)/,NAME:/\\[name=['\"]*((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)['\"]*\\]/,ATTR:/\\[\\s*((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)\\s*(?:(\\S?=)\\s*(['\"]*)(.*?)\\3|)\\s*\\]/,TAG:/^((?:[\\w\\u00c0-\\uFFFF\\*-]|\\\\.)+)/,CHILD:/:(only|nth|last|first)-child(?:\\((even|odd|[\\dn+-]*)\\))?/,POS:/:(nth|eq|gt|lt|first|last|even|odd)(?:\\((\\d*)\\))?(?=[^-]|\$)/,PSEUDO:/:((?:[\\w\\u00c0-\\uFFFF-]|\\\\.)+)(?:\\((['\"]?)((?:\\([^\\)]+\\)|[^\\(\\)]*)+)\\2\\))?/},leftMatch:{},attrMap:{\"class\":\"className\",\"for\":\"htmlFor\"},attrHandle:{href:function(g){return g.getAttribute(\"href\")}},
relative:{\"+\":function(g,h){var l=typeof h===\"string\",m=l&&!/\\W/.test(h);l=l&&!m;if(m)h=h.toLowerCase();m=0;for(var q=g.length,p;m<q;m++)if(p=g[m]){for(;(p=p.previousSibling)&&p.nodeType!==1;);g[m]=l||p&&p.nodeName.toLowerCase()===h?p||false:p===h}l&&k.filter(h,g,true)},\">\":function(g,h){var l=typeof h===\"string\";if(l&&!/\\W/.test(h)){h=h.toLowerCase();for(var m=0,q=g.length;m<q;m++){var p=g[m];if(p){l=p.parentNode;g[m]=l.nodeName.toLowerCase()===h?l:false}}}else{m=0;for(q=g.length;m<q;m++)if(p=g[m])g[m]=
l?p.parentNode:p.parentNode===h;l&&k.filter(h,g,true)}},\"\":function(g,h,l){var m=e++,q=d;if(typeof h===\"string\"&&!/\\W/.test(h)){var p=h=h.toLowerCase();q=b}q(\"parentNode\",h,m,g,p,l)},\"~\":function(g,h,l){var m=e++,q=d;if(typeof h===\"string\"&&!/\\W/.test(h)){var p=h=h.toLowerCase();q=b}q(\"previousSibling\",h,m,g,p,l)}},find:{ID:function(g,h,l){if(typeof h.getElementById!==\"undefined\"&&!l)return(g=h.getElementById(g[1]))?[g]:[]},NAME:function(g,h){if(typeof h.getElementsByName!==\"undefined\"){var l=[];
h=h.getElementsByName(g[1]);for(var m=0,q=h.length;m<q;m++)h[m].getAttribute(\"name\")===g[1]&&l.push(h[m]);return l.length===0?null:l}},TAG:function(g,h){return h.getElementsByTagName(g[1])}},preFilter:{CLASS:function(g,h,l,m,q,p){g=\" \"+g[1].replace(/\\\\/g,\"\")+\" \";if(p)return g;p=0;for(var v;(v=h[p])!=null;p++)if(v)if(q^(v.className&&(\" \"+v.className+\" \").replace(/[\\t\\n]/g,\" \").indexOf(g)>=0))l||m.push(v);else if(l)h[p]=false;return false},ID:function(g){return g[1].replace(/\\\\/g,\"\")},TAG:function(g){return g[1].toLowerCase()},
CHILD:function(g){if(g[1]===\"nth\"){var h=/(-?)(\\d*)n((?:\\+|-)?\\d*)/.exec(g[2]===\"even\"&&\"2n\"||g[2]===\"odd\"&&\"2n+1\"||!/\\D/.test(g[2])&&\"0n+\"+g[2]||g[2]);g[2]=h[1]+(h[2]||1)-0;g[3]=h[3]-0}g[0]=e++;return g},ATTR:function(g,h,l,m,q,p){h=g[1].replace(/\\\\/g,\"\");if(!p&&n.attrMap[h])g[1]=n.attrMap[h];if(g[2]===\"~=\")g[4]=\" \"+g[4]+\" \";return g},PSEUDO:function(g,h,l,m,q){if(g[1]===\"not\")if((f.exec(g[3])||\"\").length>1||/^\\w/.test(g[3]))g[3]=k(g[3],null,null,h);else{g=k.filter(g[3],h,l,true^q);l||m.push.apply(m,
g);return false}else if(n.match.POS.test(g[0])||n.match.CHILD.test(g[0]))return true;return g},POS:function(g){g.unshift(true);return g}},filters:{enabled:function(g){return g.disabled===false&&g.type!==\"hidden\"},disabled:function(g){return g.disabled===true},checked:function(g){return g.checked===true},selected:function(g){return g.selected===true},parent:function(g){return!!g.firstChild},empty:function(g){return!g.firstChild},has:function(g,h,l){return!!k(l[3],g).length},header:function(g){return/h\\d/i.test(g.nodeName)},
text:function(g){return\"text\"===g.type},radio:function(g){return\"radio\"===g.type},checkbox:function(g){return\"checkbox\"===g.type},file:function(g){return\"file\"===g.type},password:function(g){return\"password\"===g.type},submit:function(g){return\"submit\"===g.type},image:function(g){return\"image\"===g.type},reset:function(g){return\"reset\"===g.type},button:function(g){return\"button\"===g.type||g.nodeName.toLowerCase()===\"button\"},input:function(g){return/input|select|textarea|button/i.test(g.nodeName)}},
setFilters:{first:function(g,h){return h===0},last:function(g,h,l,m){return h===m.length-1},even:function(g,h){return h%2===0},odd:function(g,h){return h%2===1},lt:function(g,h,l){return h<l[3]-0},gt:function(g,h,l){return h>l[3]-0},nth:function(g,h,l){return l[3]-0===h},eq:function(g,h,l){return l[3]-0===h}},filter:{PSEUDO:function(g,h,l,m){var q=h[1],p=n.filters[q];if(p)return p(g,l,h,m);else if(q===\"contains\")return(g.textContent||g.innerText||a([g])||\"\").indexOf(h[3])>=0;else if(q===\"not\"){h=
h[3];l=0;for(m=h.length;l<m;l++)if(h[l]===g)return false;return true}else k.error(\"Syntax error, unrecognized expression: \"+q)},CHILD:function(g,h){var l=h[1],m=g;switch(l){case \"only\":case \"first\":for(;m=m.previousSibling;)if(m.nodeType===1)return false;if(l===\"first\")return true;m=g;case \"last\":for(;m=m.nextSibling;)if(m.nodeType===1)return false;return true;case \"nth\":l=h[2];var q=h[3];if(l===1&&q===0)return true;h=h[0];var p=g.parentNode;if(p&&(p.sizcache!==h||!g.nodeIndex)){var v=0;for(m=p.firstChild;m;m=
m.nextSibling)if(m.nodeType===1)m.nodeIndex=++v;p.sizcache=h}g=g.nodeIndex-q;return l===0?g===0:g%l===0&&g/l>=0}},ID:function(g,h){return g.nodeType===1&&g.getAttribute(\"id\")===h},TAG:function(g,h){return h===\"*\"&&g.nodeType===1||g.nodeName.toLowerCase()===h},CLASS:function(g,h){return(\" \"+(g.className||g.getAttribute(\"class\"))+\" \").indexOf(h)>-1},ATTR:function(g,h){var l=h[1];g=n.attrHandle[l]?n.attrHandle[l](g):g[l]!=null?g[l]:g.getAttribute(l);l=g+\"\";var m=h[2];h=h[4];return g==null?m===\"!=\":m===
\"=\"?l===h:m===\"*=\"?l.indexOf(h)>=0:m===\"~=\"?(\" \"+l+\" \").indexOf(h)>=0:!h?l&&g!==false:m===\"!=\"?l!==h:m===\"^=\"?l.indexOf(h)===0:m===\"\$=\"?l.substr(l.length-h.length)===h:m===\"|=\"?l===h||l.substr(0,h.length+1)===h+\"-\":false},POS:function(g,h,l,m){var q=n.setFilters[h[2]];if(q)return q(g,l,h,m)}}},r=n.match.POS;for(var u in n.match){n.match[u]=new RegExp(n.match[u].source+/(?![^\\[]*\\])(?![^\\(]*\\))/.source);n.leftMatch[u]=new RegExp(/(^(?:.|\\r|\\n)*?)/.source+n.match[u].source.replace(/\\\\(\\d+)/g,function(g,
h){return\"\\\\\"+(h-0+1)}))}var z=function(g,h){g=Array.prototype.slice.call(g,0);if(h){h.push.apply(h,g);return h}return g};try{Array.prototype.slice.call(s.documentElement.childNodes,0)}catch(C){z=function(g,h){h=h||[];if(j.call(g)===\"[object Array]\")Array.prototype.push.apply(h,g);else if(typeof g.length===\"number\")for(var l=0,m=g.length;l<m;l++)h.push(g[l]);else for(l=0;g[l];l++)h.push(g[l]);return h}}var B;if(s.documentElement.compareDocumentPosition)B=function(g,h){if(!g.compareDocumentPosition||
!h.compareDocumentPosition){if(g==h)i=true;return g.compareDocumentPosition?-1:1}g=g.compareDocumentPosition(h)&4?-1:g===h?0:1;if(g===0)i=true;return g};else if(\"sourceIndex\"in s.documentElement)B=function(g,h){if(!g.sourceIndex||!h.sourceIndex){if(g==h)i=true;return g.sourceIndex?-1:1}g=g.sourceIndex-h.sourceIndex;if(g===0)i=true;return g};else if(s.createRange)B=function(g,h){if(!g.ownerDocument||!h.ownerDocument){if(g==h)i=true;return g.ownerDocument?-1:1}var l=g.ownerDocument.createRange(),m=
h.ownerDocument.createRange();l.setStart(g,0);l.setEnd(g,0);m.setStart(h,0);m.setEnd(h,0);g=l.compareBoundaryPoints(Range.START_TO_END,m);if(g===0)i=true;return g};(function(){var g=s.createElement(\"div\"),h=\"script\"+(new Date).getTime();g.innerHTML=\"<a name='\"+h+\"'/>\";var l=s.documentElement;l.insertBefore(g,l.firstChild);if(s.getElementById(h)){n.find.ID=function(m,q,p){if(typeof q.getElementById!==\"undefined\"&&!p)return(q=q.getElementById(m[1]))?q.id===m[1]||typeof q.getAttributeNode!==\"undefined\"&&
q.getAttributeNode(\"id\").nodeValue===m[1]?[q]:w:[]};n.filter.ID=function(m,q){var p=typeof m.getAttributeNode!==\"undefined\"&&m.getAttributeNode(\"id\");return m.nodeType===1&&p&&p.nodeValue===q}}l.removeChild(g);l=g=null})();(function(){var g=s.createElement(\"div\");g.appendChild(s.createComment(\"\"));if(g.getElementsByTagName(\"*\").length>0)n.find.TAG=function(h,l){l=l.getElementsByTagName(h[1]);if(h[1]===\"*\"){h=[];for(var m=0;l[m];m++)l[m].nodeType===1&&h.push(l[m]);l=h}return l};g.innerHTML=\"<a href='#'></a>\";
if(g.firstChild&&typeof g.firstChild.getAttribute!==\"undefined\"&&g.firstChild.getAttribute(\"href\")!==\"#\")n.attrHandle.href=function(h){return h.getAttribute(\"href\",2)};g=null})();s.querySelectorAll&&function(){var g=k,h=s.createElement(\"div\");h.innerHTML=\"<p class='TEST'></p>\";if(!(h.querySelectorAll&&h.querySelectorAll(\".TEST\").length===0)){k=function(m,q,p,v){q=q||s;if(!v&&q.nodeType===9&&!x(q))try{return z(q.querySelectorAll(m),p)}catch(t){}return g(m,q,p,v)};for(var l in g)k[l]=g[l];h=null}}();
(function(){var g=s.createElement(\"div\");g.innerHTML=\"<div class='test e'></div><div class='test'></div>\";if(!(!g.getElementsByClassName||g.getElementsByClassName(\"e\").length===0)){g.lastChild.className=\"e\";if(g.getElementsByClassName(\"e\").length!==1){n.order.splice(1,0,\"CLASS\");n.find.CLASS=function(h,l,m){if(typeof l.getElementsByClassName!==\"undefined\"&&!m)return l.getElementsByClassName(h[1])};g=null}}})();var E=s.compareDocumentPosition?function(g,h){return!!(g.compareDocumentPosition(h)&16)}:
function(g,h){return g!==h&&(g.contains?g.contains(h):true)},x=function(g){return(g=(g?g.ownerDocument||g:0).documentElement)?g.nodeName!==\"HTML\":false},ga=function(g,h){var l=[],m=\"\",q;for(h=h.nodeType?[h]:h;q=n.match.PSEUDO.exec(g);){m+=q[0];g=g.replace(n.match.PSEUDO,\"\")}g=n.relative[g]?g+\"*\":g;q=0;for(var p=h.length;q<p;q++)k(g,h[q],l);return k.filter(m,l)};c.find=k;c.expr=k.selectors;c.expr[\":\"]=c.expr.filters;c.unique=k.uniqueSort;c.text=a;c.isXMLDoc=x;c.contains=E})();var eb=/Until\$/,fb=/^(?:parents|prevUntil|prevAll)/,
gb=/,/;R=Array.prototype.slice;var Ia=function(a,b,d){if(c.isFunction(b))return c.grep(a,function(e,j){return!!b.call(e,j,e)===d});else if(b.nodeType)return c.grep(a,function(e){return e===b===d});else if(typeof b===\"string\"){var f=c.grep(a,function(e){return e.nodeType===1});if(Ua.test(b))return c.filter(b,f,!d);else b=c.filter(b,f)}return c.grep(a,function(e){return c.inArray(e,b)>=0===d})};c.fn.extend({find:function(a){for(var b=this.pushStack(\"\",\"find\",a),d=0,f=0,e=this.length;f<e;f++){d=b.length;
c.find(a,this[f],b);if(f>0)for(var j=d;j<b.length;j++)for(var i=0;i<d;i++)if(b[i]===b[j]){b.splice(j--,1);break}}return b},has:function(a){var b=c(a);return this.filter(function(){for(var d=0,f=b.length;d<f;d++)if(c.contains(this,b[d]))return true})},not:function(a){return this.pushStack(Ia(this,a,false),\"not\",a)},filter:function(a){return this.pushStack(Ia(this,a,true),\"filter\",a)},is:function(a){return!!a&&c.filter(a,this).length>0},closest:function(a,b){if(c.isArray(a)){var d=[],f=this[0],e,j=
{},i;if(f&&a.length){e=0;for(var o=a.length;e<o;e++){i=a[e];j[i]||(j[i]=c.expr.match.POS.test(i)?c(i,b||this.context):i)}for(;f&&f.ownerDocument&&f!==b;){for(i in j){e=j[i];if(e.jquery?e.index(f)>-1:c(f).is(e)){d.push({selector:i,elem:f});delete j[i]}}f=f.parentNode}}return d}var k=c.expr.match.POS.test(a)?c(a,b||this.context):null;return this.map(function(n,r){for(;r&&r.ownerDocument&&r!==b;){if(k?k.index(r)>-1:c(r).is(a))return r;r=r.parentNode}return null})},index:function(a){if(!a||typeof a===
\"string\")return c.inArray(this[0],a?c(a):this.parent().children());return c.inArray(a.jquery?a[0]:a,this)},add:function(a,b){a=typeof a===\"string\"?c(a,b||this.context):c.makeArray(a);b=c.merge(this.get(),a);return this.pushStack(qa(a[0])||qa(b[0])?b:c.unique(b))},andSelf:function(){return this.add(this.prevObject)}});c.each({parent:function(a){return(a=a.parentNode)&&a.nodeType!==11?a:null},parents:function(a){return c.dir(a,\"parentNode\")},parentsUntil:function(a,b,d){return c.dir(a,\"parentNode\",
d)},next:function(a){return c.nth(a,2,\"nextSibling\")},prev:function(a){return c.nth(a,2,\"previousSibling\")},nextAll:function(a){return c.dir(a,\"nextSibling\")},prevAll:function(a){return c.dir(a,\"previousSibling\")},nextUntil:function(a,b,d){return c.dir(a,\"nextSibling\",d)},prevUntil:function(a,b,d){return c.dir(a,\"previousSibling\",d)},siblings:function(a){return c.sibling(a.parentNode.firstChild,a)},children:function(a){return c.sibling(a.firstChild)},contents:function(a){return c.nodeName(a,\"iframe\")?
a.contentDocument||a.contentWindow.document:c.makeArray(a.childNodes)}},function(a,b){c.fn[a]=function(d,f){var e=c.map(this,b,d);eb.test(a)||(f=d);if(f&&typeof f===\"string\")e=c.filter(f,e);e=this.length>1?c.unique(e):e;if((this.length>1||gb.test(f))&&fb.test(a))e=e.reverse();return this.pushStack(e,a,R.call(arguments).join(\",\"))}});c.extend({filter:function(a,b,d){if(d)a=\":not(\"+a+\")\";return c.find.matches(a,b)},dir:function(a,b,d){var f=[];for(a=a[b];a&&a.nodeType!==9&&(d===w||a.nodeType!==1||!c(a).is(d));){a.nodeType===
1&&f.push(a);a=a[b]}return f},nth:function(a,b,d){b=b||1;for(var f=0;a;a=a[d])if(a.nodeType===1&&++f===b)break;return a},sibling:function(a,b){for(var d=[];a;a=a.nextSibling)a.nodeType===1&&a!==b&&d.push(a);return d}});var Ja=/ jQuery\\d+=\"(?:\\d+|null)\"/g,V=/^\\s+/,Ka=/(<([\\w:]+)[^>]*?)\\/>/g,hb=/^(?:area|br|col|embed|hr|img|input|link|meta|param)\$/i,La=/<([\\w:]+)/,ib=/<tbody/i,jb=/<|&#?\\w+;/,ta=/<script|<object|<embed|<option|<style/i,ua=/checked\\s*(?:[^=]|=\\s*.checked.)/i,Ma=function(a,b,d){return hb.test(d)?
a:b+\"></\"+d+\">\"},F={option:[1,\"<select multiple='multiple'>\",\"</select>\"],legend:[1,\"<fieldset>\",\"</fieldset>\"],thead:[1,\"<table>\",\"</table>\"],tr:[2,\"<table><tbody>\",\"</tbody></table>\"],td:[3,\"<table><tbody><tr>\",\"</tr></tbody></table>\"],col:[2,\"<table><tbody></tbody><colgroup>\",\"</colgroup></table>\"],area:[1,\"<map>\",\"</map>\"],_default:[0,\"\",\"\"]};F.optgroup=F.option;F.tbody=F.tfoot=F.colgroup=F.caption=F.thead;F.th=F.td;if(!c.support.htmlSerialize)F._default=[1,\"div<div>\",\"</div>\"];c.fn.extend({text:function(a){if(c.isFunction(a))return this.each(function(b){var d=
c(this);d.text(a.call(this,b,d.text()))});if(typeof a!==\"object\"&&a!==w)return this.empty().append((this[0]&&this[0].ownerDocument||s).createTextNode(a));return c.text(this)},wrapAll:function(a){if(c.isFunction(a))return this.each(function(d){c(this).wrapAll(a.call(this,d))});if(this[0]){var b=c(a,this[0].ownerDocument).eq(0).clone(true);this[0].parentNode&&b.insertBefore(this[0]);b.map(function(){for(var d=this;d.firstChild&&d.firstChild.nodeType===1;)d=d.firstChild;return d}).append(this)}return this},
wrapInner:function(a){if(c.isFunction(a))return this.each(function(b){c(this).wrapInner(a.call(this,b))});return this.each(function(){var b=c(this),d=b.contents();d.length?d.wrapAll(a):b.append(a)})},wrap:function(a){return this.each(function(){c(this).wrapAll(a)})},unwrap:function(){return this.parent().each(function(){c.nodeName(this,\"body\")||c(this).replaceWith(this.childNodes)}).end()},append:function(){return this.domManip(arguments,true,function(a){this.nodeType===1&&this.appendChild(a)})},
prepend:function(){return this.domManip(arguments,true,function(a){this.nodeType===1&&this.insertBefore(a,this.firstChild)})},before:function(){if(this[0]&&this[0].parentNode)return this.domManip(arguments,false,function(b){this.parentNode.insertBefore(b,this)});else if(arguments.length){var a=c(arguments[0]);a.push.apply(a,this.toArray());return this.pushStack(a,\"before\",arguments)}},after:function(){if(this[0]&&this[0].parentNode)return this.domManip(arguments,false,function(b){this.parentNode.insertBefore(b,
this.nextSibling)});else if(arguments.length){var a=this.pushStack(this,\"after\",arguments);a.push.apply(a,c(arguments[0]).toArray());return a}},remove:function(a,b){for(var d=0,f;(f=this[d])!=null;d++)if(!a||c.filter(a,[f]).length){if(!b&&f.nodeType===1){c.cleanData(f.getElementsByTagName(\"*\"));c.cleanData([f])}f.parentNode&&f.parentNode.removeChild(f)}return this},empty:function(){for(var a=0,b;(b=this[a])!=null;a++)for(b.nodeType===1&&c.cleanData(b.getElementsByTagName(\"*\"));b.firstChild;)b.removeChild(b.firstChild);
return this},clone:function(a){var b=this.map(function(){if(!c.support.noCloneEvent&&!c.isXMLDoc(this)){var d=this.outerHTML,f=this.ownerDocument;if(!d){d=f.createElement(\"div\");d.appendChild(this.cloneNode(true));d=d.innerHTML}return c.clean([d.replace(Ja,\"\").replace(/=([^=\"'>\\s]+\\/)>/g,'=\"\$1\">').replace(V,\"\")],f)[0]}else return this.cloneNode(true)});if(a===true){ra(this,b);ra(this.find(\"*\"),b.find(\"*\"))}return b},html:function(a){if(a===w)return this[0]&&this[0].nodeType===1?this[0].innerHTML.replace(Ja,
\"\"):null;else if(typeof a===\"string\"&&!ta.test(a)&&(c.support.leadingWhitespace||!V.test(a))&&!F[(La.exec(a)||[\"\",\"\"])[1].toLowerCase()]){a=a.replace(Ka,Ma);try{for(var b=0,d=this.length;b<d;b++)if(this[b].nodeType===1){c.cleanData(this[b].getElementsByTagName(\"*\"));this[b].innerHTML=a}}catch(f){this.empty().append(a)}}else c.isFunction(a)?this.each(function(e){var j=c(this),i=j.html();j.empty().append(function(){return a.call(this,e,i)})}):this.empty().append(a);return this},replaceWith:function(a){if(this[0]&&
this[0].parentNode){if(c.isFunction(a))return this.each(function(b){var d=c(this),f=d.html();d.replaceWith(a.call(this,b,f))});if(typeof a!==\"string\")a=c(a).detach();return this.each(function(){var b=this.nextSibling,d=this.parentNode;c(this).remove();b?c(b).before(a):c(d).append(a)})}else return this.pushStack(c(c.isFunction(a)?a():a),\"replaceWith\",a)},detach:function(a){return this.remove(a,true)},domManip:function(a,b,d){function f(u){return c.nodeName(u,\"table\")?u.getElementsByTagName(\"tbody\")[0]||
u.appendChild(u.ownerDocument.createElement(\"tbody\")):u}var e,j,i=a[0],o=[],k;if(!c.support.checkClone&&arguments.length===3&&typeof i===\"string\"&&ua.test(i))return this.each(function(){c(this).domManip(a,b,d,true)});if(c.isFunction(i))return this.each(function(u){var z=c(this);a[0]=i.call(this,u,b?z.html():w);z.domManip(a,b,d)});if(this[0]){e=i&&i.parentNode;e=c.support.parentNode&&e&&e.nodeType===11&&e.childNodes.length===this.length?{fragment:e}:sa(a,this,o);k=e.fragment;if(j=k.childNodes.length===
1?(k=k.firstChild):k.firstChild){b=b&&c.nodeName(j,\"tr\");for(var n=0,r=this.length;n<r;n++)d.call(b?f(this[n],j):this[n],n>0||e.cacheable||this.length>1?k.cloneNode(true):k)}o.length&&c.each(o,Qa)}return this}});c.fragments={};c.each({appendTo:\"append\",prependTo:\"prepend\",insertBefore:\"before\",insertAfter:\"after\",replaceAll:\"replaceWith\"},function(a,b){c.fn[a]=function(d){var f=[];d=c(d);var e=this.length===1&&this[0].parentNode;if(e&&e.nodeType===11&&e.childNodes.length===1&&d.length===1){d[b](this[0]);
return this}else{e=0;for(var j=d.length;e<j;e++){var i=(e>0?this.clone(true):this).get();c.fn[b].apply(c(d[e]),i);f=f.concat(i)}return this.pushStack(f,a,d.selector)}}});c.extend({clean:function(a,b,d,f){b=b||s;if(typeof b.createElement===\"undefined\")b=b.ownerDocument||b[0]&&b[0].ownerDocument||s;for(var e=[],j=0,i;(i=a[j])!=null;j++){if(typeof i===\"number\")i+=\"\";if(i){if(typeof i===\"string\"&&!jb.test(i))i=b.createTextNode(i);else if(typeof i===\"string\"){i=i.replace(Ka,Ma);var o=(La.exec(i)||[\"\",
\"\"])[1].toLowerCase(),k=F[o]||F._default,n=k[0],r=b.createElement(\"div\");for(r.innerHTML=k[1]+i+k[2];n--;)r=r.lastChild;if(!c.support.tbody){n=ib.test(i);o=o===\"table\"&&!n?r.firstChild&&r.firstChild.childNodes:k[1]===\"<table>\"&&!n?r.childNodes:[];for(k=o.length-1;k>=0;--k)c.nodeName(o[k],\"tbody\")&&!o[k].childNodes.length&&o[k].parentNode.removeChild(o[k])}!c.support.leadingWhitespace&&V.test(i)&&r.insertBefore(b.createTextNode(V.exec(i)[0]),r.firstChild);i=r.childNodes}if(i.nodeType)e.push(i);else e=
c.merge(e,i)}}if(d)for(j=0;e[j];j++)if(f&&c.nodeName(e[j],\"script\")&&(!e[j].type||e[j].type.toLowerCase()===\"text/javascript\"))f.push(e[j].parentNode?e[j].parentNode.removeChild(e[j]):e[j]);else{e[j].nodeType===1&&e.splice.apply(e,[j+1,0].concat(c.makeArray(e[j].getElementsByTagName(\"script\"))));d.appendChild(e[j])}return e},cleanData:function(a){for(var b,d,f=c.cache,e=c.event.special,j=c.support.deleteExpando,i=0,o;(o=a[i])!=null;i++)if(d=o[c.expando]){b=f[d];if(b.events)for(var k in b.events)e[k]?
c.event.remove(o,k):Ca(o,k,b.handle);if(j)delete o[c.expando];else o.removeAttribute&&o.removeAttribute(c.expando);delete f[d]}}});var kb=/z-?index|font-?weight|opacity|zoom|line-?height/i,Na=/alpha\\([^)]*\\)/,Oa=/opacity=([^)]*)/,ha=/float/i,ia=/-([a-z])/ig,lb=/([A-Z])/g,mb=/^-?\\d+(?:px)?\$/i,nb=/^-?\\d/,ob={position:\"absolute\",visibility:\"hidden\",display:\"block\"},pb=[\"Left\",\"Right\"],qb=[\"Top\",\"Bottom\"],rb=s.defaultView&&s.defaultView.getComputedStyle,Pa=c.support.cssFloat?\"cssFloat\":\"styleFloat\",ja=
function(a,b){return b.toUpperCase()};c.fn.css=function(a,b){return X(this,a,b,true,function(d,f,e){if(e===w)return c.curCSS(d,f);if(typeof e===\"number\"&&!kb.test(f))e+=\"px\";c.style(d,f,e)})};c.extend({style:function(a,b,d){if(!a||a.nodeType===3||a.nodeType===8)return w;if((b===\"width\"||b===\"height\")&&parseFloat(d)<0)d=w;var f=a.style||a,e=d!==w;if(!c.support.opacity&&b===\"opacity\"){if(e){f.zoom=1;b=parseInt(d,10)+\"\"===\"NaN\"?\"\":\"alpha(opacity=\"+d*100+\")\";a=f.filter||c.curCSS(a,\"filter\")||\"\";f.filter=
Na.test(a)?a.replace(Na,b):b}return f.filter&&f.filter.indexOf(\"opacity=\")>=0?parseFloat(Oa.exec(f.filter)[1])/100+\"\":\"\"}if(ha.test(b))b=Pa;b=b.replace(ia,ja);if(e)f[b]=d;return f[b]},css:function(a,b,d,f){if(b===\"width\"||b===\"height\"){var e,j=b===\"width\"?pb:qb;function i(){e=b===\"width\"?a.offsetWidth:a.offsetHeight;f!==\"border\"&&c.each(j,function(){f||(e-=parseFloat(c.curCSS(a,\"padding\"+this,true))||0);if(f===\"margin\")e+=parseFloat(c.curCSS(a,\"margin\"+this,true))||0;else e-=parseFloat(c.curCSS(a,
\"border\"+this+\"Width\",true))||0})}a.offsetWidth!==0?i():c.swap(a,ob,i);return Math.max(0,Math.round(e))}return c.curCSS(a,b,d)},curCSS:function(a,b,d){var f,e=a.style;if(!c.support.opacity&&b===\"opacity\"&&a.currentStyle){f=Oa.test(a.currentStyle.filter||\"\")?parseFloat(RegExp.\$1)/100+\"\":\"\";return f===\"\"?\"1\":f}if(ha.test(b))b=Pa;if(!d&&e&&e[b])f=e[b];else if(rb){if(ha.test(b))b=\"float\";b=b.replace(lb,\"-\$1\").toLowerCase();e=a.ownerDocument.defaultView;if(!e)return null;if(a=e.getComputedStyle(a,null))f=
a.getPropertyValue(b);if(b===\"opacity\"&&f===\"\")f=\"1\"}else if(a.currentStyle){d=b.replace(ia,ja);f=a.currentStyle[b]||a.currentStyle[d];if(!mb.test(f)&&nb.test(f)){b=e.left;var j=a.runtimeStyle.left;a.runtimeStyle.left=a.currentStyle.left;e.left=d===\"fontSize\"?\"1em\":f||0;f=e.pixelLeft+\"px\";e.left=b;a.runtimeStyle.left=j}}return f},swap:function(a,b,d){var f={};for(var e in b){f[e]=a.style[e];a.style[e]=b[e]}d.call(a);for(e in b)a.style[e]=f[e]}});if(c.expr&&c.expr.filters){c.expr.filters.hidden=function(a){var b=
a.offsetWidth,d=a.offsetHeight,f=a.nodeName.toLowerCase()===\"tr\";return b===0&&d===0&&!f?true:b>0&&d>0&&!f?false:c.curCSS(a,\"display\")===\"none\"};c.expr.filters.visible=function(a){return!c.expr.filters.hidden(a)}}var sb=J(),tb=/<script(.|\\s)*?\\/script>/gi,ub=/select|textarea/i,vb=/color|date|datetime|email|hidden|month|number|password|range|search|tel|text|time|url|week/i,N=/=\\?(&|\$)/,ka=/\\?/,wb=/(\\?|&)_=.*?(&|\$)/,xb=/^(\\w+:)?\\/\\/([^\\/?#]+)/,yb=/%20/g,zb=c.fn.load;c.fn.extend({load:function(a,b,d){if(typeof a!==
\"string\")return zb.call(this,a);else if(!this.length)return this;var f=a.indexOf(\" \");if(f>=0){var e=a.slice(f,a.length);a=a.slice(0,f)}f=\"GET\";if(b)if(c.isFunction(b)){d=b;b=null}else if(typeof b===\"object\"){b=c.param(b,c.ajaxSettings.traditional);f=\"POST\"}var j=this;c.ajax({url:a,type:f,dataType:\"html\",data:b,complete:function(i,o){if(o===\"success\"||o===\"notmodified\")j.html(e?c(\"<div />\").append(i.responseText.replace(tb,\"\")).find(e):i.responseText);d&&j.each(d,[i.responseText,o,i])}});return this},
serialize:function(){return c.param(this.serializeArray())},serializeArray:function(){return this.map(function(){return this.elements?c.makeArray(this.elements):this}).filter(function(){return this.name&&!this.disabled&&(this.checked||ub.test(this.nodeName)||vb.test(this.type))}).map(function(a,b){a=c(this).val();return a==null?null:c.isArray(a)?c.map(a,function(d){return{name:b.name,value:d}}):{name:b.name,value:a}}).get()}});c.each(\"ajaxStart ajaxStop ajaxComplete ajaxError ajaxSuccess ajaxSend\".split(\" \"),
function(a,b){c.fn[b]=function(d){return this.bind(b,d)}});c.extend({get:function(a,b,d,f){if(c.isFunction(b)){f=f||d;d=b;b=null}return c.ajax({type:\"GET\",url:a,data:b,success:d,dataType:f})},getScript:function(a,b){return c.get(a,null,b,\"script\")},getJSON:function(a,b,d){return c.get(a,b,d,\"json\")},post:function(a,b,d,f){if(c.isFunction(b)){f=f||d;d=b;b={}}return c.ajax({type:\"POST\",url:a,data:b,success:d,dataType:f})},ajaxSetup:function(a){c.extend(c.ajaxSettings,a)},ajaxSettings:{url:location.href,
global:true,type:\"GET\",contentType:\"application/x-www-form-urlencoded\",processData:true,async:true,xhr:A.XMLHttpRequest&&(A.location.protocol!==\"file:\"||!A.ActiveXObject)?function(){return new A.XMLHttpRequest}:function(){try{return new A.ActiveXObject(\"Microsoft.XMLHTTP\")}catch(a){}},accepts:{xml:\"application/xml, text/xml\",html:\"text/html\",script:\"text/javascript, application/javascript\",json:\"application/json, text/javascript\",text:\"text/plain\",_default:\"*/*\"}},lastModified:{},etag:{},ajax:function(a){function b(){e.success&&
e.success.call(k,o,i,x);e.global&&f(\"ajaxSuccess\",[x,e])}function d(){e.complete&&e.complete.call(k,x,i);e.global&&f(\"ajaxComplete\",[x,e]);e.global&&!--c.active&&c.event.trigger(\"ajaxStop\")}function f(q,p){(e.context?c(e.context):c.event).trigger(q,p)}var e=c.extend(true,{},c.ajaxSettings,a),j,i,o,k=a&&a.context||e,n=e.type.toUpperCase();if(e.data&&e.processData&&typeof e.data!==\"string\")e.data=c.param(e.data,e.traditional);if(e.dataType===\"jsonp\"){if(n===\"GET\")N.test(e.url)||(e.url+=(ka.test(e.url)?
\"&\":\"?\")+(e.jsonp||\"callback\")+\"=?\");else if(!e.data||!N.test(e.data))e.data=(e.data?e.data+\"&\":\"\")+(e.jsonp||\"callback\")+\"=?\";e.dataType=\"json\"}if(e.dataType===\"json\"&&(e.data&&N.test(e.data)||N.test(e.url))){j=e.jsonpCallback||\"jsonp\"+sb++;if(e.data)e.data=(e.data+\"\").replace(N,\"=\"+j+\"\$1\");e.url=e.url.replace(N,\"=\"+j+\"\$1\");e.dataType=\"script\";A[j]=A[j]||function(q){o=q;b();d();A[j]=w;try{delete A[j]}catch(p){}z&&z.removeChild(C)}}if(e.dataType===\"script\"&&e.cache===null)e.cache=false;if(e.cache===
false&&n===\"GET\"){var r=J(),u=e.url.replace(wb,\"\$1_=\"+r+\"\$2\");e.url=u+(u===e.url?(ka.test(e.url)?\"&\":\"?\")+\"_=\"+r:\"\")}if(e.data&&n===\"GET\")e.url+=(ka.test(e.url)?\"&\":\"?\")+e.data;e.global&&!c.active++&&c.event.trigger(\"ajaxStart\");r=(r=xb.exec(e.url))&&(r[1]&&r[1]!==location.protocol||r[2]!==location.host);if(e.dataType===\"script\"&&n===\"GET\"&&r){var z=s.getElementsByTagName(\"head\")[0]||s.documentElement,C=s.createElement(\"script\");C.src=e.url;if(e.scriptCharset)C.charset=e.scriptCharset;if(!j){var B=
false;C.onload=C.onreadystatechange=function(){if(!B&&(!this.readyState||this.readyState===\"loaded\"||this.readyState===\"complete\")){B=true;b();d();C.onload=C.onreadystatechange=null;z&&C.parentNode&&z.removeChild(C)}}}z.insertBefore(C,z.firstChild);return w}var E=false,x=e.xhr();if(x){e.username?x.open(n,e.url,e.async,e.username,e.password):x.open(n,e.url,e.async);try{if(e.data||a&&a.contentType)x.setRequestHeader(\"Content-Type\",e.contentType);if(e.ifModified){c.lastModified[e.url]&&x.setRequestHeader(\"If-Modified-Since\",
c.lastModified[e.url]);c.etag[e.url]&&x.setRequestHeader(\"If-None-Match\",c.etag[e.url])}r||x.setRequestHeader(\"X-Requested-With\",\"XMLHttpRequest\");x.setRequestHeader(\"Accept\",e.dataType&&e.accepts[e.dataType]?e.accepts[e.dataType]+\", */*\":e.accepts._default)}catch(ga){}if(e.beforeSend&&e.beforeSend.call(k,x,e)===false){e.global&&!--c.active&&c.event.trigger(\"ajaxStop\");x.abort();return false}e.global&&f(\"ajaxSend\",[x,e]);var g=x.onreadystatechange=function(q){if(!x||x.readyState===0||q===\"abort\"){E||
d();E=true;if(x)x.onreadystatechange=c.noop}else if(!E&&x&&(x.readyState===4||q===\"timeout\")){E=true;x.onreadystatechange=c.noop;i=q===\"timeout\"?\"timeout\":!c.httpSuccess(x)?\"error\":e.ifModified&&c.httpNotModified(x,e.url)?\"notmodified\":\"success\";var p;if(i===\"success\")try{o=c.httpData(x,e.dataType,e)}catch(v){i=\"parsererror\";p=v}if(i===\"success\"||i===\"notmodified\")j||b();else c.handleError(e,x,i,p);d();q===\"timeout\"&&x.abort();if(e.async)x=null}};try{var h=x.abort;x.abort=function(){x&&h.call(x);
g(\"abort\")}}catch(l){}e.async&&e.timeout>0&&setTimeout(function(){x&&!E&&g(\"timeout\")},e.timeout);try{x.send(n===\"POST\"||n===\"PUT\"||n===\"DELETE\"?e.data:null)}catch(m){c.handleError(e,x,null,m);d()}e.async||g();return x}},handleError:function(a,b,d,f){if(a.error)a.error.call(a.context||a,b,d,f);if(a.global)(a.context?c(a.context):c.event).trigger(\"ajaxError\",[b,a,f])},active:0,httpSuccess:function(a){try{return!a.status&&location.protocol===\"file:\"||a.status>=200&&a.status<300||a.status===304||a.status===
1223||a.status===0}catch(b){}return false},httpNotModified:function(a,b){var d=a.getResponseHeader(\"Last-Modified\"),f=a.getResponseHeader(\"Etag\");if(d)c.lastModified[b]=d;if(f)c.etag[b]=f;return a.status===304||a.status===0},httpData:function(a,b,d){var f=a.getResponseHeader(\"content-type\")||\"\",e=b===\"xml\"||!b&&f.indexOf(\"xml\")>=0;a=e?a.responseXML:a.responseText;e&&a.documentElement.nodeName===\"parsererror\"&&c.error(\"parsererror\");if(d&&d.dataFilter)a=d.dataFilter(a,b);if(typeof a===\"string\")if(b===
\"json\"||!b&&f.indexOf(\"json\")>=0)a=c.parseJSON(a);else if(b===\"script\"||!b&&f.indexOf(\"javascript\")>=0)c.globalEval(a);return a},param:function(a,b){function d(i,o){if(c.isArray(o))c.each(o,function(k,n){b||/\\[\\]\$/.test(i)?f(i,n):d(i+\"[\"+(typeof n===\"object\"||c.isArray(n)?k:\"\")+\"]\",n)});else!b&&o!=null&&typeof o===\"object\"?c.each(o,function(k,n){d(i+\"[\"+k+\"]\",n)}):f(i,o)}function f(i,o){o=c.isFunction(o)?o():o;e[e.length]=encodeURIComponent(i)+\"=\"+encodeURIComponent(o)}var e=[];if(b===w)b=c.ajaxSettings.traditional;
if(c.isArray(a)||a.jquery)c.each(a,function(){f(this.name,this.value)});else for(var j in a)d(j,a[j]);return e.join(\"&\").replace(yb,\"+\")}});var la={},Ab=/toggle|show|hide/,Bb=/^([+-]=)?([\\d+-.]+)(.*)\$/,W,va=[[\"height\",\"marginTop\",\"marginBottom\",\"paddingTop\",\"paddingBottom\"],[\"width\",\"marginLeft\",\"marginRight\",\"paddingLeft\",\"paddingRight\"],[\"opacity\"]];c.fn.extend({show:function(a,b){if(a||a===0)return this.animate(K(\"show\",3),a,b);else{a=0;for(b=this.length;a<b;a++){var d=c.data(this[a],\"olddisplay\");
this[a].style.display=d||\"\";if(c.css(this[a],\"display\")===\"none\"){d=this[a].nodeName;var f;if(la[d])f=la[d];else{var e=c(\"<\"+d+\" />\").appendTo(\"body\");f=e.css(\"display\");if(f===\"none\")f=\"block\";e.remove();la[d]=f}c.data(this[a],\"olddisplay\",f)}}a=0;for(b=this.length;a<b;a++)this[a].style.display=c.data(this[a],\"olddisplay\")||\"\";return this}},hide:function(a,b){if(a||a===0)return this.animate(K(\"hide\",3),a,b);else{a=0;for(b=this.length;a<b;a++){var d=c.data(this[a],\"olddisplay\");!d&&d!==\"none\"&&c.data(this[a],
\"olddisplay\",c.css(this[a],\"display\"))}a=0;for(b=this.length;a<b;a++)this[a].style.display=\"none\";return this}},_toggle:c.fn.toggle,toggle:function(a,b){var d=typeof a===\"boolean\";if(c.isFunction(a)&&c.isFunction(b))this._toggle.apply(this,arguments);else a==null||d?this.each(function(){var f=d?a:c(this).is(\":hidden\");c(this)[f?\"show\":\"hide\"]()}):this.animate(K(\"toggle\",3),a,b);return this},fadeTo:function(a,b,d){return this.filter(\":hidden\").css(\"opacity\",0).show().end().animate({opacity:b},a,d)},
animate:function(a,b,d,f){var e=c.speed(b,d,f);if(c.isEmptyObject(a))return this.each(e.complete);return this[e.queue===false?\"each\":\"queue\"](function(){var j=c.extend({},e),i,o=this.nodeType===1&&c(this).is(\":hidden\"),k=this;for(i in a){var n=i.replace(ia,ja);if(i!==n){a[n]=a[i];delete a[i];i=n}if(a[i]===\"hide\"&&o||a[i]===\"show\"&&!o)return j.complete.call(this);if((i===\"height\"||i===\"width\")&&this.style){j.display=c.css(this,\"display\");j.overflow=this.style.overflow}if(c.isArray(a[i])){(j.specialEasing=
j.specialEasing||{})[i]=a[i][1];a[i]=a[i][0]}}if(j.overflow!=null)this.style.overflow=\"hidden\";j.curAnim=c.extend({},a);c.each(a,function(r,u){var z=new c.fx(k,j,r);if(Ab.test(u))z[u===\"toggle\"?o?\"show\":\"hide\":u](a);else{var C=Bb.exec(u),B=z.cur(true)||0;if(C){u=parseFloat(C[2]);var E=C[3]||\"px\";if(E!==\"px\"){k.style[r]=(u||1)+E;B=(u||1)/z.cur(true)*B;k.style[r]=B+E}if(C[1])u=(C[1]===\"-=\"?-1:1)*u+B;z.custom(B,u,E)}else z.custom(B,u,\"\")}});return true})},stop:function(a,b){var d=c.timers;a&&this.queue([]);
this.each(function(){for(var f=d.length-1;f>=0;f--)if(d[f].elem===this){b&&d[f](true);d.splice(f,1)}});b||this.dequeue();return this}});c.each({slideDown:K(\"show\",1),slideUp:K(\"hide\",1),slideToggle:K(\"toggle\",1),fadeIn:{opacity:\"show\"},fadeOut:{opacity:\"hide\"}},function(a,b){c.fn[a]=function(d,f){return this.animate(b,d,f)}});c.extend({speed:function(a,b,d){var f=a&&typeof a===\"object\"?a:{complete:d||!d&&b||c.isFunction(a)&&a,duration:a,easing:d&&b||b&&!c.isFunction(b)&&b};f.duration=c.fx.off?0:typeof f.duration===
\"number\"?f.duration:c.fx.speeds[f.duration]||c.fx.speeds._default;f.old=f.complete;f.complete=function(){f.queue!==false&&c(this).dequeue();c.isFunction(f.old)&&f.old.call(this)};return f},easing:{linear:function(a,b,d,f){return d+f*a},swing:function(a,b,d,f){return(-Math.cos(a*Math.PI)/2+0.5)*f+d}},timers:[],fx:function(a,b,d){this.options=b;this.elem=a;this.prop=d;if(!b.orig)b.orig={}}});c.fx.prototype={update:function(){this.options.step&&this.options.step.call(this.elem,this.now,this);(c.fx.step[this.prop]||
c.fx.step._default)(this);if((this.prop===\"height\"||this.prop===\"width\")&&this.elem.style)this.elem.style.display=\"block\"},cur:function(a){if(this.elem[this.prop]!=null&&(!this.elem.style||this.elem.style[this.prop]==null))return this.elem[this.prop];return(a=parseFloat(c.css(this.elem,this.prop,a)))&&a>-10000?a:parseFloat(c.curCSS(this.elem,this.prop))||0},custom:function(a,b,d){function f(j){return e.step(j)}this.startTime=J();this.start=a;this.end=b;this.unit=d||this.unit||\"px\";this.now=this.start;
this.pos=this.state=0;var e=this;f.elem=this.elem;if(f()&&c.timers.push(f)&&!W)W=setInterval(c.fx.tick,13)},show:function(){this.options.orig[this.prop]=c.style(this.elem,this.prop);this.options.show=true;this.custom(this.prop===\"width\"||this.prop===\"height\"?1:0,this.cur());c(this.elem).show()},hide:function(){this.options.orig[this.prop]=c.style(this.elem,this.prop);this.options.hide=true;this.custom(this.cur(),0)},step:function(a){var b=J(),d=true;if(a||b>=this.options.duration+this.startTime){this.now=
this.end;this.pos=this.state=1;this.update();this.options.curAnim[this.prop]=true;for(var f in this.options.curAnim)if(this.options.curAnim[f]!==true)d=false;if(d){if(this.options.display!=null){this.elem.style.overflow=this.options.overflow;a=c.data(this.elem,\"olddisplay\");this.elem.style.display=a?a:this.options.display;if(c.css(this.elem,\"display\")===\"none\")this.elem.style.display=\"block\"}this.options.hide&&c(this.elem).hide();if(this.options.hide||this.options.show)for(var e in this.options.curAnim)c.style(this.elem,
e,this.options.orig[e]);this.options.complete.call(this.elem)}return false}else{e=b-this.startTime;this.state=e/this.options.duration;a=this.options.easing||(c.easing.swing?\"swing\":\"linear\");this.pos=c.easing[this.options.specialEasing&&this.options.specialEasing[this.prop]||a](this.state,e,0,1,this.options.duration);this.now=this.start+(this.end-this.start)*this.pos;this.update()}return true}};c.extend(c.fx,{tick:function(){for(var a=c.timers,b=0;b<a.length;b++)a[b]()||a.splice(b--,1);a.length||
c.fx.stop()},stop:function(){clearInterval(W);W=null},speeds:{slow:600,fast:200,_default:400},step:{opacity:function(a){c.style(a.elem,\"opacity\",a.now)},_default:function(a){if(a.elem.style&&a.elem.style[a.prop]!=null)a.elem.style[a.prop]=(a.prop===\"width\"||a.prop===\"height\"?Math.max(0,a.now):a.now)+a.unit;else a.elem[a.prop]=a.now}}});if(c.expr&&c.expr.filters)c.expr.filters.animated=function(a){return c.grep(c.timers,function(b){return a===b.elem}).length};c.fn.offset=\"getBoundingClientRect\"in s.documentElement?
function(a){var b=this[0];if(a)return this.each(function(e){c.offset.setOffset(this,a,e)});if(!b||!b.ownerDocument)return null;if(b===b.ownerDocument.body)return c.offset.bodyOffset(b);var d=b.getBoundingClientRect(),f=b.ownerDocument;b=f.body;f=f.documentElement;return{top:d.top+(self.pageYOffset||c.support.boxModel&&f.scrollTop||b.scrollTop)-(f.clientTop||b.clientTop||0),left:d.left+(self.pageXOffset||c.support.boxModel&&f.scrollLeft||b.scrollLeft)-(f.clientLeft||b.clientLeft||0)}}:function(a){var b=
this[0];if(a)return this.each(function(r){c.offset.setOffset(this,a,r)});if(!b||!b.ownerDocument)return null;if(b===b.ownerDocument.body)return c.offset.bodyOffset(b);c.offset.initialize();var d=b.offsetParent,f=b,e=b.ownerDocument,j,i=e.documentElement,o=e.body;f=(e=e.defaultView)?e.getComputedStyle(b,null):b.currentStyle;for(var k=b.offsetTop,n=b.offsetLeft;(b=b.parentNode)&&b!==o&&b!==i;){if(c.offset.supportsFixedPosition&&f.position===\"fixed\")break;j=e?e.getComputedStyle(b,null):b.currentStyle;
k-=b.scrollTop;n-=b.scrollLeft;if(b===d){k+=b.offsetTop;n+=b.offsetLeft;if(c.offset.doesNotAddBorder&&!(c.offset.doesAddBorderForTableAndCells&&/^t(able|d|h)\$/i.test(b.nodeName))){k+=parseFloat(j.borderTopWidth)||0;n+=parseFloat(j.borderLeftWidth)||0}f=d;d=b.offsetParent}if(c.offset.subtractsBorderForOverflowNotVisible&&j.overflow!==\"visible\"){k+=parseFloat(j.borderTopWidth)||0;n+=parseFloat(j.borderLeftWidth)||0}f=j}if(f.position===\"relative\"||f.position===\"static\"){k+=o.offsetTop;n+=o.offsetLeft}if(c.offset.supportsFixedPosition&&
f.position===\"fixed\"){k+=Math.max(i.scrollTop,o.scrollTop);n+=Math.max(i.scrollLeft,o.scrollLeft)}return{top:k,left:n}};c.offset={initialize:function(){var a=s.body,b=s.createElement(\"div\"),d,f,e,j=parseFloat(c.curCSS(a,\"marginTop\",true))||0;c.extend(b.style,{position:\"absolute\",top:0,left:0,margin:0,border:0,width:\"1px\",height:\"1px\",visibility:\"hidden\"});b.innerHTML=\"<div style='position:absolute;top:0;left:0;margin:0;border:5px solid #000;padding:0;width:1px;height:1px;'><div></div></div><table style='position:absolute;top:0;left:0;margin:0;border:5px solid #000;padding:0;width:1px;height:1px;' cellpadding='0' cellspacing='0'><tr><td></td></tr></table>\";
a.insertBefore(b,a.firstChild);d=b.firstChild;f=d.firstChild;e=d.nextSibling.firstChild.firstChild;this.doesNotAddBorder=f.offsetTop!==5;this.doesAddBorderForTableAndCells=e.offsetTop===5;f.style.position=\"fixed\";f.style.top=\"20px\";this.supportsFixedPosition=f.offsetTop===20||f.offsetTop===15;f.style.position=f.style.top=\"\";d.style.overflow=\"hidden\";d.style.position=\"relative\";this.subtractsBorderForOverflowNotVisible=f.offsetTop===-5;this.doesNotIncludeMarginInBodyOffset=a.offsetTop!==j;a.removeChild(b);
c.offset.initialize=c.noop},bodyOffset:function(a){var b=a.offsetTop,d=a.offsetLeft;c.offset.initialize();if(c.offset.doesNotIncludeMarginInBodyOffset){b+=parseFloat(c.curCSS(a,\"marginTop\",true))||0;d+=parseFloat(c.curCSS(a,\"marginLeft\",true))||0}return{top:b,left:d}},setOffset:function(a,b,d){if(/static/.test(c.curCSS(a,\"position\")))a.style.position=\"relative\";var f=c(a),e=f.offset(),j=parseInt(c.curCSS(a,\"top\",true),10)||0,i=parseInt(c.curCSS(a,\"left\",true),10)||0;if(c.isFunction(b))b=b.call(a,
d,e);d={top:b.top-e.top+j,left:b.left-e.left+i};\"using\"in b?b.using.call(a,d):f.css(d)}};c.fn.extend({position:function(){if(!this[0])return null;var a=this[0],b=this.offsetParent(),d=this.offset(),f=/^body|html\$/i.test(b[0].nodeName)?{top:0,left:0}:b.offset();d.top-=parseFloat(c.curCSS(a,\"marginTop\",true))||0;d.left-=parseFloat(c.curCSS(a,\"marginLeft\",true))||0;f.top+=parseFloat(c.curCSS(b[0],\"borderTopWidth\",true))||0;f.left+=parseFloat(c.curCSS(b[0],\"borderLeftWidth\",true))||0;return{top:d.top-
f.top,left:d.left-f.left}},offsetParent:function(){return this.map(function(){for(var a=this.offsetParent||s.body;a&&!/^body|html\$/i.test(a.nodeName)&&c.css(a,\"position\")===\"static\";)a=a.offsetParent;return a})}});c.each([\"Left\",\"Top\"],function(a,b){var d=\"scroll\"+b;c.fn[d]=function(f){var e=this[0],j;if(!e)return null;if(f!==w)return this.each(function(){if(j=wa(this))j.scrollTo(!a?f:c(j).scrollLeft(),a?f:c(j).scrollTop());else this[d]=f});else return(j=wa(e))?\"pageXOffset\"in j?j[a?\"pageYOffset\":
\"pageXOffset\"]:c.support.boxModel&&j.document.documentElement[d]||j.document.body[d]:e[d]}});c.each([\"Height\",\"Width\"],function(a,b){var d=b.toLowerCase();c.fn[\"inner\"+b]=function(){return this[0]?c.css(this[0],d,false,\"padding\"):null};c.fn[\"outer\"+b]=function(f){return this[0]?c.css(this[0],d,false,f?\"margin\":\"border\"):null};c.fn[d]=function(f){var e=this[0];if(!e)return f==null?null:this;if(c.isFunction(f))return this.each(function(j){var i=c(this);i[d](f.call(this,j,i[d]()))});return\"scrollTo\"in
e&&e.document?e.document.compatMode===\"CSS1Compat\"&&e.document.documentElement[\"client\"+b]||e.document.body[\"client\"+b]:e.nodeType===9?Math.max(e.documentElement[\"client\"+b],e.body[\"scroll\"+b],e.documentElement[\"scroll\"+b],e.body[\"offset\"+b],e.documentElement[\"offset\"+b]):f===w?c.css(e,d):this.css(d,typeof f===\"string\"?f:f+\"px\")}});A.jQuery=A.\$=c})(window);
</script>
<script type=\"text/javascript\">
/*!
 * jQuery UI 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI
 */
(function(c,j){function k(a){return!c(a).parents().andSelf().filter(function(){return c.curCSS(this,\"visibility\")===\"hidden\"||c.expr.filters.hidden(this)}).length}c.ui=c.ui||{};if(!c.ui.version){c.extend(c.ui,{version:\"1.8.5\",keyCode:{ALT:18,BACKSPACE:8,CAPS_LOCK:20,COMMA:188,COMMAND:91,COMMAND_LEFT:91,COMMAND_RIGHT:93,CONTROL:17,DELETE:46,DOWN:40,END:35,ENTER:13,ESCAPE:27,HOME:36,INSERT:45,LEFT:37,MENU:93,NUMPAD_ADD:107,NUMPAD_DECIMAL:110,NUMPAD_DIVIDE:111,NUMPAD_ENTER:108,NUMPAD_MULTIPLY:106,
NUMPAD_SUBTRACT:109,PAGE_DOWN:34,PAGE_UP:33,PERIOD:190,RIGHT:39,SHIFT:16,SPACE:32,TAB:9,UP:38,WINDOWS:91}});c.fn.extend({_focus:c.fn.focus,focus:function(a,b){return typeof a===\"number\"?this.each(function(){var d=this;setTimeout(function(){c(d).focus();b&&b.call(d)},a)}):this._focus.apply(this,arguments)},scrollParent:function(){var a;a=c.browser.msie&&/(static|relative)/.test(this.css(\"position\"))||/absolute/.test(this.css(\"position\"))?this.parents().filter(function(){return/(relative|absolute|fixed)/.test(c.curCSS(this,
\"position\",1))&&/(auto|scroll)/.test(c.curCSS(this,\"overflow\",1)+c.curCSS(this,\"overflow-y\",1)+c.curCSS(this,\"overflow-x\",1))}).eq(0):this.parents().filter(function(){return/(auto|scroll)/.test(c.curCSS(this,\"overflow\",1)+c.curCSS(this,\"overflow-y\",1)+c.curCSS(this,\"overflow-x\",1))}).eq(0);return/fixed/.test(this.css(\"position\"))||!a.length?c(document):a},zIndex:function(a){if(a!==j)return this.css(\"zIndex\",a);if(this.length){a=c(this[0]);for(var b;a.length&&a[0]!==document;){b=a.css(\"position\");
if(b===\"absolute\"||b===\"relative\"||b===\"fixed\"){b=parseInt(a.css(\"zIndex\"));if(!isNaN(b)&&b!=0)return b}a=a.parent()}}return 0},disableSelection:function(){return this.bind(\"mousedown.ui-disableSelection selectstart.ui-disableSelection\",function(a){a.preventDefault()})},enableSelection:function(){return this.unbind(\".ui-disableSelection\")}});c.each([\"Width\",\"Height\"],function(a,b){function d(f,g,l,m){c.each(e,function(){g-=parseFloat(c.curCSS(f,\"padding\"+this,true))||0;if(l)g-=parseFloat(c.curCSS(f,
\"border\"+this+\"Width\",true))||0;if(m)g-=parseFloat(c.curCSS(f,\"margin\"+this,true))||0});return g}var e=b===\"Width\"?[\"Left\",\"Right\"]:[\"Top\",\"Bottom\"],h=b.toLowerCase(),i={innerWidth:c.fn.innerWidth,innerHeight:c.fn.innerHeight,outerWidth:c.fn.outerWidth,outerHeight:c.fn.outerHeight};c.fn[\"inner\"+b]=function(f){if(f===j)return i[\"inner\"+b].call(this);return this.each(function(){c.style(this,h,d(this,f)+\"px\")})};c.fn[\"outer\"+b]=function(f,g){if(typeof f!==\"number\")return i[\"outer\"+b].call(this,f);return this.each(function(){c.style(this,
h,d(this,f,true,g)+\"px\")})}});c.extend(c.expr[\":\"],{data:function(a,b,d){return!!c.data(a,d[3])},focusable:function(a){var b=a.nodeName.toLowerCase(),d=c.attr(a,\"tabindex\");if(\"area\"===b){b=a.parentNode;d=b.name;if(!a.href||!d||b.nodeName.toLowerCase()!==\"map\")return false;a=c(\"img[usemap=#\"+d+\"]\")[0];return!!a&&k(a)}return(/input|select|textarea|button|object/.test(b)?!a.disabled:\"a\"==b?a.href||!isNaN(d):!isNaN(d))&&k(a)},tabbable:function(a){var b=c.attr(a,\"tabindex\");return(isNaN(b)||b>=0)&&c(a).is(\":focusable\")}});
c(function(){var a=document.createElement(\"div\"),b=document.body;c.extend(a.style,{minHeight:\"100px\",height:\"auto\",padding:0,borderWidth:0});c.support.minHeight=b.appendChild(a).offsetHeight===100;b.removeChild(a).style.display=\"none\"});c.extend(c.ui,{plugin:{add:function(a,b,d){a=c.ui[a].prototype;for(var e in d){a.plugins[e]=a.plugins[e]||[];a.plugins[e].push([b,d[e]])}},call:function(a,b,d){if((b=a.plugins[b])&&a.element[0].parentNode)for(var e=0;e<b.length;e++)a.options[b[e][0]]&&b[e][1].apply(a.element,
d)}},contains:function(a,b){return document.compareDocumentPosition?a.compareDocumentPosition(b)&16:a!==b&&a.contains(b)},hasScroll:function(a,b){if(c(a).css(\"overflow\")===\"hidden\")return false;b=b&&b===\"left\"?\"scrollLeft\":\"scrollTop\";var d=false;if(a[b]>0)return true;a[b]=1;d=a[b]>0;a[b]=0;return d},isOverAxis:function(a,b,d){return a>b&&a<b+d},isOver:function(a,b,d,e,h,i){return c.ui.isOverAxis(a,d,h)&&c.ui.isOverAxis(b,e,i)}})}})(jQuery);
;/*!
 * jQuery UI Widget 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Widget
 */
(function(b,j){if(b.cleanData){var k=b.cleanData;b.cleanData=function(a){for(var c=0,d;(d=a[c])!=null;c++)b(d).triggerHandler(\"remove\");k(a)}}else{var l=b.fn.remove;b.fn.remove=function(a,c){return this.each(function(){if(!c)if(!a||b.filter(a,[this]).length)b(\"*\",this).add([this]).each(function(){b(this).triggerHandler(\"remove\")});return l.call(b(this),a,c)})}}b.widget=function(a,c,d){var e=a.split(\".\")[0],f;a=a.split(\".\")[1];f=e+\"-\"+a;if(!d){d=c;c=b.Widget}b.expr[\":\"][f]=function(h){return!!b.data(h,
a)};b[e]=b[e]||{};b[e][a]=function(h,g){arguments.length&&this._createWidget(h,g)};c=new c;c.options=b.extend(true,{},c.options);b[e][a].prototype=b.extend(true,c,{namespace:e,widgetName:a,widgetEventPrefix:b[e][a].prototype.widgetEventPrefix||a,widgetBaseClass:f},d);b.widget.bridge(a,b[e][a])};b.widget.bridge=function(a,c){b.fn[a]=function(d){var e=typeof d===\"string\",f=Array.prototype.slice.call(arguments,1),h=this;d=!e&&f.length?b.extend.apply(null,[true,d].concat(f)):d;if(e&&d.substring(0,1)===
\"_\")return h;e?this.each(function(){var g=b.data(this,a);if(!g)throw\"cannot call methods on \"+a+\" prior to initialization; attempted to call method '\"+d+\"'\";if(!b.isFunction(g[d]))throw\"no such method '\"+d+\"' for \"+a+\" widget instance\";var i=g[d].apply(g,f);if(i!==g&&i!==j){h=i;return false}}):this.each(function(){var g=b.data(this,a);g?g.option(d||{})._init():b.data(this,a,new c(d,this))});return h}};b.Widget=function(a,c){arguments.length&&this._createWidget(a,c)};b.Widget.prototype={widgetName:\"widget\",
widgetEventPrefix:\"\",options:{disabled:false},_createWidget:function(a,c){b.data(c,this.widgetName,this);this.element=b(c);this.options=b.extend(true,{},this.options,b.metadata&&b.metadata.get(c)[this.widgetName],a);var d=this;this.element.bind(\"remove.\"+this.widgetName,function(){d.destroy()});this._create();this._init()},_create:function(){},_init:function(){},destroy:function(){this.element.unbind(\".\"+this.widgetName).removeData(this.widgetName);this.widget().unbind(\".\"+this.widgetName).removeAttr(\"aria-disabled\").removeClass(this.widgetBaseClass+
\"-disabled ui-state-disabled\")},widget:function(){return this.element},option:function(a,c){var d=a,e=this;if(arguments.length===0)return b.extend({},e.options);if(typeof a===\"string\"){if(c===j)return this.options[a];d={};d[a]=c}b.each(d,function(f,h){e._setOption(f,h)});return e},_setOption:function(a,c){this.options[a]=c;if(a===\"disabled\")this.widget()[c?\"addClass\":\"removeClass\"](this.widgetBaseClass+\"-disabled ui-state-disabled\").attr(\"aria-disabled\",c);return this},enable:function(){return this._setOption(\"disabled\",
false)},disable:function(){return this._setOption(\"disabled\",true)},_trigger:function(a,c,d){var e=this.options[a];c=b.Event(c);c.type=(a===this.widgetEventPrefix?a:this.widgetEventPrefix+a).toLowerCase();d=d||{};if(c.originalEvent){a=b.event.props.length;for(var f;a;){f=b.event.props[--a];c[f]=c.originalEvent[f]}}this.element.trigger(c,d);return!(b.isFunction(e)&&e.call(this.element[0],c,d)===false||c.isDefaultPrevented())}}})(jQuery);
;/*!
 * jQuery UI Mouse 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Mouse
 *
 * Depends:
 *	jquery.ui.widget.js
 */
(function(c){c.widget(\"ui.mouse\",{options:{cancel:\":input,option\",distance:1,delay:0},_mouseInit:function(){var a=this;this.element.bind(\"mousedown.\"+this.widgetName,function(b){return a._mouseDown(b)}).bind(\"click.\"+this.widgetName,function(b){if(a._preventClickEvent){a._preventClickEvent=false;b.stopImmediatePropagation();return false}});this.started=false},_mouseDestroy:function(){this.element.unbind(\".\"+this.widgetName)},_mouseDown:function(a){a.originalEvent=a.originalEvent||{};if(!a.originalEvent.mouseHandled){this._mouseStarted&&
this._mouseUp(a);this._mouseDownEvent=a;var b=this,e=a.which==1,f=typeof this.options.cancel==\"string\"?c(a.target).parents().add(a.target).filter(this.options.cancel).length:false;if(!e||f||!this._mouseCapture(a))return true;this.mouseDelayMet=!this.options.delay;if(!this.mouseDelayMet)this._mouseDelayTimer=setTimeout(function(){b.mouseDelayMet=true},this.options.delay);if(this._mouseDistanceMet(a)&&this._mouseDelayMet(a)){this._mouseStarted=this._mouseStart(a)!==false;if(!this._mouseStarted){a.preventDefault();
return true}}this._mouseMoveDelegate=function(d){return b._mouseMove(d)};this._mouseUpDelegate=function(d){return b._mouseUp(d)};c(document).bind(\"mousemove.\"+this.widgetName,this._mouseMoveDelegate).bind(\"mouseup.\"+this.widgetName,this._mouseUpDelegate);c.browser.safari||a.preventDefault();return a.originalEvent.mouseHandled=true}},_mouseMove:function(a){if(c.browser.msie&&!a.button)return this._mouseUp(a);if(this._mouseStarted){this._mouseDrag(a);return a.preventDefault()}if(this._mouseDistanceMet(a)&&
this._mouseDelayMet(a))(this._mouseStarted=this._mouseStart(this._mouseDownEvent,a)!==false)?this._mouseDrag(a):this._mouseUp(a);return!this._mouseStarted},_mouseUp:function(a){c(document).unbind(\"mousemove.\"+this.widgetName,this._mouseMoveDelegate).unbind(\"mouseup.\"+this.widgetName,this._mouseUpDelegate);if(this._mouseStarted){this._mouseStarted=false;this._preventClickEvent=a.target==this._mouseDownEvent.target;this._mouseStop(a)}return false},_mouseDistanceMet:function(a){return Math.max(Math.abs(this._mouseDownEvent.pageX-
a.pageX),Math.abs(this._mouseDownEvent.pageY-a.pageY))>=this.options.distance},_mouseDelayMet:function(){return this.mouseDelayMet},_mouseStart:function(){},_mouseDrag:function(){},_mouseStop:function(){},_mouseCapture:function(){return true}})})(jQuery);
;/*
 * jQuery UI Position 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Position
 */
(function(c){c.ui=c.ui||{};var n=/left|center|right/,o=/top|center|bottom/,t=c.fn.position,u=c.fn.offset;c.fn.position=function(b){if(!b||!b.of)return t.apply(this,arguments);b=c.extend({},b);var a=c(b.of),d=a[0],g=(b.collision||\"flip\").split(\" \"),e=b.offset?b.offset.split(\" \"):[0,0],h,k,j;if(d.nodeType===9){h=a.width();k=a.height();j={top:0,left:0}}else if(d.scrollTo&&d.document){h=a.width();k=a.height();j={top:a.scrollTop(),left:a.scrollLeft()}}else if(d.preventDefault){b.at=\"left top\";h=k=0;j=
{top:b.of.pageY,left:b.of.pageX}}else{h=a.outerWidth();k=a.outerHeight();j=a.offset()}c.each([\"my\",\"at\"],function(){var f=(b[this]||\"\").split(\" \");if(f.length===1)f=n.test(f[0])?f.concat([\"center\"]):o.test(f[0])?[\"center\"].concat(f):[\"center\",\"center\"];f[0]=n.test(f[0])?f[0]:\"center\";f[1]=o.test(f[1])?f[1]:\"center\";b[this]=f});if(g.length===1)g[1]=g[0];e[0]=parseInt(e[0],10)||0;if(e.length===1)e[1]=e[0];e[1]=parseInt(e[1],10)||0;if(b.at[0]===\"right\")j.left+=h;else if(b.at[0]===\"center\")j.left+=h/
2;if(b.at[1]===\"bottom\")j.top+=k;else if(b.at[1]===\"center\")j.top+=k/2;j.left+=e[0];j.top+=e[1];return this.each(function(){var f=c(this),l=f.outerWidth(),m=f.outerHeight(),p=parseInt(c.curCSS(this,\"marginLeft\",true))||0,q=parseInt(c.curCSS(this,\"marginTop\",true))||0,v=l+p+parseInt(c.curCSS(this,\"marginRight\",true))||0,w=m+q+parseInt(c.curCSS(this,\"marginBottom\",true))||0,i=c.extend({},j),r;if(b.my[0]===\"right\")i.left-=l;else if(b.my[0]===\"center\")i.left-=l/2;if(b.my[1]===\"bottom\")i.top-=m;else if(b.my[1]===
\"center\")i.top-=m/2;i.left=parseInt(i.left);i.top=parseInt(i.top);r={left:i.left-p,top:i.top-q};c.each([\"left\",\"top\"],function(s,x){c.ui.position[g[s]]&&c.ui.position[g[s]][x](i,{targetWidth:h,targetHeight:k,elemWidth:l,elemHeight:m,collisionPosition:r,collisionWidth:v,collisionHeight:w,offset:e,my:b.my,at:b.at})});c.fn.bgiframe&&f.bgiframe();f.offset(c.extend(i,{using:b.using}))})};c.ui.position={fit:{left:function(b,a){var d=c(window);d=a.collisionPosition.left+a.collisionWidth-d.width()-d.scrollLeft();
b.left=d>0?b.left-d:Math.max(b.left-a.collisionPosition.left,b.left)},top:function(b,a){var d=c(window);d=a.collisionPosition.top+a.collisionHeight-d.height()-d.scrollTop();b.top=d>0?b.top-d:Math.max(b.top-a.collisionPosition.top,b.top)}},flip:{left:function(b,a){if(a.at[0]!==\"center\"){var d=c(window);d=a.collisionPosition.left+a.collisionWidth-d.width()-d.scrollLeft();var g=a.my[0]===\"left\"?-a.elemWidth:a.my[0]===\"right\"?a.elemWidth:0,e=a.at[0]===\"left\"?a.targetWidth:-a.targetWidth,h=-2*a.offset[0];
b.left+=a.collisionPosition.left<0?g+e+h:d>0?g+e+h:0}},top:function(b,a){if(a.at[1]!==\"center\"){var d=c(window);d=a.collisionPosition.top+a.collisionHeight-d.height()-d.scrollTop();var g=a.my[1]===\"top\"?-a.elemHeight:a.my[1]===\"bottom\"?a.elemHeight:0,e=a.at[1]===\"top\"?a.targetHeight:-a.targetHeight,h=-2*a.offset[1];b.top+=a.collisionPosition.top<0?g+e+h:d>0?g+e+h:0}}}};if(!c.offset.setOffset){c.offset.setOffset=function(b,a){if(/static/.test(c.curCSS(b,\"position\")))b.style.position=\"relative\";var d=
c(b),g=d.offset(),e=parseInt(c.curCSS(b,\"top\",true),10)||0,h=parseInt(c.curCSS(b,\"left\",true),10)||0;g={top:a.top-g.top+e,left:a.left-g.left+h};\"using\"in a?a.using.call(b,g):d.css(g)};c.fn.offset=function(b){var a=this[0];if(!a||!a.ownerDocument)return null;if(b)return this.each(function(){c.offset.setOffset(this,b)});return u.call(this)}}})(jQuery);
;/*
 * jQuery UI Draggable 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Draggables
 *
 * Depends:
 *	jquery.ui.core.js
 *	jquery.ui.mouse.js
 *	jquery.ui.widget.js
 */
(function(d){d.widget(\"ui.draggable\",d.ui.mouse,{widgetEventPrefix:\"drag\",options:{addClasses:true,appendTo:\"parent\",axis:false,connectToSortable:false,containment:false,cursor:\"auto\",cursorAt:false,grid:false,handle:false,helper:\"original\",iframeFix:false,opacity:false,refreshPositions:false,revert:false,revertDuration:500,scope:\"default\",scroll:true,scrollSensitivity:20,scrollSpeed:20,snap:false,snapMode:\"both\",snapTolerance:20,stack:false,zIndex:false},_create:function(){if(this.options.helper==
\"original\"&&!/^(?:r|a|f)/.test(this.element.css(\"position\")))this.element[0].style.position=\"relative\";this.options.addClasses&&this.element.addClass(\"ui-draggable\");this.options.disabled&&this.element.addClass(\"ui-draggable-disabled\");this._mouseInit()},destroy:function(){if(this.element.data(\"draggable\")){this.element.removeData(\"draggable\").unbind(\".draggable\").removeClass(\"ui-draggable ui-draggable-dragging ui-draggable-disabled\");this._mouseDestroy();return this}},_mouseCapture:function(a){var b=
this.options;if(this.helper||b.disabled||d(a.target).is(\".ui-resizable-handle\"))return false;this.handle=this._getHandle(a);if(!this.handle)return false;return true},_mouseStart:function(a){var b=this.options;this.helper=this._createHelper(a);this._cacheHelperProportions();if(d.ui.ddmanager)d.ui.ddmanager.current=this;this._cacheMargins();this.cssPosition=this.helper.css(\"position\");this.scrollParent=this.helper.scrollParent();this.offset=this.positionAbs=this.element.offset();this.offset={top:this.offset.top-
this.margins.top,left:this.offset.left-this.margins.left};d.extend(this.offset,{click:{left:a.pageX-this.offset.left,top:a.pageY-this.offset.top},parent:this._getParentOffset(),relative:this._getRelativeOffset()});this.originalPosition=this.position=this._generatePosition(a);this.originalPageX=a.pageX;this.originalPageY=a.pageY;b.cursorAt&&this._adjustOffsetFromHelper(b.cursorAt);b.containment&&this._setContainment();if(this._trigger(\"start\",a)===false){this._clear();return false}this._cacheHelperProportions();
d.ui.ddmanager&&!b.dropBehaviour&&d.ui.ddmanager.prepareOffsets(this,a);this.helper.addClass(\"ui-draggable-dragging\");this._mouseDrag(a,true);return true},_mouseDrag:function(a,b){this.position=this._generatePosition(a);this.positionAbs=this._convertPositionTo(\"absolute\");if(!b){b=this._uiHash();if(this._trigger(\"drag\",a,b)===false){this._mouseUp({});return false}this.position=b.position}if(!this.options.axis||this.options.axis!=\"y\")this.helper[0].style.left=this.position.left+\"px\";if(!this.options.axis||
this.options.axis!=\"x\")this.helper[0].style.top=this.position.top+\"px\";d.ui.ddmanager&&d.ui.ddmanager.drag(this,a);return false},_mouseStop:function(a){var b=false;if(d.ui.ddmanager&&!this.options.dropBehaviour)b=d.ui.ddmanager.drop(this,a);if(this.dropped){b=this.dropped;this.dropped=false}if(!this.element[0]||!this.element[0].parentNode)return false;if(this.options.revert==\"invalid\"&&!b||this.options.revert==\"valid\"&&b||this.options.revert===true||d.isFunction(this.options.revert)&&this.options.revert.call(this.element,
b)){var c=this;d(this.helper).animate(this.originalPosition,parseInt(this.options.revertDuration,10),function(){c._trigger(\"stop\",a)!==false&&c._clear()})}else this._trigger(\"stop\",a)!==false&&this._clear();return false},cancel:function(){this.helper.is(\".ui-draggable-dragging\")?this._mouseUp({}):this._clear();return this},_getHandle:function(a){var b=!this.options.handle||!d(this.options.handle,this.element).length?true:false;d(this.options.handle,this.element).find(\"*\").andSelf().each(function(){if(this==
a.target)b=true});return b},_createHelper:function(a){var b=this.options;a=d.isFunction(b.helper)?d(b.helper.apply(this.element[0],[a])):b.helper==\"clone\"?this.element.clone():this.element;a.parents(\"body\").length||a.appendTo(b.appendTo==\"parent\"?this.element[0].parentNode:b.appendTo);a[0]!=this.element[0]&&!/(fixed|absolute)/.test(a.css(\"position\"))&&a.css(\"position\",\"absolute\");return a},_adjustOffsetFromHelper:function(a){if(typeof a==\"string\")a=a.split(\" \");if(d.isArray(a))a={left:+a[0],top:+a[1]||
0};if(\"left\"in a)this.offset.click.left=a.left+this.margins.left;if(\"right\"in a)this.offset.click.left=this.helperProportions.width-a.right+this.margins.left;if(\"top\"in a)this.offset.click.top=a.top+this.margins.top;if(\"bottom\"in a)this.offset.click.top=this.helperProportions.height-a.bottom+this.margins.top},_getParentOffset:function(){this.offsetParent=this.helper.offsetParent();var a=this.offsetParent.offset();if(this.cssPosition==\"absolute\"&&this.scrollParent[0]!=document&&d.ui.contains(this.scrollParent[0],
this.offsetParent[0])){a.left+=this.scrollParent.scrollLeft();a.top+=this.scrollParent.scrollTop()}if(this.offsetParent[0]==document.body||this.offsetParent[0].tagName&&this.offsetParent[0].tagName.toLowerCase()==\"html\"&&d.browser.msie)a={top:0,left:0};return{top:a.top+(parseInt(this.offsetParent.css(\"borderTopWidth\"),10)||0),left:a.left+(parseInt(this.offsetParent.css(\"borderLeftWidth\"),10)||0)}},_getRelativeOffset:function(){if(this.cssPosition==\"relative\"){var a=this.element.position();return{top:a.top-
(parseInt(this.helper.css(\"top\"),10)||0)+this.scrollParent.scrollTop(),left:a.left-(parseInt(this.helper.css(\"left\"),10)||0)+this.scrollParent.scrollLeft()}}else return{top:0,left:0}},_cacheMargins:function(){this.margins={left:parseInt(this.element.css(\"marginLeft\"),10)||0,top:parseInt(this.element.css(\"marginTop\"),10)||0}},_cacheHelperProportions:function(){this.helperProportions={width:this.helper.outerWidth(),height:this.helper.outerHeight()}},_setContainment:function(){var a=this.options;if(a.containment==
\"parent\")a.containment=this.helper[0].parentNode;if(a.containment==\"document\"||a.containment==\"window\")this.containment=[0-this.offset.relative.left-this.offset.parent.left,0-this.offset.relative.top-this.offset.parent.top,d(a.containment==\"document\"?document:window).width()-this.helperProportions.width-this.margins.left,(d(a.containment==\"document\"?document:window).height()||document.body.parentNode.scrollHeight)-this.helperProportions.height-this.margins.top];if(!/^(document|window|parent)\$/.test(a.containment)&&
a.containment.constructor!=Array){var b=d(a.containment)[0];if(b){a=d(a.containment).offset();var c=d(b).css(\"overflow\")!=\"hidden\";this.containment=[a.left+(parseInt(d(b).css(\"borderLeftWidth\"),10)||0)+(parseInt(d(b).css(\"paddingLeft\"),10)||0)-this.margins.left,a.top+(parseInt(d(b).css(\"borderTopWidth\"),10)||0)+(parseInt(d(b).css(\"paddingTop\"),10)||0)-this.margins.top,a.left+(c?Math.max(b.scrollWidth,b.offsetWidth):b.offsetWidth)-(parseInt(d(b).css(\"borderLeftWidth\"),10)||0)-(parseInt(d(b).css(\"paddingRight\"),
10)||0)-this.helperProportions.width-this.margins.left,a.top+(c?Math.max(b.scrollHeight,b.offsetHeight):b.offsetHeight)-(parseInt(d(b).css(\"borderTopWidth\"),10)||0)-(parseInt(d(b).css(\"paddingBottom\"),10)||0)-this.helperProportions.height-this.margins.top]}}else if(a.containment.constructor==Array)this.containment=a.containment},_convertPositionTo:function(a,b){if(!b)b=this.position;a=a==\"absolute\"?1:-1;var c=this.cssPosition==\"absolute\"&&!(this.scrollParent[0]!=document&&d.ui.contains(this.scrollParent[0],
this.offsetParent[0]))?this.offsetParent:this.scrollParent,f=/(html|body)/i.test(c[0].tagName);return{top:b.top+this.offset.relative.top*a+this.offset.parent.top*a-(d.browser.safari&&d.browser.version<526&&this.cssPosition==\"fixed\"?0:(this.cssPosition==\"fixed\"?-this.scrollParent.scrollTop():f?0:c.scrollTop())*a),left:b.left+this.offset.relative.left*a+this.offset.parent.left*a-(d.browser.safari&&d.browser.version<526&&this.cssPosition==\"fixed\"?0:(this.cssPosition==\"fixed\"?-this.scrollParent.scrollLeft():
f?0:c.scrollLeft())*a)}},_generatePosition:function(a){var b=this.options,c=this.cssPosition==\"absolute\"&&!(this.scrollParent[0]!=document&&d.ui.contains(this.scrollParent[0],this.offsetParent[0]))?this.offsetParent:this.scrollParent,f=/(html|body)/i.test(c[0].tagName),e=a.pageX,g=a.pageY;if(this.originalPosition){if(this.containment){if(a.pageX-this.offset.click.left<this.containment[0])e=this.containment[0]+this.offset.click.left;if(a.pageY-this.offset.click.top<this.containment[1])g=this.containment[1]+
this.offset.click.top;if(a.pageX-this.offset.click.left>this.containment[2])e=this.containment[2]+this.offset.click.left;if(a.pageY-this.offset.click.top>this.containment[3])g=this.containment[3]+this.offset.click.top}if(b.grid){g=this.originalPageY+Math.round((g-this.originalPageY)/b.grid[1])*b.grid[1];g=this.containment?!(g-this.offset.click.top<this.containment[1]||g-this.offset.click.top>this.containment[3])?g:!(g-this.offset.click.top<this.containment[1])?g-b.grid[1]:g+b.grid[1]:g;e=this.originalPageX+
Math.round((e-this.originalPageX)/b.grid[0])*b.grid[0];e=this.containment?!(e-this.offset.click.left<this.containment[0]||e-this.offset.click.left>this.containment[2])?e:!(e-this.offset.click.left<this.containment[0])?e-b.grid[0]:e+b.grid[0]:e}}return{top:g-this.offset.click.top-this.offset.relative.top-this.offset.parent.top+(d.browser.safari&&d.browser.version<526&&this.cssPosition==\"fixed\"?0:this.cssPosition==\"fixed\"?-this.scrollParent.scrollTop():f?0:c.scrollTop()),left:e-this.offset.click.left-
this.offset.relative.left-this.offset.parent.left+(d.browser.safari&&d.browser.version<526&&this.cssPosition==\"fixed\"?0:this.cssPosition==\"fixed\"?-this.scrollParent.scrollLeft():f?0:c.scrollLeft())}},_clear:function(){this.helper.removeClass(\"ui-draggable-dragging\");this.helper[0]!=this.element[0]&&!this.cancelHelperRemoval&&this.helper.remove();this.helper=null;this.cancelHelperRemoval=false},_trigger:function(a,b,c){c=c||this._uiHash();d.ui.plugin.call(this,a,[b,c]);if(a==\"drag\")this.positionAbs=
this._convertPositionTo(\"absolute\");return d.Widget.prototype._trigger.call(this,a,b,c)},plugins:{},_uiHash:function(){return{helper:this.helper,position:this.position,originalPosition:this.originalPosition,offset:this.positionAbs}}});d.extend(d.ui.draggable,{version:\"1.8.5\"});d.ui.plugin.add(\"draggable\",\"connectToSortable\",{start:function(a,b){var c=d(this).data(\"draggable\"),f=c.options,e=d.extend({},b,{item:c.element});c.sortables=[];d(f.connectToSortable).each(function(){var g=d.data(this,\"sortable\");
if(g&&!g.options.disabled){c.sortables.push({instance:g,shouldRevert:g.options.revert});g._refreshItems();g._trigger(\"activate\",a,e)}})},stop:function(a,b){var c=d(this).data(\"draggable\"),f=d.extend({},b,{item:c.element});d.each(c.sortables,function(){if(this.instance.isOver){this.instance.isOver=0;c.cancelHelperRemoval=true;this.instance.cancelHelperRemoval=false;if(this.shouldRevert)this.instance.options.revert=true;this.instance._mouseStop(a);this.instance.options.helper=this.instance.options._helper;
c.options.helper==\"original\"&&this.instance.currentItem.css({top:\"auto\",left:\"auto\"})}else{this.instance.cancelHelperRemoval=false;this.instance._trigger(\"deactivate\",a,f)}})},drag:function(a,b){var c=d(this).data(\"draggable\"),f=this;d.each(c.sortables,function(){this.instance.positionAbs=c.positionAbs;this.instance.helperProportions=c.helperProportions;this.instance.offset.click=c.offset.click;if(this.instance._intersectsWith(this.instance.containerCache)){if(!this.instance.isOver){this.instance.isOver=
1;this.instance.currentItem=d(f).clone().appendTo(this.instance.element).data(\"sortable-item\",true);this.instance.options._helper=this.instance.options.helper;this.instance.options.helper=function(){return b.helper[0]};a.target=this.instance.currentItem[0];this.instance._mouseCapture(a,true);this.instance._mouseStart(a,true,true);this.instance.offset.click.top=c.offset.click.top;this.instance.offset.click.left=c.offset.click.left;this.instance.offset.parent.left-=c.offset.parent.left-this.instance.offset.parent.left;
this.instance.offset.parent.top-=c.offset.parent.top-this.instance.offset.parent.top;c._trigger(\"toSortable\",a);c.dropped=this.instance.element;c.currentItem=c.element;this.instance.fromOutside=c}this.instance.currentItem&&this.instance._mouseDrag(a)}else if(this.instance.isOver){this.instance.isOver=0;this.instance.cancelHelperRemoval=true;this.instance.options.revert=false;this.instance._trigger(\"out\",a,this.instance._uiHash(this.instance));this.instance._mouseStop(a,true);this.instance.options.helper=
this.instance.options._helper;this.instance.currentItem.remove();this.instance.placeholder&&this.instance.placeholder.remove();c._trigger(\"fromSortable\",a);c.dropped=false}})}});d.ui.plugin.add(\"draggable\",\"cursor\",{start:function(){var a=d(\"body\"),b=d(this).data(\"draggable\").options;if(a.css(\"cursor\"))b._cursor=a.css(\"cursor\");a.css(\"cursor\",b.cursor)},stop:function(){var a=d(this).data(\"draggable\").options;a._cursor&&d(\"body\").css(\"cursor\",a._cursor)}});d.ui.plugin.add(\"draggable\",\"iframeFix\",{start:function(){var a=
d(this).data(\"draggable\").options;d(a.iframeFix===true?\"iframe\":a.iframeFix).each(function(){d('<div class=\"ui-draggable-iframeFix\" style=\"background: #fff;\"></div>').css({width:this.offsetWidth+\"px\",height:this.offsetHeight+\"px\",position:\"absolute\",opacity:\"0.001\",zIndex:1E3}).css(d(this).offset()).appendTo(\"body\")})},stop:function(){d(\"div.ui-draggable-iframeFix\").each(function(){this.parentNode.removeChild(this)})}});d.ui.plugin.add(\"draggable\",\"opacity\",{start:function(a,b){a=d(b.helper);b=d(this).data(\"draggable\").options;
if(a.css(\"opacity\"))b._opacity=a.css(\"opacity\");a.css(\"opacity\",b.opacity)},stop:function(a,b){a=d(this).data(\"draggable\").options;a._opacity&&d(b.helper).css(\"opacity\",a._opacity)}});d.ui.plugin.add(\"draggable\",\"scroll\",{start:function(){var a=d(this).data(\"draggable\");if(a.scrollParent[0]!=document&&a.scrollParent[0].tagName!=\"HTML\")a.overflowOffset=a.scrollParent.offset()},drag:function(a){var b=d(this).data(\"draggable\"),c=b.options,f=false;if(b.scrollParent[0]!=document&&b.scrollParent[0].tagName!=
\"HTML\"){if(!c.axis||c.axis!=\"x\")if(b.overflowOffset.top+b.scrollParent[0].offsetHeight-a.pageY<c.scrollSensitivity)b.scrollParent[0].scrollTop=f=b.scrollParent[0].scrollTop+c.scrollSpeed;else if(a.pageY-b.overflowOffset.top<c.scrollSensitivity)b.scrollParent[0].scrollTop=f=b.scrollParent[0].scrollTop-c.scrollSpeed;if(!c.axis||c.axis!=\"y\")if(b.overflowOffset.left+b.scrollParent[0].offsetWidth-a.pageX<c.scrollSensitivity)b.scrollParent[0].scrollLeft=f=b.scrollParent[0].scrollLeft+c.scrollSpeed;else if(a.pageX-
b.overflowOffset.left<c.scrollSensitivity)b.scrollParent[0].scrollLeft=f=b.scrollParent[0].scrollLeft-c.scrollSpeed}else{if(!c.axis||c.axis!=\"x\")if(a.pageY-d(document).scrollTop()<c.scrollSensitivity)f=d(document).scrollTop(d(document).scrollTop()-c.scrollSpeed);else if(d(window).height()-(a.pageY-d(document).scrollTop())<c.scrollSensitivity)f=d(document).scrollTop(d(document).scrollTop()+c.scrollSpeed);if(!c.axis||c.axis!=\"y\")if(a.pageX-d(document).scrollLeft()<c.scrollSensitivity)f=d(document).scrollLeft(d(document).scrollLeft()-
c.scrollSpeed);else if(d(window).width()-(a.pageX-d(document).scrollLeft())<c.scrollSensitivity)f=d(document).scrollLeft(d(document).scrollLeft()+c.scrollSpeed)}f!==false&&d.ui.ddmanager&&!c.dropBehaviour&&d.ui.ddmanager.prepareOffsets(b,a)}});d.ui.plugin.add(\"draggable\",\"snap\",{start:function(){var a=d(this).data(\"draggable\"),b=a.options;a.snapElements=[];d(b.snap.constructor!=String?b.snap.items||\":data(draggable)\":b.snap).each(function(){var c=d(this),f=c.offset();this!=a.element[0]&&a.snapElements.push({item:this,
width:c.outerWidth(),height:c.outerHeight(),top:f.top,left:f.left})})},drag:function(a,b){for(var c=d(this).data(\"draggable\"),f=c.options,e=f.snapTolerance,g=b.offset.left,n=g+c.helperProportions.width,m=b.offset.top,o=m+c.helperProportions.height,h=c.snapElements.length-1;h>=0;h--){var i=c.snapElements[h].left,k=i+c.snapElements[h].width,j=c.snapElements[h].top,l=j+c.snapElements[h].height;if(i-e<g&&g<k+e&&j-e<m&&m<l+e||i-e<g&&g<k+e&&j-e<o&&o<l+e||i-e<n&&n<k+e&&j-e<m&&m<l+e||i-e<n&&n<k+e&&j-e<o&&
o<l+e){if(f.snapMode!=\"inner\"){var p=Math.abs(j-o)<=e,q=Math.abs(l-m)<=e,r=Math.abs(i-n)<=e,s=Math.abs(k-g)<=e;if(p)b.position.top=c._convertPositionTo(\"relative\",{top:j-c.helperProportions.height,left:0}).top-c.margins.top;if(q)b.position.top=c._convertPositionTo(\"relative\",{top:l,left:0}).top-c.margins.top;if(r)b.position.left=c._convertPositionTo(\"relative\",{top:0,left:i-c.helperProportions.width}).left-c.margins.left;if(s)b.position.left=c._convertPositionTo(\"relative\",{top:0,left:k}).left-c.margins.left}var t=
p||q||r||s;if(f.snapMode!=\"outer\"){p=Math.abs(j-m)<=e;q=Math.abs(l-o)<=e;r=Math.abs(i-g)<=e;s=Math.abs(k-n)<=e;if(p)b.position.top=c._convertPositionTo(\"relative\",{top:j,left:0}).top-c.margins.top;if(q)b.position.top=c._convertPositionTo(\"relative\",{top:l-c.helperProportions.height,left:0}).top-c.margins.top;if(r)b.position.left=c._convertPositionTo(\"relative\",{top:0,left:i}).left-c.margins.left;if(s)b.position.left=c._convertPositionTo(\"relative\",{top:0,left:k-c.helperProportions.width}).left-c.margins.left}if(!c.snapElements[h].snapping&&
(p||q||r||s||t))c.options.snap.snap&&c.options.snap.snap.call(c.element,a,d.extend(c._uiHash(),{snapItem:c.snapElements[h].item}));c.snapElements[h].snapping=p||q||r||s||t}else{c.snapElements[h].snapping&&c.options.snap.release&&c.options.snap.release.call(c.element,a,d.extend(c._uiHash(),{snapItem:c.snapElements[h].item}));c.snapElements[h].snapping=false}}}});d.ui.plugin.add(\"draggable\",\"stack\",{start:function(){var a=d(this).data(\"draggable\").options;a=d.makeArray(d(a.stack)).sort(function(c,f){return(parseInt(d(c).css(\"zIndex\"),
10)||0)-(parseInt(d(f).css(\"zIndex\"),10)||0)});if(a.length){var b=parseInt(a[0].style.zIndex)||0;d(a).each(function(c){this.style.zIndex=b+c});this[0].style.zIndex=b+a.length}}});d.ui.plugin.add(\"draggable\",\"zIndex\",{start:function(a,b){a=d(b.helper);b=d(this).data(\"draggable\").options;if(a.css(\"zIndex\"))b._zIndex=a.css(\"zIndex\");a.css(\"zIndex\",b.zIndex)},stop:function(a,b){a=d(this).data(\"draggable\").options;a._zIndex&&d(b.helper).css(\"zIndex\",a._zIndex)}})})(jQuery);
;/*
 * jQuery UI Droppable 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Droppables
 *
 * Depends:
 *	jquery.ui.core.js
 *	jquery.ui.widget.js
 *	jquery.ui.mouse.js
 *	jquery.ui.draggable.js
 */
(function(d){d.widget(\"ui.droppable\",{widgetEventPrefix:\"drop\",options:{accept:\"*\",activeClass:false,addClasses:true,greedy:false,hoverClass:false,scope:\"default\",tolerance:\"intersect\"},_create:function(){var a=this.options,b=a.accept;this.isover=0;this.isout=1;this.accept=d.isFunction(b)?b:function(c){return c.is(b)};this.proportions={width:this.element[0].offsetWidth,height:this.element[0].offsetHeight};d.ui.ddmanager.droppables[a.scope]=d.ui.ddmanager.droppables[a.scope]||[];d.ui.ddmanager.droppables[a.scope].push(this);
a.addClasses&&this.element.addClass(\"ui-droppable\")},destroy:function(){for(var a=d.ui.ddmanager.droppables[this.options.scope],b=0;b<a.length;b++)a[b]==this&&a.splice(b,1);this.element.removeClass(\"ui-droppable ui-droppable-disabled\").removeData(\"droppable\").unbind(\".droppable\");return this},_setOption:function(a,b){if(a==\"accept\")this.accept=d.isFunction(b)?b:function(c){return c.is(b)};d.Widget.prototype._setOption.apply(this,arguments)},_activate:function(a){var b=d.ui.ddmanager.current;this.options.activeClass&&
this.element.addClass(this.options.activeClass);b&&this._trigger(\"activate\",a,this.ui(b))},_deactivate:function(a){var b=d.ui.ddmanager.current;this.options.activeClass&&this.element.removeClass(this.options.activeClass);b&&this._trigger(\"deactivate\",a,this.ui(b))},_over:function(a){var b=d.ui.ddmanager.current;if(!(!b||(b.currentItem||b.element)[0]==this.element[0]))if(this.accept.call(this.element[0],b.currentItem||b.element)){this.options.hoverClass&&this.element.addClass(this.options.hoverClass);
this._trigger(\"over\",a,this.ui(b))}},_out:function(a){var b=d.ui.ddmanager.current;if(!(!b||(b.currentItem||b.element)[0]==this.element[0]))if(this.accept.call(this.element[0],b.currentItem||b.element)){this.options.hoverClass&&this.element.removeClass(this.options.hoverClass);this._trigger(\"out\",a,this.ui(b))}},_drop:function(a,b){var c=b||d.ui.ddmanager.current;if(!c||(c.currentItem||c.element)[0]==this.element[0])return false;var e=false;this.element.find(\":data(droppable)\").not(\".ui-draggable-dragging\").each(function(){var g=
d.data(this,\"droppable\");if(g.options.greedy&&!g.options.disabled&&g.options.scope==c.options.scope&&g.accept.call(g.element[0],c.currentItem||c.element)&&d.ui.intersect(c,d.extend(g,{offset:g.element.offset()}),g.options.tolerance)){e=true;return false}});if(e)return false;if(this.accept.call(this.element[0],c.currentItem||c.element)){this.options.activeClass&&this.element.removeClass(this.options.activeClass);this.options.hoverClass&&this.element.removeClass(this.options.hoverClass);this._trigger(\"drop\",
a,this.ui(c));return this.element}return false},ui:function(a){return{draggable:a.currentItem||a.element,helper:a.helper,position:a.position,offset:a.positionAbs}}});d.extend(d.ui.droppable,{version:\"1.8.5\"});d.ui.intersect=function(a,b,c){if(!b.offset)return false;var e=(a.positionAbs||a.position.absolute).left,g=e+a.helperProportions.width,f=(a.positionAbs||a.position.absolute).top,h=f+a.helperProportions.height,i=b.offset.left,k=i+b.proportions.width,j=b.offset.top,l=j+b.proportions.height;
switch(c){case \"fit\":return i<=e&&g<=k&&j<=f&&h<=l;case \"intersect\":return i<e+a.helperProportions.width/2&&g-a.helperProportions.width/2<k&&j<f+a.helperProportions.height/2&&h-a.helperProportions.height/2<l;case \"pointer\":return d.ui.isOver((a.positionAbs||a.position.absolute).top+(a.clickOffset||a.offset.click).top,(a.positionAbs||a.position.absolute).left+(a.clickOffset||a.offset.click).left,j,i,b.proportions.height,b.proportions.width);case \"touch\":return(f>=j&&f<=l||h>=j&&h<=l||f<j&&h>l)&&(e>=
i&&e<=k||g>=i&&g<=k||e<i&&g>k);default:return false}};d.ui.ddmanager={current:null,droppables:{\"default\":[]},prepareOffsets:function(a,b){var c=d.ui.ddmanager.droppables[a.options.scope]||[],e=b?b.type:null,g=(a.currentItem||a.element).find(\":data(droppable)\").andSelf(),f=0;a:for(;f<c.length;f++)if(!(c[f].options.disabled||a&&!c[f].accept.call(c[f].element[0],a.currentItem||a.element))){for(var h=0;h<g.length;h++)if(g[h]==c[f].element[0]){c[f].proportions.height=0;continue a}c[f].visible=c[f].element.css(\"display\")!=
\"none\";if(c[f].visible){c[f].offset=c[f].element.offset();c[f].proportions={width:c[f].element[0].offsetWidth,height:c[f].element[0].offsetHeight};e==\"mousedown\"&&c[f]._activate.call(c[f],b)}}},drop:function(a,b){var c=false;d.each(d.ui.ddmanager.droppables[a.options.scope]||[],function(){if(this.options){if(!this.options.disabled&&this.visible&&d.ui.intersect(a,this,this.options.tolerance))c=c||this._drop.call(this,b);if(!this.options.disabled&&this.visible&&this.accept.call(this.element[0],a.currentItem||
a.element)){this.isout=1;this.isover=0;this._deactivate.call(this,b)}}});return c},drag:function(a,b){a.options.refreshPositions&&d.ui.ddmanager.prepareOffsets(a,b);d.each(d.ui.ddmanager.droppables[a.options.scope]||[],function(){if(!(this.options.disabled||this.greedyChild||!this.visible)){var c=d.ui.intersect(a,this,this.options.tolerance);if(c=!c&&this.isover==1?\"isout\":c&&this.isover==0?\"isover\":null){var e;if(this.options.greedy){var g=this.element.parents(\":data(droppable):eq(0)\");if(g.length){e=
d.data(g[0],\"droppable\");e.greedyChild=c==\"isover\"?1:0}}if(e&&c==\"isover\"){e.isover=0;e.isout=1;e._out.call(e,b)}this[c]=1;this[c==\"isout\"?\"isover\":\"isout\"]=0;this[c==\"isover\"?\"_over\":\"_out\"].call(this,b);if(e&&c==\"isout\"){e.isout=0;e.isover=1;e._over.call(e,b)}}}})}}})(jQuery);
;/*
 * jQuery UI Resizable 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Resizables
 *
 * Depends:
 *	jquery.ui.core.js
 *	jquery.ui.mouse.js
 *	jquery.ui.widget.js
 */
(function(e){e.widget(\"ui.resizable\",e.ui.mouse,{widgetEventPrefix:\"resize\",options:{alsoResize:false,animate:false,animateDuration:\"slow\",animateEasing:\"swing\",aspectRatio:false,autoHide:false,containment:false,ghost:false,grid:false,handles:\"e,s,se\",helper:false,maxHeight:null,maxWidth:null,minHeight:10,minWidth:10,zIndex:1E3},_create:function(){var b=this,a=this.options;this.element.addClass(\"ui-resizable\");e.extend(this,{_aspectRatio:!!a.aspectRatio,aspectRatio:a.aspectRatio,originalElement:this.element,
_proportionallyResizeElements:[],_helper:a.helper||a.ghost||a.animate?a.helper||\"ui-resizable-helper\":null});if(this.element[0].nodeName.match(/canvas|textarea|input|select|button|img/i)){/relative/.test(this.element.css(\"position\"))&&e.browser.opera&&this.element.css({position:\"relative\",top:\"auto\",left:\"auto\"});this.element.wrap(e('<div class=\"ui-wrapper\" style=\"overflow: hidden;\"></div>').css({position:this.element.css(\"position\"),width:this.element.outerWidth(),height:this.element.outerHeight(),
top:this.element.css(\"top\"),left:this.element.css(\"left\")}));this.element=this.element.parent().data(\"resizable\",this.element.data(\"resizable\"));this.elementIsWrapper=true;this.element.css({marginLeft:this.originalElement.css(\"marginLeft\"),marginTop:this.originalElement.css(\"marginTop\"),marginRight:this.originalElement.css(\"marginRight\"),marginBottom:this.originalElement.css(\"marginBottom\")});this.originalElement.css({marginLeft:0,marginTop:0,marginRight:0,marginBottom:0});this.originalResizeStyle=
this.originalElement.css(\"resize\");this.originalElement.css(\"resize\",\"none\");this._proportionallyResizeElements.push(this.originalElement.css({position:\"static\",zoom:1,display:\"block\"}));this.originalElement.css({margin:this.originalElement.css(\"margin\")});this._proportionallyResize()}this.handles=a.handles||(!e(\".ui-resizable-handle\",this.element).length?\"e,s,se\":{n:\".ui-resizable-n\",e:\".ui-resizable-e\",s:\".ui-resizable-s\",w:\".ui-resizable-w\",se:\".ui-resizable-se\",sw:\".ui-resizable-sw\",ne:\".ui-resizable-ne\",
nw:\".ui-resizable-nw\"});if(this.handles.constructor==String){if(this.handles==\"all\")this.handles=\"n,e,s,w,se,sw,ne,nw\";var c=this.handles.split(\",\");this.handles={};for(var d=0;d<c.length;d++){var f=e.trim(c[d]),g=e('<div class=\"ui-resizable-handle '+(\"ui-resizable-\"+f)+'\"></div>');/sw|se|ne|nw/.test(f)&&g.css({zIndex:++a.zIndex});\"se\"==f&&g.addClass(\"ui-icon ui-icon-gripsmall-diagonal-se\");this.handles[f]=\".ui-resizable-\"+f;this.element.append(g)}}this._renderAxis=function(h){h=h||this.element;for(var i in this.handles){if(this.handles[i].constructor==
String)this.handles[i]=e(this.handles[i],this.element).show();if(this.elementIsWrapper&&this.originalElement[0].nodeName.match(/textarea|input|select|button/i)){var j=e(this.handles[i],this.element),k=0;k=/sw|ne|nw|se|n|s/.test(i)?j.outerHeight():j.outerWidth();j=[\"padding\",/ne|nw|n/.test(i)?\"Top\":/se|sw|s/.test(i)?\"Bottom\":/^e\$/.test(i)?\"Right\":\"Left\"].join(\"\");h.css(j,k);this._proportionallyResize()}e(this.handles[i])}};this._renderAxis(this.element);this._handles=e(\".ui-resizable-handle\",this.element).disableSelection();
this._handles.mouseover(function(){if(!b.resizing){if(this.className)var h=this.className.match(/ui-resizable-(se|sw|ne|nw|n|e|s|w)/i);b.axis=h&&h[1]?h[1]:\"se\"}});if(a.autoHide){this._handles.hide();e(this.element).addClass(\"ui-resizable-autohide\").hover(function(){e(this).removeClass(\"ui-resizable-autohide\");b._handles.show()},function(){if(!b.resizing){e(this).addClass(\"ui-resizable-autohide\");b._handles.hide()}})}this._mouseInit()},destroy:function(){this._mouseDestroy();var b=function(c){e(c).removeClass(\"ui-resizable ui-resizable-disabled ui-resizable-resizing\").removeData(\"resizable\").unbind(\".resizable\").find(\".ui-resizable-handle\").remove()};
if(this.elementIsWrapper){b(this.element);var a=this.element;a.after(this.originalElement.css({position:a.css(\"position\"),width:a.outerWidth(),height:a.outerHeight(),top:a.css(\"top\"),left:a.css(\"left\")})).remove()}this.originalElement.css(\"resize\",this.originalResizeStyle);b(this.originalElement);return this},_mouseCapture:function(b){var a=false;for(var c in this.handles)if(e(this.handles[c])[0]==b.target)a=true;return!this.options.disabled&&a},_mouseStart:function(b){var a=this.options,c=this.element.position(),
d=this.element;this.resizing=true;this.documentScroll={top:e(document).scrollTop(),left:e(document).scrollLeft()};if(d.is(\".ui-draggable\")||/absolute/.test(d.css(\"position\")))d.css({position:\"absolute\",top:c.top,left:c.left});e.browser.opera&&/relative/.test(d.css(\"position\"))&&d.css({position:\"relative\",top:\"auto\",left:\"auto\"});this._renderProxy();c=m(this.helper.css(\"left\"));var f=m(this.helper.css(\"top\"));if(a.containment){c+=e(a.containment).scrollLeft()||0;f+=e(a.containment).scrollTop()||0}this.offset=
this.helper.offset();this.position={left:c,top:f};this.size=this._helper?{width:d.outerWidth(),height:d.outerHeight()}:{width:d.width(),height:d.height()};this.originalSize=this._helper?{width:d.outerWidth(),height:d.outerHeight()}:{width:d.width(),height:d.height()};this.originalPosition={left:c,top:f};this.sizeDiff={width:d.outerWidth()-d.width(),height:d.outerHeight()-d.height()};this.originalMousePosition={left:b.pageX,top:b.pageY};this.aspectRatio=typeof a.aspectRatio==\"number\"?a.aspectRatio:
this.originalSize.width/this.originalSize.height||1;a=e(\".ui-resizable-\"+this.axis).css(\"cursor\");e(\"body\").css(\"cursor\",a==\"auto\"?this.axis+\"-resize\":a);d.addClass(\"ui-resizable-resizing\");this._propagate(\"start\",b);return true},_mouseDrag:function(b){var a=this.helper,c=this.originalMousePosition,d=this._change[this.axis];if(!d)return false;c=d.apply(this,[b,b.pageX-c.left||0,b.pageY-c.top||0]);if(this._aspectRatio||b.shiftKey)c=this._updateRatio(c,b);c=this._respectSize(c,b);this._propagate(\"resize\",
b);a.css({top:this.position.top+\"px\",left:this.position.left+\"px\",width:this.size.width+\"px\",height:this.size.height+\"px\"});!this._helper&&this._proportionallyResizeElements.length&&this._proportionallyResize();this._updateCache(c);this._trigger(\"resize\",b,this.ui());return false},_mouseStop:function(b){this.resizing=false;var a=this.options,c=this;if(this._helper){var d=this._proportionallyResizeElements,f=d.length&&/textarea/i.test(d[0].nodeName);d=f&&e.ui.hasScroll(d[0],\"left\")?0:c.sizeDiff.height;
f={width:c.size.width-(f?0:c.sizeDiff.width),height:c.size.height-d};d=parseInt(c.element.css(\"left\"),10)+(c.position.left-c.originalPosition.left)||null;var g=parseInt(c.element.css(\"top\"),10)+(c.position.top-c.originalPosition.top)||null;a.animate||this.element.css(e.extend(f,{top:g,left:d}));c.helper.height(c.size.height);c.helper.width(c.size.width);this._helper&&!a.animate&&this._proportionallyResize()}e(\"body\").css(\"cursor\",\"auto\");this.element.removeClass(\"ui-resizable-resizing\");this._propagate(\"stop\",
b);this._helper&&this.helper.remove();return false},_updateCache:function(b){this.offset=this.helper.offset();if(l(b.left))this.position.left=b.left;if(l(b.top))this.position.top=b.top;if(l(b.height))this.size.height=b.height;if(l(b.width))this.size.width=b.width},_updateRatio:function(b){var a=this.position,c=this.size,d=this.axis;if(b.height)b.width=c.height*this.aspectRatio;else if(b.width)b.height=c.width/this.aspectRatio;if(d==\"sw\"){b.left=a.left+(c.width-b.width);b.top=null}if(d==\"nw\"){b.top=
a.top+(c.height-b.height);b.left=a.left+(c.width-b.width)}return b},_respectSize:function(b){var a=this.options,c=this.axis,d=l(b.width)&&a.maxWidth&&a.maxWidth<b.width,f=l(b.height)&&a.maxHeight&&a.maxHeight<b.height,g=l(b.width)&&a.minWidth&&a.minWidth>b.width,h=l(b.height)&&a.minHeight&&a.minHeight>b.height;if(g)b.width=a.minWidth;if(h)b.height=a.minHeight;if(d)b.width=a.maxWidth;if(f)b.height=a.maxHeight;var i=this.originalPosition.left+this.originalSize.width,j=this.position.top+this.size.height,
k=/sw|nw|w/.test(c);c=/nw|ne|n/.test(c);if(g&&k)b.left=i-a.minWidth;if(d&&k)b.left=i-a.maxWidth;if(h&&c)b.top=j-a.minHeight;if(f&&c)b.top=j-a.maxHeight;if((a=!b.width&&!b.height)&&!b.left&&b.top)b.top=null;else if(a&&!b.top&&b.left)b.left=null;return b},_proportionallyResize:function(){if(this._proportionallyResizeElements.length)for(var b=this.helper||this.element,a=0;a<this._proportionallyResizeElements.length;a++){var c=this._proportionallyResizeElements[a];if(!this.borderDif){var d=[c.css(\"borderTopWidth\"),
c.css(\"borderRightWidth\"),c.css(\"borderBottomWidth\"),c.css(\"borderLeftWidth\")],f=[c.css(\"paddingTop\"),c.css(\"paddingRight\"),c.css(\"paddingBottom\"),c.css(\"paddingLeft\")];this.borderDif=e.map(d,function(g,h){g=parseInt(g,10)||0;h=parseInt(f[h],10)||0;return g+h})}e.browser.msie&&(e(b).is(\":hidden\")||e(b).parents(\":hidden\").length)||c.css({height:b.height()-this.borderDif[0]-this.borderDif[2]||0,width:b.width()-this.borderDif[1]-this.borderDif[3]||0})}},_renderProxy:function(){var b=this.options;this.elementOffset=
this.element.offset();if(this._helper){this.helper=this.helper||e('<div style=\"overflow:hidden;\"></div>');var a=e.browser.msie&&e.browser.version<7,c=a?1:0;a=a?2:-1;this.helper.addClass(this._helper).css({width:this.element.outerWidth()+a,height:this.element.outerHeight()+a,position:\"absolute\",left:this.elementOffset.left-c+\"px\",top:this.elementOffset.top-c+\"px\",zIndex:++b.zIndex});this.helper.appendTo(\"body\").disableSelection()}else this.helper=this.element},_change:{e:function(b,a){return{width:this.originalSize.width+
a}},w:function(b,a){return{left:this.originalPosition.left+a,width:this.originalSize.width-a}},n:function(b,a,c){return{top:this.originalPosition.top+c,height:this.originalSize.height-c}},s:function(b,a,c){return{height:this.originalSize.height+c}},se:function(b,a,c){return e.extend(this._change.s.apply(this,arguments),this._change.e.apply(this,[b,a,c]))},sw:function(b,a,c){return e.extend(this._change.s.apply(this,arguments),this._change.w.apply(this,[b,a,c]))},ne:function(b,a,c){return e.extend(this._change.n.apply(this,
arguments),this._change.e.apply(this,[b,a,c]))},nw:function(b,a,c){return e.extend(this._change.n.apply(this,arguments),this._change.w.apply(this,[b,a,c]))}},_propagate:function(b,a){e.ui.plugin.call(this,b,[a,this.ui()]);b!=\"resize\"&&this._trigger(b,a,this.ui())},plugins:{},ui:function(){return{originalElement:this.originalElement,element:this.element,helper:this.helper,position:this.position,size:this.size,originalSize:this.originalSize,originalPosition:this.originalPosition}}});e.extend(e.ui.resizable,
{version:\"1.8.5\"});e.ui.plugin.add(\"resizable\",\"alsoResize\",{start:function(){var b=e(this).data(\"resizable\").options,a=function(c){e(c).each(function(){var d=e(this);d.data(\"resizable-alsoresize\",{width:parseInt(d.width(),10),height:parseInt(d.height(),10),left:parseInt(d.css(\"left\"),10),top:parseInt(d.css(\"top\"),10),position:d.css(\"position\")})})};if(typeof b.alsoResize==\"object\"&&!b.alsoResize.parentNode)if(b.alsoResize.length){b.alsoResize=b.alsoResize[0];a(b.alsoResize)}else e.each(b.alsoResize,
function(c){a(c)});else a(b.alsoResize)},resize:function(b,a){var c=e(this).data(\"resizable\");b=c.options;var d=c.originalSize,f=c.originalPosition,g={height:c.size.height-d.height||0,width:c.size.width-d.width||0,top:c.position.top-f.top||0,left:c.position.left-f.left||0},h=function(i,j){e(i).each(function(){var k=e(this),q=e(this).data(\"resizable-alsoresize\"),p={},r=j&&j.length?j:k.parents(a.originalElement[0]).length?[\"width\",\"height\"]:[\"width\",\"height\",\"top\",\"left\"];e.each(r,function(n,o){if((n=
(q[o]||0)+(g[o]||0))&&n>=0)p[o]=n||null});if(e.browser.opera&&/relative/.test(k.css(\"position\"))){c._revertToRelativePosition=true;k.css({position:\"absolute\",top:\"auto\",left:\"auto\"})}k.css(p)})};typeof b.alsoResize==\"object\"&&!b.alsoResize.nodeType?e.each(b.alsoResize,function(i,j){h(i,j)}):h(b.alsoResize)},stop:function(){var b=e(this).data(\"resizable\"),a=b.options,c=function(d){e(d).each(function(){var f=e(this);f.css({position:f.data(\"resizable-alsoresize\").position})})};if(b._revertToRelativePosition){b._revertToRelativePosition=
false;typeof a.alsoResize==\"object\"&&!a.alsoResize.nodeType?e.each(a.alsoResize,function(d){c(d)}):c(a.alsoResize)}e(this).removeData(\"resizable-alsoresize\")}});e.ui.plugin.add(\"resizable\",\"animate\",{stop:function(b){var a=e(this).data(\"resizable\"),c=a.options,d=a._proportionallyResizeElements,f=d.length&&/textarea/i.test(d[0].nodeName),g=f&&e.ui.hasScroll(d[0],\"left\")?0:a.sizeDiff.height;f={width:a.size.width-(f?0:a.sizeDiff.width),height:a.size.height-g};g=parseInt(a.element.css(\"left\"),10)+(a.position.left-
a.originalPosition.left)||null;var h=parseInt(a.element.css(\"top\"),10)+(a.position.top-a.originalPosition.top)||null;a.element.animate(e.extend(f,h&&g?{top:h,left:g}:{}),{duration:c.animateDuration,easing:c.animateEasing,step:function(){var i={width:parseInt(a.element.css(\"width\"),10),height:parseInt(a.element.css(\"height\"),10),top:parseInt(a.element.css(\"top\"),10),left:parseInt(a.element.css(\"left\"),10)};d&&d.length&&e(d[0]).css({width:i.width,height:i.height});a._updateCache(i);a._propagate(\"resize\",
b)}})}});e.ui.plugin.add(\"resizable\",\"containment\",{start:function(){var b=e(this).data(\"resizable\"),a=b.element,c=b.options.containment;if(a=c instanceof e?c.get(0):/parent/.test(c)?a.parent().get(0):c){b.containerElement=e(a);if(/document/.test(c)||c==document){b.containerOffset={left:0,top:0};b.containerPosition={left:0,top:0};b.parentData={element:e(document),left:0,top:0,width:e(document).width(),height:e(document).height()||document.body.parentNode.scrollHeight}}else{var d=e(a),f=[];e([\"Top\",
\"Right\",\"Left\",\"Bottom\"]).each(function(i,j){f[i]=m(d.css(\"padding\"+j))});b.containerOffset=d.offset();b.containerPosition=d.position();b.containerSize={height:d.innerHeight()-f[3],width:d.innerWidth()-f[1]};c=b.containerOffset;var g=b.containerSize.height,h=b.containerSize.width;h=e.ui.hasScroll(a,\"left\")?a.scrollWidth:h;g=e.ui.hasScroll(a)?a.scrollHeight:g;b.parentData={element:a,left:c.left,top:c.top,width:h,height:g}}}},resize:function(b){var a=e(this).data(\"resizable\"),c=a.options,d=a.containerOffset,
f=a.position;b=a._aspectRatio||b.shiftKey;var g={top:0,left:0},h=a.containerElement;if(h[0]!=document&&/static/.test(h.css(\"position\")))g=d;if(f.left<(a._helper?d.left:0)){a.size.width+=a._helper?a.position.left-d.left:a.position.left-g.left;if(b)a.size.height=a.size.width/c.aspectRatio;a.position.left=c.helper?d.left:0}if(f.top<(a._helper?d.top:0)){a.size.height+=a._helper?a.position.top-d.top:a.position.top;if(b)a.size.width=a.size.height*c.aspectRatio;a.position.top=a._helper?d.top:0}a.offset.left=
a.parentData.left+a.position.left;a.offset.top=a.parentData.top+a.position.top;c=Math.abs((a._helper?a.offset.left-g.left:a.offset.left-g.left)+a.sizeDiff.width);d=Math.abs((a._helper?a.offset.top-g.top:a.offset.top-d.top)+a.sizeDiff.height);f=a.containerElement.get(0)==a.element.parent().get(0);g=/relative|absolute/.test(a.containerElement.css(\"position\"));if(f&&g)c-=a.parentData.left;if(c+a.size.width>=a.parentData.width){a.size.width=a.parentData.width-c;if(b)a.size.height=a.size.width/a.aspectRatio}if(d+
a.size.height>=a.parentData.height){a.size.height=a.parentData.height-d;if(b)a.size.width=a.size.height*a.aspectRatio}},stop:function(){var b=e(this).data(\"resizable\"),a=b.options,c=b.containerOffset,d=b.containerPosition,f=b.containerElement,g=e(b.helper),h=g.offset(),i=g.outerWidth()-b.sizeDiff.width;g=g.outerHeight()-b.sizeDiff.height;b._helper&&!a.animate&&/relative/.test(f.css(\"position\"))&&e(this).css({left:h.left-d.left-c.left,width:i,height:g});b._helper&&!a.animate&&/static/.test(f.css(\"position\"))&&
e(this).css({left:h.left-d.left-c.left,width:i,height:g})}});e.ui.plugin.add(\"resizable\",\"ghost\",{start:function(){var b=e(this).data(\"resizable\"),a=b.options,c=b.size;b.ghost=b.originalElement.clone();b.ghost.css({opacity:0.25,display:\"block\",position:\"relative\",height:c.height,width:c.width,margin:0,left:0,top:0}).addClass(\"ui-resizable-ghost\").addClass(typeof a.ghost==\"string\"?a.ghost:\"\");b.ghost.appendTo(b.helper)},resize:function(){var b=e(this).data(\"resizable\");b.ghost&&b.ghost.css({position:\"relative\",
height:b.size.height,width:b.size.width})},stop:function(){var b=e(this).data(\"resizable\");b.ghost&&b.helper&&b.helper.get(0).removeChild(b.ghost.get(0))}});e.ui.plugin.add(\"resizable\",\"grid\",{resize:function(){var b=e(this).data(\"resizable\"),a=b.options,c=b.size,d=b.originalSize,f=b.originalPosition,g=b.axis;a.grid=typeof a.grid==\"number\"?[a.grid,a.grid]:a.grid;var h=Math.round((c.width-d.width)/(a.grid[0]||1))*(a.grid[0]||1);a=Math.round((c.height-d.height)/(a.grid[1]||1))*(a.grid[1]||1);if(/^(se|s|e)\$/.test(g)){b.size.width=
d.width+h;b.size.height=d.height+a}else if(/^(ne)\$/.test(g)){b.size.width=d.width+h;b.size.height=d.height+a;b.position.top=f.top-a}else{if(/^(sw)\$/.test(g)){b.size.width=d.width+h;b.size.height=d.height+a}else{b.size.width=d.width+h;b.size.height=d.height+a;b.position.top=f.top-a}b.position.left=f.left-h}}});var m=function(b){return parseInt(b,10)||0},l=function(b){return!isNaN(parseInt(b,10))}})(jQuery);
;/*
 * jQuery UI Selectable 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Selectables
 *
 * Depends:
 *	jquery.ui.core.js
 *	jquery.ui.mouse.js
 *	jquery.ui.widget.js
 */
(function(e){e.widget(\"ui.selectable\",e.ui.mouse,{options:{appendTo:\"body\",autoRefresh:true,distance:0,filter:\"*\",tolerance:\"touch\"},_create:function(){var c=this;this.element.addClass(\"ui-selectable\");this.dragged=false;var f;this.refresh=function(){f=e(c.options.filter,c.element[0]);f.each(function(){var d=e(this),b=d.offset();e.data(this,\"selectable-item\",{element:this,\$element:d,left:b.left,top:b.top,right:b.left+d.outerWidth(),bottom:b.top+d.outerHeight(),startselected:false,selected:d.hasClass(\"ui-selected\"),
selecting:d.hasClass(\"ui-selecting\"),unselecting:d.hasClass(\"ui-unselecting\")})})};this.refresh();this.selectees=f.addClass(\"ui-selectee\");this._mouseInit();this.helper=e(\"<div class='ui-selectable-helper'></div>\")},destroy:function(){this.selectees.removeClass(\"ui-selectee\").removeData(\"selectable-item\");this.element.removeClass(\"ui-selectable ui-selectable-disabled\").removeData(\"selectable\").unbind(\".selectable\");this._mouseDestroy();return this},_mouseStart:function(c){var f=this;this.opos=[c.pageX,
c.pageY];if(!this.options.disabled){var d=this.options;this.selectees=e(d.filter,this.element[0]);this._trigger(\"start\",c);e(d.appendTo).append(this.helper);this.helper.css({left:c.clientX,top:c.clientY,width:0,height:0});d.autoRefresh&&this.refresh();this.selectees.filter(\".ui-selected\").each(function(){var b=e.data(this,\"selectable-item\");b.startselected=true;if(!c.metaKey){b.\$element.removeClass(\"ui-selected\");b.selected=false;b.\$element.addClass(\"ui-unselecting\");b.unselecting=true;f._trigger(\"unselecting\",
c,{unselecting:b.element})}});e(c.target).parents().andSelf().each(function(){var b=e.data(this,\"selectable-item\");if(b){var g=!c.metaKey||!b.\$element.hasClass(\"ui-selected\");b.\$element.removeClass(g?\"ui-unselecting\":\"ui-selected\").addClass(g?\"ui-selecting\":\"ui-unselecting\");b.unselecting=!g;b.selecting=g;(b.selected=g)?f._trigger(\"selecting\",c,{selecting:b.element}):f._trigger(\"unselecting\",c,{unselecting:b.element});return false}})}},_mouseDrag:function(c){var f=this;this.dragged=true;if(!this.options.disabled){var d=
this.options,b=this.opos[0],g=this.opos[1],h=c.pageX,i=c.pageY;if(b>h){var j=h;h=b;b=j}if(g>i){j=i;i=g;g=j}this.helper.css({left:b,top:g,width:h-b,height:i-g});this.selectees.each(function(){var a=e.data(this,\"selectable-item\");if(!(!a||a.element==f.element[0])){var k=false;if(d.tolerance==\"touch\")k=!(a.left>h||a.right<b||a.top>i||a.bottom<g);else if(d.tolerance==\"fit\")k=a.left>b&&a.right<h&&a.top>g&&a.bottom<i;if(k){if(a.selected){a.\$element.removeClass(\"ui-selected\");a.selected=false}if(a.unselecting){a.\$element.removeClass(\"ui-unselecting\");
a.unselecting=false}if(!a.selecting){a.\$element.addClass(\"ui-selecting\");a.selecting=true;f._trigger(\"selecting\",c,{selecting:a.element})}}else{if(a.selecting)if(c.metaKey&&a.startselected){a.\$element.removeClass(\"ui-selecting\");a.selecting=false;a.\$element.addClass(\"ui-selected\");a.selected=true}else{a.\$element.removeClass(\"ui-selecting\");a.selecting=false;if(a.startselected){a.\$element.addClass(\"ui-unselecting\");a.unselecting=true}f._trigger(\"unselecting\",c,{unselecting:a.element})}if(a.selected)if(!c.metaKey&&
!a.startselected){a.\$element.removeClass(\"ui-selected\");a.selected=false;a.\$element.addClass(\"ui-unselecting\");a.unselecting=true;f._trigger(\"unselecting\",c,{unselecting:a.element})}}}});return false}},_mouseStop:function(c){var f=this;this.dragged=false;e(\".ui-unselecting\",this.element[0]).each(function(){var d=e.data(this,\"selectable-item\");d.\$element.removeClass(\"ui-unselecting\");d.unselecting=false;d.startselected=false;f._trigger(\"unselected\",c,{unselected:d.element})});e(\".ui-selecting\",this.element[0]).each(function(){var d=
e.data(this,\"selectable-item\");d.\$element.removeClass(\"ui-selecting\").addClass(\"ui-selected\");d.selecting=false;d.selected=true;d.startselected=true;f._trigger(\"selected\",c,{selected:d.element})});this._trigger(\"stop\",c);this.helper.remove();return false}});e.extend(e.ui.selectable,{version:\"1.8.5\"})})(jQuery);
;/*
 * jQuery UI Sortable 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Sortables
 *
 * Depends:
 *	jquery.ui.core.js
 *	jquery.ui.mouse.js
 *	jquery.ui.widget.js
 */
(function(d){d.widget(\"ui.sortable\",d.ui.mouse,{widgetEventPrefix:\"sort\",options:{appendTo:\"parent\",axis:false,connectWith:false,containment:false,cursor:\"auto\",cursorAt:false,dropOnEmpty:true,forcePlaceholderSize:false,forceHelperSize:false,grid:false,handle:false,helper:\"original\",items:\"> *\",opacity:false,placeholder:false,revert:false,scroll:true,scrollSensitivity:20,scrollSpeed:20,scope:\"default\",tolerance:\"intersect\",zIndex:1E3},_create:function(){this.containerCache={};this.element.addClass(\"ui-sortable\");
this.refresh();this.floating=this.items.length?/left|right/.test(this.items[0].item.css(\"float\")):false;this.offset=this.element.offset();this._mouseInit()},destroy:function(){this.element.removeClass(\"ui-sortable ui-sortable-disabled\").removeData(\"sortable\").unbind(\".sortable\");this._mouseDestroy();for(var a=this.items.length-1;a>=0;a--)this.items[a].item.removeData(\"sortable-item\");return this},_setOption:function(a,b){if(a===\"disabled\"){this.options[a]=b;this.widget()[b?\"addClass\":\"removeClass\"](\"ui-sortable-disabled\")}else d.Widget.prototype._setOption.apply(this,
arguments)},_mouseCapture:function(a,b){if(this.reverting)return false;if(this.options.disabled||this.options.type==\"static\")return false;this._refreshItems(a);var c=null,e=this;d(a.target).parents().each(function(){if(d.data(this,\"sortable-item\")==e){c=d(this);return false}});if(d.data(a.target,\"sortable-item\")==e)c=d(a.target);if(!c)return false;if(this.options.handle&&!b){var f=false;d(this.options.handle,c).find(\"*\").andSelf().each(function(){if(this==a.target)f=true});if(!f)return false}this.currentItem=
c;this._removeCurrentsFromItems();return true},_mouseStart:function(a,b,c){b=this.options;var e=this;this.currentContainer=this;this.refreshPositions();this.helper=this._createHelper(a);this._cacheHelperProportions();this._cacheMargins();this.scrollParent=this.helper.scrollParent();this.offset=this.currentItem.offset();this.offset={top:this.offset.top-this.margins.top,left:this.offset.left-this.margins.left};this.helper.css(\"position\",\"absolute\");this.cssPosition=this.helper.css(\"position\");d.extend(this.offset,
{click:{left:a.pageX-this.offset.left,top:a.pageY-this.offset.top},parent:this._getParentOffset(),relative:this._getRelativeOffset()});this.originalPosition=this._generatePosition(a);this.originalPageX=a.pageX;this.originalPageY=a.pageY;b.cursorAt&&this._adjustOffsetFromHelper(b.cursorAt);this.domPosition={prev:this.currentItem.prev()[0],parent:this.currentItem.parent()[0]};this.helper[0]!=this.currentItem[0]&&this.currentItem.hide();this._createPlaceholder();b.containment&&this._setContainment();
if(b.cursor){if(d(\"body\").css(\"cursor\"))this._storedCursor=d(\"body\").css(\"cursor\");d(\"body\").css(\"cursor\",b.cursor)}if(b.opacity){if(this.helper.css(\"opacity\"))this._storedOpacity=this.helper.css(\"opacity\");this.helper.css(\"opacity\",b.opacity)}if(b.zIndex){if(this.helper.css(\"zIndex\"))this._storedZIndex=this.helper.css(\"zIndex\");this.helper.css(\"zIndex\",b.zIndex)}if(this.scrollParent[0]!=document&&this.scrollParent[0].tagName!=\"HTML\")this.overflowOffset=this.scrollParent.offset();this._trigger(\"start\",
a,this._uiHash());this._preserveHelperProportions||this._cacheHelperProportions();if(!c)for(c=this.containers.length-1;c>=0;c--)this.containers[c]._trigger(\"activate\",a,e._uiHash(this));if(d.ui.ddmanager)d.ui.ddmanager.current=this;d.ui.ddmanager&&!b.dropBehaviour&&d.ui.ddmanager.prepareOffsets(this,a);this.dragging=true;this.helper.addClass(\"ui-sortable-helper\");this._mouseDrag(a);return true},_mouseDrag:function(a){this.position=this._generatePosition(a);this.positionAbs=this._convertPositionTo(\"absolute\");
if(!this.lastPositionAbs)this.lastPositionAbs=this.positionAbs;if(this.options.scroll){var b=this.options,c=false;if(this.scrollParent[0]!=document&&this.scrollParent[0].tagName!=\"HTML\"){if(this.overflowOffset.top+this.scrollParent[0].offsetHeight-a.pageY<b.scrollSensitivity)this.scrollParent[0].scrollTop=c=this.scrollParent[0].scrollTop+b.scrollSpeed;else if(a.pageY-this.overflowOffset.top<b.scrollSensitivity)this.scrollParent[0].scrollTop=c=this.scrollParent[0].scrollTop-b.scrollSpeed;if(this.overflowOffset.left+
this.scrollParent[0].offsetWidth-a.pageX<b.scrollSensitivity)this.scrollParent[0].scrollLeft=c=this.scrollParent[0].scrollLeft+b.scrollSpeed;else if(a.pageX-this.overflowOffset.left<b.scrollSensitivity)this.scrollParent[0].scrollLeft=c=this.scrollParent[0].scrollLeft-b.scrollSpeed}else{if(a.pageY-d(document).scrollTop()<b.scrollSensitivity)c=d(document).scrollTop(d(document).scrollTop()-b.scrollSpeed);else if(d(window).height()-(a.pageY-d(document).scrollTop())<b.scrollSensitivity)c=d(document).scrollTop(d(document).scrollTop()+
b.scrollSpeed);if(a.pageX-d(document).scrollLeft()<b.scrollSensitivity)c=d(document).scrollLeft(d(document).scrollLeft()-b.scrollSpeed);else if(d(window).width()-(a.pageX-d(document).scrollLeft())<b.scrollSensitivity)c=d(document).scrollLeft(d(document).scrollLeft()+b.scrollSpeed)}c!==false&&d.ui.ddmanager&&!b.dropBehaviour&&d.ui.ddmanager.prepareOffsets(this,a)}this.positionAbs=this._convertPositionTo(\"absolute\");if(!this.options.axis||this.options.axis!=\"y\")this.helper[0].style.left=this.position.left+
\"px\";if(!this.options.axis||this.options.axis!=\"x\")this.helper[0].style.top=this.position.top+\"px\";for(b=this.items.length-1;b>=0;b--){c=this.items[b];var e=c.item[0],f=this._intersectsWithPointer(c);if(f)if(e!=this.currentItem[0]&&this.placeholder[f==1?\"next\":\"prev\"]()[0]!=e&&!d.ui.contains(this.placeholder[0],e)&&(this.options.type==\"semi-dynamic\"?!d.ui.contains(this.element[0],e):true)){this.direction=f==1?\"down\":\"up\";if(this.options.tolerance==\"pointer\"||this._intersectsWithSides(c))this._rearrange(a,
c);else break;this._trigger(\"change\",a,this._uiHash());break}}this._contactContainers(a);d.ui.ddmanager&&d.ui.ddmanager.drag(this,a);this._trigger(\"sort\",a,this._uiHash());this.lastPositionAbs=this.positionAbs;return false},_mouseStop:function(a,b){if(a){d.ui.ddmanager&&!this.options.dropBehaviour&&d.ui.ddmanager.drop(this,a);if(this.options.revert){var c=this;b=c.placeholder.offset();c.reverting=true;d(this.helper).animate({left:b.left-this.offset.parent.left-c.margins.left+(this.offsetParent[0]==
document.body?0:this.offsetParent[0].scrollLeft),top:b.top-this.offset.parent.top-c.margins.top+(this.offsetParent[0]==document.body?0:this.offsetParent[0].scrollTop)},parseInt(this.options.revert,10)||500,function(){c._clear(a)})}else this._clear(a,b);return false}},cancel:function(){var a=this;if(this.dragging){this._mouseUp();this.options.helper==\"original\"?this.currentItem.css(this._storedCSS).removeClass(\"ui-sortable-helper\"):this.currentItem.show();for(var b=this.containers.length-1;b>=0;b--){this.containers[b]._trigger(\"deactivate\",
null,a._uiHash(this));if(this.containers[b].containerCache.over){this.containers[b]._trigger(\"out\",null,a._uiHash(this));this.containers[b].containerCache.over=0}}}this.placeholder[0].parentNode&&this.placeholder[0].parentNode.removeChild(this.placeholder[0]);this.options.helper!=\"original\"&&this.helper&&this.helper[0].parentNode&&this.helper.remove();d.extend(this,{helper:null,dragging:false,reverting:false,_noFinalSort:null});this.domPosition.prev?d(this.domPosition.prev).after(this.currentItem):
d(this.domPosition.parent).prepend(this.currentItem);return this},serialize:function(a){var b=this._getItemsAsjQuery(a&&a.connected),c=[];a=a||{};d(b).each(function(){var e=(d(a.item||this).attr(a.attribute||\"id\")||\"\").match(a.expression||/(.+)[-=_](.+)/);if(e)c.push((a.key||e[1]+\"[]\")+\"=\"+(a.key&&a.expression?e[1]:e[2]))});!c.length&&a.key&&c.push(a.key+\"=\");return c.join(\"&\")},toArray:function(a){var b=this._getItemsAsjQuery(a&&a.connected),c=[];a=a||{};b.each(function(){c.push(d(a.item||this).attr(a.attribute||
\"id\")||\"\")});return c},_intersectsWith:function(a){var b=this.positionAbs.left,c=b+this.helperProportions.width,e=this.positionAbs.top,f=e+this.helperProportions.height,g=a.left,h=g+a.width,i=a.top,k=i+a.height,j=this.offset.click.top,l=this.offset.click.left;j=e+j>i&&e+j<k&&b+l>g&&b+l<h;return this.options.tolerance==\"pointer\"||this.options.forcePointerForContainers||this.options.tolerance!=\"pointer\"&&this.helperProportions[this.floating?\"width\":\"height\"]>a[this.floating?\"width\":\"height\"]?j:g<b+
this.helperProportions.width/2&&c-this.helperProportions.width/2<h&&i<e+this.helperProportions.height/2&&f-this.helperProportions.height/2<k},_intersectsWithPointer:function(a){var b=d.ui.isOverAxis(this.positionAbs.top+this.offset.click.top,a.top,a.height);a=d.ui.isOverAxis(this.positionAbs.left+this.offset.click.left,a.left,a.width);b=b&&a;a=this._getDragVerticalDirection();var c=this._getDragHorizontalDirection();if(!b)return false;return this.floating?c&&c==\"right\"||a==\"down\"?2:1:a&&(a==\"down\"?
2:1)},_intersectsWithSides:function(a){var b=d.ui.isOverAxis(this.positionAbs.top+this.offset.click.top,a.top+a.height/2,a.height);a=d.ui.isOverAxis(this.positionAbs.left+this.offset.click.left,a.left+a.width/2,a.width);var c=this._getDragVerticalDirection(),e=this._getDragHorizontalDirection();return this.floating&&e?e==\"right\"&&a||e==\"left\"&&!a:c&&(c==\"down\"&&b||c==\"up\"&&!b)},_getDragVerticalDirection:function(){var a=this.positionAbs.top-this.lastPositionAbs.top;return a!=0&&(a>0?\"down\":\"up\")},
_getDragHorizontalDirection:function(){var a=this.positionAbs.left-this.lastPositionAbs.left;return a!=0&&(a>0?\"right\":\"left\")},refresh:function(a){this._refreshItems(a);this.refreshPositions();return this},_connectWith:function(){var a=this.options;return a.connectWith.constructor==String?[a.connectWith]:a.connectWith},_getItemsAsjQuery:function(a){var b=[],c=[],e=this._connectWith();if(e&&a)for(a=e.length-1;a>=0;a--)for(var f=d(e[a]),g=f.length-1;g>=0;g--){var h=d.data(f[g],\"sortable\");if(h&&h!=
this&&!h.options.disabled)c.push([d.isFunction(h.options.items)?h.options.items.call(h.element):d(h.options.items,h.element).not(\".ui-sortable-helper\").not(\".ui-sortable-placeholder\"),h])}c.push([d.isFunction(this.options.items)?this.options.items.call(this.element,null,{options:this.options,item:this.currentItem}):d(this.options.items,this.element).not(\".ui-sortable-helper\").not(\".ui-sortable-placeholder\"),this]);for(a=c.length-1;a>=0;a--)c[a][0].each(function(){b.push(this)});return d(b)},_removeCurrentsFromItems:function(){for(var a=
this.currentItem.find(\":data(sortable-item)\"),b=0;b<this.items.length;b++)for(var c=0;c<a.length;c++)a[c]==this.items[b].item[0]&&this.items.splice(b,1)},_refreshItems:function(a){this.items=[];this.containers=[this];var b=this.items,c=[[d.isFunction(this.options.items)?this.options.items.call(this.element[0],a,{item:this.currentItem}):d(this.options.items,this.element),this]],e=this._connectWith();if(e)for(var f=e.length-1;f>=0;f--)for(var g=d(e[f]),h=g.length-1;h>=0;h--){var i=d.data(g[h],\"sortable\");
if(i&&i!=this&&!i.options.disabled){c.push([d.isFunction(i.options.items)?i.options.items.call(i.element[0],a,{item:this.currentItem}):d(i.options.items,i.element),i]);this.containers.push(i)}}for(f=c.length-1;f>=0;f--){a=c[f][1];e=c[f][0];h=0;for(g=e.length;h<g;h++){i=d(e[h]);i.data(\"sortable-item\",a);b.push({item:i,instance:a,width:0,height:0,left:0,top:0})}}},refreshPositions:function(a){if(this.offsetParent&&this.helper)this.offset.parent=this._getParentOffset();for(var b=this.items.length-1;b>=
0;b--){var c=this.items[b],e=this.options.toleranceElement?d(this.options.toleranceElement,c.item):c.item;if(!a){c.width=e.outerWidth();c.height=e.outerHeight()}e=e.offset();c.left=e.left;c.top=e.top}if(this.options.custom&&this.options.custom.refreshContainers)this.options.custom.refreshContainers.call(this);else for(b=this.containers.length-1;b>=0;b--){e=this.containers[b].element.offset();this.containers[b].containerCache.left=e.left;this.containers[b].containerCache.top=e.top;this.containers[b].containerCache.width=
this.containers[b].element.outerWidth();this.containers[b].containerCache.height=this.containers[b].element.outerHeight()}return this},_createPlaceholder:function(a){var b=a||this,c=b.options;if(!c.placeholder||c.placeholder.constructor==String){var e=c.placeholder;c.placeholder={element:function(){var f=d(document.createElement(b.currentItem[0].nodeName)).addClass(e||b.currentItem[0].className+\" ui-sortable-placeholder\").removeClass(\"ui-sortable-helper\")[0];if(!e)f.style.visibility=\"hidden\";return f},
update:function(f,g){if(!(e&&!c.forcePlaceholderSize)){g.height()||g.height(b.currentItem.innerHeight()-parseInt(b.currentItem.css(\"paddingTop\")||0,10)-parseInt(b.currentItem.css(\"paddingBottom\")||0,10));g.width()||g.width(b.currentItem.innerWidth()-parseInt(b.currentItem.css(\"paddingLeft\")||0,10)-parseInt(b.currentItem.css(\"paddingRight\")||0,10))}}}}b.placeholder=d(c.placeholder.element.call(b.element,b.currentItem));b.currentItem.after(b.placeholder);c.placeholder.update(b,b.placeholder)},_contactContainers:function(a){for(var b=
null,c=null,e=this.containers.length-1;e>=0;e--)if(!d.ui.contains(this.currentItem[0],this.containers[e].element[0]))if(this._intersectsWith(this.containers[e].containerCache)){if(!(b&&d.ui.contains(this.containers[e].element[0],b.element[0]))){b=this.containers[e];c=e}}else if(this.containers[e].containerCache.over){this.containers[e]._trigger(\"out\",a,this._uiHash(this));this.containers[e].containerCache.over=0}if(b)if(this.containers.length===1){this.containers[c]._trigger(\"over\",a,this._uiHash(this));
this.containers[c].containerCache.over=1}else if(this.currentContainer!=this.containers[c]){b=1E4;e=null;for(var f=this.positionAbs[this.containers[c].floating?\"left\":\"top\"],g=this.items.length-1;g>=0;g--)if(d.ui.contains(this.containers[c].element[0],this.items[g].item[0])){var h=this.items[g][this.containers[c].floating?\"left\":\"top\"];if(Math.abs(h-f)<b){b=Math.abs(h-f);e=this.items[g]}}if(e||this.options.dropOnEmpty){this.currentContainer=this.containers[c];e?this._rearrange(a,e,null,true):this._rearrange(a,
null,this.containers[c].element,true);this._trigger(\"change\",a,this._uiHash());this.containers[c]._trigger(\"change\",a,this._uiHash(this));this.options.placeholder.update(this.currentContainer,this.placeholder);this.containers[c]._trigger(\"over\",a,this._uiHash(this));this.containers[c].containerCache.over=1}}},_createHelper:function(a){var b=this.options;a=d.isFunction(b.helper)?d(b.helper.apply(this.element[0],[a,this.currentItem])):b.helper==\"clone\"?this.currentItem.clone():this.currentItem;a.parents(\"body\").length||
d(b.appendTo!=\"parent\"?b.appendTo:this.currentItem[0].parentNode)[0].appendChild(a[0]);if(a[0]==this.currentItem[0])this._storedCSS={width:this.currentItem[0].style.width,height:this.currentItem[0].style.height,position:this.currentItem.css(\"position\"),top:this.currentItem.css(\"top\"),left:this.currentItem.css(\"left\")};if(a[0].style.width==\"\"||b.forceHelperSize)a.width(this.currentItem.width());if(a[0].style.height==\"\"||b.forceHelperSize)a.height(this.currentItem.height());return a},_adjustOffsetFromHelper:function(a){if(typeof a==
\"string\")a=a.split(\" \");if(d.isArray(a))a={left:+a[0],top:+a[1]||0};if(\"left\"in a)this.offset.click.left=a.left+this.margins.left;if(\"right\"in a)this.offset.click.left=this.helperProportions.width-a.right+this.margins.left;if(\"top\"in a)this.offset.click.top=a.top+this.margins.top;if(\"bottom\"in a)this.offset.click.top=this.helperProportions.height-a.bottom+this.margins.top},_getParentOffset:function(){this.offsetParent=this.helper.offsetParent();var a=this.offsetParent.offset();if(this.cssPosition==
\"absolute\"&&this.scrollParent[0]!=document&&d.ui.contains(this.scrollParent[0],this.offsetParent[0])){a.left+=this.scrollParent.scrollLeft();a.top+=this.scrollParent.scrollTop()}if(this.offsetParent[0]==document.body||this.offsetParent[0].tagName&&this.offsetParent[0].tagName.toLowerCase()==\"html\"&&d.browser.msie)a={top:0,left:0};return{top:a.top+(parseInt(this.offsetParent.css(\"borderTopWidth\"),10)||0),left:a.left+(parseInt(this.offsetParent.css(\"borderLeftWidth\"),10)||0)}},_getRelativeOffset:function(){if(this.cssPosition==
\"relative\"){var a=this.currentItem.position();return{top:a.top-(parseInt(this.helper.css(\"top\"),10)||0)+this.scrollParent.scrollTop(),left:a.left-(parseInt(this.helper.css(\"left\"),10)||0)+this.scrollParent.scrollLeft()}}else return{top:0,left:0}},_cacheMargins:function(){this.margins={left:parseInt(this.currentItem.css(\"marginLeft\"),10)||0,top:parseInt(this.currentItem.css(\"marginTop\"),10)||0}},_cacheHelperProportions:function(){this.helperProportions={width:this.helper.outerWidth(),height:this.helper.outerHeight()}},
_setContainment:function(){var a=this.options;if(a.containment==\"parent\")a.containment=this.helper[0].parentNode;if(a.containment==\"document\"||a.containment==\"window\")this.containment=[0-this.offset.relative.left-this.offset.parent.left,0-this.offset.relative.top-this.offset.parent.top,d(a.containment==\"document\"?document:window).width()-this.helperProportions.width-this.margins.left,(d(a.containment==\"document\"?document:window).height()||document.body.parentNode.scrollHeight)-this.helperProportions.height-
this.margins.top];if(!/^(document|window|parent)\$/.test(a.containment)){var b=d(a.containment)[0];a=d(a.containment).offset();var c=d(b).css(\"overflow\")!=\"hidden\";this.containment=[a.left+(parseInt(d(b).css(\"borderLeftWidth\"),10)||0)+(parseInt(d(b).css(\"paddingLeft\"),10)||0)-this.margins.left,a.top+(parseInt(d(b).css(\"borderTopWidth\"),10)||0)+(parseInt(d(b).css(\"paddingTop\"),10)||0)-this.margins.top,a.left+(c?Math.max(b.scrollWidth,b.offsetWidth):b.offsetWidth)-(parseInt(d(b).css(\"borderLeftWidth\"),
10)||0)-(parseInt(d(b).css(\"paddingRight\"),10)||0)-this.helperProportions.width-this.margins.left,a.top+(c?Math.max(b.scrollHeight,b.offsetHeight):b.offsetHeight)-(parseInt(d(b).css(\"borderTopWidth\"),10)||0)-(parseInt(d(b).css(\"paddingBottom\"),10)||0)-this.helperProportions.height-this.margins.top]}},_convertPositionTo:function(a,b){if(!b)b=this.position;a=a==\"absolute\"?1:-1;var c=this.cssPosition==\"absolute\"&&!(this.scrollParent[0]!=document&&d.ui.contains(this.scrollParent[0],this.offsetParent[0]))?
this.offsetParent:this.scrollParent,e=/(html|body)/i.test(c[0].tagName);return{top:b.top+this.offset.relative.top*a+this.offset.parent.top*a-(d.browser.safari&&this.cssPosition==\"fixed\"?0:(this.cssPosition==\"fixed\"?-this.scrollParent.scrollTop():e?0:c.scrollTop())*a),left:b.left+this.offset.relative.left*a+this.offset.parent.left*a-(d.browser.safari&&this.cssPosition==\"fixed\"?0:(this.cssPosition==\"fixed\"?-this.scrollParent.scrollLeft():e?0:c.scrollLeft())*a)}},_generatePosition:function(a){var b=
this.options,c=this.cssPosition==\"absolute\"&&!(this.scrollParent[0]!=document&&d.ui.contains(this.scrollParent[0],this.offsetParent[0]))?this.offsetParent:this.scrollParent,e=/(html|body)/i.test(c[0].tagName);if(this.cssPosition==\"relative\"&&!(this.scrollParent[0]!=document&&this.scrollParent[0]!=this.offsetParent[0]))this.offset.relative=this._getRelativeOffset();var f=a.pageX,g=a.pageY;if(this.originalPosition){if(this.containment){if(a.pageX-this.offset.click.left<this.containment[0])f=this.containment[0]+
this.offset.click.left;if(a.pageY-this.offset.click.top<this.containment[1])g=this.containment[1]+this.offset.click.top;if(a.pageX-this.offset.click.left>this.containment[2])f=this.containment[2]+this.offset.click.left;if(a.pageY-this.offset.click.top>this.containment[3])g=this.containment[3]+this.offset.click.top}if(b.grid){g=this.originalPageY+Math.round((g-this.originalPageY)/b.grid[1])*b.grid[1];g=this.containment?!(g-this.offset.click.top<this.containment[1]||g-this.offset.click.top>this.containment[3])?
g:!(g-this.offset.click.top<this.containment[1])?g-b.grid[1]:g+b.grid[1]:g;f=this.originalPageX+Math.round((f-this.originalPageX)/b.grid[0])*b.grid[0];f=this.containment?!(f-this.offset.click.left<this.containment[0]||f-this.offset.click.left>this.containment[2])?f:!(f-this.offset.click.left<this.containment[0])?f-b.grid[0]:f+b.grid[0]:f}}return{top:g-this.offset.click.top-this.offset.relative.top-this.offset.parent.top+(d.browser.safari&&this.cssPosition==\"fixed\"?0:this.cssPosition==\"fixed\"?-this.scrollParent.scrollTop():
e?0:c.scrollTop()),left:f-this.offset.click.left-this.offset.relative.left-this.offset.parent.left+(d.browser.safari&&this.cssPosition==\"fixed\"?0:this.cssPosition==\"fixed\"?-this.scrollParent.scrollLeft():e?0:c.scrollLeft())}},_rearrange:function(a,b,c,e){c?c[0].appendChild(this.placeholder[0]):b.item[0].parentNode.insertBefore(this.placeholder[0],this.direction==\"down\"?b.item[0]:b.item[0].nextSibling);this.counter=this.counter?++this.counter:1;var f=this,g=this.counter;window.setTimeout(function(){g==
f.counter&&f.refreshPositions(!e)},0)},_clear:function(a,b){this.reverting=false;var c=[];!this._noFinalSort&&this.currentItem[0].parentNode&&this.placeholder.before(this.currentItem);this._noFinalSort=null;if(this.helper[0]==this.currentItem[0]){for(var e in this._storedCSS)if(this._storedCSS[e]==\"auto\"||this._storedCSS[e]==\"static\")this._storedCSS[e]=\"\";this.currentItem.css(this._storedCSS).removeClass(\"ui-sortable-helper\")}else this.currentItem.show();this.fromOutside&&!b&&c.push(function(f){this._trigger(\"receive\",
f,this._uiHash(this.fromOutside))});if((this.fromOutside||this.domPosition.prev!=this.currentItem.prev().not(\".ui-sortable-helper\")[0]||this.domPosition.parent!=this.currentItem.parent()[0])&&!b)c.push(function(f){this._trigger(\"update\",f,this._uiHash())});if(!d.ui.contains(this.element[0],this.currentItem[0])){b||c.push(function(f){this._trigger(\"remove\",f,this._uiHash())});for(e=this.containers.length-1;e>=0;e--)if(d.ui.contains(this.containers[e].element[0],this.currentItem[0])&&!b){c.push(function(f){return function(g){f._trigger(\"receive\",
g,this._uiHash(this))}}.call(this,this.containers[e]));c.push(function(f){return function(g){f._trigger(\"update\",g,this._uiHash(this))}}.call(this,this.containers[e]))}}for(e=this.containers.length-1;e>=0;e--){b||c.push(function(f){return function(g){f._trigger(\"deactivate\",g,this._uiHash(this))}}.call(this,this.containers[e]));if(this.containers[e].containerCache.over){c.push(function(f){return function(g){f._trigger(\"out\",g,this._uiHash(this))}}.call(this,this.containers[e]));this.containers[e].containerCache.over=
0}}this._storedCursor&&d(\"body\").css(\"cursor\",this._storedCursor);this._storedOpacity&&this.helper.css(\"opacity\",this._storedOpacity);if(this._storedZIndex)this.helper.css(\"zIndex\",this._storedZIndex==\"auto\"?\"\":this._storedZIndex);this.dragging=false;if(this.cancelHelperRemoval){if(!b){this._trigger(\"beforeStop\",a,this._uiHash());for(e=0;e<c.length;e++)c[e].call(this,a);this._trigger(\"stop\",a,this._uiHash())}return false}b||this._trigger(\"beforeStop\",a,this._uiHash());this.placeholder[0].parentNode.removeChild(this.placeholder[0]);
this.helper[0]!=this.currentItem[0]&&this.helper.remove();this.helper=null;if(!b){for(e=0;e<c.length;e++)c[e].call(this,a);this._trigger(\"stop\",a,this._uiHash())}this.fromOutside=false;return true},_trigger:function(){d.Widget.prototype._trigger.apply(this,arguments)===false&&this.cancel()},_uiHash:function(a){var b=a||this;return{helper:b.helper,placeholder:b.placeholder||d([]),position:b.position,originalPosition:b.originalPosition,offset:b.positionAbs,item:b.currentItem,sender:a?a.element:null}}});
d.extend(d.ui.sortable,{version:\"1.8.5\"})})(jQuery);
;/*
 * jQuery UI Slider 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Slider
 *
 * Depends:
 *	jquery.ui.core.js
 *	jquery.ui.mouse.js
 *	jquery.ui.widget.js
 */
(function(d){d.widget(\"ui.slider\",d.ui.mouse,{widgetEventPrefix:\"slide\",options:{animate:false,distance:0,max:100,min:0,orientation:\"horizontal\",range:false,step:1,value:0,values:null},_create:function(){var a=this,b=this.options;this._mouseSliding=this._keySliding=false;this._animateOff=true;this._handleIndex=null;this._detectOrientation();this._mouseInit();this.element.addClass(\"ui-slider ui-slider-\"+this.orientation+\" ui-widget ui-widget-content ui-corner-all\");b.disabled&&this.element.addClass(\"ui-slider-disabled ui-disabled\");
this.range=d([]);if(b.range){if(b.range===true){this.range=d(\"<div></div>\");if(!b.values)b.values=[this._valueMin(),this._valueMin()];if(b.values.length&&b.values.length!==2)b.values=[b.values[0],b.values[0]]}else this.range=d(\"<div></div>\");this.range.appendTo(this.element).addClass(\"ui-slider-range\");if(b.range===\"min\"||b.range===\"max\")this.range.addClass(\"ui-slider-range-\"+b.range);this.range.addClass(\"ui-widget-header\")}d(\".ui-slider-handle\",this.element).length===0&&d(\"<a href='#'></a>\").appendTo(this.element).addClass(\"ui-slider-handle\");
if(b.values&&b.values.length)for(;d(\".ui-slider-handle\",this.element).length<b.values.length;)d(\"<a href='#'></a>\").appendTo(this.element).addClass(\"ui-slider-handle\");this.handles=d(\".ui-slider-handle\",this.element).addClass(\"ui-state-default ui-corner-all\");this.handle=this.handles.eq(0);this.handles.add(this.range).filter(\"a\").click(function(c){c.preventDefault()}).hover(function(){b.disabled||d(this).addClass(\"ui-state-hover\")},function(){d(this).removeClass(\"ui-state-hover\")}).focus(function(){if(b.disabled)d(this).blur();
else{d(\".ui-slider .ui-state-focus\").removeClass(\"ui-state-focus\");d(this).addClass(\"ui-state-focus\")}}).blur(function(){d(this).removeClass(\"ui-state-focus\")});this.handles.each(function(c){d(this).data(\"index.ui-slider-handle\",c)});this.handles.keydown(function(c){var e=true,f=d(this).data(\"index.ui-slider-handle\"),h,g,i;if(!a.options.disabled){switch(c.keyCode){case d.ui.keyCode.HOME:case d.ui.keyCode.END:case d.ui.keyCode.PAGE_UP:case d.ui.keyCode.PAGE_DOWN:case d.ui.keyCode.UP:case d.ui.keyCode.RIGHT:case d.ui.keyCode.DOWN:case d.ui.keyCode.LEFT:e=
false;if(!a._keySliding){a._keySliding=true;d(this).addClass(\"ui-state-active\");h=a._start(c,f);if(h===false)return}break}i=a.options.step;h=a.options.values&&a.options.values.length?(g=a.values(f)):(g=a.value());switch(c.keyCode){case d.ui.keyCode.HOME:g=a._valueMin();break;case d.ui.keyCode.END:g=a._valueMax();break;case d.ui.keyCode.PAGE_UP:g=a._trimAlignValue(h+(a._valueMax()-a._valueMin())/5);break;case d.ui.keyCode.PAGE_DOWN:g=a._trimAlignValue(h-(a._valueMax()-a._valueMin())/5);break;case d.ui.keyCode.UP:case d.ui.keyCode.RIGHT:if(h===
a._valueMax())return;g=a._trimAlignValue(h+i);break;case d.ui.keyCode.DOWN:case d.ui.keyCode.LEFT:if(h===a._valueMin())return;g=a._trimAlignValue(h-i);break}a._slide(c,f,g);return e}}).keyup(function(c){var e=d(this).data(\"index.ui-slider-handle\");if(a._keySliding){a._keySliding=false;a._stop(c,e);a._change(c,e);d(this).removeClass(\"ui-state-active\")}});this._refreshValue();this._animateOff=false},destroy:function(){this.handles.remove();this.range.remove();this.element.removeClass(\"ui-slider ui-slider-horizontal ui-slider-vertical ui-slider-disabled ui-widget ui-widget-content ui-corner-all\").removeData(\"slider\").unbind(\".slider\");
this._mouseDestroy();return this},_mouseCapture:function(a){var b=this.options,c,e,f,h,g;if(b.disabled)return false;this.elementSize={width:this.element.outerWidth(),height:this.element.outerHeight()};this.elementOffset=this.element.offset();c=this._normValueFromMouse({x:a.pageX,y:a.pageY});e=this._valueMax()-this._valueMin()+1;h=this;this.handles.each(function(i){var j=Math.abs(c-h.values(i));if(e>j){e=j;f=d(this);g=i}});if(b.range===true&&this.values(1)===b.min){g+=1;f=d(this.handles[g])}if(this._start(a,
g)===false)return false;this._mouseSliding=true;h._handleIndex=g;f.addClass(\"ui-state-active\").focus();b=f.offset();this._clickOffset=!d(a.target).parents().andSelf().is(\".ui-slider-handle\")?{left:0,top:0}:{left:a.pageX-b.left-f.width()/2,top:a.pageY-b.top-f.height()/2-(parseInt(f.css(\"borderTopWidth\"),10)||0)-(parseInt(f.css(\"borderBottomWidth\"),10)||0)+(parseInt(f.css(\"marginTop\"),10)||0)};this._slide(a,g,c);return this._animateOff=true},_mouseStart:function(){return true},_mouseDrag:function(a){var b=
this._normValueFromMouse({x:a.pageX,y:a.pageY});this._slide(a,this._handleIndex,b);return false},_mouseStop:function(a){this.handles.removeClass(\"ui-state-active\");this._mouseSliding=false;this._stop(a,this._handleIndex);this._change(a,this._handleIndex);this._clickOffset=this._handleIndex=null;return this._animateOff=false},_detectOrientation:function(){this.orientation=this.options.orientation===\"vertical\"?\"vertical\":\"horizontal\"},_normValueFromMouse:function(a){var b;if(this.orientation===\"horizontal\"){b=
this.elementSize.width;a=a.x-this.elementOffset.left-(this._clickOffset?this._clickOffset.left:0)}else{b=this.elementSize.height;a=a.y-this.elementOffset.top-(this._clickOffset?this._clickOffset.top:0)}b=a/b;if(b>1)b=1;if(b<0)b=0;if(this.orientation===\"vertical\")b=1-b;a=this._valueMax()-this._valueMin();return this._trimAlignValue(this._valueMin()+b*a)},_start:function(a,b){var c={handle:this.handles[b],value:this.value()};if(this.options.values&&this.options.values.length){c.value=this.values(b);
c.values=this.values()}return this._trigger(\"start\",a,c)},_slide:function(a,b,c){var e;if(this.options.values&&this.options.values.length){e=this.values(b?0:1);if(this.options.values.length===2&&this.options.range===true&&(b===0&&c>e||b===1&&c<e))c=e;if(c!==this.values(b)){e=this.values();e[b]=c;a=this._trigger(\"slide\",a,{handle:this.handles[b],value:c,values:e});this.values(b?0:1);a!==false&&this.values(b,c,true)}}else if(c!==this.value()){a=this._trigger(\"slide\",a,{handle:this.handles[b],value:c});
a!==false&&this.value(c)}},_stop:function(a,b){var c={handle:this.handles[b],value:this.value()};if(this.options.values&&this.options.values.length){c.value=this.values(b);c.values=this.values()}this._trigger(\"stop\",a,c)},_change:function(a,b){if(!this._keySliding&&!this._mouseSliding){var c={handle:this.handles[b],value:this.value()};if(this.options.values&&this.options.values.length){c.value=this.values(b);c.values=this.values()}this._trigger(\"change\",a,c)}},value:function(a){if(arguments.length){this.options.value=
this._trimAlignValue(a);this._refreshValue();this._change(null,0)}return this._value()},values:function(a,b){var c,e,f;if(arguments.length>1){this.options.values[a]=this._trimAlignValue(b);this._refreshValue();this._change(null,a)}if(arguments.length)if(d.isArray(arguments[0])){c=this.options.values;e=arguments[0];for(f=0;f<c.length;f+=1){c[f]=this._trimAlignValue(e[f]);this._change(null,f)}this._refreshValue()}else return this.options.values&&this.options.values.length?this._values(a):this.value();
else return this._values()},_setOption:function(a,b){var c,e=0;if(d.isArray(this.options.values))e=this.options.values.length;d.Widget.prototype._setOption.apply(this,arguments);switch(a){case \"disabled\":if(b){this.handles.filter(\".ui-state-focus\").blur();this.handles.removeClass(\"ui-state-hover\");this.handles.attr(\"disabled\",\"disabled\");this.element.addClass(\"ui-disabled\")}else{this.handles.removeAttr(\"disabled\");this.element.removeClass(\"ui-disabled\")}break;case \"orientation\":this._detectOrientation();
this.element.removeClass(\"ui-slider-horizontal ui-slider-vertical\").addClass(\"ui-slider-\"+this.orientation);this._refreshValue();break;case \"value\":this._animateOff=true;this._refreshValue();this._change(null,0);this._animateOff=false;break;case \"values\":this._animateOff=true;this._refreshValue();for(c=0;c<e;c+=1)this._change(null,c);this._animateOff=false;break}},_value:function(){var a=this.options.value;return a=this._trimAlignValue(a)},_values:function(a){var b,c;if(arguments.length){b=this.options.values[a];
return b=this._trimAlignValue(b)}else{b=this.options.values.slice();for(c=0;c<b.length;c+=1)b[c]=this._trimAlignValue(b[c]);return b}},_trimAlignValue:function(a){if(a<this._valueMin())return this._valueMin();if(a>this._valueMax())return this._valueMax();var b=this.options.step>0?this.options.step:1,c=a%b;a=a-c;if(Math.abs(c)*2>=b)a+=c>0?b:-b;return parseFloat(a.toFixed(5))},_valueMin:function(){return this.options.min},_valueMax:function(){return this.options.max},_refreshValue:function(){var a=
this.options.range,b=this.options,c=this,e=!this._animateOff?b.animate:false,f,h={},g,i,j,l;if(this.options.values&&this.options.values.length)this.handles.each(function(k){f=(c.values(k)-c._valueMin())/(c._valueMax()-c._valueMin())*100;h[c.orientation===\"horizontal\"?\"left\":\"bottom\"]=f+\"%\";d(this).stop(1,1)[e?\"animate\":\"css\"](h,b.animate);if(c.options.range===true)if(c.orientation===\"horizontal\"){if(k===0)c.range.stop(1,1)[e?\"animate\":\"css\"]({left:f+\"%\"},b.animate);if(k===1)c.range[e?\"animate\":\"css\"]({width:f-
g+\"%\"},{queue:false,duration:b.animate})}else{if(k===0)c.range.stop(1,1)[e?\"animate\":\"css\"]({bottom:f+\"%\"},b.animate);if(k===1)c.range[e?\"animate\":\"css\"]({height:f-g+\"%\"},{queue:false,duration:b.animate})}g=f});else{i=this.value();j=this._valueMin();l=this._valueMax();f=l!==j?(i-j)/(l-j)*100:0;h[c.orientation===\"horizontal\"?\"left\":\"bottom\"]=f+\"%\";this.handle.stop(1,1)[e?\"animate\":\"css\"](h,b.animate);if(a===\"min\"&&this.orientation===\"horizontal\")this.range.stop(1,1)[e?\"animate\":\"css\"]({width:f+\"%\"},
b.animate);if(a===\"max\"&&this.orientation===\"horizontal\")this.range[e?\"animate\":\"css\"]({width:100-f+\"%\"},{queue:false,duration:b.animate});if(a===\"min\"&&this.orientation===\"vertical\")this.range.stop(1,1)[e?\"animate\":\"css\"]({height:f+\"%\"},b.animate);if(a===\"max\"&&this.orientation===\"vertical\")this.range[e?\"animate\":\"css\"]({height:100-f+\"%\"},{queue:false,duration:b.animate})}}});d.extend(d.ui.slider,{version:\"1.8.5\"})})(jQuery);
;/*
 * jQuery UI Effects 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/
 */
jQuery.effects||function(f,j){function l(c){var a;if(c&&c.constructor==Array&&c.length==3)return c;if(a=/rgb\\(\\s*([0-9]{1,3})\\s*,\\s*([0-9]{1,3})\\s*,\\s*([0-9]{1,3})\\s*\\)/.exec(c))return[parseInt(a[1],10),parseInt(a[2],10),parseInt(a[3],10)];if(a=/rgb\\(\\s*([0-9]+(?:\\.[0-9]+)?)\\%\\s*,\\s*([0-9]+(?:\\.[0-9]+)?)\\%\\s*,\\s*([0-9]+(?:\\.[0-9]+)?)\\%\\s*\\)/.exec(c))return[parseFloat(a[1])*2.55,parseFloat(a[2])*2.55,parseFloat(a[3])*2.55];if(a=/#([a-fA-F0-9]{2})([a-fA-F0-9]{2})([a-fA-F0-9]{2})/.exec(c))return[parseInt(a[1],
16),parseInt(a[2],16),parseInt(a[3],16)];if(a=/#([a-fA-F0-9])([a-fA-F0-9])([a-fA-F0-9])/.exec(c))return[parseInt(a[1]+a[1],16),parseInt(a[2]+a[2],16),parseInt(a[3]+a[3],16)];if(/rgba\\(0, 0, 0, 0\\)/.exec(c))return m.transparent;return m[f.trim(c).toLowerCase()]}function r(c,a){var b;do{b=f.curCSS(c,a);if(b!=\"\"&&b!=\"transparent\"||f.nodeName(c,\"body\"))break;a=\"backgroundColor\"}while(c=c.parentNode);return l(b)}function n(){var c=document.defaultView?document.defaultView.getComputedStyle(this,null):this.currentStyle,
a={},b,d;if(c&&c.length&&c[0]&&c[c[0]])for(var e=c.length;e--;){b=c[e];if(typeof c[b]==\"string\"){d=b.replace(/\\-(\\w)/g,function(g,h){return h.toUpperCase()});a[d]=c[b]}}else for(b in c)if(typeof c[b]===\"string\")a[b]=c[b];return a}function o(c){var a,b;for(a in c){b=c[a];if(b==null||f.isFunction(b)||a in s||/scrollbar/.test(a)||!/color/i.test(a)&&isNaN(parseFloat(b)))delete c[a]}return c}function t(c,a){var b={_:0},d;for(d in a)if(c[d]!=a[d])b[d]=a[d];return b}function k(c,a,b,d){if(typeof c==\"object\"){d=
a;b=null;a=c;c=a.effect}if(f.isFunction(a)){d=a;b=null;a={}}if(typeof a==\"number\"||f.fx.speeds[a]){d=b;b=a;a={}}if(f.isFunction(b)){d=b;b=null}a=a||{};b=b||a.duration;b=f.fx.off?0:typeof b==\"number\"?b:f.fx.speeds[b]||f.fx.speeds._default;d=d||a.complete;return[c,a,b,d]}f.effects={};f.each([\"backgroundColor\",\"borderBottomColor\",\"borderLeftColor\",\"borderRightColor\",\"borderTopColor\",\"color\",\"outlineColor\"],function(c,a){f.fx.step[a]=function(b){if(!b.colorInit){b.start=r(b.elem,a);b.end=l(b.end);b.colorInit=
true}b.elem.style[a]=\"rgb(\"+Math.max(Math.min(parseInt(b.pos*(b.end[0]-b.start[0])+b.start[0],10),255),0)+\",\"+Math.max(Math.min(parseInt(b.pos*(b.end[1]-b.start[1])+b.start[1],10),255),0)+\",\"+Math.max(Math.min(parseInt(b.pos*(b.end[2]-b.start[2])+b.start[2],10),255),0)+\")\"}});var m={aqua:[0,255,255],azure:[240,255,255],beige:[245,245,220],black:[0,0,0],blue:[0,0,255],brown:[165,42,42],cyan:[0,255,255],darkblue:[0,0,139],darkcyan:[0,139,139],darkgrey:[169,169,169],darkgreen:[0,100,0],darkkhaki:[189,
183,107],darkmagenta:[139,0,139],darkolivegreen:[85,107,47],darkorange:[255,140,0],darkorchid:[153,50,204],darkred:[139,0,0],darksalmon:[233,150,122],darkviolet:[148,0,211],fuchsia:[255,0,255],gold:[255,215,0],green:[0,128,0],indigo:[75,0,130],khaki:[240,230,140],lightblue:[173,216,230],lightcyan:[224,255,255],lightgreen:[144,238,144],lightgrey:[211,211,211],lightpink:[255,182,193],lightyellow:[255,255,224],lime:[0,255,0],magenta:[255,0,255],maroon:[128,0,0],navy:[0,0,128],olive:[128,128,0],orange:[255,
165,0],pink:[255,192,203],purple:[128,0,128],violet:[128,0,128],red:[255,0,0],silver:[192,192,192],white:[255,255,255],yellow:[255,255,0],transparent:[255,255,255]},p=[\"add\",\"remove\",\"toggle\"],s={border:1,borderBottom:1,borderColor:1,borderLeft:1,borderRight:1,borderTop:1,borderWidth:1,margin:1,padding:1};f.effects.animateClass=function(c,a,b,d){if(f.isFunction(b)){d=b;b=null}return this.each(function(){var e=f(this),g=e.attr(\"style\")||\" \",h=o(n.call(this)),q,u=e.attr(\"className\");f.each(p,function(v,
i){c[i]&&e[i+\"Class\"](c[i])});q=o(n.call(this));e.attr(\"className\",u);e.animate(t(h,q),a,b,function(){f.each(p,function(v,i){c[i]&&e[i+\"Class\"](c[i])});if(typeof e.attr(\"style\")==\"object\"){e.attr(\"style\").cssText=\"\";e.attr(\"style\").cssText=g}else e.attr(\"style\",g);d&&d.apply(this,arguments)})})};f.fn.extend({_addClass:f.fn.addClass,addClass:function(c,a,b,d){return a?f.effects.animateClass.apply(this,[{add:c},a,b,d]):this._addClass(c)},_removeClass:f.fn.removeClass,removeClass:function(c,a,b,d){return a?
f.effects.animateClass.apply(this,[{remove:c},a,b,d]):this._removeClass(c)},_toggleClass:f.fn.toggleClass,toggleClass:function(c,a,b,d,e){return typeof a==\"boolean\"||a===j?b?f.effects.animateClass.apply(this,[a?{add:c}:{remove:c},b,d,e]):this._toggleClass(c,a):f.effects.animateClass.apply(this,[{toggle:c},a,b,d])},switchClass:function(c,a,b,d,e){return f.effects.animateClass.apply(this,[{add:a,remove:c},b,d,e])}});f.extend(f.effects,{version:\"1.8.5\",save:function(c,a){for(var b=0;b<a.length;b++)a[b]!==
null&&c.data(\"ec.storage.\"+a[b],c[0].style[a[b]])},restore:function(c,a){for(var b=0;b<a.length;b++)a[b]!==null&&c.css(a[b],c.data(\"ec.storage.\"+a[b]))},setMode:function(c,a){if(a==\"toggle\")a=c.is(\":hidden\")?\"show\":\"hide\";return a},getBaseline:function(c,a){var b;switch(c[0]){case \"top\":b=0;break;case \"middle\":b=0.5;break;case \"bottom\":b=1;break;default:b=c[0]/a.height}switch(c[1]){case \"left\":c=0;break;case \"center\":c=0.5;break;case \"right\":c=1;break;default:c=c[1]/a.width}return{x:c,y:b}},createWrapper:function(c){if(c.parent().is(\".ui-effects-wrapper\"))return c.parent();
var a={width:c.outerWidth(true),height:c.outerHeight(true),\"float\":c.css(\"float\")},b=f(\"<div></div>\").addClass(\"ui-effects-wrapper\").css({fontSize:\"100%\",background:\"transparent\",border:\"none\",margin:0,padding:0});c.wrap(b);b=c.parent();if(c.css(\"position\")==\"static\"){b.css({position:\"relative\"});c.css({position:\"relative\"})}else{f.extend(a,{position:c.css(\"position\"),zIndex:c.css(\"z-index\")});f.each([\"top\",\"left\",\"bottom\",\"right\"],function(d,e){a[e]=c.css(e);if(isNaN(parseInt(a[e],10)))a[e]=\"auto\"});
c.css({position:\"relative\",top:0,left:0})}return b.css(a).show()},removeWrapper:function(c){if(c.parent().is(\".ui-effects-wrapper\"))return c.parent().replaceWith(c);return c},setTransition:function(c,a,b,d){d=d||{};f.each(a,function(e,g){unit=c.cssUnit(g);if(unit[0]>0)d[g]=unit[0]*b+unit[1]});return d}});f.fn.extend({effect:function(c){var a=k.apply(this,arguments);a={options:a[1],duration:a[2],callback:a[3]};var b=f.effects[c];return b&&!f.fx.off?b.call(this,a):this},_show:f.fn.show,show:function(c){if(!c||
typeof c==\"number\"||f.fx.speeds[c]||!f.effects[c])return this._show.apply(this,arguments);else{var a=k.apply(this,arguments);a[1].mode=\"show\";return this.effect.apply(this,a)}},_hide:f.fn.hide,hide:function(c){if(!c||typeof c==\"number\"||f.fx.speeds[c]||!f.effects[c])return this._hide.apply(this,arguments);else{var a=k.apply(this,arguments);a[1].mode=\"hide\";return this.effect.apply(this,a)}},__toggle:f.fn.toggle,toggle:function(c){if(!c||typeof c==\"number\"||f.fx.speeds[c]||!f.effects[c]||typeof c==
\"boolean\"||f.isFunction(c))return this.__toggle.apply(this,arguments);else{var a=k.apply(this,arguments);a[1].mode=\"toggle\";return this.effect.apply(this,a)}},cssUnit:function(c){var a=this.css(c),b=[];f.each([\"em\",\"px\",\"%\",\"pt\"],function(d,e){if(a.indexOf(e)>0)b=[parseFloat(a),e]});return b}});f.easing.jswing=f.easing.swing;f.extend(f.easing,{def:\"easeOutQuad\",swing:function(c,a,b,d,e){return f.easing[f.easing.def](c,a,b,d,e)},easeInQuad:function(c,a,b,d,e){return d*(a/=e)*a+b},easeOutQuad:function(c,
a,b,d,e){return-d*(a/=e)*(a-2)+b},easeInOutQuad:function(c,a,b,d,e){if((a/=e/2)<1)return d/2*a*a+b;return-d/2*(--a*(a-2)-1)+b},easeInCubic:function(c,a,b,d,e){return d*(a/=e)*a*a+b},easeOutCubic:function(c,a,b,d,e){return d*((a=a/e-1)*a*a+1)+b},easeInOutCubic:function(c,a,b,d,e){if((a/=e/2)<1)return d/2*a*a*a+b;return d/2*((a-=2)*a*a+2)+b},easeInQuart:function(c,a,b,d,e){return d*(a/=e)*a*a*a+b},easeOutQuart:function(c,a,b,d,e){return-d*((a=a/e-1)*a*a*a-1)+b},easeInOutQuart:function(c,a,b,d,e){if((a/=
e/2)<1)return d/2*a*a*a*a+b;return-d/2*((a-=2)*a*a*a-2)+b},easeInQuint:function(c,a,b,d,e){return d*(a/=e)*a*a*a*a+b},easeOutQuint:function(c,a,b,d,e){return d*((a=a/e-1)*a*a*a*a+1)+b},easeInOutQuint:function(c,a,b,d,e){if((a/=e/2)<1)return d/2*a*a*a*a*a+b;return d/2*((a-=2)*a*a*a*a+2)+b},easeInSine:function(c,a,b,d,e){return-d*Math.cos(a/e*(Math.PI/2))+d+b},easeOutSine:function(c,a,b,d,e){return d*Math.sin(a/e*(Math.PI/2))+b},easeInOutSine:function(c,a,b,d,e){return-d/2*(Math.cos(Math.PI*a/e)-1)+
b},easeInExpo:function(c,a,b,d,e){return a==0?b:d*Math.pow(2,10*(a/e-1))+b},easeOutExpo:function(c,a,b,d,e){return a==e?b+d:d*(-Math.pow(2,-10*a/e)+1)+b},easeInOutExpo:function(c,a,b,d,e){if(a==0)return b;if(a==e)return b+d;if((a/=e/2)<1)return d/2*Math.pow(2,10*(a-1))+b;return d/2*(-Math.pow(2,-10*--a)+2)+b},easeInCirc:function(c,a,b,d,e){return-d*(Math.sqrt(1-(a/=e)*a)-1)+b},easeOutCirc:function(c,a,b,d,e){return d*Math.sqrt(1-(a=a/e-1)*a)+b},easeInOutCirc:function(c,a,b,d,e){if((a/=e/2)<1)return-d/
2*(Math.sqrt(1-a*a)-1)+b;return d/2*(Math.sqrt(1-(a-=2)*a)+1)+b},easeInElastic:function(c,a,b,d,e){c=1.70158;var g=0,h=d;if(a==0)return b;if((a/=e)==1)return b+d;g||(g=e*0.3);if(h<Math.abs(d)){h=d;c=g/4}else c=g/(2*Math.PI)*Math.asin(d/h);return-(h*Math.pow(2,10*(a-=1))*Math.sin((a*e-c)*2*Math.PI/g))+b},easeOutElastic:function(c,a,b,d,e){c=1.70158;var g=0,h=d;if(a==0)return b;if((a/=e)==1)return b+d;g||(g=e*0.3);if(h<Math.abs(d)){h=d;c=g/4}else c=g/(2*Math.PI)*Math.asin(d/h);return h*Math.pow(2,-10*
a)*Math.sin((a*e-c)*2*Math.PI/g)+d+b},easeInOutElastic:function(c,a,b,d,e){c=1.70158;var g=0,h=d;if(a==0)return b;if((a/=e/2)==2)return b+d;g||(g=e*0.3*1.5);if(h<Math.abs(d)){h=d;c=g/4}else c=g/(2*Math.PI)*Math.asin(d/h);if(a<1)return-0.5*h*Math.pow(2,10*(a-=1))*Math.sin((a*e-c)*2*Math.PI/g)+b;return h*Math.pow(2,-10*(a-=1))*Math.sin((a*e-c)*2*Math.PI/g)*0.5+d+b},easeInBack:function(c,a,b,d,e,g){if(g==j)g=1.70158;return d*(a/=e)*a*((g+1)*a-g)+b},easeOutBack:function(c,a,b,d,e,g){if(g==j)g=1.70158;
return d*((a=a/e-1)*a*((g+1)*a+g)+1)+b},easeInOutBack:function(c,a,b,d,e,g){if(g==j)g=1.70158;if((a/=e/2)<1)return d/2*a*a*(((g*=1.525)+1)*a-g)+b;return d/2*((a-=2)*a*(((g*=1.525)+1)*a+g)+2)+b},easeInBounce:function(c,a,b,d,e){return d-f.easing.easeOutBounce(c,e-a,0,d,e)+b},easeOutBounce:function(c,a,b,d,e){return(a/=e)<1/2.75?d*7.5625*a*a+b:a<2/2.75?d*(7.5625*(a-=1.5/2.75)*a+0.75)+b:a<2.5/2.75?d*(7.5625*(a-=2.25/2.75)*a+0.9375)+b:d*(7.5625*(a-=2.625/2.75)*a+0.984375)+b},easeInOutBounce:function(c,
a,b,d,e){if(a<e/2)return f.easing.easeInBounce(c,a*2,0,d,e)*0.5+b;return f.easing.easeOutBounce(c,a*2-e,0,d,e)*0.5+d*0.5+b}})}(jQuery);
;/*
 * jQuery UI Effects Blind 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Blind
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(b){b.effects.blind=function(c){return this.queue(function(){var a=b(this),g=[\"position\",\"top\",\"left\"],f=b.effects.setMode(a,c.options.mode||\"hide\"),d=c.options.direction||\"vertical\";b.effects.save(a,g);a.show();var e=b.effects.createWrapper(a).css({overflow:\"hidden\"}),h=d==\"vertical\"?\"height\":\"width\";d=d==\"vertical\"?e.height():e.width();f==\"show\"&&e.css(h,0);var i={};i[h]=f==\"show\"?d:0;e.animate(i,c.duration,c.options.easing,function(){f==\"hide\"&&a.hide();b.effects.restore(a,g);b.effects.removeWrapper(a);
c.callback&&c.callback.apply(a[0],arguments);a.dequeue()})})}})(jQuery);
;/*
 * jQuery UI Effects Bounce 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Bounce
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(e){e.effects.bounce=function(b){return this.queue(function(){var a=e(this),l=[\"position\",\"top\",\"left\"],h=e.effects.setMode(a,b.options.mode||\"effect\"),d=b.options.direction||\"up\",c=b.options.distance||20,m=b.options.times||5,i=b.duration||250;/show|hide/.test(h)&&l.push(\"opacity\");e.effects.save(a,l);a.show();e.effects.createWrapper(a);var f=d==\"up\"||d==\"down\"?\"top\":\"left\";d=d==\"up\"||d==\"left\"?\"pos\":\"neg\";c=b.options.distance||(f==\"top\"?a.outerHeight({margin:true})/3:a.outerWidth({margin:true})/
3);if(h==\"show\")a.css(\"opacity\",0).css(f,d==\"pos\"?-c:c);if(h==\"hide\")c/=m*2;h!=\"hide\"&&m--;if(h==\"show\"){var g={opacity:1};g[f]=(d==\"pos\"?\"+=\":\"-=\")+c;a.animate(g,i/2,b.options.easing);c/=2;m--}for(g=0;g<m;g++){var j={},k={};j[f]=(d==\"pos\"?\"-=\":\"+=\")+c;k[f]=(d==\"pos\"?\"+=\":\"-=\")+c;a.animate(j,i/2,b.options.easing).animate(k,i/2,b.options.easing);c=h==\"hide\"?c*2:c/2}if(h==\"hide\"){g={opacity:0};g[f]=(d==\"pos\"?\"-=\":\"+=\")+c;a.animate(g,i/2,b.options.easing,function(){a.hide();e.effects.restore(a,l);e.effects.removeWrapper(a);
b.callback&&b.callback.apply(this,arguments)})}else{j={};k={};j[f]=(d==\"pos\"?\"-=\":\"+=\")+c;k[f]=(d==\"pos\"?\"+=\":\"-=\")+c;a.animate(j,i/2,b.options.easing).animate(k,i/2,b.options.easing,function(){e.effects.restore(a,l);e.effects.removeWrapper(a);b.callback&&b.callback.apply(this,arguments)})}a.queue(\"fx\",function(){a.dequeue()});a.dequeue()})}})(jQuery);
;/*
 * jQuery UI Effects Clip 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Clip
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(b){b.effects.clip=function(e){return this.queue(function(){var a=b(this),i=[\"position\",\"top\",\"left\",\"height\",\"width\"],f=b.effects.setMode(a,e.options.mode||\"hide\"),c=e.options.direction||\"vertical\";b.effects.save(a,i);a.show();var d=b.effects.createWrapper(a).css({overflow:\"hidden\"});d=a[0].tagName==\"IMG\"?d:a;var g={size:c==\"vertical\"?\"height\":\"width\",position:c==\"vertical\"?\"top\":\"left\"};c=c==\"vertical\"?d.height():d.width();if(f==\"show\"){d.css(g.size,0);d.css(g.position,c/2)}var h={};h[g.size]=
f==\"show\"?c:0;h[g.position]=f==\"show\"?0:c/2;d.animate(h,{queue:false,duration:e.duration,easing:e.options.easing,complete:function(){f==\"hide\"&&a.hide();b.effects.restore(a,i);b.effects.removeWrapper(a);e.callback&&e.callback.apply(a[0],arguments);a.dequeue()}})})}})(jQuery);
;/*
 * jQuery UI Effects Drop 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Drop
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(c){c.effects.drop=function(d){return this.queue(function(){var a=c(this),h=[\"position\",\"top\",\"left\",\"opacity\"],e=c.effects.setMode(a,d.options.mode||\"hide\"),b=d.options.direction||\"left\";c.effects.save(a,h);a.show();c.effects.createWrapper(a);var f=b==\"up\"||b==\"down\"?\"top\":\"left\";b=b==\"up\"||b==\"left\"?\"pos\":\"neg\";var g=d.options.distance||(f==\"top\"?a.outerHeight({margin:true})/2:a.outerWidth({margin:true})/2);if(e==\"show\")a.css(\"opacity\",0).css(f,b==\"pos\"?-g:g);var i={opacity:e==\"show\"?1:
0};i[f]=(e==\"show\"?b==\"pos\"?\"+=\":\"-=\":b==\"pos\"?\"-=\":\"+=\")+g;a.animate(i,{queue:false,duration:d.duration,easing:d.options.easing,complete:function(){e==\"hide\"&&a.hide();c.effects.restore(a,h);c.effects.removeWrapper(a);d.callback&&d.callback.apply(this,arguments);a.dequeue()}})})}})(jQuery);
;/*
 * jQuery UI Effects Explode 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Explode
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(j){j.effects.explode=function(a){return this.queue(function(){var c=a.options.pieces?Math.round(Math.sqrt(a.options.pieces)):3,d=a.options.pieces?Math.round(Math.sqrt(a.options.pieces)):3;a.options.mode=a.options.mode==\"toggle\"?j(this).is(\":visible\")?\"hide\":\"show\":a.options.mode;var b=j(this).show().css(\"visibility\",\"hidden\"),g=b.offset();g.top-=parseInt(b.css(\"marginTop\"),10)||0;g.left-=parseInt(b.css(\"marginLeft\"),10)||0;for(var h=b.outerWidth(true),i=b.outerHeight(true),e=0;e<c;e++)for(var f=
0;f<d;f++)b.clone().appendTo(\"body\").wrap(\"<div></div>\").css({position:\"absolute\",visibility:\"visible\",left:-f*(h/d),top:-e*(i/c)}).parent().addClass(\"ui-effects-explode\").css({position:\"absolute\",overflow:\"hidden\",width:h/d,height:i/c,left:g.left+f*(h/d)+(a.options.mode==\"show\"?(f-Math.floor(d/2))*(h/d):0),top:g.top+e*(i/c)+(a.options.mode==\"show\"?(e-Math.floor(c/2))*(i/c):0),opacity:a.options.mode==\"show\"?0:1}).animate({left:g.left+f*(h/d)+(a.options.mode==\"show\"?0:(f-Math.floor(d/2))*(h/d)),top:g.top+
e*(i/c)+(a.options.mode==\"show\"?0:(e-Math.floor(c/2))*(i/c)),opacity:a.options.mode==\"show\"?1:0},a.duration||500);setTimeout(function(){a.options.mode==\"show\"?b.css({visibility:\"visible\"}):b.css({visibility:\"visible\"}).hide();a.callback&&a.callback.apply(b[0]);b.dequeue();j(\"div.ui-effects-explode\").remove()},a.duration||500)})}})(jQuery);
;/*
 * jQuery UI Effects Fade 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Fade
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(b){b.effects.fade=function(a){return this.queue(function(){var c=b(this),d=b.effects.setMode(c,a.options.mode||\"hide\");c.animate({opacity:d},{queue:false,duration:a.duration,easing:a.options.easing,complete:function(){a.callback&&a.callback.apply(this,arguments);c.dequeue()}})})}})(jQuery);
;/*
 * jQuery UI Effects Fold 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Fold
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(c){c.effects.fold=function(a){return this.queue(function(){var b=c(this),j=[\"position\",\"top\",\"left\"],d=c.effects.setMode(b,a.options.mode||\"hide\"),g=a.options.size||15,h=!!a.options.horizFirst,k=a.duration?a.duration/2:c.fx.speeds._default/2;c.effects.save(b,j);b.show();var e=c.effects.createWrapper(b).css({overflow:\"hidden\"}),f=d==\"show\"!=h,l=f?[\"width\",\"height\"]:[\"height\",\"width\"];f=f?[e.width(),e.height()]:[e.height(),e.width()];var i=/([0-9]+)%/.exec(g);if(i)g=parseInt(i[1],10)/100*
f[d==\"hide\"?0:1];if(d==\"show\")e.css(h?{height:0,width:g}:{height:g,width:0});h={};i={};h[l[0]]=d==\"show\"?f[0]:g;i[l[1]]=d==\"show\"?f[1]:0;e.animate(h,k,a.options.easing).animate(i,k,a.options.easing,function(){d==\"hide\"&&b.hide();c.effects.restore(b,j);c.effects.removeWrapper(b);a.callback&&a.callback.apply(b[0],arguments);b.dequeue()})})}})(jQuery);
;/*
 * jQuery UI Effects Highlight 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Highlight
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(b){b.effects.highlight=function(c){return this.queue(function(){var a=b(this),e=[\"backgroundImage\",\"backgroundColor\",\"opacity\"],d=b.effects.setMode(a,c.options.mode||\"show\"),f={backgroundColor:a.css(\"backgroundColor\")};if(d==\"hide\")f.opacity=0;b.effects.save(a,e);a.show().css({backgroundImage:\"none\",backgroundColor:c.options.color||\"#ffff99\"}).animate(f,{queue:false,duration:c.duration,easing:c.options.easing,complete:function(){d==\"hide\"&&a.hide();b.effects.restore(a,e);d==\"show\"&&!b.support.opacity&&
this.style.removeAttribute(\"filter\");c.callback&&c.callback.apply(this,arguments);a.dequeue()}})})}})(jQuery);
;/*
 * jQuery UI Effects Pulsate 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Pulsate
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(d){d.effects.pulsate=function(a){return this.queue(function(){var b=d(this),c=d.effects.setMode(b,a.options.mode||\"show\");times=(a.options.times||5)*2-1;duration=a.duration?a.duration/2:d.fx.speeds._default/2;isVisible=b.is(\":visible\");animateTo=0;if(!isVisible){b.css(\"opacity\",0).show();animateTo=1}if(c==\"hide\"&&isVisible||c==\"show\"&&!isVisible)times--;for(c=0;c<times;c++){b.animate({opacity:animateTo},duration,a.options.easing);animateTo=(animateTo+1)%2}b.animate({opacity:animateTo},duration,
a.options.easing,function(){animateTo==0&&b.hide();a.callback&&a.callback.apply(this,arguments)});b.queue(\"fx\",function(){b.dequeue()}).dequeue()})}})(jQuery);
;/*
 * jQuery UI Effects Scale 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Scale
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(c){c.effects.puff=function(b){return this.queue(function(){var a=c(this),e=c.effects.setMode(a,b.options.mode||\"hide\"),g=parseInt(b.options.percent,10)||150,h=g/100,i={height:a.height(),width:a.width()};c.extend(b.options,{fade:true,mode:e,percent:e==\"hide\"?g:100,from:e==\"hide\"?i:{height:i.height*h,width:i.width*h}});a.effect(\"scale\",b.options,b.duration,b.callback);a.dequeue()})};c.effects.scale=function(b){return this.queue(function(){var a=c(this),e=c.extend(true,{},b.options),g=c.effects.setMode(a,
b.options.mode||\"effect\"),h=parseInt(b.options.percent,10)||(parseInt(b.options.percent,10)==0?0:g==\"hide\"?0:100),i=b.options.direction||\"both\",f=b.options.origin;if(g!=\"effect\"){e.origin=f||[\"middle\",\"center\"];e.restore=true}f={height:a.height(),width:a.width()};a.from=b.options.from||(g==\"show\"?{height:0,width:0}:f);h={y:i!=\"horizontal\"?h/100:1,x:i!=\"vertical\"?h/100:1};a.to={height:f.height*h.y,width:f.width*h.x};if(b.options.fade){if(g==\"show\"){a.from.opacity=0;a.to.opacity=1}if(g==\"hide\"){a.from.opacity=
1;a.to.opacity=0}}e.from=a.from;e.to=a.to;e.mode=g;a.effect(\"size\",e,b.duration,b.callback);a.dequeue()})};c.effects.size=function(b){return this.queue(function(){var a=c(this),e=[\"position\",\"top\",\"left\",\"width\",\"height\",\"overflow\",\"opacity\"],g=[\"position\",\"top\",\"left\",\"overflow\",\"opacity\"],h=[\"width\",\"height\",\"overflow\"],i=[\"fontSize\"],f=[\"borderTopWidth\",\"borderBottomWidth\",\"paddingTop\",\"paddingBottom\"],k=[\"borderLeftWidth\",\"borderRightWidth\",\"paddingLeft\",\"paddingRight\"],p=c.effects.setMode(a,
b.options.mode||\"effect\"),n=b.options.restore||false,m=b.options.scale||\"both\",l=b.options.origin,j={height:a.height(),width:a.width()};a.from=b.options.from||j;a.to=b.options.to||j;if(l){l=c.effects.getBaseline(l,j);a.from.top=(j.height-a.from.height)*l.y;a.from.left=(j.width-a.from.width)*l.x;a.to.top=(j.height-a.to.height)*l.y;a.to.left=(j.width-a.to.width)*l.x}var d={from:{y:a.from.height/j.height,x:a.from.width/j.width},to:{y:a.to.height/j.height,x:a.to.width/j.width}};if(m==\"box\"||m==\"both\"){if(d.from.y!=
d.to.y){e=e.concat(f);a.from=c.effects.setTransition(a,f,d.from.y,a.from);a.to=c.effects.setTransition(a,f,d.to.y,a.to)}if(d.from.x!=d.to.x){e=e.concat(k);a.from=c.effects.setTransition(a,k,d.from.x,a.from);a.to=c.effects.setTransition(a,k,d.to.x,a.to)}}if(m==\"content\"||m==\"both\")if(d.from.y!=d.to.y){e=e.concat(i);a.from=c.effects.setTransition(a,i,d.from.y,a.from);a.to=c.effects.setTransition(a,i,d.to.y,a.to)}c.effects.save(a,n?e:g);a.show();c.effects.createWrapper(a);a.css(\"overflow\",\"hidden\").css(a.from);
if(m==\"content\"||m==\"both\"){f=f.concat([\"marginTop\",\"marginBottom\"]).concat(i);k=k.concat([\"marginLeft\",\"marginRight\"]);h=e.concat(f).concat(k);a.find(\"*[width]\").each(function(){child=c(this);n&&c.effects.save(child,h);var o={height:child.height(),width:child.width()};child.from={height:o.height*d.from.y,width:o.width*d.from.x};child.to={height:o.height*d.to.y,width:o.width*d.to.x};if(d.from.y!=d.to.y){child.from=c.effects.setTransition(child,f,d.from.y,child.from);child.to=c.effects.setTransition(child,
f,d.to.y,child.to)}if(d.from.x!=d.to.x){child.from=c.effects.setTransition(child,k,d.from.x,child.from);child.to=c.effects.setTransition(child,k,d.to.x,child.to)}child.css(child.from);child.animate(child.to,b.duration,b.options.easing,function(){n&&c.effects.restore(child,h)})})}a.animate(a.to,{queue:false,duration:b.duration,easing:b.options.easing,complete:function(){a.to.opacity===0&&a.css(\"opacity\",a.from.opacity);p==\"hide\"&&a.hide();c.effects.restore(a,n?e:g);c.effects.removeWrapper(a);b.callback&&
b.callback.apply(this,arguments);a.dequeue()}})})}})(jQuery);
;/*
 * jQuery UI Effects Shake 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Shake
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(d){d.effects.shake=function(a){return this.queue(function(){var b=d(this),j=[\"position\",\"top\",\"left\"];d.effects.setMode(b,a.options.mode||\"effect\");var c=a.options.direction||\"left\",e=a.options.distance||20,l=a.options.times||3,f=a.duration||a.options.duration||140;d.effects.save(b,j);b.show();d.effects.createWrapper(b);var g=c==\"up\"||c==\"down\"?\"top\":\"left\",h=c==\"up\"||c==\"left\"?\"pos\":\"neg\";c={};var i={},k={};c[g]=(h==\"pos\"?\"-=\":\"+=\")+e;i[g]=(h==\"pos\"?\"+=\":\"-=\")+e*2;k[g]=(h==\"pos\"?\"-=\":\"+=\")+
e*2;b.animate(c,f,a.options.easing);for(e=1;e<l;e++)b.animate(i,f,a.options.easing).animate(k,f,a.options.easing);b.animate(i,f,a.options.easing).animate(c,f/2,a.options.easing,function(){d.effects.restore(b,j);d.effects.removeWrapper(b);a.callback&&a.callback.apply(this,arguments)});b.queue(\"fx\",function(){b.dequeue()});b.dequeue()})}})(jQuery);
;/*
 * jQuery UI Effects Slide 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Slide
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(c){c.effects.slide=function(d){return this.queue(function(){var a=c(this),h=[\"position\",\"top\",\"left\"],e=c.effects.setMode(a,d.options.mode||\"show\"),b=d.options.direction||\"left\";c.effects.save(a,h);a.show();c.effects.createWrapper(a).css({overflow:\"hidden\"});var f=b==\"up\"||b==\"down\"?\"top\":\"left\";b=b==\"up\"||b==\"left\"?\"pos\":\"neg\";var g=d.options.distance||(f==\"top\"?a.outerHeight({margin:true}):a.outerWidth({margin:true}));if(e==\"show\")a.css(f,b==\"pos\"?-g:g);var i={};i[f]=(e==\"show\"?b==\"pos\"?
\"+=\":\"-=\":b==\"pos\"?\"-=\":\"+=\")+g;a.animate(i,{queue:false,duration:d.duration,easing:d.options.easing,complete:function(){e==\"hide\"&&a.hide();c.effects.restore(a,h);c.effects.removeWrapper(a);d.callback&&d.callback.apply(this,arguments);a.dequeue()}})})}})(jQuery);
;/*
 * jQuery UI Effects Transfer 1.8.5
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Transfer
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function(e){e.effects.transfer=function(a){return this.queue(function(){var b=e(this),c=e(a.options.to),d=c.offset();c={top:d.top,left:d.left,height:c.innerHeight(),width:c.innerWidth()};d=b.offset();var f=e('<div class=\"ui-effects-transfer\"></div>').appendTo(document.body).addClass(a.options.className).css({top:d.top,left:d.left,height:b.innerHeight(),width:b.innerWidth(),position:\"absolute\"}).animate(c,a.duration,a.options.easing,function(){f.remove();a.callback&&a.callback.apply(b[0],arguments);
b.dequeue()})})}})(jQuery);
;
</script>
<style type=\"text/css\">
    /*
 * jQuery UI CSS Framework @VERSION
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Theming/API
 */

/* Layout helpers
----------------------------------*/
.ui-helper-hidden { display: none; }
.ui-helper-hidden-accessible { position: absolute; left: -99999999px; }
.ui-helper-reset { margin: 0; padding: 0; border: 0; outline: 0; line-height: 1.3; text-decoration: none; font-size: 100%; list-style: none; }
.ui-helper-clearfix:after { content: \".\"; display: block; height: 0; clear: both; visibility: hidden; }
.ui-helper-clearfix { display: inline-block; }
/* required comment for clearfix to work in Opera \\*/
* html .ui-helper-clearfix { height:1%; }
.ui-helper-clearfix { display:block; }
/* end clearfix */
.ui-helper-zfix { width: 100%; height: 100%; top: 0; left: 0; position: absolute; opacity: 0; filter:Alpha(Opacity=0); }


/* Interaction Cues
----------------------------------*/
.ui-state-disabled { cursor: default !important; }


/* Icons
----------------------------------*/

/* states and images */
.ui-icon { display: block; text-indent: -99999px; overflow: hidden; background-repeat: no-repeat; }


/* Misc visuals
----------------------------------*/

/* Overlays */
.ui-widget-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }


/*
 * jQuery UI CSS Framework @VERSION
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Theming/API
 *
 * To view and modify this theme, visit http://jqueryui.com/themeroller/?ffDefault=Trebuchet%20MS,%20Tahoma,%20Verdana,%20Arial,%20sans-serif&fwDefault=bold&fsDefault=1.1em&cornerRadius=4px&bgColorHeader=f6a828&bgTextureHeader=12_gloss_wave.png&bgImgOpacityHeader=35&borderColorHeader=e78f08&fcHeader=ffffff&iconColorHeader=ffffff&bgColorContent=eeeeee&bgTextureContent=03_highlight_soft.png&bgImgOpacityContent=100&borderColorContent=dddddd&fcContent=333333&iconColorContent=222222&bgColorDefault=f6f6f6&bgTextureDefault=02_glass.png&bgImgOpacityDefault=100&borderColorDefault=cccccc&fcDefault=1c94c4&iconColorDefault=ef8c08&bgColorHover=fdf5ce&bgTextureHover=02_glass.png&bgImgOpacityHover=100&borderColorHover=fbcb09&fcHover=c77405&iconColorHover=ef8c08&bgColorActive=ffffff&bgTextureActive=02_glass.png&bgImgOpacityActive=65&borderColorActive=fbd850&fcActive=eb8f00&iconColorActive=ef8c08&bgColorHighlight=ffe45c&bgTextureHighlight=03_highlight_soft.png&bgImgOpacityHighlight=75&borderColorHighlight=fed22f&fcHighlight=363636&iconColorHighlight=228ef1&bgColorError=b81900&bgTextureError=08_diagonals_thick.png&bgImgOpacityError=18&borderColorError=cd0a0a&fcError=ffffff&iconColorError=ffd27a&bgColorOverlay=666666&bgTextureOverlay=08_diagonals_thick.png&bgImgOpacityOverlay=20&opacityOverlay=50&bgColorShadow=000000&bgTextureShadow=01_flat.png&bgImgOpacityShadow=10&opacityShadow=20&thicknessShadow=5px&offsetTopShadow=-5px&offsetLeftShadow=-5px&cornerRadiusShadow=5px
 */


/* Component containers
----------------------------------*/
.ui-widget { font-family: Trebuchet MS, Tahoma, Verdana, Arial, sans-serif; font-size: 1.1em; }
.ui-widget .ui-widget { font-size: 1em; }
.ui-widget input, .ui-widget select, .ui-widget textarea, .ui-widget button { font-family: Trebuchet MS, Tahoma, Verdana, Arial, sans-serif; font-size: 1em; }
.ui-widget-content { border: 1px solid #dddddd; background: #eeeeee url(images/ui-bg_highlight-soft_100_eeeeee_1x100.png) 50% top repeat-x; color: #333333; }
.ui-widget-content a { color: #333333; }
.ui-widget-header { border: 1px solid #e78f08; background: #f6a828 url(images/ui-bg_gloss-wave_35_f6a828_500x100.png) 50% 50% repeat-x; color: #ffffff; font-weight: bold; }
.ui-widget-header a { color: #ffffff; }

/* Interaction states
----------------------------------*/
.ui-state-default, .ui-widget-content .ui-state-default, .ui-widget-header .ui-state-default { border: 1px solid #cccccc; background: #f6f6f6 url(images/ui-bg_glass_100_f6f6f6_1x400.png) 50% 50% repeat-x; font-weight: bold; color: #1c94c4; }
.ui-state-default a, .ui-state-default a:link, .ui-state-default a:visited { color: #1c94c4; text-decoration: none; }
.ui-state-hover, .ui-widget-content .ui-state-hover, .ui-widget-header .ui-state-hover, .ui-state-focus, .ui-widget-content .ui-state-focus, .ui-widget-header .ui-state-focus { border: 1px solid #fbcb09; background: #fdf5ce url(images/ui-bg_glass_100_fdf5ce_1x400.png) 50% 50% repeat-x; font-weight: bold; color: #c77405; }
.ui-state-hover a, .ui-state-hover a:hover { color: #c77405; text-decoration: none; }
.ui-state-active, .ui-widget-content .ui-state-active, .ui-widget-header .ui-state-active { border: 1px solid #fbd850; background: #ffffff url(images/ui-bg_glass_65_ffffff_1x400.png) 50% 50% repeat-x; font-weight: bold; color: #eb8f00; }
.ui-state-active a, .ui-state-active a:link, .ui-state-active a:visited { color: #eb8f00; text-decoration: none; }
.ui-widget :active { outline: none; }

/* Interaction Cues
----------------------------------*/
.ui-state-highlight, .ui-widget-content .ui-state-highlight, .ui-widget-header .ui-state-highlight  {border: 1px solid #fed22f; background: #ffe45c url(images/ui-bg_highlight-soft_75_ffe45c_1x100.png) 50% top repeat-x; color: #363636; }
.ui-state-highlight a, .ui-widget-content .ui-state-highlight a,.ui-widget-header .ui-state-highlight a { color: #363636; }
.ui-state-error, .ui-widget-content .ui-state-error, .ui-widget-header .ui-state-error {border: 1px solid #cd0a0a; background: #b81900 url(images/ui-bg_diagonals-thick_18_b81900_40x40.png) 50% 50% repeat; color: #ffffff; }
.ui-state-error a, .ui-widget-content .ui-state-error a, .ui-widget-header .ui-state-error a { color: #ffffff; }
.ui-state-error-text, .ui-widget-content .ui-state-error-text, .ui-widget-header .ui-state-error-text { color: #ffffff; }
.ui-priority-primary, .ui-widget-content .ui-priority-primary, .ui-widget-header .ui-priority-primary { font-weight: bold; }
.ui-priority-secondary, .ui-widget-content .ui-priority-secondary,  .ui-widget-header .ui-priority-secondary { opacity: .7; filter:Alpha(Opacity=70); font-weight: normal; }
.ui-state-disabled, .ui-widget-content .ui-state-disabled, .ui-widget-header .ui-state-disabled { opacity: .35; filter:Alpha(Opacity=35); background-image: none; }

/* Icons
----------------------------------*/

/* states and images */
.ui-icon { width: 16px; height: 16px; background-image: url(images/ui-icons_222222_256x240.png); }
.ui-widget-content .ui-icon {background-image: url(images/ui-icons_222222_256x240.png); }
.ui-widget-header .ui-icon {background-image: url(images/ui-icons_ffffff_256x240.png); }
.ui-state-default .ui-icon { background-image: url(images/ui-icons_ef8c08_256x240.png); }
.ui-state-hover .ui-icon, .ui-state-focus .ui-icon {background-image: url(images/ui-icons_ef8c08_256x240.png); }
.ui-state-active .ui-icon {background-image: url(images/ui-icons_ef8c08_256x240.png); }
.ui-state-highlight .ui-icon {background-image: url(images/ui-icons_228ef1_256x240.png); }
.ui-state-error .ui-icon, .ui-state-error-text .ui-icon {background-image: url(images/ui-icons_ffd27a_256x240.png); }

/* positioning */
.ui-icon-carat-1-n { background-position: 0 0; }
.ui-icon-carat-1-ne { background-position: -16px 0; }
.ui-icon-carat-1-e { background-position: -32px 0; }
.ui-icon-carat-1-se { background-position: -48px 0; }
.ui-icon-carat-1-s { background-position: -64px 0; }
.ui-icon-carat-1-sw { background-position: -80px 0; }
.ui-icon-carat-1-w { background-position: -96px 0; }
.ui-icon-carat-1-nw { background-position: -112px 0; }
.ui-icon-carat-2-n-s { background-position: -128px 0; }
.ui-icon-carat-2-e-w { background-position: -144px 0; }
.ui-icon-triangle-1-n { background-position: 0 -16px; }
.ui-icon-triangle-1-ne { background-position: -16px -16px; }
.ui-icon-triangle-1-e { background-position: -32px -16px; }
.ui-icon-triangle-1-se { background-position: -48px -16px; }
.ui-icon-triangle-1-s { background-position: -64px -16px; }
.ui-icon-triangle-1-sw { background-position: -80px -16px; }
.ui-icon-triangle-1-w { background-position: -96px -16px; }
.ui-icon-triangle-1-nw { background-position: -112px -16px; }
.ui-icon-triangle-2-n-s { background-position: -128px -16px; }
.ui-icon-triangle-2-e-w { background-position: -144px -16px; }
.ui-icon-arrow-1-n { background-position: 0 -32px; }
.ui-icon-arrow-1-ne { background-position: -16px -32px; }
.ui-icon-arrow-1-e { background-position: -32px -32px; }
.ui-icon-arrow-1-se { background-position: -48px -32px; }
.ui-icon-arrow-1-s { background-position: -64px -32px; }
.ui-icon-arrow-1-sw { background-position: -80px -32px; }
.ui-icon-arrow-1-w { background-position: -96px -32px; }
.ui-icon-arrow-1-nw { background-position: -112px -32px; }
.ui-icon-arrow-2-n-s { background-position: -128px -32px; }
.ui-icon-arrow-2-ne-sw { background-position: -144px -32px; }
.ui-icon-arrow-2-e-w { background-position: -160px -32px; }
.ui-icon-arrow-2-se-nw { background-position: -176px -32px; }
.ui-icon-arrowstop-1-n { background-position: -192px -32px; }
.ui-icon-arrowstop-1-e { background-position: -208px -32px; }
.ui-icon-arrowstop-1-s { background-position: -224px -32px; }
.ui-icon-arrowstop-1-w { background-position: -240px -32px; }
.ui-icon-arrowthick-1-n { background-position: 0 -48px; }
.ui-icon-arrowthick-1-ne { background-position: -16px -48px; }
.ui-icon-arrowthick-1-e { background-position: -32px -48px; }
.ui-icon-arrowthick-1-se { background-position: -48px -48px; }
.ui-icon-arrowthick-1-s { background-position: -64px -48px; }
.ui-icon-arrowthick-1-sw { background-position: -80px -48px; }
.ui-icon-arrowthick-1-w { background-position: -96px -48px; }
.ui-icon-arrowthick-1-nw { background-position: -112px -48px; }
.ui-icon-arrowthick-2-n-s { background-position: -128px -48px; }
.ui-icon-arrowthick-2-ne-sw { background-position: -144px -48px; }
.ui-icon-arrowthick-2-e-w { background-position: -160px -48px; }
.ui-icon-arrowthick-2-se-nw { background-position: -176px -48px; }
.ui-icon-arrowthickstop-1-n { background-position: -192px -48px; }
.ui-icon-arrowthickstop-1-e { background-position: -208px -48px; }
.ui-icon-arrowthickstop-1-s { background-position: -224px -48px; }
.ui-icon-arrowthickstop-1-w { background-position: -240px -48px; }
.ui-icon-arrowreturnthick-1-w { background-position: 0 -64px; }
.ui-icon-arrowreturnthick-1-n { background-position: -16px -64px; }
.ui-icon-arrowreturnthick-1-e { background-position: -32px -64px; }
.ui-icon-arrowreturnthick-1-s { background-position: -48px -64px; }
.ui-icon-arrowreturn-1-w { background-position: -64px -64px; }
.ui-icon-arrowreturn-1-n { background-position: -80px -64px; }
.ui-icon-arrowreturn-1-e { background-position: -96px -64px; }
.ui-icon-arrowreturn-1-s { background-position: -112px -64px; }
.ui-icon-arrowrefresh-1-w { background-position: -128px -64px; }
.ui-icon-arrowrefresh-1-n { background-position: -144px -64px; }
.ui-icon-arrowrefresh-1-e { background-position: -160px -64px; }
.ui-icon-arrowrefresh-1-s { background-position: -176px -64px; }
.ui-icon-arrow-4 { background-position: 0 -80px; }
.ui-icon-arrow-4-diag { background-position: -16px -80px; }
.ui-icon-extlink { background-position: -32px -80px; }
.ui-icon-newwin { background-position: -48px -80px; }
.ui-icon-refresh { background-position: -64px -80px; }
.ui-icon-shuffle { background-position: -80px -80px; }
.ui-icon-transfer-e-w { background-position: -96px -80px; }
.ui-icon-transferthick-e-w { background-position: -112px -80px; }
.ui-icon-folder-collapsed { background-position: 0 -96px; }
.ui-icon-folder-open { background-position: -16px -96px; }
.ui-icon-document { background-position: -32px -96px; }
.ui-icon-document-b { background-position: -48px -96px; }
.ui-icon-note { background-position: -64px -96px; }
.ui-icon-mail-closed { background-position: -80px -96px; }
.ui-icon-mail-open { background-position: -96px -96px; }
.ui-icon-suitcase { background-position: -112px -96px; }
.ui-icon-comment { background-position: -128px -96px; }
.ui-icon-person { background-position: -144px -96px; }
.ui-icon-print { background-position: -160px -96px; }
.ui-icon-trash { background-position: -176px -96px; }
.ui-icon-locked { background-position: -192px -96px; }
.ui-icon-unlocked { background-position: -208px -96px; }
.ui-icon-bookmark { background-position: -224px -96px; }
.ui-icon-tag { background-position: -240px -96px; }
.ui-icon-home { background-position: 0 -112px; }
.ui-icon-flag { background-position: -16px -112px; }
.ui-icon-calendar { background-position: -32px -112px; }
.ui-icon-cart { background-position: -48px -112px; }
.ui-icon-pencil { background-position: -64px -112px; }
.ui-icon-clock { background-position: -80px -112px; }
.ui-icon-disk { background-position: -96px -112px; }
.ui-icon-calculator { background-position: -112px -112px; }
.ui-icon-zoomin { background-position: -128px -112px; }
.ui-icon-zoomout { background-position: -144px -112px; }
.ui-icon-search { background-position: -160px -112px; }
.ui-icon-wrench { background-position: -176px -112px; }
.ui-icon-gear { background-position: -192px -112px; }
.ui-icon-heart { background-position: -208px -112px; }
.ui-icon-star { background-position: -224px -112px; }
.ui-icon-link { background-position: -240px -112px; }
.ui-icon-cancel { background-position: 0 -128px; }
.ui-icon-plus { background-position: -16px -128px; }
.ui-icon-plusthick { background-position: -32px -128px; }
.ui-icon-minus { background-position: -48px -128px; }
.ui-icon-minusthick { background-position: -64px -128px; }
.ui-icon-close { background-position: -80px -128px; }
.ui-icon-closethick { background-position: -96px -128px; }
.ui-icon-key { background-position: -112px -128px; }
.ui-icon-lightbulb { background-position: -128px -128px; }
.ui-icon-scissors { background-position: -144px -128px; }
.ui-icon-clipboard { background-position: -160px -128px; }
.ui-icon-copy { background-position: -176px -128px; }
.ui-icon-contact { background-position: -192px -128px; }
.ui-icon-image { background-position: -208px -128px; }
.ui-icon-video { background-position: -224px -128px; }
.ui-icon-script { background-position: -240px -128px; }
.ui-icon-alert { background-position: 0 -144px; }
.ui-icon-info { background-position: -16px -144px; }
.ui-icon-notice { background-position: -32px -144px; }
.ui-icon-help { background-position: -48px -144px; }
.ui-icon-check { background-position: -64px -144px; }
.ui-icon-bullet { background-position: -80px -144px; }
.ui-icon-radio-off { background-position: -96px -144px; }
.ui-icon-radio-on { background-position: -112px -144px; }
.ui-icon-pin-w { background-position: -128px -144px; }
.ui-icon-pin-s { background-position: -144px -144px; }
.ui-icon-play { background-position: 0 -160px; }
.ui-icon-pause { background-position: -16px -160px; }
.ui-icon-seek-next { background-position: -32px -160px; }
.ui-icon-seek-prev { background-position: -48px -160px; }
.ui-icon-seek-end { background-position: -64px -160px; }
.ui-icon-seek-start { background-position: -80px -160px; }
/* ui-icon-seek-first is deprecated, use ui-icon-seek-start instead */
.ui-icon-seek-first { background-position: -80px -160px; }
.ui-icon-stop { background-position: -96px -160px; }
.ui-icon-eject { background-position: -112px -160px; }
.ui-icon-volume-off { background-position: -128px -160px; }
.ui-icon-volume-on { background-position: -144px -160px; }
.ui-icon-power { background-position: 0 -176px; }
.ui-icon-signal-diag { background-position: -16px -176px; }
.ui-icon-signal { background-position: -32px -176px; }
.ui-icon-battery-0 { background-position: -48px -176px; }
.ui-icon-battery-1 { background-position: -64px -176px; }
.ui-icon-battery-2 { background-position: -80px -176px; }
.ui-icon-battery-3 { background-position: -96px -176px; }
.ui-icon-circle-plus { background-position: 0 -192px; }
.ui-icon-circle-minus { background-position: -16px -192px; }
.ui-icon-circle-close { background-position: -32px -192px; }
.ui-icon-circle-triangle-e { background-position: -48px -192px; }
.ui-icon-circle-triangle-s { background-position: -64px -192px; }
.ui-icon-circle-triangle-w { background-position: -80px -192px; }
.ui-icon-circle-triangle-n { background-position: -96px -192px; }
.ui-icon-circle-arrow-e { background-position: -112px -192px; }
.ui-icon-circle-arrow-s { background-position: -128px -192px; }
.ui-icon-circle-arrow-w { background-position: -144px -192px; }
.ui-icon-circle-arrow-n { background-position: -160px -192px; }
.ui-icon-circle-zoomin { background-position: -176px -192px; }
.ui-icon-circle-zoomout { background-position: -192px -192px; }
.ui-icon-circle-check { background-position: -208px -192px; }
.ui-icon-circlesmall-plus { background-position: 0 -208px; }
.ui-icon-circlesmall-minus { background-position: -16px -208px; }
.ui-icon-circlesmall-close { background-position: -32px -208px; }
.ui-icon-squaresmall-plus { background-position: -48px -208px; }
.ui-icon-squaresmall-minus { background-position: -64px -208px; }
.ui-icon-squaresmall-close { background-position: -80px -208px; }
.ui-icon-grip-dotted-vertical { background-position: 0 -224px; }
.ui-icon-grip-dotted-horizontal { background-position: -16px -224px; }
.ui-icon-grip-solid-vertical { background-position: -32px -224px; }
.ui-icon-grip-solid-horizontal { background-position: -48px -224px; }
.ui-icon-gripsmall-diagonal-se { background-position: -64px -224px; }
.ui-icon-grip-diagonal-se { background-position: -80px -224px; }


/* Misc visuals
----------------------------------*/

/* Corner radius */
.ui-corner-tl { -moz-border-radius-topleft: 4px; -webkit-border-top-left-radius: 4px; border-top-left-radius: 4px; }
.ui-corner-tr { -moz-border-radius-topright: 4px; -webkit-border-top-right-radius: 4px; border-top-right-radius: 4px; }
.ui-corner-bl { -moz-border-radius-bottomleft: 4px; -webkit-border-bottom-left-radius: 4px; border-bottom-left-radius: 4px; }
.ui-corner-br { -moz-border-radius-bottomright: 4px; -webkit-border-bottom-right-radius: 4px; border-bottom-right-radius: 4px; }
.ui-corner-top { -moz-border-radius-topleft: 4px; -webkit-border-top-left-radius: 4px; border-top-left-radius: 4px; -moz-border-radius-topright: 4px; -webkit-border-top-right-radius: 4px; border-top-right-radius: 4px; }
.ui-corner-bottom { -moz-border-radius-bottomleft: 4px; -webkit-border-bottom-left-radius: 4px; border-bottom-left-radius: 4px; -moz-border-radius-bottomright: 4px; -webkit-border-bottom-right-radius: 4px; border-bottom-right-radius: 4px; }
.ui-corner-right {  -moz-border-radius-topright: 4px; -webkit-border-top-right-radius: 4px; border-top-right-radius: 4px; -moz-border-radius-bottomright: 4px; -webkit-border-bottom-right-radius: 4px; border-bottom-right-radius: 4px; }
.ui-corner-left { -moz-border-radius-topleft: 4px; -webkit-border-top-left-radius: 4px; border-top-left-radius: 4px; -moz-border-radius-bottomleft: 4px; -webkit-border-bottom-left-radius: 4px; border-bottom-left-radius: 4px; }
.ui-corner-all { -moz-border-radius: 4px; -webkit-border-radius: 4px; border-radius: 4px; }

/* Overlays */
.ui-widget-overlay { background: #666666 url(images/ui-bg_diagonals-thick_20_666666_40x40.png) 50% 50% repeat; opacity: .50;filter:Alpha(Opacity=50); }
.ui-widget-shadow { margin: -5px 0 0 -5px; padding: 5px; background: #000000 url(images/ui-bg_flat_10_000000_40x100.png) 50% 50% repeat-x; opacity: .20;filter:Alpha(Opacity=20); -moz-border-radius: 5px; -webkit-border-radius: 5px; border-radius: 5px; }/*
 * jQuery UI Slider @VERSION
 *
 * Copyright 2010, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Slider#theming
 */
.ui-slider { position: relative; text-align: left; }
.ui-slider .ui-slider-handle { position: absolute; z-index: 2; width: 1.2em; height: 1.2em; cursor: default; }
.ui-slider .ui-slider-range { position: absolute; z-index: 1; font-size: .7em; display: block; border: 0; background-position: 0 0; }

.ui-slider-horizontal { height: .8em; }
.ui-slider-horizontal .ui-slider-handle { top: -.3em; margin-left: -.6em; }
.ui-slider-horizontal .ui-slider-range { top: 0; height: 100%; }
.ui-slider-horizontal .ui-slider-range-min { left: 0; }
.ui-slider-horizontal .ui-slider-range-max { right: 0; }

.ui-slider-vertical { width: .8em; height: 100px; }
.ui-slider-vertical .ui-slider-handle { left: -.3em; margin-left: 0; margin-bottom: -.6em; }
.ui-slider-vertical .ui-slider-range { left: 0; width: 100%; }
.ui-slider-vertical .ui-slider-range-min { bottom: 0; }
.ui-slider-vertical .ui-slider-range-max { top: 0; }
</style>


  <script type=\"text/javascript\">
    jQuery(function() {

      var application = {
        cssParams: {
          0: {
            subject: {
              width: \"99%\",
              height: \"85%\"
            }
          },
          0.25: {
            subject: {
              width: \"98%\",
              height: \"115%\"
            },
            debug: {
              left: \"75.5%\",
              width: \"97%\",
              height: \"194%\"
            }
          },
          0.5: {
            subject: {
              width: \"98%\",
              height: \"158%\"
            },
            debug: {
              left: \"51%\",
              width: \"97%\",
              height: \"158%\"
            }
          },
          0.75: {
            subject: {
              width: \"98%\",
              height: \"194%\"
            },
            debug: {
              left: \"26%\",
              width: \"97%\",
              height: \"120%\"
            }
          },
           1: {
            debug: {
              left: \"1%\",
              width: \"97%\",
              height: \"85%\"
            }
          }
        },
        subjectZoom: 0.5,
        debugZoom: 0.5,
        debugLeft: 51,
        debugWidth: 97,
        debugHeight: 150,
        subjectHeight: 150,
        barVisible: true,
        selectableTarget: true,
        subjectUrl: jQuery(\"#subject\").attr(\"src\")
      };


      application.zoom = function(scale) {
        var transformations = application.cssParams[scale];
        if (transformations.subject) {
          for (var i in transformations.subject) {
            jQuery(\"#subject\").css(i, transformations.subject[i]);
          }
        }
        if (transformations.debug) {
          for (var i in transformations.debug) {
            jQuery(\"#debug\").css(i, transformations.debug[i]);
          }
        }

        this.debugZoom = scale; 
        jQuery(\"#debug\").css(\"zoom\", this.debugZoom * 100 + \"%\");
        jQuery(\"#debug\").css(\"-moz-transform\", \"scale(\" + this.debugZoom + \")\");
        jQuery(\"#debug\").css(\"-webkit-transform\", \"scale(\" + this.debugZoom + \")\");
        jQuery(\"#debug\").css(\"-o-transform\", \"scale(\" + this.debugZoom + \")\");

        this.subjectZoom = 1 - scale;
        jQuery(\"#subject\").css(\"zoom\", this.subjectZoom * 100 + \"%\");
        jQuery(\"#subject\").css(\"-moz-transform\", \"scale(\" + this.subjectZoom + \")\");
        jQuery(\"#subject\").css(\"-webkit-transform\", \"scale(\" + this.subjectZoom + \")\");
        jQuery(\"#subject\").css(\"-o-transform\", \"scale(\" + this.subjectZoom + \")\");
      }

      application.toggleBar = function() {
        if (this.barVisible) {
          jQuery(\"#switcher-bar\").hide(\"slide\", { direction: \"left\" }, 1000);
          jQuery(\"#switcher-arrow\").html(\"&rarr;\");
          this.barVisible = false;
        } else {
          jQuery(\"#switcher-bar\").show(\"slide\", { direction: \"left\" }, 1000);
          jQuery(\"#switcher-arrow\").html(\"&larr;\");
          this.barVisible = true;
        }
      }

      jQuery(\"#subject\").load(function(){
        window.debug.location.reload(false)
        console.log(\"refresh debug\");
      });

      jQuery(\"#refresh\").click(function(obj) {
        var subject = jQuery(\"#subject\");
        subject.attr(\"src\", subject.attr(\"src\"));
      });

      jQuery(\"#refresh_debug\").click(function(obj) {
         window.debug.location.reload(false)
      });

      jQuery(\"#target\").click(function(obj) {
        if (application.selectableTarget) {
          obj.target.selectionStart = 0;
          obj.target.selectionEnd = obj.target.value.length;
          application.selectableTarget = false;
        }
        jQuery(this).removeClass(\"inactive\");
        jQuery(this).addClass(\"active\");
      });

      jQuery(\"#target\").blur(function(obj) {
        application.selectableTarget = true;
      })

      jQuery(\"#target\").keypress(function(event) {
        if (event.keyCode == 13) {
          jQuery(this).trigger(\"change\");
          jQuery(\"#refresh\").focus();
        }
      });

      jQuery(\"#target\").change(function(obj) {
        jQuery('#subject').attr(\"src\", application.subjectUrl + obj.target.value);
        jQuery(this).trigger(\"focusout\");
      });

      jQuery(\"#target\").focusout(function(obj) {
        jQuery(this).removeClass(\"active\");
        jQuery(this).addClass(\"inactive\");        
      });      

      jQuery(\"#switcher\").click(function(obj) {
        application.toggleBar();
      })

      jQuery(\"#slider\").slider({
        value: 0.75,
        min: 0,
        max: 1,
        step: 0.25,
        slide: function(event, ui) {
          application.zoom(ui.value);
        }
      });

      application.zoom(0.75);

    });
  </script>
  <style type=\"text/css\">
    body {
      overflow: hidden;
    }
    .panel_subject {
      height: 145%;
      width: 100%;
      position: absolute;
      margin-top: 60px;
      zoom: 50%;
      -moz-transform: scale(0.5);
      -moz-transform-origin: 0 0;
      -o-transform: scale(0.5);
      -o-transform-origin: 0 0;
      -webkit-transform: scale(0.5);
      -webkit-transform-origin: 0 0;
    }
    .panel_debug {
      height: 145%;
      position: absolute;
      margin-top: 60px;
      left: 51%;
      width: 97%;
      zoom: 50%;
      -moz-transform: scale(0.5);
      -moz-transform-origin: 0 0;
      -o-transform: scale(0.5);
      -o-transform-origin: 0 0;
      -webkit-transform: scale(0.5);
      -webkit-transform-origin: 0 0;
    }
    .bar {
      position: absolute;
      top: 10px;
      left: 20px;
    }
    .switcher {
      background: #3623FF;
      width: 20px;
      position: absolute;
      height: 50px;
      cursor: pointer;
      top: 5px;
      left: 1px;
      padding-top: 10px;
    }
    .switcher-arrow {
      font-weight: bold;
      color: #FFFFFF;
      padding-top: 10px;
      font-size: 19px;
      cursor: pointer;
    }
    .switcher-bar {
      margin-left: 20px;
    }
    .slider {
      margin-bottom: 10px;
    }
    .input {
      padding:2px 0 2px 7px;
      color: #000;
      -moz-border-radius: 5px 5px 5px 5px;
      -webkit-border-radius: 5px 5px 5px 5px;
      border-radius: 5px 5px 5px 5px;
      border: 1px solid #000000;
    }
    .inactive{
      border: 1px solid #DFDFDF;
      border-bottom:
      1px dashed #FF0000;
      background:#FFF1A8;
    }
    .active{border: 1px solid #8F8F8F;}
    .hidden{display: none;}
    .button {
      -moz-border-radius: 5px 5px 5px 5px;
      -webkit-border-radius: 5px 5px 5px 5px;
      border-radius: 5px 5px 5px 5px;
      border: 1px solid #000000;
      height: 22px;
      margin-left: 5px;
      padding: 0 5px 2px;
    }
  </style>
</head>
<body>
  <iframe src=\"http://onkasko.aak.dev\" class=\"panel_subject\" id=\"subject\" name=\"subject\"></iframe>
  <iframe src=\"http://onkasko.aak.dev/debug.html\" class=\"panel_debug\" id=\"debug\" name=\"debug\"></iframe>
  <input type=\"hidden\" id=\"hash\" name=\"hash\" value=\"\" />
  <div id=\"switcher\" class=\"switcher\">
    <span id=\"switcher-arrow\" class=\"switcher-arrow\">&larr;</span>
  </div>
  <div class=\"bar\">
    <div id=\"switcher-bar\" class=\"switcher-bar\">
      <div id=\"slider\" class=\"slider\"></div>
      <input type=\"text\" id=\"target\" value=\"/\" class=\"input inactive\" />
      <input type=\"text\" id=\"input-hidden\" value=\"\" class=\"hidden\" />
      <input type=\"button\" value=\"Refresh\" id=\"refresh\" class=\"button\" />
      <input type=\"button\" value=\"Refresh debuger\" id=\"refresh_debug\" class=\"button\" />
    </div>
  </div>
</body>
</html>";
  }

}

