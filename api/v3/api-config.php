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

    // AppGini application integration
    $dir = dirname(__FILE__);
    include "{$dir}/../lib.php";

    // Auth data
    $token = [
        "user" => "",
        "pass" => ""
    ];

?>