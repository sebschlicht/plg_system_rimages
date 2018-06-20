<?php

// no direct access
defined( '_JEXEC' ) or die;

// dependencies
require_once 'DomTreeTraverser.php';
require_once 'FileHelper.php';
require_once 'HtmlHelper.php';
require_once 'PredefinedBreakpoints.php';

if (!defined( 'JPATH_TMP' ))
{
    define( 'JPATH_TMP', JPATH_ROOT . '/tmp');
}

// set up a logger
JLog::addLogger(
    array(
         // Sets file name
         'text_file' => 'plg_system_rimages.log.php',
         'logger' => 'messagequeue'
    ),
        // Sets messages of all log levels to be sent to the file
    JLog::ALL,
        // The log category/categories which should be recorded in this file
        // In this case, it's just the one category from our extension, still
        // we need to put it inside an array
    array('rimages')
);

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
        $html = $this->processHtml( $row->text, $breakpointPackages );
        if ($html) $row->text = $html;

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

            $html = $this->processHtml( JResponse::getBody(), $breakpointPackages );
            if ($html) JResponse::setBody( $html );
        }
    }
    
    /**
     * Processes a piece of HTML markup code and returns the processed version.
     * Processing, in this context, means to wrap images with a picture tag and add alternative version according to the configuration.
     * 
     * @param string $html HTML code to process
     * @param array $breakpointPackages configured breakpoint packages
     * @return string|bool passed HTML code with responsive images or false if no non-responsible images found
     */
    private function processHtml( $html, $breakpointPackages )
    {
        // don't process HTML without configure breakpoints
        if (!$breakpointPackages || !$html) return false;

        // set up DOM tree traverser
        $dt = new DomTreeTraverser();
        $dt->loadHtml( $html );

        $filterImages = function ( $node ) { return $node->tagName === 'img'; };

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
        return $imagesReplaced ? $dt->getHtml() : false;
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

        // load local image
        $doDownloadExternalImages = $this->params->get( 'download_images', false );
        if (!FileHelper::isExternalUrl( $src ))
        {
            JLog::add( "Processing local image: $src", JLog::DEBUG, 'rimages' );
            $relFile = FileHelper::getLocalPath( $src );
            $orgFile = JPATH_ROOT . "/$relFile";
            if (!FileHlper::isPathWithin( $orgFile, JPATH_ROOT ))
            {
                JLog::add( "Original image path is outside of system boundaries!", JLog::WARNING, 'rimages' );
                return false;
            }

            if(!is_file( $orgFile ))
            {
                JLog::add( "Image file '$relFile' is missing!", JLog::WARNING, 'rimages' );
                return false;
            }
        }
        // load external image
        elseif ($doDownloadExternalImages)
        {
            JLog::add( "Processing remote image: $src", JLog::DEBUG, 'rimages' );
            $relFile = FileHelper::buildRelativePathFromUrl( $src );
            $orgFile = JPATH_TMP . "/$relFile";
            if (!FileHlper::isPathWithin( $orgFile, JPATH_TMP ))
            {
                JLog::add( "Original image path is outside of system boundaries!", JLog::WARNING, 'rimages' );
                return false;
            }

            // download the external image if missing or obsolete
            if (!is_file( $orgFile ) || !$this->isCacheValid( $src ))
            {
                JLog::add( "Downloading remote image to '$orgFile'...", JLog::DEBUG, 'rimages' );
                if (!FileHelper::downloadFile( $src, $orgFile ))
                {
                    JLog::add( "Failed to download remote image '$src' to '$orgFile'!", JLog::WARNING, 'rimages' );
                    return false;
                }
            }
        }
        // ignore external image
        else
        {
            JLog::add( "Skipping remote image: $src", JLog::DEBUG, 'rimages' );
            return false;
        }

        JLog::add( "Relative image path: $relFile", JLog::DEBUG, 'rimages' );
        JLog::add( "Original image path: $orgFile", JLog::DEBUG, 'rimages' );
        
        // build replica directory
        $replicaRoot = rtrim( $this->params->get( 'replica_root', 'images/rimages' ), '/' );
        $replicaSrc = "$replicaRoot/$relFile";
        $replicaDir = JPATH_ROOT . "/$replicaSrc";
        if (!FileHelper::isPathWithin( $replicaDir, JPATH_ROOT . "/$replicaRoot" ))
        {
            JLog::add( "Replica path is outside of system boundaries!", JLog::WARNING, 'rimages' );
            return false;
        }
        JLog::add( "Replica folder (short): $replicaSrc", JLog::DEBUG, 'rimages' );

        // load file info
        $pathInfo = pathinfo( $relFile );
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        
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
            $srcResponsive = "$replicaSrc/" . self::buildResponsiveImageFilename( $pathInfo, $viewportWidthValue );
            JLog::add( "Responsive image version $viewportWidthValue: $srcResponsive", JLog::DEBUG, 'rimages' );

            // generate the file if not available and automatic creation enabled
            if (!is_file( $srcResponsive ))
            {
                // check if image generation enabled and width provided
                if ($doGenerateMissingSources && $breakpoint['image'])
                {
                    jimport('joomla.filesystem.folder');
                    if (!is_dir( $replicaDir ))
                    {
                        if (!JFolder::create( $replicaDir ))
                        {
                            JLog::add( "Failed to create replica folder '$replicaDir'!", JLog::ERROR, 'rimages' );
                            continue;
                        }
                    }
                    if (self::isWritable( $replicaDir ))
                    {
                        if (!self::generateImage( $orgFile, $srcResponsive, $breakpoint['image'] ))
                        {
                            JLog::add( "Failed to generate missing version '$srcResponsive'!", JLog::ERROR, 'rimages' );
                            continue;
                        }
                    }
                    else
                    {
                        JLog::add( "Failed to access replica folder at '$replicaDir'!", JLog::ERROR, 'rimages' );
                        continue;
                    }
                }
                else
                {
                    JLog::add( "Automatic image generation disabled or target width undefined!", JLog::DEBUG, 'rimages' );
                    continue;
                }
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
     * Builds the file name of the responsive version of an image.
     * 
     * @param array $pathInfo path info of the original image
     * @param int $width (optional) image width for a width suffix, leave out to refer to the original size
     * @return string file name for the responsive image version in the specified size
     */
    private static function buildResponsiveImageFilename( &$pathInfo, $width = null )
    {
        return $pathInfo['filename'] . ($width !== null ? "_$width" : '') . '.jpg';
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
