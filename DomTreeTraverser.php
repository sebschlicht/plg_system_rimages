<?php

class DomTreeTraverser {

    private $dom;

    private $node;

    private $level;

    private $classes;

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

    public function loadHtml( $html )
    {
        $this->dom = new DOMDocument('1.0','UTF-8');
        $this->dom->substituteEntities=FALSE;
        $this->dom->recover=TRUE;
        $this->dom->strictErrorChecking=FALSE;
        $this->dom->loadHtml( $html );

        $this->level = 0;
        $this->node = null;
    }

    public function findNext( $selector )
    {
        $selectors = self::parseSelector( $selector );

        do {
            if ($this->nextNode()) $this->loadNode();
        } while ($this->node && !$this->is( $selectors ));

        return $this->node;
    }

    private function nextNode( $allowDeeper = true )
    {
        if ($this->node === null) return $this->node = $this->dom->documentElement;

        $node = $this->node;
        $level = $this->level;
        $traversingUp = false;

        for ($i = 0; $i === 0 || $traversingUp;)
        {
            // traverse deeper if allowed and a child is available
            if ( !$traversingUp && $allowDeeper && $node->firstChild )
            {
                $node = $node->firstChild;
                $level += 1;
                break;
            }

            // invalidate current classes if traversing sideways/up
            foreach ($classes as $class => $classLevel)
            {
                if ($classLevel <= $level) $classes[$class] = 0;
            }

            // traverse sideways if siblings available
            if ($node->nextSibling)
            {
                $node = $node->nextSibling;
                break;
            }
            // abort if we reached the root node
            else if (!$level)
            {
                $node = null;
                break;
            }
            // traverse up
            elseif ($node->parentNode)
            {
                $node = $node->parentNode;
                $level -= 1;
                $traversingUp = true;
            }
        }

        if ($node)
        {
            $this->node = $node;
            $this->level = $level;
        }
        return $node;
    }

    public function find( $selector )
    {
        $result = [];
        $selectors = self::parseSelector( $selector );
        var_dump($selectors);

        while ($this->nextNode())
        {
            if ($this->_is( $selectors, $this->node )) array_push( $result, $this->node );
        }
        return $result;
    }

    public function is( $selector )
    {
        $selectors = self::parseSelector( $selector );
        return $this->_is( $selectors, $this->node );
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
}

$dt = new DomTreeTraverser();
$dt->loadHtml( '<div class="a"><img src="a.jpg"/></div> <div class="b"><img src="b.jpg"/></div>' );
var_dump( $dt->find( '.b img' ) );
