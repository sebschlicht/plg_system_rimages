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
    const CFG_GLOBAL = 'global';

    /**
     * configuration key for the content image configuration
     */
    const CFG_CONTENT = 'content';

    /**
     * configuration key for image configurations per module position
     */
    const CFG_MODULE_POSITION = 'module-position';

    /**
     * configuration key for image configurations per module
     */
    const CFG_MODULE = 'module';

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

        // configuration: content || global
        $configuration = $this->loadConfiguredBreakpoints( self::CFG_CONTENT );
        if (!$configuration) $configuration = $this->loadConfiguredBreakpoints( self::CFG_GLOBAL );

        // (generate and) inject responsive images
        $row->text = $this->processHtml( $row->text, $configuration );
        
        return true;
    }
    
    /**
     * Trigger the processing of module HTML code when in front-end.
     * 
     * @param object &$module A reference to a Module object that holds all the data of the module
     * @param array &$attribs An array of attributes for the module
     */
    public function onAfterRenderModule( &$module, &$attribs )
    {
        $app = JFactory::getApplication();
        if ($app->isSite())
        {
            // configuration: module || module position || global
            $configuration = $this->loadConfiguredBreakpoints( self::CFG_MODULE, $module->id );
            if (!$configuration) $configuration = $this->loadConfiguredBreakpoints( self::CFG_MODULE_POSITION, $module->position );
            if (!$configuration) $configuration = $this->loadConfiguredBreakpoints( self::CFG_GLOBAL );

            $module->content = $this->processHtml( $module->content, $configuration );
        }
    }

    /**
     * Trigger the processing of remaining images (neither content nor module) when in front-end, using the global configuration.
     */
    public function onAfterRender()
    {
        $app = JFactory::getApplication();
        if ($app->isSite())
        {
            // configuration: global
            $configuration = $this->loadConfiguredBreakpoints( self::CFG_GLOBAL );
            JResponse::setBody( $this->processHtml( JResponse::getBody(), $configuration ) );
        }
    }
    
    /**
     * Process a pice of HTML markup code and returns the processed version.
     * Processing, in this context, means to wrap images with a picture tag and add alternative version according to the configuration.
     * 
     * @param string $html HTML code to process
     * @param array $configuration configured breakpoints
     * @return string passed HTML code with responsive images
     */
    private function processHtml( $html, $breakpoints )
    {
        // don't process HTML without configure breakpoints
        if (!$breakpoints || !$html) return $html;

        // set up DOM tree traverser
        $dt = new DomTreeTraverser();
        libxml_use_internal_errors( true );
        $dt->loadHtml( $html );
        libxml_clear_errors();

        // find all images outside of picture tags
        // TODO use user-specified selector
        $images = $dt->find( 'img' );
        $images = $dt->remove( $images, 'picture img' );

        $didMagic = false;
        foreach ($images as $image)
        {
            $sources = $this->getAvailableSources( $image, $breakpoints );
            if ($sources)
            {
                $dt->replaceImageTag( $image, $sources );
                $didMagic = true;
            }
        }

        return $didMagic ? $dt->getHtml() : $html;
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
                if (!$doGenerateMissingSources) continue;

                // TODO not implemented
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
            }
            else
            {
                // TODO? option to convert and store external images in a local folder
                $directory = null;
                $filename = null;
            }
        }
        return [
            'directory' => $directory,
            'filename' => $filename,
            'isExternalUrl' => $isExternalUrl,
        ];
    }

    private function buildFilePath( $directory, $filename, $width )
    {
        // TODO support other image formats
        return $directory . DIRECTORY_SEPARATOR . $filename . "_$width.jpg";
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
     * Loads the breakpoints from a section or subsection of the plugin configuration.
     * 
     * @param string $section name of the configuration section
     * @param string $subsection (optional) name of a subsection of the specified section
     * @return array the specified breakpoints from the plugin configuration or null if a subsection has been specified but is missing
     */
    private function loadConfiguredBreakpoints( $section, $subsection = null )
    {
        // parse configuration once per request
        if ( !$this->config )
        {
            $this->config = [
                self::CFG_MODULE => $this->loadFlatBreakpoints( 'module-id', 'module-breakpoints' ),
                self::CFG_MODULE_POSITION => $this->loadFlatBreakpoints( 'modpos-position', 'modpos-breakpoints' ),
                self::CFG_CONTENT => $this->loadSubformBreakpoints( 'content-items' ),
                self::CFG_GLOBAL => $this->loadSubformBreakpoints( 'global-items' ),
            ];
        }

        // retrieve section or subsection if specified and existing
        if (!$subsection) return $this->config[$section];
        else return array_key_exists( $subsection, $this->config[$section] ) ? $this->config[$section][$subsection] : false;
    }

    /**
     * Loads breakpoints from the plugin configuration that are stored in a flat manner, having a single identifier field.
     * Both field names are expected to be suffixed by increasing numbers, starting at 1.
     * 
     * @param string $fieldnameId name of the identifier field
     * @param string $fieldnameBreakpoints name of the breakpoint list field
     * @return array breakpoints grouped by seen identifiers
     */
    private function loadFlatBreakpoints( $fieldnameId, $fieldnameBreakpoints )
    {
        // search for tuples of id and breakpoints as long as such tuples are available
        $result = [];
        for ($i = 1; $i === 1 || $id && $breakpoints; $i++)
        {
            // try to retrieve tuple with current index
            $id = $this->params->get( "$fieldnameId$i", false );
            $breakpoints = $this->params->get( "$fieldnameBreakpoints$i", false );

            // add breakpoints as plain array
            if ($id && $breakpoints)
            {
                $result[$id] = array_values( (array) $breakpoints );
            }
        }
        return $result;
    }

    /**
     * Loads breakpoints from the plugin configuration that are stored in a (multiple) subform field.
     * 
     * @param string $name name of the subform field
     * @return array breakpoints represented by the subform items
     */
    private function loadSubformBreakpoints( $name )
    {
        $result = $this->params->get( $name, false );
        if ($result)
        {
            // transform object with sub-objects to a plain array
            $castToArray = function( $o ) { return (array) $o; };
            $result = array_map( $castToArray, array_values( (array) $result ) );
        }
        return $result ? $result : [];
    }
}
