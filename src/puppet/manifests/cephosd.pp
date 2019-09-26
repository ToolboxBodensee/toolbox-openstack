# Install and configure a CEPH OSD node for OCI
#
# == Parameters
#
# [*bootstrap_osd_key*]
# example: 'AQABsWZSgEDmJhAAkAGSOOAJwrMHrM5Pz5On1A=='
#
# [*fsid*]
# example: '066F558C-6789-4A93-AAF1-5AF1BA01A3AD'
#
# [*ceph_mon_initial_members*]
# example: 'mon1,mon2,mon3'
#
# [*ceph_mon_host*]
# example: '<ip of mon1>,<ip of mon2>,<ip of mon3>'
#
class oci::cephosd(
  $machine_hostname         = undef,
  $machine_ip               = undef,
  $etc_hosts                = undef,
  $time_server_host         = undef,
  $all_masters              = undef,
  $all_masters_ip           = undef,
  $vip_hostname             = undef,
  $vip_ipaddr               = undef,
  $vip_netmask              = undef,
  $network_ipaddr           = undef,
  $network_cidr             = undef,
  $use_ssl                  = true,

  $block_devices            = undef,

  $ceph_admin_key           = undef,
  $ceph_openstack_key       = undef,
  $ceph_mon_key             = undef,
  $ceph_bootstrap_osd_key   = undef,
  $ceph_fsid                = undef,
  $ceph_mon_initial_members = undef,
  $ceph_mon_host            = undef,

  $use_ceph_cluster_net     = false,
  $cephnet_ip               = undef,
  $cephnet_network_addr     = undef,
  $cephnet_network_cidr     = undef,
  $cephnet_mtu              = undef,
){

  ::oci::sysctl { 'oci-rox': }

  class { '::oci::etchosts': etc_hosts_file => $etc_hosts, }

  # Right on time!
  class { '::oci::chrony': time_server_host => $time_server_host, }

  if $use_ceph_cluster_net {
    $cluster_network = "${cephnet_network_addr}/${cephnet_network_cidr}"
    $cluster_ip = $cephnet_ip
  }else{
    $cluster_network = undef
    $cluster_ip = ""
  }

  class { 'ceph':
    fsid                => $ceph_fsid,
    ensure              => 'present',
    authentication_type => 'cephx',
    mon_initial_members => $ceph_mon_initial_members,
    mon_host            => $ceph_mon_host,
    public_network      => "${network_ipaddr}/${network_cidr}",
    cluster_network     => $cluster_network,
  }->
  ceph::key { 'client.admin':
    secret  => $ceph_admin_key,
    cap_mon => 'allow *',
    cap_osd => 'allow *',
    cap_mds => 'allow',
  }->
  ceph::key { 'client.openstack':
    secret  => $ceph_openstack_key,
    mode    => '0644',
    cap_mon => 'profile rbd',
    cap_osd => 'profile rbd pool=cinder, profile rbd pool=nova, profile rbd pool=glance, profile rbd pool=gnocchi, profile rbd pool=cinderback',
  }->
  ceph::key { 'client.bootstrap-osd':
    secret       => $ceph_bootstrap_osd_key,
    keyring_path => '/var/lib/ceph/bootstrap-osd/ceph.keyring',
    cap_mon      => 'allow profile bootstrap-osd',
  }

  $block_devices.each |Integer $index, String $value| {
    exec { "create-osd-${value}":
      command => "/usr/bin/oci-make-osd ${value} ${cluster_ip} # comment to satisfy puppet syntax requirements",
      unless  => '/bin/false # comment to satisfy puppet syntax requirements',
      require => Ceph::Key['client.bootstrap-osd'],
    }
  }
}
