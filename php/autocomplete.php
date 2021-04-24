<?php

include 'common.php';

$db = establish_database();

$service = "";
$customer_email = "";
$customer_phone = "";
$end = "";
$order = "";
$completed = "";
$sql = "SELECT * FROM {$DB_PREFIX}orders;";
$result = $db->query($sql);
foreach ($result as $row) {

    $service = $row['service'];
    $customer_email = $row['customer_email'];
    $customer_phone = $row['customer_phone'];
    $timestamp = $row['timestamp'];
    $start = $row['start'];
    $end = $row['end'];
    $order = $row['order_number'];
    $status = $row['status'];
    $duration = $row['duration'];
    $schedule = $row['schedule'];
    $timezone = $row['timezone'];
    $wage = $row["wage"];
    $currently_paused = $row["currently_paused"];
    $paused_time = $row['paused_time'];

    $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
    $utc->setTimezone(new DateTimeZone($timezone));

    $message = $service . ' (' . $order . ') on ' . $utc->format('F j, Y, g:i a') . ' has been marked completed by our system. If the support provided by the provider was inadequate you can dispute the transaction by texting back DISPUTE to this number.';

    if ($status == 'st' && minutes_since($start) > ($duration * 60)) {
        if ($currently_paused == 'y') {
            resume_order($order);
        }
        start_stop_order($order);
    }
    
    if ($status == 'en' && minutes_since($schedule) > 1440) {
        mark_completed($order, $message);
    }
}
