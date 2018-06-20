<?php

/**
 * Class to work with pre-defined breakpoints, such as from Bootstrap.
 */
class PredefinedBreakpoints
{
    /**
     * pre-defined maximum widths (in pixels)
     */
    private static $MAX_WIDTHS = [
        // Bootstrap breakpoints
        'xs' => 767,
        'sm' => 991,
        'md' => 1199,
    ];

    /**
     * Retrieves a pre-defined breakpoint width.
     * 
     * @param string $widthTitle title of the pre-defined breakpoint
     * @return int|false requested width in pixels or false if there's no breakpoint with the given title
     */
    public static function getPredefinedWidth( $widthTitle )
    {
        return array_key_exists( $widthTitle, self::$MAX_WIDTHS ) ? self::$MAX_WIDTHS[$widthTitle] : false;
    }
}
