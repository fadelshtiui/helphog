<?php

include 'common.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

// Set the content-type to XML to send back TwiML from the PHP Helper Library
header("content-type: text/xml");
$response = new MessagingResponse();

$body = $_REQUEST['Body'];
$number = $_REQUEST['From'];
$formatted_phone = substr($number, 2, strlen($number) - 2);

$db = establish_database();

$email = "";
$type = "Personal";
$alerts = "";
$stmnt = $db->prepare("SELECT email, type, alerts FROM {$DB_PREFIX}login WHERE phone = ?;");
$stmnt->execute(array($formatted_phone));
foreach ($stmnt->fetchAll() as $row) {
    $email = $row['email'];
    $type = $row['type'];
    $alerts = $row['alerts'];
}

if ($email == "") {
    $result = $db->query("SELECT * FROM {$DB_PREFIX}guests;");
    foreach ($result as $row) {
        if ($row['phone'] == $formatted_phone) {
            $stmnt = $db->prepare("SELECT customer_email FROM {$DB_PREFIX}orders WHERE customer_phone = ?;");
            $stmnt->execute(array($formatted_phone));
            foreach ($stmnt->fetchAll() as $row) {
                $email = $row['customer_email'];
            }
        }
    }
}

if ($email == "") {
    return;
}

error_log($alerts);

$alert = '';

//add to blacklist
if(strtolower($body) == "stop" ){

    $sql = "INSERT INTO {$DB_PREFIX}blacklisted (number) VALUES (?);";
    $stmt = $db->prepare($sql);
    $params = array($number);
    $stmt->execute($params);

    if ($alerts == "both"){
        $alert = "email";
    }else if ($alerts == "sms"){
        $alert = "none";
    }else{
        $alert = $alerts;
    }

    $sql = "UPDATE {$DB_PREFIX}login SET alerts = ? WHERE email = ?";
    $stmt = $db->prepare($sql);
    $params = array($alert, $email);
    $stmt->execute($params);

    return;
}

//remove from blacklist
if(strtolower($body) == "start" || strtolower($body) == "unstop"){

    $sql = "DELETE FROM {$DB_PREFIX}blacklisted WHERE number = ?";
    $stmt = $db->prepare($sql);
    $params = array($number);
    $stmt->execute($params);

    if ($alerts == "email"){
        $alert = "both";
    }else if ($alerts == "none"){
        $alert = "sms";
    }else{
        $alert = $alerts;
    }

    $sql = "UPDATE {$DB_PREFIX}login SET alerts = ? WHERE email = ?";
    $stmt = $db->prepare($sql);
    $params = array($alert, $email);
    $stmt->execute($params);

    return;
}



$pieces = explode(' ', trim(strtolower($body)));
$command = $pieces[0];
$order_number = $pieces[1];

if (strtolower(strlen($order_number) != 5 || count($pieces) != 2)) { // incorrect format

    $response->message("Error: incorrect usage. Format is: COMMAND ORDER-NUMBER (Ex. START 12345)");

} else {

    $status = '';
    $currently_paused = '';
    $end = '';
    $found = false;
    $stmnt = $db->prepare("SELECT * FROM {$DB_PREFIX}orders WHERE order_number = ?;");
    $stmnt->execute(array($order_number));
    foreach ($stmnt->fetchAll() as $row) {
        if ($order_number === $row['order_number']) {
            $found = true;
            $status = $row['status'];
            $currently_paused = $row['currently_paused'];
        }
    }

    if (!$found) { // order number not found

        $response->message("Error: order number not found.");

    } else {

        if ($command == 'dispute') { // customer

            if (!validate_customer_phone($order_number, $formatted_phone)) {
                $response->message("Error: you are not the customer of this order.");
            } else if (minutes_since($end) <= 1440) {
                $response->message("Error: you must dispute orders within 24 hours.");
            } else if ($status == "re") {
                $response->message("Error: this order has been refunded.");
            } else if ($status == "mc") {
                dispute_order($order_number);
                $response->message("The provider has been notified of your concern and should contact you shortly.");
            } else {
                $response->message("Error: this order is not completed.");
            }

        } else { // provider

            if (!validate_provider_email($order_number, $email)) { // not their order

                $response->message("Error: you are not the provider of this task.");

            } else {

                if ($command == "complete") {

                    if ($status == "mc") {
                        $response->message("Error: this task has already been marked completed.");
                    } else if ($status == "cl") {
                        $response->message("Error: this task has not been started.");
                    } else if ($status == "en") {
                        $success = mark_completed($order_number, '');
                        if ($success) {
                            $response->message("Task completed.");
                        } else {
                            $response->message("Error: since this order has been disputed several times, our staff will now reach out to both you and the customer to resolve any issues.");
                        }
                    } else {
                        $response->message("Error: this task is still in progress. Text STOP ORDER-NUMBER to end your work-hours.");
                    }


                } else if ($command == 'start') {

                    if ($status == "mc") {
                        $response->message('Error: this task has already been stopped.');
                    } else if ($status == "di") {
                        $response->message('Error: this task is currently disputed.');
                    } else if ($status == "cl") {
                        $start_result = start_stop_order($order_number);
                        if ($start_result == "") {
                            $response->message('Task started.');
                        } else {
                            $response->message($start_result);
                        }
                    } else {
                        $response->message('Error: your task has already been started.');
                    }

                } else if ($command == 'pause') {

                    if ($status == "mc") {
                        $response->message('Error: this task has already been stopped.');
                    } else if ($status == "di") {
                        $response->message('Error: this task is currently disputed.');
                    } else if ($status == 'st' && $currently_paused == 'n') {
                        pause_order($order_number);
                        $response->message("Task paused");
                    } else if ($currently_paused == 'y'){
                        $response->message("Error: task is already paused.");
                    } else {
                        $response->message("Error: task has not been started yet.");
                    }

                } else if ($command == 'resume') {

                    if ($status == "di") {
                        $response->message('Error: this task is currently disputed.');
                    } else if ($currently_paused == 'y') {
                        resume_order($order_number);
                        $response->message("Task resumed.");
                    } else if ($status == "en"){
                        $response->message("Error: you cannot resume an order that has already been stopped.");
                    } else if ($status == "mc"){
                        $response->message("Error: you cannot resume an order that has already been completed.");
                    } else {
                        $response->message("Error: this task is not currently paused.");
                    }


                } else if ($command == "stop") {

                    if ($status == "di") {
                        $response->message('Error: this task is currently disputed.');
                    } else if ($status == "st") {
                        start_stop_order($order_number);
                        $response->message("Task stopped.");
                    } else if ($status == 'mc'){
                        $response->message("Error: this task has already been stopped.");
                    } else {
                        $response->message("Error: this task has not been started yet.");
                    }

                } else {

                    if ($type == "Personal") {
                        $response->message("Command not understood. Text DISPUTE ORDER-NUMBER (Ex. DISPUTE 12345) to dispute an order.");
                    } else {
                        $response->message("Command not understood. The format of all commands is: COMMAND ORDER-NUMBER (Ex. START 12345). The list of all valid commands is: START to clock in, PAUSE to pause your work-hours, RESUME to resume your work-hours, STOP to end your work-hours, and COMPLETE to mark your order completed.");
                    }

                }
            }
        }
    }
}

print $response;
