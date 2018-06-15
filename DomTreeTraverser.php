<?php

class DomTreeTraverser {

    private $dom;

    private $node;

    private $breakpoints;

    public function loadHtml( &$html )
    {
        $this->dom = new DOMDocument('1.0','UTF-8');
        $this->dom->substituteEntities = FALSE;
        $this->dom->recover = TRUE;
        $this->dom->strictErrorChecking = FALSE;
        $this->dom->loadHtml( $html );

        $this->node = $this->dom->documentElement->firstChild;
        if ($this->node->tagName === 'head' && $this->node->nextSibling) $this->node = $this->node->nextSibling;
    }

    public function find( $selector )
    {
        $result = [];
        $selectors = self::parseSelector( $selector );

        $i = 0;
        while ($this->nextNode() && $i++ < 1000)
        {
            // skip text nodes
            if ($this->node->nodeType !== 1) continue;

            if ($this->_is( $selectors, $this->node )) array_push( $result, $this->node );
        }
        return $result;
    }

    public function remove( &$results, $selector )
    {
        $selectors = self::parseSelector( $selector );
        return $this->_remove( $results, $selectors );
    }

    public function is( $selector )
    {
        $selectors = self::parseSelector( $selector );
        return $this->_is( $selectors, $this->node );
    }

    private function nextNode( $allowDeeper = true )
    {
        $node = $this->node;
        $traversingUp = false;

        for ($i = 0; $i === 0 || $traversingUp;)
        {
            // traverse deeper if allowed and a child is available
            if ( !$traversingUp && $allowDeeper && $node->firstChild )
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

    private function _remove( &$results, &$selectors )
    {
        $filter = function( &$node ) use ($selectors) { return !$this->_is( $selectors, $node ); };
        return array_filter( $results, $filter );
    }

    private function _is( &$selectors, &$node )
    {
        foreach( $selectors as $selector)
        {
            if ($this->_isSingle( $selector, $node )) return true;
        }
        return false;
    }

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

    private function _hasClass( $class, &$node )
    {
        return ($node instanceof DOMElement) && $node->hasAttribute( 'class' ) && preg_match( '/(^| )' . $class . '($| )/', $node->getAttribute( 'class' ) );
    }

    private static function parseSelector( $selector )
    {
        // split selector string into single selectors
        $selectors = explode( ',', $selector );
        // process single selectors to extract parts
        return array_map( 'DomTreeTraverser::_parseSingleSelector', $selectors );
    }

    private static function _parseSingleSelector( &$selector )
    {
        // reverse parts to have final target at position zero
        $parts = array_reverse( explode( ' ', $selector ) );
        // process parts to extract atoms
        return array_map( 'DomTreeTraverser::_parsePart', $parts );
    }

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

    public function replaceImageTag( &$node, &$sources )
    {
        // create DOM node from sources
        $d = new DOMDocument();
        $d->loadHtml( '<picture>' . implode( '', $sources ) . '</picture>' );
        $e = $d->documentElement->firstChild->firstChild;

        // append original img tag 
        $e->appendChild( $d->importNode( $node ) );

        // replace img tag in target document
        $node->parentNode->replaceChild( $this->dom->importNode( $e, true ), $node );
    }

    public function getHtml()
    {
        return $this->dom->saveHtml();
    }
}
