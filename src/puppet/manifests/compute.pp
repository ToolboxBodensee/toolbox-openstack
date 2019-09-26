class oci::compute(
  $openstack_release        = undef,
  $cluster_name             = undef,
  $machine_hostname         = undef,
  $machine_ip               = undef,
  $etc_hosts                = undef,
  $time_server_host         = undef,
  $bridge_mapping_list      = undef,
  $external_network_list    = undef,
  $first_master             = undef,
  $first_master_ip          = undef,
  $cluster_domain           = undef,
  $vmnet_ip                 = undef,
  $vmnet_mtu                = undef,
  $all_masters              = undef,
  $all_masters_ip           = undef,
  $vip_hostname             = undef,
  $vip_ipaddr               = undef,
  $vip_netmask              = undef,
  $sql_vip_ip               = undef,
  $network_ipaddr           = undef,
  $network_cidr             = undef,
  $use_ssl                  = true,
  $pass_nova_messaging      = undef,
  $pass_nova_authtoken      = undef,
  $pass_nova_ssh_pub        = undef,
  $pass_nova_ssh_priv       = undef,
  $pass_neutron_authtoken   = undef,
  $pass_metadata_proxy_shared_secret = undef,
  $pass_neutron_messaging   = undef,
  $pass_placement_authtoken = undef,

  $has_subrole_ceilometer   = false,
  $pass_ceilometer_messaging = undef,
  $pass_ceilometer_authtoken = undef,
  $pass_ceilometer_telemetry = undef,

  $pass_cinder_db           = undef,
  $pass_cinder_authtoken    = undef,
  $pass_cinder_messaging    = undef,

  $cluster_has_osds         = undef,
  $use_ceph_if_available    = 'no',
  $ceph_fsid                = undef,
  $ceph_libvirtuuid         = undef,
  $ceph_bootstrap_osd_key   = undef,
  $ceph_admin_key           = undef,
  $ceph_openstack_key       = undef,
  $ceph_mon_key             = undef,
  $ceph_mon_initial_members = undef,
  $ceph_mon_host            = undef,

  $disable_notifications    = false,
  $enable_monitoring_graphs = false,
  $monitoring_graphite_host = undef,
  $monitoring_graphite_port = '2003',
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
  $keystone_admin_uri = "${base_url}:${api_port}/identity"

  # Some useful sysctl customization
  ::oci::sysctl { 'oci-rox': }

  class { '::oci::etchosts': etc_hosts_file => $etc_hosts, }

  # Right on time!
  class { '::oci::chrony': time_server_host => $time_server_host, }

  # We need haproxy for proxying the metadata proxy server
  # because of TLS + Eventlet + Python 3
  class { 'haproxy':
    global_options   => {
      'maxconn' => '256',
      'user'    => 'haproxy',
      'group'   => 'haproxy',
      'daemon'  => '',
      'nbproc'  => '4',
    },
    defaults_options => {
      'mode'    => 'http',
    },
    merge_options => true,
  }

  $haproxy_options_for_metadata_proxy = [
    { 'use_backend' => 'metadatabe' },
  ]
  haproxy::frontend { 'openstackfe':
    mode      => 'http',
    bind      => { "127.0.0.1:8775" => [] },
    options   => $haproxy_options_for_metadata_proxy,
  }
  haproxy::backend { 'metadatabe':
    options => [
       { 'option' => 'forwardfor' },
       { 'mode' => 'http' },
       { 'balance' => 'roundrobin' },
    ],
  }
  haproxy::balancermember { 'metadatabm':
    listening_service => 'metadatabe',
    ipaddresses       => $all_masters_ip,
    server_names      => $all_masters,
    ports             => 8775,
#    options           => 'check',
  }

  ############
  ### Nova ###
  ############
  if $disable_notifications {
    $nova_notif_transport_url = ''
  } else {
    $nova_notif_transport_url = os_transport_url({
                                  'transport' => $messaging_notify_proto,
                                  'hosts'     => fqdn_rotate($all_masters),
                                  'port'      => $messaging_notify_port,
                                  'username'  => 'nova',
                                  'password'  => $pass_nova_messaging,
                                })
  }
  class { '::nova':
    default_transport_url      => os_transport_url({
      'transport' => $messaging_default_proto,
      'hosts'     => fqdn_rotate($all_masters),
      'port'      => $messaging_default_port,
      'username'  => 'nova',
      'password'  => $pass_nova_messaging,
    }),
    notification_transport_url => $nova_notif_transport_url,
    database_connection    => '',
    rabbit_use_ssl         => $use_ssl,
    rabbit_ha_queues       => true,
    kombu_ssl_ca_certs     => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    amqp_sasl_mechanisms   => 'PLAIN',
    use_ipv6               => false,
    glance_api_servers     => "${base_url}/image",
    notification_driver    => 'messagingv2',
    notify_on_state_change => 'vm_and_task_state',
    nova_public_key        => { type => 'ssh-rsa', key => $pass_nova_ssh_pub },
    nova_private_key       => { type => 'ssh-rsa', key => base64('decode', $pass_nova_ssh_priv) },
  }

  class { '::nova::keystone::authtoken':
    password             => $pass_nova_authtoken,
    auth_url             => $keystone_admin_uri,
    www_authenticate_uri => $keystone_auth_uri,
    cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
  }

  # The +0 is there for converting the string to an int,
  # 1536 is for the PoC, 8192 is for real deployments where we do expect
  # hosts to hold more than 16GB of RAM.
  if (($::memorysize_mb + 0) < 16000) {
    $reserved_host_memory_mb = 1536
  }else{
    $reserved_host_memory_mb = 8192
  }

  nova_config {
    'placement/cafile':          value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'placement/auth_url':        value => $keystone_admin_uri;
    'placement/region_name':     value => 'RegionOne';
    'placement/password':        value => $pass_placement_authtoken;
    'placement/project_name':    value => 'services';
    'glance/cafile':             value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'keystone/cafile':           value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'neutron/cafile':            value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'cinder/cafile':             value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'service_user/cafile':       value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'DEFAULT/use_cow_images':    value => 'False';
    'DEFAULT/reserved_host_disk_mb': value => '4096';
  }

  # Needed, so puppet-openstack can restart virtlogd and libvirtd after configuration
  # so it listen to TCP before nova-compute starts.
  include ::nova::compute::libvirt::services
  Service['virtlogd'] -> Service['libvirtd']

  if $openstack_release == 'rocky'{
    class { '::nova::compute':
      vnc_enabled                      => true,
      vncproxy_host                    => "${vip_hostname}",
      vncproxy_protocol                => 'https',
      vncproxy_port                    => '443',
      vncproxy_path                    => '/novnc/vnc_auto.html',
      vncserver_proxyclient_address    => $machine_ip,
      instance_usage_audit             => true,
      instance_usage_audit_period      => 'hour',
      keymgr_api_class                 => 'castellan.key_manager.barbican_key_manager.BarbicanKeyManager',
      barbican_auth_endpoint           => "${base_url}/identity/v3",
      barbican_endpoint                => "${base_url}/keymanager",
      keymgr_backend                   => 'castellan.key_manager.barbican_key_manager.BarbicanKeyManager',
      resume_guests_state_on_host_boot => true,
      allow_resize_to_same_host        => true,
      reserved_host_memory             => $reserved_host_memory_mb,
#     resize_confirm_window            => '60',
    }
  } else {
    class { '::nova::compute':
      vnc_enabled                      => true,
      vncproxy_host                    => "${vip_hostname}",
      vncproxy_protocol                => 'https',
      vncproxy_port                    => '443',
      vncproxy_path                    => '/novnc/vnc_auto.html',
      vncserver_proxyclient_address    => $machine_ip,
      instance_usage_audit             => true,
      instance_usage_audit_period      => 'hour',
      barbican_auth_endpoint           => "${base_url}/identity/v3",
      barbican_endpoint                => "${base_url}/keymanager",
      keymgr_backend                   => 'castellan.key_manager.barbican_key_manager.BarbicanKeyManager',
      resume_guests_state_on_host_boot => true,
      allow_resize_to_same_host        => true,
      reserved_host_memory             => $reserved_host_memory_mb,
#     resize_confirm_window            => '60',
    }
    class { '::nova::logging':
      debug => true,
    }
  }

  class { '::nova::compute::libvirt':
    libvirt_virt_type       => 'kvm',
    libvirt_cpu_mode        => 'host-passthrough',
    # Setting-up cpu_model doesn't seem to work in the KVM PoC.
    # This is to be investigated.
#    libvirt_cpu_mode        => 'custom',
#    libvirt_cpu_model       => 'SandyBridge-IBRS',
    migration_support       => true,
    # False is needed because of the above include ::nova::compute::libvirt::services
    manage_libvirt_services => false,
    preallocate_images      => 'space',
    # This is one week retention.
    remove_unused_original_minimum_age_seconds => '604800',
    vncserver_listen        => '0.0.0.0',
  }->
  file_line { 'parallel-shutdown-of-vms':
    path   => '/etc/default/libvirt-guests',
    match  => '.*PARALLEL_SHUTDOWN.*=.*',
    line   => 'PARALLEL_SHUTDOWN=8',
  }->
  file_line { 'shutdown-timeout-of-vms':
    path   => '/etc/default/libvirt-guests',
    match  => '.*SHUTDOWN_TIMEOUT.*=.*',
    line   => 'SHUTDOWN_TIMEOUT=120',
  }->
  file_line { 'start-delay-of-vms':
    path   => '/etc/default/libvirt-guests',
    match  => '.*START_DELAY.*=.*',
    line   => 'START_DELAY=4',
  }

  if $openstack_release == 'rocky'{
    class { '::nova::network::neutron':
      neutron_auth_url      => "${base_url}/identity/v3",
      neutron_url           => "${base_url}/network",
      neutron_password      => $pass_neutron_authtoken,
      default_floating_pool => 'public',
      dhcp_domain           => '',
    }
    nova_config {
      'neutron/endpoint_override': value => "${base_url}/network";
    }
  }else{
    class { '::nova::network::neutron':
      neutron_auth_url          => "${base_url}/identity/v3",
      neutron_url               => "${base_url}/network",
      neutron_password          => $pass_neutron_authtoken,
      default_floating_pool     => 'public',
      dhcp_domain               => '',
      neutron_endpoint_override => "${base_url}/network",
    }
  }

  if $cluster_has_osds {
    class { 'ceph':
      fsid                => $ceph_fsid,
      ensure              => 'present',
      authentication_type => 'cephx',
      mon_initial_members => $ceph_mon_initial_members,
      mon_host            => $ceph_mon_host,
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
    }
    if $use_ceph_if_available == 'yes' {
      class { '::nova::compute::rbd':
        libvirt_rbd_user        => 'openstack',
        libvirt_rbd_secret_uuid => $ceph_libvirtuuid,
        libvirt_rbd_secret_key  => $ceph_openstack_key,
        libvirt_images_rbd_pool => 'nova',
        rbd_keyring             => 'client.openstack',
        # ceph packaging is already managed by puppet-ceph
        manage_ceph_client      => false,
        require => Ceph::Key['client.openstack'],
      }
    }else{
      # If we have Ceph OSDs but we don't use /var/lib/nova/instances,
      # we still need the secret to be defined on libvirt: let's do it
      # without ::nova::compute::rbd, and by the way, let's disable
      # RBD on nova.conf, just in case someone changed his mind, and
      # disabled such config on the node.

      $rbd_keyring = 'client.openstack'
      $libvirt_rbd_secret_uuid = $ceph_libvirtuuid
      file { '/etc/nova/secret.xml':
        content => template('nova/secret.xml-compute.erb'),
        require => Ceph::Key['client.openstack'],
      }

      #Variable name shrunk in favor of removing
      #the more than 140 chars puppet-lint warning.
      #variable used in the get-or-set virsh secret
      #resource.
      $cm = '/usr/bin/virsh secret-define --file /etc/nova/secret.xml | /usr/bin/awk \'{print $2}\' | sed \'/^$/d\' > /etc/nova/virsh.secret'
      exec { 'get-or-set virsh secret':
        command => $cm,
        unless  => "/usr/bin/virsh secret-list | grep -i ${libvirt_rbd_secret_uuid}",
        require => File['/etc/nova/secret.xml'],
      }
      Service<| title == 'libvirt' |> -> Exec['get-or-set virsh secret']
      nova_config {
        'libvirt/images_type':          ensure => absent;
        'libvirt/images_rbd_pool':      ensure => absent;
        'libvirt/images_rbd_ceph_conf': ensure => absent;
        'libvirt/rbd_secret_uuid':      value => $ceph_libvirtuuid;
        'libvirt/rbd_user':             value => 'openstack';
      }
      # make sure ceph pool exists before running nova-compute
      # Note: cannot be done on the compute nodes, since we aren't doing pools here
      # but on the controller.
      #Exec['create-nova'] -> Service['nova-compute']
    }
  }

  ###############
  ### Neutron ###
  ###############
  if $vmnet_mtu == 0 {
    $vmnet_mtu_real = 1500
  }else{
    $vmnet_mtu_real = $vmnet_mtu
  }

  if $disable_notifications {
    $neutron_notif_transport_url = ''
  } else {
    $neutron_notif_transport_url = os_transport_url({
                                     'transport' => $messaging_notify_proto,
                                     'hosts'     => fqdn_rotate($all_masters),
                                     'port'      => $messaging_notify_port,
                                     'username'  => 'neutron',
                                     'password'  => $pass_neutron_messaging,
                                   })
  }
  if $openstack_release == 'rocky'{
    class { '::neutron':
      debug                      => true,
      default_transport_url      => os_transport_url({
        'transport' => $messaging_default_proto,
        'hosts'     => fqdn_rotate($all_masters),
        'port'      => $messaging_default_port,
        'username'  => 'neutron',
        'password'  => $pass_neutron_messaging,
      }),
      notification_transport_url => $neutron_notif_transport_url,
      dns_domain                 => $cluster_domain,
      rabbit_use_ssl             => $use_ssl,
      rabbit_ha_queues           => true,
      kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
      amqp_sasl_mechanisms       => 'PLAIN',
      allow_overlapping_ips      => true,
      core_plugin                => 'ml2',
#      service_plugins            => ['router', 'metering', 'firewall', 'qos', 'trunk', 'neutron_lbaas.services.loadbalancer.plugin.LoadBalancerPluginv2'],
      service_plugins            => ['router', 'metering', 'qos', 'trunk', 'firewall_v2'],
      bind_host                  => $machine_ip,
      notification_driver        => 'messagingv2',
      global_physnet_mtu         => $vmnet_mtu_real,
    }
  }else{
    class { '::neutron':
      default_transport_url      => os_transport_url({
        'transport' => $messaging_default_proto,
        'hosts'     => fqdn_rotate($all_masters),
        'port'      => $messaging_default_port,
        'username'  => 'neutron',
        'password'  => $pass_neutron_messaging,
      }),
      notification_transport_url => $neutron_notif_transport_url,
      dns_domain                 => $cluster_domain,
      rabbit_use_ssl             => $use_ssl,
      rabbit_ha_queues           => true,
      kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
      amqp_sasl_mechanisms       => 'PLAIN',
      allow_overlapping_ips      => true,
      core_plugin                => 'ml2',
#      service_plugins            => ['router', 'metering', 'firewall', 'qos', 'trunk', 'neutron_lbaas.services.loadbalancer.plugin.LoadBalancerPluginv2'],
      service_plugins            => ['router', 'metering', 'qos', 'trunk', 'firewall_v2'],
      bind_host                  => $machine_ip,
      notification_driver        => 'messagingv2',
      global_physnet_mtu         => $vmnet_mtu_real,
    }
  }
  neutron_config {
    'nova/cafile':         value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    'database/connection': value => '';
  }

  class { '::neutron::server::notifications':
    auth_url => $keystone_admin_uri,
    password => $pass_nova_authtoken,
  }

  class { '::neutron::client': }
  class { '::neutron::keystone::authtoken':
    password             => $pass_neutron_authtoken,
    auth_url             => $keystone_admin_uri,
    www_authenticate_uri => $keystone_auth_uri,
    cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
  }
  class { '::neutron::agents::ml2::ovs':
    local_ip                   => $vmnet_ip,
    tunnel_types               => ['vxlan'],
    bridge_uplinks             => ['eth0'],
    bridge_mappings            => $bridge_mapping_list,
#    extensions                 => 'fwaas_v2',
    extensions                 => '',
    l2_population              => true,
    arp_responder              => false,
    firewall_driver            => 'iptables_hybrid',
    drop_flows_on_start        => false,
    enable_distributed_routing => true,
    manage_vswitch             => false,
  }
  class { '::neutron::agents::l3':
    interface_driver => 'openvswitch',
    debug            => true,
    agent_mode       => 'dvr',
    ha_enabled       => false,
#    extensions       => 'fwaas_v2',
    extensions       => '',
  }
  neutron_l3_agent_config {
    'DEFAULT/external_network_bridge': value => '';
  }

  class { '::neutron::plugins::ml2':
    type_drivers         => ['flat', 'vxlan', 'vlan', ],
    tenant_network_types => ['flat', 'vxlan', 'vlan', ],
    extension_drivers    => 'port_security,qos',
    mechanism_drivers    => 'openvswitch,l2population',
    firewall_driver      => 'iptables_v2',
    flat_networks        => $external_network_list,
    network_vlan_ranges  => $external_network_list,
    vni_ranges           => '1000:9999',
    path_mtu             => $vmnet_mtu,
  }
  class { '::neutron::agents::dhcp':
    interface_driver         => 'openvswitch',
    debug                    => true,
    enable_isolated_metadata => true,
    enable_metadata_network  => true,
  }
  class { '::neutron::agents::metering':
    interface_driver => 'openvswitch',
    debug            => true,
  }
  class { '::neutron::services::fwaas':
    enabled       => true,
    agent_version => 'v2',
    driver        => 'iptables_v2',
  }
  class { '::neutron::agents::metadata':
    debug             => true,
    shared_secret     => $pass_metadata_proxy_shared_secret,
    metadata_workers  => 2,
    package_ensure    => 'latest',
    metadata_protocol => 'http',
    metadata_host     => $vip_ipaddr,
  }
  package { 'l2gw networking':
    name => 'python3-networking-l2gw',
    ensure => installed,
  }
  ##############
  ### Cinder ###
  ##############
  # If using Ceph, then we setup cinder-volume and cinder-backup over Ceph
  # on each and every compute nodes. The goal is to spread the load away
  # from the controller nodes.
  if $cluster_has_osds {
    include ::cinder::client
    # Cinder main class (ie: cinder-common config)
    if $openstack_release == 'rocky'{
      class { '::cinder':
        debug                 => true,
        default_transport_url => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'cinder',
          'password'  => $pass_cinder_messaging,
        }),
        database_connection   => "mysql+pymysql://cinder:${pass_cinder_db}@${sql_vip_ip}/cinderdb?charset=utf8",
        rabbit_use_ssl        => $use_ssl,
        rabbit_ha_queues      => true,
        kombu_ssl_ca_certs    => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms  => 'PLAIN',
      }
    }else{
      class { '::cinder':
        default_transport_url => os_transport_url({
          'transport' => $messaging_default_proto,
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => $messaging_default_port,
          'username'  => 'cinder',
          'password'  => $pass_cinder_messaging,
        }),
        database_connection   => "mysql+pymysql://cinder:${pass_cinder_db}@${sql_vip_ip}/cinderdb?charset=utf8",
        rabbit_use_ssl        => $use_ssl,
        rabbit_ha_queues      => true,
        kombu_ssl_ca_certs    => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms  => 'PLAIN',
      }
    }

    cinder_config {
      'nova/cafile':                       value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'service_user/cafile':               value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
      'DEFAULT/backup_driver':             value => 'cinder.backup.drivers.ceph.CephBackupDriver';
      'DEFAULT/snapshot_clone_size':       value => '200';
      'DEFAULT/rbd_pool':                  value => 'cinder';
      'DEFAULT/rbd_ceph_conf':             value => '/etc/ceph/ceph.conf';
      'DEFAULT/rbd_secret_uuid':           value => $ceph_libvirtuuid;
      'DEFAULT/backup_swift_auth_url':     value => $keystone_auth_uri;
      'DEFAULT/backup_ceph_user':          value => 'openstack';
      'DEFAULT/backup_ceph_pool':          value => 'cinderback';
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

    class { '::cinder::backends':
      enabled_backends => ['CEPH_1'],
    }

    cinder::backend::rbd { 'CEPH_1':
      rbd_user           => 'openstack',
      rbd_pool           => 'cinder',
      rbd_secret_uuid    => $ceph_libvirtuuid,
      manage_volume_type => true,
      backend_host       => $machine_hostname,
    }

    # Clear volumes on delete (for data security)
    class { '::cinder::volume':
      volume_clear => 'zero',
    }

    # A cinder-backup service on each volume nodes
    class { '::cinder::backup': }

    # Avoids Cinder to lookup for the catalogue
    class { '::cinder::glance':
      glance_api_servers => "${base_url}/image",
    }
  }

  ##################
  ### Ceilometer ###
  ##################
  if $has_subrole_ceilometer {
    if $openstack_release == 'rocky'{
      class { '::ceilometer':
        debug                      => true,
        telemetry_secret           => $pass_ceilometer_telemetry,
        default_transport_url      => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'ceilometer',
          'password'  => $pass_ceilometer_messaging,
        }),
        notification_transport_url => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'ceilometer',
          'password'  => $pass_ceilometer_messaging,
        }),
        rabbit_use_ssl             => $use_ssl,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
      }
    }else{
      class { '::ceilometer':
        telemetry_secret           => $pass_ceilometer_telemetry,
        default_transport_url      => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'ceilometer',
          'password'  => $pass_ceilometer_messaging,
        }),
        notification_transport_url => os_transport_url({
          'transport' => 'rabbit',
          'hosts'     => fqdn_rotate($all_masters),
          'port'      => '5671',
          'username'  => 'ceilometer',
          'password'  => $pass_ceilometer_messaging,
        }),
        rabbit_use_ssl             => $use_ssl,
        kombu_ssl_ca_certs         => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
        amqp_sasl_mechanisms       => 'PLAIN',
      }
    }
    class { '::ceilometer::keystone::authtoken':
      password             => $pass_ceilometer_authtoken,
      auth_url             => $keystone_admin_uri,
      www_authenticate_uri => $keystone_auth_uri,
      cafile               => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    class { '::ceilometer::agent::auth':
      auth_password => $pass_ceilometer_authtoken,
      auth_url      => $keystone_auth_uri,
      auth_cacert   => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem',
    }
    # Looks like the above sets ca_file instead of cafile.
    ceilometer_config {
      'service_credentials/cafile': value => '/etc/ssl/certs/oci-pki-oci-ca-chain.pem';
    }
    class { '::ceilometer::agent::compute':
      instance_discovery_method => 'libvirt_metadata',
    }
  }
}
