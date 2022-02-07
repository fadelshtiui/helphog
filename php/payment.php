<?php

include 'common.php';

require __DIR__ . '/taxjar/vendor/autoload.php';

$stripe = new \Stripe\StripeClient($STRIPE_API_KEY);

$db = establish_database();


header('Content-Type: application/json');
try {
    $cred_check = true;
    // retrieve JSON from POST body
    $json_str = file_get_contents('php://input');
    $json_obj = json_decode($json_str);
    $banned = is_banned($json_obj->creds);
    $service = $json_obj->items[0]->service;
    $prorated = is_prorated($service);
    $tax_code = get_tax_code($service);
    error_log("tax code: " . $tax_code);
    $order_info = $json_obj->checkout;
    $order_amount = calculate_order_amount($json_obj->items);
    $tax_info = calculate_tax($order_amount / 100, $tax_code, $order_info);
    $tax_to_collect = $tax_info[0];
    $sales_tax_percent = $tax_info[1];
    $session = $json_obj->creds[0]->session;

    if (!$banned) {
        
        $logged_in = check_session($session);
    	
    	$customer_id = "";
    	$payment_method = "";
    	$card_brand = "";
    	$card_last4 = "";
    	if ($logged_in) {
    	    $saved_payment_info = retrieve_stripe_info($order_info, $session);
            $customer_id = $saved_payment_info[0];
            $payment_method = $saved_payment_info[1];
            $card_brand = $saved_payment_info[2];
            $card_last4 = $saved_payment_info[3];
    	}
    	
    	if ($customer_id != '' && $payment_method != '') { // returning stripe customer
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $order_amount + $tax_to_collect,
                'currency' => 'usd',
                'capture_method' => 'manual',
                'customer' => $customer_id,
                'payment_method'=> $payment_method,
            ]);
        } else if ($customer_id != '' && $payment_method == '') { // edge case where they don't have a payment method
            
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $order_amount + $tax_to_collect,
                'currency' => 'usd',
                'capture_method' => 'manual',
                'customer' => $customer_id,
                'setup_future_usage' => 'on_session',
            ]);
        } else { // guest
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $order_amount + $tax_to_collect,
                'currency' => 'usd',
                'capture_method' => 'manual',
            ]);
        }
        
        if ($sales_tax_percent == 0) {
            $tax_rate = "";
        } else {
            $tax_rate = $sales_tax_percent * 100 . "%";
        }
        $order_number = create_order($payment_intent, $order_info, $json_obj->items, $tax_rate, $session);
        
        $stripe->paymentIntents->update(
             $payment_intent->id,
            ['description' => $order_number]
        );
        $output = [
            'clientSecret' => $payment_intent->client_secret,
            'payment_method'=> $payment_method,
            'taxRate' => $tax_rate,
            'prorated' => $prorated,
            'card_brand'=> $card_brand,
            'last4' => $card_last4,
        ];
        echo json_encode($output);
    } else {
        echo json_encode(['error' => "fail"]);
    }
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}


// checks for existing customerID
// creates customer id if no payment information is stored on database
function retrieve_stripe_info($order_info, $session): array
{
    
    include 'constants.php';
    $db = establish_database();
    
    $customer_id = "";
    $payment_method = "";
    $card_brand = "";
    $card_last4 = "";
    
    $user = get_user_info($session);
        
    if ($user["customer_id"] == "") {
        $customer = \Stripe\Customer::create([
            'email' => $user["email"],
        ]);
        $customer_id = $customer->id;

        $session_name = $user['session_name'];
        $sql = "UPDATE {$DB_PREFIX}login SET customer_id = ? WHERE {$session_name} = ?";
        $stmt = $db->prepare($sql);
        $params = array($customer_id, $user['match_session']);
        $stmt->execute($params);
        
    } else {
        $customer_id = $user["customer_id"];

        $cards = \Stripe\PaymentMethod::all([
            "customer" => $customer_id, "type" => "card"
        ]);
        
        $latest_created = 0;
        
        foreach ($cards as $card) {
            if ($card->created > $latest_created){
                $card_brand = $card->card->brand;
                $card_last4 = $card->card->last4;
                $payment_method = $card->id;
                $latest_created = $card->created;
            }
        }
        
    }
    
    return array($customer_id, $payment_method, $card_brand , $card_last4);
}
    
// returns an array [amount to collect, sales tax percentage]
// return [0, 0] if no tax
function calculate_tax($price, $tax_code, $order_info): array
{

    if ($tax_code == '') {
        return array(0, 0);
    }

    $client = TaxJar\Client::withApiKey('8052c7c79f531012b785b96371c225cb');
    $order_taxes = $client->taxForOrder([
        'to_zip' => $order_info->zip,
        'to_state' => $order_info->state,
        'to_city' => $order_info->city,
        'to_street' => $order_info->address,
        'amount' => $price,
        'shipping' => 0,
        'line_items' => [
            [
                'id' => '1',
                'quantity' => 1,
                'product_tax_code' => $tax_code,
                'unit_price' => $price,
                'discount' => 0
            ]
        ]
    ]);
    
    return array(($order_taxes->amount_to_collect) * 100, $order_taxes->rate);

}

