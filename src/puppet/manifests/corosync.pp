class oci::corosync(
  $machine_ip      = undef,
  $all_machine_ips = undef,
  $all_machine_ids = undef,
){
  class { 'corosync':
    authkey                  => '/var/lib/puppet/ssl/certs/ca.pem',
    bind_address             => $machine_ip,
    unicast_addresses        => $all_machine_ips,
    cluster_name             => 'sqlcluster',
    enable_secauth           => true,
    set_votequorum           => true,
    quorum_members           => $all_machine_ips,
    quorum_members_ids       => $all_machine_ids,
    log_stderr               => false,
    log_function_name        => true,
    syslog_priority          => 'debug',
    debug                    => true,
  }
  corosync::service { 'pacemaker':
    version => '0',
  }->
  cs_property { 'stonith-enabled':
    value   => 'false',
  }->
  cs_property { 'no-quorum-policy':
    value   => 'stop',
  }
}