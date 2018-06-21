<?php

// no direct access
defined( '_JEXEC' ) or die;

// dependencies
jimport('joomla.filesystem.folder');
require_once 'DomTreeTraverser.php';
require_once 'FileHelper.php';
require_once 'HtmlHelper.php';
require_once 'PredefinedBreakpoints.php';

if (!defined( 'JPATH_TMP' ))
{
    define( 'JPATH_TMP', JPATH_ROOT . '/tmp');
}

// set up a file logger
JLog::addLogger(
    array(
         'text_file' => 'plg_system_rimages.log.php'
    ),
    JLog::WARNING,
    array('rimages')
);
if (JDEBUG)
{
    // set up an on-screen logger for debugging
    JLog::addLogger(
        array(
            'logger' => 'messagequeue'
        ),
        JLog::ALL,
        array('rimages')
    );
}

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
     * database table name for external images
     */
    private static $DB_EXTERNAL_IMAGES = '#__rimages_externalimages';

    /**
     * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
     * If you want to support 3.0 series you must override the constructor
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * Database object
     *
     * @var    JDatabaseDriver
     * @since  3.3
     */
    protected $db;

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
        // don't process HTML if it's empty
        if (!$html || ctype_space( $html )) return false;

        // set up DOM tree traverser
        $dt = new DomTreeTraverser();
        $dt->loadHtml( $html );

        $filterImages = function ( $node ) { return $node->tagName === 'img'; };

        // process configured breakpoint packages
        $imagesReplaced = false;
        foreach ($breakpointPackages as $breakpointPackage)
        {
            // find matching non-responsive images
            $images = $dt->find( $breakpointPackage['selector'] );
            $images = $dt->remove( $images, 'picture img' );
            $images = array_filter( $images, $filterImages );

            // process specified images
            foreach ($images as $image)
            {
                // ignore previously processed original images
                if (!$image->hasAttribute( 'data-rimages' ))
                {
                    $tagHtml = $this->getAvailableSources( $image, $breakpointPackage );

                    if ($tagHtml)
                    {
                        // inject picture/img tag
                        $dt->replaceNode( $image, $tagHtml );
                        $imagesReplaced = true;
                    }
                }
            }
        }

        // process all images to replace originals
        if ( $this->params->get( 'replace_original', true ) )
        {
            $images = $dt->find( 'img' );
            $images = $dt->remove( $images, 'picture img' );
            foreach ($images as $image)
            {
                // ignore previously processed original images
                if (!$image->hasAttribute( 'data-rimages' ))
                {
                    $tagHtml = $this->getAvailableSources( $image );
    
                    if ($tagHtml)
                    {
                        // inject picture/img tag
                        $dt->replaceNode( $image, $tagHtml );
                        $imagesReplaced = true;
                    }
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
     * @param array $breakpointPackage configured breakpoint package
     * @return string HTML code of the image/picture tag
     */
    private function getAvailableSources( &$image, &$breakpointPackage = null )
    {
        // try to load image source
        $src = $image->hasAttribute( 'src' ) ? $image->getAttribute( 'src' ) : false;
        if (!$src) return false;

        $doGenerateImages = $this->getPackageGenerateImages( $breakpointPackage );
        $doDownloadExternalImages = $this->params->get( 'download_images', false );
        $doReplaceOriginalImage = $this->params->get( 'replace_original', true );

        // load local image
        if (!FileHelper::isExternalUrl( $src ))
        {
            JLog::add( "Processing local image: $src", JLog::DEBUG, 'rimages' );
            $relFile = FileHelper::getLocalPath( $src );
            $orgFile = JPATH_ROOT . "/$relFile";

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
            if (!FileHelper::isPathWithin( $orgFile, JPATH_TMP ))
            {
                JLog::add( "Original image path '$orgFile' is outside of system boundaries!", JLog::WARNING, 'rimages' );
                return false;
            }

            // download the external image if missing or obsolete
            if ($doGenerateImages && !is_file( $orgFile ) || !$this->isCacheValid( $src ))
            {
                JLog::add( "Downloading remote image to '$orgFile'...", JLog::DEBUG, 'rimages' );
                if (!JFolder::create( dirname( $orgFile ) ))
                {
                    JLog::add( "Failed to create download target folder for '$orgFile'!", JLog::WARNING, 'rimages' );
                    return false;
                }
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
        $replicaRootDir = JPATH_ROOT . "/$replicaRoot";
        $replicaSrc = "$replicaRoot/$relFile";
        $replicaDir = JPATH_ROOT . "/$replicaSrc";
        if (!FileHelper::isPathWithin( $replicaDir, $replicaRootDir ))
        {
            JLog::add( "Replica path '$replicaDir' is outside of system boundaries '$replicaRootDir'!", JLog::WARNING, 'rimages' );
            return false;
        }
        JLog::add( "Replica folder (short): $replicaSrc", JLog::DEBUG, 'rimages' );
       
        // loop configured breakpoints
        $sources = [];
        if ($breakpointPackage)
        {
            foreach( $breakpointPackage['breakpoints'] as $breakpoint)
            {
                // load targeted viewport width
                $viewportWidth = self::getBreakpointWidth( $breakpoint );
                if (!$viewportWidth) continue;
    
                // load the responsive image version (may create it)
                $srcResponsive = $this->loadResponsiveImage( $orgFile, $replicaDir, $doGenerateImages, $viewportWidth,
                    $breakpoint['imageWidth'] );
                if (!$srcResponsive) continue;
    
                // build and add source tag
                $sourceTag = HtmlHelper::buildSimpleTag( 'source', [
                    'media' => "(max-width: {$viewportWidth}px)",
                    'srcset' => $srcResponsive,
                    'data-rimages-w' => $viewportWidth,
                ] );
                array_push( $sources, $sourceTag );
            }
        }

        // handle original image
        $imgAttr = HtmlHelper::getNodeAttributes( $image );
        if ($doReplaceOriginalImage)
        {
            // replace original image with its compressed version
            $srcCompressed = $this->loadResponsiveImage( $orgFile, $replicaDir, $doGenerateImages );
            if ($srcCompressed) $imgAttr['src'] = $srcCompressed;

            // mark img as processed if it won't be within a picture tag
            if (!$sources) $imgAttr['data-rimages'] = null;
        }
        array_push( $sources, HtmlHelper::buildSimpleTag( 'img', $imgAttr ) );

        // return img or picture tag, if sources available
        return count( $sources ) === 1 ? $sources[0] : '<picture>' . implode( '', $sources ) . '</picture>';
    }

    /**
     * Loads a responsive version of an image and, if desired, creates it if it's missing.
     * 
     * @param string $orgFile path to the original image file
     * @param string $replicaDir path to the replica folder
     * @param bool $doGenerateImages (optional) flag whether to generate the responsive version if it's missing
     * @param int $viewportWidth (optional) viewport width name, if it's a resized version
     * @param int $imageWidth (optional) target width of the image, if it's a resized version
     * @param bool $doRegenerateImages (optional) flag whether to re-generate the responsive version if it's existing
     * @return string|false relative path to the responsive image version or false if it's not available
     */
    private function loadResponsiveImage( &$orgFile, &$replicaDir, $doGenerateImages = true, $viewportWidth = null,
        $imageWidth = null, $doRegenerateImages = false )
    {
        // build path to respective responsive version
        $replicaSrc = substr( $replicaDir, strlen( JPATH_ROOT ) + 1 );
        $pathInfo = pathinfo( $orgFile );
        $srcResponsive = "$replicaSrc/" . self::buildResponsiveImageFilename( $pathInfo, $viewportWidth );
        JLog::add( 'Responsive image version (' . ($viewportWidth ? $viewportWidth : 'org') . "): $srcResponsive", JLog::DEBUG, 'rimages' );

        // generate if missing or regeneration requested
        if ($doRegenerateImages || !is_file( $srcResponsive ))
        {
            // check if image generation enabled 
            if ($doGenerateImages)
            {
                // create missing replica folder
                if (!is_dir( $replicaDir ) && !JFolder::create( $replicaDir ))
                {
                    JLog::add( "Failed to create replica folder '$replicaDir'!", JLog::ERROR, 'rimages' );
                    return false;
                }
                // generate responsive image version
                try
                {
                    if (!self::generateImage( $orgFile, $srcResponsive, $imageWidth ))
                    {
                        JLog::add( "Failed to generate missing version '$srcResponsive'!", JLog::ERROR, 'rimages' );
                        return false;
                    }
                    else
                    {
                        // image has been generated successfully
                        return $srcResponsive;
                    }
                }
                catch (Exception $e)
                {
                    JLog::add( "Failed to generate missing version '$srcResponsive': {$e->getMessage()}!", JLog::ERROR, 'rimages' );
                    return false;
                }
            }
            else
            {
                JLog::add( "Automatic image generation disabled or target width undefined!", JLog::DEBUG, 'rimages' );
                return false;
            }
        }
        else
        {
            // use existing image
            return $srcResponsive;
        }
    }

    /**
     * Retrieves the generate images flag of a package, that is either globally configured value or its package override.
     * The flag will always be false if the ImageMagick extension isn't available.
     * 
     * @param array $breakpointPackage breakpoint package
     * @param bool generate images flag to be applied to the package
     */
    private function getPackageGenerateImages( &$breakpointPackage )
    {
        return extension_loaded( 'imagick' )
            && (($breakpointPackage && array_key_exists( 'generate_images', $breakpointPackage ))
                ? $breakpointPackage['generate_images'] : $this->params->get( 'generate_images', true ));
    }

    /**
     * Retrieves the actual maximum width of a breakpoint.
     * 
     * @param array $breakpoint breakpoint
     * @return int|null breakpoint width or null if the pre-defined width couldn't be resolved
     */
    private static function getBreakpointWidth( &$breakpoint )
    {
        $widthName = $breakpoint['width'] !== '0' ? $breakpoint['width'] : $breakpoint['customWidth'];
        return (filter_var( $widthName, FILTER_VALIDATE_INT ) === false)
            ? PredefinedBreakpoints::getPredefinedWidth( $widthName )
            : $widthName;
    }

    /**
     * Checks whether an image has to be re-downloaded as its caching duration expired.
     * If the image wasn't cached before, a caching entry will be created and stored.
     * If the caching duration is expired, the respective caching entry will be updated immediately.
     * 
     * @param string $src image source
     * @return bool true if the image's caching duration hasn't expired yet, false otherwise
     */
    private function isCacheValid( $src )
    {
        $hash = sha1( $src );
        $cachingDuration = $this->params->get( 'cache_images', 1440 );

        // look for cache entry
        $query = $this->db->getQuery( true )
            ->select( $this->db->quoteName( 'timestamp' ) )
            ->from( $this->db->quoteName( self::$DB_EXTERNAL_IMAGES ) )
            ->where( $this->db->quoteName( 'imgid' ) . " = '$hash'" );
        
        $this->db->setQuery( $query );
        $timestamp = $this->db->loadResult();

        // build object
        $co = new stdClass();
        $co->imgid = $hash;
        $co->timestamp = $timestamp ? $timestamp : date('Y-m-d H:i:s');

        // check if cached image obsolete, update if necessary
        if ($timestamp)
        {
            $dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $co->timestamp );
            if ( $dt->modify( "+$cachingDuration minutes" ) < new DateTime() )
            {
                $co->timestamp = date('Y-m-d H:i:s');
                $this->db->updateObject( self::$DB_EXTERNAL_IMAGES, $co, 'imgid' );
                return false;
            }
            else
            {
                return true;
            }
        }
        // store cache entry
        else
        {
            $this->db->insertObject( self::$DB_EXTERNAL_IMAGES, $co, 'imgid' );
            return false;
        }
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
        return $pathInfo['filename'] . ($width ? "_$width" : '') . '.jpg';
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
        $sortBreakpoints = function( $b1, $b2 ) {
            $width1 = self::getBreakpointWidth( $b1 );
            $width2 = self::getBreakpointWidth( $b2 );
            return $width1 < $width2 ? -1 : ($width1 > $width2 ? 1 : 0); 
        };

        // search for tuples of selector and breakpoints as long as such tuples are available
        $packages = [];
        for ($i = 1; $i === 1 || $selector && $breakpoints; $i++)
        {
            // try to retrieve tuple with current index
            $selector = $this->params->get( "{$fieldPrefix}_selector$i", false );
            $breakpoints = $this->params->get( "{$fieldPrefix}_breakpoints$i", false );

            if ($selector && $breakpoints)
            {
                // build breakpoint package
                $package = [
                    'selector' => $selector,
                    'breakpoints' => array_map( $castToArray, array_values( (array) $breakpoints ) ),
                ];

                // add package with sorted breakpoints
                usort( $package['breakpoints'], $sortBreakpoints );
                array_push( $packages, $package );
            }
        }
        return $packages;
    }

    /**
     * Generates a compressed JPEG version of an image, following the Google PageSpeed Insight recommendation for images.
     * The image may be resized during this process if a maximum width is specified.
     * The generation is aborted if it's resized but the original image doesn't exceed the given width.
     * 
     * @param string $source path to the original image
     * @param string $target target path for the file being generated
     * @param int $maxWidth (optional) maximum width of the compressed image
     * @return bool true if the image has been generated successfully, false otherwise
     */
    private static function generateImage( $source, $target, $maxWidth = null )
    {
        $im = new Imagick( $source );
        $im = $im->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );

        $im->stripImage();

        if ($maxWidth)
        {
            $imgWidth = $im->getImageWidth();
            if ($imgWidth > $maxWidth)
            {
                $targetHeight = $maxWidth * ($im->getImageHeight() / $imgWidth);
                $im->resizeImage( $maxWidth, $targetHeight, Imagick::FILTER_SINC, 1 );
            }
            else
            {
                return false;
            }
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
