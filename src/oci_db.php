<?php
// Automatic database array generation for OCI
// Generation date: 2019-03(Mar)-14 Thursday 14:42
$database = array(
"version" => "1.0.0",
"tables" => array(
	"blockdevices" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"machine_id" => "int(11) NOT NULL default '0'",
			"name" => "varchar(255) NOT NULL default ''",
			"size_mb" => "int(11) NOT NULL default '0'"
			),
		"primary" => "(id)"
		),
	"clusters" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"name" => "varchar(64) NOT NULL default ''",
			"domain" => "varchar(253) NOT NULL default ''",
			"vip_hostname" => "varchar(255) NOT NULL default ''",
			"first_master_machine_id" => "int(11) NULL default NULL",
			"first_sql_machine_id" => "int(11) NULL default NULL",
			"swift_part_power" => "int(11) NOT NULL default '18'",
			"swift_replicas" => "int(11) NOT NULL default '3'",
			"swift_min_part_hours" => "int(11) NOT NULL default '1'",
			"swift_proxy_hostname" => "varchar(255) NOT NULL default ''",
			"swift_encryption_key_id" => "varchar(255) NOT NULL default ''",
			"swift_disable_encryption" => "enum('yes','no') NOT NULL default 'yes'",
			"haproxy_custom_url" => "varchar(255) NOT NULL default ''",
			"statsd_hostname" => "varchar(255) NOT NULL default ''",
			"time_server_host" => "varchar(255) NOT NULL default '0.debian.pool.ntp.org'",
			"amp_secgroup_list" => "varchar(255) NOT NULL default ''",
			"amp_boot_network_list" => "varchar(255) NOT NULL default ''",
			"disable_notifications" => "enum('yes','no') NOT NULL default 'no'",
			"enable_monitoring_graphs" => "enum('yes','no') NOT NULL default 'no'",
			"monitoring_graphite_host" => "varchar(255) NOT NULL default ''",
			"monitoring_graphite_port" => "int(6) NOT NULL default '2003'",
			),
		"primary" => "(id)",
		"keys" => array(
			"name" => "(name)"
			)
		),
	"ifnames" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"machine_id" => "int(11) NOT NULL default '0'",
			"name" => "varchar(255) NOT NULL default ''",
			"macaddr" => "varchar(20) NOT NULL default ''",
			"max_speed" => "int(11) NOT NULL default '10'"
			),
		"primary" => "(id)"
		),
	"ips" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"network" => "int(11) NOT NULL default '0'",
			"ip" => "bigint(128) NOT NULL default '0'",
			"type" => "enum('4','6') NOT NULL default '4'",
			"machine" => "int(11) NOT NULL default '0'",
			"usefor" => "enum('machine','vip') NOT NULL default 'machine'",
			"vip_usage" => "varchar(64) NOT NULL default 'api'"
			),
		"primary" => "(id)",
		"keys" => array(
			"uniqueip" => "(ip)",
			"uniquemachine" => "(network,machine)"
			)
		),
	"locations" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"name" => "varchar(64) NOT NULL default ''",
			"swiftregion" => "varchar(64) NOT NULL default ''"
			),
		"primary" => "(id)",
		"keys" => array(
			"uniquename" => "(name)"
			)
		),
	"machines" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"memory" => "int(11) NOT NULL default '0'",
			"ipaddr" => "varchar(255) NOT NULL default ''",
			"serial" => "varchar(128) NOT NULL default ''",
			"product_name" => "varchar(128) NOT NULL default ''",
			"hostname" => "varchar(255) NOT NULL default ''",
			"installed" => "enum('yes','no') NOT NULL default 'no'",
			"puppet_status" => "enum('notrun','running','success','failure') NOT NULL default 'notrun'",
			"lastseen" => "timestamp NULL default NULL",
			"status" => "varchar(128) NOT NULL default 'None'",
			"role" => "varchar(64) NOT NULL default ''",
			"cluster" => "int(11) NULL default NULL",
			"ipmi_use" => "enum('yes','no') NOT NULL default 'no'",
			"ipmi_call_chassis_bootdev" => "enum('yes','no') NOT NULL default 'no'",
			"ipmi_addr" => "varchar(254) NOT NULL default ''",
			"ipmi_port" => "int(11) NOT NULL default '623'",
			"ipmi_username" => "varchar(64) NOT NULL default ''",
			"ipmi_password" => "varchar(64) NOT NULL default ''",
			"location_id" => "int(11) NULL default NULL",
			"notes" => "varchar(256) NOT NULL default ''",
			"loc_dc" => "varchar(255) NOT NULL default ''",
			"loc_row" => "varchar(255) NOT NULL default ''",
			"loc_rack" => "varchar(255) NOT NULL default ''",
			"loc_u_start" => "varchar(255) NOT NULL default ''",
			"loc_u_end" => "varchar(255) NOT NULL default ''",
			"ladvd_report" => "varchar(128) NOT NULL default ''",
			"bios_version" => "varchar(128) NOT NULL default ''",
			"ipmi_firmware_version" => "varchar(128) NOT NULL default ''",
			"ipmi_detected_ip" => "varchar(64) NOT NULL default ''",
			"use_ceph_if_available" => "enum('yes','no') NOT NULL default 'no'",
			"install_on_raid" => "enum('yes','no') NOT NULL default 'no'",
			"raid_type" => "enum('0','1','10','5') NOT NULL default '1'",
			"raid_dev0" => "varchar(64) NOT NULL default 'sda'",
			"raid_dev1" => "varchar(64) NOT NULL default 'sdb'",
			"raid_dev2" => "varchar(64) NOT NULL default 'sdc'",
			"raid_dev3" => "varchar(64) NOT NULL default 'sdd'",
			"serial_console_dev" => "varchar(64) NOT NULL default 'ttyS1'",
			),
		"primary" => "(id)",
		"keys" => array(
			"serial" => "(serial)"
			)
		),
	"networks" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"name" => "varchar(128) NOT NULL default ''",
			"ip" => "varchar(64) NOT NULL default ''",
			"cidr" => "int(3) NOT NULL default '0'",
			"is_public" => "enum('yes','no') NOT NULL default 'no'",
			"cluster" => "int(11) NULL default NULL",
			"role" => "varchar(64) NULL default NULL",
			"iface1" => "varchar(32) NULL default NULL",
			"iface2" => "varchar(32) NULL default NULL",
			"bridgename" => "varchar(32) NULL default NULL",
			"vlan" => "int(11) NULL default NULL",
			"mtu" => "int(11) NOT NULL default '0'",
			"location_id" => "int(11) NULL default NULL"
			),
		"primary" => "(id)",
		"keys" => array(
			"name" => "(name)"
			)
		),
	"passwords" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"cluster" => "int(11) NOT NULL default '0'",
			"service" => "varchar(64) NOT NULL default ''",
			"passtype" => "varchar(64) NOT NULL default ''",
			"pass" => "varchar(128) NOT NULL default ''",
			"passtxt1" => "text character set utf8 collate utf8_unicode_ci",
			"passtxt2" => "text character set utf8 collate utf8_unicode_ci"
			),
		"primary" => "(id)"
		),
	"rolecounts" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"cluster" => "int(11) NOT NULL default '0'",
			"role" => "int(11) NOT NULL default '0'",
			"count" => "int(11) NOT NULL default '0'"
			),
		"primary" => "(id)",
		"keys" => array(
			"cluster" => "(cluster,role)"
			)
		),
	"roles" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"name" => "varchar(64) NOT NULL default ''"
			),
		"primary" => "(id)",
		"keys" => array(
			"name" => "(name)"
			)
		),
	"swiftregions" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"name" => "varchar(64) NOT NULL default ''"
			),
		"primary" => "(id)",
		"keys" => array(
			"name" => "(name)"
			)
		),
	"users" => array(
		"vars" => array(
			"id" => "int(11) NOT NULL auto_increment",
			"login" => "varchar(128) NOT NULL default ''",
			"hashed_password" => "varchar(128) NOT NULL default ''",
			"use_radius" => "enum('yes','no') NOT NULL default 'yes'",
			"activated" => "enum('yes','no') NOT NULL default 'yes'",
			"is_admin" => "enum('yes','no') NOT NULL default 'no'"
			),
		"primary" => "(id)",
		"keys" => array(
			"name" => "(login)"
			)
		)
	)
);
?>
