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

$data = json_decode(file_get_contents('php://input'), true);

$remote_addr = $_SERVER['REMOTE_ADDR'];

// Validate memory size
if(!isset($data["memory"][0]["size"]))	die("No memory size reported.");
$memory_size = $data["memory"][0]["size"];
$reg = '/^[0-9]{1,11}$/';
if(!preg_match($reg,$memory_size))	die("Memory size not an int of size max 11 chars.");
$safe_memory_size = $memory_size;

// These aren't interesting for the moment
//$memory_type = $data["memory"][0]["type"];
//$memory_speed = $data["memory"][0]["speed"];
//$memory_manufacturer = $data["memory"][0]["manufacturer"];

// Validate serial number
if (!isset($data["machine"][0]["serial"]))	die("No serial number reported.");
$serial_number = $data["machine"][0]["serial"];
$reg = '/^[ _0-9a-zA-Z-]{1,64}$/';
if(!preg_match($reg,$serial_number))	die("Chassis serial number not in acceptable format.");
$safe_serial_number = $serial_number;

if (!isset($data["machine"][0]["productname"])){
    $prod_name = "No product name reported.";
}else{
    $reg = '/^[ ;_.0-9a-zA-Z-(),+]{1,120}$/';
    if(!preg_match($reg, $data["machine"][0]["productname"])){
        $prod_name = "Product name not in acceptable format.";
    }else{
        $prod_name = $data["machine"][0]["productname"];
    }
}

if (!isset($data["machine"][0]["ladvd_report"])){
    $ladvd_report = "No ladvd report";
}else{
    $reg = '/^[ ;_.0-9a-zA-Z-(),+]{1,120}$/';
    if(!preg_match($reg, $data["machine"][0]["ladvd_report"])){
        $ladvd_report = "Detect failed";
    }else{
        $ladvd_report = $data["machine"][0]["ladvd_report"];
    }
}

if (!isset($data["machine"][0]["bios_version"])){
    $bios_version = "Not reported";
}else{
    $reg = '/^[ ;_.0-9a-zA-Z-(),+]{1,120}$/';
    if(!preg_match($reg, $data["machine"][0]["bios_version"])){
        $bios_version = "BIOS version is weird.";
    }else{
        $bios_version = $data["machine"][0]["bios_version"];
    }
}

if (!isset($data["machine"][0]["ipmi_firmware_version"])){
    $ipmi_version = "Not reported";
}else{
    $reg = '/^[ ;_.0-9a-zA-Z-(),+]{1,120}$/';
    if(!preg_match($reg, $data["machine"][0]["ipmi_firmware_version"])){
        $ipmi_version = "Detect failed";
    }else{
        $ipmi_version = $data["machine"][0]["ipmi_firmware_version"];
    }
}

if (!isset($data["machine"][0]["ipmi_detected_ip"])){
    $ipmi_detected_ip = "Not reported";
}else{
    $reg = '/^[ :.0-9a-fA-F]{1,120}$/';
    if(!preg_match($reg, $data["machine"][0]["ipmi_detected_ip"])){
        $ipmi_detected_ip = "Detect failed";
    }else{
        $ipmi_detected_ip = $data["machine"][0]["ipmi_detected_ip"];
    }
}

