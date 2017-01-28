<?php

/**
 * Clase manejadora de la conexion
 *
 */
class DbConnect {

    private $conn;

    function __construct() {        
    }
    //Estableciendo la conexion a la base de datos
    function connect() {
        include_once dirname(__FILE__) . '/Config.php';

        // Conexion a la base de datos
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if (mysqli_connect_errno()) {
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }


        return $this->conn;
    }

}

?>
