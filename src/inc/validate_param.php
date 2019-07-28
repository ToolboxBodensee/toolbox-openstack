<?php

function check_machine_with_ip_exists($con, $safe_ipv4){
    $r = mysqli_query($con, "SELECT * FROM machines WHERE ipaddr='$safe_ipv4'");
    $n = mysqli_num_rows($r);
    if($n != 1){
        return FALSE;
    }else{
        return TRUE;
    }
}

function check_cluster_with_id_exists($con, $safe_id){
    $q = "SELECT * FROM clusters WHERE id='$safe_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        return FALSE;
    }else{
        return TRUE;
    }
}

# IPv4
function validate_ip($ip){
    if(!isset($_REQUEST[$ip]) || $_REQUEST[$ip] == ""){
        return FALSE;
    }
    $param = $_REQUEST[$ip];
    $reg = "/^([0-9]){1,3}\.([0-9]){1,3}\.([0-9]){1,3}\.([0-9]){1,3}\$/";
    if(!preg_match($reg,$param))       return FALSE;
    else                    return TRUE;
}

function safe_ipv4($param_name){
    if(validate_ip($param_name) === FALSE){
        return FALSE;
    }
    return $_REQUEST[$param_name];
}

function validate_uuid($uuid){
    if(!isset($_REQUEST[$uuid]) || $_REQUEST[$uuid] == ""){
       return FALSE;
    }
    $param = $_REQUEST[$uuid];
    $reg = "/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\$/";
    if(!preg_match($reg,$param))       return FALSE;
    else                    return TRUE;
}

function safe_uuid($param_name){
    if(validate_uuid($param_name) === FALSE){
        return FALSE;
    }
    return $_REQUEST[$param_name];
}

function validate_uuid_list($uuid_list){
    if(!isset($_REQUEST[$uuid_list])){
        return FALSE;
    }
    $param = $_REQUEST[$uuid_list];
    $tok = strtok($param, ",");

    $reg = "/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\$/";

    while ($tok !== FALSE) {
        if(!preg_match($reg,$tok))       return FALSE;
        $tok = strtok(",");
    }
    return TRUE;
}

function safe_uuid_list($param_name){
    if(validate_uuid_list($param_name) === FALSE){
        return FALSE;
    }
    return $_REQUEST[$param_name];
}

# Domain name
function validate_domain_name($hostname){
    if(!isset($_REQUEST[$hostname]) || $_REQUEST[$hostname] == ""){
        return FALSE;
    }
    $param = $_REQUEST[$hostname];
    $reg = "/^\b((?=[a-z0-9-]{1,63}\.)(xn--)?[a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,63}\b\$/";
    if(!preg_match($reg,$param))       return FALSE;
    else                    return TRUE;
}

function safe_domain_name($param_name){
    if(validate_domain_name($param_name) === FALSE){
        return FALSE;
    }else{
        $dom = $_REQUEST[$param_name];
        $IDN = new idna_convert(array('idn_version' => 2008));
        $punnycode = $IDN->encode_uri($dom);
        return $punnycode;
    }
}

# Cluster name
function validate_cluster_name($cluster){
    if(!isset($_REQUEST[$cluster]) || $_REQUEST[$cluster] == ""){
        return FALSE;
    }
    $param = $_REQUEST[$cluster];
    $reg = "/^([a-z0-9]+)([a-z0-9-]*)([a-z0-9]+)\$/";
    if(!preg_match($reg,$param))	return FALSE;
    else	return TRUE;
}

function safe_cluster_name($param_name){
    if(validate_cluster_name($param_name) === FALSE){
        return FALSE;
    }
    return $_REQUEST[$param_name];
}

# Passwords
function validate_password($password){
    if(!isset($_REQUEST[$password]) || $_REQUEST[$password] == ""){
        return FALSE;
    }
    $param = $_REQUEST[$password];
    $reg = "/^[-a-zA-Z0-9]+\$/";
    if(!preg_match($reg,$param))       return FALSE;
     else                    return TRUE;
}

function safe_password($param_name){
    if(validate_password($param_name) === FALSE){
        return FALSE;
    }
    return $_REQUEST[$param_name];
}

