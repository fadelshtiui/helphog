<?php

include 'common.php';

$response = new stdClass();
$response->error = "";

if (isset($_POST["ordernumber"]) && (isset($_POST['secret']) || isset($_POST['session']))) {
    $order_number = trim($_POST["ordernumber"]);
    $cancel_key = trim($_POST['secret']);
    $session = trim($_POST['session']);
    
    $db = establish_database();
    
    $sql = "";
    $params = array();
    if (isset($_POST['session']) && validate_customer($order_number, $session)) {
        $sql = "SELECT * FROM orders WHERE order_number = ?;";
        $params = array($order_number);
    } else {
        $sql = "SELECT * FROM orders WHERE order_number = ? AND cancel_key = ?;";
        $params = array($order_number, $cancel_key);
    }
    
    $service = "";
    $schedule = "";
    $stmnt = $db->prepare($sql);
    $stmnt->execute($params);
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
