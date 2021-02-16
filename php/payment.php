<?php

include 'common.php';

$db = establish_database();
$price = 0;

//Need to throw error if service not found
function calculateOrderAmount(array $items): int {
    $entry = $items[0];
    
    $service = $entry->service;
    $duration = $entry->duration;
    $people = $entry->people;
    
    global $db;
    $wage = "";
    $cost = "";
    $stmnt = $db->prepare("SELECT cost, wage FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach($stmnt->fetchAll() as $row) {
        $cost = $row["cost"];
        $wage = $row["wage"];
    }
       
    $price;   
    if ($wage == "per") {
        $price = $people * $cost;
    } else {
        $price = $people * $cost * $duration;
    }
    
    return $price * 100;
}

function createOrder($paymentIntent, $order_info, array $items){
    global $db;
    $entry = $items[0];
    
    $service = $entry->service;
    
    $remote = "";
    $stmnt = $db->prepare("SELECT remote FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach($stmnt->fetchAll() as $row) {
        $remote = $row["remote"];
    }

    $order_number;
    $unique = false;
    while (!$unique) {
        $order_number = time() % 100000;
        if ($order_number >= 10000) {
            $unique = true;
            $result = $db->query("SELECT order_number FROM orders");
            foreach ($result as $row) {
                if ($order_number == $row["order_number"]) {
                    $unique = false;
                }
            }
        }
    }
    
    if (strlen($order_info->message) > 1000){
        $order_info->message = substr($order_info->message, 0, 1000);
    }
    
    session_start();
    $_SESSION['order'] = $order_info->order;
    $_SESSION['service'] = $order_info->service;
    $_SESSION['customeremail'] = $order_info->customeremail;
    $_SESSION['schedule'] = $order_info->schedule;
    $_SESSION['order'] = $order_info->order;
    $_SESSION['phone'] = $order_info->phone;
    $_SESSION['message'] = $order_info->message;
    if ($remote == "y"){
        $_SESSION['zip'] = "";
        $_SESSION['address'] = "Remote (online)";
        $_SESSION['city'] = "";
        $_SESSION['state'] = "";
    }else{
        $_SESSION['zip'] = $order_info->zip;
        $_SESSION['address'] = $order_info->address;
        $_SESSION['city'] = $order_info->city;
        $_SESSION['state'] = $order_info->state;
    }
    $_SESSION['people'] = $order_info->people;
    $_SESSION['duration'] = $order_info->duration;
    $_SESSION['day'] = $order_info->day;
    $_SESSION['order'] = $order_info->order;
    $_SESSION['tzoffset'] = $order_info->tzoffset;
    $_SESSION['cancel_buffer'] = $order_info->cancelbuffer;
    
    $_SESSION['ordernumber'] = $order_number;
    $_SESSION['intent'] = $paymentIntent->id;
    
}

function checkAcc(array $creds): bool {
    $db = establish_database();
    $entry = $creds[0];
    $email = $entry->email;
    $phone = $entry->phone;
    
    $stmnt = $db->prepare("SELECT banned FROM guests WHERE phone = ?;");
    $stmnt->execute(array($phone));
    foreach($stmnt->fetchAll() as $row) {
        if ($row["banned"] == "y"){
            return false;
        }
    }
    
    $stmnt = $db->prepare("SELECT banned FROM login WHERE email = ?;");
    $stmnt->execute(array($email));
    foreach($stmnt->fetchAll() as $row) {
        if ($row["banned"] == "y"){
            return false;
        }
    }

    return true;
}


header('Content-Type: application/json');
try {
  $cred_check = true;
  // retrieve JSON from POST body
  $json_str = file_get_contents('php://input');
  $json_obj = json_decode($json_str);
  $cred_check = checkAcc($json_obj->creds);
  
  $order_info = $json_obj->checkout;
  
  if($cred_check){
      $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => calculateOrderAmount($json_obj->items),
        'currency' => 'usd',
        'capture_method' => 'manual',
      ]);
      createOrder($paymentIntent, $order_info, $json_obj->items);
      $output = [
        'clientSecret' => $paymentIntent->client_secret,
      ];
      echo json_encode($output);
  }else{
      echo json_encode(['error' => "fail"]);
  }
  
} catch (Error $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}


