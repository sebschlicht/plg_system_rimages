<?php

// no direct access
defined( '_JEXEC' ) or die;

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
        $configuration = $this->loadPluginConfig( self::CFG_CONTENT );
        if (!$configuration) $configuration = $this->loadPluginConfig( self::CFG_GLOBAL );

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
            $configuration = $this->loadPluginConfig( self::CFG_MODULE, $module->id );
            if (!$configuration) $configuration = $this->loadPluginConfig( self::CFG_MODULE_POSITION, $module->position );
            if (!$configuration) $configuration = $this->loadPluginConfig( self::CFG_GLOBAL );

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
            $configuration = $this->loadPluginConfig( self::CFG_GLOBAL );
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
        if (!$breakpoints) return $html;

        // find all images outside of picture tags
        $regexImages = '/<picture>.*?<\/picture>(*SKIP)(*FAIL)|<img.*?src=[\"\']?([^\"\'\s]*)[\"\']?.*?>/';
        $matches = [];
        preg_match_all($regexImages, $html, $matches, PREG_SET_ORDER);

        // loop detected images
        foreach ($matches as $match)
        {
            // extract path information from image source
            $localBasePath = $this->extractPathInfo( $match[1] );

            // loop configured max-widths
            $imageVersions = [];
            foreach ($breakpoints as $breakpoint)
            {
                // load targeted viewport width
                $viewportWidth = $breakpoint[$breakpoint['type'] === '0' ? 'custom' : 'type'];
                $viewportWidthValue = $this->translateWidthName( $viewportWidth );

                // build path to respective responsive version
                $srcResponsive = $this->buildFilePath( $localBasePath, $viewportWidth );

                // generate the file if not available
                if (!is_file( $srcResponsive ))
                {
                    // TODO not implemented
                }

                // build and add source tag
                $sourceTag = $this->generateShortTag( 'source', [
                    'media' => '(' . ($breakpoint['border'] === 'max' ? 'max' : 'min') . "-width: {$viewportWidthValue}px)",
                    'srcset' => $srcResponsive,
                    'data-rimages-w' => $viewportWidthValue,
                ] );
                array_push( $imageVersions, $sourceTag );
            }
            
            if ($imageVersions)
            {
                // add original version
                array_push( $imageVersions, $match[0] );
                            
                // create and inject a picture tag with all versions
                $pictureTag = '<picture>' . implode( '', $imageVersions ) . '</picture';
                $html = preg_replace( $regexImages, $pictureTag, $html, 1 );
            }
        }
        return $html;
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
        // check if image source path or URL
        if ( preg_match( '/^https?:/i', $imgSrc ) === 0 )
        {
            $path = pathinfo( $imgSrc );
            $directory = $path['dirname'];
            $filename = $path['filename'];
            $isExternalUrl = false;
        }
        else
        {
            // TODO convert URL to path if pointing at this site
            $directory = null;
            $filename = null;
            $isExternalUrl = true;
            // TODO? option to convert and store external images in a local folder
        }
        return [
            'directory' => $directory,
            'filename' => $filename,
            'isExternalUrl' => $isExternalUrl,
        ];
    }

    private function buildFilePath( $localBasePath, $widthTitle )
    {
        // if directory empty or a path: prefix with Joomla! site directory
        if (!$localBasePath['isExternalUrl'])
        {
            $localBasePath['directory'] = JUri::base( true ) . DIRECTORY_SEPARATOR . $localBasePath['directory'];
        }

        // TODO support other image formats
        return $localBasePath['directory'] . DIRECTORY_SEPARATOR . $localBasePath['filename'] . "_$widthTitle.jpg";
    }

    private function translateWidthName( $widthName )
    {
        $widths = [
            'xs' => '767',
            'sm' => '991',
            'md' => '1199',
        ];
        return array_key_exists( $widthName, $widths ) ? $widths[$widthName] : $widthName;
    }

    private function loadPluginConfig( $section, $identifier = null )
    {
        if ( !$this->config )
        {
            $this->config = [
                self::CFG_MODULE => $this->loadModuleConfig(),
                self::CFG_MODULE_POSITION => $this->loadModulePosConfig(),
                self::CFG_CONTENT => $this->loadSubformItems( 'content-items' ),
                self::CFG_GLOBAL => $this->loadSubformItems( 'global-items' ),
            ];
            //echo json_encode( $this->config ), '<br>';
        }

        if (!$identifier) return $this->config[$section];
        else return array_key_exists( $identifier, $this->config[$section] ) ? $this->config[$section][$identifier] : false;
    }

    private function loadModuleConfig()
    {
        $moduleCfg = [];
        for ($i = 1; $i === 1 || $id && $breakpoints; $i++)
        {
            $id = $this->params->get( "module-id$i", false );
            $breakpoints = $this->params->get( "module-breakpoints$i", false );
            
            if ($id && $breakpoints)
            {
                $moduleCfg[$id] = array_values( (array) $breakpoints );
            }
            else break;
        }
        return $moduleCfg;
    }

    private function loadModulePosConfig()
    {
        $modulePosCfg = [];
        for ($i = 1; $i === 1 || $position && $breakpoints; $i++)
        {
            $position = $this->params->get( "modpos-position$i", false );
            $breakpoints = $this->params->get( "modpos-breakpoints$i", false );
            
            if ($position && $breakpoints)
            {
                $modulePosCfg[$position] = array_values( (array) $breakpoints );
            }
        }
        return $modulePosCfg;
    }

    private function loadSubformItems( $name )
    {
        $items = $this->params->get( $name, false );
        if ($items) 
        {
            $castToArray = function( $o ) { return (array) $o; };
            $items = array_map( $castToArray, array_values( (array) $items ) );
        }
        return $items ? $items : [];
    }
}
