<?php

include 'common.php';

use Twilio\Rest\Client;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$response = new stdClass();
$response->ordernumber = "";
$response->error = "none";

session_start();

if (isset($_SESSION["intent"]) && isset($_SESSION["customeremail"]) && isset($_SESSION["service"]) && isset($_SESSION["schedule"]) && isset($_SESSION["address"]) && isset($_SESSION["zip"]) && isset($_SESSION["city"]) && isset($_SESSION["state"]) && isset($_SESSION["message"]) && isset($_SESSION["phone"]) && isset($_SESSION["order"]) && isset($_SESSION["people"]) && isset($_SESSION["duration"]) && isset($_SESSION["ordernumber"])) {
    
    $db = establish_database();
    
    $message = $_SESSION["message"];
    $response->message = $message;
    
    $order = $_SESSION["order"];
    $duration = $_SESSION["duration"];
    
    $day = $_SESSION["day"];
    
    $people;
    $orig_duration = intval($_SESSION["duration"]);
    
    
    $result = $db->query("SELECT order_cookie FROM orders;");
    $found = false;
    foreach ($result as $row) {
        if ($order === $row['order_cookie']) {
            $found = true;
        }
    }
    
    if (!$found) {
        $intent;
        $order_number = $_SESSION["ordernumber"];
        $intent = $_SESSION['intent'];
        $customer_email = $_SESSION["customeremail"];
        $service = $_SESSION["service"];
        $schedule = $_SESSION["schedule"];
        $street_address = $_SESSION["address"];
        $zip = $_SESSION["zip"];
        $city = ucfirst($_SESSION["city"]);
        $state = $_SESSION["state"];
        $customer_phone = $_SESSION["phone"];
        $people = $_SESSION["people"];
        
        $address = $street_address . " " . $city . " " . $state . " " . $zip;
        
        $response->address = $street_address;
        $response->city = $city;
        $response->state = $state;
        $response->zip = $zip;
        $response->schedule = $schedule;
        $response->service = $service;
        
        $response->duration = $_SESSION['duration'];
        
        $prorated = "";
        $price = "";
        $wage = "";
        $name = "";
        $stmnt = $db->prepare("SELECT * FROM services WHERE service = ?;");
        $stmnt->execute(array($service));
        foreach($stmnt->fetchAll() as $row) {
            $wage = $row["wage"];
            $price = $row["cost"];
            $prorated = $row["prorated"];
        }
        
        $response->cost = $price;
        $response->wage = $wage;
        $found = false;
        $result = $db->query("SELECT email FROM login;");
        foreach ($result as $row) {
            if ($customer_email === $row['email']) {
                $found = true;
            }
        }
        
        $durationTemp = $duration;
        
        if ($wage == "hour" ){
            $duration = $duration . " hour(s)";
        }
        if ($wage == "per" ){
            $duration = "No time limit";
        }
        
        
        if ($found) {
            $no_address = false;
            $no_city = false;
            $no_state = false;
            
            $name = "";
            $stmnt = $db->prepare("SELECT * FROM login WHERE email = ?;");
            $stmnt->execute(array($customer_email));
            foreach($stmnt->fetchAll() as $row) {
                if (strlen($row["address"]) < 1) {
                    $no_address = true;
                }
                if (strlen($row["city"]) < 1) {
                    $no_city = true;
                }
                if (strlen($row["state"]) < 1) {
                    $no_state = true;
                }
            }
            
            if ($no_address && $no_city && $no_state) {
                $sql = "UPDATE login SET zip = ? WHERE email = ?";
                $stmt = $db->prepare($sql);
                $params = array($_SESSION['zip'], $customer_email);
                $stmt->execute($params);
            }
            if ($no_address) {
                $sql = "UPDATE login SET address = ? WHERE email = ?";
                $stmt = $db->prepare($sql);
                $params = array($street_address, $customer_email);
                $stmt->execute($params);
            }
            if ($no_city) {
                $sql = "UPDATE login SET city = ? WHERE email = ?";
                $stmt = $db->prepare($sql);
                $params = array($city, $customer_email);
                $stmt->execute($params);
            }
            if ($no_state) {
                $sql = "UPDATE login SET state = ? WHERE email = ?";
                $stmt = $db->prepare($sql);
                $params = array($state, $customer_email);
                $stmt->execute($params);
            }
        }
        
        $accept_key = '' . bin2hex(openssl_random_pseudo_bytes(12));
        $cancel_key = '' . bin2hex(openssl_random_pseudo_bytes(128));
        
        $sql = "INSERT INTO orders (order_number, customer_email, timestamp, schedule, address, service, message, cost, customer_phone, client_email, wage, order_cookie, duration, people, intent, status, prorated, accept_key, cancel_key, timezone) VALUES (:order_number, :customer_email, :timestamp, :schedule, :address, :service, :message, :price, :customer_phone, :client_email, :wage, :order_cookie, :duration, :people, :intent, :status, :prorated, :accept_key, :cancel_key, :timezone);";
        
        $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone($_SESSION['tzoffset']));
        $utc->setTimezone(new DateTimeZone('UTC'));
        
        $time = date('m-d-y H:i:s');
        $stmt = $db->prepare($sql);
        $params = array("order_number" => $order_number, "customer_email" => $customer_email, "timestamp" => $time, "schedule" => $utc->format('Y-m-d H:i:s'), "address" => $address, "service" => $service, "message" => $message, "price" => $price, "customer_phone" => $customer_phone, "client_email" => "", "wage" => $wage, "order_cookie" => $order, "duration" => $durationTemp, "people" => $people, "intent" => $intent, "status" => "pe", "prorated" => $prorated, "accept_key" => $accept_key, "cancel_key" => $cancel_key, "timezone" => $_SESSION['tzoffset']);
        $stmt->execute($params);
        
        $orig_price = $price;
        
        if ($wage == "hour"){
            $providerWage = "$" . $price . "/hr";
        }else{
            $providerWage = "$" . $price;
        }
        
        if ($wage == "hour") {
            $price = $price * $people;
            $price = $price * $durationTemp;
        } else {
            $price *= $people;
        }
        
        $peopleText = "people";
        if ($people == 1) {
            $peopleText = "person";
        }
        
        $hourText = "hour";
        if ($durationTemp > 1) {
            $hourText = "hours";
        }
        
        // $durationText = "Until Completion";
        $durationText = $durationTemp . " " . $hourText;
        
        if ($wage == "hour") {
            $subtotal = $people . " " . $peopleText . " at $" . $orig_price . "/hr (" . $durationText . ")";
        } else {
            $subtotal = $people . " " . $peopleText . " for $" . $price;
        }
        
        $price = "$" . $price;

        $name = "";
        $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
        $stmnt->execute(array($customer_email));
        foreach($stmnt->fetchAll() as $row) {
            $name = $row['firstname'];
        }
        
        if ($name == ""){
            $notfound = true;
            
            $name = "";
            $stmnt = $db->prepare("SELECT phone FROM guests WHERE phone = ?;");
            $stmnt->execute(array($customer_phone));
            foreach($stmnt->fetchAll() as $row) {
                if ($phone = $row["phone"]){
                    $notfound = false;
                }
            }
            if ($notfound){
                $sql = "INSERT INTO guests (phone) VALUES (?);";
                $stmt = $db->prepare($sql);
                $params = array($customer_phone, $customer_email);
                $stmt->execute($params);
            }
        }
        
        $mail = new PHPMailer;
    
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'html';
        $mail->Host = "smtp.gmail.com";
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';
        $mail->SMTPAuth = true;
        $mail->Username = "admin@helphog.com";
        $mail->Password = "Monkeybanana";
        $mail->setFrom('no-reply@helphog.com', 'HelpHog');
        $mail->addAddress($customer_email, 'To');
        
        $mail->Subject = "HelpHog - Confirmation Email";
        $mail->Body    = get_confirmation_email($order_number, $price, $service, $name, $schedule, $_SESSION["message"], $address, $people, $subtotal, $cancel_key);
        $mail->IsHTML(true); 
        
        $mail->send();
        $mail->ClearAllRecipients();
        
        $response->firstname = $name;
        $response->schedule = $schedule;
        $response->people = $people;
        
        $available_providers = $_SESSION['available_providers'];

        foreach ($available_providers as $provider) {
            
            send_new_task_email($provider->email, $providerWage, $order_number, $duration, $accept_key, $provider->tz, $_SESSION['schedule'], $_SESSION['tzoffset'], $_SESSION["address"], $_SESSION['city'], $_SESSION['state'], $_SESSION['zip'], $_SESSION['service'], $_SESSION['message']);
            
            send_new_task_text($provider->phone, $provider->email, $order_number, $providerWage, $_SESSION["message"], $duration, $accept_key, $provider->tz, $people, $_SESSION['schedule'], $_SESSION['tzoffset'], $_SESSION['address'], $_SESSION['city'], $_SESSION['state'], $_SESSION['zip'], $_SESSION['service']);
        }
        
        $response->ordernumber = $order_number;
        
    } else {
        
        $response->error = "tried to refresh confirmation page";
    }
    
} else {
    
    $response->error = "missing session parameters";
    
}

echo json_encode($response);

?>