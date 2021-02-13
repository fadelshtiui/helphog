<?php
include 'common.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$stripe = new \Stripe\StripeClient(
  'sk_test_51H77jdJsNEOoWwBJR4lupAfmJ6ZLABBPCWvwiNqv99a9rr0mfhyNZ1L823ae56gIxJLUEZKDvXKepbCN1lIwPXp200KKA5Ni5p'
);


$db = establish_database();
$sql = "SELECT * FROM orders;";
$result = $db->query($sql);

foreach ($result as $row) {
    
    $order_number = $row["order_number"];
    $service = $row["service"];
    $customer_email = $row["customer_email"];
    $customer_phone = $row["customer_phone"];
    $price = $row["cost"];
    $duration = $row["duration"];
    $wage = $row["wage"];
    $address = $row["address"];
    $message = $row["message"];
    $schedule = $row["schedule"];
    $people = $row["people"];
    $client_email = $row["client_email"];
    $secondary_providers = $row["secondary_providers"];
    $status = $row["status"];
    $timezone = $row["timezone"];
    
    if ($status == "pe") {
        
        $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
        $utc->setTimezone(new DateTimeZone($timezone));
        $schedule = $utc->format('F j, Y, g:i a');
    
        $needsToBeCancelled = false;
        if (minutes_until($row["schedule"]) < 30) {
            if ($client_email == "") {
                $needsToBeCancelled = true;
            } else if ($people > 1) {
                if ($secondary_providers == "") {
                    $needsToBeCancelled = true;
                }
                if (count(explode(',', $secondary_providers)) + 1 < $people) {
                    $needsToBeCancelled = true;
                }
            }
        }
        
        if ($needsToBeCancelled) {
            $payment_info = payment($order_number);
        
            error_log($payment_info->intent);
            
            $stripe->paymentIntents->cancel(
              trim($payment_info->intent),
              []
            );
            
            if ($client_email != "") {
                $primary_work_phone = "";
                $stmnt = $db->prepare("SELECT work_phone FROM login WHERE email = ?;");
                $stmnt->execute(array($client_email));
                foreach($stmnt->fetchAll() as $row) {
                    $primary_work_phone = $row['work_phone'];
                }
                
                sendPrimary($client_email, $service, $order_number, $schedule);
                sendTextProvider($service, $order_number, $primary_work_phone, $schedule);
                
            }
            
            
            foreach(explode(',', $secondary_providers) as $email){
                if ($email != "") {
                    $work_phone = "";
                    $stmnt = $db->prepare("SELECT work_phone FROM login WHERE email = ?;");
                    $stmnt->execute(array($email));
                    foreach($stmnt->fetchAll() as $row) {
                        $work_phone = $row['work_phone'];
                    }
                    sendTextProvider($service, $order_number, $work_phone, $schedule);
                    sendPrimary($email, $service, $order_number, $schedule);
                }
            }
            
            $name = "";
            $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
            $stmnt->execute(array($customer_email));
            foreach($stmnt->fetchAll() as $row) {
                $name = $row['firstname'];
            }

            $sql = "UPDATE orders SET status = ? WHERE order_number = ?;";
            $stmt = $db->prepare($sql);
            $params = array("ac", $order_number);
            $stmt->execute($params);
            
            sendTextCustomer($service, $order_number, $customer_phone, $schedule);
            
            
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
            $mail->IsHTML(true); 
            
            $mail->Subject = "HelpHog - Task Cancelled";
            $mail->Body    = noProviderFound($service, $order_number, $schedule);
            $mail->send();
            
            
        }
        
    }
        
}

function sendPrimary($customer_email, $service, $order_number){
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
    $mail->IsHTML(true); 
    
    $mail->Subject = "HelpHog - Task Cancelled";
    $mail->Body    = noPartnersFound($service, $order_number, $schedule);
    $mail->send();
}
    


function sendTextCustomer($service, $order, $phonenumber, $schedule){
    $sid = 'ACc66538a897dd4c177a17f4e9439854b5';
    $token = '18a458337ffdfd10617571e495314311';
    $client = new Client($sid, $token);
    $client->messages->create('+1' . $phonenumber, array('from' => '+12532593451', 'body' => 'Your order for ' . $service . ' (' . $order . ') on ' . $schedule . ' was canceled because the service provider was not located in time. We appologize for the inconvenience.'));
    
}

function sendTextProvider($service, $order, $phonenumber, $schedule){
    $sid = 'ACc66538a897dd4c177a17f4e9439854b5';
    $token = '18a458337ffdfd10617571e495314311';
    $client = new Client($sid, $token);
    $client->messages->create('+1' . $phonenumber, array('from' => '+12532593451', 'body' => 'Your task for ' . $service . ' (' . $order . ') on ' . $schedule . ' was canceled because one or more of the secondary providers were not located for this task'));
    
}

?>