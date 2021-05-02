<?php

include 'common.php';

$db = establish_database();

$result = $db->query("SELECT * FROM {$DB_PREFIX}orders;");
foreach ($result as $row) {
    
    if (minutes_since($row['mc_timestamp']) >= 1440 && $row["status"] == "mc") {
        
        pay_provider($row["order_number"]);
        
    }
}


