<?php

    /*  Created by: Rafael Carneiro de Moraes
     *  https://github.com/rafinhacarneiro/
     *  
     *  Deletes data based on informed ID.
     */

    namespace v3\Classes;

    use v3\core\Core;
    
    class DELETE extends Core {

        // Retrieves the database from the app
        public function __construct($base){
            parent::__construct($base);
        }

        // Retrieves and validates the request
        public function getRequest($request){

            // Informs an error if no table was informed
            if(isset($request["delete"]) && empty($request["delete"])){
                $this -> setError("table-null");
                return false;
            }
            
            // Informs an error if the informed table doesn't exist
            if(!$this -> validTable($request["delete"])){
                $this -> setError("table-failed");
                return false;
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

        // Called to delete data
        public function DELETE(){

            // If the informed ID doesn't exists, informs an error
            if(!$this -> exists(2)){
                $this -> setError("reg-inexistent");
                return false;
            }

            // Tries to delete the data
            $this -> del();
        }

        // Delete data from informed table
        private function del(){

            $table = mb_strtolower(trim($this -> request["delete"]));
            $pkField = getPKFieldName($table);
            $id = $this -> sqlMap($this -> request["id"]);

            // Hook - Before delete
            $hook = "{$table}_before_delete";
            $args = array();
            $skip = false;

            if(function_exists($hook)) $hook($id, $skip, $this -> user, $args);

            // Delete
            $sql = "DELETE FROM {$table} WHERE {$pkField} = {$id}";

            try {
                
                sql($sql, $eo);

                $deleted = (!$this -> exists(2));

                // If still exists, informs an error
                if(!$deleted){
                    $this -> setError("delete-failed");
                    return false;
                }

                // Hook - After delete
                $hook = "{$table}_after_delete";

                if(function_exists($hook)) $hook($id, $this -> user, $args);
                
                $this -> report = [
                    "success" => true
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
