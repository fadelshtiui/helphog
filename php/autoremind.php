<?php
include 'common.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

$db = establish_database();

$result = $db->query("SELECT * FROM {$DB_PREFIX}orders");

foreach($result as $row) {

    $order_number = $row["order_number"];
    $service = $row["service"];
    $email = $row["customer_email"];
    $schedule = $row['schedule'];
    $provider_email = $row["client_email"];

    $minutes_until = minutes_until($row["schedule"]);
    if ($minutes_until < 45.0 && $row["reminded"] == "n" && $row["status"] == "cl") {

        $phone = "";
        $tz = "";
        $alerts = "";
        $stmnt = $db->prepare("SELECT phone, timezone, alerts FROM {$DB_PREFIX}login WHERE email = ?;");
        $stmnt->execute(array($row["client_email"]));
        foreach($stmnt->fetchAll() as $row) {
            $phone = $row['phone'];
            $tz = $row['timezone'];
            $alerts = $row['alerts'];
        }

        $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
        $local_date->setTimezone(new DateTimeZone($tz));

        $time = $local_date->format('g:i a');

        if ($alerts == 'sms' || 'both'){
            send_text($phone, 'Reminder: You have ' . $service  . ' in ' . round($minutes_until) . ' minutes.');
        }
        if ($alerts == 'email' || 'both'){
            send_email($provider_email, "no-reply@helphog.com", "Reminder",  get_partners_email('You have ' . $service  . ' in ' . round($minutes_until) . ' minutes.'));
        }
        ios_provider_notification($provider_email, "Reminder", 'You have ' . $service  . ' in ' . round($minutes_until) . ' minutes.', $order_number, '#1ecd97');

        $sql = "UPDATE {$DB_PREFIX}orders SET reminded = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array('y', $order_number);
        $stmt->execute($params);
    }
}
