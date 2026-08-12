<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');
/** @var mysqli $con */
$con = mysqli_connect("localhost", "root", "", "cadlete_crm", 3306);

if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_query($con, "SET time_zone = '+05:30'");
