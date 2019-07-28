<?php

function add_node_to_cluster($con, $conf, $machine_id, $cluster_id, $role_name, $location_id){
    $json["status"] = "success";
    $json["message"] = "Successfuly queried API.";

    # Fetch cluster name and domain
    $q = "SELECT * FROM clusters WHERE id='$cluster_id'";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    $n = mysqli_num_rows($r);
    if($n != 1){
        $json["status"] = "error";
        $json["message"] = "Cannot find cluster in database.";
        return $json;
    }
    $a = mysqli_fetch_array($r);
    $cluster_name = $a["name"];
    $cluster_domain = $a["domain"];

    # Fetch role ID
    $q = "SELECT * FROM roles WHERE name='$role_name'";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    $n = mysqli_num_rows($r);
    if($n == 0){
        $json["status"] = "error";
        $json["message"] = "Cannot find role in database.";
        return $json;
    }
    $a = mysqli_fetch_array($r);
    $role_id = $a["id"];

    # Get the role count for this role,
    # if the record doesn't exist, create it,
    # otherwise, increment, and finally, calculate
    # the current role count for the node we're adding.
    $q = "SELECT * FROM rolecounts WHERE cluster='$cluster_id' AND role='$role_id'";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    $n = mysqli_num_rows($r);
    if($n == 0){
        $role_count = 0;
        $q = "INSERT INTO rolecounts (cluster, role, count) VALUES ('$cluster_id', '$role_id', '1')";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $safe_role_count_id = mysqli_insert_id($con);
        $safe_role_count = "1";
    }else{
        $a = mysqli_fetch_array($r);
        $safe_role_count = $a["count"] + 1;
        $safe_role_count_id = $a["id"];

        $q = "UPDATE rolecounts SET count='$safe_role_count' WHERE id='$safe_role_count_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
    }

    # Check if there's no controller yet in the cluster.
    # If there's none, set this machine as the first_master
    if($role_name == "controller"){
        $q = "SELECT id FROM machines WHERE role='controller' AND cluster='$cluster_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $n = mysqli_num_rows($r);
        if($n == 0){
            $q = "UPDATE clusters SET first_master_machine_id='$machine_id' WHERE id='$cluster_id'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
        }
    }

    # Check if there's no sql or controller node yet in the cluster:
    # - reserve a VIP on the management network.
    # If the machine is the first SQL node we're adding:
    # - set this machine as the first_sql
    # If the machine is the first controller node we're adding, and there's
    # no SQL node yet:
    # - set this machine as the first_sql
#    if($role_name == "sql" || $role_name == 'controller'){
    if($role_name == "sql"){
        $q = "SELECT id FROM machines WHERE role='sql' AND cluster='$cluster_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $num_sql_nodes = mysqli_num_rows($r);

        $q = "SELECT id FROM machines WHERE role='controller' AND cluster='$cluster_id'";
        $r = mysqli_query($con, $q);
        if($r === FALSE){
            $json["status"] = "error";
            $json["message"] = mysqli_error($con);
            return $json;
        }
        $num_controller_nodes = mysqli_num_rows($r);

        # Create the VIP for SQL if it didn't exist
        if($num_sql_nodes == 0 && $num_controller_nodes == 0){
            $q = "SELECT * FROM networks WHERE cluster='$cluster_id' AND location_id='$location_id' AND role!='vm-net' AND role!='ovs-bridge' AND role!='ceph-cluster' LIMIT 1";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
            $n = mysqli_num_rows($r);
            if($n > 0){
                $network = mysqli_fetch_array($r);
                $network_id = $network["id"];
                reserve_ip_address($con, $conf, $network_id, 0, 'vip', 'sql');
            }
        }

        # Set the new machine as first_sql_machine_id if it is a SQL node and there's no SQL node added yet
        if($num_sql_nodes == 0 && $role_name == "sql"){
            $q = "UPDATE clusters SET first_sql_machine_id='$machine_id' WHERE id='$cluster_id'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
        }

        # Set the new machine as first_sql_machine_id if it is a controller node and there's no SQL or controller node added yet
        if($num_sql_nodes == 0 && $num_controller_nodes == 0 && $role_name == "controller"){
            $q = "UPDATE clusters SET first_sql_machine_id='$machine_id' WHERE id='$cluster_id'";
            $r = mysqli_query($con, $q);
            if($r === FALSE){
                $json["status"] = "error";
                $json["message"] = mysqli_error($con);
                return $json;
            }
        }
    }

    # Finally perform the UPDATE query for the machine so
    # that it joins the cluster
    $safe_hostname = $cluster_name . "-" . $role_name . "-" . $safe_role_count . "." . $cluster_domain;
    $q = "UPDATE machines SET cluster='$cluster_id', role='$role_name', hostname='$safe_hostname', location_id='$location_id' WHERE id='$machine_id'";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    # and assign IPs to it
    $json = slave_assign_all_networks_ip_addresses($con, $conf, $machine_id, "machine", $location_id);

    // Create the machine's SSL cert
    $cmd = "sudo /usr/bin/oci-gen-slave-node-cert $safe_hostname";
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    return $json;
}

function insert_cluster_pass($con, $conf, $cluster_id, $service, $passtype){
    if($service == "ceph" || $service == "gnocchi"){
        if($passtype == "fsid" || $passtype == "libvirtuuid" || $passtype == "uuid"){
            $hex = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                           mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                           mt_rand(0, 0xffff),
                           mt_rand(0, 0x0fff) | 0x4000,
                           mt_rand(0, 0x3fff) | 0x8000,
                           mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff) );
        }else{
            # Use ceph-authtool to generate the Ceph keys.
            # Using openssl + base64 didn't work. If someone
            # finds a way, that would avoid ceph-common as depends for OCI.
            $cmd = "ceph-authtool --gen-print-key";
            $output = array();
            $return_var = 0;
            exec($cmd, $output, $return_var);
            $hex = $output[0];
        }
    }elseif($service == "nova" && $passtype == "ssh"){
        # Generate the keypair
        $tmp_file = tempnam("/tmp", "nova-ssh-key-");
        unlink($tmp_file);

        $cmd = "ssh-keygen -t rsa -f $tmp_file -P ''";
        $output = "";
        $return_var = 0;
        exec($cmd, $output, $return_var);

        $private_key = file_get_contents($tmp_file);
        $public_key = file_get_contents($tmp_file . ".pub");
        strtok($public_key, " ");
        $public_key = strtok(" ");
        unlink($tmp_file);
        unlink($tmp_file . ".pub");

        # Store it
        $q = "INSERT INTO passwords (cluster, service, passtype, passtxt1, passtxt2) VALUES ('$cluster_id', '$service', '$passtype', '" . serialize($public_key) . "', '" . serialize($private_key) . "')";
        $r = mysqli_query($con, $q);
        return;
    }elseif($service == "keystone" && ($passtype == "credential1" || $passtype == "credential2")){
        $hex = base64_encode(openssl_random_pseudo_bytes(32, $crypto_strong));
    }else{
        $bytes = openssl_random_pseudo_bytes(32, $crypto_strong);
        $hex   = bin2hex($bytes);
    }
    $q = "INSERT INTO passwords (cluster, service, passtype, pass) VALUES ('$cluster_id', '$service', '$passtype', '$hex')";
    $r = mysqli_query($con, $q);
}

