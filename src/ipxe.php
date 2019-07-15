<?php

require_once("inc/read_conf.php");
require_once("inc/db.php");
require_once("inc/auth.php");

$conf = readmyconf();
$con = connectme($conf);

if(oci_ip_check($con, $conf) === FALSE){
    die("Source IP not in openstack-cluster-installer.conf.");
}

$remote_addr = $_SERVER['REMOTE_ADDR'];

$r = mysqli_query($con, "SELECT * FROM machines WHERE ipaddr='" . $remote_addr . "'");
$n = mysqli_num_rows($r);
if($n == 1){
    $a = mysqli_fetch_array($r);
    switch($a["status"]){
    case "firstboot":
    case "installed":
        error_log("ipxe.php: Getting $remote_addr to boot on HDD");
        print("#!ipxe

# Boot the first local HDD
sanboot --no-describe --drive 0x80
");
        break;
    case "bootinglive":
    case "live":
    case "installing":
    default:
        error_log("ipxe.php: Getting $remote_addr to boot on live");
        print("#!ipxe

# Do normal PXE booting
chain tftp://192.168.100.2/lpxelinux.0
");
        break;
    }
}else{
    error_log("ipxe.php: Getting $remote_addr to boot on live");
    print("#!ipxe

# Do normal PXE booting
chain tftp://192.168.100.2/lpxelinux.0
");
}

?>
