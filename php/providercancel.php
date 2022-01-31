<?php
include 'common.php';

$stripe = new \Stripe\StripeClient(
    $STRIPE_API_KEY
);

if (isset($_POST["ordernumber"]) && isset($_POST['session']) && isset($_POST['tzoffset']) && isset($_POST['role'])) {

    sleep(3);

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
        $timezone = "";
        $zip = "";
        $status = "";
        $secondary_providers = "";
        $stmnt = $db->prepare("SELECT * FROM {$DB_PREFIX}orders WHERE order_number = ?;");
        $stmnt->execute(array($order_number));
        foreach ($stmnt->fetchAll() as $row) {
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
            $tz = $row['timezone'];
            $secondary_providers = $row['secondary_providers'];
            $status = $row['status'];
        }

        if ($status != "pe" && $status != "cl") {
            echo "already started";
        } else {

            if ($wage == "hour") {
                $duration = $duration . " hour(s)";
            }
            if ($wage == "per") {
                $duration = "No time limit";
            }

            $user = get_user_info($session);
            $cancelling_provider = $user['email'];

            $cancels = 0;
            $stmnt = $db->prepare("SELECT cancels FROM {$DB_PREFIX}login WHERE email = ?;");
            $stmnt->execute(array($cancelling_provider));
            foreach ($stmnt->fetchAll() as $row) {
                $cancels = $row['cancels'];
            }

            $cancels = $cancels + 1;
            if ($cancels > 1) {
                banning($cancels, $cancelling_provider);
            }

            $sql = "UPDATE {$DB_PREFIX}login SET cancels = ? WHERE email = ?;";
            $stmt = $db->prepare($sql);
            $params = array($cancels, $cancelling_provider);
            $stmt->execute($params);

            if (minutes_until($schedule) < 15) {

                $payment_info = payment($order_number);

                $stripe->paymentIntents->cancel(
                    trim($payment_info->intent),
                    []
                );

                $name = "";
                $stmnt = $db->prepare("SELECT firstname, timezone FROM {$DB_PREFIX}login WHERE email = ?;");
                $stmnt->execute(array($customer_email));
                foreach ($stmnt->fetchAll() as $row) {
                    $name = ' ' . $row['firstname'];
                }

                $sql = "UPDATE {$DB_PREFIX}orders SET status = ? WHERE order_number = ?;";
                $stmt = $db->prepare($sql);
                $params = array("pc", $order_number);
                $stmt->execute($params);

                $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
                $local_date->setTimezone(new DateTimeZone($tz));

                // send_email($customer_email, "admin@helphog.com", "Order Cancelled", get_cancel_email($name, $service, $order_number, $local_date->format('m\-d\-y \a\t g:ia')));

                echo 'task cancelled';
            } else {

                if ($wage == "hour") {
                    $providerWage = "$" . $price . "/hr";
                } else {
                    $providerWage = "$" . $price;
                }

                $re_notify_list = explode(',', $clicked);
                $contact = array();
                foreach ($re_notify_list as $email) {

                    if ($email != "" && $email != $cancelling_provider) {
                        $stmnt = $db->prepare("SELECT phone, timezone FROM {$DB_PREFIX}login WHERE email = ?;");
                        $stmnt->execute(array($email));
                        foreach ($stmnt->fetchAll() as $row) {
                            $entry = new stdClass();
                            $entry->email = $email;
                            $entry->phone = $row['phone'];
                            $entry->tz = $row['timezone'];
                            array_push($contact, $entry);
                        }
                    }
                }

                if (strtotime($schedule) - 3600000 < $t) {
                    $departureTime = $t;
                } else {
                    $departureTime = $departureTime - 3600000;
                }

                foreach ($contact as $provider) {

                    if (address_works_for_provider($address, $provider->email, $departureTime)->within) {
                        send_new_task_email($provider->email, $providerWage, $order_number, $duration, $accept_key, $provider->tz, $schedule, 'UTC', $address, $city, $state, $zip, $service, $message);
                        send_new_task_text($provider->phone, $provider->email, $order_number, $providerWage, $message, $duration, $accept_key, $provider->tz, $people, $schedule, 'UTC', $address, $city, $state, $zip, $service);
                    }
                }

                if ($role == "primary") {
                    $sql = "UPDATE {$DB_PREFIX}orders SET client_email = ?, status = ? WHERE order_number = ?;";
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
                        $updated_string .= ",";
                        $updated_string .= $array_without_cancelling_provider[$i];
                    }
                    if ($updated_string == ",") {
                        $updated_string = "";
                    }

                    $sql = "UPDATE {$DB_PREFIX}orders SET secondary_providers = ?, status = ? WHERE order_number = ?";
                    $stmt = $db->prepare($sql);
                    $params = array($updated_string, 'pe', $order_number);
                    $stmt->execute($params);
                }

                echo 'reactivated task';
            }
        }
    } else {
        echo 'access denied';
    }
} else {
    echo 'missing parameters';
}
