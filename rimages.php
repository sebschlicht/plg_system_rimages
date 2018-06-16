<?php

// no direct access
defined( '_JEXEC' ) or die;

require_once 'DomTreeTraverser.php';

class PlgSystemRimages extends JPlugin
{
    // TODO class constants require PHP 5.5+

    /**
     * configuration key for the global image configuration
     */
    private static $CFG_GLOBAL = 'global';

    /**
     * configuration key for the content image configuration
     */
    private static $CFG_CONTENT = 'content';

    /**
     * parsed plugin configuration
     */
    private $config;

    /**
     * pre-defined upper breakpoints (max-width)
     */
    private static $MAX_WIDTHS = [
        // Bootstrap breakpoints
        'xs' => 767,
        'sm' => 991,
        'md' => 1199,
    ];

    /**
     * pre-defined lower breakpoints (min-width)
     */
    private static $MIN_WIDTHS = [
        // Bootstrap breakpoints
        'sm' => 768,
        'md' => 992,
        'ld' => 1200,
    ];

    /**
     * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
     * If you want to support 3.0 series you must override the constructor
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * Trigger the processing of content HTML code.
     *
     * @param   string   $context  The context of the content being passed to the plugin.
     * @param   mixed    &$row     An object with a "text" property
     * @param   mixed    $params   Additional parameters. See {@see PlgContentContent()}.
     * @param   integer  $page     Optional page number. Unused. Defaults to zero.
     *
     * @return  boolean	True on success.
     */
    public function onContentPrepare( $context, &$row, $params, $page = 0 )
    {
        // don't run this plugin when the content is being indexed
        if ($context == 'com_finder.indexer') {
            return true;
        }

        // load breakpoints from content configuration, (generate and) inject responsive images
        $breakpointPackages = $this->loadBreakpointPackages( self::$CFG_CONTENT );
        $row->text = $this->processHtml( $row->text, $breakpointPackages );

        return true;
    }

    /**
     * Trigger the processing of remaining images (neither content nor module) when in front-end, using the global configuration.
     */
    public function onAfterRender()
    {
        $app = JFactory::getApplication();
        if ($app->isSite())
        {
            // load breakpoints from global configuration, (generate and) inject responsive images
            $breakpointPackages = $this->loadBreakpointPackages( self::$CFG_GLOBAL );
            JResponse::setBody( $this->processHtml( JResponse::getBody(), $breakpointPackages ) );
        }
    }
    
    /**
     * Process a pice of HTML markup code and returns the processed version.
     * Processing, in this context, means to wrap images with a picture tag and add alternative version according to the configuration.
     * 
     * @param string $html HTML code to process
     * @param array $breakpointPackages configured breakpoint packages
     * @return string passed HTML code with responsive images
     */
    private function processHtml( $html, $breakpointPackages )
    {
        // don't process HTML without configure breakpoints
        if (!$breakpointPackages || !$html) return $html;

        $filterImages = function ( $node ) { return $node->tagName === 'img'; };

        // set up DOM tree traverser
        $dt = new DomTreeTraverser();
        libxml_use_internal_errors( true );
        $dt->loadHtml( $html );
        libxml_clear_errors();

        // process configured breakpoint packages
        $imagesReplaced = false;
        $canGenerateImages = extension_loaded( 'imagick' );
        $globalGenerateImages = $this->params->get( 'generate_images', false );
        $generateImages = false;
        foreach ($breakpointPackages as $breakpointPackage)
        {
            // check if image generation enabled
            $generateImages = $canGenerateImages
                && array_key_exists( 'generate_images', $breakpointPackage) ? $breakpointPackage['generate_images'] : $globalGenerateImages;

            // find matching non-responsive images
            $images = $dt->find( $breakpointPackage['selector'] );
            $images = $dt->remove( $images, 'picture img' );
            $images = array_filter( $images, $filterImages );

            // process specified images
            foreach ($images as $image)
            {
                $sources = $this->getAvailableSources( $image, $breakpointPackage['breakpoints'], $generateImages );
                if ($sources)
                {
                    $dt->replaceImageTag( $image, $sources );
                    $imagesReplaced = true;
                }
            }
        }
        return $imagesReplaced ? $dt->getHtml() : $html;
    }

    private function getAvailableSources( &$image, &$breakpoints, $doGenerateMissingSources = false )
    {
        // try to load image source
        $src = $image->hasAttribute( 'src' ) ? $image->getAttribute( 'src' ) : false;
        if (!$src) return false;

        // extract path information from image source
        $localBasePath = $this->extractPathInfo( $src );

        // loop configured breakpoints
        $sources = [];
        foreach( $breakpoints as $breakpoint)
        {
            // load targeted viewport width
            $viewportWidth = $breakpoint[$breakpoint['type'] === '0' ? 'custom' : 'type'];
            $viewportWidthValue = $this->getWidthValue( $viewportWidth, $breakpoint['border'] );
            if (!$viewportWidthValue) continue;

            // build path to respective responsive version
            $srcResponsive = $this->buildFilePath( $localBasePath['directory'], $localBasePath['filename'], $viewportWidthValue );

            // generate the file if not available and automatic creation enabled
            if (!is_file( $srcResponsive ))
            {
                // check if image generation is enabled and possible
                if (!$doGenerateMissingSources || !$breakpoint['image'] || $localBasePath['isExternalUrl']) continue;

                // build file path to original file and generate responsive version
                $srcOriginal = $this->buildOriginalFilePath( $localBasePath['directory'], $localBasePath['filename'], $localBasePath['extension'] );

                if (!self::isWritable( $srcResponsive ) || !$this->generateImage( $srcOriginal, $srcResponsive, $breakpoint['image'] )) continue;
            }

            // build and add source tag
            $sourceTag = $this->generateShortTag( 'source', [
                'media' => '(' . ($breakpoint['border'] === 'max' ? 'max' : 'min') . "-width: {$viewportWidthValue}px)",
                'srcset' => $srcResponsive,
                'data-rimages-w' => $viewportWidthValue,
            ] );
            array_push( $sources, $sourceTag );
        }
        return $sources;
    }