// TODO: throw error if service not found
// returns total order amount in pennies
function calculate_order_amount(array $items): int
{
    $entry = $items[0];

    $service = $entry->service;
    $duration = $entry->duration;
    $people = $entry->people;

    global $db;
    $wage = "";
    $cost = "";
    $providers = "";
    $stmnt = $db->prepare("SELECT cost, wage, providers FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach ($stmnt->fetchAll() as $row) {
        $cost = $row["cost"];
        $wage = $row["wage"];
        $providers = $row["providers"];
    }

    if ($providers != 0) {
        $people = $providers;
    }

    $price = 0;
    if ($wage == "per") {
        $price = $people * $cost;
    } else {
        $price = $people * $cost * $duration;
    }
    return $price * 100;
}

// returns 'y' if service is prorated and 'n' otherwise
function is_prorated($service): string {
    global $db;
    $prorated = "";
    $stmnt = $db->prepare("SELECT prorated FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach ($stmnt->fetchAll() as $row) {
        $prorated = $row["prorated"];
    }
    return $prorated;
}

// returns tax code
function get_tax_code($service): string {
    global $db;

    $tax_code = "";
    $stmnt = $db->prepare("SELECT taxcode FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach ($stmnt->fetchAll() as $row) {
        $tax_code = $row["taxcode"];
    }
    return $tax_code;
}

function create_order($payment_intent, $order_info, array $items, $tax_rate, $session): string
{
    include 'constants.php';

    global $db;
    $entry = $items[0];

    $service = $entry->service;

    $remote = "";
    $providers = "";
    $stmnt = $db->prepare("SELECT remote, providers FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach ($stmnt->fetchAll() as $row) {
        $remote = $row["remote"];
        $providers = $row["providers"];
    }

    $utc = new DateTime(date('Y-m-d H:i:s', strtotime($order_info->schedule)), new DateTimeZone($order_info->tzoffset));
    $utc->setTimezone(new DateTimeZone('UTC'));

    $now = new DateTime(gmdate('Y-m-d H:i:s'));

    $default_cancel_buffer = round((strtotime($utc->format('Y-m-d H:i:s')) - strtotime($now->format('Y-m-d H:i:s'))) / 60 / 2);

    $order_number;
    $unique = false;
    while (!$unique) {
        $order_number = (time() + mt_rand()) % 100000;
        if ($order_number >= 10000) {
            $unique = true;
            $result = $db->query("SELECT order_number FROM {$DB_PREFIX}orders");
            foreach ($result as $row) {
                if ($order_number == $row["order_number"]) {
                    $unique = false;
                    break;
                }
            }
        }
    }

    if (strlen($order_info->message) > 1000) {
        $order_info->message = substr($order_info->message, 0, 1000);
    }
    session_start();
    
    if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']),"apple")) {
        $cookie_lifetime = 365 * 24 * 60 * 60; // A year in seconds
        setcookie("ses_id", session_id(), time() + $cookie_lifetime);
    }
    
    $_SESSION['order'] = $order_info->order;
    $_SESSION['service'] = $order_info->service;
    
    $user = get_user_info($session);
    if ($order_info->customeremail != '') {
        $_SESSION['customeremail'] = $order_info->customeremail;
    } else {
        $_SESSION['customeremail'] = $user["email"];
    }
    
    $_SESSION['schedule'] = $order_info->schedule;
    $_SESSION['order'] = $order_info->order;
    
    if ($order_info->phone != '') {
        $_SESSION['phone'] = $order_info->phone;
    } else {
        $_SESSION['phone'] = $user["phone"];
    }
    
    $_SESSION['message'] = $order_info->message;
    $_SESSION['taxrate'] = $tax_rate;
    if ($remote == "y") {
        $_SESSION['zip'] = "";
        $_SESSION['address'] = "Remote (online)";
        $_SESSION['city'] = "";
        $_SESSION['state'] = "";
    } else {
        $_SESSION['zip'] = $order_info->zip;
        $_SESSION['address'] = $order_info->address;
        $_SESSION['city'] = $order_info->city;
        $_SESSION['state'] = $order_info->state;
    }
    if ($providers == 0) {
        $_SESSION['people'] = $order_info->people;
    } else {
        $_SESSION['people'] = $providers;
    }
    $_SESSION['duration'] = $order_info->duration;
    $_SESSION['day'] = $order_info->day;
    $_SESSION['order'] = $order_info->order;
    $_SESSION['tzoffset'] = $order_info->tzoffset;
    if ($order_info->cancelbuffer == 0) {
        $_SESSION['cancel_buffer'] = $default_cancel_buffer;
    } else {
        $_SESSION['cancel_buffer'] = $order_info->cancelbuffer;
    }
    $_SESSION['ordernumber'] = $order_number;
    $_SESSION['intent'] = $payment_intent->id;
    $_SESSION['providerId'] = $order_info->providerId;
    
    return $order_number;

}

// returns false if user is banned, true otherwise
function is_banned(array $creds): bool
{
    include 'constants.php';

    $db = establish_database();
    $entry = $creds[0];
    $email = $entry->email;
    $phone = $entry->phone;
    $session = $entry->session;
    
    if ($session != '') {
        $user = get_user_info($session);
        if ($user["banned"] == "y") {
            return true;
        }
    } else { // guest
        $stmnt = $db->prepare("SELECT banned FROM {$DB_PREFIX}guests WHERE phone = ?;");
        $stmnt->execute(array($phone));
        foreach ($stmnt->fetchAll() as $row) {
            if ($row["banned"] == "y") {
                return true;
            }
        }
    }
    return false;

}
