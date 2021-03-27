<?php

include 'common.php';

require __DIR__ . '/taxjar/vendor/autoload.php';

$db = establish_database();

header('Content-Type: application/json');
try {
    $cred_check = true;
    // retrieve JSON from POST body
    $json_str = file_get_contents('php://input');
    $json_obj = json_decode($json_str);
    error_log('checking if user is banned...');
    $cred_check = checkAcc($json_obj->creds);
    error_log('checking if order is prorated...');
    $prorated = checkProrated($json_obj->items);
    error_log('retrieving tax code...');
    $taxCode = taxCode($json_obj->items);
    $order_info = $json_obj->checkout;
    error_log('calculating payment price...');
    $order_amount = calculateOrderAmount($json_obj->items);
    error_log('calculating sales tax...');
    $taxParameters = calculateTax($order_amount / 100, $taxCode, $order_info);
    if ($cred_check) {
        error_log('creating payment intent...');
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $order_amount + $taxParameters[0],
            'currency' => 'usd',
            'capture_method' => 'manual',
        ]);
        if ($taxParameters[1] == 0) {
            $taxRate = "";
        } else {
            $taxRate = $taxParameters[1] * 100 . "%";
        }
        error_log('creating order...');
        createOrder($paymentIntent, $order_info, $json_obj->items, $taxRate);
        error_log('done!');
        $output = [
            'clientSecret' => $paymentIntent->client_secret,
            'taxRate' => $taxRate,
            'prorated' => $prorated,
        ];
        echo json_encode($output);
    } else {
        echo json_encode(['error' => "fail"]);
    }
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// returns an array [amount to collect, sales tax percentage]
// return [0, 0] if no tax
function calculateTax($price, $taxCode, $order_info): array
{

    if ($taxCode == '') {
        return array(0, 0);
    }

    $client = TaxJar\Client::withApiKey('69bf1c893fbd334f69cbeab198128f8f');
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

function createOrder($paymentIntent, $order_info, array $items, $taxRate)
{
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

    // $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone($order_info->tzoffset));
    // $utc->setTimezone(new DateTimeZone('UTC'));

    $time = date('Y-m-d H:i:s');
    // $utc->format('Y-m-d H:i:s');

    // $cancelBuffer = ($time - $utc) / 60;

    $order_number = 0;
    $unique = false;
    while (!$unique) {
        $order_number = (time() + mt_rand()) % 100000;
        error_log($order_number);
        if ($order_number >= 10000) {
            $unique = true;
            $result = $db->query("SELECT order_number FROM orders");
            foreach ($result as $row) {
                if ($order_number == $row["order_number"]) {
                    $unique = false;
                    break;
                }
            }
        }
    }

    error_log("done generating order number!");

    if (strlen($order_info->message) > 1000) {
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
    // if ($order_info->cancelbuffer == 0){
    //     $_SESSION['cancel_buffer'] = $cancelBuffer;
    // }else{
    $_SESSION['cancel_buffer'] = $order_info->cancelbuffer;
    // }
    $_SESSION['ordernumber'] = $order_number;
    $_SESSION['intent'] = $paymentIntent->id;
}

function checkAcc(array $creds): bool
{
    $db = establish_database();
    $entry = $creds[0];
    $email = $entry->email;
    $phone = $entry->phone;

    $stmnt = $db->prepare("SELECT banned FROM guests WHERE phone = ?;");
    $stmnt->execute(array($phone));
    foreach ($stmnt->fetchAll() as $row) {
        if ($row["banned"] == "y") {
            return false;
        }
    }

    $stmnt = $db->prepare("SELECT banned FROM login WHERE email = ?;");
    $stmnt->execute(array($email));
    foreach ($stmnt->fetchAll() as $row) {
        if ($row["banned"] == "y") {
            return false;
        }
    }

    return true;
}