function new_cluster($con, $conf, $cluster_name, $cluster_domain){
    $json["status"] = "success";
    $json["message"] = "Successfuly queried API.";

    // Check if cluster exists
    $r = mysqli_query($con, "SELECT * FROM clusters WHERE name='$cluster_name'");
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    $n = mysqli_num_rows($r);
    if($n != 0){
        $json["status"] = "error";
        $json["message"] = "Error: cluster name $cluster_name already exists.";
        return $json;
    }

    // Create the cluster
    $q = "INSERT INTO clusters (name, domain) VALUES ('$cluster_name', '$cluster_domain'); ";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    $cluster_id = mysqli_insert_id($con);

    // Provision passwords for later
    insert_cluster_pass($con, $conf, $cluster_id, 'mysql',    'rootuser');
    insert_cluster_pass($con, $conf, $cluster_id, 'mysql',    'backup');
    insert_cluster_pass($con, $conf, $cluster_id, 'rabbitmq', 'cookie');
    insert_cluster_pass($con, $conf, $cluster_id, 'keystone', 'adminuser');
    insert_cluster_pass($con, $conf, $cluster_id, 'keystone', 'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'keystone', 'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'keystone', 'credential1');
    insert_cluster_pass($con, $conf, $cluster_id, 'keystone', 'credential2');
    insert_cluster_pass($con, $conf, $cluster_id, 'keystone', 'fernetkey1');
    insert_cluster_pass($con, $conf, $cluster_id, 'keystone', 'fernetkey2');
    insert_cluster_pass($con, $conf, $cluster_id, 'glance',   'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'glance',   'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'glance',   'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'nova',     'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'nova',     'apidb');
    insert_cluster_pass($con, $conf, $cluster_id, 'nova',     'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'nova',     'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'nova',     'ssh');
    insert_cluster_pass($con, $conf, $cluster_id, 'novaneutron', 'shared_secret');
    insert_cluster_pass($con, $conf, $cluster_id, 'placement','db');
    insert_cluster_pass($con, $conf, $cluster_id, 'placement','authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'cinder',   'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'cinder',   'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'cinder',   'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'neutron',  'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'neutron',  'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'neutron',  'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'heat',     'encryptkey');
    insert_cluster_pass($con, $conf, $cluster_id, 'heat',     'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'heat',     'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'heat',     'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'heat',     'keystone_domain');
    insert_cluster_pass($con, $conf, $cluster_id, 'swift',    'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'swift',    'hashpathsuffix');
    insert_cluster_pass($con, $conf, $cluster_id, 'swift',    'hashpathprefix');
    insert_cluster_pass($con, $conf, $cluster_id, 'swift',    'encryption');
    insert_cluster_pass($con, $conf, $cluster_id, 'horizon',  'secretkey');
    insert_cluster_pass($con, $conf, $cluster_id, 'barbican', 'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'barbican', 'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'barbican', 'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceph',     'fsid');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceph',     'libvirtuuid');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceph',     'adminkey');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceph',     'openstackkey');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceph',     'monkey');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceph',     'mgrkey');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceph',     'bootstraposdkey');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceilometer','db');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceilometer','messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceilometer','authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'ceilometer','telemetry');
    insert_cluster_pass($con, $conf, $cluster_id, 'gnocchi',  'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'gnocchi',  'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'gnocchi',  'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'panko'  ,  'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'panko',    'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'panko',    'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'gnocchi',  'uuid');
    insert_cluster_pass($con, $conf, $cluster_id, 'cloudkitty','db');
    insert_cluster_pass($con, $conf, $cluster_id, 'cloudkitty','messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'cloudkitty','authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'redis',    'redis');
    insert_cluster_pass($con, $conf, $cluster_id, 'aodh',     'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'aodh',     'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'aodh',     'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'octavia',  'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'octavia',  'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'octavia',  'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'octavia',  'heatbeatkey');
    insert_cluster_pass($con, $conf, $cluster_id, 'magnum',   'db');
    insert_cluster_pass($con, $conf, $cluster_id, 'magnum',   'messaging');
    insert_cluster_pass($con, $conf, $cluster_id, 'magnum',   'authtoken');
    insert_cluster_pass($con, $conf, $cluster_id, 'magnum',   'domain');

    $dir = "/var/lib/oci/clusters/$cluster_name";
    mkdir($dir, 0700);

    // Provision an API SSL certificate
    $api_hostname = $cluster_name . "-api." . $cluster_domain;
    $cmd = "sudo /usr/bin/oci-gen-slave-node-cert $api_hostname";
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    ##########################################
    ### Create an ssh key for this cluster ###
    ##########################################
    $ssh_key_dir = "/var/lib/oci/clusters/$cluster_name/ssh";
    mkdir($ssh_key_dir, 0700);
    $cmd = "ssh-keygen -P '' -f $ssh_key_dir/id_rsa";
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    return $json;
}

function cluster_delete($con, $conf, $cluster_name){
    $json["status"] = "success";
    $json["message"] = "Successfuly queried API.";

    $q = "SELECT * FROM clusters WHERE name='$cluster_name'";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }
    $n = mysqli_num_rows($r);
    if($n != 1){
        $json["status"] = "error";
        $json["message"] = "Error: cluster name doesn't exist.";
        return $json;
    }
    $cluster = mysqli_fetch_array($r);
    $cluster_id = $cluster["id"];

    $q = "DELETE FROM passwords WHERE cluster='$cluster_id'";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }

    $q = "DELETE FROM clusters WHERE name='$cluster_name'";
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $json["status"] = "error";
        $json["message"] = mysqli_error($con);
        return $json;
    }

    return $json;
}

// Fetch all networks where the machine has IP in.
function slave_fetch_networks($con, $conf, $machine_id){
    $out = array(
        "status"   => "success",
        "message"  => "Succesfully fetched networks.",
        "networks" => array(),
    );
    // Fetch the machine
    $q = "SELECT * FROM machines WHERE id='$machine_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out["status"]  = "error";
        $out["message"] = "Cannot find machine: $machine_id.";
        return $out;
    }
    $machine = mysqli_fetch_array($r);
    $cluster_id = $machine["cluster"];
    $role = $machine["role"];
    $location_id = $machine["location_id"];

    // Fetch its network
    $q = "SELECT * FROM networks WHERE cluster='$cluster_id' AND role='$role' AND location_id='$location_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n >= 1){
        for($i=0;$i<$n;$i++){
            $out["networks"][] = mysqli_fetch_array($r);
        }
    }else{
        $q = "SELECT * FROM networks WHERE cluster='$cluster_id' AND role='norole' AND location_id='$location_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $out["networks"][] = mysqli_fetch_array($r);
        }
    }

    $q = "SELECT * FROM networks WHERE cluster='$cluster_id' AND role='all' AND location_id='$location_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    for($i=0;$i<$n;$i++){
        $out["networks"][] = mysqli_fetch_array($r);
    }

    if($role == 'network' or $role == 'compute'){
        $q = "SELECT * FROM networks WHERE cluster='$cluster_id' AND (role='vm-net' OR role='ovs-bridge') AND location_id='$location_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $out["networks"][] = mysqli_fetch_array($r);
        }
    }

    if($role == 'cephosd'){
        $q = "SELECT * FROM networks WHERE cluster='$cluster_id' AND (role='ceph-cluster') AND location_id='$location_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $out["networks"][] = mysqli_fetch_array($r);
        }
    }

    // If there's no network node, then the controller needs the VM trafic network
    if($role == 'controller'){
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='network'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n == 0){
            $q = "SELECT * FROM networks WHERE cluster='$cluster_id' AND (role='vm-net' OR role='ovs-bridge') AND location_id='$location_id'";
            $r = mysqli_query($con, $q);
            $n = mysqli_num_rows($r);
            for($i=0;$i<$n;$i++){
                $out["networks"][] = mysqli_fetch_array($r);
            }
        }
    }

    return $out;
}

// Reserve an IP address in the "ips" table.
// usefor can be either machine or vip, if vip, then machine_id must be zero.
function reserve_ip_address($con, $conf, $network_id, $machine_id, $usefor, $vip_usage="api"){
    $out = array(
        "status"  => "success",
        "message" => "Succesfully reserved IP adresses.",
    );

    $q = "SELECT * FROM networks WHERE id='$network_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out["status"]  = "error";
        $out["message"] = "Cannot find network: $network_id.";
        return $out;
    }
    $network = mysqli_fetch_array($r);
    $network_ip   = $network["ip"];
    $network_name = $network["name"];
    $network_cidr = $network["cidr"];

    // Calculate first and last IP of the network
    $number_of_ip = 2 ** (32 - $network_cidr);
    $network_ip_long = ip2long($network_ip);
    $network_first_ip_long = $network_ip_long + 2;
    $netowrk_last_ip_long = $network_ip_long + $number_of_ip - 2;

    // ******** START SEMAPHORE ********
    $key   = ftok(__FILE__,'m');
    $mysem = sem_get($key);
    sem_acquire($mysem);

    // Check if there's either no IP left, or no IP provisionned yet.
    $q = "SELECT id FROM ips WHERE network='$network_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n >= ($number_of_ip - 3)){
        $out["status"]  = "error";
        $out["message"] = "No IP available in the network $network_name.";
        sem_release($mysem);
        return $out;
    }
    if($n == 0){
        // Just use the first IP
        $q = "INSERT INTO ips (usefor,network,type,ip,machine,vip_usage) VALUES ('$usefor', '$network_id', '4', '$network_first_ip_long','$machine_id','$vip_usage')";
    }else{
        // Get first IP address available
        $q = "INSERT INTO ips (usefor,network,machine,type,vip_usage,ip) SELECT '$usefor', '$network_id', '$machine_id', '4', '$vip_usage', outip.ip+1 FROM ips outip WHERE network='$network_id' AND NOT (SELECT COUNT(*) FROM ips inip WHERE inip.ip = outip.ip+1 AND network='$network_id') ORDER BY ip ASC LIMIT 1";
    }
    $r = mysqli_query($con, $q);
    if($r === FALSE){
        $out["status"]  = "error";
        $out["message"] = "Could not reserve new IP, query: $q, error: ".mysqli_error($con);
        sem_release($mysem);
        return $out;
    }
    sem_release($mysem);
    // ******** END SEMAPHORE ********
    return $out;
}

