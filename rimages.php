<?php

// no direct access
defined( '_JEXEC' ) or die;

require_once 'DomTreeTraverser.php';
require_once 'HtmlHelper.php';
require_once 'PredefinedBreakpoints.php';

/**
 * System plugin to make images on the website responsive.
 * 
 * Next to reducing the sizes of the original image - without modifying its dimensions - alternative versions are populated via the picture tag.
 * This process heavily depends on the configuration of breakpoints, widths where alternative version of images should be used.
 * The generation of alternative versions can be automated, based on ImageMagick.
 * 
 * Breakpoints are organized in packages which apply to a certain CSS selector and the respective set of matching image tags.
 */
class PlgSystemRimages extends JPlugin
{
    /**
     * configuration key for the global image configuration
     */
    private static $CFG_GLOBAL = 'global';

    /**
     * configuration key for the content image configuration
     */
    private static $CFG_CONTENT = 'content';

    /**
     * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
     * If you want to support 3.0 series you must override the constructor
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * Triggers the processing of content HTML code.
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
     * Triggers the processing of remaining images (neither content nor module) when in front-end, using the global configuration.
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
     * Processes a piece of HTML markup code and returns the processed version.
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
                    // inject picture tag
                    $dt->replaceNode( $image, '<picture>' . implode( '', $sources ) . '</picture>' );
                    $imagesReplaced = true;
                }
            }
        }
        return $imagesReplaced ? $dt->getHtml() : $html;
    }

    /**
     * Retrieves the sources of an image.
     * This includes alternative versions for the configured breakpoints (source tags) alongside with the original image (img tag).
     * 
     * @param DOMNode $image image node
     * @param array $breakpoints configured breakpoint package
     * @param bool $doGenerateMissingSources flag whether to generate missing alternative version automatically (defaults to false)
     * @return array list of sources (as HTML code)
     */
    private function getAvailableSources( &$image, &$breakpoints, $doGenerateMissingSources = false )
    {
        // try to load image source
        $src = $image->hasAttribute( 'src' ) ? $image->getAttribute( 'src' ) : false;
        if (!$src) return false;

        // extract path information from image source
        $localBasePath = self::extractPathInfo( $src );
        if (!$localBasePath) return false;

        // loop configured breakpoints
        $sources = [];
        foreach( $breakpoints as $breakpoint)
        {
            // load targeted viewport width
            $viewportWidth = $breakpoint[$breakpoint['type'] === '0' ? 'custom' : 'type'];
            $viewportWidthValue = (filter_var( $viewportWidth, FILTER_VALIDATE_INT ) === false)
                ? PredefinedBreakpoints::getPredefinedWidth( $viewportWidth, $breakpoint['border'] )
                : $viewportWidth;
            if (!$viewportWidthValue) continue;

            // build path to respective responsive version
            $srcResponsive = self::buildFilePath( $localBasePath['directory'], $localBasePath['filename'], $viewportWidthValue );

            // generate the file if not available and automatic creation enabled
            if (!is_file( $srcResponsive ))
            {
                // check if image generation is enabled and possible
                if (!$doGenerateMissingSources || !$breakpoint['image']) continue;

                // build file path to original file and generate responsive version
                $srcOriginal = self::buildOriginalFilePath( $localBasePath['directory'], $localBasePath['filename'], $localBasePath['extension'] );

                if (!self::isWritable( $srcResponsive ) || !self::generateImage( $srcOriginal, $srcResponsive, $breakpoint['image'] )) continue;
            }

            // build and add source tag
            $sourceTag = HtmlHelper::buildSimpleTag( 'source', [
                'media' => '(' . ($breakpoint['border'] === 'max' ? 'max' : 'min') . "-width: {$viewportWidthValue}px)",
                'srcset' => $srcResponsive,
                'data-rimages-w' => $viewportWidthValue,
            ] );
            array_push( $sources, $sourceTag );
        }

        // add original image
        array_push( $sources, HtmlHelper::buildSimpleTag( 'img', HtmlHelper::getNodeAttributes( $image ) ) );

        return $sources;
    }

    /**
     * Extracts the path information from an image source.
     * 
     * @param string $imgSrc image source (path or URL)
     * @return array|bool path information to localize the image or false if the source attribute points at an external image
     */
    private static function extractPathInfo( $imgSrc )
    {
        // check if image source is path or URL
        if ( preg_match( '/^https?:/i', $imgSrc ) === 0 )
        {
            $path = pathinfo( $imgSrc );
            $directory = $path['dirname'];
            $filename = $path['filename'];
            $extension = $path['extension'];
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
                return false;
            }
        }
        return [
            'directory' => $directory,
            'filename' => $filename,
            'extension' => $extension,
        ];
    }

    /**
     * Builds the path to an alternative version of an image file.
     * 
     * @param string $directory directory of the file
     * @param string $filename name of the file
     * @param int $width width of the image
     * @return string path to the alternative version of the image width the given width
     */
    private static function buildFilePath( $directory, $filename, $width )
    {
        return $directory . DIRECTORY_SEPARATOR . $filename . "_$width.jpg";
    }

    /**
     * Builds the path to the original version of an image file.
     * 
     * @param string $directory directory of the file
     * @param string $filename name of the file
     * @param string $extension file extension
     * @return string path to the specified image
     */
    private static function buildOriginalFilePath( $directory, $filename, $extension )
    {
        // make absolute paths relative
        if (substr( $directory, 0, 1 ) === '/') $directory = substr( $directory, strlen( JURI::base( true ) ) + 1 );

        return JPATH_ROOT . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $filename . ".$extension";
    }

    /**
     * Checks whether a path is writable.
     * If the given path is a file, it's checked whether its parental directory can be written.
     * 
     * @param string $path path to be checked
     * @return bool true if the given path can be written, false otherwise
     */
    private static function isWritable( $path )
    {
        return is_writable( is_dir( $path ) ? $path : dirname( $path ) );
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

    /**
     * Generates a reduced JPEG version of an image, following the Google PageSpeed Insight recommendation for images.
     * 
     * @param string $source path to the original image
     * @param string $target target path for the file being generated
     */
    private static function generateImage( $source, $target, $maxWidth )
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
