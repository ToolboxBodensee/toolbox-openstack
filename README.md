# What is OpenStack Cluster Installer (OCI)

**Entwicklung nur auf [GitLab](https://gitlab.com/ToolboxBodensee/toolbox-infrastructure/toolbox-openstack). Auf GitHub befindet sich lediglich ein Mirror**

### General description

OCI (OpenStack Cluster Installer) is a software to provision an OpenStack
cluster automatically. This package install a provisioning machine, which
consists of a DHCP server, a PXE boot server, a web server, and a
puppet-master.

Once computers in the cluster boot for the first time, a Debian live system
is served by OCI, to act as a discovery image. This live system then reports
the hardware features back to OCI. The computers can then be installed with
Debian from that live system, configured with a puppet-agent that will connect
to the puppet-master of OCI. After Debian is installed, the server reboots
under it, and OpenStack services is then provisionned in these machines,
depending on their role in the cluster.

OCI is fully packaged in Debian, including all of the Puppet modules and so
on. After installing the OCI package and its dependency, no other artificat
needs to be installed on your provisioning server.

### What OpenStack services can OCI install?

Currently, OCI can install:
- Swift (with optional dedicated proxy nodes)
- Keystone
- Cinder (LVM or Ceph backend)
- Glance (Swift or Ceph backend)
- Heat
- Horizon
- Nova
- Neutron
- Barbican

All of this in a high availability way, using haproxy and corosync for
the controller nodes for all services.

All services are fully using TLS, even within the cluster.

As a general rule, what OCI does, is check what type of nodes are part
of the cluster, and take decisions depending on it. For example, if there
are some Ceph OSD nodes, OCI will use Ceph as a backend for Glance and Nova.
If there are some Cinder Volume nodes, OCI will use them with the LVM
backend. If there is some Swiftstore, nodes, but no Swiftproxy, the proxies
will be installed in the controller. If there are some Ceph OSD nodes, but
no dedicated Ceph MON nodes, the controllers will act as Ceph monitors.
If there are some Compute nodes, then Cinder, Nova and Neutron will be
installed on the controller nodes. Etc.

The minimum number of controller nodes is 3, though it is possible to
install the 3 controllers on VMs on a single server (of course, loosing
the high availability feature if the hardware fails).

### Who initiated the project? Who are the main contributors?

OCI has been written from scratch by Thomas Goirand (zigo). The work is
fully sponsored by Infomaniak Network, who is using it in production.
Hopefully, this project, over time, will gather more contributors.

# How to install your puppet-master/PXE server

## Installing the package

### The package repository

The package is either available from plain Debian Sid/Buster, or from the
OpenStack stretch-rocky backport repositories. If using Stretch is desired,
then the below repository must be added to the sources.list file:

```
deb http://stretch-rocky.debian.net/debian stretch-rocky-backports main
deb-src http://stretch-rocky.debian.net/debian stretch-rocky-backports main

deb http://stretch-rocky.debian.net/debian stretch-rocky-backports-nochange main
deb-src http://stretch-rocky.debian.net/debian stretch-rocky-backports-nochange main
```

The repository key is available this way:

```
apt-get update
apt-get install --allow-unauthenticated -y openstack-backports-archive-keyring
apt-get update
```

### Install the package

Simply install the package:

```
apt-get install openstack-cluster-installer
```

### Install a db server

MariaDB will do:

```
apt-get install mariadb-server dbconfig-common
```

It is possible to the db creation and credentials by hand, or to let OCI handle
it automatically with dbconfig-common. If APT is running in
non-interactive mode, or if during the installation, the user doesn't ask
for the automatic db handling by dbconfig-common, here's how to create the
database:

```
apt-get install openstack-pkg-tools
. /usr/share/openstack-pkg-tools/pkgos_func
PASSWORD=$(openssl rand -hex 16)
pkgos_inifile set /etc/openstack-cluster-installer/openstack-cluster-installer.conf database connection mysql+pymysql://oci:${PASSWORD}@localhost:3306/oci"
mysql --execute 'CREATE DATABASE oci;'
mysql --execute "GRANT ALL PRIVILEGES ON oci.* TO 'oci'@'localhost' IDENTIFIED BY '${PASSWORD}';"
```

One must then make sure that the "connection" directive in
/etc/openstack-cluster-installer/openstack-cluster-installer.conf doesn't
contain spaces before and after the equal sign. Then the db is populated
below.

### Configuring OCI

Make sure the db is in sync (if it is, you'll see table exists errors):

```
apt-get install -y php-cli
cd /usr/share/openstack-cluster-installer ; php db_sync.php
```

Then edit /etc/openstack-cluster-installer/openstack-cluster-installer.conf
and make it looks the way it pleases you (ie: change network values, etc.).

### Generate the OCI's root CA

To handle TLS, OCI is using its own root CA. The root CA certificate is
distributed on all nodes of the cluster. To create the initial root CA,
there's a script to do it all:

```
oci-root-ca-gen
```

At this point, you should be able to browse through OCI's web interface:
```
firefox http://your-ip-address/oci/
```

However, you need a login/pass to get in. There's a shell utility to manage
your usernames. To add a new user, do this:

```
oci-userdb -a mylogin mypassword
```

Passwords are hashed using the PHP password_hash() function using the
BCRYPT algo.

Also, OCI is capable of using an external Radius for its authentication.
However, you still need to manually add logins in the db. What's bellow
inserts a new user that has an entry in the radius server:

```
oci-userdb -r newuser@example.com
```

Note that you also need to configure your radius server address and
shared secret in openstack-cluster-installer.conf.

Note that even if there is an authentication system, it is strongly advised
to not expose OCI to the public internet. The best setup is if your
provisionning server isn't reachable at all from the outside.

## Installing side services

### ISC-DHCPD

Configure isc-dhcp to match your network configuration. Note that
"next-server" must be the address of your puppet-master node (ie: the dhcp
server that we're currently configuring).

Edit /etc/default/isc-dhcpd:

```
sed -i 's/INTERFACESv4=.*/INTERFACESv4="eth0"/' /etc/default/isc-dhcp-server
```

Then edit /etc/dhcp/dhcpd.conf:

```
allow booting;
allow bootp;
default-lease-time 600;
max-lease-time 7200;
ddns-update-style none;
authoritative;
ignore-client-uids On;

subnet 192.168.100.0 netmask 255.255.255.0 {
        range 192.168.100.20 192.168.100.80;
        option domain-name infomaniak.ch;
        option domain-name-servers 9.9.9.9;
        option routers 192.168.100.1;
        option subnet-mask 255.255.255.0;
        option broadcast-address 192.168.100.255;
        next-server 192.168.100.2;
        if exists user-class and option user-class = "iPXE" {
                filename "http://192.168.100.2/oci/ipxe.php";
        } else {
                filename "pxelinux.0";
        }
}
```

Carefully note that 192.168.100.2 must be the address of your OCI server,
as it will be used for serving PXE, TFTP and web for the slave nodes.
It is of course fine to use another address if your OCI server does,
so feel free to adapt the above to your liking.

Also, for OCI to allow query from the DHCP range, you must add your
DHCP subnets to TRUSTED_NETWORKS in openstack-cluster-installer.conf.

### tftpd

Configure tftp-hpa to serve files from OCI:

```
sed -i 's#TFTP_DIRECTORY=.*#TFTP_DIRECTORY="/var/lib/openstack-cluster-installer/tftp"#' /etc/default/tftpd-hpa
```

Then restart tftpd-hpa.

## Getting ready to install servers

### Configuring ssh keys

When setting-up, OCI will create a public / private ssh keypair in here:

```
/etc/openstack-cluster-installer/id_rsa
```

Once done, it will copy the corresponding id_rsa.pub content into:

```
/etc/openstack-cluster-installer/authorized_keys
```

and will also add all the public keys it finds under
/root/.ssh/authorized_keys in it. Later on, this file will be copied
in the OCI Debian live image, and in all new systems OCI will install.
OCI will later on use the private key it generated to log into the
servers, while your keys will also be present so you can log into each
individual servers using your private key. Therefore, it is strongly
advise to customize /etc/openstack-cluster-installer/authorized_keys
*before* you build the OCI Debian Live image.

### Build OCI's live image ###

```
mkdir -p /root/live-image
cd /root/live-image
openstack-cluster-installer-build-live-image --pxe-server-ip 192.168.100.2 --debian-mirror-addr http://deb.debian.org/debian --debian-security-mirror-addr http://security.debian.org/
cp -auxf /var/lib/openstack-cluster-installer/tftp/* /usr/share/openstack-cluster-installer
cd ..
rm -rf /root/live-image
```

Is is possible to use package proxy servers like approx,
or local mirrors, which gives the possibility to have your cluster
and OCI itself completely disconnected from internet.

### Configure puppet's ENC

Once the puppet-master service is installed, its external node
classifier (ENC) directives must be set, so that OCI acts as ENC
(which means OCI will define roles and puppet classes to call when
installing a new server with puppet):

```
. /usr/share/openstack-pkg-tools/pkgos_func
pkgos_add_directive /etc/puppet/puppet.conf master "external_nodes = /usr/bin/oci-puppet-external-node-classifier" "# Path to enc"
pkgos_inifile set /etc/puppet/puppet.conf master external_nodes /usr/bin/oci-puppet-external-node-classifier
pkgos_add_directive /etc/puppet/puppet.conf master "node_terminus = exec" "# Tell what type of ENC"
pkgos_inifile set /etc/puppet/puppet.conf master node_terminus exec
```

then restart the puppet-master service.

### Optional: approx

To speed-up package download, it is highly recommended to install approx
locally on your OCI provisionning server, and use its address when
setting-up servers (the address is set in
/etc/openstack-cluster-installer/openstack-cluster-installer.conf).

# Using OCI

## Booting-up servers

Start-up a bunch of computers, booting them with PXE. If everything goes well, they will
catch the OCI's DHCP, and boot-up OCI's Debian live image. Once the server
is up, an agent will run to report to OCI's web interface. Just refresh
OCI's web interface, and you will see machines. You can also use the CLI
tool:

```
# apt-get install openstack-cluster-installer-cli
# ocicli machine-list
serial   ipaddr          memory  status     lastseen             cluster  hostname
2S2JGM2  192.168.100.37  4096    live       2018-09-20 09:22:31  null
2S2JGM3  192.168.100.39  4096    live       2018-09-20 09:22:50  null
```

Note that ocicli can either use a login/password which can be set in
the OCI's internal db, or the IP address of the server where ocicli runs can
be white-listed in /etc/openstack-cluster-installer/openstack-cluster-installer.conf.

## Creating Swift regions, locations, networks, roles and clusters

### Before we start

In this documentation, everything is done through the command line using
ocicli. However, absolutely everything can also be done using the web
interface. It is just easier to explain using the CLI, as this avoids
the necessity of showing snapshots of the web interface.

### Creating Swift regions and locations

Before installing the systems on your servers, clusters must be defined.
This starts by setting-up Swift regions. In a Swift cluster, there are
zones and regions. When uploading a file to Swift, it is replicated on
N zones (usually 3). If 2 regions are defined, then Swift tries to
replicate objects on both regions.

Under OCI, you must first define Swift regions. To do so, click on
"Swift region" on the web interface, or using ocicli, type:

```
# ocicli swift-region-create datacenter-1
# ocicli swift-region-create datacenter-2
```

Then create locations attached to these regions:

```
# ocicli dc1-zone1 datacenter-1
# ocicli dc1-zone2 datacenter-1
# ocicli dc2-zone1 datacenter-2
```

Later on, when adding a swift data node to a cluster (data nodes are
the servers that will actually do the Swift storage), a location must
be selected.

Once the locations have been defined, it is time to define networks.
Networks are attached to locations as well. The Swift zones and regions
will be related to these locations and regions.

### Creating networks

```
# ocicli network-create dc1-net1 192.168.101.0 24 dc1-zone1 no
```

The above command will create a subnet 192.168.101.0/24, located at
dc1-zone1. Let's create 2 more networks:

```
# ocicli network-create dc1-net2 192.168.102.0 24 dc1-zone2 no
# ocicli network-create dc2-net1 192.168.103.0 24 dc2-zone1 no
```

Next, for the cluster to be reachable, let's create a public network
on which customers will connect:

```
# ocicli network-create pubnet1 203.0.113.0 28 public yes
```

Note that if using a /32, it will be setup on the lo interface of
your controller. The expected setup is to use BGP to route that
public IP on the controller. To do that, it is possible to customize
the ENC and add BGP peering to your router. See at the end of this
documentation for that.

### Creating a new cluster

Let's create a new cluster:

```
# ocicli cluster-create swift01 example.com
```

Now that we have a new cluster, the networks we created can be added to it:

```
# ocicli network-add dc1-net1 swift01 all eth0
# ocicli network-add dc1-net2 swift01 all eth0
# ocicli network-add dc2-net1 swift01 all eth0
# ocicli network-add pubnet1 swift01 all eth0
```

When adding the public network, automatically, one IP address will be
reserved for the VIP (Virtual Private IP). This IP address will later
be shared by the controller nodes, to perform HA (High Availability),
controlled by pacemaker / corosync. The principle is: if one of
the controllers nodes is hosting the VIP (and it's assigned to its
eth0), and becomes unavailable (let's say, the server crashes or the
network wire is unplugged), then the VIP is re-assigned to the eth0
of another controller node of the cluster.

If selecting 2 network interfaces (for example, eth0 and eth1), then
bonding will be used. Note that your network equipment (switches, etc.)
must be configured accordingly (LACP, etc.), and that the setup of
these equipment is out of the scope of this documentation. Consult your
network equipment vendor for more information.

## Enrolling servers in a cluster

Now that we have networks assigned to the cluster, it is time to add
assign servers to the cluster. Let's say we have the below output:

```
# ocicli machine-list
serial  ipaddr          memory  status  lastseen             cluster  hostname
C1      192.168.100.20  8192    live    2018-09-19 20:31:57  null
C2      192.168.100.21  8192    live    2018-09-19 20:31:04  null
C3      192.168.100.22  8192    live    2018-09-19 20:31:14  null
C4      192.168.100.23  5120    live    2018-09-19 20:31:08  null
C5      192.168.100.24  5120    live    2018-09-19 20:31:06  null
C6      192.168.100.25  5120    live    2018-09-19 20:31:14  null
C7      192.168.100.26  4096    live    2018-09-19 20:31:18  null
C8      192.168.100.27  4096    live    2018-09-19 20:31:26  null
C9      192.168.100.28  4096    live    2018-09-19 20:30:50  null
CA      192.168.100.29  4096    live    2018-09-19 20:31:00  null
CB      192.168.100.30  4096    live    2018-09-19 20:31:07  null
CC      192.168.100.31  4096    live    2018-09-19 20:31:20  null
CD      192.168.100.32  4096    live    2018-09-19 20:31:28  null
CE      192.168.100.33  4096    live    2018-09-19 20:31:33  null
CF      192.168.100.34  4096    live    2018-09-19 20:31:40  null
D0      192.168.100.35  4096    live    2018-09-19 20:31:47  null
D1      192.168.100.37  4096    live    2018-09-21 20:31:23  null
D2      192.168.100.39  4096    live    2018-09-21 20:31:31  null
```

Then we can enroll machines in the cluster this way:

```
# ocicli machine-add C1 swift01 controller dc1-zone1
# ocicli machine-add C2 swift01 controller dc1-zone2
# ocicli machine-add C3 swift01 controller dc2-zone1
# ocicli machine-add C4 swift01 swiftproxy dc1-zone1
# ocicli machine-add C5 swift01 swiftproxy dc1-zone2
# ocicli machine-add C6 swift01 swiftproxy dc2-zone1
# ocicli machine-add C7 swift01 swiftstore dc1-zone1
# ocicli machine-add C8 swift01 swiftstore dc1-zone2
# ocicli machine-add C9 swift01 swiftstore dc2-zone1
# ocicli machine-add CA swift01 swiftstore dc1-zone1
# ocicli machine-add CB swift01 swiftstore dc1-zone2
# ocicli machine-add CC swift01 swiftstore dc2-zone1
```

As a result, there's going to be 1 controller, 1 Swift proxy and
2 Swift data node on each zone of our clusters. IP addresses will
automatically be assigned to servers as you add them to the clusters.
They aren't shown in ocicli, but you can check for them through the
web interface. The result should be like this:

```
# ocicli machine-list
serial  ipaddr          memory  status  lastseen             cluster  hostname
C1      192.168.100.20  8192    live    2018-09-19 20:31:57  7        swift01-controller-1.example.com
C2      192.168.100.21  8192    live    2018-09-19 20:31:04  7        swift01-controller-2.example.com
C3      192.168.100.22  8192    live    2018-09-19 20:31:14  7        swift01-controller-3.example.com
C4      192.168.100.23  5120    live    2018-09-19 20:31:08  7        swift01-swiftproxy-1.example.com
C5      192.168.100.24  5120    live    2018-09-19 20:31:06  7        swift01-swiftproxy-2.example.com
C6      192.168.100.25  5120    live    2018-09-19 20:31:14  7        swift01-swiftproxy-3.example.com
C7      192.168.100.26  4096    live    2018-09-19 20:31:18  7        swift01-swiftstore-1.example.com
C8      192.168.100.27  4096    live    2018-09-19 20:31:26  7        swift01-swiftstore-2.example.com
C9      192.168.100.28  4096    live    2018-09-19 20:30:50  7        swift01-swiftstore-3.example.com
CA      192.168.100.29  4096    live    2018-09-19 20:31:00  7        swift01-swiftstore-4.example.com
CB      192.168.100.30  4096    live    2018-09-19 20:31:07  7        swift01-swiftstore-5.example.com
CC      192.168.100.31  4096    live    2018-09-19 20:31:20  7        swift01-swiftstore-6.example.com
CD      192.168.100.32  4096    live    2018-09-19 20:31:28  null
CE      192.168.100.33  4096    live    2018-09-19 20:31:33  null
CF      192.168.100.34  4096    live    2018-09-19 20:31:40  null
D0      192.168.100.35  4096    live    2018-09-19 20:31:47  null
D1      192.168.100.37  4096    live    2018-09-21 20:31:23  null
D2      192.168.100.39  4096    live    2018-09-21 20:31:31  null
```

As you can see, hostnames are calculated automatically as well.

## Calculating the Swift ring

Before starting to install servers, the swift ring must be built.
Simply issue this command:

```
# ocicli swift-calculate-ring swift01
```

Note that it may take a very long time, depending on your cluster size.
This is expected. Just be patient.

## Installing servers

There's no (yet) a big "install the cluster" button on the web interface, or on
the CLI. Instead, servers must be installed one by one:

```
# ocicli machine-install-os C1
# ocicli machine-install-os C2
# ocicli machine-install-os C3
```

It is advised to first install the controller nodes, manually check that
they are installed correctly (for example, check that "openstack user list"
works), then the Swift store nodes, then the Swift proxy nodes. However,
nodes of the same type can be installed at once. Also, du to the use of
a VIP and corosync/pacemaker, controller nodes *must* be installed roughly
at the same time.

It is possible to see a server's installation log last lines using the
CLI as well:

```
# ocicli machine-install-log C1
```

This will show the logs of the system installation from /var/log/oci,
then once the server has rebooted, it will show the puppet logs from
/var/log/puppet-first-run.

## Checking your installation

Login on a controller node. To do that, list its IP:

```
# CONTROLLER_IP=$(ocicli machine-list | grep C1 | awk '{print $2}')
# ssh root@${CONTROLLER_IP}
```

Once logged into the controller, you'll see login credentials under
/root/oci-openrc.sh. Source it and try:

```
# . /root/oci-openrc.sh
# openstack user list
```

You can also try Swift:

```
# . /root/oci-openrc.sh
# openstack container create foo
# echo "test" >bar
# openstack object create foo bar
# rm bar
# openstack object delete foo bar
```

## Enabling Swift object encryption

Locally on the Swift store, Swift stores the object in clear form. This
means that anyone with physical access to the data center can pull a hard
drive and objects can be accessed from the /srv/node folder.
To mitigate this risk, Swift can do encryption of the objects it stores.
The metadata (accounts, containters, etc.) will still be stored in clear
form, but at least, the data that is stored encrypted.

The way this is implemented in OCI is to use Barbican. This is the reason
why Barbican is provisionned by default on the controller nodes. By default,
encryption isn't activated. To activate it, you must first store the key
for object encryption in the Barbican store. It can be done this way:

```
# ENC_KEY=$(openssl rand -hex 32)
# openstack secret store --name swift-encryption-key \
  --payload-content-type=text/plain --algorithm aes \
  --bit-length 256 --mode ctr --secret-type symmetric \
  --payload ${ENC_KEY}
+---------------+--------------------------------------------------------------------------------------------+
| Field         | Value                                                                                      |
+---------------+--------------------------------------------------------------------------------------------+
| Secret href   | https://swift01-api.example.com/keymanager/v1/secrets/6ba8dd62-d752-4144-b803-b32012d707d0 |
| Name          | swift-encryption-key                                                                       |
| Created       | None                                                                                       |
| Status        | None                                                                                       |
| Content types | {'default': 'text/plain'}                                                                  |
| Algorithm     | aes                                                                                        |
| Bit length    | 256                                                                                        |
| Secret type   | symmetric                                                                                  |
| Mode          | ctr                                                                                        |
| Expiration    | None                                                                                       |
+---------------+--------------------------------------------------------------------------------------------+
```

Once that's done, the key ID (here: 6ba8dd62-d752-4144-b803-b32012d707d0)
has to be entered in the OCI's web interface, in the cluster definition,
under "Swift encryption key id (blank: no encryption):". Once that's done,
another puppet run is needed on the swift proxy nodes:

```
root@C1-swift01-swiftproxy-1>_ ~ # OS_CACERT=/etc/ssl/certs/oci-pki-oci-ca-chain.pem puppet agent --test --debug
```

This should enable encryption. Note that the encryption key must be stored
in Barbican under the user swift and project services, so that Swift has
access to it.

## Adding other types of nodes

OCI can handle, by default, the below types of nodes:

- cephmon: Ceph monitor
- cephosd: Ceph data machines
- compute: Nova compute and Neutron DVR nodes
- controller: The OpenStack control plane, running all API and daemons
- swiftproxy: Swift proxy servers
- swiftstore: Swift data machines
- volume: Cinder LVM nodes

It is only mandatory to install 3 controllers, then everything else is
optional. There's nothing to configure, OCI will understand what the
user wants depending of what type of nodes is provisioned.

If cephosd nodes are deployed, then everything will be using Ceph:
- Nova
- Glance
- Cinder

Though even with Ceph, setting-up volume nodes will add the LVM
backend capability. With or without volume nodes, if some OSD nodes
are deployed, cinder-volume with Ceph backend will be installed on
the controller nodes.

Live migration of VMs between compute nodes is only possible if using
Cpeh (ie: if some Ceph OSD nodes are deployed).

Ceph MON nodes are optional. If they aren't deplyed, the Ceph MON and
MGR will be installed on the controller nodes.

# Advanced usage
## Customizing the ENC

In /etc/openstack-cluster-installer/hiera, you'll find 2 folders and a
all.yaml. These are to allow one to customize the output of OCI's ENC.
For example, if you put:

```
   ntp:
      servers:
         - 0.us.pool.ntp.org iburst
```

in /etc/openstack-cluster-installer/hiera/all.yaml, then all nodes will
be configured with ntp using 0.us.pool.ntp.org to synchronize time.

If we have a swift01 cluster, then the full folder structure is as follow:

```
/etc/openstack-cluster-installer/hiera/roles/controller.yaml
/etc/openstack-cluster-installer/hiera/roles/swiftproxy.yaml
/etc/openstack-cluster-installer/hiera/roles/swiftstore.yaml
/etc/openstack-cluster-installer/hiera/nodes/-hostname-of-your-node-.yaml
/etc/openstack-cluster-installer/hiera/all.yaml
/etc/openstack-cluster-installer/hiera/clusters/swift01/roles/controller.yaml
/etc/openstack-cluster-installer/hiera/clusters/swift01/roles/swiftproxy.yaml
/etc/openstack-cluster-installer/hiera/clusters/swift01/roles/swiftstore.yaml
/etc/openstack-cluster-installer/hiera/clusters/swift01/nodes/-hostname-of-your-node-.yaml
/etc/openstack-cluster-installer/hiera/clusters/swift01/all.yaml

```

## Customizing installed server at setup time

Sometimes, it is desirable to configure a server at setup time. For example,
it could be needed to configure routing (using BGP) for the virtual IP to be
available at setup time. OCI offers all what's needed in order to enrich the
server configuration at install time, before puppet agent even starts.

Say you want to configure swift01-controller-1 in your swift01 cluster, add
quagga to it, and add some configuration files. Simply create the folder,
fill content in it, and add a oci-packages-list file:

```
# mkdir -p /var/lib/oci/clusters/swift01/swift01-controller-1.infomaniak.ch/oci-in-target
# cd /var/lib/oci/clusters/swift01/swift01-controller-1.infomaniak.ch
# echo -n "quagga,tmux" >oci-packages-list
# mkdir -p oci-in-target/etc/quagga
# echo "some conf" >oci-in-target/etc/quagga/bgpd.conf
```

When OCI provision the baremetal server, it looks if the oci-packages-list
file exists. If it does, the packages are added when installing. Then the
oci-in-target content is copied into the target system.

## Using a BGP VIP

The same way, you can for example, decide to have the VIP of your
controllers to use BGP routing. To do that, write in
/etc/openstack-cluster-installer/roles/controller.yaml:

```
   quagga::bgpd:
      my_asn: 64496,
      router_id: 192.0.2.1
      networks4:
         - '192.0.2.0/24'
      peers:
         64497:
            addr4:
               - '192.0.2.2'
            desc: TEST Network
```

Though you may want to do this only for a specific node of a single
cluster of servers, rather than all. In such case, simply use this
filepath scheme:
/etc/openstack-cluster-installer/clusters/cloud1/nodes/cloud1-controller-1.example.com.yaml

For all controllers of the cloud1 cluster, use:
/etc/openstack-cluster-installer/clusters/cloud1/roles/controller.yaml

## Doing a test in OCI's manifests for debug purpose

If you would like to test a change in OCI's puppet files, edit them
in /usr/share/puppet/modules/oci, then on the master run, for example:

```
# puppet master --compile swift01-controller-1.example.com
# /etc/init.d/puppet-master stop
# /etc/init.d/puppet-master start
```

then on swift01-controller-1.example.com you can run:

```
# OS_CACERT=/etc/ssl/certs/oci-pki-oci-ca-chain.pem puppet agent --test --debug
```

## Customizing files and packages in your servers.

If you wish to customize the file contents of your hosts, simply write
any file in, for example:

```
/var/lib/oci/clusters/swift01/swift01-controller-1.example.com/oci-in-target
```

and it will be copied in the server you'll be installing.

The same way, you can add additional packages to your server by adding their
names in this file:

```
/var/lib/oci/clusters/swift01/swift01-controller-1.example.com/oci-packages-list
```

Packages must be listed on a single line, separated by comas. For example:

```
quagga,bind
```

### Enabling Hiera for environment

If you need to enable Hiera, you can do it this way:
```
# mkdir -p /etc/puppet/code/environments/production/manifests/
# echo "hiera_include('classes')" > /etc/puppet/code/environments/production/manifests/site.pp
# cat /etc/puppet/code/hiera/common.yaml
---
classes:
  - xxx
...
```
#!/bin/sh

set -e
set -x

# Once deployment is ready

There's currently a few issues that need to be addressed by hand. Hopefully,
all of these will be automated in a near future. In the mean while, please
do contribute the fixes if you find out how, or just do as per what's below.

## Fixing-up the controllers

Unfortunately, sometimes, there's some scheduling issues in the puppet
apply. If this happens, one can try to relaunch the puppet thing:

```
# OS_CACERT=/etc/ssl/certs/oci-pki-oci-ca-chain.pem puppet agent --test --debug 2>&1 | tee /var/log/puppet-run-1
```

Do this on the controller-1 node first, wait until it finishes, then restart
it on the other controller nodes.

## Adding custom firewall rulles

OCI is using puppet-module-puppetlabs-firewall, and flushes iptables on each
run. Therefore, if you need custom firewall rules, you also have to do it
via puppet. If you want to do apply the same firewall rules on all nodes,
simply edit the site.pp like this in /etc/puppet/code/environments/production/manifests/site.pp:

```
hiera_include('classes')

firewall { '000 allow monitoring network':
  proto       => tcp,
  action      => accept,
  source      => "10.3.50.0/24",
}
```

Note that the firewall rule is prefixed with a number. This is mandatory.
Also, make sure that this number doesn't enter in conflict with an already
existing rule.

What's done by OCI is: protect the controller's VIP (deny access to it from
the outside), and protect the swiftstore ports for account, container and
object servers from any query not from within the cluster. So the above will
allow a monitoring server from 10.3.50.0/24 to monitor your swiftstore
ndoes.

## Setting-up redis cluster

Currently, this is not yet automated:

```
# redis-cli -h 192.168.101.2 --cluster create 192.168.101.2:6379 192.168.101.3:6379 192.168.101.4:6379
```

## Enabling cloudkitty rating

First, enable the hashmap module:

```
cloudkitty module enable hashmap
cloudkitty module set priority hashmap 100
```

Note that the error 503 may be just ignored, it still works, as "module
list" shows. Now, let's add rating for instances:

```
cloudkitty hashmap group create instance_uptime_flavor
cloudkitty hashmap service create compute
cloudkitty hashmap field create 96a34245-83ae-406b-9621-c4dcd627fb8e flavor
```

The above ID is the one of the hashmap service create. Then we reuse the ID
of the field create we just had for the -f parameter, and the group ID for
the -g parameter below:
```
cloudkitty hashmap mapping create --field-id ce85c041-00a9-4a6a-a25d-9ebf028692b6 --value demo-flavor -t flat -g 2a986ce8-60a3-4f09-911e-c9989d875187 0.03
```

## Adding compute nodes

To add the compute node to the cluster and check it's there, on the controller, do:

```
# . oci-openrc
# su nova -s /bin/sh -c "nova-manage cell_v2 discover_hosts"
# openstack hypervisor list
+----+-------------------------------+-----------------+---------------+-------+
| ID | Hypervisor Hostname           | Hypervisor Type | Host IP       | State |
+----+-------------------------------+-----------------+---------------+-------+
|  4 | swift01-compute-1.example.com | QEMU            | 192.168.103.7 | up    |
+----+-------------------------------+-----------------+---------------+-------+
```

There's nothing more to it... :)

## Installing a first OpenStack image

```
wget http://cdimage.debian.org/cdimage/openstack/current-9/debian-9-openstack-amd64.qcow2
openstack image create \
	--container-format bare --disk-format qcow2 \
	--file debian-9-openstack-amd64.qcow2 \
	debian-9-openstack-amd64
```

## Setting-up networking

There's many ways to handle networking in OpenStack. This documentation only
quickly covers one way, and it is out of the scope of this doc to explain
all of OpenStack networking. However, the reader must know that OCI is
setting-up compute nodes using DVR (Distributed Virtual Routers), which
means a Neutron router is installed on every compute nodes. Also,
OpenVSwitch is used, using VXLan between the compute nodes. Anyway, here's
one way to setup networking. Something like this may do it:

```
# Create external network
openstack network create --external --provider-physical-network external --provider-network-type flat ext-net
openstack subnet create --network ext-net --allocation-pool start=192.168.105.100,end=192.168.105.199 --dns-nameserver 84.16.67.69 --gateway 192.168.105.1 --subnet-range 192.168.105.0/24 --no-dhcp ext-subnet

# Create internal network
openstack network create --share demo-net
openstack subnet create --network demo-net --subnet-range 192.168.200.0/24 --dns-nameserver 84.16.67.69 demo-subnet

# Create router, add it to demo-subnet and set it as gateway
openstack router create demo-router
openstack router add subnet demo-router demo-subnet
openstack router set demo-router --external-gateway ext-net

# Create a few floating IPs
openstack floating ip create ext-net
openstack floating ip create ext-net
openstack floating ip create ext-net
openstack floating ip create ext-net
openstack floating ip create ext-net

# Add rules to the admin's security group to allow ping and ssh
SECURITY_GROUP=$(openstack security group list --project admin --format=csv | q -d , -H 'SELECT ID FROM -')
openstack security group rule create --ingress --protocol tcp --dst-port 22 ${SECURITY_GROUP}
openstack security group rule create --protocol icmp --ingress ${SECURITY_GROUP}
```

## Adding an ssh key

```
openstack keypair create --public-key ~/.ssh/id_rsa.pub demo-keypair
```

## Creating flavor

```
openstack flavor create --ram 2048 --disk 5 --vcpus 1 demo-flavor
openstack flavor create --ram 6144 --disk 20 --vcpus 2 cpu2-ram6-disk20
openstack flavor create --ram 12288 --disk 40 --vcpus 4 cpu4-ram12-disk40
```

## Boot a VM

```
#!/bin/sh

set -e
set -x

NETWORK_ID=$(openstack network list --name demo-net -c ID -f value)
IMAGE_ID=$(openstack image list -f csv 2>/dev/null | q -H -d , "SELECT ID FROM - WHERE Name LIKE 'debian-9%.qcow2'")
FLAVOR_ID=$(openstack flavor show demo-flavor -c id -f value)

openstack server create --image ${IMAGE_ID} --flavor ${FLAVOR_ID} \
	--key-name demo-keypair --nic net-id=${NETWORK_ID} --availability-zone nova:swift01-compute-1.infomaniak.ch demo-server
```

## Add billing

The below script will rate "demo-flavor" at 0.01:

```
cloudkitty module enable hashmap
cloudkitty module set priority hashmap 100
cloudkitty hashmap group create instance_uptime_flavor_id
GROUP_ID=$(cloudkitty hashmap group list -f value -c "Group ID")

cloudkitty hashmap service create instance
SERVICE_ID=$(cloudkitty hashmap service list -f value -c "Service ID")

cloudkitty hashmap field create ${SERVICE_ID} flavor_id
FIELD_ID=$(cloudkitty hashmap field list ${SERVICE_ID} -f value -c "Field ID")

FLAVOR_ID=$(openstack flavor show demo-flavor -f value -c id)

cloudkitty hashmap mapping create 0.01 --field-id ${FIELD_ID} --value ${FLAVOR_ID} -g ${GROUP_ID} -t flat
```

The rest may be found here: https://docs.openstack.org/cloudkitty/latest/user/rating/hashmap.html

Also, add the role rating to the admin:

```
openstack role add --user admin --project admin rating
```

Note: currently, after installing the cluster, all ceilometer agents must be
restarted in order to obtain metrics, even though they appear to be well
configured.

## Add Octavia service

All of what's done below can be done with 2 helper scripts:

```
oci-octavia-amphora-secgroups-sshkey-lbrole-and-network 
oci-octavia-certs
```

If you wish to do things manually, here's how it works.

Create the Amphora image. This can be done with DIB (Disk Image Builder)
like this:

```
sudo apt-get install git python-pip python-virtualenv python-diskimage-builder qemu kpartx debootstrap
git clone https://github.com/openstack/octavia
cd octavia/diskimage-create
./diskimage-create.sh
openstack image create --container-format bare --disk-format qcow2 --file amphora-x64-haproxy.qcow2 amphora-x64-haproxy.qcow2
openstack image set --tag amphora amphora-x64-haproxy.qcow2
```

or you can use openstack-debian-image, simply launching the contrib script:

```
/usr/share/doc/openstack-debian-images/examples/octavia/amphora-build
```

Create the Octavia network. If, like in the PoC package, you are
running with a specific br-lb bridge bound to an external network called
external1, something like this will do:

```
openstack network create --external --provider-physical-network external1 --provider-network-type flat lb-mgmt-net
openstack subnet create --network lb-mgmt-net --allocation-pool start=192.168.104.4,end=192.168.104.250 --dns-nameserver 84.16.67.69 --dns-nameserver 84.16.67.70 --gateway 192.168.104.1 --subnet-range 192.168.104.0/24 lb-mgmt-subnet
```

Then we need s specific security groups for Octavia (make sure to use
/root/octavia-openrc, not the admin's one):

```
openstack security group create lb-mgmt-sec-grp
openstack security group rule create --protocol icmp lb-mgmt-sec-grp
openstack security group rule create --protocol tcp --dst-port 22 lb-mgmt-sec-grp
openstack security group rule create --protocol tcp --dst-port 9443 lb-mgmt-sec-grp
openstack security group rule create --protocol icmpv6 --ethertype IPv6 --remote-ip ::/0 lb-mgmt-sec-grp
openstack security group rule create --protocol tcp --dst-port 22 --ethertype IPv6 --remote-ip ::/0 lb-mgmt-sec-grp
openstack security group rule create --protocol tcp --dst-port 9443 --ethertype IPv6 --remote-ip ::/0 lb-mgmt-sec-grp

openstack security group create lb-health-mgr-sec-grp
openstack security group rule create --protocol udp --dst-port 5555 lb-health-mgr-sec-grp
openstack security group rule create --protocol udp --dst-port 5555 --ethertype IPv6 --remote-ip ::/0 lb-health-mgr-sec-grp
```

Then we create an ssh keypair:

```
mkdir /etc/octavia/.ssh
ssh-keygen -t rsa -f /etc/octavia/.ssh/octavia_ssh_key
chown -R octavia:octavia /etc/octavia/.ssh
rsync -e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no' -avz --delete /etc/octavia/.ssh/ root@z-controller-2:/etc/octavia/.ssh/
rsync -e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no' -avz --delete /etc/octavia/.ssh/ root@z-controller-3:/etc/octavia/.ssh/
. /root/octavia-openrc
openstack keypair create --public-key /etc/octavia/.ssh/octavia_ssh_key.pub octavia-ssh-key
```

Make the certs as per the upstream tutorial at https://docs.openstack.org/octavia/latest/admin/guides/certificates.html

Rsync the certs to the other 2 controllers:

```
rsync -e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no' -avz --delete /etc/octavia/certs/ root@z-controller-2:/etc/octavia/certs/
rsync -e 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no' -avz --delete /etc/octavia/certs/ root@z-controller-3:/etc/octavia/certs/
```

Edit octavia.conf and set amp_boot_network_list and amp_secgroup_list IDs.

Then restart all Octavia services on all controllers.

Create the load-balancer_admin role and assign it:

```
openstack role create load-balancer_admin
openstack role add --project admin --user admin load-balancer_admin
```

Now, one must set, with ocicli, the boot network and security group list for
the amphora:

```
ocicli cluster-set swift01 \
	--amp-boot-network-list 0c50875f-368a-4f43-802a-8350b330c127 \
	--amp-secgroup-list b94afddb-4fe1-4450-a1b8-25f36a354b7d,012584cd-ffde-483b-a55a-a1afba52bc20
```

Then we can start using Octavia:

```
openstack loadbalancer create --name lb-test-1 --vip-subnet-id ext-subnet
```
How to use the load balancer is described here:

https://docs.openstack.org/octavia/latest/user/guides/basic-cookbook.html

## Setting-up no limits for services resources

As some services may spawn instances, like for example Octavia or Magnum, it
may be desirable to set no limit for some resources of the services project:

```
openstack quota set --secgroup-rules -1 --secgroups -1 --instances -1 --ram -1 --cores -1 --ports -1 services
```

The quota will apply for the virtual resources the services project will
create, for example, use openstack loadbalancer quota show PROJECT_NAME to
set the max number of loadbalancer for a project.

## Add Magnum service

First, upload the coreos image and set the property correctly:

```
openstack image create --file coreos_production_openstack_image.img coreos_production_openstack_image.img
openstack image set --property os_distro=coreos coreos_production_openstack_image.img
```

Then create the COE template:

```
openstack coe cluster template create k8s-cluster-template \
    --image coreos_production_openstack_image.img --keypair demo-keypair \
    --external-network ext-net --dns-nameserver 84.16.67.69 --flavor demo-flavor \
    --docker-volume-size 5 --network-driver flannel --coe kubernetes
```

Then create the Magnum cluster:

```
openstack coe cluster create k8s-cluster \
                      --cluster-template k8s-cluster-template \
                      --master-count 1 \
                      --node-count 2
```

Looks like coreos wouldn't work for k8s. Instead:

```
wget https://download.fedoraproject.org/pub/alt/atomic/stable/Fedora-Atomic-27-20180419.0/CloudImages/x86_64/images/Fedora-Atomic-27-20180419.0.x86_64.qcow2
openstack image create \
                      --disk-format=qcow2 \
                      --container-format=bare \
                      --file=Fedora-Atomic-27-20180419.0.x86_64.qcow2 \
                      --property os_distro='fedora-atomic' \
                      fedora-atomic-latest
openstack coe cluster template create kubernetes-cluster-template \
	--image fedora-atomic-latest --keypair demo-keypair \
	--external-network ext-net --dns-nameserver 84.16.67.69 \
	--master-flavor demo-flavor --flavor demo-flavor \
	--docker-volume-size 5 --network-driver flannel \
	--coe kubernetes
```