function reserve_ip_to_all_slaves_of_network($con, $conf, $cluster_id, $network_id, $role){
    $out = array(
        "status" => "success",
        "message" => "Successfully assigned IP for network id $network_id and cluster $cluster_id.",
    );

    $q = "SELECT role,location_id FROM networks WHERE id='$network_id'";
    $r = mysqli_query($con, $q);
    $a = mysqli_fetch_array($r);
    if($a["role"] == "ovs-bridge"){
        $out["message"] = "Not allocating IP on an ovs-bridge network.";
        return $out;
    }
    $location_id = $a["location_id"];

    if($role == "vm-net"){
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='network'";
        $r = mysqli_query($con, $q);
        $a = mysqli_fetch_array($r);
        $n = mysqli_num_rows($r);
        if($n > 0){
            $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND (role='network' OR role='compute') AND location_id='$location_id'";
        }else{
            $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND (role='controller' OR role='compute') AND location_id='$location_id'";
        }
    }elseif($role == "ceph-cluster"){
        $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='cephosd' AND location_id='$location_id'";
    }elseif($role == "ovs-bridge"){
        return $out;
    }else{
        if($role != "all"){
            $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='$role' AND location_id='$location_id'";
        }else{
            $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND location_id='$location_id'";
        }
    }
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    for($i=0;$i<$n;$i++){
        $machine = mysqli_fetch_array($r);
        $machine_id = $machine["id"];
        $ret = reserve_ip_address($con, $conf, $network_id, $machine_id, "machine");
        if($ret["status"] != "success"){
            return $ret;
        }
    }
    return $out;
}

function slave_assign_all_networks_ip_addresses($con, $conf, $id, $usefor, $location_id){
    $out = array(
        "status"  => "success",
        "message" => "Succesfully reserved IP adresses.",
    );
    if($usefor != "machine" && $usefor != "vip"){
        $out["status"]  = "error";
        $out["message"] = "Parameter usefor should be either machine or vip.";
    }

    if($usefor == "vip"){
        // If usefor is vip, then $id will in fact contain a network ID
        $network_id = $id;
    }else{
        $machine_id = $id;
    }

    // Fetch the machine
    $q = "SELECT * FROM machines WHERE id='$machine_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out["status"]  = "error";
        $out["message"] = "Cannot find machine: $machine_id.";
        return $out;
    }
    $machine = mysqli_fetch_array($r);
    $cluster_id = $machine["cluster"];
    $role = $machine["role"];

    $machine_networks = slave_fetch_networks($con, $conf, $machine_id);

    for($i=0;$i<sizeof($machine_networks["networks"]);$i++){
        $network_id   = $machine_networks["networks"][$i]["id"];
        if($machine_networks["networks"][$i]["is_public"] == "no" && $machine_networks["networks"][$i]["role"] != "ovs-bridge"){
            $ret = reserve_ip_address($con, $conf, $network_id, $machine_id, "machine");
        }
        if($ret["status"] == "error"){
            return $ret;
        }
    }
    return $out;
}

function slave_fetch_network_config($con, $conf, $machine_id){
    $out = array(
        "status"  => "success",
        "message" => "Succesfully fetched network config.",
    );
    $machine_networks = slave_fetch_networks($con, $conf, $machine_id);
    for($i=0;$i<sizeof($machine_networks["networks"]);$i++){
        $q = "SELECT INET_NTOA(ip) AS ipaddr FROM ips WHERE network='". $machine_networks["networks"][$i]["id"] ."' AND machine='$machine_id'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            continue;
        }
        $a = mysqli_fetch_array($r);
        $machine_networks["networks"][$i]["ipaddr"] = $a["ipaddr"];
        $network_ip_long = ip2long($machine_networks["networks"][$i]["ip"]);
        $network_gateway_long = $network_ip_long + 1;
        $machine_networks["networks"][$i]["gateway"] = long2ip($network_gateway_long);
    }
    return $machine_networks;
}

function get_ethname_from_network_config($con, $conf, $machine_id, $iface_in){
        $iface = "";
        $qeth = "";
        switch($iface_in){
        case "eth0":
            $iface = "eth0";
            break;
        case "eth1":
            $iface = "eth1";
            break;
        case "eth2":
            $iface = "eth2";
            break;
        case "eth3":
            $iface = "eth3";
            break;
        case "eth4":
            $iface = "eth4";
            break;
        case "eth5":
            $iface = "eth5";
            break;
        case "10m1":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='10' ORDER BY name LIMIT 1";
            break;
        case "10m2":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='10' ORDER BY name LIMIT 1,1";
            break;
        case "10m3":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='10' ORDER BY name LIMIT 2,1";
            break;
        case "10m4":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='10' ORDER BY name LIMIT 3,1";
            break;
        case "100m1":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='100' ORDER BY name LIMIT 1";
            break;
        case "100m2":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='100' ORDER BY name LIMIT 1,1";
            break;
        case "100m3":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='100' ORDER BY name LIMIT 2,1";
            break;
        case "100m4":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='100' ORDER BY name LIMIT 3,1";
            break;
        case "1g1":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='1000' ORDER BY name LIMIT 1";
            break;
        case "1g2":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='1000' ORDER BY name LIMIT 1,1";
            break;
        case "1g3":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='1000' ORDER BY name LIMIT 2,1";
            break;
        case "1g4":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed='1000' ORDER BY name LIMIT 3,1";
            break;
        case "10g1":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed>='10000' ORDER BY name LIMIT 1";
            break;
        case "10g2":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed>='10000' ORDER BY name LIMIT 1,1";
            break;
        case "10g3":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed>='10000' ORDER BY name LIMIT 2,1";
            break;
        case "10g4":
            $qeth = "SELECT name FROM ifnames WHERE machine_id='$machine_id' AND max_speed>='10000' ORDER BY name LIMIT 3,1";
            break;
        }
        if($qeth != ""){
            $reth = mysqli_query($con, $qeth);
            $neth = mysqli_num_rows($reth);
            if($neth != 1){
                $out["status"]  = "error";
                $out["message"] = "Cannot find block device: $q<br>";
                return $out;
            }
            $aeth = mysqli_fetch_array($reth);
            $iface = $aeth["name"];
        }
        return $iface;
}

