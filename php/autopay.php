<?php

include 'common.php';

$db = establish_database();

$result = $db->query("SELECT * FROM {$DB_PREFIX}orders;");
foreach ($result as $row) {

    try {

        if (minutes_since($row['mc_timestamp']) >= 1440 && $row["status"] == "mc") {

            pay_provider($row["order_number"]);
        }
    } catch (\Throwable $e) {

        error_log($e->getMessage());

        send_email('maksim_maxim@live.com', "no-reply@helphog.com", "FATAL ERROR - autopay.php (" . $row["order_number"] . ")", $e->getMessage());
        send_email('fadelshtiui@gmail.com', "no-reply@helphog.com", "FATAL ERROR - autopay.php (" . $row["order_number"] . ")", $e->getMessage());
    }
}
