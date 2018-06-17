<?php

// no direct access
defined( '_JEXEC' ) or die;

/**
 * Helper class to build HTML strings.
 */
class HtmlHelper
{
    /**
     * valid HTML 5 tags
     */
    private static $HTML5_TAGS = [
        // semantic and structural elements
        'article', 'aside', 'details', 'dialog', 'figcaption', 'figure', 'footer', 'header', 'main', 'mark',
        'menuitem', 'meter', 'nav', 'progress', 'rp', 'rt', 'ruby', 'section', 'summary', 'time',
        // text-level elements
        'bdi',  'wbr',
        // form elements, graphics and media elements
        'datalist', 'keygen', 'output', 'canvas', 'svg', 'audio', 'embed', 'picture', 'source', 'track', 'video',
    ];

    /**
     * Checks whether a tag is a HTML 5 tag.
     * 
     * @param string $tagName tag name
     * @return bool true if the tag is a valid HTML 5 tag, false otherwise
     */
    public static function isHtml5Tag( $tagName )
    {
        return in_array( $tagName, self::$HTML5_TAGS );
    }

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
