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

if (!defined('CADLETE_ENCRYPTION_KEY')) {
    define('CADLETE_ENCRYPTION_KEY', 'CadleteCRM@2026_SecureKey_#987');
    define('CADLETE_ENCRYPTION_METHOD', 'AES-256-CBC');
}

if (!function_exists('encrypt_password')) {
    /**
     * Encrypt password with AES-256-CBC
     * @param string $password
     * @return string
     */
    function encrypt_password($password): string
    {
        if ($password === '' || $password === null) {
            return '';
        }
        $key = hash('sha256', CADLETE_ENCRYPTION_KEY);
        $iv = substr(hash('sha256', 'cadlete_crm_iv_fixed'), 0, 16);
        $encrypted = openssl_encrypt((string)$password, CADLETE_ENCRYPTION_METHOD, $key, 0, $iv);
        return 'ENC:' . $encrypted;
    }
}

if (!function_exists('decrypt_password')) {
    /**
     * Decrypt password from AES-256-CBC (or return as-is if plain/unencrypted)
     * @param string $encrypted_password
     * @return string
     */
    function decrypt_password($encrypted_password): string
    {
        if (empty($encrypted_password)) {
            return '';
        }
        if (strpos($encrypted_password, 'ENC:') === 0) {
            $cipher = substr($encrypted_password, 4);
            $key = hash('sha256', CADLETE_ENCRYPTION_KEY);
            $iv = substr(hash('sha256', 'cadlete_crm_iv_fixed'), 0, 16);
            $decrypted = openssl_decrypt($cipher, CADLETE_ENCRYPTION_METHOD, $key, 0, $iv);
            return ($decrypted !== false) ? $decrypted : $encrypted_password;
        }
        return (string)$encrypted_password;
    }
}

if (!function_exists('verify_secure_password')) {
    /**
     * Verify input password against stored password (supports ENC: AES, bcrypt hash, or plain-text)
     * @param string $input_password
     * @param string $stored_password
     * @return bool
     */
    function verify_secure_password($input_password, $stored_password): bool
    {
        $input_password = (string)$input_password;
        $stored_password = (string)$stored_password;

        if (strpos($stored_password, 'ENC:') === 0) {
            $decrypted = decrypt_password($stored_password);
            return ($input_password === $decrypted);
        }

        // Support bcrypt
        if (password_get_info($stored_password)['algo'] !== 0) {
            if (password_verify($input_password, $stored_password)) {
                return true;
            }
        }

        // Support legacy plain text
        return ($input_password === $stored_password);
    }
}

