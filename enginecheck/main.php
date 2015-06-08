<?php
/**
 * Created by BlackCube Technologies
 * Developer: Edward Rodriguez
 * Date: 10/30/2012
 * Time: 7:20 PM
 * 
 */

//ini_set("error_reporting","E_ERROR | E_PARSE");

define("PATH",dirname(__FILE__));
define("RelativePath", PATH);
define("PathToCurrentPage", "/");
define("FileName","main.php");
define("ScriptPath","/etc");
date_default_timezone_set("America/Santo_Domingo");
include_once("Common.php");
include_once(RelativePath . "/Template.php");

//require_once("auth.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

class RadInfo {
    function checkradinfo($clientip) {
        $today = date("Y-m-d");
		$callingstationid = "";

        //$sql = "select callingstationid from radacct
        //where framedipaddress = '$clientip' and date(acctstarttime) = '$today'
        //and acctstoptime is null limit 1 ";

        $db = new clsDBdbConnection();
//		do {
        	$sql = "select callingstationid from radacct
        	where framedipaddress = '$clientip' and acctstoptime is null
        	order by radacctid desc limit 1 ";
            
        	$db->query($sql);
        	$db->next_record();
        	$callingstationid = trim($db->f("callingstationid"));

            //DEBUGING CODE
            //$now = date("Y-m-d H:i:s");
            //$handle = fopen("/tmp/logbct.txt","w");
            //fwrite($handle,"IP: $clientip IS WAITING FOR ACCOUNTING! $now \n");
            //fclose($handle);

//		} while (strlen($callingstationid) <= 0);

        $db->close();

        //DEBUGING CODE
        //$handle = fopen("/tmp/logbct-out.txt","w");
        //fwrite($handle,"IP: $clientip IS OUT OF THE LOOP! $now \n");
        //fclose($handle);

        $db = new clsDBdbConnection();

        if ( strlen($callingstationid) > 0 ) {
            $this->setlogin($callingstationid,$clientip);
        } else {
            //The clientip address was not found inthe radacct,so by default will be a limited connection
            //This code should never execute because ther is a loop at the time that will wait
            //until the accounting arrives.

            $clientip = trim($_SERVER["REMOTE_ADDR"]);

            //If there is not an initial session info in radacct will choose the last known accoutn value assigned to the client ip
            $sql = "select callingstationid from radacct where framedipaddress = '$clientip' order by radacctid desc limit 1 ";
            $db->query($sql);
            $db->next_record();
            $callingstationid = trim($db->f("callingstationid"));

            $uri = $_SERVER["REDIRECT_URI"];
            $uritmp = explode("redirurl=",$uri);
            $uri = $uritmp[1];
            //Log redirect for unlimited accounts
			/* Log has been disabled
            $sqlinsert = "insert into logredirect (clientip,account,accounttype,uri)";
            $sqlinsert .= " values('$clientip','$callingstationid',2,'$uri')";
            $db->query($sqlinsert);
            $db->next_record();
			*/

            $this->setlogin($callingstationid,$clientip);

        }

        $db->close();
    }


    function setlogin($callingstationid,$clientip) {
        $db = new clsDBdbConnection();

        //Setting up radcheck engine radius login info for the user
        //fixed mac address:  00050d9fdf57

        $exits = CCDLookUp("1 as exist","openaccounts","phoneaccount = '$callingstationid'",$db);
        if ($exits == "1") {
            //This will unset the rule of the last ip address hold by the account
            $lastip = CCDLookUp("lastip","accountsiplog","phoneaccount = '$callingstationid'",$db);
            if (strlen($lastip) > 0) {
                if ($lastip != $clientip) {
                    $sqlupdate = "update accountsiplog set lastip = '$clientip' where phoneaccount = '$callingstationid' ";
                    $db->query($sqlupdate);
                    $db->next_record();

                    $this->unsetrule($lastip);
                }
            } else {
                $lastip = CCDLookUp("lastip","accountsiplog","phoneaccount = '$callingstationid'",$db);
                if (strlen($lastip) <= 0) {
                    $sqlinsertip = "insert into accountsiplog(phoneaccount,lastip) values('$callingstationid','$clientip')";
                    $db->query($sqlinsertip);
                    $db->next_record();
                }
            }

            $uri = $_SERVER["REDIRECT_URI"];
            $uritmp = explode("redirurl=",$uri);
            $uri = $uritmp[1];
            //Log redirect for unlimited accounts
			/* Log has been disabled
            $sqlinsert = "insert into logredirect (clientip,account,accounttype,uri)";
            $sqlinsert .= " values('$clientip','$callingstationid',1,'$uri')";
            $db->query($sqlinsert);
            $db->next_record();
			*/

            //Set the rule to the unlimited account
            $this->setrule($clientip);

        } else {
            //Indicates that the connected user is a limited internet access account
            //By default limited account wont have any rules, it goes straight as limited

            $uri = $_SERVER["REDIRECT_URI"];
            $uritmp = explode("redirurl=",$uri);
            $uri = $uritmp[1];
			/* Log has been redirect
            $sqlinsert = "insert into logredirect (clientip,account,accounttype,uri)";
            $sqlinsert .= " values('$clientip','$callingstationid',0,'$uri')";
            $db->query($sqlinsert);
            $db->next_record();
			*/

        }

        $db->close();

    }


    function setrule($clientip) {
       if (strlen($clientip) > 0) {
            $command = "/bin/sh ".ScriptPath.'/sedin '.$clientip;
            $result = exec($command);
        }
    }

    function unsetrule($clientip) {
       if (strlen($clientip) > 0) {
            $command = "/bin/sh ".ScriptPath.'/sedout '.$clientip;
            $result = exec($command);
                       
            $this->disconnect_client($clientip,"LOGOUT",1);

        }        
    }


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




}



?>
 
