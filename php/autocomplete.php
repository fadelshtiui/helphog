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
    $schedule = $utc->format('F j, Y, g:i a');

    $local_start = new DateTime(date('Y-m-d H:i:s', strtotime($start)), new DateTimeZone('UTC'));

    $message = $service . ' (' . $order . ') on ' . $schedule . ' has been marked completed by our system. If the support provided by the provider was inadequate you can dispute the transaction by texting back DISPUTE to this number.';


    if ($wage == "per"){
        if ($currently_paused == 'y' && minutes_since($row['schedule']) > 1435){
            resume_order($order);
            start_stop_order($order);
            mark_completed($order, $message);
        }else if ($status == 'en' && minutes_since($row['schedule']) > 1435) {
            mark_completed($order, $message);
        }else if ($status == 'st' && minutes_since($row['schedule']) > 1435) {
            start_stop_order($order);
            mark_completed($order, $message);
        }

    }else{
        $now = new DateTime(gmdate('Y-m-d H:i:s'));
        $diff = strtotime($now->format('Y-m-d H:i:s')) - strtotime($local_start->format('Y-m-d H:i:s'));
        $workedTime = payment($order)->worked_time;
        if ($currently_paused == 'y' && minutes_since($schedule) >= 1435){
            resume_order($order);
            start_stop_order($order);
            mark_completed($order, $message);
        }else if ($status == 'en' && ($workedTime/3600 >= $duration || minutes_since($schedule) >= 1435)){
            mark_completed($order, $message);
        }else if ($status == 'st'  && ($workedTime/3600 >= $duration || minutes_since($schedule) >= 1435)){
            start_stop_order($order);
            mark_completed($order, $message);
        }
    }
}

?>
