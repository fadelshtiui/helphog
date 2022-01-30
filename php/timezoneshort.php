<?php

if (isset($_GET['timezone'])) {
    
    $timezone_id = $_GET['timezone'];
    
    $date = new DateTime(null, new DateTimeZone($timezone_id));
    echo $date->format('T');
    
}

?>