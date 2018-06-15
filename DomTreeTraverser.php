<?php

class DomTreeTraverser {

    private $dom;

    private $node;

    private $breakpoints;

    public function loadHtml( $html )
    {
        $this->dom = new DOMDocument('1.0','UTF-8');
        $this->dom->substituteEntities = FALSE;
        $this->dom->recover = TRUE;
        $this->dom->strictErrorChecking = FALSE;
        $this->dom->loadHtml( $html );

        $this->node = null;
    }

    public function find( $selector )
    {
        $result = [];
        $selectors = self::parseSelector( $selector );

        while ($this->nextNode())
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
        if ($this->node === null)
        {
            $this->node = $this->dom->documentElement;
            return true;
        }

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
        $atoms = preg_split( '/(?=[.#])/', $part, 0, PREG_SPLIT_NO_EMPTY );
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

    public function replace( &$node, $html )
    {
        if (!$node->parentNode) return false;

        $d = new DOMDocument();
        $d->loadHtml( $html );
        echo $d->saveHtml(), "<br>";
        $e = $d->documentElement->firstChild;
        $node->parentNode->replaceChild( $this->dom->importNode( $e ), $node );
        return true;
    }

    public function getHtml()
    {
        return $this->dom->saveHtml();
    }
}

/*
 current execution time of 1000 iterations: 7.1s -> 7ms per loop
 */

$dt = new DomTreeTraverser();
$dt->loadHtml( '<div class="a"><img src="a.jpg"/></div> <div class="b"><img src="b.jpg"/><picture><source /><img src="p-b.jpg"/></picture></div>' );
$images = $dt->find( 'img' );
var_dump(count($images) . ' images');
echo "<br><br>";
$images = $dt->remove( $images, 'picture img' );
var_dump(count($images) . ' outside of picture tag');
echo "<br><br>";
foreach( $images as $img )
{
    $dt->replace( $img, "<picture><img src=\"blubb.jpg\"/></picture>" );
}
echo $dt->getHtml();
?>