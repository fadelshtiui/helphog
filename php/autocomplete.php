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

    if ($wage == "per") {
        // condition:   order has been started and it's been more than the ordered time
        // action:      pause order if necessary, and then stop the order
        // reasoning:   worked time doesn't matter since it's a flat rate service
        if ($status == 'st' && minutes_since($start) > ($duration * 60)) {
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
    } else {

        if ($status == 'st') {

            $worked_time = 0;

            if ($currently_paused == 'n') {

                $worked_time = minutes_since($start) - ($paused_time / 60.0);
            } else { // currently_paused == 'y'

                $timestamp_of_last_pause = "";
                $timestamp_of_last_resume = "";
                $stmnt = $db->prepare("SELECT pause, resume FROM {$DB_PREFIX}orders WHERE order_number = ?;");
                $stmnt->execute(array($order));
                foreach ($stmnt->fetchAll() as $row) {
                    $timestamp_of_last_pause = $row['pause'];
                    $timestamp_of_last_resume = $row['resume'];
                }

                $ts1 = strtotime($timestamp_of_last_pause);
                $ts2 = strtotime($timestamp_of_last_resume);
                $seconds_diff = $ts2 - $ts1;

                $new_paused_time_if_resumed_right_now = $seconds_diff + $paused_time;

                $worked_time_if_resumed_right_now = minutes_since($start) - $new_paused_time_if_resumed_right_now;

                $worked_time = $worked_time_if_resumed_right_now;
            }

            if ($worked_time > ($duration * 60)) {
                if ($currently_paused == 'y') {
                    resume_order($order);
                }
                start_stop_order($order);
            }
        } else if ($status == 'en' && minutes_since($schedule) > 1440) {

            mark_completed($order, $message);
        }
    }
}
