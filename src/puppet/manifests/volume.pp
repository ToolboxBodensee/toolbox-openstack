# Install and configure a CEPH MON node for OCI
#
# == Parameters
#
# [*block_devices*]
# List of block devices the volume node can use as LVM backend.
class oci::volume(
  $machine_hostname         = undef,
  $machine_ip               = undef,
  $etc_hosts                = undef,
  $time_server_host         = undef,
  $first_master             = undef,
  $first_master_ip          = undef,
  $all_masters              = undef,
  $all_masters_ip           = undef,
  $vip_hostname             = undef,
  $vip_ipaddr               = undef,
  $vip_netmask              = undef,
  $sql_vip_ip               = undef,
  $network_ipaddr           = undef,
  $network_cidr             = undef,
  $use_ssl                  = true,

  $block_devices            = undef,
  $vgname                   = undef,
  $backup_backend           = undef,

  $pass_cinder_db           = undef,
  $pass_cinder_authtoken    = undef,
  $pass_cinder_messaging    = undef,
){

  if $use_ssl {
    $proto = 'https'
    $messaging_default_port = '5671'
    $messaging_notify_port = '5671'
    $api_port = 443
  } else {
    $proto = 'http'
    $messaging_default_port = '5672'
    $messaging_notify_port = '5672'
    $api_port = 80
  }
  $messaging_default_proto = 'rabbit'
  $messaging_notify_proto = 'rabbit'

  $base_url = "${proto}://${vip_hostname}"
  $keystone_auth_uri  = "${base_url}:${api_port}/identity"
  $keystone_admin_uri = "${base_url}:${api_port}/identity-admin"

  ::oci::sysctl { 'oci-rox': }

  class { '::oci::etchosts': etc_hosts_file => $etc_hosts, }

  # Right on time!
  class { '::oci::chrony': time_server_host => $time_server_host, }

  include ::cinder::client
  # Cinder main class (ie: cinder-common config)
  class { '::cinder':
    default_transport_url => os_transport_url({
      'transport' => $messaging_default_proto,
      'hosts'     => fqdn_rotate($all_masters),
      'port'      => $messaging_default_port,
      'username'  => 'cinder',
      'password'  => $pass_cinder_messaging,
    }),
    # TODO: Fix hostname !
    database_connection   => "mysql+pymysql://cinder:${pass_cinder_db}@${sql_vip_ip}/cinderdb?charset=utf8",
    rabbit_use_ssl         => $use_ssl,
    rabbit_ha_queues       => true,
    kombu_ssl_ca_certs     => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    amqp_sasl_mechanisms   => 'PLAIN',
    debug                 => true,
  }

  case $backup_backend {
    'ceph': {
      $backup_backend_real = 'cinder.backup.drivers.ceph.CephBackupDriver'
      $provision_cinder_backup = false
    }
    'swift': {
      $backup_backend_real = 'cinder.backup.drivers.swift.SwiftBackupDriver'
      $provision_cinder_backup = true
    }
    'none': {
      $backup_backend_real = ''
      $provision_cinder_backup = false
    }
  }


  cinder_config {
    'nova/cafile':                       value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'service_user/cafile':               value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'DEFAULT/backup_driver':             value => $backup_backend_real;
    'DEFAULT/snapshot_clone_size':       value => '200';
    'DEFAULT/backup_swift_auth_url':     value => $keystone_auth_uri;
    'DEFAULT/backup_swift_ca_cert_file': value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'ssl/ca_file':                       value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
  }

  # Configure the authtoken
  class { '::cinder::keystone::authtoken':
    password             => $pass_cinder_authtoken,
    auth_url             => $keystone_admin_uri,
    www_authenticate_uri => $keystone_auth_uri,
    memcached_servers    => $memcached_servers,
    cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
  }

  # Clear volumes on delete (for data security)
  class { '::cinder::volume':
    volume_clear => 'zero',
  }

  # A cinder-backup service on each volume nodes
  if $provision_cinder_backup {
    class { '::cinder::backup': }
  }

  # Avoids Cinder to lookup for the catalogue
  class { '::cinder::glance':
    glance_api_servers => "${base_url}/image",
  }

  # Configure the LVM backend
  cinder::backend::iscsi { 'LVM_1':
    iscsi_ip_address   => $machine_ip,
    volume_group       => $vgname,
    iscsi_protocol     => 'iscsi',
    extra_options      => {
    	'LVM_1/reserved_percentage' => { 'value' => '10' },
    	'LVM_1/volume_clear_size' => { 'value' => '90' }
    },
    manage_volume_type => true,
  }

  class { '::cinder::backends':
    enabled_backends => ['LVM_1'],
  }

}
