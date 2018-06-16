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
}
