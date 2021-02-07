<?php

include 'common.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set the content-type to XML to send back TwiML from the PHP Helper Library
header("content-type: text/xml");
$response = new MessagingResponse();

$body = $_REQUEST['Body'];
$number = $_REQUEST['From'];
$formatted_phone = substr($number, 2, strlen($number) - 2);

$db = establish_database();

$email = "";
$type = "Personal";
$stmnt = $db->prepare("SELECT email, type FROM login WHERE phone = ?;");
$stmnt->execute(array($formatted_phone));
foreach($stmnt->fetchAll() as $row) {
    $email = $row['customer_email'];
    $type = $row["type"];
}

if ($email == "") {
    $result = $db->query("SELECT * FROM guests;");
    foreach ($result as $row) {
        if ($row['phone'] == $formatted_phone) {
            $stmnt = $db->prepare("SELECT customer_email FROM orders WHERE customer_phone = ?;");
            $stmnt->execute(array($formatted_phone));
            foreach($stmnt->fetchAll() as $row) {
                $email = $row['customer_email'];
            }
        }
    }
}

if ($email == "") {
    return;
}

$order_number = "none";

if (trim(strtolower($body)) == 'dispute') {
    
    $now = gmdate('Y-m-d H:i:s');
    
    $orders_array = array();
    $stmnt = $db->prepare("SELECT * FROM orders WHERE customer_phone = ?;");
    $stmnt->execute(array($formatted_phone));
    foreach($stmnt->fetchAll() as $row) {
        if (minutes_since($row['end']) <= 1440) {
            array_push($orders_array, $row);
        }
    }
    
    if (count($orders_array) == 1) {
        
        $order_info = payment($orders_array[0]["order_number"]);
        
        if ($orders_array[0]["status"] == "re") {
            
            $response->message("Error: this order has been refunded.");
            
        } else if ($orders_array[0]["status"] != "mc") {
            
            $response->message("Error: this order is not completed.");
            
        } else {
            
            dispute_order($orders_array[0]["order_number"]);
            
            $response->message("The provider has been notified of your concern and should contact you shortly.");
        }
        
    } else if (count($orders_array) > 1) {
        
        $message = "You have multiple orders within the last 24 hours. Please also include the order number of the order you would like to dispute:\n";
        $message.= "Ex. \"dispute 12345\"\n\n";
        for ($i = 0;$i < count($orders_array);$i++) {
            $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($orders_array[$i]["schedule"])), new DateTimeZone('UTC'));
            $local_date->setTimezone(new DateTimeZone($orders_array[$i]["timezone"]));
            $message.= $orders_array[$i]["order_number"] . " " . $orders_array[$i]["service"] . " on " . $local_date->format("F j, Y, g:i a") . "\n";
        }
        $response->message($message);
        
    } else {
        
        $response->message("Error: you have no orders to dispute.");
        
    }
    
} else if (strpos(strtolower($body), 'dispute') !== false) {

    $pieces = explode(' ', trim(strtolower($body)));
    if (strtolower(trim($pieces[0])) != 'dispute' || strlen($pieces[1]) != 5 || count($pieces) != 2) {
        
        $response->message("Error: incorrect usage. Format is: \"dispute order-number\"");
        
    } else {
            
        $order_info = payment(trim($pieces[1]));
        $empty = new \stdClass();
        if ($order_info == $empty) {
            
            $response->message("Error: invalid order number.");
            
        } else {
            
            if (validate_customer_phone($pieces[1], $formatted_phone)) {
            
                $status = "";
                $within_24_hours = false;
                $stmnt = $db->prepare("SELECT end, status FROM orders WHERE order_number = ?;");
                $stmnt->execute(array($pieces[1]));
                foreach($stmnt->fetchAll() as $row) {
                    if (minutes_since($row['end']) <= 1440) {
                        $within_24_hours = true;
                        $status = $row['status'];
                    }
                }
                
                if ($within_24_hours) {
                    
                    if ($status == 're') {
                        
                        $response->message("Error: this order has been refunded.");
                        
                    } else if ($status != 'mc') {
                        
                        $response->message("Error: this order is not completed.");
                        
                    } else {
                        
                        dispute_order($pieces[1]);
                        
                        $response->message("The provider has been notified of your concern and should contact you shortly.");
                    }
                    
                } else {
                    
                    $response->message("Error: you must dispute tasks within 24 hours of completion.");
                    
                }
                
            }
            
        }
        
    }
    
} else if (trim(strtolower($body)) == "completed" && $type == "Business") {
    
    $service = "";
    $customer_email = "";
    $customer_phone = "";
    $count = 0;
    $stmnt = $db->prepare("SELECT service, customer_email, customer_phone, order_number FROM orders WHERE client_email = ? AND status = 'en';");
    $stmnt->execute(array($email));
    foreach($stmnt->fetchAll() as $row) {
        $service = $row['service'];
        $customer_email = $row['customer_email'];
        $customer_phone = $row['customer_phone'];
        $order_number = $row['order_number'];
        $count++;
    }
    
    if ($count == 0) {
        
        $response->message("Error: you have no orders that can be marked completed.");
        
    } else if ($count > 1) {
        
        $message = "You have more than one order that has recently ended. Please also include the order number of the order you would like to mark as completed:\n";
        $message.= "Ex. \"completed 12345\"\n\n";
        $response->message($message);
        
    } else {
        
        mark_completed($order_number, '');
        
        $response->message("Task completed.");
    }
            
    
            
} else if (strpos(strtolower($body), 'completed') !== false && $type == "Business") {
    
    $pieces = explode(' ', trim(strtolower($body)));
    
    if ($pieces[0] != 'completed' || strlen($pieces[1]) != 5 || count($pieces) != 2) {
        
        $response->message("Error: incorrect usage. Format is: \"completed order-number\"");
        
    } else {
        
        $order_info = payment(trim($pieces[1]));
        $empty = new \stdClass();
        
        if ($order_info == $empty) {
            
            $response->message("Error: invalid order number.");
            
        } else {
            
            $status = "";
            $stmnt = $db->prepare("SELECT status FROM orders WHERE order_number = ?;");
            $stmnt->execute(array($pieces[1]));
            foreach($stmnt->fetchAll() as $row) {
                $status = $row['status'];
            }
            if ($status == "mc") {
                
                $response->message("Error: this task has already been marked completed.");
                
            } else if ($status == "en") {
        
                if (validate_provider_email($pieces[1], $email)) {
                    
                    mark_completed($pieces[1], '');
                    $response->message("Task completed.");
                    
                } else {
                    
                    $response->message("Error: invalid order number.");
                    
                }
                
            } else {
                    
                $response->message("Error: this task is still in progress. Text DONE to end your work-hours.");
                
            }
            
        }
    }

} else if ((trim(strtolower($body)) == 'done' || trim(strtolower($body)) == 'resume' || trim(strtolower($body)) == 'pause' || trim(strtolower($body)) == 'begin') && $type == "Business") {
    
    $min_distance = 1000000;
    $order_number = "none";
    
    $timezone = "";
    $stmnt = $db->prepare("SELECT timezone FROM login WHERE email = ?;");
    $stmnt->execute(array($email));
    foreach($stmnt->fetchAll() as $row) {
        $timezone = $row["timezone"];
    }

    $status = "";
    $currently_paused = "";
    $stmnt = $db->prepare("SELECT * FROM orders WHERE client_email = ?;");
    $stmnt->execute(array($email));
    foreach($stmnt->fetchAll() as $row) {
        
        $service = $row["service"];
        $curr_order_number = $row["order_number"];

        if (minutes_until($row["schedule"]) < $min_distance && $row["end"] == "2019-02-18 01:53:14") {
            
            $min_distance = minutes_until($row["schedule"]);
            $order_number = $curr_order_number;
            $currently_paused = $row["currently_paused"];
            $status = $row['status'];
            
        }
    }
    
    if ($order_number == "none") {
        
        $response->message('Error: you have no active orders today');
        
    } else {
        
        if (trim(strtolower($body)) == 'begin') {
            
            if ($status == "cl") {
                
                start_stop_order($order_number);
                $response->message('Task started.');
                
            } else {
                
                $response->message('Error: your task has already been started');
                
            }
            
        } else if (trim(strtolower($body)) == 'pause') {
            
            if ($status == 'st' && $currently_paused == 'n') {
                
                pause_order($order_number);
                $response->message("Task Paused");
                
            } else {
                
                $response->message("Error: task has not been started yet.");
                
            }
            
        } else if (trim(strtolower($body)) == 'resume') {
            
            if ($currently_paused == 'y') {
                
                resume_order($order_number);
                $response->message("Task Resumed");
                
            } else {
                
                $response->message("Error: this task is not currently paused.");
                
            }
            
        } else { // trim(strtolower($body)) == 'done'
            
            if ($status == "st") {
                
                start_stop_order($order_number);
                $response->message("Task Ended");
                
            } else {
                
                $response->message("Error: this task has not yet been started.");
                
            }
            
        }
        
    }
    
} else {
    if ($type == "Personal") {
        $response->message("Command not understood. Text DISPUTE to dispute an order.");
    } else {
        $response->message("Command not understood. Text BEGIN to clock in, text PAUSE to pause your work-hours, text RESUME to resume your work-hours, text DONE to end your work-hours, and text COMPLETED to mark your order completed. If you are a customer, text DISPUTE to dispute an order.");
    }
}

print $response;