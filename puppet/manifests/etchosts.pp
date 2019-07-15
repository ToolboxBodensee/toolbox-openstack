# == Class: etchosts
#
# Maintains the /etc/hosts so it always contains all the cluster node info
#
# === Parameters:
#
# [*etc_hosts_file*]
#   (optional) A base64 representation of the node's /etc/hosts file
#
class oci::etchosts(
  $etc_hosts_file = undef,
){

  if $etc_hosts_file == undef {
    fail('etc_hosts_file should be a base64 of the /etc/hosts file')
  }

  file { "/etc/hosts":
    ensure                  => file,
    owner                   => "root",
    content                 => base64('decode', $etc_hosts_file),
    selinux_ignore_defaults => true,
    mode                    => '0644',
  }
}
