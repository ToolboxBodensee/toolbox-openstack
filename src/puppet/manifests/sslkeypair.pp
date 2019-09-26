#
# Provision an SSL keypair using what's already generated in:
# /etc/ssl/private/ssl-cert-snakeoil.key (private key) and
# /etc/ssl/certs/ssl-cert-snakeoil.pem (public cert).
#
# The result will be /etc/<service_name>/ssl/<private|public>/<FQDN>.<key|pem>
# 
define oci::sslkeypair(
  $notify_service_name = 'httpd',
){

    file { "/etc/${name}/ssl":
      ensure                  => directory,
      owner                   => 'root',
      mode                    => '0755',
      selinux_ignore_defaults => true,
      require                 => Anchor["${name}::install::end"],
    }->
    file { "/etc/${name}/ssl/private":
      ensure                  => directory,
      owner                   => 'root',
      mode                    => '0755',
      selinux_ignore_defaults => true,
    }->
    file { "/etc/${name}/ssl/public":
      ensure                  => directory,
      owner                   => 'root',
      mode                    => '0755',
      selinux_ignore_defaults => true,
    }->
    file { "/etc/${name}/ssl/private/${::fqdn}.pem":
      ensure                  => present,
      owner                   => "${name}",
      source                  => "/etc/ssl/private/ssl-cert-snakeoil.key",
      selinux_ignore_defaults => true,
      mode                    => '0600',
    }->
    file { "/etc/${name}/ssl/public/${::fqdn}.crt":
      ensure                  => present,
      owner                   => "${name}",
      source                  => '/etc/ssl/certs/ssl-cert-snakeoil.pem',
      selinux_ignore_defaults => true,
      mode                    => '0644',
      notify                  => Service[$notify_service_name],
    }
}
