<?php

    /*  Created by: Rafael Carneiro de Moraes
     *  https://github.com/rafinhacarneiro/
     *  
     *  Updates data from table.
     */

    namespace v3\Classes;

    use v3\core\Core;
    
    class PATCH extends Core {

        // Retrieves the database from the app
        public function __construct($base){
            parent::__construct($base);
        }

        // Retrieves and validates the request
        public function getRequest($request){

            // Informs an error if no table was informed
            if(isset($request["update"]) && empty($request["update"])){
                $this -> setError("table-null");
                return false;
            }

            // Informs an error if the informed table doesn't exist
            if(!$this -> validTable($request["update"])){
                $this -> setError("table-failed");
                return false;
            }

            // Informs an error if no data was informed
            if(isset($request["info"]) && empty($request["info"])){
                $this -> setError("info-null");
                return false;
            }

            foreach($request["info"] as $field => $value){
                if(!$this -> validField($request["update"], $field)){
                    $this -> setError("field-failed");
                    return false;
                }
                
                $request["info"][$field] = makeSafe($value);
            }
            
            // Informs an error if no ID was informed
            if(isset($request["id"]) && empty($request["id"])){
                $this -> setError("id-null");
                return false;
            }

            $request["id"] = makeSafe($request["id"]);

            $this -> request = $request;
            return true;
        }

        // Called to update the data
        public function PATCH(){

            // If the informed ID doesn't exists, informs an error
            if(!$this -> exists()){
                $this -> setError("reg-inexistent");
                return false;
            }

            $this -> update();
        }

        // Updates data from the informed table
        private function update(){
            $table = strtolower(trim($this -> request["update"]));
            $pkField = getPKFieldName($table);
            $id = $this -> sqlMap($this -> request["id"]);

            $set = array();

            foreach($this -> request["info"] as $field => $value){
                $set[] = "{$field} = ". $this -> sqlMap($value);
            }

            $set = implode(", ", $set);
            
            $sql = "UPDATE {$table} SET {$set} WHERE {$pkField} = {$id}";

            try {
                
                sql($sql, $eo);

                $updated = $this -> read();

                if(!$updated){
                    $this -> setError("reg-null");

                    return false;
                }

                $this -> report = [
                    "success" => true,
                    "updatedID" => $updated
                ];

                return true;

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
