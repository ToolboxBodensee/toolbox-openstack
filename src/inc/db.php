<?php

function connectme($conf){
    global $con;
    global $conf;
    $con = mysqli_init();

    # Parse the DNS to extart user, pass, host, port and db name
    $no_proto = str_replace("mysql://","", $conf["database"]["DSN"]);
    $userpass = strtok($no_proto, "@");
    $hostdbport = str_replace($userpass . "@", "", $no_proto);
    $host = strtok($hostdbport, ":");
    $dbport = str_replace($host . ":", "", $hostdbport);
    $port = strtok($dbport, "/");
    $dbname = str_replace($port . "/", "", $dbport);
    $user = strtok($userpass, ":");
    $pass = str_replace($user . ":", "", $userpass);

    mysqli_real_connect($con, $host, $user, $pass, $dbname, $port);
    return $con;
}


?>