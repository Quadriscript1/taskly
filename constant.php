<?php 
//start session
session_start();


//create constants to store non-repeated values

    define('SITEURL', '/');
    define('LOCALHOST', 'localhost');
    define('DB_USERNAME', 'root');
    define('DB_PASSWORD', '1234');
    define('DB_NAME','ecommerce');






 //execute query and save data in the database

 $conn = mysqli_connect(LOCALHOST,DB_USERNAME,DB_PASSWORD) or die(mysqli_error($error));

 $db_select = mysqli_select_db($conn,DB_NAME);




?>