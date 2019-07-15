<?php

function readmyconf() {
    $conf = parse_ini_file("/etc/openstack-cluster-installer/openstack-cluster-installer.conf", true);
    $conf["database"]["DSN"] = str_replace("+pymysql","", $conf["database"]["connection"]);

    # Calculate network params to make it easier to write /etc/network/interfaces
    $cidr = $conf["network"]["OPENSTACK_CLUSTER_NETWORK"];
    $cidr_exploded = explode("/", $cidr);
    $conf["network"]["network"] = $cidr_exploded[0];
    $conf["network"]["netmaskclass"] = $cidr_exploded[1];
    $output = "";
    exec("ipcalc $cidr | grep HostMin | awk '{print \$2}'", $output);
    $conf["network"]["hostmin"] = $output[0];
    $output = "";
    exec("ipcalc $cidr | grep HostMax | awk '{print \$2}'", $output);
    $conf["network"]["hostmax"] = $output[0];
    $output = "";
    exec("ipcalc $cidr | grep Netmask | awk '{print \$2}'", $output);
    $conf["network"]["netmask"] = $output[0];
    $output = "";
    exec("ipcalc $cidr | grep Broadcast | awk '{print \$2}'", $output);
    $conf["network"]["broadcast"] = $output[0];

    $trusted_networks = $conf["network"]["TRUSTED_NETWORKS"];
    $trusted_networks_exploded = explode(",", $trusted_networks);
    for($i=0;$i<sizeof($trusted_networks_exploded);$i++){
        $cidr_exploded = explode("/", $trusted_networks_exploded[$i]);
        $conf["network"]["trusted_nets"][$i]['ip'] = $cidr_exploded[0];
        $conf["network"]["trusted_nets"][$i]['cidr'] = $cidr_exploded[1];
    }

    return $conf;
}
?>
