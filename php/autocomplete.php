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

    $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
    $utc->setTimezone(new DateTimeZone($timezone));

    $local_start = new DateTime(date('Y-m-d H:i:s', strtotime($start)), new DateTimeZone('UTC'));

    $message = $service . ' (' . $order . ') on ' . $utc->format('F j, Y, g:i a') . ' has been marked completed by our system. If the support provided by the provider was inadequate you can dispute the transaction by texting back DISPUTE to this number.';

    if ($wage == "per") {
        // condition:   order has been started and it's been more than the ordered time
        // action:      pause order if necessary, and then stop the order
        // reasoning:   worked time doesn't matter since it's a flat rate service
        if ($status == 'st' && minutes_since($schedule) > ($duration * 60)) { 
            if ($currently_paused == 'y') {
                resume_order($order);
            }
            start_stop_order($order);
        }
        // condition:   order has been stopped and 24 hours have passed since scheduled order time
        // action:      mark order completed
        // reasoning:   this gives provider time to upload receipts
        if ($status == 'en' && minutes_since($schedule) > 1440) { 
            mark_completed($order, $message);
        }
    } else{
        
        // condition:   order was left on pause and 24 hours have passed since scheduled order time
        // action:      resume the order, stop it, and mark it completed
        // reasoning:   can't check worked time because that doesn't get updated until you resume
        if ($currently_paused == 'y' && minutes_since($schedule) > 1440) { 
            resume_order($order);
            start_stop_order($order);
            mark_completed($order, $message);
        } else {
            $worked_time = payment($order)->worked_time;
            // condition:   order was started and provider has worked (not including paused time) more than ordered duration
            // action:      resume the order if necessary, and then stop it
            // reasoning:   provider won't get paid for more work than this anyways
            if ($status == 'st' && $worked_time > ($duration * 60)) {
                if ($currently_paused == 'y') {
                    resume_order($order);
                }
                start_stop_order($order);
            }
            // condition:   order is stopped and 24 hours have passed since scheduled order time
            // action:      mark order completed
            // reasoning:   this gives provider time to upload receipts
            if ($status == 'en' && minutes_since($schedule) > 1440) {
                mark_completed($order, $message);
            }
        }
        
    }
}
