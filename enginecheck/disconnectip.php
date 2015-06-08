<?php
/**
 * Created by BlackCube Technologies
 * Developer: EdwardData
 * Date: 12/6/2012
 * Time: 7:28 PM
 * 
 */

require_once("auth.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

header("Expires: 0");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

function disconnect_client($clientip, $logoutReason = "LOGOUT", $term_cause = 1) {

    global $g, $config;

    $radiusservers = captiveportal_get_radius_servers();
    $cplock = lock('captiveportal');

    /* read database */
    $cpdb = captiveportal_read_db();
    $ssid = array();
    $cpdb1 = file("/var/db/captiveportal.db", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($cpdb1 as $key) {
        $tmparr = explode(",",$key);
        $ssid[] = $tmparr[5];
    }
    $unsetindex = array();
    
    /* find entry */
    for ($i = 0; $i < count($ssid); $i++) {
       $tmpssid = $ssid[$i];
       if ($cpdb[$tmpssid][2] == $clientip) {
           $cpentry = $cpdb[$tmpssid];
           $unsetindex[] = $cpdb[$tmpssid][5];
           captiveportal_write_db($cpdb,false,$unsetindex);
           captiveportal_disconnect($cpentry,$radiusservers, $term_cause);
           captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],$logoutReason);
            break;
        }
    }

    unlock($cplock);

}

disconnect_client("192.168.222.33","LOGOUT",1);

 
?>
 
