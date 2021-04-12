<?php

include 'common.php';

$db = establish_database();

$result = $db->query("SELECT * FROM {$DB_PREFIX}orders;");
foreach ($result as $row) {
    $order_number = $row["order_number"];
    $service = $row["service"];
    $people = $row["people"];
    $secondary_providers = $row["secondary_providers"];
    $schedule = $row["schedule"];
    $timezone = $row["timezone"];
    
    $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
    $utc->setTimezone(new DateTimeZone($timezone));
    $schedule = $utc->format('F j, Y, g:i a');
    
    if (minutes_since($row['mc_timestamp']) >= 1440 && $row["status"] == "mc") {
        
        pay_provider($order_number);
        
    }
}