# Machine serial number
function validate_serial($serial){
    if(!isset($_REQUEST[$serial]) || $_REQUEST[$serial] == ""){
        return FALSE;
    }
    $param = $_REQUEST[$serial];
    $reg = "/^[-a-zA-Z0-9]+\$/";
    if(!preg_match($reg,$param))       return FALSE;
     else                    return TRUE;
}

function safe_serial($param_name){
    if(validate_serial($param_name) === FALSE){
        return FALSE;
    }
    return $_REQUEST[$param_name];
}

# FQDN
function validate_fqdn($param_name){
    if(!isset($_REQUEST[$param_name]) || $_REQUEST[$param_name] == ""){
        return FALSE;
    }
    $param = $_REQUEST[$param_name];
    # No more than 253 chars total
    $reg = '/^.{1,253}$/';
    if(!preg_match($reg,$param)) return FALSE;
    # Split each strings separated by dots and check then one by one
    $fqdn_array = explode("." , $param);
    foreach($fqdn_array as $subdom){
        # Each elements cannot be >= 63 in length
        # and allow punnycode (ie: xn--)
        $reg = '/^((?!-))(xn--)?[a-z0-9][a-z0-9-]{0,61}[a-z0-9]{0,1}$/';
        if(!preg_match($reg,$subdom)) return FALSE;
    }
    return TRUE;
}

function safe_fqdn($param_name){
    if(validate_fqdn($param_name) === FALSE) return FALSE;
    return $_REQUEST[$param_name];
}

# Is numeric int
function validate_int($param_name){
    if(!isset($_REQUEST[$param_name]) || $_REQUEST[$param_name] == ""){
        return FALSE;
    }
    $param = $_REQUEST[$param_name];
    $reg = '/^[0-9]{1,253}$/';
    if(!preg_match($reg,$param))        return FALSE;
    else        return TRUE;
}

function safe_int($param_name){
    if(validate_int($param_name) === FALSE) return FALSE;
    return $_REQUEST[$param_name];
}

# URL
function validate_url($param_name){
    if(!isset($_REQUEST[$param_name]) || $_REQUEST[$param_name] == ""){
        return FALSE;
    }
    $param = $_REQUEST[$param_name];
    # No more than 253 chars total
    $reg = '/^.{1,253}$/';
    if(!preg_match($reg,$param)) return FALSE;
    if(!filter_var($param, FILTER_VALIDATE_URL)) {
        return FALSE;
    }
    return TRUE;
}

function safe_url($param_name){
    if(validate_url($param_name) === FALSE) return FALSE;
    return $_REQUEST[$param_name];
}

function validate_ethname($param_name){
    if(!isset($_REQUEST[$param_name]) || $_REQUEST[$param_name] == ""){
        return FALSE; 
    }
    $param = $_REQUEST[$param_name];

    switch($param){
    case "none":
    case "eth0":
    case "eth1":
    case "eth2":
    case "eth3":
    case "eth4":
    case "eth5":
    case "10m1":
    case "10m2":
    case "10m3":
    case "10m4":
    case "100m1":
    case "100m2":
    case "100m3":
    case "100m4":
    case "1g1":
    case "1g2":
    case "1g3":
    case "1g4":
    case "10g1":
    case "10g2":
    case "10g3":
    case "10g4":
        return TRUE;
    default:
        return FALSE;
    }
}

function safe_ethname($param_name){
    if(validate_ethname($param_name) === FALSE) return FALSE;
    return $_REQUEST[$param_name];
}

function validate_blockdev_name($param_name){
    if(!isset($_REQUEST[$param_name]) || $_REQUEST[$param_name] == ""){
        return FALSE;
    }
    $param = $_REQUEST[$param_name];
    $reg = "/^[-a-zA-Z0-9]+\$/";
    if(!preg_match($reg,$param))       return FALSE;
     else                    return TRUE;
}

function safe_blockdev_name($param_name){
    if(validate_blockdev_name($param_name) === FALSE) return FALSE;
    return $_REQUEST[$param_name];
}

?>