function slave_install_server_os_command($con, $conf, $machine_id){
    $out = array(
        "status"  => "success",
        "message" => "Succesfully reserved IPs and generated command line.",
        "cmd"     => "",
    );
    // Fetch the machine
    $q = "SELECT * FROM machines WHERE id='$machine_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out["status"]  = "error";
        $out["message"] = "Cannot find machine: $machine_id.";
        return $out;
    }
    $machine = mysqli_fetch_array($r);
    $cluster_id = $machine["cluster"];
    $role = $machine["role"];

    // Get its block device
    $q = "SELECT * FROM blockdevices WHERE machine_id='$machine_id' AND name LIKE '%a'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out["status"]  = "error";
        $out["message"] = "Cannot find block device: $q<br>";
        return $out;
    }
    $a2 = mysqli_fetch_array($r);
    $install_hdd_name = $a2["name"];

    $machine_networks = slave_fetch_network_config($con, $conf, $machine_id);
    if(sizeof($machine_networks["networks"]) == 0){
        $out["status"]  = "error";
        $out["message"] = "No network configured for this machine.";
        return $out;
    }

    $has_vm_net = "no";
    $has_ovs_bridge = "no";
    $ovs_bridge_list = array();
    $has_cephnet = "no";
    for($i=0;$i<sizeof($machine_networks["networks"]);$i++){
        if($machine_networks["networks"][$i]["role"] == "vm-net"){
            $has_vm_net = "yes";
            if(isset($vm_net)){
                $out["status"]  = "error";
                $out["message"] = "Only a single VM trafic network can be set.";
                return $out;
            }
            $vm_net = $machine_networks["networks"][$i];
        }elseif($machine_networks["networks"][$i]["role"] == "ovs-bridge"){
            $has_ovs_bridge = "yes";
            $ovs_bridge_list[] = $machine_networks["networks"][$i];
        }elseif($machine_networks["networks"][$i]["role"] == "ceph-cluster"){
            if($has_cephnet == "yes"){
                $out["status"]  = "error";
                $out["message"] = "Only a single CephNET network can be set.";
                return $out;
            }
            $has_cephnet = "yes";
            $cephnet = $machine_networks["networks"][$i];
        }elseif($machine_networks["networks"][$i]["is_public"] == "yes"){
            continue;
        }else{
            if(isset($mgmt_net)){
                $out["status"]  = "error";
                $out["message"] = "Only a single management network can be set.";
                return $out;
            }
            $mgmt_net = $machine_networks["networks"][$i];
        }
    }

    if($has_vm_net == "yes"){
        if(!isset($mgmt_net)){
            $out["status"]  = "error";
            $out["message"] = "No management network is set.";
            return $out;
        }
        $br_ex_ovs_bridged_network = "yes";

        $addr_param = ",addr=" . $mgmt_net["ipaddr"] . "/" . $mgmt_net["cidr"] . ":" . $mgmt_net["gateway"] . ",vmnet_addr=" . $vm_net["ipaddr"] . "/" . $vm_net["cidr"] . ",vmnet_iface0=" . $vm_net["iface1"] . ",vmnet_iface1=" . $vm_net["iface2"]. ",ovsifaces=yes";
        if($mgmt_net["mtu"] != 0){
            $addr_param .= ",mtu=" . $mgmt_net["mtu"];
        }
        $vm_net_vlan = $vm_net["vlan"];
        if(!is_null($vm_net_vlan)){
            $addr_param .= ",vmnet_vlan=$vm_net_vlan";
        }

        $iface1 = get_ethname_from_network_config($con, $conf, $machine_id, $mgmt_net["iface1"]);

        $netvlan = $mgmt_net["vlan"];
        if($mgmt_net["iface2"] != "none"){
            $iface2 = get_ethname_from_network_config($con, $conf, $machine_id, $mgmt_net["iface2"]);
            if(is_null($netvlan)){
                $network_params .= " --static-iface type=bond,iface0=$iface1,iface1=$iface2" . $addr_param;
            }else{
                $network_params .= " --static-iface type=bondvlan,vlannum=$netvlan,iface0=$iface1,iface1=$iface2" . $addr_param;
            }
        }else{
            $network_params .= " --static-iface type=normal,iface0=$iface1" . $addr_param;
        }
        $network_params .= ",extra_ovs=yes";
        if($has_ovs_bridge == "yes"){
            for($i=0;$i<sizeof($ovs_bridge_list);$i++){
                $network_params .= " --static-iface type=ovsbridge,ovsbridgename=" . $ovs_bridge_list[$i]["bridgename"] . ",iface0=" . $ovs_bridge_list[$i]["iface1"] . ",iface1=" . $ovs_bridge_list[$i]["iface2"];
            }
        }
    }else{
        $br_ex_ovs_bridged_network = "no";
        $network_params = "";
        for($i=0;$i<sizeof($machine_networks["networks"]);$i++){
            $onenet  = $machine_networks["networks"][$i];

            if($onenet["is_public"] == "yes"){
                continue;
            }

            // If 2 networks have the same interface names, then we must
            // use virtual interface namings (aka: eth0:0 or bond0:0)
            $virtual_interface_num = 0;
            $use_virtual_interface = "no";
            for($j=0;$j<sizeof($machine_networks["networks"]);$j++){
                if($i == $j){
                    $virtual_interface_name = ":$virtual_interface_num";
                    $virtual_interface_num++;
                    continue;
                }
                if($machine_networks["networks"][$i]['iface1'] == $machine_networks["networks"][$j]['iface1'] && $machine_networks["networks"][$i]['iface2'] == $machine_networks["networks"][$j]['iface2']){
                    $virtual_interface_num++;
                    $use_virtual_interface = "yes";
                }
            }

            $netvlan = $onenet["vlan"];
            if($onenet["role"] == "ceph-cluster"){
                // Do not set gateway for the Ceph cluster network
                $addr_param = ",addr=" . $onenet["ipaddr"] . "/" . $onenet["cidr"] . ":";
            }else{
                $addr_param = ",addr=" . $onenet["ipaddr"] . "/" . $onenet["cidr"] . ":" . $onenet["gateway"];
            }

            $iface1 = get_ethname_from_network_config($con, $conf, $machine_id, $onenet["iface1"]);

            if($onenet["iface2"] != "none"){
                $iface2 = get_ethname_from_network_config($con, $conf, $machine_id, $onenet["iface2"]);
                if(is_null($netvlan)){
                    $network_params .= " --static-iface type=bond,iface0=$iface1,iface1=$iface2" . $addr_param;
                }else{
                    $network_params .= " --static-iface type=bondvlan,vlannum=$netvlan,iface0=$iface1,iface1=$iface2" . $addr_param;
                }
            }else{
                $network_params .= " --static-iface type=normal,iface0=$iface1" . $addr_param;
            }
            if($role == "compute" || $role == "network"){
                if($has_vm_net == "no" || $machine_networks["networks"][$i]["role"] == "vm-net"){
                    $network_params .= ",ovsbr=br-ex";
                    $br_ex_ovs_bridged_network = "yes";
                }
            }
            // Setup controller's interface on br-ex if there's compute nodes but no network nodes
            if($role == "controller"){
                // Get the number of compute nodes
                $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='compute'";
                $r = mysqli_query($con, $q);
                if($r === FALSE){
                    $json["status"] = "error";
                    $json["message"] = mysqli_error($con);
                    return $json;
                }
                $num_compute_nodes = mysqli_num_rows($r);

                // Get the number of compute nodes
                $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='network'";
                $r = mysqli_query($con, $q);
                if($r === FALSE){
                    $json["status"] = "error";
                    $json["message"] = mysqli_error($con);
                    return $json;
                }
                $num_network_nodes = mysqli_num_rows($r);
                if($num_compute_nodes > 0 && $num_network_nodes == 0){
                    if($has_vm_net == "no" || $machine_networks["networks"][$i]["role"] == "vm-net"){
                        $network_params .= ",ovsbr=br-ex";
                        $br_ex_ovs_bridged_network = "yes";
                    }
                }
            }
            if($onenet["mtu"] != 0){
                $network_params .= ",mtu=" . $onenet["mtu"];
                $br_ex_ovs_bridged_network = "yes";
            }
        }
    }

    // Get its cluster name and fetch the additional package list for this node
    $q = "SELECT * FROM clusters WHERE id='$cluster_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    $package_list_file = "";
    if($n == 1){
        $cluster = mysqli_fetch_array($r);
        $cluster_name = $cluster["name"];
        $package_list_path = "/var/lib/oci/clusters/$cluster_name/" . $machine["hostname"] . "/oci-packages-list";
        if(file_exists($package_list_path)){
            $package_list_file = ",";
            $package_list_file .= file_get_contents($package_list_path);
        }
    }

    $cmd  = "oci-install-with-report";
    $cmd .= $network_params;
    $cmd .= " --release ".$conf["releasenames"]["debian_release"];
    $cmd .= " --debootstrap-url ".$conf["network"]["debian_mirror"];
    $cmd .= " --sources.list-mirror ".$conf["network"]["debian_mirror"];
    $cmd .= " --security-mirror ".$conf["network"]["debian_security_mirror"];

    if($machine["install_on_raid"] == "no"){
        $cmd .= " --dest-hdd $install_hdd_name";
    }else{
        switch($machine["raid_type"]){
        case "0":
            $cmd .= " --dest-hdd raid0";
            $cmd .= " --raid-devices ".$machine["raid_dev0"].",".$machine["raid_dev1"];
            break;
        case "1":
            $cmd .= " --dest-hdd raid1";
            $cmd .= " --raid-devices ".$machine["raid_dev0"].",".$machine["raid_dev1"];
            break;
        case "10":
            $cmd .= " --dest-hdd raid10";
            $cmd .= " --raid-devices ".$machine["raid_dev0"].",".$machine["raid_dev1"].",".$machine["raid_dev2"].",".$machine["raid_dev3"];
            break;
#        case "5":
#            $cmd .= " --dest-hdd raid5";
#            $cmd .= " --raid-devices ".$machine["raid_dev0"].",".$machine["raid_dev1"].",".$machine["raid_dev2"].",".$machine["raid_dev3"];
#            break;
        default:
            echo "Not implemented yet...";
            die();
            break;
        }
    }
    $cmd .= " --no-cloud-init --extra-packages gnupg2,haveged,uuid-runtime,iotop,iftop,man-db,curl,less,lsb-release,joe,ssl-cert,most,screen,vim,vim-tiny,tcpd,xfsdump,unzip,tcpdump,ntpstat,ca-certificates,rpcbind,lftp,at,tree,lsof,bind9-host,dnsutils,strace,tmux,nano,bash-completion,openssl,ntp,file,net-tools,iproute2,ipmitool,ca-certificates,xfsprogs,e2fsprogs,parted,nmap,mtr-tiny,ladvd$package_list_file";
    $cmd .= " --hook-script /usr/bin/openstack-cluster-installer-bodi-hook-script";
    $cmd .= " --root-ssh-key /root/.ssh/authorized_keys";
    $cmd .= " --install-nonfree-repo --postinstall-packages q-text-as-data,firmware-bnx2,firmware-bnx2x,plymouth,puppet,bridge-utils,grc,ccze,ncdu,lvm2,intel-microcode,smartmontools,kexec-tools";
    if($br_ex_ovs_bridged_network == "yes"){
        $cmd .= ",openvswitch-switch";
    }
    $cmd .= " --hostname " . $machine["hostname"];
    if($machine["serial_console_dev"] != "none"){
        $cmd .= " --add-serial-getty ".$machine["serial_console_dev"];
    }
    $cmd .= " --tty-autologin yes";
    $cmd .= " --reboot-after-install";
    if($role == "swiftstore"){
        if($machine["install_on_raid"] == "no"){
            $q = "SELECT * FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '%da' ORDER BY name";
        }else{
            switch($machine["raid_type"]){
            case "0":
            case "1":
                $q = "SELECT * FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '".$machine["raid_dev0"]."' AND name NOT LIKE '".$machine["raid_dev1"]."' ORDER BY name";
                break;
            case "10":
                $q = "SELECT * FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '".$machine["raid_dev0"]."' AND name NOT LIKE '".$machine["raid_dev1"]."' AND name NOT LIKE '".$machine["raid_dev2"]."' AND name NOT LIKE '".$machine["raid_dev3"]."' ORDER BY name";
            default:
                echo "Not implemented yet...";
                die();
                break;
            }
        }
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n < 1){
            $out["status"]  = "error";
            $out["message"] = "Cannot find storage block device for this machine, and it's a swiftstore...<br>";
            return $out;
        }
        if($role == "volume"){
            $format_cmd = " --vgcreate ";
        }else{
            $format_cmd = " --xfsformat ";
        }
        for($i=0;$i<$n;$i++){
            $blockdev = mysqli_fetch_array($r);
            if($i != 0){
                $format_cmd .= ",";
            }
            $format_cmd .= $blockdev["name"];
        }
        $cmd .= $format_cmd;
    }
    $cmd .= " >/var/log/oci.log 2>&1 &";

    $out["cmd"] = $cmd;
    return $out;
}

