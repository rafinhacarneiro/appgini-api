<?php

    namespace v3;

    use v3\core\Credentials;

    // API
    $requestMethod = $_SERVER["REQUEST_METHOD"];

    $acceptedMethods = array("GET", "POST", "PUT", "PATCH", "DELETE");
    
    if(in_array($requestMethod, $acceptedMethods)){
        
        include "autoload.php";
        include "api-config.php";

        // Validates auth. data.
        // If it's ok, do the request
        // Else, informs an error
    
        // Verifies if a login was informed
        if(array_key_exists("PHP_AUTH_USER", $_SERVER)) $token["user"] = $_SERVER["PHP_AUTH_USER"];
        if(array_key_exists("PHP_AUTH_PW", $_SERVER)) $token["pass"] = $_SERVER["PHP_AUTH_PW"];
    
        $credentials = new Credentials();
        $validated = $credentials -> validateCredential($token);
    
        if(!$validated) die($credentials -> toJson());
    
        // Do the request
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        
        $data = ($requestMethod == "GET" ?
                    $_GET :
                    json_decode(file_get_contents('php://input'), true)
                );
    
        // CRUD
        // If no data was informed, show the database model
        if(empty($data)){
            $requestMethod = "Tables";
        }
    
        $class = "v3\\classes\\{$requestMethod}";
        
        $api = new $class($dbDatabase);
    
        if(@$api -> getRequest($data) || $tables) $api -> $requestMethod();
    
        if($api) echo $api -> toJson();
    }

?>
