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
    $cred_check = checkAcc($json_obj->creds);
    $prorated = checkProrated($json_obj->items);
    $taxCode = taxCode($json_obj->items);
    $order_info = $json_obj->checkout;
    $order_amount = calculateOrderAmount($json_obj->items);
    $taxParameters = calculateTax($order_amount / 100, $taxCode, $order_info);
    $customerId = customerId($order_info);

    if ($cred_check) {

        if ($customerId[0] != '' && $customerId[1] != ''){
            $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $order_amount + $taxParameters[0],
            'currency' => 'usd',
            'capture_method' => 'manual',
            'customer' => $customerId[0],
            'payment_method'=> $customerId[1],

        ]);


        }else if($customerId[0] != '' && $customerId[1] == ''){
            $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $order_amount + $taxParameters[0],
            'currency' => 'usd',
            'capture_method' => 'manual',
            'customer' => $customerId[0],
            'setup_future_usage' => 'on_session',

        ]);

        }else{
            $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $order_amount + $taxParameters[0],
            'currency' => 'usd',
            'capture_method' => 'manual',

        ]);

        }

        if ($taxParameters[1] == 0) {
            $taxRate = "";
        } else {
            $taxRate = $taxParameters[1] * 100 . "%";
        }
        $order_number = createOrder($paymentIntent, $order_info, $json_obj->items, $taxRate);

        $stripe->paymentIntents->update(
             $paymentIntent->id,
            ['description' => $order_number]
        );
        $output = [
            'clientSecret' => $paymentIntent->client_secret,
            'payment_method'=> $customerId[1],
            'taxRate' => $taxRate,
            'prorated' => $prorated,
            'card_brand'=> $customerId[2],
            'last4' => $customerId[3],
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
function customerId($order_info): array
{

    include 'constants.php';
    $db = establish_database();

    $customer_id = "";
    $payment_method = "";
    $card_brand = "";
    $card_last4 = "";
    $latest_created = 0;


    $customer_email = $order_info->customeremail;
    $createAcc = true;
    $acc_exists = false;

    $result = $db->query("SELECT email FROM {$DB_PREFIX}login;");
	foreach ($result as $row) {
		if ($customer_email === $row['email']) {
			$acc_exists = true;
		}
	}

    if($acc_exists){
        $stmnt = $db->prepare("SELECT customer_id FROM {$DB_PREFIX}login WHERE email = ?;");
        $stmnt->execute(array($customer_email));
        foreach ($stmnt->fetchAll() as $row) {

            if ($row["customer_id"] == "") {
                $customer = \Stripe\Customer::create([
                    'email' => $customer_email,
                    ]);
                $customer_id = $customer->id;

                $sql2 = "UPDATE {$DB_PREFIX}login SET customer_id = ? WHERE email = ?";
				$stmt = $db->prepare($sql2);
				$params = array($customer_id, $customer_email);
				$stmt->execute($params);

            }else{
                $customer_id = $row["customer_id"];

                $cards = \Stripe\PaymentMethod::all([
                  "customer" => $customer_id, "type" => "card"
                ]);

                foreach ($cards as $card) {
                    if ($card->created > $latest_created){
                        $card_brand = $card->card->brand;
                        $card_last4 = $card->card->last4;
                        $payment_method = $card->id;
                    }
                    $latest_created = $card->created;
                }

            }
        }
    }
    return array($customer_id, $payment_method, $card_brand , $card_last4 );
}

// returns an array [amount to collect, sales tax percentage]
// return [0, 0] if no tax
function calculateTax($price, $taxCode, $order_info): array
{

    if ($taxCode == '') {
        return array(0, 0);
    }

    $client = TaxJar\Client::withApiKey('e4332888e3463438895749896684a8e9');
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
                'product_tax_code' => $taxCode,
                'unit_price' => $price,
                'discount' => 0
            ]
        ]
    ]);

    $taxParameters = array(($order_taxes->amount_to_collect) * 100, $order_taxes->rate);

    return $taxParameters;
}

//Need to throw error if service not found
function calculateOrderAmount(array $items): int
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

function checkProrated(array $items): string
{
    global $db;
    $entry = $items[0];

    $service = $entry->service;

    $prorated = "";
    $stmnt = $db->prepare("SELECT prorated FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach ($stmnt->fetchAll() as $row) {
        $prorated = $row["prorated"];
    }
    return $prorated;
}

function taxCode(array $items): string
{
    global $db;
    $entry = $items[0];

    $service = $entry->service;

    $taxCode = "";
    $stmnt = $db->prepare("SELECT taxcode FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach ($stmnt->fetchAll() as $row) {
        $taxCode = $row["taxcode"];
    }
    return $taxCode;
}

function createOrder($paymentIntent, $order_info, array $items, $taxRate): string
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
        $cookieLifetime = 365 * 24 * 60 * 60; // A year in seconds
        setcookie("ses_id",session_id(),time()+$cookieLifetime);
    }

    $_SESSION['order'] = $order_info->order;
    $_SESSION['service'] = $order_info->service;
    $_SESSION['customeremail'] = $order_info->customeremail;
    $_SESSION['schedule'] = $order_info->schedule;
    $_SESSION['order'] = $order_info->order;
    $_SESSION['phone'] = $order_info->phone;
    $_SESSION['message'] = $order_info->message;
    $_SESSION['taxrate'] = $taxRate;
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
    $_SESSION['intent'] = $paymentIntent->id;
    $_SESSION['providerId'] = $order_info->providerId;

    return $order_number;

}

function checkAcc(array $creds): bool
{
    include 'constants.php';

    $db = establish_database();
    $entry = $creds[0];
    $email = $entry->email;
    $phone = $entry->phone;

    $stmnt = $db->prepare("SELECT banned FROM {$DB_PREFIX}guests WHERE phone = ?;");
    $stmnt->execute(array($phone));
    foreach ($stmnt->fetchAll() as $row) {
        if ($row["banned"] == "y") {
            return false;
        }
    }

    $stmnt = $db->prepare("SELECT banned FROM {$DB_PREFIX}login WHERE email = ?;");
    $stmnt->execute(array($email));
    foreach ($stmnt->fetchAll() as $row) {
        if ($row["banned"] == "y") {
            return false;
        }
    }

    return true;
}
