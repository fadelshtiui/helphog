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

if (isset($_GET["ordernumber"]) && isset($_GET['secret']) || isset($_POST['ordernumber']) && isset($_POST['session']) ) {
    
    $order = "";
    if (isset($_GET['ordernumber'])) {
        $order = $_GET["ordernumber"];
    } else {
        $order = $_POST["ordernumber"];
    }
    
    $validated = false;
    $is_post_request = false;
    
    if (isset($_GET['ordernumber']) && isset($_GET['secret'])) {
        
        $stmnt = $db->prepare("SELECT cancel_key FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            if ($row["cancel_key"] === $_GET['secret']) {
                $validated = true;
            }
        }
    
    }
    
    if (isset($_POST['ordernumber']) && isset($_POST['session'])) {
        
        $validated = validate_customer($order, $_POST['session']);
        $is_post_request = true;
        
    }
    
    if ($validated) {
    
        $service = "";
        $schedule = "";
        $status = ""; 
        $providerEmail = "";
        $secondary_provider = "";
        $customerEmail = "";
        $people = "";
        $stmnt = $db->prepare("SELECT * FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            error_log(print_r($row, true));
            $service = $row["service"];
            $schedule = $row["schedule"];
            $status = $row["status"]; 
            $providerEmail = $row["client_email"];
            $secondary_providers = $row["secondary_providers"];
            $customerEmail = $row["customer_email"];
            $people = $row["people"];
        }
        
        $customerName = "";
        $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
        $stmnt->execute(array($customerEmail));
        foreach($stmnt->fetchAll() as $row) {
            $customerName = $row['firstname'];
        }
        
        error_log('provider email: ' . $providerEmail);
        
        $tz = "";
        $providerName = "";
        $stmnt = $db->prepare("SELECT firstname, timezone FROM login WHERE email = ?;");
        $stmnt->execute(array($providerEmail));
        foreach($stmnt->fetchAll() as $row) {
            $providerName = $row['firstname'];
            $tz = $row['timezone'];
        }
        
        error_log("provider timezone: " . $tz);
        
        if ($status == "cc" || $status == "pc" || $status == "ac") {
            
            if ($is_post_request) {
                echo 'alreadycanceled';
            } else {
                echo '<script>window.location.href = "https://helphog.com/alreadycanceled";</script>';
            }
            
            
        } else if ($status == "st") { 
            
            if ($is_post_request) {
                echo 'ordererror';
            } else {
                echo '<script>window.location.href = "https://helphog.com/ordererror";</script>';
            }
        
        } else {
                
            $amount = 0;
            $payment_info = payment($order);
            if (minutes_until($schedule) < 1440) { // within 24 hours
                // $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
                // $local_date->setTimezone(new DateTimeZone($tz));
                $amount = 15;
                $intent = \Stripe\PaymentIntent::retrieve(trim($payment_info->intent));
                $intent->capture(['amount_to_capture' => $amount * 100]);
                if ($providerEmail != '' && $secondary_providers == '') {
                    $providerMessage = 'We are informing you that the order for ' . $service . ' (' . $order . ') has been canceled by the customer. Since the customer canceled within 24 hours of the scheduled date, you will be receiving a $10 compensation. We apologize for the inconvience.';
                    $customerMessage = 'Your service request for ' . $service . ' (' . $order .') has been canceled. The refund will appear in your bank statement within 5-10 business days. Since you canceled your task within 24 hours of the scheduled start time, you are billed a one-time fee of $' . $amount . '.';
                    
                    $stripe_acc = "";
                    $stmnt = $db->prepare("SELECT stripe_acc FROM login WHERE email = ?;");
                    $stmnt->execute(array($providerEmail));
                    foreach ($stmnt->fetchAll() as $row) {
                        $stripe_acc = $row["stripe_acc"];
                    }
                    
                    $transfer = \Stripe\Transfer::create([
                      "amount" => 1000,
                      "currency" => "usd",
                      "destination" => $stripe_acc,
                      "description" => $service . " (" . $order . ")",
                      "transfer_group" => '{' . $order . '}',
                    ]);
                    
                } else {
                    $providerMessage = 'We are informing you that the order for ' . $service . ' (' . $order . ') has been canceled by the customer. We apologize for the inconvience.';
                    $customerMessage = 'Your service request for ' . $service . ' (' . $order .') has been canceled. The refund will appear in your bank statement within 5-10 business days. Since you canceled your task within 24 hours of the scheduled start time, you are billed a one-time fee of $' . $amount . '.';
                }
            } else {
                $providerMessage = 'We are informing you that the order for ' . $service . ' (' . $order . ')  has been canceled by the customer. We apologize for the inconvience.';
                $customerMessage = 'Your service request for ' . $service . ' (' . $order .') has been canceled. The full refund will appear in your bank statement within 5-10 business days.';
                error_log($payment_info->intent);
                $stripe->paymentIntents->cancel(
                  trim($payment_info->intent),
                  []
                );
            }
            if ($providerEmail != ""){
                providerEmail($providerEmail, $providerMessage, $service, $providerName);
            }
            if ($secondary_providers != ""){
                $providers = explode("," , $secondary_providers);
                foreach ($providers as $provider){
                    providerEmail($providerEmail, $providerMessage, $service, $providerName);
                }
            }
            customerEmail($customerEmail, $customerMessage, $service, $customerName);
            
            $sql = "UPDATE orders SET status = 'cc' WHERE order_number = ?;";
            $stmt = $db->prepare($sql);
            $params = array($order);
            $stmt->execute($params);
            
            if ($is_post_request) {
                echo 'ordercanceled';
            } else {
                echo '<script>window.location.href = "https://helphog.com/ordercanceled";</script>';
            }
            
        }
        
    } else {
        echo 'invalid parameters';
    }
} else {
    echo 'missing parameters';
}

function providerEmail($providerEmail, $providerMessage, $service, $providerName){
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
    $mail->addAddress($providerEmail, 'To');
    
    $mail->Subject = $service . " Canceled";
    $mail->Body    = customer_cancel($providerMessage, $providerName);
    $mail->IsHTML(true);
    
    $mail->send();
    
    $mail->ClearAllRecipients();
}

function customerEmail($customerEmail, $customerMessage, $service, $customerName){
    
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
    $mail->addAddress($customerEmail, 'To');
    
    $mail->Subject = $service . " Canceled";
    $mail->Body    = customer_cancel($customerMessage, $customerName);
    $mail->IsHTML(true);
    
    $mail->send();
    
    $mail->ClearAllRecipients();
}


   
?>