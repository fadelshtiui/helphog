<?php

include 'constants.php';

require __DIR__ . '/stripe-php-master/init.php';
// This is your real test secret API key.
\Stripe\Stripe::setApiKey($STRIPE_API_KEY);
require __DIR__ . '/twilio-php-master/src/Twilio/autoload.php';

use Twilio\TwiML\MessagingResponse;
use Twilio\Rest\Client;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

ini_set('display_errors', 'On');
# error_reporting(E_ALL);
error_reporting(E_ERROR | E_PARSE);

function banning($cancels, $client_email)
{
	include 'constants.php';

	$db = establish_database();

	$name = "";
	$stmnt = $db->prepare("SELECT firstname FROM {$DB_PREFIX}login WHERE email = ?;");
	$stmnt->execute(array($client_email));
	foreach ($stmnt->fetchAll() as $row) {
		$name = ' ' . $row['firstname'];
	}

	if ($cancels == '2') {
		$note = "Our system has noticed several order cancellations on your behalf. We ask you not to claim orders if you are unable to fulfill them. Further cancellations will result in the suspension of your provider account.";
	}
	if ($cancels == '3') {
		$sql = "UPDATE {$DB_PREFIX}login SET type = ?, banned = 'y' WHERE email = ?;";
		$stmt = $db->prepare($sql);
		$params = array("Personal", $client_email);
		$stmt->execute($params);
		$note = "Due to excessive number of canceled orders on your behalf, provider privileges have been temporarily removed from your account. If you have any questions please contact us.";
	}

	send_email($client_email, "no-reply@helphog.com", "Account Notice", get_notice_email($name, $note));
}

function send_email($to, $from, $subject, $message)
{
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
	$mail->setFrom($from, 'HelpHog');
	$mail->addAddress($to, 'To');

	$mail->Subject = $subject;
	$mail->Body    = $message;
	$mail->IsHTML(true);

	$mail->send();

	$mail->ClearAllRecipients();
}

function send_text($phonenumber, $message)
{
	$sid = 'ACc66538a897dd4c177a17f4e9439854b5';
	$token = '18a458337ffdfd10617571e495314311';
	$client = new Client($sid, $token);
	$client->messages->create('+1' . $phonenumber, array('from' => '+12532593451', 'body' => $message));
}

