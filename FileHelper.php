<?php

/**
 * Helper class to work with files, directories and paths.
 */
class FileHelper
{
    /**
     * Checks whether a string is an URL, in contrast to a path.
     * This function includes no validity checks.
     * 
     * @param string $url potential URL
     * @return bool true if the given URL is an URL indeed, false otherwise
     */
    public static function isUrl( $url )
    {
        return preg_match( '/^https?:\/\//i', $url ) !== 0;
    }

    /**
     * Checks whether an URL is external or not.
     * The URL is considered as external if its domain doesn't exactly match the server's HTTP host.
     * 
     * @param string $url URL
     * @return bool true if the URL is external, false otherwise
     */
    public static function isExternalUrl( $url )
    {
        return self::isUrl( $url ) && preg_match( '/^https?:\/\/' . preg_quote( $_SERVER['HTTP_HOST'], '/' ) . '/i', $url ) !== 1;
    }

    /**
     * Retrieves the path part of an URL.
     * 
     * @param string $url URL
     * @return string absolute path that the URL points at
     */
    public static function getPathFromUrl( $url )
    {
        return parse_url( $url, PHP_URL_PATH );
    }

    /**
     * Checks whether a path is absolute or relative.
     * 
     * @param string $path path
     * @return bool true if the path is absolute, false if it's relative
     */
    public static function isAbsolutePath( $path )
    {
        return substr( $path, 0, 1 ) === '/';
    }

    /**
     * Retrieves the relative version of a path.
     * 
     * @param string absolute or relative path
     * @return string relative path
     */
    public static function getRelativePath( $path )
    {
        return !self::isAbsolutePath( $path ) ? $path : substr( $path, strlen( JURI::base( true ) ) + 1 );
    }

    /**
     * Gets the relative path of a path or URL pointing at a file on the current server.
     * 
     * @param string $path path or URL pointing at a local file
     * @return string relative path pointing at the same file as the given path or URL
     */
    public static function getLocalPath( $path )
    {
        return self::getRelativePath( self::isUrl( $path ) ? self::getPathFromUrl( $path ) : $path );
    }

    /**
     * Builds the relative path for an external URL, including its domain and query.
     * 
     * @param string $url external URL
     * @return string relative path representing the full URL
     */
    public static function buildRelativePathFromUrl( $url )
    {
        // parse URL to get domain and path
        $parts = parse_url( $url );
        if (!$parts) return false;

        return $parts['host'] . $parts['path'] . '/' . sha1( $parts['query'] . '#' . $parts['fragment'] );
    }

    /**
     * Downloads a remote file to this server.
     * 
     * @param string $url URL to the remote file
     * @param string $path path to the local destination file
     * @return int|bool number of bytes written, or false on failure
     */
    public static function downloadFile( $url, $path )
    {
        return file_put_contents( $path, fopen( $url, 'r' ) );
    }

    /**
     * Checks whether a path lies within a certain directory.
     * The given path will be expanded to its real path before the test.
     * 
     * @param string $path path to be checked
     * @param string $directory directory that the given that should lie in
     * @return bool true if the given path lies within the given directory, false otherwise
     */
    public static function isPathWithin( $path, $directory )
    {
        // ensure single trailing slash
        $directory = rtrim( $directory, '/' ) . '/';
        return substr( realpath( $path ), 0, strlen( $directory ) ) === $directory;
    }
}
