<?
ini_set('display_errors', 1);
error_reporting(E_ALL & ~ E_NOTICE);

require_once 'Kawaz_DBSessionHandler.php';
$dbSessionHandler = new Kawaz\DBSessionHandler("mysql:host=xxxx;dbname=xxx","user","password");
session_set_save_handler($dbSessionHandler);


session_start();
echo "count=" . $_SESSION["count"]++;