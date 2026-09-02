<?php
$config = require dirname(__DIR__) . '/../environment.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');
/** @var mysqli $con */
$con = mysqli_connect($config['DB_HOST'], $config['DB_USER'], $config['DB_PASS'], $config['DB_NAME'], $config['DB_PORT']);

if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_query($con, "SET time_zone = '+05:30'");

if (!function_exists('format_emp_id')) {
    /**
     * Format employee ID to the CD series: e.g. 1 -> CD001, 2 -> CD002
     * @param mixed $id
     * @return string
     */
    function format_emp_id($id): string
    {
        if (empty($id)) {
            return '';
        }
        if (is_string($id) && preg_match('/^CD\d+/i', $id)) {
            return strtoupper($id);
        }
        return 'CD' . sprintf('%03d', (int)$id);
    }
}