function &payment($order)
{
	include 'constants.php';

	$db = establish_database();
	$result = new \stdClass();
	$sales_tax_percent = 0;
	$duration = 0;
	$cost = 0;
	$people = 0;
	$wage = "";
	$prorated = "";
	$worked_time = 0;
	$stmnt = $db->prepare("SELECT * FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order));
	foreach ($stmnt->fetchAll() as $row) {
		$sales_tax_percent = $row['sales_tax_percent'];
		$duration = $row['duration'];
		$cost = $row['cost'];
		$people = $row['people'];
		$wage = $row['wage'];
		$prorated = $row['prorated'];
		$result->intent = $row['intent'];

		if ($row["wage"] == "hour") {
			$ts1 = strtotime($row["start"]);
			$ts2 = strtotime($row["end"]);
			$seconds_diff = $ts2 - $ts1;
			$seconds_diff -= $row["paused_time"];
			$worked_time = ($seconds_diff / 3600);
		}
	}

	$result->wage = $wage;
	$result->duration = $duration;
	$result->worked_time = $worked_time;

	$total_before_tax = $cost * $people;
	$provider_payout = $cost * 0.9;

	if ($wage == "hour") {
		if ($worked_time < 1 && $prorated == "n") {
			$worked_time = 1;
		}
		if ($worked_time > $duration) {
			$worked_time = $duration;
		}

		$total_before_tax *= $worked_time;
		$provider_payout *= $worked_time;
	}

	$tax_collected = round($total_before_tax * ($sales_tax_percent / 100.0), 2);

	$result->customer_payment = round($total_before_tax + $tax_collected, 2);
	$result->provider_payout = $provider_payout;
	$result->total_before_tax = $total_before_tax;
	$result->tax_collected = $tax_collected;

	return $result;
}

function pay_provider($order_number)
{
	include 'constants.php';

	$stripe = new \Stripe\StripeClient(
		$STRIPE_API_KEY
	);

	$db = establish_database();

	$service = "";
	$tz = "";
	$schedule = "";
	$secondary_providers = "";
	$provider_email = "";
	$stmnt = $db->prepare("SELECT * FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order_number));
	foreach ($stmnt->fetchAll() as $row) {
		$service = $row["service"];
		$schedule = $row["schedule"];
		$secondary_providers = $row['secondary_providers'];
		$provider_email = $row['client_email'];
		$tz = $row['timezone'];
		$customer_email = $row['customer_email'];
		$status = $row['status'];
	}

	$payment_info = payment($order_number);

	if ($payment_info->customer_payment < 0.50 && $status != 'pd' && $status == 'mc') {

		$name = "";
		$stmnt = $db->prepare("SELECT firstname FROM {$DB_PREFIX}login WHERE email = ?;");
		$stmnt->execute(array($customer_email));
		foreach ($stmnt->fetchAll() as $row) {
			$name = ' ' . $row['firstname'];
		}

		$local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
		$local_date->setTimezone(new DateTimeZone($tz));

		$schedule = $local_date->format('m\-d\-y \a\t g:ia');

		$stripe->paymentIntents->cancel(
			trim($payment_info->intent),
			[]
		);

		$sql1 = "UPDATE {$DB_PREFIX}orders SET tax_collected = ?, status = 'pd' WHERE order_number = ?";
		$stmt1 = $db->prepare($sql1);
		$params1 = array(0, $order_number);
		$stmt1->execute($params1);

		send_email($customer_email, "no-reply@helphog.com", "Payment Waived", sendNoChargeEmail($service, $order_number, $schedule, $name));
	} else {
		$intent = \Stripe\PaymentIntent::retrieve(trim($payment_info->intent));
		$intent->capture(['amount_to_capture' => round($payment_info->customer_payment * 100)]);

		$stripe_acc = "";
		$stmnt = $db->prepare("SELECT stripe_acc FROM {$DB_PREFIX}login WHERE email = ?;");
		$stmnt->execute(array($provider_email));
		foreach ($stmnt->fetchAll() as $row) {
			$stripe_acc = $row['stripe_acc'];
		}

		$transfer = \Stripe\Transfer::create([
			"amount" => ceil($payment_info->provider_payout * 100),
			"currency" => "usd",
			"destination" => $stripe_acc,
			"description" => $service . " (" . $order_number . ")",
			"transfer_group" => '{' . $order_number . '}',
		]);

		$providers = explode(",", $secondary_providers);
		foreach ($providers as $provider) {
			if ($provider != "") {
				$secondary_stripe_acc = "";
				$stmnt = $db->prepare("SELECT stripe_acc FROM {$DB_PREFIX}login WHERE email = ?;");
				$stmnt->execute(array($provider));
				foreach ($stmnt->fetchAll() as $row) {
					$secondary_stripe_acc = $row["stripe_acc"];
					$transfer = \Stripe\Transfer::create([
						"amount" => ceil($payment_info->provider_payout * 100),
						"currency" => "usd",
						"destination" => $secondary_stripe_acc,
						"description" => $service . " (" . $order_number . ")",
						"transfer_group" => '{' . $order_number . '}',
					]);
				}
			}
		}

		$sql = "UPDATE {$DB_PREFIX}orders SET tax_collected = ?, status = 'pd' WHERE order_number = ?";
		$stmt = $db->prepare($sql);
		$params = array($payment_info->tax_collected, $order_number);
		$stmt->execute($params);
	}
}

function send_new_task_email($client, $price, $ordernumber, $duration, $secret_key, $tz, $schedule, $tzoffset, $address, $city, $state, $zip, $service, $message)
{
	include 'constants.php';

	$db = establish_database();
	$name = "";
	$alerts = "";
	$stmnt = $db->prepare("SELECT firstname, alerts FROM {$DB_PREFIX}login WHERE email = ?;");
	$stmnt->execute(array($client));
	foreach ($stmnt->fetchAll() as $row) {
		$name = ' ' . $row['firstname'];
		$alerts = $row['alerts'];
	}

	if ($alerts == "email" || $alerts == "both") {

		$local_time = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone($tzoffset));
		$local_time->setTimezone(new DateTimeZone($tz));

		if ($address == "Remote (online)") {
			$location = $address;
		} else {
			$location = ucfirst($city) . ', ' . $state . ' ' . $zip;
		}

		send_email($client, "no-reply@helphog.com", "New Task Available", get_claim_email($service, $local_time->format("F j, Y, g:i a"), $location, $client, $ordernumber, $price, $message, $name, $duration, $secret_key));
	}
}

function send_new_task_text($phonenumber, $email, $ordernumber, $price, $message, $duration, $secret_key, $tz, $people, $schedule, $tzoffset, $address, $city, $state, $zip, $service)
{
	include 'constants.php';

	$db = establish_database();

	$alerts = "";
	$stmnt = $db->prepare("SELECT alerts FROM {$DB_PREFIX}login WHERE email = ?;");
	$stmnt->execute(array($email));
	foreach ($stmnt->fetchAll() as $row) {
		$alerts = $row['alerts'];
	}

	$local_time = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone($tzoffset));
	$local_time->setTimezone(new DateTimeZone($tz));
	$t = time();

	if ($address == "Remote (online)") {
		$location = $address;
		$commute = "";
	} else {
		$location = ucfirst($city) . ', ' . $state;
		$address = str_replace(' ', '+', $address . '+' . $city . '+' . $state . '+' . $zip);
		$departureTime = "";
		if (strtotime($schedule) - 3600000 < $t) {
			$departureTime = $t;
		} else {
			$departureTime = $departureTime - 3600000;
		}
		$matrix = address_works_for_provider($address, $email, $departureTime);
		$commute = "Estimated commute: " . ceil(($matrix->traffic) / 60) . " minutes";
	}

	$partners = "";

	if ($people > 1) {
		$partners = "Task requires cordinating with " . ($people - 1) . " other provider(s)";
	}

	if ($alerts == "sms" || $alerts == "both") {

		$sid = 'ACc66538a897dd4c177a17f4e9439854b5';
		$token = '18a458337ffdfd10617571e495314311';
		$client = new Client($sid, $token);
		$client->messages->create('+1' . $phonenumber, array('from' => '+12532593451', 'body' => 'There\'s a new service request in your area!

Service: ' . $service . '
Date: ' . $local_time->format("F j, Y, g:i a") . '
Max duration: ' . $duration . '
' . $commute . '
Location: ' . $location . '
Pay: ' . $price . '
' . $partners . '

Message: ' . $message . '

Tap on the following link to obtain this job:

https://' . $SUBDOMAIN . 'helphog.com/php/accept.php?email=' . $email . '&ordernumber=' . $ordernumber . '&secret=' . $secret_key));
	}
}

function minutes_until($time)
{
	$then = new DateTime(date('Y-m-d H:i:s', strtotime($time)), new DateTimeZone('UTC'));
	$now = new DateTime(gmdate('Y-m-d H:i:s'));

	$diff = strtotime($then->format('Y-m-d H:i:s')) - strtotime($now->format('Y-m-d H:i:s'));

	return $diff / 60;
}

function minutes_since($time)
{
	$then = new DateTime(date('Y-m-d H:i:s', strtotime($time)), new DateTimeZone('UTC'));
	$now = new DateTime(gmdate('Y-m-d H:i:s'));

	$diff = strtotime($now->format('Y-m-d H:i:s')) - strtotime($then->format('Y-m-d H:i:s'));

	return $diff / 60;
}

function user_exists($session)
{
	include 'constants.php';

	$db = establish_database();

	$result = $db->query("SELECT session FROM {$DB_PREFIX}login;");
	foreach ($result as $row) {
		if (hash_equals($row['session'], $session)) {
			return true;
		}
	}

	return false;
}


function validate_customer($order, $session)
{
	include 'constants.php';

	$db = establish_database();

	$customer_email = "";
	$stmnt = $db->prepare("SELECT customer_email FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order));
	foreach ($stmnt->fetchAll() as $row) {
		$customer_email = $row['customer_email'];
	}

	$stmnt = $db->prepare("SELECT session FROM {$DB_PREFIX}login WHERE email = ?;");
	$stmnt->execute(array($customer_email));
	foreach ($stmnt->fetchAll() as $row) {
		if (hash_equals($row['session'], $session)) {
			return true;
		}
	}

	return false;
}

function validate_customer_phone($order, $phone)
{
	include 'constants.php';

	$db = establish_database();

	$customer_phone = "";
	$stmnt = $db->prepare("SELECT customer_phone FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order));
	foreach ($stmnt->fetchAll() as $row) {
		if (hash_equals($row['customer_phone'], $phone)) {
			return true;
		}
	}

	return false;
}

function address_works_for_provider($address, $email, $orderTime)
{
	include 'constants.php';

	$db = establish_database();
	$distanceMatrix = new \stdClass();
	$stmnt = $db->prepare("SELECT work_address, work_state, work_city, work_zip, radius FROM {$DB_PREFIX}login WHERE email = ?;");
	$stmnt->execute(array($email));
	$radius = 0;
	$work_address = "";
	$work_state = "";
	$work_city = "";
	$work_zip = "";
	foreach ($stmnt->fetchAll() as $row) {
		$radius = intval($row["radius"]);
		$work_address = $row["work_address"];
		$work_state = $row["work_state"];
		$work_city = $row["work_city"];
		$work_zip = $row["work_zip"];
	}

	$start = str_replace(' ', '+', $work_address . '+' . $work_city . '+' . $work_state . '+' . $work_zip);

	$url = "https://maps.googleapis.com/maps/api/distancematrix/json?units=imperial";
	$url .= "&traffic_model=best_guess";
	$url .= "&departure_time=" . $orderTime;
	$url .= "&origins=" . $start;
	$url .= "&destinations=" . $address;
	$url .= "&key=AIzaSyBLOFTNoq2ypQGRX_CgCMSUkBhFlmPYWCg";

	$response = file_get_contents($url);
	$json = json_decode($response);

	$distance = intval($json->rows[0]->elements[0]->distance->value) / 1609;
	$duration = intval($json->rows[0]->elements[0]->duration->value);
	$traffic = intval($json->rows[0]->elements[0]->duration_in_traffic->value);

	$distanceMatrix->within = $distance <= $radius;
	$distanceMatrix->duration = $duration;
	$distanceMatrix->traffic = $traffic;

	return $distanceMatrix;
}

function validate_provider_email($order, $email)
{
	include 'constants.php';

	$db = establish_database();

	$stmnt = $db->prepare("SELECT client_email FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order));
	foreach ($stmnt->fetchAll() as $row) {
		if (hash_equals($row['client_email'], $email)) {
			return true;
		}
	}

	return false;
}

function validate_provider($order, $session)
{
	include 'constants.php';

	$db = establish_database();
	$all_providers = array();

	$stmnt = $db->prepare("SELECT client_email, secondary_providers FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order));
	foreach ($stmnt->fetchAll() as $row) {
		$client_email = $row['client_email'];
		$secondary_providers = $row['secondary_providers'];


		if ($client_email != "") {
			array_push($all_providers, $client_email);
		}

		$secondary_providers_array = explode(',', $secondary_providers);
		foreach ($secondary_providers_array as $secondary_provider) {
			if ($secondary_provider != "") {
				array_push($all_providers, $secondary_provider);
			}
		}
	}

	foreach ($all_providers as $provider) {
		$stmnt = $db->prepare("SELECT session FROM {$DB_PREFIX}login WHERE email = ?;");
		$stmnt->execute(array($provider));
		foreach ($stmnt->fetchAll() as $row) {
			if (hash_equals($row['session'], $session)) {
				return true;
			}
		}
	}

	return false;
}

function validate_user($email, $session)
{
	include 'constants.php';

	$db = establish_database();
	$customer_email = "";
	$stmnt = $db->prepare("SELECT session FROM {$DB_PREFIX}login WHERE email = ?;");
	$stmnt->execute(array($email));
	foreach ($stmnt->fetchAll() as $row) {
		if (hash_equals($row['session'], $session)) {
			return true;
		}
	}

	return false;
}

function &validate_form($firstname, $lastname, $email, $password, $zip, $confirm, $phone)
{
	include 'constants.php';

	$db = establish_database();

	$first_name_error = "";
	$last_name_error = "";
	$email_error = "";
	$password_error = "";
	$phone_error = "";
	$zip_error = "";
	$confirm_error = "";

	if (!preg_match("/^[A-Za-z]+$/", $firstname)) {
		$first_name_error = "true";
	}
	if ($firstname == "") {
		$first_name_error = "empty";
	}

	if (!preg_match("/^[A-Za-z]+$/", $lastname)) {
		$last_name_error = "true";
	}
	if ($lastname == "") {
		$last_name_error = "empty";
	}

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$email_error = "true";
	}
	if ($email == "") {
		$email_error = "empty";
	}
	$result = $db->query("SELECT email FROM {$DB_PREFIX}login;");
	foreach ($result as $row) {
		if ($email === $row['email']) {
			$email_error = "found";
		}
	}

	if (!preg_match("/^\S{6,}$/", $password)) {
		$password_error = "true";
	}
	if ($password == "") {
		$password_error = "empty";
	}

	if (!preg_match("/^[0-9]{5}$/", $zip)) {
		$zip_error = "true";
	}
	if ($zip == "") {
		$zip_error = "empty";
	}

	if ($confirm !== $password) {
		$confirm_error = "true";
	}
	if ($confirm == "") {
		$confirm_error = "empty";
	}

	if (!preg_match("/^[0-9]{10}$/", $phone)) {
		$phone_error = "true";
	}
	if ($phone == "") {
		$phone_error = "empty";
	}
	$result = $db->query("SELECT phone FROM {$DB_PREFIX}login;");
	foreach ($result as $row) {
		if ($phone === $row['phone']) {
			$phone_error = "found";
		}
	}

	$errors = new \stdClass();
	$errors->firstnameerror = $first_name_error;
	$errors->lastnameerror = $last_name_error;
	$errors->emailerror = $email_error;
	$errors->passworderror = $password_error;
	$errors->confirmerror = $confirm_error;
	$errors->phoneerror = $phone_error;
	$errors->ziperror = $zip_error;

	return $errors;
}

function pause_order($order)
{
	include 'constants.php';

	$db = establish_database();

	$time = gmdate('y-m-d H:i:s');
	$sql = "UPDATE {$DB_PREFIX}orders SET pause = ?, currently_paused = ? WHERE order_number = ?";
	$stmt = $db->prepare($sql);
	$params = array($time, 'y', $order);
	$stmt->execute($params);
}

function resume_order($order)
{
	include 'constants.php';

	$db = establish_database();

	$time = gmdate('y-m-d H:i:s');
	$sql = "UPDATE {$DB_PREFIX}orders SET resume = ?, currently_paused = ? WHERE order_number = ?";
	$stmt = $db->prepare($sql);
	$params = array($time, 'n', $order);
	$stmt->execute($params);

	$start_actual = "";
	$end_actual = "";
	$stmnt = $db->prepare("SELECT pause, resume FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order));
	foreach ($stmnt->fetchAll() as $row) {
		$start_actual = $row['pause'];
		$end_actual = $row['resume'];
	}

	$ts1 = strtotime($start_actual);
	$ts2 = strtotime($end_actual);
	$seconds_diff = $ts2 - $ts1;

	$oldtimeactual = "";
	$stmnt = $db->prepare("SELECT paused_time FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order));
	foreach ($stmnt->fetchAll() as $row) {
		$oldtimeactual = $row['paused_time'];
	}

	$new_time = $seconds_diff + $oldtimeactual;

	$sql = "UPDATE {$DB_PREFIX}orders SET paused_time = ? WHERE order_number = ?";
	$stmt = $db->prepare($sql);
	$params = array($new_time, $order);
	$stmt->execute($params);
}

function start_stop_order($order)
{
	include 'constants.php';

	$db = establish_database();
	$time = gmdate('y-m-d H:i:s');

	$status = "";
	$schedule = "";
	$stmnt = $db->prepare("SELECT status, schedule FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order));
	foreach ($stmnt->fetchAll() as $row) {
		$status = $row['status'];
		$schedule = $row['schedule'];
	}

	if (minutes_until($schedule) > 45) {

		return 'You can only start orders within 45 minutes of the schedule time.';
	} else {

		$sql = "";
		if ($status == "st") {
			$sql = "UPDATE {$DB_PREFIX}orders SET end = ?, status = 'en' WHERE order_number = ?";
		} else if ($status == "cl") {
			$sql = "UPDATE {$DB_PREFIX}orders SET start = ?, status = 'st' WHERE order_number = ?";
		}

		if ($sql != "") {
			$stmt = $db->prepare($sql);
			$params = array($time, $order);
			$stmt->execute($params);
			return '';
		} else {
			return 'This order has not been fully claimed. Secondary providers must first claim this order.';
		}
	}
}

function getId($email)
{
	include 'constants.php';

	$db = establish_database();
	$stmnt = $db->prepare("SELECT id FROM {$DB_PREFIX}login WHERE email = ?;");
	$stmnt->execute(array($email));
	foreach ($stmnt->fetchAll() as $row) {
		$providerId = $row['id'];
	}
	return $providerId;
}

function mark_completed($order, $message)
{
	include 'constants.php';

	$payment_info = payment($order);

	$db = establish_database();

	$service = "";
	$customer_email = "";
	$customer_phone = "";
	$wage = "";
	$cost = "";
	$people = "";
	$schedule = "";
	$client_email = "";
	$tz = "";
	$disputes = 0;
	$tax_rate = 0.00;
	$stmnt = $db->prepare("SELECT * FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order));
	foreach ($stmnt->fetchAll() as $row) {
		$service = $row['service'];
		$customer_email = $row['customer_email'];
		$customer_phone = $row['customer_phone'];
		$disputes = $row['disputes'];
		$cost = $row['cost'];
		$wage = $row['wage'];
		$people = $row['people'];
		$schedule = $row['schedule'];
		$client_email = $row['client_email'];
		$tz = $row['timezone'];
		$tax_rate = $row['sales_tax_percent'];
	}

	$providerId = getId($client_email);


	if ($disputes < 3) {
		$name = "";
		$phone = "";
		$stmnt = $db->prepare("SELECT firstname, phone FROM {$DB_PREFIX}login WHERE email = ?;");
		$stmnt->execute(array($customer_email));
		foreach ($stmnt->fetchAll() as $row) {
			$name = " " . $row['firstname'];
			$phone = $row['phone'];
		}

		//Receipt text and email

		$customer_payment = $payment_info->customer_payment;
		$tax_collected = $payment_info->tax_collected;
		$duration =  $payment_info->worked_time;
		$total_before_tax = $payment_info->total_before_tax;

		$local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
		$local_date->setTimezone(new DateTimeZone($tz));

		$schedule = $local_date->format('m\-d\-y \a\t g:ia');

		error_log("customer payment" . $customer_payment . "tax" . $tax_collected . "duration" . $duration . "total_before_tax" . $total_before_tax);

		$price = $cost;

		if ($wage == "hour") {
			$duration = $duration . " hour(s)";
		}
		if ($wage == "per") {
			$duration = "No time limit";
		}

		$orig_price = $cost;

		if ($wage == "hour") {
			$price = $price * $people;
		} else {
			$cost *= $people;
		}

		$peopleText = "providers";
		if ($people == 1) {
			$peopleText = "provider";
		}

		$amount = 0;
		if ($wage == "hour") {
			$subtotal = $people . " " . $peopleText . " at $" . money_format('%.2n', $orig_price) . "/hr (" . number_format((float)$duration, 2, '.', '') . " hours)";
			$amount = $total_before_tax;
		} else {
			$subtotal = $people . " " . $peopleText . " for $" . money_format('%.2n', $price);
			$amount = $cost;
		}

		send_email($customer_email, "orders@helphog.com", "Receipt for " . $service, get_receipt($name, $service, $order, $schedule, $subtotal, $amount, $tax_collected, $customer_payment, $providerId, $tax_rate));

		$message = $service . ' (' . $order . ') on ' . $schedule  . ' has been marked completed. Here is the order summary:

' . $subtotal . '

Subtotal      -  $' .  money_format('%.2n', $amount) . '

Sales tax (' . $tax_rate . '%) -  $' . money_format('%.2n', $tax_collected) . '

Total Amount  -  $' . money_format('%.2n', $customer_payment) . '

If there\'s an issue with the quality of service provided, you may dispute this order by texting back DISPUTE.

For future orders with the same provider use #' . $providerId . ' at checkout.';


		send_text($customer_phone, $message);

		$current_timestamp = gmdate("Y-m-d H:i:s");
		$sql = "UPDATE {$DB_PREFIX}orders SET status = ?, mc_timestamp = ? WHERE order_number = ?";
		$stmt = $db->prepare($sql);
		$params = array('mc', $current_timestamp, $order);
		$stmt->execute($params);

		return true;
	} else {

		return false;
	}
}

/**
 * adds the given provider to the given order
 *
 * @param {string} email - email of provider
 * @param {number} order_number - order number of order to be claimed
 * @param {string} accept_key - secret key generated during checkout process
 * @return {string} JS script tag that redirects the page to appropriate outcome
 */
function claim_order($email, $order_number, $accept_key, $mobile)
{
	include 'constants.php';

	$db = establish_database();

	$clicked = '';
	$found = false;
	$cancelled = true;
	$wage = '';
	$stmnt = $db->prepare("SELECT wage, duration, accept_key, status, clicked FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order_number));
	foreach ($stmnt->fetchAll() as $row) {
		if ($row['accept_key'] === $accept_key) {
			$found = true;
		}
		if ($row['status'] == 'pe') {
			$cancelled = false;
		}
		$duration = $row['duration'];
		$wage = $row['wage'];
		$clicked = $row['clicked'];
	}

	$new_clicked = "";
	$already_clicked = false;
	if ($clicked == "") {
		$new_clicked = $email;
	} else {
		$re_notify_list = explode(',', $clicked);

		foreach ($re_notify_list as $clicked_email) {
			if ($email == $clicked_email) {
				$already_clicked = true;
				break;
			}
		}
		if (!$already_clicked) {
			$new_clicked = $clicked . ',' . $email;
		} else {
			$new_clicked = $clicked;
		}
	}

	$sql = "UPDATE {$DB_PREFIX}orders SET clicked = ? WHERE order_number = ?";
	$stmt = $db->prepare($sql);
	$params = array($new_clicked, $order_number);
	$stmt->execute($params);

	if ($found && !$cancelled) {

		if ($wage == "hour") {
			$duration = $duration . " hour(s)";
		} else {
			$duration = "No time limit";
		}

		$client_email = "";
		$stmnt = $db->prepare("SELECT client_email FROM {$DB_PREFIX}orders WHERE order_number = ?;");
		$stmnt->execute(array($order_number));
		foreach ($stmnt->fetchAll() as $row) {
			$client_email = $row['client_email'];
		}

		$client_phone = "";
		$stmnt = $db->prepare("SELECT phone FROM {$DB_PREFIX}login WHERE email = ?;");
		$stmnt->execute(array($client_email));
		foreach ($stmnt->fetchAll() as $row) {
			$client_phone = $row['phone'];
		}

		$first_provider = ($client_email == "");

		$people = 1;
		$stmnt = $db->prepare("SELECT people FROM {$DB_PREFIX}orders WHERE order_number = ?;");
		$stmnt->execute(array($order_number));
		foreach ($stmnt->fetchAll() as $row) {
			$people = $row['people'];
		}

		if ($email == $client_email) {
			return '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/error?message=Sorry!+Looks+like+someone+has+already+claimed+this+order";</script>';
		}

		$secondary_providers = "";
		$stmnt = $db->prepare("SELECT secondary_providers FROM {$DB_PREFIX}orders WHERE order_number = ?;");
		$stmnt->execute(array($order_number));
		foreach ($stmnt->fetchAll() as $row) {
			$secondary_providers = $row['secondary_providers'];
		}

		if (!$first_provider) {

			if ($people == 1) {
				return '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/error?message=Sorry!+Looks+like+someone+has+already+claimed+this+order";</script>';
			} else {

				if (strpos($secondary_providers, $email) !== false) {
					return '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/error?message=Sorry!+Looks+like+someone+has+already+claimed+this+order";</script>';
				}

				$num_secondary = 0;
				if ($secondary_providers == "") {
					$num_secondary = 0;
				} else {
					$num_secondary = count(explode(',', $secondary_providers));
				}

				if ($num_secondary + 1 >= $people) {
					return '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/error?message=Sorry!+Looks+like+someone+has+already+claimed+this+order";</script>';
				} else {

					$new_secondary = "";
					if ($secondary_providers == "") {
						$new_secondary = $email;
					} else {
						$new_secondary = $secondary_providers . ',' . $email;
					}

					if ($num_secondary + 2 == $people) { // plus 1 for primary, and 1 for person currently claiming

						$sql = "UPDATE {$DB_PREFIX}orders SET status = ? WHERE order_number = ?";
						$stmt = $db->prepare($sql);
						$params = array("cl", $order_number);
						$stmt->execute($params);

						$secondary_providers_array = explode(',', $new_secondary);
						$message = "Here are the providers you will be working with:\n";
						foreach ($secondary_providers_array as $curr_email) {

							$name = "";
							$phone = "";
							$stmnt = $db->prepare("SELECT firstname, phone FROM {$DB_PREFIX}login WHERE email = ?;");
							$stmnt->execute(array($curr_email));
							foreach ($stmnt->fetchAll() as $row) {
								$name = $row['firstname'];
								$phone = $row['phone'];
							}

							$message .= $name . " (" . $phone . ")\n";
						}

						$sid = 'ACc66538a897dd4c177a17f4e9439854b5';
						$token = '18a458337ffdfd10617571e495314311';
						$client = new Client($sid, $token);
						$client_phone = '+1' . $client_phone;
						$client->messages->create($client_phone, array('from' => '+12532593451', 'body' => $message));
					}

					$sql = "UPDATE {$DB_PREFIX}orders SET secondary_providers = ?  WHERE order_number = ?";
					$stmt = $db->prepare($sql);
					$params = array($new_secondary, $order_number);
					$stmt->execute($params);

					send_claimed_notification($order_number, $email, "secondary", $db, $duration);
					return '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/success?You+have+successfully+claimed+the+task!&link=provider&content=manage+and+monitor+your+order";</script>';
				}
			}
		} else { // first provider

			$replacing_primary_provider = ($secondary_providers != "" && count(explode(',', $secondary_providers)) + 1 == $people);

			if ($people == 1 || $replacing_primary_provider) {
				$sql = "UPDATE {$DB_PREFIX}orders SET status = ? WHERE order_number = ?";
				$stmt = $db->prepare($sql);
				$params = array("cl", $order_number);
				$stmt->execute($params);
			}

			if ($replacing_primary_provider) {
				$secondary_providers_array = explode(',', $secondary_providers);
				$message = "Here are the providers you will be working with:\n";
				foreach ($secondary_providers_array as $curr_email) {

					$name = "";
					$phone = "";
					$stmnt = $db->prepare("SELECT firstname, phone FROM {$DB_PREFIX}login WHERE email = ?;");
					$stmnt->execute(array($curr_email));
					foreach ($stmnt->fetchAll() as $row) {
						$name = $row['firstname'];
						$phone = $row['phone'];
					}

					$message .= $name . " (" . $phone . ")\n";
				}

				$stmnt = $db->prepare("SELECT phone FROM {$DB_PREFIX}login WHERE email = ?;");
				$stmnt->execute(array($email));
				foreach ($stmnt->fetchAll() as $row) {
					$client_phone = $row['phone'];
				}

				$sid = 'ACc66538a897dd4c177a17f4e9439854b5';
				$token = '18a458337ffdfd10617571e495314311';
				$client = new Client($sid, $token);
				$client_phone = '+1' . $client_phone;
				$client->messages->create($client_phone, array('from' => '+12532593451', 'body' => $message));
			}

			$sql = "UPDATE {$DB_PREFIX}orders SET client_email = ? WHERE order_number = ?";
			$stmt = $db->prepare($sql);
			$params = array($email, $order_number);
			$stmt->execute($params);

			send_claimed_notification($order_number, $email, "primary", $db, $duration);

			if ($mobile) {
				return '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/mobileclaimed";</script>';
			}
			return '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/success?message=You+have+successfully+claimed+the+task!&link=provider&content=manage+and+monitor+your+order";</script>';
		}
	}
	if ($mobile) {
		return '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/mobiledecline";</script>';
	}
	return '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/error?message=Sorry!+Looks+like+someone+has+already+claimed+this+order";</script>';
}

function dispute_order($order_number)
{
	include 'constants.php';

	$db = establish_database();

	$service = "";
	$client_email = "";
	$end_time = "";
	$secondary_providers = "";
	$customer_email = "";
	$customer_phone = "";
	$been_disputed = "";
	$schedule = "";
	$tz = "";
	$order_disputes = 0;
	$stmnt = $db->prepare("SELECT * FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order_number));
	foreach ($stmnt->fetchAll() as $row) {
		$service = $row['service'];
		$client_email = $row['client_email'];
		$customer_email = $row['customer_email'];
		$end_time = $row['end'];
		$secondary_providers = $row['secondary_providers'];
		$customer_phone = $row['customer_phone'];
		$been_disputed = $row['been_disputed'];
		$order_disputes = $row['disputes'];
		$schedule = $row['schedule'];
		$tz = $row['timezone'];
	}

	if (minutes_since($end_time) <= 1440) {

		$email_found = false;
		$disputes = 0;
		$result = $db->query("SELECT email, disputes FROM {$DB_PREFIX}login;");
		foreach ($result as $row) {
			if ($customer_email == $row['email']) {
				$email_found = true;
				$disputes = $row['disputes'];
			}
		}

		$table = "{$DB_PREFIX}login";
		if (!$email_found) {
			$result = $db->query("SELECT phone, disputes FROM {$DB_PREFIX}guests;");
			foreach ($result as $row) {
				if ($customer_phone == $row['phone']) {
					$disputes = $row['disputes'];
				}
			}
			$table = "{$DB_PREFIX}guests";
		}

		if ($been_disputed == 'n') {

			$disputes += 1;
			$sql = "UPDATE " . $table . " SET disputes = ? WHERE phone = ?";
			$stmt = $db->prepare($sql);
			$params = array($disputes, $customer_phone);
			$stmt->execute($params);

			//banning
			$num_orders = 0;
			$orders = $db->query("SELECT * FROM {$DB_PREFIX}orders;");
			foreach ($orders as $order) {
				if ($order["customer_phone"] == $customer_phone) {
					$num_orders++;
				}
			}

			if ($num_orders >= 4) {
				// bans users if more that 50% of their orders are disputed
				if ($disputes / $num_orders > 0.5) {
					if ($email_found) {
						$sql = "UPDATE {$DB_PREFIX}login SET banned = ? WHERE email = ?";
						$stmt = $db->prepare($sql);
						$params = array('y', $customer_email);
						$stmt->execute($params);
					} else {
						$sql = "UPDATE {$DB_PREFIX}guests SET banned = ? WHERE phone = ?";
						$stmt = $db->prepare($sql);
						$params = array('y', $customer_phone);
						$stmt->execute($params);
					}
				}
			}
		}

		$order_disputes += 1;
		$sql = "UPDATE {$DB_PREFIX}orders SET status = ?, been_disputed = 'y', disputes = ? WHERE order_number = ?";
		$stmt = $db->prepare($sql);
		$params = array('di', $order_disputes, $order_number);
		$stmt->execute($params);

		if ($order_disputes == 3) {
			send_email("admin@helphog.com", "admin@helphog.com", "Mediation Required", 'Order Number: ' . $order_number);
		}

		$all_emails = array();
		array_push($all_emails, $client_email);
		$secondary_emails = explode(',', $secondary_providers);

		for ($i = 0; $i < count($secondary_emails); $i++) {
			if ($secondary_emails[$i] != "") {
				array_push($all_emails, $secondary_emails[$i]);
			}
		}

		for ($i = 0; $i < count($all_emails); $i++) {
			$name = "";
			$stmnt = $db->prepare("SELECT firstname FROM {$DB_PREFIX}login WHERE email = ?;");
			$stmnt->execute(array($all_emails[$i]));
			foreach ($stmnt->fetchAll() as $row) {
				$name = ' ' . $row['firstname'];
			}

			$local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
			$local_date->setTimezone(new DateTimeZone($tz));

			send_email($all_emails[$i], "no-reply@helphog.com", "Task Disputed", get_dispute_email($name, $service, $schedule, $order_number));
		}

		return true;
	} else {
		return false;
	}
}

function &establish_database()
{
	include 'constants.php';

	$host = 'localhost';
	$dbname = 'regiuzkk_help';
	$user = 'regiuzkk_help';
	$password = '3ZY1v^}T,9]b';
	$ds = "mysql:host={$host};dbname={$dbname};charset=utf8";

	try {
		$db = new PDO($ds, $user, $password);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	} catch (PDOException $ex) {
		header("Content-type: text/plain");
		print "Can not connect to the database. Please try again later.\n";
		print "Error details: $ex \n";
		die();
	}
	return $db;
}

function check_session($post_session)
{
	include 'constants.php';

	$db = establish_database();
	$found = false;
	if ($post_session != "") {
		$sessions = $db->query("SELECT session FROM {$DB_PREFIX}login;");
		foreach ($sessions as $session) {
			if (hash_equals($post_session, $session[0])) {
				$found = true;
			}
		}
	}
	return $found;
}

function get_order_status($order)
{
	include 'constants.php';

	$db = establish_database();
	$order_status = "nf";
	$result = $db->query("SELECT order_number FROM {$DB_PREFIX}orders;");
	$found = false;
	foreach ($result as $row) {
		if ($order === $row['order_number']) {
			$found = true;
		}
	}
	if ($found) {
		$stmnt = $db->prepare("SELECT status FROM {$DB_PREFIX}orders WHERE order_number = ?;");
		$stmnt->execute(array($order));
		foreach ($stmnt->fetchAll() as $row) {
			$order_status = $row['status'];
		}
	}
	return $order_status;
}

function send_claimed_notification($order_number, $email, $type, $db, $duration)
{
	include 'constants.php';

	$wage = "";
	$customer_message = "";
	$service = "";
	$schedule = "";
	$address = "";
	$price = "";
	$customer_email = "";
	$customer_phone = "";
	$client_phone = "";
	$stmnt = $db->prepare("SELECT service, schedule, address, customer_email, message, customer_phone, cost, wage FROM {$DB_PREFIX}orders WHERE order_number = ?;");
	$stmnt->execute(array($order_number));
	foreach ($stmnt->fetchAll() as $row) {
		$service = $row['service'];
		$schedule = $row['schedule'];
		$address = $row['address'];
		$customer_email = $row['customer_email'];
		$customer_message = $row['message'];
		$customer_phone = $row['customer_phone'];
		$price = "$" . $row['cost'];
		$wage = $row['wage'];
	}



	if ($wage == "hour") {
		$price .= "/hr";
	}

	$name = "";
	$client_phone = "";
	$alerts = "";
	$stmnt = $db->prepare("SELECT firstname, phone, alerts, timezone FROM {$DB_PREFIX}login WHERE email = ?;");
	$stmnt->execute(array($email));
	foreach ($stmnt->fetchAll() as $row) {
		$name = ' ' . $row['firstname'];
		$client_phone = $row['phone'];
		$alerts = $row['alerts'];
		$tz = $row['timezone'];
	}

	$local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
	$local_date->setTimezone(new DateTimeZone($tz));

	send_email($email, "no-reply@helphog.com", "Claimed Task", get_claimed_email($customer_message, $service, $local_date->format("F j, Y, g:i a"), $address, $price, $customer_email, $customer_phone, $name, $duration));

	$sid = 'ACc66538a897dd4c177a17f4e9439854b5';
	$token = '18a458337ffdfd10617571e495314311';
	$client = new Client($sid, $token);
	$client_phone = '+1' . $client_phone;
	$client->messages->create($client_phone, array('from' => '+12532593451', 'body' => 'Please contact the customer immediately to follow up on their order. Here are the order details:

Customer Contact:
Email: ' . $customer_email . '
Phone: ' . $customer_phone . '

Order: ' . $order_number . '
Service: ' . $service . '
Date: ' . $local_date->format("F j, Y, g:i a") . '
Max duration: ' . $duration . '
Location: ' . $address . '
Pay: ' . $price . '

Message from Customer: ' . $customer_message));
}

function get_confirmation_email($order_number, $cost, $service, $name, $schedule, $customer_message, $address, $providers, $subtotal, $cancel_key, $provider)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%; align="center" border="0";">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;background-color:white;">
        																		<![endif]-->
                                            <div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Service Requested</div>        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ',</h2>
        			<p style="color:black">Your request for ' . $service . ' has been completed. Our respondent will contact you via email/text shortly.</p>
                    <p style="color:black">Quality of service is our priority, so if you are not satisfied with your service or have any questions, please reply to this email (support@helphog.com) so we can resolve any issues.</p>
              <br>
        			<p style="color:black"><span style="color: #1c2029;">Order Number:  </span>' . $order_number . '</p>
        			<p style="color:black"><span style="color: #1c2029;">Message:  </span>' . $customer_message . '</p>
                    <p style="color:black"><span style="color: #1c2029;">Service: </span>' . $service . '</p>
                    <p style="color:black"><span style="color: #1c2029;">Date: </span>' . $schedule . '</p>
                    <p style="color:black"><span style="color: #1c2029;">Address:  </span>' . $address . '</p>
                    <p style="color:black"><span style="color: #1c2029;">Providers:  </span>' . $providers . $provider . '</p>
                    <p style="color:black"><span style="color: #1c2029;">Subtotal:  </span>' . $subtotal . '</p>
                    <p style="color:black"><span style="color: #1c2029;">Maximum Cost:  </span>' . $cost . '</p>
                    </div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr><td style="border:none;border-radius:3px;color:white;cursor:auto;padding:15px 19px;" align="center" valign="middle" bgcolor="#e47d68"><a href="https://www.' . $SUBDOMAIN . 'helphog.com/cancel?ordernumber=' . $order_number . '&secret=' . $cancel_key . '" style="text-decoration:none;line-height:100%;color:white;font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:15px;font-weight:normal;text-transform:none;margin:0px;" target="_blank">
               Cancel Order
               </a></td></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>

        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
';
}

function get_signup_email($email, $firstname, $secret_key)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Get Verified on He!pHog</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hi ' . $firstname . ',</h2>
        			<p>This email account (' . $email . ') was used to create an account on helphog.com</p>
        			<p>If this was your request, please verify your account by clicking the following link:</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr><td style="border:none;border-radius:3px;color:white;cursor:auto;padding:15px 19px;" align="center" valign="middle" bgcolor="#1ecd97">
        			<a href="https://' . $SUBDOMAIN . 'helphog.com/php/verify.php?email=' . $email . '&secret=' . $secret_key . '" style="text-decoration:none;line-height:100%;color:white;font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:15px;font-weight:normal;text-transform:none;margin:0px;" target="_blank">
        			Verify Account
        			</a></td></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://' . $SUBDOMAIN . 'www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
';
}

function get_reset_email($email, $random_hash, $name)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Reset Your Password</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello ' . $name . ',</h2>
        			<p>The password for your account (' . $email . ') on helphog.com is attempting to be reset.</p>
        			<p>If this was your request, please reset your password using the link below:</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr><td style="border:none;border-radius:3px;color:white;cursor:auto;padding:15px 19px;" align="center" valign="middle" bgcolor="#1ecd97">
        			<a href="https://' . $SUBDOMAIN . 'helphog.com/reset?code=' . $random_hash . '"style="text-decoration:none;line-height:100%;color:white;font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:15px;font-weight:normal;text-transform:none;margin:0px;" target="_blank">
        			Reset Password
        			</a></td></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color:#1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a> HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
';
}

function get_claimed_email($customer_message, $service, $schedule, $address, $price, $customer_email, $customer_phone, $name, $duration)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(.../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Your Service Report</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ',</h2>
        			<p>Now that you\'ve confirmed your availablity to complete this task, please see the information below. Please contact the customer immediately and follow up on their order.</p><br>
        			<p><span style="color: #1c2029;">Message:  </span>' . $customer_message . '</p>
                    <p><span style="color: #1c2029;">Service: </span>' . $service . '</p>
                    <p><span style="color: #1c2029;">Date: </span>' . $schedule . '</p>
                    <p><span style="color: #1c2029;">Max Duration: </span>' . $duration . '</p>
                    <p><span style="color: #1c2029;">Location:  </span>' . $address . '</p>
                    <p><span style="color: #1c2029;">Salary:  </span>' . $price . '</p>
                    <p><span style="color: #1c2029;">Customer Phone:  </span>' . $customer_phone . '</p>
                    <p><span style="color: #1c2029;">Customer Email:  </span>' . $customer_email . '</p><br>
        			<p><span style="color: #1c2029;">To manage your order click <a href="https://' . $SUBDOMAIN . 'helphog.com/provider">here</a> </span></p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>

';
}

function get_claim_email($service, $schedule, $location, $client, $order_number, $price, $customer_message, $name, $duration, $secret_key)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Service Requested</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ',</h2>
        			<p>There was a service request in your area. To claim this job please check over the details and then click the claim task button below. Please ignore this email if you are unable to provide this service for whatever reason. Visit the <a href="https://' . $SUBDOMAIN . 'helphog.com/provider">provider portal</a> to modify your notification preferences.</p>
        			<p><span style="color: #1c2029;">Message:  </span>' . $customer_message . '</p>
                    <p><span style="color: #1c2029;">Service: </span>' . $service . '</p>
                    <p><span style="color: #1c2029;">Date: </span>' . $schedule . '</p>
                    <p><span style="color: #1c2029;">Max Duration: </span>' . $duration . '</p>
                    <p><span style="color: #1c2029;">Location:  </span>' . $location . '</p>
                    <p><span style="color: #1c2029;">Salary:  </span>' . $price . '</p>
               </div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr><td style="border:none;border-radius:3px;color:white;cursor:auto;padding:15px 19px;" align="center" valign="middle" bgcolor="#1ecd97">
               <a href="https://www.' . $SUBDOMAIN . 'helphog.com/php/accept.php?email=' . $client . '&ordernumber=' . $order_number . '&secret=' . $secret_key . '" style="text-decoration:none;line-height:100%;color:white;font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:15px;font-weight:normal;text-transform:none;margin:0px;" target="_blank">
               Claim Task
               </a></td></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>

        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
';
}

function get_address_email($to_send, $name)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Address Changed</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello ' . $name . ', </h2>
        			<p>The address for your account (' . $to_send . ') on helphog.com has been changed.</p>
        			<p>If this was not your request, please change your password immediately.</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
';
}

function get_cancel_email($name, $service, $order_number, $schedule)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color: white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p>Unfortunately, your order of ' . $service . ' (' . $order_number . ') on ' . $schedule . ' has been cancelled. Your provider has encountered extenuating circumstances, and will not be able to complete the service. You will be notified shortly if another provider picks up your order.</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
';
}

function provider_never_started($service, $order_number, $schedule, $name)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color: white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p>Unfortunately, your order of ' . $service . ' (' . $order_number . ') on ' . $schedule . ' has been cancelled. Your provider has not started working on your order. The refund will appear in your bank statement within 5-10 business days.</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
';
}

function noProviderFound($service, $order, $schedule, $name)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p>Unfortunately, your order of ' . $service . ' (' . $order . ') on ' .  $schedule . ' has been cancelled. The provider designated for your task has not been located. We apologize for the inconvenience this may have caused you and you will not be charged for this order. You can place another order if you\'re still seeking our services.</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
>';
}

function noPartnersFound($service, $order, $schedule, $name)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p>Other providers intended to work with you on ' . $service . ' (' . $order . ') on ' . $schedule . ' have not been found. The task has been terminated.</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
}

function partner_never_started($service, $order, $schedule, $name)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p>The primary provider for ' . $service . ' (' . $order . ') on ' . $schedule . ' has not started the task. The task has been terminated.</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
}

function customer_cancel($message, $name)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p>' . $message . '</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
';
}

function get_refund_email($name, $service, $order, $schedule)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Refunded</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p>The refund for your order of ' . $service . ' (' . $order . ') on ' . $schedule . ' has been issued. The refund will appear in your bank statement within 5-10 business days.</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
}

function sendNoChargeEmail($service, $order_number, $schedule, $name)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Refunded</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p> Due to the short duration of ' . $service . ' (' . $order_number . ') on ' . $schedule . ', you will receive a full refund. The refund will appear in your bank statement within 5-10 business days.</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
}

