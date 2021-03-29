<?php

include 'common.php';

$response = new stdClass();
$response->ordernumber = "";
$response->error = "none";

session_start();

if (isset($_SESSION["intent"]) && isset($_SESSION["customeremail"]) && isset($_SESSION["service"]) && isset($_SESSION["schedule"]) && isset($_SESSION["address"]) && isset($_SESSION["zip"]) && isset($_SESSION["city"]) && isset($_SESSION["state"]) && isset($_SESSION["message"]) && isset($_SESSION["phone"]) && isset($_SESSION["order"]) && isset($_SESSION["people"]) && isset($_SESSION["duration"]) && isset($_SESSION["ordernumber"])) {

    $db = establish_database();

    $message = $_SESSION["message"];
    $response->message = $message;

    $order = $_SESSION["order"];
    $duration = $_SESSION["duration"];

    $day = $_SESSION["day"];

    $people;
    $orig_duration = intval($_SESSION["duration"]);


    $result = $db->query("SELECT order_cookie FROM orders;");
    $found = false;
    foreach ($result as $row) {
        if ($order === $row['order_cookie']) {
            $found = true;
        }
    }

    if (!$found) {
        $intent;
        $order_number = $_SESSION["ordernumber"];
        $intent = $_SESSION['intent'];
        $customer_email = $_SESSION["customeremail"];
        $service = $_SESSION["service"];
        $schedule = $_SESSION["schedule"];
        $street_address = $_SESSION["address"];
        $zip = $_SESSION["zip"];
        $city = ucfirst($_SESSION["city"]);
        $state = $_SESSION["state"];
        $customer_phone = $_SESSION["phone"];
        $people = $_SESSION["people"];
        $sales_tax_percent = $_SESSION["taxrate"];

        $address = $street_address . " " . $city . " " . $state . " " . $zip;

        $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone($_SESSION['tzoffset']));
        $utc->setTimezone(new DateTimeZone('UTC'));

        if (minutes_until($utc->format('Y-m-d H:i:s')) < 20) {

            $response->error = "waited very long before clicking place order";
        } else {

            $response->address = $street_address;
            $response->city = $city;
            $response->state = $state;
            $response->zip = $zip;
            $response->schedule = $schedule;
            $response->service = $service;
            $response->taxrate = $sales_tax_percent;

            $response->duration = $_SESSION['duration'];

            $prorated = "";
            $price = "";
            $wage = "";
            $name = "";
            $stmnt = $db->prepare("SELECT * FROM services WHERE service = ?;");
            $stmnt->execute(array($service));
            foreach ($stmnt->fetchAll() as $row) {
                $wage = $row["wage"];
                $price = $row["cost"];
                $prorated = $row["prorated"];
            }

            $response->cost = $price;
            $response->wage = $wage;
            $found = false;
            $result = $db->query("SELECT email FROM login;");
            foreach ($result as $row) {
                if ($customer_email === $row['email']) {
                    $found = true;
                }
            }

            $durationTemp = $duration;

            if ($wage == "hour") {
                $duration = $duration . " hour(s)";
            }
            if ($wage == "per") {
                $duration = "No time limit";
            }


            if ($found && $street_address != "Remote (online)") {
                
                $sql = "UPDATE login SET zip = ?, address = ?, city = ?, state = ? WHERE email = ?";
                $stmt = $db->prepare($sql);
                $params = array($_SESSION['zip'], $street_address, $city, $state, $customer_email);
                $stmt->execute($params);
                
            }

            $accept_key = '' . bin2hex(openssl_random_pseudo_bytes(12));
            $cancel_key = '' . bin2hex(openssl_random_pseudo_bytes(128));
            $image_key = '' . bin2hex(openssl_random_pseudo_bytes(128));

            $sql = "INSERT INTO orders (order_number, customer_email, timestamp, schedule, address, service, message, cost, customer_phone, client_email, wage, order_cookie, duration, people, intent, status, prorated, accept_key, cancel_key, timezone, cancel_buffer, image_key, sales_tax_percent, street_address, city, state, zip) VALUES (:order_number, :customer_email, :timestamp, :schedule, :address, :service, :message, :price, :customer_phone, :client_email, :wage, :order_cookie, :duration, :people, :intent, :status, :prorated, :accept_key, :cancel_key, :timezone, :cancel_buffer, :image_key, :sales_tax_percent, :street_address, :city, :state, :zip);";

            $time = date('m-d-y H:i:s');
            $stmt = $db->prepare($sql);
            $params = array("order_number" => $order_number, "customer_email" => $customer_email, "timestamp" => $time, "schedule" => $utc->format('Y-m-d H:i:s'), "address" => $address, "service" => $service, "message" => $message, "price" => $price, "customer_phone" => $customer_phone, "client_email" => "", "wage" => $wage, "order_cookie" => $order, "duration" => $durationTemp, "people" => $people, "intent" => $intent, "status" => "pe", "prorated" => $prorated, "accept_key" => $accept_key, "cancel_key" => $cancel_key, "timezone" => $_SESSION['tzoffset'], "cancel_buffer" => $_SESSION['cancel_buffer'], "image_key" => $image_key, "sales_tax_percent" => doubleval($sales_tax_percent), "street_address" => $street_address, "city" => $city, "state" => $state, "zip" => $zip);
            $stmt->execute($params);

            $orig_price = $price;

            if ($wage == "hour") {
                $providerWage = "$" . $price . "/hr";
            } else {
                $providerWage = "$" . $price;
            }

            if ($wage == "hour") {
                $price = $price * $people;
                $price = $price * $durationTemp;
            } else {
                $price *= $people;
            }

            $peopleText = "people";
            if ($people == 1) {
                $peopleText = "person";
            }

            $hourText = "hour";
            if ($durationTemp > 1) {
                $hourText = "hours";
            }

            // $durationText = "Until Completion";
            $durationText = $durationTemp . " " . $hourText;

            if ($wage == "hour") {
                $subtotal = $people . " " . $peopleText . " at $" . $orig_price . "/hr (" . $durationText . ")";
            } else {
                $subtotal = $people . " " . $peopleText . " for $" . $price;
            }

            $price = "$" . $price;

            $name = "";
            $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
            $stmnt->execute(array($customer_email));
            foreach ($stmnt->fetchAll() as $row) {
                $name = $row['firstname'];
            }

            if ($name == "") {
                $found = false;
                $name = "";
                $result = $db->query("SELECT phone FROM guests;");
                foreach ($result as $row) {
                    if ($customer_phone == $row["phone"]) {
                        $found = true;
                    }
                }
                $current_timestamp = gmdate("Y-m-d H:i:s");
                if (!$found) {
                    $sql = "INSERT INTO guests (phone, timestamp) VALUES (?, ?);";
                    $stmt = $db->prepare($sql);
                    $params = array($customer_phone, $current_timestamp);
                    $stmt->execute($params);
                } else {
                    $sql = "UPDATE guests SET timestamp = ? WHERE phone = ?;";
                    $stmt = $db->prepare($sql);
                    $params = array($current_timestamp, $customer_phone);
                    $stmt->execute($params);
                }
            }

            send_email($customer_email, "no-reply@helphog.com", "HelpHog - Confirmation Email", get_confirmation_email($order_number, $price, $service, $name, $schedule, $_SESSION["message"], $address, $people, $subtotal, $cancel_key));

            $response->firstname = $name;
            $response->schedule = $schedule;
            $response->people = $people;

            $available_providers = $_SESSION['available_providers'];

            foreach ($available_providers as $provider) {

                send_new_task_email($provider->email, $providerWage, $order_number, $duration, $accept_key, $provider->tz, $_SESSION['schedule'], $_SESSION['tzoffset'], $_SESSION["address"], $_SESSION['city'], $_SESSION['state'], $_SESSION['zip'], $_SESSION['service'], $_SESSION['message']);

                send_new_task_text($provider->phone, $provider->email, $order_number, $providerWage, $_SESSION["message"], $duration, $accept_key, $provider->tz, $people, $_SESSION['schedule'], $_SESSION['tzoffset'], $_SESSION['address'], $_SESSION['city'], $_SESSION['state'], $_SESSION['zip'], $_SESSION['service']);
            }

            $response->ordernumber = $order_number;
        }
    } else {

        $response->error = "tried to refresh confirmation page";
    }
} else {

    $response->error = "missing session parameters";
}

echo json_encode($response);
