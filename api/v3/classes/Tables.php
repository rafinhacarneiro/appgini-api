<?php

    /*  Created by: Rafael Carneiro de Moraes
     *  https://github.com/rafinhacarneiro/
     *  
     *  Updates data from table.
     */

    namespace v3\Classes;

    use v3\core\Core;

    class Tables extends Core {

        // Retrieves the database from the app
        public function __construct($base = null){
            parent::__construct($base);
        }

        // Retrieves and validates the request
        public function getRequest(){
            return true;
        }

        // Returns the database model
        public function Tables(){

            $base = $this -> base;

            $resp = [
                "tabelas" => $this -> base
            ];

            $this -> report = $resp;
        }
    }

?>
