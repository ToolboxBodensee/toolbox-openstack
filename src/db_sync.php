#!/usr/bin/env php

<?php

# This script updates the strutures of the database,
# so it keeps compatibility with backward versions.
# It normaly doesn't alter the CONTENT of the db itself.

require_once("inc/read_conf.php");
require_once("inc/db.php");

$conf = readmyconf();
$con = connectme($conf);

if(isset($_SERVER['REMOTE_ADDR'])){
	die("This script should be invoked from the shell, not from the web.");
}

echo "==> Restor DB script for OCI\n";

#Set default timezona tog et rid of warnings...
if(function_exists("date_default_timezone_set") and function_exists("date_default_timezone_get"))
@date_default_timezone_set(@date_default_timezone_get());

chdir(dirname(__FILE__));
require("oci_db.php");

function mysql_table_exists($con, $table){
	$exists = mysqli_query($con, "SELECT 1 FROM $table LIMIT 0");
	if ($exists) return true;
	return false;
}

// Return true=field found, false=field not found
function findFieldInTable($con, $table,$field){
	$q = "SELECT * FROM $table LIMIT 0;";
	$res = mysqli_query($con, $q) or die("Could not query $q!");;
	$num_fields = mysqli_num_fields($res);
	for($i=0;$i<$num_fields;$i++){
		$fld_object = mysqli_fetch_field_direct($res, $i);
		if( strtolower($fld_object->name) == strtolower($field)){
			return true;
		}
	}
	mysqli_free_result($res);
	return false;
}

function findKeyInTable($con, $table,$key){
	$q = "SHOW INDEX FROM $table";
	$res = mysqli_query($con, $q) or die("Could not query $q!");
	$num_keys = mysqli_num_rows($res);
	for($i=0;$i<$num_keys;$i++){
		$a = mysqli_fetch_array($res);
		if(strtolower($a["Key_name"]) == strtolower($key)){
			mysqli_free_result($res);
			return true;
		}
	}
	mysqli_free_result($res);
	return false;
}

function my_table_exists($con, $table_name){
	$q = "SHOW TABLES LIKE '$table_name'";
	$res = mysqli_query($con, $q) or die("Could not query $q!");
	return mysqli_num_rows($res) > 0;
}

