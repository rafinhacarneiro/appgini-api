<?php

    // Defines the JSON UTF-8 response format
    header("Content-Type: application/json;charset=utf-8");

    class api{

        /* -------- PROPERTIES -------- */
        
        // Admin user data
        private $tokens = array();
        // Request data
        private $request = array();

        // Database mirror
        private $base = array();

        // API response
        public $report = array();
        public $meta = array();

        /* -------- METHODS -------- */
        
        function __construct(){

            // AppGini integration
            include dirname(__FILE__)."/../lib.php";

            // Fetches the Admins' info
            $sql = "SELECT memberID, passMD5
                    FROM membership_users
                    WHERE groupID = 2";

            $query = sql($sql, $eo);

            $users = array();

            while($res = db_fetch_assoc($query)){
                $memberID = strtolower($res["memberID"]);
                $passMD5 = $res["passMD5"];

                $users[$memberID] = $passMD5;
            }

            $this -> tokens = $users;

            // Fetches an app's database mirror
            $sql = "SELECT
                        t.TABLE_NAME AS tbl,
                        GROUP_CONCAT(DISTINCT REPLACE(c.COLUMN_NAME, '?=', '') SEPARATOR '|') AS cols
                    FROM INFORMATION_SCHEMA.TABLES t
                    INNER JOIN INFORMATION_SCHEMA.COLUMNS c
                        ON c.TABLE_NAME = t.TABLE_NAME
                    WHERE
                        t.table_schema = '{$dbDatabase}' AND
                        c.COLUMN_NAME NOT LIKE 'field%'
                    GROUP BY t.TABLE_NAME
                    ORDER BY t.TABLE_NAME ASC";

            $query = sql($sql, $eo);

            $tables = array();

            while($res = db_fetch_assoc($query)){
                $res = array_map("mb_strtolower", $res);

                $tables[$res["tbl"]] = explode("|", $res["cols"]);
            }

            $this -> base = $tables;

            // Sets meta data to the response
            $this -> meta = array(
                "ip" => $_SERVER['REMOTE_ADDR'],
                "timestamp" => date("Y-m-d H:i:s"),
            );
        }

        // Prints the JSON reponse
        function __destruct(){
            echo json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        }

        // Validates the user's authentication
        function validateCredential($token){
            $user = strtolower(trim($token["user"]));
            $pass = $token["pass"];

            // Checks if the given username is valid
            $userExists = array_key_exists($user, $this -> tokens);
            $correctCredentials = false;

            // Checks if the given password matches the user's password
            if($userExists) $correctCredentials = (password_verify($pass, $this -> tokens[$user]) || $this -> tokens[$user] == md5($pass));

            // Unsets the Admins' info
            unset($this -> tokens);

            if($userExists && $correctCredentials) return true;

            $this -> setError("login-failed");
            return false;
        }

        // Recieves the request data
        function getRequest($request){
            // Checks if the parameter is set but empty
            if(isset($request["tb"]) && empty($request["tb"])){
                $this -> setError("table-null");
                return false;
            }

            // If the parameter is not set, defines "all", so it can search for the database mirror
            if(!isset($request["tb"])){
                $request["tb"] = "all";
            }

            $request["tb"] = trim(mb_strtolower($request["tb"]));

            // This parameters can be an array of values
            $params = array("search", "orderBy", "orderDir");

            foreach($params as $param){
                if(isset($request[$param])){
                    // Turns GET data into an array
                    if($_SERVER["REQUEST_METHOD"] == "GET"){
                        $request[$param] =  (substr_count($request[$param], " ") ?
                                                explode(" ", $request[$param]) :
                                                explode("+", $request[$param])
                                            );
                    }
                    // Turns POST data single values into an array
                    if(!is_array($request[$param])) $request[$param] = array($request[$param]);

                    $request[$param] = array_map("mb_strtolower", $request[$param]);
                }
            }

            $this -> request = $request;
            return true;
        }

        // Error reporting method
        function setError($type){

            $errors = [
                "login-failed" => "Authentication failed",
                "table-failed" => "Nonexistent table",
                "table-null" => "Table not informed",
                "reg-null" => "There's no returned data to this query",
                "reg-failed" => "Incorrect query parameters",
                "order-failed" => "Incorrect Order field",
                "orderDir-failed" => "Incorrect Order direction",
                "orderCount-failed" => "The quantity of fields to order and order directions should be the same for 2 fields or more",
                "orderExists-failed" => "The quantity of fields to order and order directions should be the same for 2 fields or more",
                "limit-failed" => "Limit value prohibited",
                "page-failed" => "Page value prohibited",
                "id-failed" => "ID value prohibited",
                "where-failed" => "Search operator prohibited",
                "field-failed" => "Nonexistent field"
            ];

            $this -> report = array("error" => array($type => $errors[$type]));
        }

        // Checks if the informed table is valid
        function validTable(){
            return array_key_exists($this -> request["tb"], $this -> base);
        }

        // Checks if the informed table's field is valid
        function validField($field){
            $table = $this -> request["tb"];
            $field = strtolower(trim($field));

            return in_array($field, $this -> base[$table]);
        }

        function query(){
            
            // Checks if the table value is set
            if($this -> validTable()){
                $table = strtolower(trim($this -> request["tb"]));

                $pkField = getPKFieldName($table);

                $sqlFields = (get_sql_fields($table) ? get_sql_fields($table) : "*");
                $sqlFrom = (get_sql_from($table, true) ? get_sql_from($table, true) : $table);
                $sqlFrom = str_replace(" WHERE 1=1", "", $sqlFrom);

                $sql = "SELECT {$sqlFields} FROM {$sqlFrom}";

                // Checks if a single row is requested
                if(isset($this -> request["id"])){

                    $id = intval($this -> request["id"]);

                    if(!$id){
                        $this -> setError("id-failed");
                        return;
                    }

                    $sqlWhere = " WHERE {$table}.{$pkField} = '{$id}'";

                    $sql .= $sqlWhere;
                
                // If false, creates a query
                } else {

                    $limit = 30;
                    $offset = 1;

                    $sqlWhere = "";
                    $sqlOrderBy = (substr_count($table, "membership") ? "" :" ORDER BY {$table}.id");
                    $sqlOrderDir = (substr_count($table, "membership") ? "" : " DESC");
                    $sqlLimit = " LIMIT {$limit} OFFSET ";

                    // Validates and adds the searched info to the query
                    if(isset($this -> request["search"])){

                        $sqlWhere = " WHERE";

                        foreach($this -> request["search"] as $count => $search){
                            $search = urldecode($search);

                            preg_match("/(\w+)(\W+)(.*)/", $search, $matches);

                            list($full, $field, $op, $value) = $matches;

                            $field = makeSafe(trim(strtolower($field)));

                            if(!$this -> validField($field)){
                                $this -> setError("field-failed");
                                return;
                            }

                            $value = makeSafe(trim($value));

                            if($count > 0) $sqlWhere .= " AND";

                            switch(trim($op)){
                                case ":": $sqlWhere .= " {$table}.{$field} = '{$value}'"; break;
                                case "!:": $sqlWhere .= " {$table}.{$field} <> '{$value}'"; break;
                                case "*": $sqlWhere .= " {$table}.{$field} IS NULL"; break;
                                case "!*": $sqlWhere .= " {$table}.{$field} IS NOT NULL"; break;
                                case "::": $sqlWhere .= " {$table}.{$field} LIKE '%{$value}%'"; break;
                                case "!::": $sqlWhere .= " {$table}.{$field} NOT LIKE '%{$value}%'"; break;
                                case ">": $sqlWhere .= " {$table}.{$field} > '{$value}'"; break;
                                case ">:": $sqlWhere .= " {$table}.{$field} >= '{$value}'"; break;
                                case "<": $sqlWhere .= " {$table}.{$field} < '{$value}'"; break;
                                case "<:": $sqlWhere .= " {$table}.{$field} <= '{$value}'"; break;
                                case "><":
                                    list($value1, $value2) = explode("|", $value);
                                    $sqlWhere .= " {$table}.{$field} > '{$value1}' AND {$table}.{$field} < '{$value2}'"; break;
                                case ":><":
                                    list($value1, $value2) = explode("|", $value);
                                    $sqlWhere .= " {$table}.{$field} >= '{$value1}' AND {$table}.{$field} < '{$value2}'"; break;
                                case "><:":
                                    list($value1, $value2) = explode("|", $value);
                                    $sqlWhere .= " {$table}.{$field} > '{$value1}' AND {$table}.{$field} <= '{$value2}'"; break;
                                case ":><:":
                                    list($value1, $value2) = explode("|", $value);
                                    $sqlWhere .= " {$table}.{$field} >= '{$value1}' AND {$table}.{$field} <= '{$value2}'"; break;
                                case "@":
                                    $values = implode(", ", array_trim(explode("|", $value)));
                                    $sqlWhere .= " {$table}.{$field} IN ({$values})"; break;
                                case "!@":
                                    $values = implode(", ", array_trim(explode("|", $value)));
                                    $sqlWhere .= " {$table}.{$field} NOT IN ({$values})"; break;
                                default:
                                    $this -> setError("where-failed");
                                    return;
                            }
                        }
                    }

                    // Validates and adds an ordenation to the query
                    if(isset($this -> request["orderBy"])) {

                        foreach($this -> request["orderBy"] as $i => $orderBy){
                            if(!$this -> validField($orderBy)){
                                $this -> setError("order-failed");
                                return;
                            }

                            $this -> request["orderBy"][$i] = "{$table}.{$orderBy}";
                        }
                    }

                    // Validates and adds an ordenation direction to the query
                    if(isset($this -> request["orderDir"])){

                        $countOrderDir = count($this -> request["orderDir"]);
                        $orderByExists = isset($this -> request["orderBy"]);

                        // Checks if there is more than 1 ordenation direction but no ordenation fields
                        if(!$orderByExists && $countOrderDir > 1){
                            $this -> setError("orderExists-failed");
                            return;
                        }

                        // Checks if there the ordernation direction are asc/desc only
                        foreach($this -> request["orderDir"] as $orderDir){
                            $orderDir = strtolower(trim($orderDir));

                            if(!in_array($orderDir, array("asc", "desc"))){
                                $this -> setError("orderDir-failed");
                                return;
                            }
                        }

                        if($countOrderDir == 1){
                            $sqlOrderDir = $this -> request["orderDir"][0];

                            if($orderByExists) $sqlOrderBy = " ORDER BY ". implode(", ", $this -> request["orderBy"]);

                        } else {
                            $countOrderBy = count($this -> request["orderBy"]);

                            // Checks if the quantity of ordenation fields and ordenation directions are the same
                            if($countOrderBy != $countOrderDir){
                                $this -> setError("orderCount-failed");
                                return;
                            }

                            foreach($this -> request["orderBy"] as $i => $orderBy){
                                $this -> request["orderBy"][$i] = "{$orderBy} {$this -> request["orderDir"][$i]}";
                            }

                            $sqlOrderBy = " ORDER BY ". implode(", ", $this -> request["orderBy"]);
                            $sqlOrderDir = "";
                        }
                    }

                    $sqlOrderBy .= " ". $sqlOrderDir;

                    // Checks if an especific page was requested
                    if(isset($this -> request["page"])){
                        $page = intval($this -> request["page"]);

                        if(!$page){
                            $this -> setError("page-failed");
                            return;
                        }

                        $offset = $page;
                    }

                    // Checks if limit of rows was altered
                    if(isset($this -> request["limit"])) {

                        $limit = intval($this -> request["limit"]);

                        // If the limit value is numeric, changes the limit value
                        if($limit){
                            $offset = $limit * ($offset - 1);

                            $sqlLimit = " LIMIT {$limit} OFFSET {$offset}";

                        // If it's not numeric, but it's value is "all", remove limitation from the query
                        } else if(strtolower(trim($this -> request["limit"])) == "all"){
                            $sqlLimit = "";
                        // Else, returns an error
                        } else{
                            $this -> setError("limit-failed");
                            return;
                        }
                    } else {
                        $offset = $limit * ($offset - 1);

                        $sqlLimit .= "{$offset}";
                    }

                    $sql .= $sqlWhere . $sqlOrderBy . $sqlLimit;
                }

                // Try to do the query
                try {
                    $query = sql($sql, $eo);
                    $regs = array();
                    $hasRegs = false;

                    do {
                        if(!empty($row)){
                            $hasRegs = true;

                            $regs[] = $row;
                        }
                    } while($row = db_fetch_assoc($query));

                    $this -> report = $regs;

                    // If there's no return data, informs an error
                    if(!$hasRegs) $this -> setError("reg-null");
                // Catch possible query errors
                } catch(Throwable $t) { // PHP 7
                    $this -> setError("reg-failed");
                } catch(Exception $e){  // PHP 5.6
                    $this -> setError("reg-failed");
                }

            // Checks if the database mirror were requested
            } else if(strtolower(trim($this -> request["tb"])) == "all") {

                $base = $this -> base;

                $resp = [
                    "tabelas" => $this -> base
                ];

                $this -> report = $resp;

            // The table does not exists in the database
            } else {
                $this -> setError("table-failed");
            }
        }
    }

    /* ---------- API Usage ---------- */

    // User informed values for username and password
    $token = [
        "user" => "",
        "pass" => ""
    ];

    if(array_key_exists("PHP_AUTH_USER", $_SERVER)) $token["user"] = $_SERVER["PHP_AUTH_USER"];
    if(array_key_exists("PHP_AUTH_PW", $_SERVER)) $token["pass"] = $_SERVER["PHP_AUTH_PW"];

    // Initiates the API
    $api = new api();

    // If the token was validated, continue.
    // Else, returns and error
    if($api -> validateCredential($token)){
        $data = ($_SERVER["REQUEST_METHOD"] == "GET" ?                      // Was it a GET request?
                    $_GET :                                                 // If true, use the $_GET array
                    json_decode(file_get_contents('php://input'), true)     // Else, use the JSON from POST
                );

        // Checks if the request has errors
        if($api -> getRequest($data)){
            // If true, do the query
            // Else, informs an error
            $api -> query();
        }
    }

?>
