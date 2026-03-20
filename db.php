<?php
$host = "localhost";
$user = "dmsoghwg_KimKangho";
$pass = "Isabella0304!";
$dbname = "dmsoghwg_s3903858";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to handle Vietnamese characters correctly
$conn->set_charset("utf8mb4");
?>