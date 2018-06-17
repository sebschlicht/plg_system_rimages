<?php

// no direct access
defined( '_JEXEC' ) or die;

// dependencies
require_once 'HtmlHelper.php';

/**
 * Class to load a DOM tree and find particular sets of nodes via CSS selectors like in jQuery.
 */
class DomTreeTraverser
{
    /**
     * DOM document
     */
    private $dom;

    /**
     * current DOM node
     */
    private $node;

    /**
     * DOM tree loading errors
     */
    public $errors;

    /**
     * Loads the DOM tree of a HTML document.
     * 
     * @param string $html HTML code of the document
     */
    public function loadHtml( &$html )
    {
        $this->dom = new DOMDocument('1.0','UTF-8');
        $this->dom->substituteEntities = FALSE;
        $this->dom->recover = TRUE;
        $this->dom->strictErrorChecking = FALSE;

        // filter warnings; it seems as a doctype, html and body tags lead to warnings
        libxml_use_internal_errors( true );
        $this->dom->loadHtml( $html, LIBXML_NOWARNING );
        $filterHtml5Warnings = function( &$error ) {
            if ($error->code !== 800) return false;
            elseif ($error->code !== 801) return true;
            return preg_match( '/^Tag (\w+) invalid/', $error->message, $match ) ? !HtmlHelper::isHtml5Tag( $match[1] ) : true;
        };
        $this->errors = array_filter( libxml_get_errors(), $filterHtml5Warnings );
        libxml_clear_errors();

        // start with the body tag
        $this->node = $this->dom->documentElement->firstChild;
        if ($this->node->nodeName === 'head' && $this->node->nextSibling) $this->node = $this->node->nextSibling;
        
        return !$this->errors;
    }

    /**
     * Finds all nodes which match a selector.
     * 
     * Due to robustness issues, this function can only scan up to 1000 nodes.
     * 
     * @param string $selector CSS selector
     * @return array matching nodes
     */
    public function find( $selector )
    {
        $result = [];
        $selectors = self::parseSelector( $selector );

        $i = 0;
        while ($this->nextNode() && $i++ < 1000)
        {
            // skip text nodes
            if ($this->node->nodeType !== 1) continue;

            // add matching nodes
            if ($this->_is( $selectors, $this->node )) array_push( $result, $this->node );
        }
        return $result;
    }

    /**
     * Removes all nodes from a set of nodes which match a selector.
     * 
     * @param array $results set of nodes
     * @param string $selector CSS selector
     * @return array the given set of nodes without elements matching the given selector
     */
    public function remove( &$results, $selector )
    {
        $selectors = self::parseSelector( $selector );
        return $this->_remove( $results, $selectors );
    }

    /**
     * Checks whether the current node matches a selector.
     * 
     * @param string $selector CSS selector
     * @return bool true of the current node matches the given selector, false otherwise
     */
    public function is( $selector )
    {
        $selectors = self::parseSelector( $selector );
        return $this->_is( $selectors, $this->node );
    }

    /**
     * Further traverses the DOM tree starting from the current node.
     * 
     * The next node is determined as follows:
     * If the current node has children, the first child is the next node.
     * Otherwise, if the current node has a sibling, this is the next node.
     * Otherwise the DOM tree will be traversed back up until a parent node with a sibling is reached and this sibling is the next node.
     * 
     * @return bool true if a next node is available and has been set as the current node
     */
    private function nextNode()
    {
        $node = $this->node;
        $traversingUp = false;

        for ($i = 0; $i === 0 || $traversingUp;)
        {
            // traverse deeper if allowed and a child is available
            if ( !$traversingUp && $node->firstChild )
            {
                $node = $node->firstChild;
                break;
            }

            // traverse sideways if siblings available
            if ($node->nextSibling)
            {
                $node = $node->nextSibling;
                break;
            }
            // traverse up, if possible
            elseif ($node->parentNode)
            {
                $node = $node->parentNode;
                $traversingUp = true;
            }
            // abort if we reached the root node
            else
            {
                $node = null;
                break;
            }
        }

        $this->node = $node;
        return !!$node;
    }

    /**
     * Internal version of this->remove.
     * 
     * @param array $results set of nodes
     * @param array $selectors parsed selectors
     * @return array set of nodes removed by nodes which matched one of the selectors
     */
    private function _remove( &$results, &$selectors )
    {
        $filter = function( &$node ) use ($selectors) { return !$this->_is( $selectors, $node ); };
        return array_filter( $results, $filter );
    }

