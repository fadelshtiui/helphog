<?php
include 'common.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$stripe = new \Stripe\StripeClient(
  'sk_test_51H77jdJsNEOoWwBJR4lupAfmJ6ZLABBPCWvwiNqv99a9rr0mfhyNZ1L823ae56gIxJLUEZKDvXKepbCN1lIwPXp200KKA5Ni5p'
);

if (isset($_POST["ordernumber"]) && isset($_POST['session']) && isset($_POST['tzoffset']) && isset($_POST['role'])) {
    $order_number = trim($_POST["ordernumber"]);
    $session = trim($_POST['session']);
    $tzoffset = trim($_POST['tzoffset']);
    $role = trim($_POST['role']);
    
    if (validate_provider($order_number, $session)) {

        $db = establish_database();
        
        $service = "";
        $customer_email = "";
        $price = "";
        $address = "";
        $client_email = "";
        $duration = "";
        $wage = "";
        $accept_key = "";
        $schedule = "";
        $message = "";
        $people = "";
        $clicked = "";
        $city = "";
        $state = "";
        $zip = "";
        $secondary_providers = "";
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
            $accept_key = $row['accept_key'];
            $schedule = $row['schedule'];
            $message = $row['message'];
            $people = $row['people'];
            $clicked = $row['clicked'];
            $city = $row['city'];
            $state = $row['state'];
            $zip = $row['zip'];
            $secondary_providers = $row['secondary_providers'];
        }
        
        if ($wage == "hour" ){
            $duration = $duration . " hour(s)";
        }
        if ($wage == "per" ){
            $duration = "No time limit";
        }
        
        $cancelling_provider = "";
        $stmnt = $db->prepare("SELECT email FROM login WHERE session = ?;");
        $stmnt->execute(array($session));
        foreach($stmnt->fetchAll() as $row) {
            $cancelling_provider = $row['email'];
        }
        
        $cancels = 0;
        $stmnt = $db->prepare("SELECT cancels FROM login WHERE email = ?;");
        $stmnt->execute(array($cancelling_provider));
        foreach($stmnt->fetchAll() as $row) {
            $cancels = $row['cancels'];
        }
        
        $cancels = $cancels + 1;
        if ($cancels > 1){
            banning($cancels, $cancelling_provider);
        }
        
        $sql = "UPDATE login SET cancels = ? WHERE email = ?;";
        $stmt = $db->prepare($sql);
        $params = array($cancels, $cancelling_provider);
        $stmt->execute($params);
        
        if (minutes_until($schedule) < 15){
        
            $payment_info = payment($order_number);
        
            $stripe->paymentIntents->cancel(
              trim($payment_info->intent),
              []
            );
            
            $name = "";
            $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
            $stmnt->execute(array($customer_email));
            foreach($stmnt->fetchAll() as $row) {
                $name = $row['firstname'];
            }
            
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
            $mail->addAddress($customer_email, 'To');
            
            $mail->Subject = "HelpHog - Task Cancelled";
            $mail->Body    = get_cancel_email($name, $service);
            $mail->IsHTML(true);
            $mail->send();
            
            echo 'task cancelled';
        
        } else {
            
            if ($wage == "hour") {
                $providerWage = "$" . $price . "/hr";
            } else{
                $providerWage = "$" . $price;
            }
            
            $re_notify_list = explode(',', $clicked);
            $contact = array();
            foreach ($re_notify_list as $email) {
                
                if ($email != "" && $email != $cancelling_provider) {
                    $stmnt = $db->prepare("SELECT phone, timezone FROM login WHERE email = ?;");
                    $stmnt->execute(array($email));
                    foreach($stmnt->fetchAll() as $row) {
                        $entry = new stdClass();
                        $entry->email = $email;
                        $entry->phone = $row['phone'];
                        $entry->tz = $row['timezone'];
                        array_push($contact, $entry);
                    }
                }
                
            }
            
            if (strtotime($schedule) - 3600000 < $t){
                $departureTime = $t;
            } else {
                $departureTime = $departureTime - 3600000;  
            }
            
            foreach ($contact as $provider) {
                
                if (address_works_for_provider($address, $provider->email, $departureTime)->within) {
                    send_new_task_email($provider->email, $providerWage, $order_number, $duration, $accept_key, $provider->tz, $schedule, $tzoffset, $address, $city, $state, $zip, $service, $message);
                    send_new_task_text($provider->phone, $provider->email, $order_number, $providerWage, $message, $duration, $accept_key, $provider->tz, $people, $schedule, $tzoffset, $address, $city, $state, $zip, $service);
                }
            }
            
            if ($role == "primary") {
                $sql = "UPDATE orders SET client_email = ?, status = ? WHERE order_number = ?;";
                $stmt = $db->prepare($sql);
                $params = array("", "pe", $order_number);
                $stmt->execute($params);
            } else {
                
                echo 'cancelling as secondary...';
                
                $secondary_providers_array = explode(",", $secondary_providers);
            
                $array_without_cancelling_provider = array_diff($secondary_providers_array, array($cancelling_provider));
                
                $updated_string = "";
                if (count($array_without_cancelling_provider) > 0) {
                    $updated_string = $array_without_cancelling_provider[1];
                }
                for ($i = 1; $i < count($array_without_cancelling_provider); $i++) {
                    $updated_string.= ",";
                    $updated_string.= $array_without_cancelling_provider[$i];
                }
                if ($updated_string == ",") {
                    $updated_string = "";
                }
                
                $sql = "UPDATE orders SET secondary_providers = ?, status = ? WHERE order_number = ?";
                $stmt = $db->prepare($sql);
                $params = array($updated_string, 'pe', $order_number);
                $stmt->execute($params);
            
            }
            
            
            
            echo 'reactivated task';
            
        }
    } else {
        echo 'access denied';
    }
} else {
    echo 'missing parameters';
}

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
    
?>