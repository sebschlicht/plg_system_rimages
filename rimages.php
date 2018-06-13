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

        // load cascaded configuration
        $configuration = $this->params->get( self::CFG_CONTENT, false );
        $configuration = $configuration ? $configuration : $this->params->get( self::CFG_GLOBAL, false );

        // TODO debug
        if (!$configuration) $configuration = [ 320 ];

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
            // configuration: module
            $configuration = $this->params->get( self::CFG_MODULE, false );
            if ($configuration) $configuration = $configuration[$module->id];
            // configuration: module position
            if (!$configuration)
            {
                $configuration = $this->params->get( self::CFG_MODULE_POSITION, false );
                if ($configuration) $configuration = $configuration[$module->position];
            }
            // configuration: global
            if (!$configuration) $configuration = $this->params->get( self::CFG_GLOBAL, false );

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
            $configuration = $this->params->get( self::CFG_GLOBAL, false );
            JResponse::setBody( $this->processHtml( JResponse::getBody(), $configuration ) );
        }
    }
    
    /**
     * Process a pice of HTML markup code and returns the processed version.
     * Processing, in this context, means to wrap images with a picture tag and add alternative version according to the configuration.
     * 
     * @param string $html HTML code to process
     * @param object $configuration configuration object
     * @return string passed HTML code with responsive images
     */
    private function processHtml( $html, $configuration )
    {
        echo 'processHtml( ' . json_encode( $configuration ) . '  )<br>';
        // don't process HTML without a configuation
        if (!$configuration || !is_array($configuration)) return $html;

        // find all images outside of picture tags
        $regexImages = '/<picture>.*?<\/picture>(*SKIP)(*FAIL)|<img.*?src=[\"\']?([^\"\'\s]*)[\"\']?.*?>/';
        $matches = [];
        preg_match_all($regexImages, $html, $matches, PREG_SET_ORDER);

        // loop detected images
        foreach ($matches as $match)
        {
            // check if image source path or URL
            $src = $match[1];
            $regexUrl = '/^https?:/i';
            if ( preg_match( $regexUrl, $src ) === 0 )
            {
                $path = pathinfo( $src );
                $directory = $path['dirname'];
                $filename = $path['filename'];
            }
            else
            {
                // TODO convert URL to path if pointing at this site
                // TODO? option to convert and store external images in a local folder
            }

            // loop configured max-widths
            $imageVersions = [];
            foreach ($configuration as $widthName)
            {
                // responsive version is like the original one but suffixed with the target width
                $width = $this->translateWidthName( $widthName );
                $srcResponsive = $this->buildFilePath( $directory, "{$filename}_$widthName.jpg" );
                // TODO support other image formats

                // generate the file if not available
                if (!is_file( $srcResponsive ))
                {
                    // TODO not implemented
                }
                
                array_push( $imageVersions, "<source media='(max-width: $width" . "px)' srcset='$srcResponsive' data-rimages-w='$widthName'>" );
            }
            
            // add original version
            array_push( $imageVersions, $match[0] );
            
            // create and inject a picture tag with all versions
            $pictureTag = '<picture>' . implode( '', $imageVersions ) . '</picture';
            $html = preg_replace( $regexImages, $pictureTag, $html, 1 );
        }
        return $html;
    }

    private function buildFilePath( $directory, $basename )
    {
        // if directory empty or a path: prefix with Joomla! site directory
        if (!$directory || substr( $directory, 0, 4 ) !== 'http')
        {
            $directory = JUri::base( true ) . DIRECTORY_SEPARATOR . $directory;
        }
        return $directory . DIRECTORY_SEPARATOR . $basename;
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
}
