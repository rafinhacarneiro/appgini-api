<?php

    /*  Created by: Rafael Carneiro de Moraes
     *  https://github.com/rafinhacarneiro/
     *  
     *  Core functionality
     */

    namespace v3\core;

    class Core {

        /* --------------- Common Properties --------------- */
        // Database model
        protected $base = array();
        protected $user = array();

        // Request data
        protected $request = array();

        // Response data
        public $report = array();
        public $extra = array();
        public $meta = array();

        /*
         * Construct
         * Cria os usuários e tokens para a resposta.
         * Resgata as tabelas e campos da base de dados
         */
        public function __construct($user, $base = null){

            // Regsta o usuário que realizou a requisição
            $this -> user = getMemberInfo($user);
            // Resgata qual base de dados a API está usando
            $this -> getBase($base);
        }

        /* --------------- Common Methods --------------- */

        // Retrieves the database model
        // @param: $base    String  The database name.
        protected function getBase($base){
            $sql = "SELECT
                        LOWER(t.TABLE_NAME) AS tbls,
                        GROUP_CONCAT(DISTINCT
                                     REPLACE(LOWER(c.COLUMN_NAME), '?=', '')
                                     ORDER BY c.COLUMN_NAME ASC
                                     SEPARATOR '|') AS cols
                    FROM INFORMATION_SCHEMA.TABLES t
                    INNER JOIN INFORMATION_SCHEMA.COLUMNS c
                        ON c.TABLE_NAME = t.TABLE_NAME
                    WHERE
                        t.TABLE_SCHEMA = '{$base}' AND
                        c.COLUMN_NAME NOT LIKE 'field%'
                    GROUP BY t.TABLE_NAME
                    ORDER BY t.TABLE_NAME ASC";

            $query = sql($sql, $eo);

            $tables = array();

            while($res = db_fetch_assoc($query)){
                $res = array_map("mb_strtolower", $res);
                
                $tables[$res["tbls"]] = explode("|", $res["cols"]);
            }

            $this -> base = $tables;
        }

        // Returns data involved in simple quotation marks
        // @params  $value      Any value.
        // @return  The value with simple quotation marks arround it.
        protected function sqlMap($value){

            if(is_numeric($value)) $value = (is_int($value) ? intval($value) : floatval($value));

            switch(gettype($value)){
                case "integer": return $value; break;
                case "double":  return $value; break;
                case "boolean": return ($value && $value ? 1 : 0); break;
                default: return (strtolower(trim($value)) == "null" ? "NULL" : "'{$value}'");
            }
        }

        // Defines the response as an error
        public function setError(String $type){

            $errors = [
                "order-failed"          => "ORDER BY field(s) incorrect.",
                "orderCount-failed"     => "The quantity of ORDER BY direction(s) should be 1 or the same of ORDER BY fields",
                "orderDir-failed"       => "ORDER BY direction incorrect.",
                "orderExists-failed"    => "The quantity of ORDER BY direction(s) should be 1 or the same of ORDER BY fields",
                "login-failed"          => "Authentication failed. Try again.",
                "table-failed"          => "Inexistent table.",
                "table-null"            => "No table informed.",
                "reg-null"              => "No data to show in this query.",
                "limit-failed"          => "LIMIT value prohibited.",
                "page-failed"           => "Page value prohibited.",
                "id-failed"             => "ID value prohibited.",
                "where-failed"          => "Search operation prohibited.",
                "field-failed"          => "Inexistent field.",
                "info-null"             => "No data informed on the request.",
                "reg-failed"            => "Couldn't create a new row. Correct the data and try again.",
                "id-null"               => "No ID was informed.",
                "reg-inexistent"        => "There's no row related to given ID.",
                "delete-failed"         => "Delete failed. Correct the data and try again."
            ];

            $errorCodes = [
                "field-failed"          => 400,
                "id-failed"             => 400,
                "id-null"               => 400,
                "info-null"             => 400,
                "order-failed"          => 400,
                "orderCount-failed"     => 400,
                "orderDir-failed"       => 400,
                "orderExists-failed"    => 400,
                "limit-failed"          => 400,
                "page-failed"           => 400,
                "reg-failed"            => 400,
                "rqmet-failed"          => 400,
                "table-failed"          => 400,
                "table-null"            => 400,
                "where-failed"          => 400,
                "login-failed"          => 401,
                "reg-null"              => 404,
                "reg-inexistent"        => 400,
                "delete-failed"         => 400
            ];

            http_response_code($errorCodes[$type]);

            $this -> report = array("error" => $errors[$type], "type" => $type);
        }
        
        // Checks if the row already exists.
        // @params  $type  Integer  The CRUD operation
        // @return  true if exists, false if not.
        protected function exists($type = 1){

            $requestMode = [
                1 => "update",
                2 => "delete",
                3 => "create"
            ];

            $table = strtolower(trim($this -> request[$requestMode[$type]]));
            $pkField = getPKFieldName($table);

            $sql = "SELECT COUNT({$pkField}) FROM {$table}";

            if($type != 3){
                $id = $this -> sqlMap($this -> request["id"]);

                $sql .= " WHERE {$pkField} = {$id}";
            } else{
                $search = array();

                foreach($this -> request["info"] as $field => $value){
                    $search[] = "{$field} = ".$this -> sqlMap($value);
                }

                $sql .= " WHERE ".implode(" AND ", $search);
            }

            $sql .= " ORDER BY {$pkField} ASC LIMIT 1";

            return intval(sqlValue($sql));
        }
        
        // Returns the ID of one row
        // @params  $type  Integer  The CRUD operation
        // @return  numeric ID if exists, 0 (false) if not.
        protected function read($type = 1){
            $requestMode = [
                1 => "update",
                2 => "delete",
                3 => "create"
            ];

            $table = strtolower(trim($this -> request[$requestMode[$type]]));
            $comb = array();
            
            foreach($this -> request["info"] as $field => $value){
                $comb[] = "{$field} = ". $this -> sqlMap($value);
            }

            $pkField = getPKFieldName($table);
            
            $sql = "SELECT {$pkField} FROM {$table} WHERE ". implode(" AND ", $comb) ." ORDER BY {$pkField} ASC LIMIT 1";

            return intval(sqlValue($sql));
        }

        // Returns if the informed table exists on the database model
        // @params  $table  String  A possible table.
        // @return  true if exists, false if not.
        public function validTable(String $table){
            $table = mb_strtolower(trim($table));
            return array_key_exists($table, $this -> base);
        }

        // Returns if the informed field exists on the informed table
        // @params  $table  String  A possible table.
        // @params  $field  String  A possible field.
        // @return  true if exists, false if not.
        public function validField(String $table, String $field){
            $table = mb_strtolower(trim($table));
            $field = mb_strtolower(trim($field));
            return in_array($field, $this -> base[$table]);
        }

        // Includes the table hooks file
        public function includeHooksFile($requestMethod, $path = "../../"){

            $type = array(
                "POST"   => "create",
                "GET"    => "read",
                "PATCH"  => "update",
                "PUT"    => "update",
                "DELETE" => "delete"
            );

            $table = $this -> request[$type[$requestMethod]];

            $dir = $path . "hooks";

            $file = "{$dir}/{$table}.php";

            if(file_exists($file)) include $file;

        }

        // Defines the response format
        public function getFormat(){
            $format = $this -> request["format"];

            if(!isset($this -> request["format"])) $format = "json";

            return $format;
        }

        // Returns an JSON enconded response.
        // @return  String with the response as JSON
        public function toJson(){
            
            // Deletes the "extra" propertie if empty
            if(empty($this -> extra)) unset($this -> extra);

            // Set metadata of the request
            $this -> meta = array(
                "remote-ip" => $_SERVER['REMOTE_ADDR'],
                "timestamp" => time(),
            );

            // Defines a JSON output
            header("Content-Type: application/json;charset=utf-8");

            return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        }

        // Returns an CSV file response.
        // @return  String with the response as CSV
        public function toCSV(){

            // Set metadata of the request
            $time = time();
            $remote_ip = ip2long($_SERVER['REMOTE_ADDR']);

            // Build the CSV response
            $first = true;
            $header = array();
            $csv = "";

            foreach($this -> report as $row){
                if($first) $header = array_keys($row);

                $csv .= "\n".implode(";", $row);
            }

            $csv = implode(";", $header) . $csv;

            // Defines a CSV file output
            header("Content-type: text/csv;charset=utf-8");
            header("Content-Disposition: attachment; filename={$remote_ip}.{$time}.csv");
            
            return $csv;
        }

    }
