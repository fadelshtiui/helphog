<?php

include 'common.php';

$stripe = new \Stripe\StripeClient(
  'sk_test_51H77jdJsNEOoWwBJR4lupAfmJ6ZLABBPCWvwiNqv99a9rr0mfhyNZ1L823ae56gIxJLUEZKDvXKepbCN1lIwPXp200KKA5Ni5p'
);


$db = establish_database();

$result = $db->query("SELECT * FROM orders;");
foreach ($result as $row) {
    $order_number = $row["order_number"];
    $service = $row["service"];
    $people = $row["people"];
    $secondary_providers = $row["secondary_providers"];
    $schedule = $row["schedule"];
    $timezone = $row["timezone"];
    
    $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
    $utc->setTimezone(new DateTimeZone($timezone));
    $schedule = $utc->format('F j, Y, g:i a');
    
    if (minutes_since($row['schedule']) >= 1440 && $row["status"] == "mc") {
        
        $payment_info = payment($order_number);
        
        if ($payment_info->customerPayment < 0.50){
            sendNoChargeEmail($service, $order_number, $schedule);
            error_log($payment_info->intent);
            
            $stripe->paymentIntents->cancel(
              trim($payment_info->intent),
              []
            );
        } else {
            $intent = \Stripe\PaymentIntent::retrieve(trim($payment_info->intent));
            $intent->capture(['amount_to_capture' => ceil($payment_info->customerPayment * 100)]);
            
            $stripe_acc = "";
            $stmnt = $db->prepare("SELECT stripe_acc FROM login WHERE email = ?;");
            $stmnt->execute(array($row["client_email"]));
            foreach($stmnt->fetchAll() as $row) {
                $stripe_acc = $row['stripe_acc'];
            }
            
            $transfer = \Stripe\Transfer::create([
              "amount" => ceil($payment_info->providerPayout * 100),
              "currency" => "usd",
              "destination" => $stripe_acc,
              "description" => $service . " (" . $order_number . ")",
              "transfer_group" => '{' . $order_number . '}',
            ]);
            
            if (intval($people) > 1){
                $providers = explode("," , $secondary_providers);
                foreach ($providers as $provider){
                    
                    $secondary_stripe_acc = "";
                    $stmnt = $db->prepare("SELECT stripe_acc FROM login WHERE email = ?;");
                    $stmnt->execute(array($provider));
                    foreach($stmnt->fetchAll() as $row) {
                        
                        $secondary_stripe_acc = $row["stripe_acc"];
                        $transfer = \Stripe\Transfer::create([
                          "amount" => ceil($payment_info->providerPayout * 100),
                          "currency" => "usd",
                          "destination" => $secondary_stripe_acc,
                          "description" => $service . " (" . $order_number . ")",
                          "transfer_group" => '{' . $order_number . '}',
                        ]); 
                    }
                }
            }
            
            $sql = "UPDATE orders SET status = ? WHERE order_number = ?";
            $stmt = $db->prepare($sql);
            $params = array('pd', $order_number);
            $stmt->execute($params);
        }
    }
}


