<?php

require_once("inc/read_conf.php");
require_once("inc/db.php");
require_once("inc/ssh.php");
require_once("inc/auth.php");

$conf = readmyconf();
$con = connectme($conf);

session_start();
$text_login = oci_auth_me_please($con, $conf);
if(isset($_SESSION['login']) && $_SESSION['login'] == '1'){
	$ret = send_ssh_cmd($conf, $con, $_REQUEST["ipaddr"], "tail -n 50 /var/log/oci.log");

	print("<html><head><link rel='stylesheet' href='oci.css'>
<title>Logs for ".$_REQUEST["ipaddr"]."</title>
<meta http-equiv='refresh' content='2'>
</head><body><font color='white'>");
	print(nl2br(htmlspecialchars($ret)));
	print("</font></body></html>");
}else{
	echo "<html><head><link rel='stylesheet' href='oci.css'>
<style>
body {font-family: Arial;}
</style>
</head>
<body>Not authenticated.</body>
</html>";
}

?>