function base64_etc_hosts($con, $conf, $machine_id){
    return base64_encode(slave_calculate_hosts_file($con, $conf, $machine_id));
}

// Calculate a /etc/hosts file containing the IP and names
// of the slave host itself, and all its peers in the cluster
function slave_calculate_hosts_file($con, $conf, $machine_id){
    $q = "SELECT * FROM machines WHERE id='$machine_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out .= "Cannot find machine: $machine_id<br>";
        return $out;
    }
    $machine = mysqli_fetch_array($r);
    $machine_fqdn     = $machine["hostname"];
    $machine_cluster  = $machine["cluster"];
    $machine_ipaddr   = $machine["ipaddr"];
    $machine_role     = $machine["role"];

    if(!is_null($machine_cluster)){
        $q = "SELECT * FROM clusters WHERE id='$machine_cluster'";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n != 1){
            $out .= "Cannot find machine: $machine_id<br>";
            return $out;
        }
        $cluster = mysqli_fetch_array($r);
        $cluster_domain       = $cluster["domain"];
        $cluster_id           = $cluster["id"];
        $cluster_name         = $cluster["name"];
        $cluster_vip_hostname = $cluster["vip_hostname"];
        if($cluster_vip_hostname == ""){
            $cluster_vip_hostname = $cluster_name ."-api." . $cluster_domain;
        }

        // Remove the hostname from the machine's FQDN
        $machine_hostname = str_replace("." . $cluster_domain, "", $machine_fqdn);

        $q = "SELECT INET_NTOA(ip) AS addr FROM ips WHERE machine='$machine_id' LIMIT 1";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n >= 1){
            $ip = mysqli_fetch_array($r);
            $machine_ipaddr = $ip["addr"];
        }
    }else{
        $machine_hostname = $machine_fqdn;
    }

    $pxe_server_hostname = gethostname();
    $pxe_server_ipaddr = $conf["network"]["OCI_IP"];

    $out = "127.0.0.1	localhost
$machine_ipaddr	$machine_fqdn $machine_hostname

# The following lines are desirable for IPv6 capable hosts
::1     localhost ip6-localhost ip6-loopback
ff02::1 ip6-allnodes
ff02::2 ip6-allrouters

# The puppet master:
$pxe_server_ipaddr	$pxe_server_hostname
";

    $out .= "# VIP address:
";

    // Fetch the API VIP ip
    if(!is_null($machine_cluster)){
        $q = "SELECT * FROM networks WHERE cluster='$machine_cluster' AND is_public='yes' AND (role='all' OR role='controller') LIMIT 1";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n == 1){
            $network = mysqli_fetch_array($r);
            $network_id   = $network["id"];
            $network_cidr = $network["cidr"];
            if($network_cidr == "32"){
                $vip_addr = $network["ip"];
                $out .= $vip_addr ." " . $cluster_name ."-api." . $cluster_domain . "\n";
            }else{
                $q = "SELECT INET_NTOA(ip) AS addr FROM ips WHERE network='$network_id' AND usefor='vip' AND vip_usage='api'";
                $r = mysqli_query($con, $q);
                $n = mysqli_num_rows($r);
                if($n == 1){
                    $vip = mysqli_fetch_array($r);
                    $vip_addr = $vip["addr"];
                    $out .= $vip_addr ." " . $cluster_name ."-api." . $cluster_domain . "\n";
                }
            }
        }
        // Fetch the SQL VIP if it exists
        $q = "SELECT * FROM networks WHERE cluster='$machine_cluster' AND is_public='no' AND role!='ovs-bridge' AND role!='vm-net' AND role!='ceph-cluster' LIMIT 1";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        if($n == 1){
            $network = mysqli_fetch_array($r);
            $network_id   = $network["id"];

            $q = "SELECT INET_NTOA(ip) AS addr FROM ips WHERE network='$network_id' AND usefor='vip' AND vip_usage='sql'";
            $r = mysqli_query($con, $q);
            $n = mysqli_num_rows($r);
            if($n == 1){
                $vip = mysqli_fetch_array($r);
                $vip_addr = $vip["addr"];
                $out .= $vip_addr ." " . $cluster_name ."-sql." . $cluster_domain . "\n";
            }
        }
    }

    $out .= "# Nodes in this cluster:
";
    if(!is_null($machine_cluster)){
        $q = "SELECT * FROM machines WHERE cluster='$machine_cluster' AND id != '$machine_id' ORDER BY role, id";
        $r = mysqli_query($con, $q);
        $n = mysqli_num_rows($r);
        for($i=0;$i<$n;$i++){
            $other_host = mysqli_fetch_array($r);
            $other_host_ipaddr   = $other_host["ipaddr"];
            $other_host_fqdn     = $other_host["hostname"];
            $other_host_hostname = str_replace("." . $cluster_domain, "", $other_host_fqdn);
            $other_host_id       = $other_host["id"];

            $q_oh = "SELECT INET_NTOA(ips.ip) AS addr FROM networks, ips WHERE ips.machine='$other_host_id' AND networks.cluster='$machine_cluster' AND networks.id=ips.network LIMIT 1";
            $r_oh = mysqli_query($con, $q_oh);
            $n_oh = mysqli_num_rows($r_oh);
            if($n_oh == 1){
                $ip_oh = mysqli_fetch_array($r_oh);
                $other_host_ipaddr = $ip_oh["addr"];
            }
            $out .= "$other_host_ipaddr	$other_host_fqdn $other_host_hostname\n";
        }
    }
    return $out;
}

