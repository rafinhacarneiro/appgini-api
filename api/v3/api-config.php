<?php

    // Compatibility with PHP <= 5.4.0
    if( !function_exists('http_response_code') ){
        function http_response_code($newcode = NULL) {
            static $code = 200;
            if($newcode !== NULL){
                header('X-PHP-Response-Code: '.$newcode, true, $newcode);
                if(!headers_sent()) $code = $newcode;
            }
            return $code;
        }
    }

    // Defines a JSON UTF-8 response
    header("Content-Type: application/json;charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");

    // AppGini application integration
    $appGiniPath = $_SERVER['DOCUMENT_ROOT'];

    // Try to find lib.php in the root
    $possiblePath = glob( "{$appGiniPath}/lib.php" );

    if( !empty($possiblePath) ) {
        $rootFolder = $appGiniPath ."/";
        $appGiniPath .= "/lib.php";
    } else {

        // If nothing is found, search down the folders till the API folder
        $root = explode( "/", $appGiniPath );
        $file = explode( "/", str_replace( "\\", "/", __FILE__ ) );
        
        // Defines a list of parent folder to search
        $relPath = array_values( array_diff( $file, $root ) );
        $lastPathEl = count($relPath) - 1;
        unset($relPath[$lastPathEl]);

        $found = false;
        $i = 0;

        while( !$found ) {

            // If all the parent folders' search fails, forcibly breaks the loop
            if( !array_key_exists($i, $relPath ) ) break;

            $appGiniPath .= "/{$relPath[$i]}";

            $possiblePath = glob( "{$appGiniPath}/lib.php" );
            
            // If the lib.php file is found, saves it's path
            if( !empty($possiblePath) ) {
                $rootFolder = $appGiniPath ."/";
                $appGiniPath = $possiblePath[0];
                $found = true;
            } else {
                $i++;
            }
        }

        // If the loop was forcibly broken, returns an error
        if( !$found ){
            $report = array(
                "report" => array(
                    "error" => "AppGini not found. Please, reinstall the API",
                    "type"  => "appgini-failed"
                ),
                "meta" => array(
                    "remote-ip" => $_SERVER['REMOTE_ADDR'],
                    "timestamp" => time(),
                )
            );

            http_response_code(404);

            exit( json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) );
        }
    }
    
    include $appGiniPath;

    // Auth data
    $token = [
        "user" => "",
        "pass" => ""
    ];

?>