<?php

    /*  Created by: Rafael Carneiro de Moraes
     *  https://github.com/rafinhacarneiro/
     *  
     *  Retrieves data from table.
     */

    namespace v3\Classes;

    use v3\core\Core;

    class GET extends Core {

        // Retrieves the database from the app
        public function __construct($base = null){
            parent::__construct($base);
        }

        // Retrieves and validates the request
        public function getRequest(Array $request){

            if(isset($request["read"]) && empty($request["read"])){
                $this -> setError("table-null");
                return false;
            }

            if(!isset($request["read"])) $request["read"] = "all";
            $request["read"] = trim(mb_strtolower($request["read"]));

            // Parameters that can be an array
            $params = array("search", "orderBy", "orderDir");

            foreach($params as $param){
                if(isset($request[$param])){
                    if(!is_array($request[$param])){
                        $request[$param] =  (substr_count($request[$param], " ") ?
                                                explode(" ", $request[$param]) :
                                                explode("+", $request[$param])
                                            );
                    }

                    $request[$param] = array_map("mb_strtolower", $request[$param]);
                }
            }

            $this -> request = $request;
            return true;
        }

        // Called to get the data
        public function GET(){

            $table = strtolower(trim($this -> request["read"]));

            $sqlFields = (get_sql_fields($table) ? get_sql_fields($table) : "*");
            $sqlFrom = (get_sql_from($table, true) ? get_sql_from($table, true) : $table);
            $sqlFrom = str_replace(" WHERE 1=1", "", $sqlFrom);

            $sql = "SELECT {$sqlFields} FROM {$sqlFrom}";

            if(isset($this -> request["id"])){

                $pkField = getPKFieldName($table);

                $id = intval($this -> request["id"]);

                if(!$id){
                    $this -> setError("id-failed");
                    return;
                }

                $sqlWhere = " AND {$table}.{$pkField} = {$id}";

                $sql .= $sqlWhere;

            } else{

                $limit = 30;
                $offset = 1;

                $sqlWhere = "";
                $sqlOrderBy = (substr_count($table, "membership") ? "" :" ORDER BY {$table}.id");
                $sqlOrderDir = (substr_count($table, "membership") ? "" : " DESC");
                $sqlLimit = " LIMIT {$limit} OFFSET ";

                if(isset($this -> request["search"])){

                    $sqlWhere = " WHERE";

                    $search = $this -> search($this -> request["search"]);

                    if(!$search) return;

                    $sqlWhere .= $search;
                }

                // Checks for field ordenation request
                if(isset($this -> request["orderBy"])) {

                    $orderBy = $this -> orderBy($this -> request["orderBy"]);

                    if(!$orderBy) return;

                    $this -> request["orderBy"] = $orderBy;

                }

                // Checks for ordenation direction request
                if(isset($this -> request["orderDir"])){

                    $countOrderDir = count($this -> request["orderDir"]);
                    $orderByExists = isset($this -> request["orderBy"]);

                    // Checks if theres more directions than fields to order
                    if(!$orderByExists && $countOrderDir > 1){
                        $this -> setError("orderExists-failed");
                        return;
                    }

                    // Checks for valid order directions
                    foreach($this -> request["orderDir"] as $orderDir){
                        $orderDir = strtolower(trim($orderDir));

                        if(!in_array($orderDir, array("asc", "desc"))){
                            $this -> setError("orderDir-failed");
                            return;
                        }
                    }

                    // If theres only one order direction informed, joins the order fields
                    if($countOrderDir == 1){
                        $sqlOrderDir = $this -> request["orderDir"][0];

                        // Se existem campos para ordenação
                        if($orderByExists) $sqlOrderBy = " ORDER BY ". implode(", ", $this -> request["orderBy"]);

                    // Else, pairs the fields with their respective direction
                    } else{
                        $countOrderBy = count($this -> request["orderBy"]);

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

                // Check for page request
                if(isset($this -> request["page"])){
                    $page = intval($this -> request["page"]);

                    if(!$page){
                        $this -> setError("page-failed");
                        return;
                    }

                    $offset = $page;
                }

                // Checks for limitation request
                if(isset($this -> request["limit"])) {

                    $limit = intval($this -> request["limit"]);

                    // If it's numeric, change the limit number
                    if($limit){
                        $offset = $limit * ($offset - 1);

                        $sqlLimit = " LIMIT {$limit} OFFSET {$offset}";

                    // Else, checks if it's query for all the data
                    } else if(strtolower(trim($this -> request["limit"])) == "all"){
                        $sqlLimit = "";
                    // Else, informs an error.
                    } else{
                        $this -> setError("limit-failed");
                        return;
                    }
                } else{
                    $offset = $limit * ($offset - 1);

                    $sqlLimit .= "{$offset}";
                }

                $sql .= $sqlWhere . $sqlOrderBy . $sqlLimit;
            }

            // Check for a existent table.
            // If true, do the query
            if($this -> validTable($table)){

                $query = sql($sql, $eo);

                $regs = array();
                $hasRegs = false;

                do{
                    if(!empty($row)){
                        $hasRegs = true;

                        $regs[] = $row;
                    }
                } while($row = db_fetch_assoc($query));

                $this -> report = $regs;

                if(!$hasRegs) $this -> setError("reg-null");

            // Else, informs an error
            } else{
                $this -> setError("table-failed");
            }

            return $this;
        }

        // Returns the "WHERE ..." part of the query for an advanced request
        private function search($searched){
            $sqlWhere = "";

            $table = strtolower(trim($this -> request["read"]));

            foreach($searched as $count => $search){
                $search = urldecode($search);

                preg_match("/(\w+)(\W+)(.*)/", $search, $matches);

                list($full, $field, $op, $value) = $matches;

                $field = makeSafe(trim(strtolower($field)));

                // If the field isn't valid, informs an error
                if(!$this -> validField($table, $field)){
                    $this -> setError("field-failed");
                    return false;
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
                        $values = implode(", ", array_map(array($this, "sqlMap"), explode("|", $value)));
                        $sqlWhere .= " {$table}.{$field} IN ({$values})"; break;
                    case "!@":
                        $values = implode(", ", array_map(array($this, "sqlMap"), explode("|", $value)));
                        $sqlWhere .= " {$table}.{$field} NOT IN ({$values})"; break;
                    default:
                        $this -> setError("where-failed");
                        return false;
                }
            }

            return $sqlWhere;
        }

        // Returns the order fields for a query
        private function orderBy($orders){
            $table = strtolower(trim($this -> request["read"]));
            foreach($orders as $i => $orderBy){
                
                // If the field isn't valid, informs an error
                if(!$this -> validField($table, $orderBy)){
                    $this -> setError("order-failed");
                    return false;
                }

                $orders[$i] = "{$this -> request["read"]}.{$orderBy}";
            }

            return $orders;
        }

    }

?>