function get_notice_email($name, $message)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Notice</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p>' . $message . ' </p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://www.' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>
';
}

function get_dispute_email($name, $service, $schedule, $order)
{
	include 'constants.php';

	return '<body style="background: #F9F9F9;">
        	<div style="background-color:#F9F9F9;">
        	<div style="margin:0px auto;max-width:640px;background:transparent;">
        		<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0">
        		<tbody>
        			<tr>
        				<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 0px;">
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        						<tr>
        							<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        								<![endif]-->
        								<div style="max-width:640px;margin:0 auto;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden">
        									<div style="margin:0px auto;max-width:640px;background:#7289DA url(https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940) top center / cover no-repeat;">
        										<!--[if mso | IE]>
        										<v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
        											<![endif]-->
        											<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#7289DA url(../images/emaillogo.png) top center / cover no-repeat;" align="center" border="0" background="https://images.pexels.com/photos/688336/green-door-wood-entrance-688336.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940">
        												<tbody>
        													<tr>
        														<td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:57px;">
        															<!--[if mso | IE]>
        															<table role="presentation" border="0" cellpadding="0" cellspacing="0">
        																<tr style="background-color:white;">
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:black;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Disputed</div>
        																		<!--[if mso | IE]>
        																	</td>
        																</tr>
        															</table>
        															<![endif]-->
        														</td>
        													</tr>
        												</tbody>
        											</table>
        											<!--[if mso | IE]>
        										</v:textbox>
        										</v:rect>
        										<![endif]-->
        									</div>
        									<!--[if mso | IE]>
        							</td>
        						</tr>
        					</table>
        					<![endif]-->
        					<!--[if mso | IE]>
        					<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:#ffffff;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:40px 70px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px 0px 20px;" align="left"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:24px;text-align:left;">
        			<!--             <p><img src="" alt="" title="None" width="500" style="height: auto;"></p> -->
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello' . $name . ', </h2>
        			<p>Your completion ' . $service . ' (' . $order . ') on ' . $schedule . ' has been disputed by the customer. The customer either felt that the work was unsatisfactory, or that the additional expenditures added were unfair. Please contact the customer to resolve the issue, or us directly if you are not able to come to a resolution.</p>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr></tr></tbody></table></td></tr></tbody></table></div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--></div><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;"><div style="font-size:1px;line-height:12px;">&nbsp;</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0 auto;max-width:640px;background:#ffffff;box-shadow:0px 1px 5px rgba(0,0,0,0.1);border-radius:4px;overflow:hidden;"><table cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:#ffffff;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;font-size:0px;padding:0px;"><!--[if mso | IE]>
        			<table border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:30px 70px 0px 70px;" align="center"><div style="cursor:auto;color: #1ecd97;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:18px;line-height:16px;text-align:center;">If you have any other questions/concerns, please contact us:</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:14px 70px 30px 70px;" align="center"><div style="cursor:auto;color:#737F8D;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:16px;line-height:22px;text-align:center;">
        			(425) 640-3926 or support@helphog.com
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        			<!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="640" align="center" style="width:640px;">
        			<tr>
        			<td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
        			<![endif]--><div style="margin:0px auto;max-width:640px;background:transparent;"><table role="presentation" cellpadding="0" cellspacing="0" style="font-size:0px;width:100%;background:transparent;" align="center" border="0"><tbody><tr><td style="text-align:center;vertical-align:top;direction:ltr;font-size:0px;padding:20px 0px;"><!--[if mso | IE]>
        			<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:top;width:640px;">
        			<![endif]--><div aria-labelledby="mj-column-per-100" class="mj-column-per-100 outlook-group-fix" style="vertical-align:top;display:inline-block;direction:ltr;font-size:13px;text-align:left;width:100%;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0"><tbody><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			<a href="https://' . $SUBDOMAIN . 'www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://' . $SUBDOMAIN . 'helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
}

