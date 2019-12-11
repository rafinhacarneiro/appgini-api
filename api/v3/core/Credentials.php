<?php

    /*  Created by: Rafael Carneiro de Moraes
     *  https://github.com/rafinhacarneiro/
     *  
     *  Checks credentials.
     */

    namespace v3\core;

    class Credentials {

        /* --------------- Properties --------------- */
        // Users
        private $tokens = array();

        // Retrieves the Admin users and their password hash
        public function __construct(){
            $sql = "SELECT memberID, passMD5
                    FROM membership_users
                    WHERE groupID = 2";

            $query = sql($sql, $eo);

            $users = array();

            while($res = db_fetch_assoc($query)){
                $memberID = $res["memberID"];
                $passMD5 = $res["passMD5"];

                $users[$memberID] = $passMD5;
            }

            $this -> tokens = $users;
        }

        // Validates the given auth data
        // @return  true if check, false if not
        public function validateCredential(Array $token){

            $user = $token["user"];
            $pass = $token["pass"];

            $userExists = array_key_exists($user, $this -> tokens);
            $correctCredentials = false;

            if($userExists) $correctCredentials = (password_verify($pass, $this -> tokens[$user]) || $this -> tokens[$user] == md5($pass));

            unset($this -> tokens);

            $logedIn = ($userExists && $correctCredentials);

            return $logedIn;
        }

        // Returns an JSON enconded error.
        // @return  String with the response as JSON
        public function toJson(){

            // Informa um erro de autenticação
            $this -> report = array(
                "error" => "Authentication failed. Try again.",
                "type"  => "login-failed"
            );

            // Informa os meta dados da consulta
            $this -> meta = array(
                "remote-ip" => $_SERVER['REMOTE_ADDR'],
                "timestamp" => time(),
            );

            http_response_code(401);

            return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        }

    }

?>
