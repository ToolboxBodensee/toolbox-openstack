class oci::haproxy(
){
  # First, we accept binding on non-local IPs:
  sysctl::value { 'net.ipv4.ip_nonlocal_bind':
    value => "1",
    target => '/etc/sysctl.d/ip-nonlocal-bind.conf',
  }->
  file { "/etc/haproxy/ssl":
    ensure                  => directory,
    owner                   => 'root',
    mode                    => '0755',
    selinux_ignore_defaults => true,
    require                 => Package['haproxy'],
  }->
  file { "/etc/haproxy/ssl/private":
    ensure                  => directory,
    owner                   => 'root',
    mode                    => '0755',
    selinux_ignore_defaults => true,
  }->
  file { "/etc/haproxy/ssl/private/oci-pki-api.pem":
    ensure                  => present,
    owner                   => "haproxy",
    source                  => "/etc/ssl/private/oci-pki-api.pem",
    selinux_ignore_defaults => true,
    mode                    => '0600',
  }
  class { 'haproxy':
    global_options   => {
      'maxconn' => '40960',
      'user'    => 'haproxy',
      'group'   => 'haproxy',
      'daemon'  => '',
      'nbproc'  => '4',
    },
    defaults_options => {
      'mode'    => 'http',
        'timeout' => [
          'http-request 10s',
          'queue 1m',
          'connect 10s',
          'client 1m',
          'server 1m',
          'check 10s',
        ],
    },
    merge_options => true,
  }
}