$tables = $database["tables"];
$nbr_tables = sizeof($tables);
echo "Checking and updating $nbr_tables table structures:";
$tblnames = array_keys($tables);
for($i=0;$i<$nbr_tables;$i++){
	$curtbl = $tblnames[$i];
	$t = $tables[$curtbl];
	echo " ".$curtbl;
	$allvars = $t["vars"];
	$varnames = array_keys($allvars);
	$numvars = sizeof($allvars);
	// If no table exist, then build a CREATE TABLE statement
	if( !my_table_exists($con, $curtbl) ){
		$qc = "CREATE TABLE IF NOT EXISTS ".$curtbl."(\n";
		for($j=0;$j<$numvars;$j++){
			if($j != 0){
				$qc .= ",\n";
			}
			$qc .= "  ".$varnames[$j] ." ".$allvars[$varnames[$j]];
		}
		if( isset( $t["primary"] ) ){
			// Todo: remove the parentesys from dtc_db.php and add them here
			$qc .= ",\n  PRIMARY KEY ".$t["primary"];
		}
		if( isset( $t["keys"] )){
			$nkeys = sizeof($t["keys"]);
			$ak = array_keys($t["keys"]);
			for($x=0;$x<$nkeys;$x++){
				// Todo: add parentesis here, remove them from the dtc_db.php file
				$qc .= ",\n  UNIQUE KEY ".$ak[$x]." ".$t["keys"][ $ak[$x] ];
			}
		}
		if( isset( $t["index"] )){
			$nidx = sizeof($t["index"]);
			$ai = array_keys($t["index"]);
			for($x=0;$x<$nidx;$x++){
				$qc .= ",\n  KEY ".$ai[$x]." ".$t["index"][ $ai[$x] ];
			}
		}
		if( isset( $t["max_rows"] )){
			$qc .= "\n)MAX_ROWS=1 ENGINE=MyISAM\n";
		}else{
			$qc .= "\n)ENGINE=MyISAM\n";
		}
		// echo $q;
		$r = mysqli_query($con, $qc)or die("Cannot execute query: \"$qc\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
	// If the table exists already, then check all variables types, primary key, unique keys
	// and remove useless variables.
	// All this to make sure that we upgrade correctly each tables.
	}else{
		// First, we check if all feilds from dtc_db.php are present
		for($j=0;$j<$numvars;$j++){
			$v = $varnames[$j];
			$vc = $allvars[$v];
			// If the field is present, create it.
			$q = "SHOW FULL COLUMNS FROM $curtbl WHERE Field='$v'";
			$r = mysqli_query($con, $q)or die("Cannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
			$n = mysqli_num_rows($r);
			if($n == 0){
				// If we are adding a new auto_increment field, then we must drop the current PRIMARY KEY
				// before adding this new field.
				if( strstr($vc, "auto_increment") != FALSE){
					// In case there was a primary key, drop it!
					$q = "ALTER IGNORE TABLE $curtbl DROP PRIMARY KEY;";
					// Don't die, in some case it can fail!
					$r = mysqli_query($con, $q); // or die("\nCannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
					$q = "ALTER TABLE $curtbl ADD $v $vc PRIMARY KEY;";
					$r = mysqli_query($con, $q)or print("\nCannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con)."\n");
				}else{
					$q = "ALTER TABLE $curtbl ADD $v $vc;";
					echo " (add var: $v)";
					$r = mysqli_query($con, $q)or print("\nCannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con)."\n");
				}
			// If it is present in MySQL already, then we need to check if types are marching
			// if types don't match, then we issue an ALTER TABLE
			}else{
				$a = mysqli_fetch_array($r);
				$a_extra = $a["Extra"];
				$a_type = $a["Type"];
				$a_collate = $a["Collation"];
				switch($a_type){
				case "blob":
					$type = $a_type;
					break;
				case "text":
					$type = $a_type;
					$q2 = "SELECT character_set_name FROM information_schema.`COLUMNS` WHERE table_name = '".$curtbl."' AND column_name = '".$v."'";
                        		$r2 = mysqli_query($con, $q2)or die("Cannot execute query: \"$q2\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
					$a2 = mysqli_fetch_array($r2);
					if($a2["character_set_name"] != 'latin1'){
						$type .= ' character set '.$a2["character_set_name"];
					} 
					mysqli_free_result($r2);
					if($a_collate != 'latin1_bin'){
						$type .= ' collate '.$a_collate;
					}
					if($a["Null"] == "NO"){
						$type .= " NOT NULL";
					}
					break;
				case "time":
					if($a["Null"] == "NO"){
						$type = $a_type." NOT NULL default '00:00:00'";
					}else{
						$type = $a_type." default NULL";
					}
					break;
				case "date":
					if($a["Null"] == "NO"){
						$type = $a_type." NOT NULL default '0000-00-00'";
					}else{
						$type = $a_type." default NULL";
					}
					break;
				case "datetime":
					if($a["Null"] == "NO"){
						$type = $a_type." NOT NULL default '0000-00-00 00:00:00'";
					}else{
						$type = $a_type." default NULL";
					}
					break;
				case "timestamp":
					if($a["Null"] == "NO"){
						$type = $a_type." NOT NULL default '0'";
					}else{
						$type = $a_type." default NULL";
					}
				default:
					if($a_extra == "auto_increment"){
						$type = $a_type." NOT NULL auto_increment";
					}else{
						if($a["Null"] == "NO"){
							$type = $a_type." NOT NULL default '".$a["Default"]."'";
						}else{
							$type = $a_type." NULL default NULL";
						}
					}
				}
				// If MySQL and dtc_db.php don't match, it means we need to update the variable type
				if($type != $vc){
					echo "\n\nIn db, table $curtbl, field $v: \"$type\"\n";
					echo "In file, table $curtbl, field $v: \"$vc\"\n";
					$q = "ALTER TABLE $curtbl CHANGE $v $v $vc;";
					echo "Altering: $q\n";
//					$r = mysqli_query($con, $q)or print("\nCannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con)."\n");
				}
			}
		}

		// Make sure all the unique keys of dtc_db.php are in MySQL
		if( isset($t["keys"]) ){
			$keys = $t["keys"];
			$numvars = sizeof($keys);
			$varnames = array_keys($keys);
			for($j=0;$j<$numvars;$j++){
				$key_name = $varnames[$j];
				if(!findKeyInTable($con, $curtbl,$key_name)){
					$var_2_add = "UNIQUE KEY ".$key_name;
					$q = "ALTER TABLE ".$curtbl." ADD $var_2_add ".$keys[$key_name].";";
					$r = mysqli_query($q)or die("\nCannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
				}
			}
		}
		// Make sure all keys in MySQL are also present in dtc_db.php
		// and dorps the one that aren't in both

		// First, check if primary keys in MySQL and in dtc_db.php are matching
		// So we first get the primary key from DB, and then compare.
		$q = "SHOW INDEX FROM $curtbl WHERE Key_name='PRIMARY'";
		$r = mysqli_query($con, $q)or die("Cannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
		$n = mysqli_num_rows($r);
		$pkey = "";
		for($j=0;$j<$n;$j++){
			$apk = mysqli_fetch_array($r);
			if($j>0){
				$pkey .= ",";
			}
			$pkey .= $apk["Column_name"];
		}
		// Is this a primary key that is new in dtc_db.php?
		if($n == 0 && isset($t["primary"])){
			$q = "ALTER IGNORE TABLE $curtbl ADD PRIMARY KEY dtcprimary ".$t["primary"].";";
			$r = mysqli_query($con, $q)or die("Cannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
		// Does dtc_db.php drops a primary key?
		}elseif($n > 0 && !isset($t["primary"])){
			$q = "ALTER IGNORE TABLE $curtbl DROP PRIMARY KEY;";
			$r = mysqli_query($con, $q)or die("Cannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
		// If there's no primary key at all, do nothing...
		}elseif($n == 0 && !isset($t["primary"])){
			echo "";
		// Are the primary keys in dtc_db and in MySQL different? If yes, drop and add
		}elseif( "(".$pkey.")" != $t["primary"] ){
			$pk = $t["primary"];
			// Check if we have a auto_increment value somewhere, it which case we don't touch the PRIMARY key
			// Simply because it has been done just above !
			$nop_pk = substr($pk,1,strlen($pk)-2); // The string without the (parrentesys,between,field,names)
			if(isset($t["vars"][ $nop_pk ]) ){
				if( strstr($t["vars"][ $nop_pk ],"auto_increment") === FALSE){
					// Always remove and readd the PRIMARY KEY in case it has changed
					$q = "ALTER IGNORE TABLE $curtbl DROP PRIMARY KEY;";
					$r = mysqli_query($con, $q)or die("Cannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
					$q = "ALTER IGNORE TABLE $curtbl ADD PRIMARY KEY dtcprimary $pk;";
					$r = mysqli_query($con, $q)or die("Cannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
				}
			}
		}


		// We have to rebuild indexes in order to get rid of past mistakes in the db in case of panel upgrade
		$q = "SHOW INDEX FROM $curtbl WHERE Key_name NOT LIKE 'PRIMARY' AND Non_unique='1' and Seq_in_index='1';";
		$r = mysqli_query($con, $q)or die("Cannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
		$n = mysqli_num_rows($r);
		for($j=0;$j<$n;$j++){
			$a = mysqli_fetch_array($r);
			// Drop all indexes
			$q2 = "ALTER TABLE $curtbl DROP INDEX ".$a["Key_name"].";";
			$r2 = mysqli_query($con, $q2)or die("Cannot execute query: \"$q2\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
		}
		// The readd all indexes
		if( isset($t["index"]) ){
			$indexes = $t["index"];
			$numvars = sizeof($indexes);
			if($numvars > 0){
				$varnames = array_keys($indexes);
				for($j=0;$j<$numvars;$j++){
					$v = $varnames[$j];
					// We have to rebuild indexes in order to get rid of past mistakes in the db in case of panel upgrade
					if(findKeyInTable($con, $curtbl,$v)){
						$q = "ALTER TABLE $curtbl DROP INDEX ".$v."";
						$r = mysqli_query($con, $q)or die("Cannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
					}
					$q = "ALTER TABLE $curtbl ADD INDEX ".$v." ".$indexes[$v].";";
					$r = mysqli_query($con, $q)or die("Cannot execute query: \"$q\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
				}
			}
		}
	}
}
echo "\n";

### After all the db schema is in, perform the default INSERTs
$q = array();

$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (1,'compute');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (2,'controller');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (3,'network');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (4,'volume');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (5,'sql');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (6,'messaging');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (7,'cephmon');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (8,'cephosd');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (9,'swiftproxy');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (10,'swiftstore');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (11,'custom');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (12,'network');";
$q[] = "INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (13,'debmirror');";

foreach ($q as $sql){
	$r = mysqli_query($con, $sql);
	if($r === FALSE){
		print("MySQL error: ". mysqli_error($con) . "\n");
		print("when evaluating: $sql\n\n");
	}
}
?>
