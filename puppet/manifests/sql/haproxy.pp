# Creates the haproxy front-end and back-end for a MariaDB galera cluster
#
# == Parameters
#
# [*sql_vip_ip*]
#   (required) IP address of the virtual IP used to connect to MariaDB
#
# [*first_sql*]
#   (required) Hostname of the non-backup SQL node
#
# [*first_sql_ip*]
#   (required) IP address of the non-backup SQL node
#
# [*non_master_sql*]
#   (required) Hostnames of the backup SQL nodes
#
# [*non_master_sql_ip*]
#   (required) IP addresses of the backup SQL nodes
#
class oci::sql::haproxy(
  $sql_vip_ip        = undef,
  $first_sql         = undef,
  $first_sql_ip      = undef,
  $non_master_sql    = undef,
  $non_master_sql_ip = undef,
){
  haproxy::frontend { 'galerafe':
    mode      => 'tcp',
    bind      => { "${sql_vip_ip}:3306" => [] },
    options   => [
      { 'timeout'         => 'client 3600s'},
      { 'default_backend' => 'galerabe'},
    ],
  }

  haproxy::backend { 'galerabe':
    options => [
       { 'mode'    => 'tcp' },
       { 'balance' => 'roundrobin' },
       { 'timeout' => 'check 5000' },
       { 'timeout' => 'server 3600s' },
    ],
  }
  haproxy::balancermember { 'galerabm':
    listening_service => 'galerabe',
    ipaddresses       => $first_sql_ip,
    server_names      => $first_sql,
    options           => 'check inter 4000 port 9200 fall 3 rise 5',
  }
  haproxy::balancermember { 'galerabmback':
    listening_service => 'galerabe',
    ipaddresses       => $non_master_sql_ip,
    server_names      => $non_master_sql,
    options           => 'check inter 4000 port 9200 fall 3 rise 5 backup',
  }
}