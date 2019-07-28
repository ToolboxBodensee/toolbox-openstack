#!/usr/bin/env php

<?php

require_once("inc/read_conf.php");
require_once("inc/db.php");

$conf = readmyconf();
$con = connectme($conf);

if(isset($_SERVER['REMOTE_ADDR'])){
	die("This script should be invoked from the shell, not from the web.");
}

$q = "SHOW TABLES";
$result = mysqli_query($con, $q)or die("Cannot query \"$q\" !\nError in ".__FILE__." line ".__LINE__.": ".mysqli_error($con));

if (!$result) {
   echo "DB Error, could not list tables\n";
   echo 'MySQL Error: ' . mysqli_error($con);
   exit;
}

$out = "";

$out .= "<?php
// Automatic database array generation for OCI
// Generation date: ".date("Y-m(M)-d l H:i")."
\$database = array(
\"version\" => \"1.0.0\",
\"tables\" => array(\n";

$num = mysqli_num_rows($result);
for($j=0;$j<$num;$j++){
	$row = mysqli_fetch_row($result);
	$out .= "\t\"$row[0]\" => array(\n";
	$q = "DESCRIBE $row[0];";
	$r = mysqli_query($con, $q)or die("Cannot query \"$q\" !\nError in ".__FILE__." line ".__LINE__.": ".mysqli_error($con));
	$n = mysqli_num_rows($r);
	$out .= "\t\t\"vars\" => array(\n";
	for($i=0;$i<$n;$i++){
		$a = mysqli_fetch_array($r);
		$out .= "\t\t\t\"".$a['Field']."\" => \"".$a['Type'];

		$qshow = "SHOW FULL COLUMNS FROM $row[0] WHERE Field='".$a['Field']."'";
		$rshow = mysqli_query($con, $qshow)or die("Cannot query \"$qshow\" !\nError in ".__FILE__." line ".__LINE__.": ".mysqli_error($con));
		$myfield = mysqli_fetch_array($rshow);
		$a_type = $myfield["Type"];
		$a_extra = $myfield["Extra"];
		if(isset($myfield["Collation"])){
			$a_collate = $myfield["Collation"];
		}
		switch($a_type){
		case "text":
			$q2 = "SELECT character_set_name FROM information_schema.`COLUMNS` WHERE table_name = '".$row[0]."' AND column_name = '".$a['Field']."'";
			$r2 = mysqli_query($con, $q2)or die("Cannot execute query: \"$q2\" line ".__LINE__." in file ".__FILE__.", mysql said: ".mysqli_error($con));
			$a2 = mysqli_fetch_array($r2);
			if($a2["character_set_name"] != 'latin1'){
				$out .= ' character set '.$a2["character_set_name"];
			}
			mysqli_free_result($r2);
			if($a_collate != 'latin1_bin'){
				$out .= ' collate '.$a_collate;
			}
			break;
		default:
			if($a['Null'] == 'YES'){
				$out .= " NULL";
				if($a['Default'] == NULL){
					$out .= " default NULL";
				}
			}else{
				$out .= " NOT NULL";
				if($a['Extra'] != ''){
					$out .= " ".$a['Extra'];
				}else{
					if($a['Default'] == "NULL" && $a["Extra"] == ''){
						$out .= " default ''";
					}else{
						$out .= " default '".$a['Default']."'";
					}
				}
			}
			break;
		}
		$out .= "\"";
		if($i < $n-1)
			$out .= ",\n";
		else
			$out .= "\n\t\t\t)";
	}
	$q = "SHOW INDEX FROM $row[0];";
        $r = mysqli_query($con, $q)or die("Cannot query \"$q\" !\nError in ".__FILE__." line ".__LINE__.": ".mysqli_error($con));
        $n = mysqli_num_rows($r);
	if($i > 0){
		$out .= ",\n";
		// Get all the keys and index in memory for the given table
		unset($primaries);
		unset($keys);
		unset($indexes);
		for($i=0;$i<$n;$i++){
        	        $a = mysqli_fetch_array($r);
			if($a['Key_name'] == "PRIMARY"){
				$primaries[] = $a['Column_name'];
			}else{
				if($a['Non_unique'] == "0"){
					$keys[ $a['Key_name'] ][] = $a['Column_name'];
				}else{
					$indexes[ $a['Key_name'] ][] = $a['Column_name'];
				}
			}
		}
		// Produce the array of index and keys
		if(sizeof($primaries) > 0){
			$out .= "\t\t\"primary\" => \"(".$primaries[0];

			// Display all the keys here...
			for($i=1;$i<sizeof($primaries);$i++){
				$out .= ",".$primaries[$i];
			}
			$out .= ")\"";
			if( (isset($keys) && sizeof($keys) > 0) || (isset($indexes) && sizeof($indexes) > 0)){
				$out .= ",\n";
			}else{
				$out .= "\n";
			}
		}
		if(isset($keys) && sizeof($keys) > 0){
			$out .= "\t\t\"keys\" => array(\n";
			
			// Backup all the UNIC keys here
			$kkeys = @array_keys($keys);
			for($i=0;$i<sizeof($kkeys);$i++){
				$cur = $keys[ $kkeys[$i] ];
				$out .= "\t\t\t\"".$kkeys[$i]."\" => \"(";
				for($k=0;$k<sizeof($cur);$k++){
					if($k>0)	$out .= ",";
					$out .= $cur[$k];
				}
				if($i<sizeof($kkeys)-1)
					$out .= ")\",\n";
				else
					$out .= ")\"\n";
			}
			
			$out .= "\t\t\t)";
			if(isset($indexes) && sizeof($indexes) > 0){
				$out .= ",\n";
			}else{
				$out .= "\n";
			}
		}
		if(isset($indexes) && sizeof($indexes) > 0){
			$out .= "\t\t\"index\" => array(\n";
			
			// Backup all the INDEX keys here
			$kkeys = @array_keys($indexes);
			for($i=0;$i<sizeof($kkeys);$i++){
				$cur = $indexes[ $kkeys[$i] ];
				$out .= "\t\t\t\"".$kkeys[$i]."\" => \"(";
				for($k=0;$k<sizeof($cur);$k++){
					if($k>0)	$out .= ",";
					$out .= $cur[$k];
				}
				if($i<sizeof($kkeys)-1)
					$out .= ")\",\n";
				else
					$out .= ")\"\n";
			}


			$out .= "\t\t\t)\n";
		}
	}else{
		$out .= "\n";
	}
	if($j < $num-1)
		$out .= "\t\t),\n";
	else
		$out .= "\t\t)\n";
	mysqli_free_result($r);
}
$out .= "\t)\n);\n?>\n";
mysqli_free_result($result);

$fp = fopen("oci_db.php","w+b");
fwrite($fp,$out);
fclose($fp);

?>