<?php

function page_header($con,$conf){
	if( isset($_REQUEST["h-open-tab"])){
		switch($_REQUEST["h-open-tab"]){
		case "Clusters":
			$onload_h_tab = "ClustersBt";
			break;
		case "Roles":
			$onload_h_tab = "RolesBt";
			break;
		case "Networks":
			$onload_h_tab = "NetworksBt";
			break;
		case "Locations":
			$onload_h_tab = "LocationsBt";
			break;
		case "Swift-regions":
			$onload_h_tab = "Swift-regionsBt";
			break;
		default:
			$onload_h_tab = "MachinesBt";
			break;
		}
	}else{
		$onload_h_tab = "MachinesBt";
	}

	if( isset($_REQUEST["v-open-tab"])){
		// This is to check there's no XSS here !
		if(validate_fqdn("v-open-tab") === FALSE){
			$onload_v_tab = "defaultOpenCluster";
		}else{
			$onload_v_tab = $_REQUEST["v-open-tab"]."Bt";
		}
	}else{
		$onload_v_tab = "defaultOpenCluster";
	}

	$out = "
<html><head><link rel='stylesheet' href='oci.css'><style>
body {font-family: Arial;}

/*************** Tooltips for MAC addresses, etc. ***************/
.tooltip {
    position: relative;
    display: block;
    border-bottom: 1px dotted black;
    color: #FFFFFF !important;
}

.tooltip .tooltiptext {
    visibility: hidden;
    width: 240px;
    background-color: #000000 !important;
    color: #FFFFFF !important;
    text-align: center;
    border-radius: 6px;
    padding: 5px 0;

    /* Position the tooltip */
    position: absolute;
    z-index: 1;
}

.tooltip:hover .tooltiptext {
    visibility: visible;
}

/*************** horizontal tabs ***************/
/* Style the tab */
.tab {
    overflow: hidden;
    border: 1px solid #ccc;
    background-color: #f1f1f1;
}

/* Style the buttons inside the tab */
.tab button {
    background-color: inherit;
    float: left;
    border: none;
    outline: none;
    cursor: pointer;
    padding: 14px 16px;
    transition: 0.3s;
    font-size: 17px;
}

/* Change background color of buttons on hover */
.tab button:hover {
    background-color: #ddd;
}

/* Create an active/current tablink class */
.tab button.active {
    background-color: #ccc;
}

/* Style the tab content */
.tabcontent {
    display: none;
    padding: 6px 12px;
    border: 1px solid #ccc;
    border-top: none;
}
/*************** vertical tabs ***************/
/* Style the tab */
.vtab {
    float: left;
    border: 1px solid #ccc;
    background-color: #f1f1f1;
}

/* Style the buttons inside the tab */
.vtab button {
    display: block;
    background-color: inherit;
    color: black;
    padding: 22px 16px;
    width: 100%;
    border: none;
    outline: none;
    text-align: left;
    cursor: pointer;
    transition: 0.3s;
    font-size: 17px;
}

/* Change background color of buttons on hover */
.vtab button:hover {
    background-color: #ddd;
}

/* Create an active/current tab button class */
.vtab button.active {
    background-color: #ccc;
}

/* Style the tab content */
.vtabcontent {
    float: left;
    padding: 0px 12px;
    border: 1px solid #ccc;
    border-left: none;
    height: 300px;
}
</style>
<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName(\"tabcontent\");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = \"none\";
    }
    tablinks = document.getElementsByClassName(\"tablinks\");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(\" active\", \"\");
    }
    document.getElementById(tabName).style.display = \"block\";
    evt.currentTarget.className += \" active\";
}


function openVTab(evt, clusterName) {
    var i, vtabcontent, vtablinks;
    vtabcontent = document.getElementsByClassName(\"vtabcontent\");
    for (i = 0; i < vtabcontent.length; i++) {
        vtabcontent[i].style.display = \"none\";
    }
    vtablinks = document.getElementsByClassName(\"vtablinks\");
    for (i = 0; i < vtablinks.length; i++) {
        vtablinks[i].className = vtablinks[i].className.replace(\" active\", \"\");
    }
    document.getElementById(clusterName).style.display = \"block\";
    evt.currentTarget.className += \" active\";
}

function myLoadEvent(){
    document.getElementById(\"$onload_h_tab\").click();
    document.getElementById(\"$onload_v_tab\").click();
}
</script>
</head>";
	return $out;
}

?>
