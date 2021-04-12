<?php
include 'common.php';
include 'keys.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

$stripe = new \Stripe\StripeClient(
    $STRIPE_API_KEY
);

$db = establish_database();

if (isset($_GET["ordernumber"]) && isset($_GET['secret']) || isset($_POST['ordernumber']) && isset($_POST['session']) ) {

    $order = "";
    if (isset($_GET['ordernumber'])) {
        $order = trim($_GET["ordernumber"]);
    } else {
        $order = trim($_POST["ordernumber"]);
    }

    $validated = false;
    $is_post_request = false;

    if (isset($_GET['ordernumber']) && isset($_GET['secret'])) {

        $stmnt = $db->prepare("SELECT cancel_key FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            if ($row["cancel_key"] === trim($_GET['secret'])) {
                $validated = true;
            }
        }

    }

    if (isset($_POST['ordernumber']) && isset($_POST['session'])) {

        $validated = validate_customer($order, trim($_POST['session']));
        $is_post_request = true;

    }

    if ($validated) {

        $customer_timezone = "";
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
            $service = $row["service"];
            $schedule = $row["schedule"];
            $status = $row["status"];
            $providerEmail = $row["client_email"];
            $secondary_providers = $row["secondary_providers"];
            $customerEmail = $row["customer_email"];
            $people = $row["people"];
            $customer_timezone = $row['timezone'];
        }

        $customerName = "";
        $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
        $stmnt->execute(array($customerEmail));
        foreach($stmnt->fetchAll() as $row) {
            $customerName = ' ' . $row['firstname'];
        }

        if ($status == "cc" || $status == "pc" || $status == "ac") {

            if ($is_post_request) {
                echo 'error?message=This+order+has+already+been+canceled';
            } else {
                echo '<script>window.location.href = "https://helphog.com/error?message=This+order+has+already+been+canceled";</script>';
            }


        } else if ($status == "st") {

            if ($is_post_request) {
                echo 'ordererror';
            } else {
                echo '<script>window.location.href = "https://helphog.com/error?message=Sorry,+you+cannot+cancel+an+order+that+is+currently+is+in+progress";</script>';
            }

        } else {

            $tz = "";
            $providerName = "";
            $phone = "";
            $stmnt = $db->prepare("SELECT firstname, timezone, phone FROM login WHERE email = ?;");
            $stmnt->execute(array($providerEmail));
            foreach($stmnt->fetchAll() as $row) {
                $providerName = ' ' . $row['firstname'];
                $tz = $row['timezone'];
                $phone = $row['phone'];
            }
            
            $customer_local_date;

            $amount = 0;
            $payment_info = payment($order);
            
            $customer_local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
            $customer_local_date->setTimezone(new DateTimeZone($customer_timezone));

            $providerMessage = 'We are informing you that the order for ' . $service . ' (' . $order . ') has been canceled by the customer. We apologize for the inconvience.';
            $customerMessage = '';
            if (minutes_until($schedule) < 1440) { // within 24 hours
                $amount = 15;
                $intent = \Stripe\PaymentIntent::retrieve(trim($payment_info->intent));
                $intent->capture(['amount_to_capture' => $amount * 100]);
                if ($providerEmail != '' && $secondary_providers == '') {
                    $providerMessage = 'We are informing you that the order for ' . $service . ' (' . $order . ') has been canceled by the customer. Since the customer canceled within 24 hours of the scheduled date, you will be receiving a $10 compensation. We apologize for the inconvience.';

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

                }
                $customerMessage = 'Your service request for ' . $service . ' (' . $order .') on ' . $customer_local_date->format("F j, Y, g:i a") . ' has been canceled. The refund will appear in your bank statement within 5-10 business days. Since you canceled your task within 24 hours of the scheduled start time, you are billed a one-time fee of $' . $amount . '.';
            } else {
                $customerMessage = 'Your service request for ' . $service . ' (' . $order . ') on ' . $customer_local_date->format("F j, Y, g:i a") . ' has been canceled. The full refund will appear in your bank statement within 5-10 business days.';
                $stripe->paymentIntents->cancel(
                  trim($payment_info->intent),
                  []
                );
            }

           
            if ($providerEmail != ""){

                send_email($providerEmail, "no-reply@helphog.com", $service . " Canceled", customer_cancel($providerMessage, $providerName));

                sendTextProvider($service, $order, $phone, $local_date->format("F j, Y, g:i a"));
            }
            if ($secondary_providers != ""){
                $providers = explode("," , $secondary_providers);
                foreach ($providers as $provider){

                    $phonenumber = "";
                    $name = "";
                    $stmnt = $db->prepare("SELECT firstname, phone FROM login WHERE email = ?;");
                    $stmnt->execute(array($provider));
                    foreach($stmnt->fetchAll() as $row) {
                        $phonenumber = $row['phone'];
                        $name = ' ' . $row['firstname'];
                    }
                    send_email($provider, "no-reply@helphog.com", $service . " Canceled", customer_cancel($providerMessage, $name));
                    sendTextProvider($service, $order, $phonenumber, $local_date->format("F j, Y, g:i a"));
                }
            }
            
            send_email($customerEmail, "no-reply@helphog.com", $service . " Canceled", customer_cancel($customerMessage, $customerName));

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

function sendTextProvider($service, $order, $phonenumber, $schedule){
    $sid = 'ACc66538a897dd4c177a17f4e9439854b5';
    $token = '18a458337ffdfd10617571e495314311';
    $client = new Client($sid, $token);
    $client->messages->create('+1' . $phonenumber, array('from' => '+12532593451', 'body' => 'Your task for ' . $service . ' (' . $order . ') on ' . $schedule . ' was canceled by the customer.'));
}
