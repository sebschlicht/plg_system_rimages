<?php

// no direct access
defined( '_JEXEC' ) or die;

/**
 * Helper class to build HTML strings.
 */
class HtmlHelper
{
    /**
     * Builds the HTML string of a tag with attributes but no children.
     * 
     * @param string $tagName tag name
     * @param array $attributes attributes as associative array
     * @return string HTML code representing the specified tag
     */
    public static function buildSimpleTag( $tagName, $attributes )
    {
        $html = "<$tagName";
        foreach ($attributes as $key => $value)
        {
            $html .= " $key=\"$value\"";
        }
        return $html . '/>';
    }

    /**
     * Returns the attributes of a node.
     * 
     * @param DOMNode $node node
     * @return array attributes of the specified node
     */
    public static function getNodeAttributes( &$node )
    {
        $attributes = [];
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $attr)
            {
                $attributes[$attr->nodeName] = $attr->nodeValue;
            }
        }
        return $attributes;
    }
}
