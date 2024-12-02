<?php
    $servername = "localhost";
    $username = "root"; $password = "";
    $dbname = "users"; 
    $password = '';
    $db = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password); 
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>