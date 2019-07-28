<?php

function seconds_to_duration ($seconds){
    if(($seconds == 0) || ($seconds == 1)){
        return "$seconds sec";
    }
    if($seconds < 120){
        return "$seconds secs";
    }
    $minutes = floor($seconds / 60);
    if($minutes < 120){
        return "$minutes mins";
    }
    $hours = floor($minutes/60);
    if($hours < 48){
        return "$hours hours";
    }
    $days = floor($hours / 24);
    return "$days days";
}

function mb_to_smart_size ($mb) {
    if($mb < 4096){
        return $mb. " MB";
    }
    $gb = round($mb / 1024, 1);
    if($gb < 4096){
        return $gb . " GB";
    }
    $tb = round($gb / 1024, 1);
    return $tb . " TB";
}

?>
