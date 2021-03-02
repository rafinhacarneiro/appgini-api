<?php

    /*  Created by: Rafael Carneiro de Moraes
     *  https://github.com/rafinhacarneiro/
     *  
     *  Creates a row in the table.
     */

    namespace v3\Classes;

    use v3\core\Core;
    
    class POST extends Core {

        // Retrieves the database from the app
        public function __construct($base){
            parent::__construct($base);
        }

        // Retrieves and validates the request
        public function getRequest($request){

            // Informs an error if no table was informed
            if(isset($request["create"]) && empty($request["create"])){
                $this -> setError("table-null");
                return false;
            }
            
            // Informs an error if the informed table doesn't exist
            if(!$this -> validTable($request["create"])){
                $this -> setError("table-failed");
                return false;
            }

            // Informs an error if no data was informed
            if(isset($request["info"]) && empty($request["info"])){
                $this -> setError("info-null");
                return false;
            }

            foreach($request["info"] as $field => $value){
                if(!$this -> validField($request["create"], $field)){
                    $this -> setError("field-failed");
                    return false;
                }

                $request["info"][$field] = makeSafe($value);
            }

            $this -> request = $request;
            return true;
        }

        // Called to create the data
        public function POST(){
            $table = mb_strtolower(trim($this -> request["create"]));

            // If the informed data already exists, informs the ID of the row
            $exists = $this -> read(3);
            if($exists){
                $this -> report = [
                    "success" => true,
                    "createdID" => $exists
                ];

                http_response_code(201);

                return true;
            }

            $fields = array_keys($this -> request["info"]);
            $values = array_map(array($this, "sqlMap"), $this -> request["info"]);

            $cols = implode(", ", $fields);
            $vals = implode(", ", $values);

            $sql = "INSERT INTO {$table} ({$cols}) VALUES ($vals)";

            try {

                sql($sql, $eo);

                $created = $this -> read(3);

                if(!$created){
                    $this -> setError("reg-null");

                    return false;
                }

                $this -> report = [
                    "success" => true,
                    "createdID" => $created
                ];

                http_response_code(201);

            // Catches query errors, informs nice errors
            } catch(Throwable $t) {
                $this -> setError("reg-failed");
                return false;
            } catch(Exception $e){
                $this -> setError("reg-failed");
                return false;
            }
        }
        
    }

?>
