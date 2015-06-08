<?php
/**
 * Created by BlackCube Technologies
 * Developer: EdwardData
 * Date: 10/31/2012
 * Time: 3:06 PM
 * 
 */

//ob_start();
require_once("main.php");

$clientip = $_SERVER["REMOTE_ADDR"];
$radinfo = new RadInfo();
$radinfo->checkradinfo($clientip);
//ob_end_clean();
?>
 