    private function generateShortTag( $tag, $attributes )
    {
        $result = "<$tag";
        foreach ($attributes as $key => $value)
        {
            $result .= " $key=\"$value\"";
        }
        return $result . '/>';
    }

    private function extractPathInfo( $imgSrc )
    {
        // check if image source is path or URL
        if ( preg_match( '/^https?:/i', $imgSrc ) === 0 )
        {
            $path = pathinfo( $imgSrc );
            $directory = $path['dirname'];
            $filename = $path['filename'];
            $extension = $path['extension'];
            $isExternalUrl = false;
        }
        else
        {
            $url = parse_url( $imgSrc );
            $isExternalUrl = ($url['host'] !== $_SERVER['HTTP_HOST']);

            if (!$isExternalUrl)
            {
                // convert URL to local path
                $path = pathinfo( $url['path'] );
                $directory = substr( $path['dirname'], strlen( JUri::base( true ) ) + 1 );
                $filename = $path['filename'];
                $extension = $path['extension'];
            }
            else
            {
                // TODO? option to convert and store external images in a local folder
                $directory = null;
                $filename = null;
                $extension = null;
            }
        }
        return [
            'directory' => $directory,
            'filename' => $filename,
            'extension' => $extension,
            'isExternalUrl' => $isExternalUrl,
        ];
    }

    private function buildFilePath( $directory, $filename, $width )
    {
        // TODO? support other image formats
        return $directory . DIRECTORY_SEPARATOR . $filename . "_$width.jpg";
    }

    private function buildOriginalFilePath( $directory, $filename, $extension )
    {
        // make absolute paths relative
        if (substr( $directory, 0, 1 ) === '/') $directory = substr( $directory, strlen( JURI::base( true ) ) + 1 );

        return JPATH_ROOT . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $filename . ".$extension";
    }

    private static function isWritable( $path )
    {
        return is_writable( is_dir( $path ) ? $path : dirname( $path ) );
    }

    private function getWidthValue( $widthName, $border )
    {
        if (filter_var( $widthName, FILTER_VALIDATE_INT ) === false)
        {
            $widths = ($border === 'max') ? self::$MAX_WIDTHS : self::$MIN_WIDTHS;
            return array_key_exists( $widthName, $widths ) ? $widths[$widthName] : null;
        }
        else
        {
            return $widthName;
        }
    }

    /**
     * Loads breakpoints from the plugin configuration that are stored in a flat manner, having a single identifier field.
     * Both field names are expected to be suffixed by increasing numbers, starting at 1.
     * 
     * @param string $fieldnameId name of the identifier field
     * @param string $fieldnameBreakpoints name of the breakpoint list field
     * @return array breakpoints grouped by seen identifiers
     */
    private function loadBreakpointPackages( $fieldPrefix )
    {
        $castToArray = function( $o ) { return (array) $o; };

        // search for tuples of selector and breakpoints as long as such tuples are available
        $packages = [];
        for ($i = 1; $i === 1 || $selector && $breakpoints; $i++)
        {
            // try to retrieve tuple with current index
            $selector = $this->params->get( "{$fieldPrefix}_selector$i", false );
            $breakpoints = $this->params->get( "{$fieldPrefix}_breakpoints$i", false );
            $generateImages = $this->params->get( "{$fieldPrefix}_generate_images", null );

            if ($selector && $breakpoints)
            {
                // build and add breakpoint package
                $package = [
                    'selector' => $selector,
                    'breakpoints' => array_map( $castToArray, array_values( (array) $breakpoints ) ),
                ];
                if ($generateImages !== null) $package['generate_images'] = $generateImages;

                array_push( $packages, $package );
            }
        }
        return $packages;
    }

    private function generateImage( $source, $target, $maxWidth )
    {
        $im = new Imagick();
        $im->readImage( $source );
        $im = $im->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );

        $im->stripImage();

        $imgWidth = $im->getImageWidth();
        if ($imgWidth > $maxWidth)
        {
            $targetHeight = $maxWidth * ($im->getImageHeight() / $imgWidth);
            $im->resizeImage( $maxWidth, $targetHeight, Imagick::FILTER_SINC, 1 );
        }

        $im->setImageFormat( 'jpg' );
        $im->setImageCompression( Imagick::COMPRESSION_JPEG );
        $im->setImageCompressionQuality( 85 );
        $im->setInterlaceScheme( Imagick::INTERLACE_JPEG );

        $im->transformImageColorspace( Imagick::COLORSPACE_SRGB );
        $im->setSamplingFactors( ['2x2', '1x1', '1x1'] );

        return $im->writeImage( $target );
    }
}
