<?php

    // Autoload function to include classes file
    spl_autoload_register(function($class_name){

        // Ignores AppGini related classes
        $appGiniClasses = array("RememberMe");

        if(!in_array($class_name, $appGiniClasses)){
            
            $fileName  = str_replace('v3\\', "", $class_name);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $fileName).".php";
            
            require $fileName;
        }
    });

?>