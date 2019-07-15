<?php

require_once("inc/read_conf.php");
require_once("inc/db.php");
require_once("inc/idna_convert.class.php");
require_once("inc/validate_param.php");
require_once("inc/auth.php");

$conf = readmyconf();
$con = connectme($conf);

if(oci_ip_check($con, $conf) === FALSE){
    die("Source IP not in openstack-cluster-installer.conf.");
}

$remote_addr = $_SERVER['REMOTE_ADDR'];

$safe_chassis_serial = safe_serial("chassis-serial");
if($safe_chassis_serial === FALSE){
    $q = "SELECT * FROM machines WHERE ipaddr='$remote_addr'";
}else{
    $q = "SELECT * FROM machines WHERE serial='$safe_chassis_serial'";
}

$status = "";
$puppet_status = "";

$r = mysqli_query($con, $q);
$n = mysqli_num_rows($r);
if($n == 0){
    die();
}else{
    $machine = mysqli_fetch_array($r);
    $id = $machine["id"];
    if( isset($_REQUEST["status"]) ){
        switch($_REQUEST["status"]){
        case "live":
            $status = ", status='live'";
            break;
        case "installing":
            $status = ", status='installing'";
            break;
        case "installed":
            $status = ", status='installed'";
            // Since there can be a host in the between doing SNAT, the REMOTE_ADDR
            // can be wrong, so we fix that by reading the parameter.
            $safe_ipv4 = safe_ipv4("ipaddr");
            if($safe_ipv4 === FALSE){
                error_log("install-status.php: No ipaddr reported after install!");
            }else{
                $remote_addr = $safe_ipv4;
            }
            // Since the machine is booted and installed, we need to sign the puppet cert.
            // We can do it with sudo, as there's a sudoers file installed.
            $output = "";
            $machine_hostname = $machine["hostname"];
            error_log("install-status.php: sudo /usr/bin/puppet cert sign $machine_hostname");
            $cmd = "sudo /usr/bin/puppet cert sign " . $machine_hostname;
            exec($cmd , $output);
            break;
        case "firstboot":
            $status = ", status='firstboot'";
            break;
        case "puppet-running":
            $puppet_status = ", puppet_status='running'";
            break;
        case "puppet-success":
            $puppet_status = ", puppet_status='success'";
            break;
        case "puppet-failure":
            $puppet_status = ", puppet_status='failure'";
            break;
        default:
            $status = ", status='unkown'";
            break;
        }
    }else{
        $status = ", status='unkown'";
    }
    // We keep the machine id...
    if($safe_chassis_serial === FALSE){
        $req = "UPDATE machines SET lastseen=NOW() $puppet_status $status WHERE ipaddr='$remote_addr'";
    }else{
        $req = "UPDATE machines SET lastseen=NOW() $puppet_status $status, ipaddr='$remote_addr' WHERE serial='$safe_chassis_serial'";
    }
    $r = mysqli_query($con, $req);
}

?>
