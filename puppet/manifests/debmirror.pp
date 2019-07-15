class oci::debmirror(
  $machine_hostname         = undef,
  $machine_ip               = undef,
  $etc_hosts                = undef,
  $time_server_host         = undef,
){

  ::oci::sysctl { 'oci-rox': }

  class { '::oci::etchosts': etc_hosts_file => $etc_hosts, }

  # Right on time!
  class { '::oci::chrony': time_server_host => $time_server_host, }

  class {'::archvsync':
    manage_apache   => true,
    manage_pureftpd => true,
    package_ensure  => 'present',
    mirrorname      => $::fqdn,
    to              => '/home/ftp/debian/',
    mailto          => 'toto@example.com',
    homedir         => '/home/ftp',
    hub             => 'false',
    rsync_host      => 'ftp.fr.debian.org',
    rsync_path      => 'debian',
    info_maintainer => 'Toor Op <root@localhost>',
    info_sponsor    => 'World Company SA <https://www.example.com>',
    info_country    => 'US',
    info_location   => 'Nowhere city',
    info_throughput => '10Gb',
    arch_include    => 'amd64 source',
    arch_exclude    => '',
    logdir          => '/home/ftp/log',
  }
}
