<?php

    /*  Created by: Rafael Carneiro de Moraes
     *  https://github.com/rafinhacarneiro/
     *  
     *  Updates data from table.
     */

    namespace v3\Classes;

    use v3\core\Core;
    
    class PUT extends Core {

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
        public function PUT(){

            // If the informed ID exists, updates the existent row
            if($this -> exists()){
                $this -> update();
            // Else, creates a new row with data
            } else{
                $this -> create();
            }

        }

        // Creates a new row
        private function create(){

            $table = mb_strtolower(trim($this -> request["update"]));

            // Hook - Before insert
            $hook = "{$table}_before_insert";
            $args = array();
                
            if(function_exists($hook)) $hook($this -> request["info"], $this -> user, $args);
            
            $fields = array_keys($this -> request["info"]);
            $values = array_map(array($this, "sqlMap"), $this -> request["info"]);

            $cols = implode(", ", $fields);
            $vals = implode(", ", $values);

            $sql = "INSERT INTO {$table} ({$cols}) VALUES ($vals)";

            try {
                
                sql($sql, $eo);

                $created = $this -> read();

                if(!$created){
                    $this -> setError("reg-null");

                    return false;
                }

                // Hook - After insert
                $hook = "{$table}_after_insert";

                $this -> request["info"]["selectedID"] = $created;
                
                if(function_exists($hook)) $hook($this -> request["info"], $this -> user, $args);

                http_response_code(201);

                $this -> report = [
                    "success" => true,
                    "createdID" => $created
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

        // Updates data from the informed table
        private function update(){
            $table = mb_strtolower(trim($this -> request["update"]));
            $pkField = getPKFieldName($table);
            $id = $this -> sqlMap($this -> request["id"]);

            // Old values
            $fields = implode(", ", $this -> base[$table]);
            $query = sql("SELECT {$fields} FROM {$table} WHERE {$pkField} = {$id}", $eo);
            $old = db_fetch_assoc($query);

            $this -> request["info"] = array_merge($old, $this -> request["info"]);

            // Hook - Before update
            $hook = "{$table}_before_update";
            $args = array();

            $this -> request["info"]["selectedID"] = $id;
            
            if(function_exists($hook)) $hook($this -> request["info"], $this -> user, $args);

            unset($this -> request["info"]["selectedID"]);

            // Update
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

                // Hook - After update
                $hook = "{$table}_after_update";
                
                $this -> request["info"]["selectedID"] = $id;
                
                if(function_exists($hook)) $hook($this -> request["info"], $this -> user, $args);

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
