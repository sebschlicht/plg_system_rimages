<?php

class DomTreeTraverser {

    private $dom;

    private $node;

    private $level;

    private $classes;

    private $parseSelector;

    public function __construct()
    {
        $this->parseSelector = function( $selector ) { return preg_split( '/(?=[.#])/', $selector, 0, PREG_SPLIT_NO_EMPTY ); };
    }

    private function parseSelector( $selector )
    {
        $selectors = explode( ',', $selector );
        return array_map( $this->parseSelector, $selectors );
    }

    public function loadHtml( $html )
    {
        $this->dom = new DOMDocument('1.0','UTF-8');
        $this->dom->substituteEntities=FALSE;
        $this->dom->recover=TRUE;
        $this->dom->strictErrorChecking=FALSE;
        $this->dom->loadHtml( $html );

        $this->level = 0;
        $this->classes = [];
        $this->node = $this->dom->documentElement;
    }

    public function findNext( $selector )
    {
        $selectors = $this->parseSelector( $selector );
        do {
            if ($this->nextNode()) $this->loadNode();
        } while (!$this->is( $selectors ));
    }

    public function is( $selector )
    {
        $selectors = is_array( $selector ) ? $selector : $this->parseSelector( $selector );
        foreach ($selectors as $s)
        {
            foreach ($s as $part)
            {
                if (substr( $part, 0, 1 ) === '#')
                {
                    // TODO
                }
                elseif (substr( $part, 0, 1 ) === '.')
                {
                    if ($this->hasClass( substr( $part, 1 ) )) return true;
                }
                else
                {
                    if ($this->node->nodeName === $part) return true;
                }
            }
        }
        return false;
    }

    private function loadNode()
    {
        // load CSS classes
        if ($this->node->hasAttribute( 'class' ))
        {
            foreach (explode( ' ', $this->node->getAttribute( 'class' ) ) as $class)
            {
                if (!array_key_exists( $class, $this->classes )) $this->classes[$class] = $this->level;
            }
        }
    }

    private function nextNode( $allowDeeper = true )
    {
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

    public function inClass( $class )
    {
        return array_key_exists( $class, $this->classes ) && $this->classes[$class];
    }

    public function hasClass( $class )
    {
        return array_key_exists( $class, $this->classes ) && $this->classes[$class] === $this->level;
    }
}