function build_swift_ring($con, $conf, $cluster_id, $verbose="no"){
    $out = "";
    $q = "SELECT * FROM clusters WHERE id='$cluster_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out .= "Cannot find cluster: $cluster_id<br>";
        return $out;
    }
    $cluster = mysqli_fetch_array($r);
    $cluster_name = $cluster["name"];
    $cluster_swift_part_power     = $cluster["swift_part_power"];
    $cluster_swift_replicas       = $cluster["swift_replicas"];
    $cluster_swift_min_part_hours = $cluster["swift_min_part_hours"];

    #################################################
    ### Create the swift ring if it doesn't exist ###
    #################################################
    # First, we check if there's some swiftstore machines in the cluster, in which case
    # we do need a swift ring.

    if(!is_dir("/var/lib/oci/clusters")){
        mkdir("/var/lib/oci/clusters", 0755);
    }
    if(!is_dir("/var/lib/oci/clusters/$cluster_name")){
        mkdir("/var/lib/oci/clusters/$cluster_name", 0755);
    }


    $swift_ring_path = "/var/lib/oci/clusters/$cluster_name/swift-ring";
    if(!is_dir($swift_ring_path)){
        mkdir($swift_ring_path, 0755);
    }

    # Account
    $cmd = "swift-ring-builder $swift_ring_path/account.builder create $cluster_swift_part_power $cluster_swift_replicas $cluster_swift_min_part_hours";
    if($verbose == "yes"){ print("Creating account.builder ring: $cmd<br>\n"); ob_flush(); }else{ $out .= "Creating account.builder ring:\n   ===> $cmd\n";}
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    # Container
    $cmd = "swift-ring-builder $swift_ring_path/container.builder create $cluster_swift_part_power $cluster_swift_replicas $cluster_swift_min_part_hours";
    if($verbose == "yes"){ print("Creating container.builder ring: $cmd<br>\n"); ob_flush(); }else{ $out .= "Creating container.builder ring:\n   ===> $cmd\n";}
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    # Object
    $cmd = "swift-ring-builder $swift_ring_path/object.builder create $cluster_swift_part_power $cluster_swift_replicas $cluster_swift_min_part_hours";
    if($verbose == "yes"){ print("Creating object.builder ring: $cmd<br>\n"); ob_flush(); }else{ $out .= "Creating object.builder ring:\n   ===> $cmd\n";}
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);


    $q = "SELECT machines.id AS id, machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr, machines.location_id AS locationid, machines.install_on_raid AS install_on_raid, machines.raid_type AS raid_type, machines.raid_dev0 AS raid_dev0, machines.raid_dev1 AS raid_dev1, machines.raid_dev2 AS raid_dev2, machines.raid_dev3 AS raid_dev3 FROM ips,machines WHERE machines.cluster='$cluster_id' AND machines.role='swiftstore' AND ips.machine=machines.id ORDER BY ips.ip";
#    if($verbose == "yes"){ print("Selecting: $q<br>\n"); ob_flush(); }else{ $out .= "Selecting: $q\n";}
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    for($i=0;$i<$n;$i++){
        $machine = mysqli_fetch_array($r);
        $blockdev_locationid = $machine["locationid"];
        $blockdev_ipaddr     = $machine["ipaddr"];
        $hostname            = $machine["hostname"];
        $machine_id          = $machine["id"];
        if($machine["install_on_raid"] == "no"){
            $q = "SELECT blockdevices.name AS hddname FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '%da' ORDER BY blockdevices.name";
        }else{
            switch($machine["raid_type"]){
            case "0":
            case "1":
                $q = "SELECT blockdevices.name AS hddname FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '".$machine["raid_dev0"]."' AND name NOT LIKE '".$machine["raid_dev1"]."' ORDER BY blockdevices.name";
                break;
            case "10":
                $q = "SELECT blockdevices.name AS hddname FROM blockdevices WHERE machine_id='$machine_id' AND name NOT LIKE '".$machine["raid_dev0"]."' AND name NOT LIKE '".$machine["raid_dev1"]."' AND name NOT LIKE '".$machine["raid_dev2"]."' AND name NOT LIKE '".$machine["raid_dev3"]."' ORDER BY blockdevices.name";
                break;
            default:
            case "5":
                die("Raid configuratoin for $hostname not supported yet: RAID".$machine["raid_type"]);
                break;
            }
        }
#        if($verbose == "yes"){ print("Selecting: $q<br>\n"); ob_flush(); }else{ $out .= "Selecting: $q\n";}
        $r2 = mysqli_query($con, $q);
        $n2 = mysqli_num_rows($r2);
        $blockdev_object_port = 6200;
        for($j=0;$j<$n2;$j++){
            $blockdev = mysqli_fetch_array($r2);
            $blockdev_devicename = $blockdev["hddname"];

            $q = "SELECT swiftregions.id AS swiftregion FROM locations,swiftregions WHERE locations.id='$blockdev_locationid' AND swiftregions.name=locations.swiftregion";
            $rsw = mysqli_query($con, $q);
            $nsw = mysqli_num_rows($rsw);
            if($nsw == 0){
                $swiftregion = "0";
            }else{
                $asr = mysqli_fetch_array($rsw);
                $swiftregion = $asr["swiftregion"] + 1;
            }

            # Account
            $cmd = "swift-ring-builder $swift_ring_path/account.builder add --region $swiftregion --zone $blockdev_locationid --ip $blockdev_ipaddr --port 6002 --device $blockdev_devicename --weight 100";
            if($verbose == "yes"){ print("Adding $hostname, $blockdev_devicename to the account ring: $cmd<br>\n"); ob_flush(); }else{ $out .= "Adding $hostname, $blockdev_devicename to the account ring:\n   ===> $cmd\n";}
            $output = array();
            $return_var = 0;
            exec($cmd, $output, $return_var);

            # Container
            $cmd = "swift-ring-builder $swift_ring_path/container.builder add --region $swiftregion --zone $blockdev_locationid --ip $blockdev_ipaddr --port 6001 --device $blockdev_devicename --weight 100";
            if($verbose == "yes"){ print("Adding $hostname, $blockdev_devicename to the container ring: $cmd<br>\n"); ob_flush(); }else{ $out .= "Adding $hostname, $blockdev_devicename to the container ring:\n   ===> $cmd\n";}
            $output = array();
            $return_var = 0;
            exec($cmd, $output, $return_var);

            # Object
            $cmd = "swift-ring-builder $swift_ring_path/object.builder add --region $swiftregion --zone $blockdev_locationid --ip $blockdev_ipaddr --port $blockdev_object_port --device $blockdev_devicename --weight 100";
            if($verbose == "yes"){ print("Adding $hostname, $blockdev_devicename to the object ring: $cmd<br>\n"); ob_flush(); }else{ $out .= "Adding $hostname, $blockdev_devicename to the object ring:\n   ===> $cmd\n";}
            $output = array();
            $return_var = 0;
            exec($cmd, $output, $return_var);

            $blockdev_object_port += 1;
        }
    }

    # Account
    $cmd = "swift-ring-builder $swift_ring_path/account.builder rebalance";
    if($verbose == "yes"){ print("Rebalancing account ring: $cmd<br>\n"); ob_flush(); }else{ $out .= "Rebalancing account ring:\n   ===> $cmd\n";}
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    # Container
    $cmd = "swift-ring-builder $swift_ring_path/container.builder rebalance";
    if($verbose == "yes"){ print("Rebalancing container ring: $cmd<br>"); ob_flush(); }else{ $out .= "Rebalancing container ring:\n   ===> $cmd\n";}
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    # Object
    $cmd = "swift-ring-builder $swift_ring_path/object.builder rebalance";
    if($verbose == "yes"){ print("Rebalancing object ring: $cmd<br>\n"); ob_flush(); }else{ $out .= "Rebalancing object ring:\n   ===> $cmd\n";}
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);
    if($verbose == "yes"){ print("All done!"); }else{ $out .= "All done!";}

    $q = "SELECT machines.hostname AS hostname, INET_NTOA(ips.ip) AS ipaddr FROM ips,machines WHERE machines.cluster='$cluster_id' AND (machines.role='swiftstore' OR machines.role='swiftproxy') AND ips.machine=machines.id ORDER BY ips.ip";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    $script = "#!/bin/sh
set -e

";
    for($i=0;$i<$n;$i++){
        $machine = mysqli_fetch_array($r);
        $ipaddr   = $machine["ipaddr"];
        $hostname = $machine["hostname"];
        $script .= "echo \"===> Copying ring to: $hostname\"\n";
        $script .= "scp $swift_ring_path/account.builder $swift_ring_path/container.builder $swift_ring_path/object.builder $swift_ring_path/account.ring.gz $swift_ring_path/container.ring.gz $swift_ring_path/object.ring.gz $ipaddr:/etc/swift\n";
        $script .= "ssh $ipaddr \"chown swift:swift /etc/swift/account.ring.gz /etc/swift/container.ring.gz /etc/swift/object.ring.gz\"\n";
    }
    file_put_contents("$swift_ring_path/scp-ring", $script);
    chmod("$swift_ring_path/scp-ring", "0755");

    return $out;
}