    /**
     * Checks whether a node matches a parsed selector.
     * 
     * @param array $selectors parsed selectors
     * @param DOMNode $node node
     * @return bool true if the given node matches any of the specified selectors, false otherwise
     */
    private function _is( &$selectors, &$node )
    {
        foreach( $selectors as $selector)
        {
            if ($this->_isSingle( $selector, $node )) return true;
        }
        return false;
    }

    /**
     * Checks whether a node matches a single, parsed selector.
     * 
     * @param array $selector parsed selector
     * @param DOMNode $node node
     * @return bool true if the given node matches the specified selector, false otherwise
     */
    private function _isSingle( &$selector, &$node )
    {
        $numParts = count( $selector );
        $pointer = $node;
        for ($i = 0; $i < $numParts; $i++)
        {
            // traverse up if possible and we're not searching for the final target until we find the current target
            while (!$this->_isPart( $selector[$i], $pointer ))
            {
                if ($i && $pointer->parentNode) $pointer = $pointer->parentNode;
                else return false;
            }
        }
        return true;
    }

    /**
     * Checks wether a node matches a part of a single selector.
     * In a selector such as '.test img' we have two parts : ['.test', 'img']
     * 
     * @param array $part selector part
     * @param DOMNode $node node
     * @return bool true if the given node matches the given selector part, false otherwise
     */
    private function _isPart( &$part, &$node )
    {
        // compare HTML tag
        if ($part['tag'] && $node->nodeName !== $part['tag']) return false;
        // compare id attribute
        if ($part['id'] && (!$node->hasAttribute( 'id' ) || $node->getAttribute( 'id' ) !== $part['id'])) return false;
        // compare classes
        if ($part['classes']) foreach ($part['classes'] as $class) if (!$this->_hasClass( $class, $node )) return false;

        return true;
    }

    /**
     * Checks whether a node has a certain CSS class.
     * 
     * @param string $class CSS class
     * @param DOMNode $node node
     * @return bool true if the given node has the given CSS class
     */
    private function _hasClass( $class, &$node )
    {
        return ($node instanceof DOMElement) && $node->hasAttribute( 'class' ) && preg_match( '/(^| )' . $class . '($| )/', $node->getAttribute( 'class' ) );
    }

    /**
     * Parses a CSS selector.
     * The selector is de-combined into single selectors which are split into parts themseleves.
     * 
     * @param string $selector CSS selector
     * @return array parsed selector
     */
    private static function parseSelector( $selector )
    {
        // split selector string into single selectors
        $selectors = explode( ',', $selector );
        // process single selectors to extract parts
        return array_map( 'DomTreeTraverser::_parseSingleSelector', $selectors );
    }

    /**
     * Parses a single selector into.
     * The selector is split into parts.
     * 
     * @param array $selector single selector
     * @return array parsed single selector
     */
    private static function _parseSingleSelector( &$selector )
    {
        // reverse parts to have final target at position zero
        $parts = array_reverse( explode( ' ', $selector ) );
        // process parts to extract atoms
        return array_map( 'DomTreeTraverser::_parsePart', $parts );
    }

    /**
     * Parses a selector part.
     * The part is split into atoms, namely id, class and tag disclosures.
     * 
     * @param array $part selector part
     * @return array parsed selector part
     */
    private static function _parsePart( &$part )
    {
        $atoms = preg_split( '/(?=[.#])/', trim( $part ), 0, PREG_SPLIT_NO_EMPTY );
        $result = [
            'id' => false,
            'classes' => false,
            'tag' => false,
        ];
        foreach($atoms as $atom)
        {
            if (substr( $atom, 0, 1 ) === '#') $result['id'] = substr( $atom, 1 );
            if (substr( $atom, 0, 1 ) === '.')
            {
                if ($result['classes'] === false) $result['classes'] = [];
                array_push( $result['classes'], substr( $atom, 1 ) );
            }
            else $result['tag'] = $atom;
        }
        return $result;
    }

    /**
     * Replaces a node with a new node, read from HTML.
     * 
     * @param DOMNode $node node to be replaced
     * @param string $html HTML code that the node should be replaced with
     */
    public function replaceNode( &$node, $html )
    {
        // create DOM node from HTML code
        $d = new DOMDocument();
        $d->loadHtml( $html );
        $e = $d->documentElement->firstChild->firstChild;

        // replace node in target document
        $node->parentNode->replaceChild( $this->dom->importNode( $e, true ), $node );
    }

    /**
     * Retrieves the HTML code of the current DOM tree, including all manipulations.
     * 
     * @return string HTML code of the current DOM tree
     */
    public function getHtml()
    {
        return $this->dom->saveHtml();
    }
}
