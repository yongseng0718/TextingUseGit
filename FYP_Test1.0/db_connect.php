<?php
function open_connection() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "product1.0";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}
?>