function ipmi_set_boot_device($con, $conf, $machine_id, $bootdev){
    $q = "SELECT * FROM machines WHERE id='$machine_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out .= "Cannot find machine: $machine_id<br>";
        return $out;
    }
    $machine = mysqli_fetch_array($r);

    $ipmi_use                  = $machine["ipmi_use"];
    $ipmi_call_chassis_bootdev = $machine["ipmi_call_chassis_bootdev"];
    $ipmi_addr                 = $machine["ipmi_addr"];
    $ipmi_port                 = $machine["ipmi_port"];
    $ipmi_username             = $machine["ipmi_username"];
    $ipmi_password             = $machine["ipmi_password"];

    if($ipmi_use == "yes" && $ipmi_call_chassis_bootdev == "yes"){
        $cmd = "ipmitool -I lanplus -H $ipmi_addr -p $ipmi_port -U $ipmi_username -P $ipmi_password chassis bootdev $bootdev options=persistent";
        $output = "";
        $return_var = 0;
        exec($cmd, $output, $return_var);
    }
}

function slave_install_os($con, $conf, $machine_id, $install_cmd){
    $q = "SELECT * FROM machines WHERE id='$machine_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out .= "Cannot find machine: $machine_id<br>";
        return $out;
    }
    $machine = mysqli_fetch_array($r);
    $machine_ipaddr   = $machine["ipaddr"];
    $machine_hostname = $machine["hostname"];
    $machine_role     = $machine["role"];
    $cluster_id       = $machine["cluster"];

    // Set boot device if using that IPMI option
    ipmi_set_boot_device($con, $conf, $machine_id, "disk");

    $q = "SELECT * FROM clusters WHERE id='$cluster_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out .= "Cannot find cluster: $cluster_id<br>";
        return $out;
    }
    $cluster = mysqli_fetch_array($r);
    $cluster_name = $cluster["name"];

    #######################################################
    ### Create a folder for filesystem template of host ###
    #######################################################
    $template_path = "/var/lib/oci/clusters/$cluster_name/$machine_hostname";
    if(!is_dir("/var/lib/oci/clusters")){
        mkdir("/var/lib/oci/clusters", 0755);
    }
    if(!is_dir("/var/lib/oci/clusters/$cluster_name")){
        mkdir("/var/lib/oci/clusters/$cluster_name", 0755);
    }
    if(!is_dir("/var/lib/oci/clusters/$cluster_name/$machine_hostname")){
        mkdir("/var/lib/oci/clusters/$cluster_name/$machine_hostname", 0755);
    }

    #########################
    ### Manage /etc/hosts ###
    #########################
    // Calculate and scp the /etc/hosts file
    $host_file = slave_calculate_hosts_file($con, $conf, $machine_id);
    file_put_contents("$template_path/oci-hosts-file", $host_file);

    #################################################################
    ### Manage puppet-master hostname file and client certificate ###
    #################################################################
    // Send the puppet-master hostname to /puppet-master-host
    file_put_contents("$template_path/puppet-master-host", gethostname());

    // Delete a probably already existing cert
    $cmd = "sudo /usr/bin/puppet cert clean $machine_hostname";
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    // Generate puppet certificates
    $cmd = "sudo /usr/bin/puppet ca generate $machine_hostname";
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    $cmd = "sudo /usr/bin/oci-copy-slave-node-generate-key $machine_hostname $template_path 2>&1";
    exec($cmd, $output, $return_var);

    # We keep only the signed certificate, everything else can
    # go away from the puppet-master.
    $cmd = "sudo /usr/bin/oci-remove-slave-node-generated-key $machine_hostname";
    exec($cmd, $output, $return_var);

    #######################################################
    ### Manage PKI x509 slave node certificate and keys ###
    #######################################################
    # Copy all of the CA's .pem files
    $ca_pem_dir = "/var/lib/oci/ssl/ca";
    if (is_dir($ca_pem_dir)) {
        if ($dh = opendir($ca_pem_dir)) {
            while (($file = readdir($dh)) !== false) {
                if(filetype($ca_pem_dir . "/" . $file) == "file"){
                    copy("$ca_pem_dir/$file", "$template_path/$file");
                }
            }
            closedir($dh);
        }
    }

    # Copy the server's private/public keypair
    $node_keys_dir = "/var/lib/oci/ssl/slave-nodes/$machine_hostname";
    if (is_dir($node_keys_dir)) {
        if ($dh = opendir($node_keys_dir)) {
            while (($file = readdir($dh)) !== false) {
                if(filetype($node_keys_dir . "/" . $file) == "file"){
                    copy("$node_keys_dir/$file", "$template_path/$file");
                }
            }
            closedir($dh);
        }
    }

    # If the machine is a controller, then haproxy will need the API SSL keys
    # to be used in haproxy.
    $api_keys = "/var/lib/oci/ssl/slave-nodes/$machine_hostname";
    $q = "SELECT * FROM clusters WHERE id='$cluster_id'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 1){
        $out .= "Cannot find machine: $machine_id<br>";
        return $out;
    }
    $cluster = mysqli_fetch_array($r);
    $cluster_name            = $cluster["name"];
    $cluster_domain          = $cluster["domain"];
    $cluster_vip_hostname    = $cluster["vip_hostname"];
    $first_master_machine_id = $cluster["first_master_machine_id"];
    $cluster_swift_proxy_hostname = $cluster["swift_proxy_hostname"];

    if($cluster_vip_hostname == ""){
        $api_hostname = $cluster_name . "-api." . $cluster_domain;
    }else{
        $api_hostname = $cluster_vip_hostname;
    }

    # Copy the API key
    $api_keys_dir = "/var/lib/oci/ssl/slave-nodes/$api_hostname";
    if (is_dir($api_keys_dir)) {
        if ($dh = opendir($api_keys_dir)) {
            while (($file = readdir($dh)) !== false) {
                if(filetype($api_keys_dir . "/" . $file) == "file"){
                    switch($file){
                    case "$api_hostname.key":
                        // Only controllers need the private key
                        if($machine_role == "controller"){
                            copy("$api_keys_dir/$file", "$template_path/oci-pki-api.key");
                        }
                        break;
                    case "$api_hostname.crt":
                        // But everyone needs the cert
                        copy("$api_keys_dir/$file", "$template_path/oci-pki-api.crt");
                        break;
                    case "$api_hostname.csr":
                        // But everyone needs the cert
                        copy("$api_keys_dir/$file", "$template_path/oci-pki-api.csr");
                        break;
                    case "$api_hostname.pem":
                        // But everyone needs the cert
                        copy("$api_keys_dir/$file", "$template_path/oci-pki-api.pem");
                        break;
                    default:
                        break;
                    }
                }
            }
            closedir($dh);
        }
    }

    mkdir("$template_path/oci-in-target");
    mkdir("$template_path/oci-in-target/etc");

    #############################
    ### Manage the swift ring ###
    #############################
    $swift_ring_path = "/var/lib/oci/clusters/$cluster_name/swift-ring";

    # Only build the swift ring if there's some swiftstore nodes.
    $q = "SELECT * FROM machines WHERE cluster='$cluster_id' AND role='swiftstore'";
    $r = mysqli_query($con, $q);
    $n = mysqli_num_rows($r);
    if($n != 0){
        if(file_exists("$swift_ring_path/account.ring.gz") === FALSE || file_exists("$swift_ring_path/container.ring.gz") === FALSE ||file_exists("$swift_ring_path/object.ring.gz") === FALSE){
            build_swift_ring($con, $conf, $cluster_id);
        }
    }

    if($machine_role == "swiftstore" || $machine_role == "swiftproxy"){
        mkdir("$template_path/oci-in-target/etc/swift");
        copy("$swift_ring_path/account.ring.gz", "$template_path/oci-in-target/etc/swift/account.ring.gz");
        copy("$swift_ring_path/container.ring.gz", "$template_path/oci-in-target/etc/swift/container.ring.gz");
        copy("$swift_ring_path/object.ring.gz", "$template_path/oci-in-target/etc/swift/object.ring.gz");
    }

    # Copy the cert+key if using a custom swiftproxy URL
    if($machine_role == "swiftproxy"){
        if($cluster_swift_proxy_hostname != ""){
            $swift_proxy_key_dir = "/var/lib/oci/ssl/slave-nodes/$cluster_swift_proxy_hostname";
            if (is_dir($swift_proxy_key_dir)) {
                if ($dh = opendir($swift_proxy_key_dir)) {
                    while (($file = readdir($dh)) !== false) {
                        if(filetype($swift_proxy_key_dir . "/" . $file) == "file"){
                            switch($file){
                            case "$cluster_swift_proxy_hostname.key":
                                copy("$swift_proxy_key_dir/$file", "$template_path/oci-pki-swiftproxy.key");
                                break;
                            case "$cluster_swift_proxy_hostname.crt":
                                copy("$swift_proxy_key_dir/$file", "$template_path/oci-pki-swiftproxy.crt");
                                break;
                            case "$cluster_swift_proxy_hostname.csr":
                                copy("$swift_proxy_key_dir/$file", "$template_path/oci-pki-swiftproxy.csr");
                                break;
                            case "$cluster_swift_proxy_hostname.pem":
                                copy("$swift_proxy_key_dir/$file", "$template_path/oci-pki-swiftproxy.pem");
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    ############################
    ### Manage the /etc/motd ###
    ############################
    if( file_exists("/etc/openstack-cluster-installer/motd") ){
        $motd_content = file_get_contents("/etc/openstack-cluster-installer/motd");
    }else{
        $motd_content = "";
    }

    $motd_content .= "
Welcome to $machine_hostname.
This OS was installed using OCI:
https://salsa.debian.org/openstack-team/debian/openstack-cluster-installer

";
    file_put_contents("$template_path/oci-in-target/etc/motd", $motd_content);

    ##################################
    ### Copy /usr/bin/oci-make-osd ###
    ##################################
    if($machine_role == "cephosd"){
        mkdir("$template_path/oci-in-target/usr");
        mkdir("$template_path/oci-in-target/usr/bin");
        copy("/usr/bin/oci-make-osd", "$template_path/oci-in-target/usr/bin/oci-make-osd");
        chmod("$template_path/oci-in-target/usr/bin/oci-make-osd",0755);
    }

    ###########################################################
    ### Copy the cluster's ssh keypair if it's a controller ###
    ###########################################################
    if($machine_role == "controller"){
        mkdir("$template_path/oci-in-target/usr");
        mkdir("$template_path/oci-in-target/usr/bin");
        $ssh_key_dir = "/var/lib/oci/clusters/$cluster_name/ssh";
        if(file_exists("$ssh_key_dir/id_rsa")){
            mkdir("$template_path/oci-in-target/root");
            mkdir("$template_path/oci-in-target/root/.ssh", 0700);
            copy("$ssh_key_dir/id_rsa", "$template_path/oci-in-target/root/.ssh/id_rsa");
            if(file_exists("$ssh_key_dir/id_rsa.pub")){
                copy("$ssh_key_dir/id_rsa.pub", "$template_path/oci-in-target/root/.ssh/id_rsa.pub");
            }
        }
        # Add fernet rotation script if it's the first master server
        if($machine_id == $first_master_machine_id){
            mkdir("$template_path/oci-in-target/etc/cron.weekly");
            copy("/usr/bin/oci-fernet-keys-rotate", "$template_path/oci-in-target/etc/cron.weekly/oci-fernet-keys-rotate");
            mkdir("$template_path/oci-in-target/etc/cron.hourly");
            copy("/usr/bin/oci-glance-image-rsync", "$template_path/oci-in-target/etc/cron.hourly/oci-glance-image-rsync");
        }
        copy("/usr/bin/oci-auto-join-rabbitmq-cluster", "$template_path/oci-in-target/usr/bin/oci-auto-join-rabbitmq-cluster");
        chmod("$template_path/oci-in-target/usr/bin/oci-auto-join-rabbitmq-cluster",0755);

        # This is for wait operations to make sure the SQL cluster is up...
        copy("/usr/bin/oci-wait-for-sql", "$template_path/oci-in-target/usr/bin/oci-wait-for-sql");

        # Octavia's helper scripts
        copy("/usr/bin/oci-octavia-amphora-secgroups-sshkey-lbrole-and-network", "$template_path/oci-in-target/usr/bin/oci-octavia-amphora-secgroups-sshkey-lbrole-and-network");
        copy("/usr/bin/oci-octavia-certs", "$template_path/oci-in-target/usr/bin/oci-octavia-certs");
    }

    ###################################
    ### Copy files for the SQL role ###
    ###################################
    if($machine_role == "sql"){
        mkdir("$template_path/oci-in-target/usr");
        mkdir("$template_path/oci-in-target/usr/bin");
        # This is for wait operations to make sure the SQL cluster is up...
        copy("/usr/bin/oci-wait-for-sql", "$template_path/oci-in-target/usr/bin/oci-wait-for-sql");
    }

    ##################################################################
    ### If the machine is a swiftstore, we need the facts.d helper ###
    ##################################################################
    if($machine_role == "swiftstore" || $machine_role == "cephosd"){
        mkdir("$template_path/oci-in-target/etc/facter");
        mkdir("$template_path/oci-in-target/etc/facter/facts.d");
        copy("/etc/facter/facts.d/swift_blockdevs_names_to_uuid.sh", "$template_path/oci-in-target/etc/facter/facts.d/swift_blockdevs_names_to_uuid.sh");
        copy("/etc/facter/facts.d/swift_fstab_dev_list.sh", "$template_path/oci-in-target/etc/facter/facts.d/swift_fstab_dev_list.sh");
        copy("/usr/bin/oci-hdd-maint", "$template_path/oci-in-target/usr/bin/oci-hdd-maint");
    }

    #############################################################################################
    ### If the machine is a swiftstore, copy the maintenance script for replacing broken HDDs ###
    #############################################################################################
    if($machine_role == "swiftstore"){
        mkdir("$template_path/oci-in-target/usr");
        mkdir("$template_path/oci-in-target/usr/bin");
        copy("/usr/bin/oci-hdd-maint", "$template_path/oci-in-target/usr/bin/oci-hdd-maint");
        chmod("$template_path/oci-in-target/usr/bin/oci-hdd-maint",0755);
    }

    #############################################################
    ### If the machine is a compute, we need the fixup script ###
    #############################################################
    if($machine_role == "compute"){
        mkdir("$template_path/oci-in-target/usr");
        mkdir("$template_path/oci-in-target/usr/bin");
        copy("/usr/bin/oci-fixup-compute-node", "$template_path/oci-in-target/usr/bin/oci-fixup-compute-node");
        chmod("$template_path/oci-in-target/usr/bin/oci-fixup-compute-node",0755);
        copy("/usr/bin/oci-build-nova-instances-vg", "$template_path/oci-in-target/usr/bin/oci-build-nova-instances-vg");
        chmod("$template_path/oci-in-target/usr/bin/oci-build-nova-instances-vg",0755);
        copy("/usr/bin/oci-fix-nova-ssh-config", "$template_path/oci-in-target/usr/bin/oci-fix-nova-ssh-config");
        chmod("$template_path/oci-in-target/usr/bin/oci-fix-nova-ssh-config",0755);
    }

    ###################
    ### Volume node ###
    ###################
    if($machine_role == "volume"){
        mkdir("$template_path/oci-in-target/usr");
        mkdir("$template_path/oci-in-target/usr/bin");
        copy("/usr/bin/oci-build-cinder-volume-vg", "$template_path/oci-in-target/usr/bin/oci-build-cinder-volume-vg");
        chmod("$template_path/oci-in-target/usr/bin/oci-build-cinder-volume-vg",0755);
    }

    #################################################
    ### Copy the gpg pubkey of all backport repos ###
    #################################################
    copy("/etc/openstack-cluster-installer/pubkey.gpg", "$template_path/oci-backports-pubkey.gpg");

    ###########################################################################
    ### Create a tarball from the $template_path folder, scp it, extract it ###
    ###########################################################################
    $cmd = "cd /var/lib/oci/clusters/$cluster_name/$machine_hostname && tar -C /var/lib/oci/clusters/$cluster_name/$machine_hostname -cvzf /var/lib/oci/clusters/$cluster_name/$machine_hostname.tar.gz *";
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);

    scp_a_file($conf, $con, $machine_ipaddr, "/var/lib/oci/clusters/$cluster_name/$machine_hostname.tar.gz", "/oci-to-extract-tarball.tar.gz", 0644);
    $ret = send_ssh_cmd($conf, $con, $machine_ipaddr, "tar -C / -xvzf /oci-to-extract-tarball.tar.gz");

    #####################################
    ### Perform the actual OS install ###
    #####################################
    $ret = send_ssh_cmd($conf, $con, $machine_ipaddr, $install_cmd);
}

?>
