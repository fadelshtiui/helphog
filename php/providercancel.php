<?php
include 'common.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$stripe = new \Stripe\StripeClient(
  'sk_test_51H77jdJsNEOoWwBJR4lupAfmJ6ZLABBPCWvwiNqv99a9rr0mfhyNZ1L823ae56gIxJLUEZKDvXKepbCN1lIwPXp200KKA5Ni5p'
);

function banning($cancels, $client_email) {
    $db = establish_database();
    
    $name = "";
    $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
    $stmnt->execute(array($client_email));
    foreach($stmnt->fetchAll() as $row) {
        $name = $row['firstname'];
    }
    
    if ($cancels == '2'){
        $note = "Our system has noticed several order cancellations on your behalf. We ask you not to claim orders if you are unable to fulfill them. Further cancellations will result in the suspension of your provider account.";
    }
    if ($cancels == '3'){
        $sql = "UPDATE login SET type = ?, banned = 'y' WHERE email = ?;";
        $stmt = $db->prepare($sql);
        $params = array("Personal", $client_email);
        $stmt->execute($params);
        $note = "Due to excessive number of canceled orders on your behalf, provider privileges have been temporarily removed from your account. If you have any questions please contact us.";
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
    $mail->addAddress($client_email, 'To');
    
    $mail->Subject = "Provider Account Notice";
    $mail->Body    = get_notice_email($name, $note);
    $mail->IsHTML(true);
    
    $mail->send();
    
    $mail->ClearAllRecipients();
}

/*
function send_email($client, $price, $ordernumber, $duration) {
    $db = establish_database();
    
    $name = "";
    $alerts= "";
    $stmnt = $db->prepare("SELECT firstname, alerts, timezone FROM login WHERE email = ?;");
    $stmnt->execute(array($client));
    foreach($stmnt->fetchAll() as $row) {
        $name = $row['firstname'];
        $alerts = $row['alerts'];
        $tz = $row['timezone'];
    }
    
    if ($alerts == "email" || $alerts == "both"){
    
        $service = "";
        $schedule = "";
        $address = "";
        $message = "";
        $stmnt = $db->prepare("SELECT * FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($ordernumber));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row["service"];
            $schedule = $row["schedule"];
            $address = $row["address"];
            $message = $row["message"];
            $acceptkey = $row["accept_key"];
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
        $mail->addAddress($client, 'To');
        
        
        
        $date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone($tz));
        
        $mail->Subject = "HelpHog - New Task Available";
        $mail->Body    = get_claim_email($service, $date->format("F j, Y, g:i a"), $address, $client, $ordernumber, $price, $message, $name, $duration, $acceptkey);
        $mail->IsHTML(true); 
        
        $mail->send();
        $mail->ClearAllRecipients();
    }
}

    
function send_text($phonenumber, $email, $ordernumber, $price, $address, $message, $duration) {
    
    $db = establish_database();
    
    $alerts="";
    $stmnt = $db->prepare("SELECT alerts, timezone FROM login WHERE email = ?;");
    $stmnt->execute(array($email));
    foreach($stmnt->fetchAll() as $row) {
        $alerts = $row['alerts'];
        $tz = $row['timezone'];
    }
    
    if ($alerts == "sms" || $alerts == "both") {
    
        $service = "";
        $schedule = "";
        $stmnt = $db->prepare("SELECT service, schedule FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($ordernumber));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $schedule = $row['schedule'];
        }
        $date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone($tz));
        $split = explode(" ", $address);
        $zip = $split[count($split)-1];
        $city = '';
        $sid = 'ACc66538a897dd4c177a17f4e9439854b5';
        $token = '18a458337ffdfd10617571e495314311';
        $client = new Client($sid, $token);
        
    $client->messages->create('+1' . $phonenumber, array('from' => '+12532593451', 'body' => 'There\'s a new service request in your area!
    
Service: ' . $service . '
Date: ' . $date->format('F j Y') . '
Max duration: ' . $duration . '
Location: ' . $city . $zip . ' 
Pay: ' . $price . '

Message: ' . $message . '

Tap on the following link to obtain this job:
->
https://helphog.com/php/accept.php?email=' . $email . '&ordernumber=' . $ordernumber . '<-'));
}
}
*/

if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    $order_number = trim($_POST["ordernumber"]);
    $session = trim($_POST['session']);
    
    if (validate_provider($order_number, $session)) {

        $db = establish_database();
        
        
        $service = "";
        $customer_email = "";
        $price = "";
        $address = "";
        $duration = "";
        $wage = "";
        $stmnt = $db->prepare("SELECT * FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order_number));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $customer_email = $row['customer_email'];
            $price = $row['cost'];
            $address = $row['address'];
            $client_email = $row['client_email'];
            $duration = $row['duration'];
            $wage = $row['wage'];
        }
        
        if ($wage == "hour" ){
            $duration = $duration . " hour(s)";
        }
        if ($wage == "per" ){
            $duration = "No time limit";
        }
        
        $cancels = 0;
        $stmnt = $db->prepare("SELECT cancels FROM login WHERE email = ?;");
        $stmnt->execute(array($client_email));
        foreach($stmnt->fetchAll() as $row) {
            $cancels = $row['cancels'];
        }
        
        $cancels = $cancels + 1;
        if ($cancels > 1){
            banning($cancels, $client_email);
        }
        
        $sql = "UPDATE login SET cancels = ? WHERE email = ?;";
        $stmt = $db->prepare($sql);
        $params = array($cancels, $client_email);
        $stmt->execute($params);
        
        $service = "";
        $schedule = "";
        $address = "";
        $customer = "";
        $message = "";
        $wage = "";
        $stmnt = $db->prepare("SELECT * FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order_number));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row["service"];
            $schedule = $row["schedule"];
            $address = $row["address"];
            $message = $row["message"];
            $wage = $row['wage'];
            $secondary = $row['secondary_providers'];
            $customer = $row['customer_email'];
        }
        
        $name = "";
        $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
        $stmnt->execute(array($customer_email));
        foreach($stmnt->fetchAll() as $row) {
            $name = $row['firstname'];
        }
        

        // if (minutes_until($schedule) < 15){
        
        $payment_info = payment($order_number);
        
        error_log($payment_info->intent);
        
        $stripe->paymentIntents->cancel(
          trim($payment_info->intent),
          []
        );
        
        $sql = "UPDATE orders SET status = ? WHERE order_number = ?;";
        $stmt = $db->prepare($sql);
        $params = array("pc", $order_number);
        $stmt->execute($params);
        
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
        $mail->setFrom('admin@helphog.com', 'HelpHog');
        $mail->addAddress($customer, 'To');
        
        $mail->Subject = "HelpHog - Task Cancelled";
        $mail->Body    = get_cancel_email($name, $service);
        $mail->IsHTML(true);
        $mail->send();
            
        /*
        
        } else {
            
            if ($wage == "hour") {
                $providerWage = "$" . $price . "/hr";
            } else{
                $providerWage = "$" . $price;
            }
            
            $all_phones = array();
            $all_emails = array();
    
            $stmnt = $db->prepare("SELECT email, phone FROM login WHERE services LIKE ? AND type='Business';");
            $stmnt->execute(array('%' . $service . '%'));
            foreach($stmnt->fetchAll() as $row) {
                array_push($all_emails, $row['email']);
                array_push($all_phones, $row['phone']);
            }
            
            $name = "";
            $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
            $stmnt->execute(array($customer_email));
            foreach($stmnt->fetchAll() as $row) {
                $name = $row['firstname'];
            }
            
            $sql = "UPDATE orders SET client_email = ?, secondary_providers = ?, status = ? WHERE order_number = ?;";
            $stmt = $db->prepare($sql);
            $params = array("", "", "pc", $order_number);
            $stmt->execute($params);
            
            if (strtotime($schedule) - 3600000 < $t){
                $departureTime = $t;
            }else{
                $departureTime = $departureTime - 3600000;  
            }
            
            foreach ($all_emails as $email) {
                
                if (address_works_for_provider($address, $email, $departureTime)->within) {
                    $availability = TRUE;
                    send_email($email, $providerWage, $order_number, $duration, $name);
                }
            }
            
            foreach ($all_phones as $phonenumber) {
                
                $current_email = "";
                $stmnt = $db->prepare("SELECT email FROM login WHERE phone = ?;");
                $stmnt->execute(array($phonenumber));
                foreach($stmnt->fetchAll() as $row) {
                    $current_email = $row['email'];
                }
                
                if (address_works_for_provider($address, $current_email, $departureTime)->within) {
                    send_text($phonenumber, $current_email, $order_number, $providerWage, $address, $message, $duration);
                }
                
            }
        }
        */
    }
}
    
?>