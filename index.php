<?php

    // Define a página como um JSON UTF-8
    header("Content-Type: application/json;charset=utf-8");

    // Classe da API
    class api{

        // PROPRIEDADES
        // Recebe os dados do Request
        private $tokens = array();
        private $request = array();

        // Tabelas possíveis de pesquisa
        private $base = array();

        // Resposta da aplicação
        public $report = array();
        public $meta = array();

        // MÉTODOS
        //
        // Cria os usuários e tokens para a resposta
        function __construct(){

            // Ligação com o Organizer
            $dir = dirname(__FILE__)."/..";
            include "$dir/../lib.php";

            // Resgata os usuários que podem realizar consultas com a API
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

            // Resgata as tabelas possíveis de consulta e suas colunas
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
                $tables[$res["tbl"]] = explode("|", $res["cols"]);
            }

            $this -> base = $tables;

            // Informa os meta dados da consulta
            $this -> meta = array(
                "ip" => $_SERVER['REMOTE_ADDR'],
                "timestamp" => date("Y-m-d H:i:s"),
            );
        }

        function __destruct(){
            unset($this -> base);
            unset($this -> request);

            echo json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        }

        // Valida se o usuário pode usar esta API
        function validateCredential($token){
            $user = strtolower(trim($token["user"]));
            $pass = $token["pass"];

            $userExists = array_key_exists($user, $this -> tokens);
            $correctCredentials = false;

            if($userExists) $correctCredentials = (password_verify($pass, $this -> tokens[$user]) || $this -> tokens[$user] == md5($pass));

            unset($this -> tokens);

            if($userExists && $correctCredentials) return true;

            $this -> setError("login-failed");
            return false;
        }

        // Recebe o request
        function getRequest($request){

            if(isset($request["tb"]) && empty($request["tb"])){
                $this -> setError("table-null");
                return false;
            }

            if(!isset($request["tb"])){
                $request["tb"] = "all";
            }

            // Parâmetros que podem ser arrays
            $params = array("search", "orderBy", "orderDir");

            foreach($params as $param){
                if(isset($request[$param])){
                    if($_SERVER["REQUEST_METHOD"] == "GET"){
                        $request[$param] =  (substr_count($request[$param], " ") ?
                                                explode(" ", $request[$param]) :
                                                explode("+", $request[$param])
                                            );
                    }

                    if(!is_array($request[$param])) $request[$param] = array($request[$param]);
                }
            }

            $this -> request = $request;
            return true;
        }

        // Define a resposta como erro
        function setError($type){

            $errors = [
                "login-failed" => "Autenticação falhou. Corrija os dados e tente novamente.",
                "table-failed" => "Esta tabela não existe.",
                "table-null" => "Nenhuma tabela para consulta foi informada.",
                "reg-null" => "Não existem registros para exibir nesta consulta.",
                "reg-failed" => "Parâmetros de consulta incorretos",
                "order-failed" => "Campo de ordenação incorreto.",
                "orderDir-failed" => "Direção de ordenação incorreta.",
                "orderCount-failed" => "A quantidade de direções de ordenação e de campos para ordenação deve ser a mesma.",
                "orderExists-failed" => "A quantidade de direções de ordenação e de campos para ordenação deve ser a mesma.",
                "limit-failed" => "Limitição de dados não permitida.",
                "page-failed" => "Valor para página não permitido.",
                "id-failed" => "Valor não permitido para ID.",
                "where-failed" => "Operação de busca não permitida.",
                "field-failed" => "Campo não existente."
            ];

            $this -> report = $errors[$type];
        }

        // Retorna se a tabela é válida
        function validTable(){
            return array_key_exists(strtolower(trim($this -> request["tb"])), $this -> base);
        }

        // Retorna se o campo designado existe na tabela
        function validField($field){
            $table = $this -> request["tb"];

            return (in_array(strtolower(trim($field)), $this -> base[$table]) ? true : false);
        }

        // Realiza a pesquisa e retorna a resposta
        function query(){
            // Verifica se a consulta tem uma tabela foco
            if($this -> validTable()){
                $table = strtolower(trim($this -> request["tb"]));

                $pkField = getPKFieldName($table);

                $sqlFields = (get_sql_fields($table) ? get_sql_fields($table) : "*");
                $sqlFrom = (get_sql_from($table, true) ? get_sql_from($table, true) : $table);
                $sqlFrom = str_replace(" WHERE 1=1", "", $sqlFrom);

                $sql = "SELECT {$sqlFields} FROM {$sqlFrom}";

                if(isset($this -> request["id"])){

                    $id = intval($this -> request["id"]);

                    if(!$id){
                        $this -> setError("id-failed");
                        return;
                    }

                    $sqlWhere = " AND {$table}.{$pkField} = '{$id}'";

                    $sql .= $sqlWhere;

                } else {

                    $limit = 30;
                    $offset = 1;

                    $sqlWhere = "";
                    $sqlOrderBy = (substr_count($table, "membership") ? "" :" ORDER BY {$table}.id");
                    $sqlOrderDir = (substr_count($table, "membership") ? "" : " DESC");
                    $sqlLimit = " LIMIT {$limit} OFFSET ";

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

                    // Verifica a se existe pedido de ordenação por campo
                    if(isset($this -> request["orderBy"])) {

                        foreach($this -> request["orderBy"] as $i => $orderBy){
                            if(!$this -> validField($orderBy)){
                                $this -> setError("order-failed");
                                return;
                            }

                            $this -> request["orderBy"][$i] = "{$table}.{$orderBy}";
                        }
                    }

                    // Verifica a se existe pedido de direção de ordenação
                    if(isset($this -> request["orderDir"])){

                        $countOrderDir = count($this -> request["orderDir"]);
                        $orderByExists = isset($this -> request["orderBy"]);

                        // Verifica se existem mais direções de ordenação do que campos para ordenar
                        if(!$orderByExists && $countOrderDir > 1){
                            $this -> setError("orderExists-failed");
                            return;
                        }

                        // Verifica se as ordenações podem ser aceitas
                        foreach($this -> request["orderDir"] as $orderDir){
                            $orderDir = strtolower(trim($orderDir));

                            if(!in_array($orderDir, array("asc", "desc"))){
                                $this -> setError("orderDir-failed");
                                return;
                            }
                        }

                        // Se existe somente direção de ordenação
                        if($countOrderDir == 1){
                            $sqlOrderDir = $this -> request["orderDir"][0];

                            // Se existem campos para ordenação
                            if($orderByExists) $sqlOrderBy = " ORDER BY ". implode(", ", $this -> request["orderBy"]);

                        // Senão
                        } else {
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

                    // Verifica a se existe pedido de uma página específica
                    if(isset($this -> request["page"])){
                        $page = intval($this -> request["page"]);

                        if(!$page){
                            $this -> setError("page-failed");
                            return;
                        }

                        $offset = $page;
                    }

                    // Verifica a se existe pedido de limitação/paginação
                    if(isset($this -> request["limit"])) {

                        $limit = intval($this -> request["limit"]);

                        // Se for requisitado um limite numérico
                        if($limit){
                            $offset = $limit * ($offset - 1);

                            $sqlLimit = " LIMIT {$limit} OFFSET {$offset}";

                        // Se forem requisitados todos os dados
                        } else if(strtolower(trim($this -> request["limit"])) == "all"){
                            $sqlLimit = "";
                        // Senão, retorna um erro
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

                    if(!$hasRegs) $this -> setError("reg-null");
                } catch(Throwable $t) {
                    $this -> setError("reg-failed");
                } catch(Exception $e){
                    $this -> setError("reg-failed");
                }

            // Senão, verifica se foi pedido as tabelas disponíveis
            } else if(strtolower(trim($this -> request["tb"])) == "all") {

                $base = $this -> base;

                $resp = [
                    "tabelas" => $this -> base
                ];

                $this -> report = $resp;

            // Senão, retorna um erro
            } else {
                $this -> setError("table-failed");
            }
        }
    }

    // Resgate dos dados de autenticação
    $token = [
        "user" => "",
        "pass" => ""
    ];

    if(array_key_exists("PHP_AUTH_USER", $_SERVER)) $token["user"] = $_SERVER["PHP_AUTH_USER"];
    if(array_key_exists("PHP_AUTH_PW", $_SERVER)) $token["pass"] = $_SERVER["PHP_AUTH_PW"];

    // Início da API
    // Valida se o dados de auth. estão corretos
    $api = new api($token);

    if($api -> validateCredential($token)){
        // Se sim, realiza o request
        // Senão, exibe o erro

        $data = ($_SERVER["REQUEST_METHOD"] == "GET" ? $_GET : json_decode(file_get_contents('php://input'), true));

        if($api -> getRequest($data)){
            $api -> query();
        }
    }

?>