function get_receipt($name, $service, $order_number, $schedule, $description, $cost, $tax, $total, $providerId, $tax_rate)
{
	include 'constants.php';

	return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="x-apple-disable-message-reformatting" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <title></title>
    <style type="text/css" rel="stylesheet" media="all">
    /* Base ------------------------------ */

    @import url("https://fonts.googleapis.com/css?family=Nunito+Sans:400,700&display=swap");
    body {
      width: 100% !important;
      height: 100%;
      margin: 0;
      -webkit-text-size-adjust: none;
    }

    a {
      color: #3869D4;
    }

    a img {
      border: none;
    }

    td {
      word-break: break-word;
    }

    .preheader {
      display: none !important;
      visibility: hidden;
      mso-hide: all;
      font-size: 1px;
      line-height: 1px;
      max-height: 0;
      max-width: 0;
      opacity: 0;
      overflow: hidden;
    }
    /* Type ------------------------------ */

    body,
    td,
    th {
      font-family: "Nunito Sans", Helvetica, Arial, sans-serif;
    }

    h1 {
      margin-top: 0;
      color: #333333;
      font-size: 22px;
      font-weight: bold;
      text-align: left;
    }

    h2 {
      margin-top: 0;
      color: #333333;
      font-size: 16px;
      font-weight: bold;
      text-align: left;
    }

    h3 {
      margin-top: 0;
      color: #333333;
      font-size: 14px;
      font-weight: bold;
      text-align: left;
    }

    td,
    th {
      font-size: 16px;
    }

    p,
    ul,
    ol,
    blockquote {
      margin: .4em 0 1.1875em;
      font-size: 16px;
      line-height: 1.625;
    }

    p.sub {
      font-size: 13px;
    }
    /* Utilities ------------------------------ */

    .align-right {
      text-align: right;
    }

    .align-left {
      text-align: left;
    }

    .align-center {
      text-align: center;
    }
    /* Buttons ------------------------------ */

    .button {
      background-color: #3869D4;
      border-top: 10px solid #3869D4;
      border-right: 18px solid #3869D4;
      border-bottom: 10px solid #3869D4;
      border-left: 18px solid #3869D4;
      display: inline-block;
      color: #FFF;
      text-decoration: none;
      border-radius: 3px;
      box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16);
      -webkit-text-size-adjust: none;
      box-sizing: border-box;
    }

    .button--green {
      background-color: #22BC66;
      border-top: 10px solid #22BC66;
      border-right: 18px solid #22BC66;
      border-bottom: 10px solid #22BC66;
      border-left: 18px solid #22BC66;
    }

    .button--red {
      background-color: #FF6136;
      border-top: 10px solid #FF6136;
      border-right: 18px solid #FF6136;
      border-bottom: 10px solid #FF6136;
      border-left: 18px solid #FF6136;
    }

    @media only screen and (max-width: 500px) {
      .button {
        width: 100% !important;
        text-align: center !important;
      }
    }
    /* Attribute list ------------------------------ */

    .attributes {
      margin: 0 0 21px;
    }

    .attributes_content {
      background-color: #F4F4F7;
      padding: 16px;
    }

    .attributes_item {
      padding: 0;
    }
    /* Related Items ------------------------------ */

    .related {
      width: 100%;
      margin: 0;
      padding: 25px 0 0 0;
      -premailer-width: 100%;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
    }

    .related_item {
      padding: 10px 0;
      color: #CBCCCF;
      font-size: 15px;
      line-height: 18px;
    }

    .related_item-title {
      display: block;
      margin: .5em 0 0;
    }

    .related_item-thumb {
      display: block;
      padding-bottom: 10px;
    }

    .related_heading {
      border-top: 1px solid #CBCCCF;
      text-align: center;
      padding: 25px 0 10px;
    }
    /* Discount Code ------------------------------ */

    .discount {
      width: 100%;
      margin: 0;
      padding: 24px;
      -premailer-width: 100%;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
      background-color: #F4F4F7;
      border: 2px dashed #CBCCCF;
    }

    .discount_heading {
      text-align: center;
    }

    .discount_body {
      text-align: center;
      font-size: 15px;
    }
    /* Social Icons ------------------------------ */

    .social {
      width: auto;
    }

    .social td {
      padding: 0;
      width: auto;
    }

    .social_icon {
      height: 20px;
      margin: 0 8px 10px 8px;
      padding: 0;
    }
    /* Data table ------------------------------ */

    .purchase {
      width: 100%;
      margin: 0;
      padding: 35px 0;
      -premailer-width: 100%;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
    }

    .purchase_content {
      width: 100%;
      margin: 0;
      padding: 25px 0 0 0;
      -premailer-width: 100%;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
    }

    .purchase_item {
      padding: 10px 0;
      color: #51545E;
      font-size: 15px;
      line-height: 18px;
    }

    .purchase_heading {
      padding-bottom: 8px;
      border-bottom: 1px solid #EAEAEC;
    }

    .purchase_heading p {
      margin: 0;
      color: #85878E;
      font-size: 12px;
    }

    .purchase_footer {
      padding-top: 15px;
      border-top: 1px solid #EAEAEC;
    }

    .purchase_total {
      margin: 0;
      text-align: right;
      font-weight: bold;
      color: #333333;
    }

    .purchase_total--label {
      padding: 0 15px 0 0;
    }

    body {
      background-color: #F4F4F7;
      color: #51545E;
    }

    p {
      color: #51545E;
    }

    p.sub {
      color: #6B6E76;
    }

    .email-wrapper {
      width: 100%;
      margin: 0;
      padding: 0;
      -premailer-width: 100%;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
      background-color: #F4F4F7;
    }

    .email-content {
      width: 100%;
      margin: 0;
      padding: 0;
      -premailer-width: 100%;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
    }
    /* Masthead ----------------------- */

    .email-masthead {
      padding: 25px 0;
      text-align: center;
    }

    .email-masthead_logo {
      width: 94px;
    }

    .email-masthead_name {
      font-size: 16px;
      font-weight: bold;
      color: #A8AAAF;
      text-decoration: none;
      text-shadow: 0 1px 0 white;
    }
    /* Body ------------------------------ */

    .email-body {
      width: 100%;
      margin: 0;
      padding: 0;
      -premailer-width: 100%;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
      background-color: #FFFFFF;
    }

    .email-body_inner {
      width: 570px;
      margin: 0 auto;
      padding: 0;
      -premailer-width: 570px;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
      background-color: #FFFFFF;
    }

    .email-footer {
      width: 570px;
      margin: 0 auto;
      padding: 0;
      -premailer-width: 570px;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
      text-align: center;
    }

    .email-footer p {
      color: #6B6E76;
    }

    .body-action {
      width: 100%;
      margin: 30px auto;
      padding: 0;
      -premailer-width: 100%;
      -premailer-cellpadding: 0;
      -premailer-cellspacing: 0;
      text-align: center;
    }

    .body-sub {
      margin-top: 25px;
      padding-top: 25px;
      border-top: 1px solid #EAEAEC;
    }

    .content-cell {
      padding: 35px;
    }
    /*Media Queries ------------------------------ */

    @media only screen and (max-width: 600px) {
      .email-body_inner,
      .email-footer {
        width: 100% !important;
      }
    }

    @media (prefers-color-scheme: dark) {
      body,
      .email-body,
      .email-body_inner,
      .email-content,
      .email-wrapper,
      .email-masthead,
      .email-footer {
        background-color: #333333 !important;
        color: #FFF !important;
      }
      p,
      ul,
      ol,
      blockquote,
      h1,
      h2,
      h3,
      span,
      .purchase_item {
        color: #FFF !important;
      }
      .attributes_content,
      .discount {
        background-color: #222 !important;
      }
      .email-masthead_name {
        text-shadow: none !important;
      }
    }

    :root {
      color-scheme: light dark;
      supported-color-schemes: light dark;
    }
    </style>
    <!--[if mso]>
    <style type="text/css">
      .f-fallback  {
        font-family: Arial, sans-serif;
      }
    </style>
  <![endif]-->
  </head>
  <body>
    <span class="preheader">This is a receipt for your recent purchase on ' . $schedule . '. No payment is due with this receipt.</span>
    <table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
      <tr>
        <td align="center">
          <table class="email-content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
              <td class="email-masthead">
                <a href="https://' . $SUBDOMAIN . 'helphog.com/results?search=' . $service . '" class="f-fallback email-masthead_name">
                ' . $service . '
              </a>
              </td>
            </tr>
            <!-- Email Body -->
            <tr>
              <td class="email-body" width="100%" cellpadding="0" cellspacing="0">
                <table class="email-body_inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                  <!-- Body content -->
                  <tr>
                    <td class="content-cell">
                      <div class="f-fallback">
                        <h1>Hi' . $name . ',</h1>
                        <p>Thanks for using HelpHog. This email is the receipt for your purchase. No payment is due.</p>
                        <p>This purchase will appear as &quotHELPHOG - Service&quot on your bank statement.</p>

                        <table class="purchase" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                          <tr>
                            <td>
                              <h3>Order: ' . $order_number . '</h3></td>
                            <td>
                              <h3 class="align-right">' . $schedule . '</h3></td>
                          </tr>
                          <tr>
                            <td colspan="2">
                              <table class="purchase_content" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                  <th class="purchase_heading" align="left">
                                    <p class="f-fallback">Description</p>
                                  </th>
                                  <th class="purchase_heading" align="right">
                                    <p class="f-fallback">Amount</p>
                                  </th>
                                </tr>
                                ' . $service . '
                                <tr>
                                  <td width="80%" class="purchase_item"><span class="f-fallback">' . $description . ' </span></td>
                                  <td class="align-right" width="20%" class="purchase_item"><span class="f-fallback">$' . money_format('%.2n', $cost) . '</span></td>
                                </tr>
                                <tr>
                                 <td width="80%" class="purchase_item"><span class="f-fallback">Sales Tax (' . $tax_rate . '%)</span></td>
                                 <td class="align-right" width="20%" class="purchase_item"><span class="f-fallback">$' . money_format('%.2n', $tax) . '</span></td>
                                </tr>
                                <tr>
                                  <td width="80%" class="purchase_footer" valign="middle">
                                    <p class="f-fallback purchase_total purchase_total--label">Total</p>
                                  </td>
                                  <td width="20%" class="purchase_footer" valign="middle">
                                    <p class="f-fallback purchase_total">$' . money_format('%.2n', $total) . '</p>
                                  </td>
                                </tr>
                              </table>
                            </td>
                          </tr>
                        </table>
                        <p>For future orders with the same provider use #' . $providerId . ' at checkout.</p>
                        <p>If you have any questions about this receipt, simply reply to this email or reach out to our <a href="' . $SUBDOMAIN . 'helphog.com/contact">support team</a> for help.</p>
                        <p>Cheers,
                          <br>The HelpHog Team</p>
                        <!-- Action -->
                        <table class="body-action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                          <tr>
                            <td align="center">
                              <!-- Border based button
            https://litmus.com/blog/a-guide-to-bulletproof-buttons-in-email-design -->
                            </td>
                          </tr>
                        </table>
                        <!-- Sub copy -->

                      </div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td>
                <table class="email-footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                  <tr>
                    <td class="content-cell" align="center">
                      <p class="f-fallback sub align-center">&copy; 2021 HelpHog, LLC. All rights reserved.</p>
                      <p class="f-fallback sub align-center">
                        HelpHog, LLC
                        <br>19427 73rd Ave West.
                        <br>Lynnwood, WA 98036
                      </p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
';
}
