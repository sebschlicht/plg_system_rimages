<?php

/**
 * Class to work with pre-defined breakpoints, such as from Bootstrap.
 */
class PredefinedBreakpoints
{
    /**
     * pre-defined minimum widths (in pixels)
     */
    private static $MIN_WIDTHS = [
        // Bootstrap breakpoints
        'sm' => 768,
        'md' => 992,
        'ld' => 1200,
    ];

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
     * @param string $border min/max, depending on whether the breakpoint's minimum or maximum width is requested
     * @return int|false requested width in pixels or false if there's no breakpoint with the given title
     */
    public static function getPredefinedWidth( $widthTitle, $border )
    {
        $array = ($border === 'min') ? self::$MIN_WIDTHS : self::$MAX_WIDTHS;
        return array_key_exists( $widthTitle, $array ) ? $array[$widthTitle] : false;
    }
}
