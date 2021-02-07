<?php

include 'common.php';

$response = new stdClass();
$response->error = "";

if (isset($_POST["ordernumber"]) && isset($_POST['secret'])) {
    $order_number = $_POST["ordernumber"];
    $cancel_key = $_POST['secret'];
    
    $db = establish_database();
    
    $service = "";
    $schedule = "";
    $stmnt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND cancel_key = ?;");
    $stmnt->execute(array($order_number, $cancel_key));
    foreach($stmnt->fetchAll() as $row) {
        $service = $row["service"];
        $schedule = $row["schedule"];
        $email = $row['client_email'];
    }
    
    $utc_time = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));

    if (minutes_until($schedule) < 1440){
        $response->within = "true";
    } else{
        $response->within = "false";
    }
    
    $response->order = $order_number;
    $response->service = $service;
    $response->schedule = $utc_time->format("F j, Y, g:i a");
    
} else {
    $response->error = "missing parameters";
}

header('Content-type: application/json');
print json_encode($response);

?>