<?php
include 'common.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db = establish_database();

$result = $db->query("SELECT * FROM orders");

foreach($result as $row) {

    $order_number = $row["order_number"];
    $service = $row["service"];
    $email = $row["customer_email"];
    $schedule = $row['schedule'];

    $minutes_until = minutes_until($row["schedule"]);
    if ($minutes_until < 45.0 && $row["reminded"] == "n" && $row["status"] == "cl") {
        
        $phone = "";
        $tz = "";
        $stmnt = $db->prepare("SELECT phone, timezone FROM login WHERE email = ?;");
        $stmnt->execute(array($row["client_email"]));
        foreach($stmnt->fetchAll() as $row) {
            $phone = $row['phone'];
            $tz = $row['timezone'];
        }
        
        $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
        $local_date->setTimezone(new DateTimeZone($tz));
        
        $time = $local_date->format('g:i a');
        
        $sid = 'ACc66538a897dd4c177a17f4e9439854b5';
        $token = '18a458337ffdfd10617571e495314311';
        $client = new Client($sid, $token);
        $client->messages->create('+1' . $phone, array('from' => '+12532593451', 'body' => 'Reminder: You have ' . $service  . ' in ' . round($minutes_until) . ' minute(s).'));
        
        $sql = "UPDATE orders SET reminded = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array('y', $order_number);
        $stmt->execute($params);
    }
}
