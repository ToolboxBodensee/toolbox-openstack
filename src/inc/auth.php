<?php

function oci_auth_user($con, $conf, $login, $pass){
	$json["status"] = "error";
	$json["message"] = "Error during auth: ";

	$safe_login = mysqli_real_escape_string($con, $login);

	$q = "SELECT * FROM users WHERE login='$safe_login'";
	$r = mysqli_query($con, $q);
	if($r === FALSE){
		$json["message"] .= mysqli_error($con);
		return $json;
	}
	$n = mysqli_num_rows($r);
	if($n != 1){
		$json["message"] .= "no user by that name.";
		return $json;
	}
	$user = mysqli_fetch_array($r);
	$hashed_password = $user["hashed_password"];
	$use_radius      = $user["use_radius"];
	$activated       = $user["activated"];
	$is_admin        = $user["is_admin"];

	if($activated == "no"){
		$json["message"] .= "user is disabled.";
		return $json;
	}

	if($use_radius == "no"){
		if(password_verify($pass, $hashed_password) === TRUE){
			$json["status"] = "success";
			$json["message"] = "Authenticated successfully.";
			$json["data"]["is_admin"] = $is_admin;
		}else{
			$json["message"] .= "password not verified.";
		}
		return $json;
	}else{
		if($conf["radius"]["use_radius"] != "1"){
			$json["message"] .= "radius auth is not activated in the config file.";
			return $json;
		}
		$radius = radius_auth_open();
		if($radius === FALSE){
			$json["message"] .= "radius_auth_open() failed.";
			return $json;
		}
		$ret = radius_add_server($radius, $conf["radius"]["server_hostname"], 1812, $conf["radius"]["shared_secret"], 2, 3);
		if($ret === FALSE){
			$json["message"] .= "radius_add_server() failed.";
			return $json;
		}
		$ret = radius_create_request($radius, RADIUS_ACCESS_REQUEST);
		if($ret === FALSE){
			$json["message"] .= "radius_create_request() failed.";
			return $json;
		}
		$ret = radius_put_attr($radius, RADIUS_USER_NAME, $login);
		if($ret === FALSE){
			$json["message"] .= "radius_put_attr() for username failed.";
			return $json;
		}
		$ret = radius_put_attr($radius, RADIUS_USER_PASSWORD, $pass);
		if($ret === FALSE){
			$json["message"] .= "radius_put_attr() for password failed.";
			return $json;
		}
		$result = radius_send_request($radius);
		switch ($result) {
		case RADIUS_ACCESS_ACCEPT:
			$json["status"] = "success";
			$json["message"] = "Authenticated successfully.";
			$json["data"]["is_admin"] = $is_admin;
			return $json;
			break;
		case RADIUS_ACCESS_REJECT:
			// An Access-Reject response to an Access-Request indicating that the RADIUS server could not authenticate the user.
			$json["message"] .= "radius server responded with Access-Reject.";
			return $json;
			break;
		case RADIUS_ACCESS_CHALLENGE:
			// An Access-Challenge response to an Access-Request indicating that the RADIUS server requires further information in another Access-Request before authenticating the user.
			$json["message"] .= "radius server responded with Access-Challenge.";
			return $json;
			break;
		default:
			$json["message"] .= "a RADIUS error has occurred: " . radius_strerror($radius);
			return $json;
			break;
		}
	}
}

function oci_auth_me_please($con, $conf){
	$out = "Please login";
	// If the user is attempting to log in
	if( isset($_REQUEST["login_action"]) ){
		switch($_REQUEST["login_action"]){
		case "login":
			if (!isset($_REQUEST["email"]) || !isset($_REQUEST["password"])){
				die("Get away!");
			}
			$ret = oci_auth_user($con, $conf, $_REQUEST["email"], $_REQUEST["password"]);
			if($ret["status"] == "success"){
				$_SESSION['login'] = '1';
				$_SESSION['email'] = $_REQUEST["email"];
			}else{
				$out = $ret["message"];
			}
			break;
		default:
			$_SESSION['login'] = '0';
			$_SESSION['email'] = '';
			session_destroy();
			$out = "Logged out";
			break;
		}
	}
	return $out;
}

function oci_ip_check($con, $conf){
	if($_SERVER['REMOTE_ADDR'] == "::1"){
		$ip_to_check = ip2long("127.0.0.1");
	}else{
		$ip_to_check = ip2long($_SERVER['REMOTE_ADDR']);
	}
	// Check against the config file first.
	$trusted_nets = $conf["network"]["trusted_nets"];
	for($i=0;$i<sizeof($trusted_nets);$i++){
		$first_ip = ip2long($trusted_nets[$i]["ip"]);
		$last_ip = $first_ip + pow(2, (32 - $trusted_nets[$i]["cidr"])) - 1;
		if($ip_to_check >= $first_ip && $last_ip >= $ip_to_check){
			return TRUE;
		}
	}

	// Allow anything on local loopback.
	$first_ip = ip2long("127.0.0.1");
	$last_ip = ip2long("127.255.255.255");
	if($ip_to_check >= $first_ip && $last_ip >= $ip_to_check){
		return TRUE;
	}

	// Then check against the configured db.
	$q = "SELECT ip,cidr FROM networks";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            return FALSE;
        }
        $n = mysqli_num_rows($r);
	for($i=0;$i<$n;$i++){
		$net = mysqli_fetch_array($r);
		$first_ip = ip2long($net["ip"]);
		$last_ip = $first_ip + pow(2, (32 - $net["cidr"])) - 1;
		if($ip_to_check >= $first_ip && $last_ip >= $ip_to_check){
			return TRUE;
		}
	}
	return FALSE;
}


?>