$r = mysqli_query($con, "SELECT * FROM machines WHERE serial='" . $safe_serial_number . "'");
$n = mysqli_num_rows($r);
if($n == 0){
    $r = mysqli_query($con, "INSERT INTO machines (ipaddr, serial, product_name, ladvd_report, bios_version, ipmi_firmware_version, ipmi_detected_ip, memory, status) VALUES ('" . $remote_addr. "', '$safe_serial_number' , '$prod_name', '$ladvd_report', '$bios_version', '$ipmi_version', '$ipmi_detected_ip', '$safe_memory_size', 'live')");
    if($r === FALSE){
        $error = mysqli_error($con);
        printf("Error inserting: %s\n",  mysqli_error($con));
    }
    $machine_id = mysqli_insert_id($con);

    if (isset($data["blockdevices"])){
        foreach($data["blockdevices"] as &$blockdevice){
            if(isset($blockdevice['name']) && isset($blockdevice['size'])){
                $blk_name = $blockdevice['name'];
                $reg = '/^[0-9a-zA-Z-]{1,64}$/';
                if(!preg_match($reg,$blk_name))	die("Block device name suspicious");
                $safe_blk_name = $blk_name;
                $blk_size = $blockdevice['size'];
                $reg = '/^[0-9]{6,16}$/';
                if(!preg_match($reg,$blk_size))	die("Block device size not an int of size max 11 chars.");
                $safe_blk_size = $blk_size / (1024*1024);
                $r = mysqli_query($con, "INSERT INTO blockdevices (machine_id, name, size_mb) VALUES ('".$machine_id."', '".$safe_blk_name."', '".$safe_blk_size."')");
            }
        }
    }

    if(isset($data["interfaces"])){
        foreach($data["interfaces"] as &$iface){
            if(isset($iface["name"]) && isset($iface["macaddr"]) && isset($iface["max_speed"])){
                $if_name = $iface["name"];
                $reg = '/^[0-9a-zA-Z-]{1,64}$/';
                if(!preg_match($reg,$blk_name)) die("Network interface device name suspicious");
                $safe_if_name = $if_name;
                $if_macaddr = $iface["macaddr"];
                $reg = '/^[0-9a-fA-F]{2}:[0-9a-fA-F]{2}:[0-9a-fA-F]{2}:[0-9a-fA-F]{2}:[0-9a-fA-F]{2}:[0-9a-fA-F]{2}$/';
                if(!preg_match($reg,$if_macaddr)) die("Network interface MAC address suspicious");
                $safe_if_macaddr = $if_macaddr;
                $if_max_speed = $iface["max_speed"];
                $reg = '/^[0-9]{1,10}$/';
                if(!preg_match($reg,$if_max_speed)) die("Network interface max_speed suspicious");
                $safe_if_max_speed = $if_max_speed;
                $r = mysqli_query($con, "INSERT INTO ifnames (machine_id, name, macaddr, max_speed) VALUES ('$machine_id', '$safe_if_name', '$safe_if_macaddr', '$safe_if_max_speed')");
            }
        }
    }
}else{
    $a = mysqli_fetch_array($r);
    $machine_id = $a["id"];
    // We keep the machine id...
    $req = "UPDATE machines SET ipaddr='$remote_addr', memory='".$safe_memory_size."', serial='$safe_serial_number', product_name='$prod_name', ladvd_report='$ladvd_report', bios_version='$bios_version', ipmi_firmware_version='$ipmi_version', ipmi_detected_ip='$ipmi_detected_ip', lastseen=NOW() WHERE serial='$safe_serial_number'";
    $r = mysqli_query($con, $req);
    if (isset($data["blockdevices"])){
        $blkdev_array = array();
        foreach($data["blockdevices"] as &$blockdevice){
            if(isset($blockdevice['name']) && isset($blockdevice['size'])){
                $blk_name = $blockdevice['name'];
                $reg = '/^[0-9a-zA-Z-]{1,64}$/';
                if(!preg_match($reg,$blk_name)) die("Block device name suspicious");
                $safe_blk_name = $blk_name;
                $blk_size = $blockdevice['size'];
                $reg = '/^[0-9]{6,16}$/';
                if(!preg_match($reg,$blk_size)) die("Block device size not an int of size max 11 chars.");
                $safe_blk_size = $blk_size / (1024*1024);
                $blkdev_array[] = array(
                    "name" => $safe_blk_name,
                    "size_mb" => $safe_blk_size,
                    );
            }
        }
        // Check if there's a difference between the report and the db
        $all_if_in_sql = "yes";
        for($i=0;$i<sizeof($blkdev_array);$i++){
            $myblkdev = $blkdev_array[$i];
            $safe_blk_name = $myblkdev["name"];
            $safe_blk_size = $myblkdev["size_mb"];
            $q = "SELECT id FROM blockdevices WHERE machine_id='$machine_id' AND name='$safe_blk_name' AND size_mb='$safe_blk_size'";
            $r = mysqli_query($con, $q);
            $n = mysqli_num_rows($r);
            if($n != 1){
                $all_if_in_sql = "no";
            }
        }

        // Check if the number of dev don't match
        $q = "SELECT id FROM blockdevices WHERE machine_id='$machine_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != sizeof($blkdev_array)){
            $all_if_in_sql = "no";
        }

        // If there is, delete all block devices from db, and record them again
        if($all_if_in_sql == "no"){
            $r = mysqli_query($con, "DELETE FROM blockdevices WHERE machine_id='".$machine_id."'");
            for($i=0;$i<sizeof($blkdev_array);$i++){
                $myblkdev = $blkdev_array[$i];
                $safe_blk_name = $myblkdev["name"];
                $safe_blk_size = $myblkdev["size_mb"];
                $r = mysqli_query($con, "INSERT INTO blockdevices (machine_id, name, size_mb) VALUES ('".$machine_id."', '".$safe_blk_name."', '".$safe_blk_size."')");
            }
        }
    }

    if(isset($data["interfaces"])){
        $iface_array = [];
        foreach($data["interfaces"] as &$iface){
            if(isset($iface["name"]) && isset($iface["macaddr"]) && isset($iface["max_speed"])){
                $if_name = $iface["name"];
                $reg = '/^[0-9a-zA-Z-]{1,64}$/';
                if(!preg_match($reg,$if_name)) die("Network interface device name suspicious");
                $safe_if_name = $if_name;
                $if_macaddr = $iface["macaddr"];
                $reg = '/^[0-9a-fA-F]{2}:[0-9a-fA-F]{2}:[0-9a-fA-F]{2}:[0-9a-fA-F]{2}:[0-9a-fA-F]{2}:[0-9a-fA-F]{2}$/';
                if(!preg_match($reg,$if_macaddr)) die("Network interface MAC address suspicious");
                $safe_if_macaddr = $if_macaddr;
                $if_max_speed = $iface["max_speed"];
                $reg = '/^[0-9]{1,10}$/';
                if(!preg_match($reg,$if_max_speed)) die("Network interface max_speed suspicious");
                $safe_if_max_speed = $if_max_speed;
                $iface_array[] = array(
                        "name" => $safe_if_name,
                        "macaddr" => $safe_if_macaddr,
                        "max_speed" => $safe_if_max_speed,
                    );
            }
        }

        // Check if there's a difference between the report and the db
        $all_if_in_sql = "yes";
        for($i=0;$i<sizeof($iface_array);$i++){
            $myiface = $iface_array[$i];
            $safe_if_name      = $myiface["name"];
            $safe_if_macaddr   = $myiface["macaddr"];
            $safe_if_max_speed = $myiface["max_speed"];
            $q = "SELECT id FROM ifnames WHERE machine_id='$machine_id' AND name='$safe_if_name' AND macaddr='$safe_if_macaddr' AND max_speed='$safe_if_max_speed'";
            $r = mysqli_query($con, $q);
            $n = mysqli_num_rows($r);
            if($n != 1){
                $all_if_in_sql = "no";
            }
        }
        // If there is, delete all ifaces from db, and record them again
        if($all_if_in_sql == "no"){
            $r = mysqli_query($con, "DELETE FROM ifnames WHERE machine_id='".$machine_id."'");
            for($i=0;$i<sizeof($iface_array);$i++){
                $myiface = $iface_array[$i];
                $safe_if_name      = $myiface["name"];
                $safe_if_macaddr   = $myiface["macaddr"];
                $safe_if_max_speed = $myiface["max_speed"];
                $q = "INSERT INTO ifnames (machine_id, name, macaddr, max_speed) VALUES ('$machine_id', '$safe_if_name', '$safe_if_macaddr', '$safe_if_max_speed')";
                $r = mysqli_query($con, $q);
            }
        }
    }
}

print("Ok\n");
?>
