<?php
include 'common.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

$stripe = new \Stripe\StripeClient(
    $STRIPE_API_KEY
);


$db = establish_database();
$sql = "SELECT * FROM {$DB_PREFIX}orders;";
$result = $db->query($sql);

foreach ($result as $row) {

    try {

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
        $tz = $row["timezone"];
        $cancel_buffer = $row['cancel_buffer'];
        $customer_timezone = $row['timezone'];

        if ($status == "pe" || $status == 'cl') {

            $needsToBeCancelled = false;

            if ($status == 'pe') {
                if (minutes_until($row["schedule"]) < $cancel_buffer) {
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
            }

            $provider_never_started = false;

            if ($status == 'cl' && minutes_since($schedule) > 120) {
                $needsToBeCancelled = true;
                $provider_never_started = true;

                $cancels = 0;
                $stmnt = $db->prepare("SELECT cancels FROM {$DB_PREFIX}login WHERE email = ?;");
                $stmnt->execute(array($client_email));
                foreach ($stmnt->fetchAll() as $row) {
                    $cancels = $row['cancels'];
                }

                $cancels = $cancels + 1;
                if ($cancels > 1) {
                    banning($cancels, $client_email);
                }

                $sql = "UPDATE {$DB_PREFIX}login SET cancels = ? WHERE email = ?;";
                $stmt = $db->prepare($sql);
                $params = array($cancels, $client_email);
                $stmt->execute($params);
            }

            if ($needsToBeCancelled) {

                $payment_info = payment($order_number);

                if ($client_email != "") {
                    $primary_work_phone = "";
                    $name = "";
                    $timezone = "";
                    $stmnt = $db->prepare("SELECT work_phone, firstname, timezone FROM {$DB_PREFIX}login WHERE email = ?;");
                    $stmnt->execute(array($client_email));
                    foreach ($stmnt->fetchAll() as $row) {
                        $primary_work_phone = $row['work_phone'];
                        $name = ' ' . $row['firstname'];
                        $timezone = $row['timezone'];
                    }

                    $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
                    $utc->setTimezone(new DateTimeZone($timezone));

                    if ($provider_never_started) {
                        send_email($client_email, "no-reply@helphog.com", "Task Cancelled", partner_never_started($service, $order_number, $utc->format('F j, Y, g:i a'), $name));
                    } else {
                        send_email($client_email, "no-reply@helphog.com", "Task Cancelled", noPartnersFound($service, $order_number, $utc->format('F j, Y, g:i a'), $name));
                    }

                    sendTextProvider($service, $order_number, $primary_work_phone, $utc->format('F j, Y, g:i a'), $provider_never_started);
                }


                foreach (explode(',', $secondary_providers) as $email) {
                    if ($email != "") {
                        $work_phone = "";
                        $phone = "";
                        $name2 = "";
                        $timezone = "";
                        $stmnt = $db->prepare("SELECT work_phone, phone, firstname, timezone FROM {$DB_PREFIX}login WHERE email = ?;");
                        $stmnt->execute(array($email));
                        foreach ($stmnt->fetchAll() as $row) {
                            $work_phone = $row['work_phone'];
                            if ($work_phone == "") {
                                $work_phone = $row['phone'];
                            }
                            $name2 = ' ' . $row['firstname'];
                            $timezone = $row['timezone'];
                        }

                        $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
                        $utc->setTimezone(new DateTimeZone($timezone));

                        sendTextProvider($service, $order_number, $work_phone, $utc->format('F j, Y, g:i a'), $provider_never_started);

                        if ($provider_never_started) {
                            send_email($email, "no-reply@helphog.com", "Task Cancelled", partner_never_started($service, $order_number, $utc->format('F j, Y, g:i a'), $name2));
                        } else {
                            send_email($email, "no-reply@helphog.com", "Task Cancelled", noPartnersFound($service, $order_number, $utc->format('F j, Y, g:i a'), $name2));
                        }
                    }
                }

                $name3 = "";
                $stmnt = $db->prepare("SELECT firstname, timezone FROM {$DB_PREFIX}login WHERE email = ?;");
                $stmnt->execute(array($customer_email));
                foreach ($stmnt->fetchAll() as $row) {
                    $name3 = ' ' . $row['firstname'];
                }

                $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
                $utc->setTimezone(new DateTimeZone($customer_timezone));

                $stripe->paymentIntents->cancel(
                    trim($payment_info->intent),
                    []
                );

                sendTextCustomer($service, $order_number, $customer_phone, $utc->format('F j, Y, g:i a'), $provider_never_started);

                if ($provider_never_started) {
                    send_email($customer_email, "no-reply@helphog.com", "Task Cancelled", provider_never_started($service, $order_number, $utc->format('F j, Y, g:i a'), $name3));
                    ios_customer_notification($customer_email, "Order Cancelled", "The provider for " .  $service . " (" . $order_number . ")" . " has not started working on your order.", $order_number, "#ff0000");
                } else {
                    send_email($customer_email, "no-reply@helphog.com", "Task Cancelled", noProviderFound($service, $order_number, $utc->format('F j, Y, g:i a'), $name3));
                    ios_customer_notification($customer_email, "Order Cancelled", "The provider for " .  $service . " (" . $order_number . ")" . " was not located in time.", $order_number, "#ff0000");
                }

                $sql = "UPDATE {$DB_PREFIX}orders SET status = ? WHERE order_number = ?;";
                $stmt = $db->prepare($sql);
                $params = array("ac", $order_number);
                $stmt->execute($params);
            }
        }
    } catch (\Throwable $e) {

        error_log($e->getMessage());

        send_email('maksim_maxim@live.com', "no-reply@helphog.com", "FATAL ERROR - autocancel.php (" . $row["order_number"] . ")", $e->getMessage());
        send_email('fadelshtiui@gmail.com', "no-reply@helphog.com", "FATAL ERROR - autocancel.php (" . $row["order_number"] . ")", $e->getMessage());
    }
}

function sendTextCustomer($service, $order, $phonenumber, $schedule, $provider_never_started)
{
    $message = '';
    if ($provider_never_started) {
        $message = ' was canceled because the provider has not started working on your order. The refund will appear in your bank statement within 5-10 business days.';
    } else {
        $message = ' was canceled because the service provider was not located in time. We apologize for the inconvenience.';
    }

    $sid = 'ACc66538a897dd4c177a17f4e9439854b5';
    $token = '18a458337ffdfd10617571e495314311';
    $client = new Client($sid, $token);
    $client->messages->create('+1' . $phonenumber, array('from' => '+12532593451', 'body' => 'Your order for ' . $service . ' (' . $order . ') on ' . $schedule . $message));
}

function sendTextProvider($service, $order, $phonenumber, $schedule, $provider_never_started)
{
    $message = '';
    if ($provider_never_started) {
        $message = ' was canceled because the primary provider has not started working on the order.';
    } else {
        $message = ' was canceled because one or more of the secondary providers were not located for this task.';
    }

    $sid = 'ACc66538a897dd4c177a17f4e9439854b5';
    $token = '18a458337ffdfd10617571e495314311';
    $client = new Client($sid, $token);
    $client->messages->create('+1' . $phonenumber, array('from' => '+12532593451', 'body' => 'Your task for ' . $service . ' (' . $order . ') on ' . $schedule . $message));
}
