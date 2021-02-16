<?php

    require __DIR__ . '/stripe-php-master/init.php';
    // This is your real test secret API key.
    \Stripe\Stripe::setApiKey('sk_test_51H77jdJsNEOoWwBJR4lupAfmJ6ZLABBPCWvwiNqv99a9rr0mfhyNZ1L823ae56gIxJLUEZKDvXKepbCN1lIwPXp200KKA5Ni5p');
    require __DIR__ . '/twilio-php-master/src/Twilio/autoload.php';
    
    use Twilio\TwiML\MessagingResponse;
    use Twilio\Rest\Client;
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    
    require 'PHPMailer-master/src/Exception.php';
    require 'PHPMailer-master/src/PHPMailer.php';
    require 'PHPMailer-master/src/SMTP.php';

    ini_set('display_errors', 'On');
    error_reporting(E_ALL);
    error_reporting(E_ERROR | E_PARSE);
    
    function send_new_task_email($client, $price, $ordernumber, $duration, $secret_key, $tz, $schedule, $tzoffset, $address, $city, $state, $zip, $service, $message) {
        $db = establish_database();
        $name = "";
        $alerts = "";
        $stmnt = $db->prepare("SELECT firstname, alerts FROM login WHERE email = ?;");
        $stmnt->execute(array($client));
        foreach($stmnt->fetchAll() as $row) {
            $name = $row['firstname'];
            $alerts = $row['alerts'];
        }
        
        if ($alerts == "email" || $alerts == "both"){
        
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
            $mail->setFrom('no-reply@helphog.com', 'HelpHog');
            $mail->addAddress($client, 'To');
            
            $local_time = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone($tzoffset));
            $local_time->setTimezone(new DateTimeZone($tz));
            
            $mail->Subject = "HelpHog - New Task Available";
            
            if ($address == "Remote (online)"){
                $location = $address;
            } else {
                $location = ucfirst($city). ', ' . $state . ' ' . $zip;
            }
            $mail->Body    = get_claim_email($service, $local_time->format("F j, Y, g:i a"), $location , $client, $ordernumber, $price, $message, $name, $duration, $secret_key);
            $mail->IsHTML(true); 
            
            $mail->send();
            $mail->ClearAllRecipients();
        }
    }
    
    function send_new_task_text($phonenumber, $email, $ordernumber, $price, $message, $duration, $secret_key, $tz, $people, $schedule, $tzoffset, $address, $city, $state, $zip, $service) {
    
        $db = establish_database();
        
        $alerts="";
        $stmnt = $db->prepare("SELECT alerts FROM login WHERE email = ?;");
        $stmnt->execute(array($email));
        foreach($stmnt->fetchAll() as $row) {
            $alerts = $row['alerts'];
        }
        
        $local_time = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone($tzoffset));
        $local_time->setTimezone(new DateTimeZone($tz));
        $t = time();
        
        if ($address == "Remote (online)"){
            $location = $address;
            $commute = "";
        }else{
            $location = ucfirst($city) . ', ' . $state;
            $address = str_replace(' ', '+', $address . '+' . $city . '+' . $state . '+' . $zip);
            if (strtotime($schedule) - 3600000 < $t){
                $departureTime = $t;
            }else{
                $departureTime = $departureTime - 3600000;  
            }
            $matrix = address_works_for_provider($address, $email, $departureTime);
            $commute = "Estimated commute: " . ceil(($matrix -> traffic)/60) . " minutes";
        }
        
        $partners = "";
        
        if ($people > 1){
            $partners = "Task requires cordinating with " . ($people - 1) . " other provider(s)";
        }
        
        if ($alerts == "sms" || $alerts == "both"){
    
            $sid = 'ACc66538a897dd4c177a17f4e9439854b5';
            $token = '18a458337ffdfd10617571e495314311';
            $client = new Client($sid, $token);
            $client->messages->create('+1' . $phonenumber, array('from' => '+12532593451', 'body' => 'There\'s a new service request in your area!

Service: ' . $service . '
Order Number: ' . $ordernumber . '
Date: ' . $local_time->format("F j, Y, g:i a") . '
Max duration: ' . $duration . '
' . $commute . '
Location: ' . $location . '
Pay: ' . $price . '
' . $partners . '

Message: ' . $message . '

Tap on the following link to obtain this job:

https://helphog.com/php/accept.php?email=' . $email . '&ordernumber=' . $ordernumber . '&secret=' . $secret_key));
        }
    }

    
    function cancel_order() {
        $name = "";
        $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
        $stmnt->execute(array($customer_email));
        foreach($stmnt->fetchAll() as $row) {
            $name = $row['firstname'];
        }
        
        $sql = "UPDATE orders SET client_email = ?, secondary_providers = ?, status = ? WHERE order_number = ?;";
        $stmt = $db->prepare($sql);
        $params = array("", "", "ac", $order_number);
        $stmt->execute($params);
        
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
        $mail->setFrom('admin@helphog.com', 'HelpHog');
        $mail->addAddress($customer_email, 'To');
        $mail->IsHTML(true); 
        
        $mail->Subject = "HelpHog - Task Cancelled";
        $mail->Body    = noProviderFound($service, $order_number);
        $mail->send();
    }
    
    function minutes_until($time) {
        $then = new DateTime(date('Y-m-d H:i:s', strtotime($time)), new DateTimeZone('UTC'));
        $now = new DateTime(gmdate('Y-m-d H:i:s'));
        
        $diff = strtotime($then->format('Y-m-d H:i:s')) - strtotime($now->format('Y-m-d H:i:s'));
        
        return $diff / 60;
    }
    
    function minutes_since($time) {
        $then = new DateTime(date('Y-m-d H:i:s', strtotime($time)), new DateTimeZone('UTC'));
        $now = new DateTime(gmdate('Y-m-d H:i:s'));
        
        $diff = strtotime($now->format('Y-m-d H:i:s')) - strtotime($then->format('Y-m-d H:i:s'));
        
        return $diff / 60;
    }
    
    
    function validate_customer($order, $session) {
        $db = establish_database();
        
        $customer_email = "";
        $stmnt = $db->prepare("SELECT customer_email FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order)); 
        foreach($stmnt->fetchAll() as $row) {
            $customer_email = $row['customer_email'];
        }
        
        $stmnt = $db->prepare("SELECT session FROM login WHERE email = ?;");
        $stmnt->execute(array($customer_email)); 
        foreach($stmnt->fetchAll() as $row) {
            if (hash_equals($row['session'], $session)) {
                return true;
            }
        }
        
        return false;
    }
    
    function validate_customer_phone($order, $phone) {
        $db = establish_database();
        
        $customer_phone = "";
        $stmnt = $db->prepare("SELECT customer_phone FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order)); 
        foreach($stmnt->fetchAll() as $row) {
            if (hash_equals($row['customer_phone'], $phone)) {
                return true;
            }
        }
        
        return false;
        
    }
    
    function address_works_for_provider($address, $email, $orderTime) {

        $db = establish_database();
        $distanceMatrix = new \stdClass();
        $stmnt = $db->prepare("SELECT work_address, work_state, work_city, work_zip, radius FROM login WHERE email = ?;");
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
    
    function validate_provider_email($order, $email) {
        $db = establish_database();
        
        $stmnt = $db->prepare("SELECT client_email FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order)); 
        foreach($stmnt->fetchAll() as $row) {
            if (hash_equals($row['client_email'], $email)) {
                return true;
            }
        }
        
        return false;
        
    }
    
    function validate_provider($order, $session) {
        $db = establish_database();
        
        $client_email = "";
        $stmnt = $db->prepare("SELECT client_email, secondary_providers FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order)); 
        foreach($stmnt->fetchAll() as $row) {
            $client_email = $row['client_email'];
            $secondary_providers = $row['secondary_providers'];
            
            $all_providers = array();
            if ($client_email != "") {
                array_push($all_providers, $client_email);
            }
            
            $secondary_providers_array = explode(',', $secondary_providers);
            foreach($secondary_providers_array as $secondary_provider) {
                if ($secondary_provider != "") {
                    array_push($all_providers, $secondary_provider);
                }
            }
            
        }
        
        foreach ($all_providers as $provider) {
            $stmnt = $db->prepare("SELECT session FROM login WHERE email = ?;");
            $stmnt->execute(array($provider)); 
            foreach($stmnt->fetchAll() as $row) {
                if (hash_equals($row['session'], $session)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    function validate_user($email, $session) {
        $customer_email = "";
        $stmnt = $db->prepare("SELECT session FROM login WHERE email = ?;");
        $stmnt->execute(array($email)); 
        foreach($stmnt->fetchAll() as $row) {
            if (hash_equals($row['session'], $session)) {
                return true;
            }
        }
        
        return false;
    }
    
    function send_new_applicant_email($firstname, $email, $workfield, $experience, $phone) {
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
        $mail->setFrom('admin@helphog.com', 'HelpHog');
        $mail->addAddress("admin@helphog.com", 'To');

        $message = 'Name: ' . $firstname . "\n\n";
        $message .= 'Phone: ' . $phone . "\n\n";
        $message .= 'Email: ' . $email . "\n\n";
        $message .= 'Workfield: ' . $workfield . "\n\n";
        $message .= 'Experience: ' . $experience . "\n\n";
        
        $mail->Subject = "Help - New Applicant";
        $mail->Body    = $message;
        $mail->send();
    }
    
    function send_verification_email($firstname, $email, $secret_key) {
        
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
        $mail->setFrom('no-reply@helphog.com', 'HelpHog');
        $mail->addAddress($email, 'To');
        
        $mail->Subject = "HelpHog - Account Confirmation";
        $mail->Body    = get_signup_email($email, $firstname, $secret_key);
        $mail->IsHTML(true);
        
        $mail->send();
        
        $mail->ClearAllRecipients();
    }
    
    function &validate_form($firstname, $lastname, $email, $password, $zip, $confirm, $phone) {
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
        $result = $db->query("SELECT email FROM login;");
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
        $result = $db->query("SELECT phone FROM login;");
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
    
    function pause_order($order) {
        
        $db = establish_database();
        
        $time = gmdate('y-m-d H:i:s');
        $sql = "UPDATE orders SET pause = ?, currently_paused = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array($time, 'y', $order);
        $stmt->execute($params);
    }
    
    function resume_order($order) {
        $db = establish_database();
        
        $time = gmdate('y-m-d H:i:s');
        $sql = "UPDATE orders SET resume = ?, currently_paused = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array($time, 'n', $order);
        $stmt->execute($params);
        
        $start_actual = "";
        $end_actual = "";
        $stmnt = $db->prepare("SELECT pause, resume FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $start_actual = $row['pause'];
            $end_actual = $row['resume'];
        }
        
        $ts1 = strtotime($start_actual);
        $ts2 = strtotime($end_actual);
        $seconds_diff = $ts2 - $ts1;
        
        $oldtimeactual = "";
        $stmnt = $db->prepare("SELECT paused_time FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $oldtimeactual = $row['paused_time'];
        }
        
        $new_time = $seconds_diff + $oldtimeactual;
        
        $sql = "UPDATE orders SET paused_time = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array($new_time, $order);
        $stmt->execute($params);
        
    }
    
    function start_stop_order($order) {
        
        $db = establish_database();
        $time = gmdate('y-m-d H:i:s');
        
        $status = "";
        $stmnt = $db->prepare("SELECT status FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $status = $row['status'];
        }
        
        $sql = "";
        if ($status == "st") {
            $sql = "UPDATE orders SET end = ?, status = 'en' WHERE order_number = ?";
        } else if ($status == "cl") {
            $sql = "UPDATE orders SET start = ?, status = 'st' WHERE order_number = ?";
        }
        
        if ($sql != "") {
            $stmt = $db->prepare($sql);
            $params = array($time, $order);
            $stmt->execute($params);
            return true;
        } else {
            return false;
        }
    }
    
    function mark_completed($order, $message) {
        $db = establish_database();
        
        $service = "";
        $customer_email = "";
        $customer_phone = "";
        $disputes = 0;
        $stmnt = $db->prepare("SELECT * FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $customer_email = $row['customer_email'];
            $customer_phone = $row['customer_phone'];
            $disputes = $row['disputes'];
        }
        
        if ($disputes < 3) {
            $name = "";
            $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
            $stmnt->execute(array($customer_email));
            foreach($stmnt->fetchAll() as $row) {
                $name = $row['firstname'];
            }
            
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
            $mail->setFrom('no-reply@helphog.com', 'HelpHog');
            $mail->addAddress($customer_email, 'To');
            
            $mail->Subject = "HelpHog - Task Completed";
            $mail->Body    = get_marked_completed_email($name, $service);
            $mail->IsHTML(true);
            
            $mail->send();
            
            $mail->ClearAllRecipients();
            
            if ($message != "") {
                $sid = 'ACc66538a897dd4c177a17f4e9439854b5';
                $token = '18a458337ffdfd10617571e495314311';
                $client = new Client($sid, $token);
                $client->messages->create('+1' . $customer_phone, array('from' => '+12532593451', 'body' => $message));
            }
            
            
            $sql = "UPDATE orders SET status = ? WHERE order_number = ?";
            $stmt = $db->prepare($sql);
            $params = array('mc', $order);
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
    function claim_order($email, $order_number, $accept_key) {
        
        $db = establish_database();
        
        $clicked = '';
        $found = false;
        $cancelled = true;
        $wage = '';
        $db_duration;
        $stmnt = $db->prepare("SELECT wage, duration, accept_key, status, clicked FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order_number));
        foreach($stmnt->fetchAll() as $row) {
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
        
        $sql = "UPDATE orders SET clicked = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array($new_clicked, $order_number);
        $stmt->execute($params);
        
        if ($found && !$cancelled) {
        
            if ($wage == "hour" ){
                $duration = $duration . " hour(s)";
            } else {
                $duration = "No time limit";
            }
            
            $client_email = "";
            $stmnt = $db->prepare("SELECT client_email FROM orders WHERE order_number = ?;");
            $stmnt->execute(array($order_number));
            foreach($stmnt->fetchAll() as $row) {
                $client_email = $row['client_email'];
            }
            
            $client_phone = "";
            $stmnt = $db->prepare("SELECT phone FROM login WHERE email = ?;");
            $stmnt->execute(array($client_email));
            foreach($stmnt->fetchAll() as $row) {
                $client_phone = $row['phone'];
            }
            
            $first_provider = ($client_email == "");
            
            $people = 1;
            $stmnt = $db->prepare("SELECT people FROM orders WHERE order_number = ?;");
            $stmnt->execute(array($order_number));
            foreach($stmnt->fetchAll() as $row) {
                $people = $row['people'];
            }
            
            if ($email == $client_email) {
                return '<script>window.location.href = "https://helphog.com/decline";</script>';
            }
            
            if (!$first_provider) {
                
                if ($people == 1) {
                    return '<script>window.location.href = "https://helphog.com/decline";</script>';
                } else {
                    
                    $secondary_providers = "";
                    $stmnt = $db->prepare("SELECT secondary_providers FROM orders WHERE order_number = ?;");
                    $stmnt->execute(array($order_number));
                    foreach($stmnt->fetchAll() as $row) {
                        $secondary_providers = $row['secondary_providers'];
                    }
                    
                    if (strpos($secondary_providers, $email) !== false) {
                        return '<script>window.location.href = "https://helphog.com/decline";</script>';
                    }
                    
                    $num_secondary;
                    if ($secondary_providers == "") {
                        $num_secondary = 0;
                    } else {
                        $num_secondary = count(explode(',', $secondary_providers));
                    }
                    
                    if ($num_secondary + 1 >= $people) {
                        return '<script>window.location.href = "https://helphog.com/decline";</script>';
                    } else {
                        
                        $new_secondary;
                        if ($secondary_providers == "") {
                            $new_secondary = $email;
                        } else {
                            $new_secondary = $secondary_providers . ',' . $email;
                        }
                        
                        if ($num_secondary + 2 == $people) { // plus 1 for primary, and 1 for person currently claiming
                        
                            $sql = "UPDATE orders SET status = ? WHERE order_number = ?";
                            $stmt = $db->prepare($sql);
                            $params = array("cl", $order_number);
                            $stmt->execute($params);
                        
                            $secondary_providers_array = explode(',', $new_secondary);
                            $message = "Here are the providers you will be working with:\n";
                            foreach ($secondary_providers_array as $curr_email) {
                                
                                $name = "";
                                $phone = "";
                                $stmnt = $db->prepare("SELECT firstname, phone FROM login WHERE email = ?;");
                                $stmnt->execute(array($curr_email));
                                foreach($stmnt->fetchAll() as $row) {
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
                        
                        $sql = "UPDATE orders SET secondary_providers = ?  WHERE order_number = ?";
                        $stmt = $db->prepare($sql);
                        $params = array($new_secondary, $order_number);
                        $stmt->execute($params);
                        
                        send_claimed_notification($order_number, $email, "secondary", $db, $duration);
                        return '<script>window.location.href = "https://helphog.com/claimedsecondary";</script>';
                    }
                }
                
            } else { // first provider
            
                if ($people == 1) {
                    $sql = "UPDATE orders SET status = ? WHERE order_number = ?";
                    $stmt = $db->prepare($sql);
                    $params = array("cl", $order_number);
                    $stmt->execute($params);
                }
                
                $sql = "UPDATE orders SET client_email = ? WHERE order_number = ?";
                $stmt = $db->prepare($sql);
                $params = array($email, $order_number);
                $stmt->execute($params);
                
                send_claimed_notification($order_number, $email, "primary", $db, $duration);
                return '<script>window.location.href = "https://helphog.com/claimed";</script>';
            }
        }
        
        return '<script>window.location.href = "https://helphog.com/decline";</script>';
        
    }
    
    function dispute_order($order_number) {
        $db = establish_database();
    
        $service = "";
        $client_email = "";
        $end_time = "";
        $secondary_providers = "";
        $customer_email = "";
        $customer_phone = "";
        $been_disputed = "";
        $order_disputes = 0;
        $stmnt = $db->prepare("SELECT * FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order_number));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $client_email = $row['client_email'];
            $customer_email = $row['customer_email'];
            $end_time = $row['end'];
            $secondary_providers = $row['secondary_providers'];
            $customer_phone = $row['customer_phone'];
            $been_disputed = $row['been_disputed'];
            $order_disputes = $row['disputes'];
        }
        
        if (minutes_since($end_time) <= 1440) {
        
            $email_found = false;
            $disputes = 0;
            $result = $db->query("SELECT email, disputes FROM login;");
            foreach ($result as $row) {
                if ($customer_email == $row['email']) {
                    $email_found = true;
                    $disputes = $row['disputes'];
                }
            }
            
            $table = "login";
            if (!$email_found){
                $result = $db->query("SELECT phone, disputes FROM guests;");
                foreach ($result as $row) {
                    if ($customer_phone == $row['phone']) {
                        $disputes = $row['disputes'];
                    }
                }
                $table = "guests";
            }
            
            if ($been_disputed == 'n') {
                
                $disputes += 1;
                $sql = "UPDATE " . $table . " SET disputes = ? WHERE phone = ?";
                $stmt = $db->prepare($sql);
                $params = array($disputes, $customer_phone);
                $stmt->execute($params);
            
                //banning
                $num_orders = 0;
                $orders = $db->query("SELECT * FROM orders;");
                foreach ($orders as $order) {
                    if ($order["customer_phone"] == $customer_phone){
                        $num_orders++;
                    }
                }
                
                if ($num_orders >= 4){
                    // bans users if more that 50% of their orders are disputed
                    if ($disputes / $num_orders > 0.5) {
                        if ($email_found){
                            $sql = "UPDATE login SET banned = ? WHERE email = ?";
                            $stmt = $db->prepare($sql);
                            $params = array('y', $customer_email);
                            $stmt->execute($params);
                        } else {
                            $sql = "UPDATE guests SET banned = ? WHERE phone = ?";
                            $stmt = $db->prepare($sql);
                            $params = array('y', $customer_phone);
                            $stmt->execute($params);
                        }
                    }
                }
                
            }
            
            $order_disputes += 1;
            $sql = "UPDATE orders SET status = ?, been_disputed = 'y', disputes = ? WHERE order_number = ?";
            $stmt = $db->prepare($sql);
            $params = array('di', $order_disputes, $order_number);
            $stmt->execute($params);
            
            if ($order_disputes == 3) {
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
                $mail->setFrom('admin@helphog.com', 'HelpHog');
                $mail->addAddress("admin@helphog.com", 'To');
        
                $message = 'Order Number: ' . $order_number;
                
                $mail->Subject = "HelpHog - Mediation Required";
                $mail->Body    = $message;
                $mail->send();
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
                send_dispute_email($all_emails[$i], $service, $db);
            }

            return true;
        } else {
            return false;
        }
        
    }
    
    function send_dispute_email($client_email, $service, $db) {
    
        $name = "";
        $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
        $stmnt->execute(array($client_email));
        foreach($stmnt->fetchAll() as $row) {
            $name = $row['firstname'];
        }
        
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
        $mail->setFrom('no-reply@helphog.com', 'HelpHog');
        $mail->addAddress($client_email, 'To');
        
        $mail->Subject = "HelpHog - Task Disputed";
        $mail->Body    = get_dispute_email($name, $service);
        $mail->IsHTML(true);
        $mail->send();
        $mail->ClearAllRecipients();
    }


    function &establish_database() {
        $host = 'localhost';
        $dbname = 'regiuzkk_help';
        $user = 'regiuzkk_help';
        $password = '3ZY1v^}T,9]b';
        $ds = "mysql:host={$host};dbname={$dbname};charset=utf8";
    
        try {
            $db = new PDO($ds, $user, $password);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch (PDOException $ex) {
            header("Content-type: text/plain");
            print "Can not connect to the database. Please try again later.\n";
            print "Error details: $ex \n";
            die();
        }
        return $db;
    }
    
    function check_session($post_session) {
        $db = establish_database();
        $found = false;
        if ($post_session != "") {
            $sessions = $db->query("SELECT session FROM login;");
            foreach ($sessions as $session) {
                if (hash_equals($post_session, $session[0])) {
                    $found = true;
                }
            }
        }
        return $found;
    }
    
    function get_order_status($order) {
        $db = establish_database();
        $order_status = "nf";
        $result = $db->query("SELECT order_number FROM orders;");
        $found = false;
        foreach ($result as $row) {
            if ($order === $row['order_number']) {
                $found = true;
            }
        }
        if ($found) {
            $stmnt = $db->prepare("SELECT status FROM orders WHERE order_number = ?;");
            $stmnt->execute(array($order));
            foreach($stmnt->fetchAll() as $row) {
                $order_status = $row['status'];
            }
        }
        return $order_status;
    }
    
    function &payment($order) {
        $db = establish_database();
        $result = new \stdClass();
        $stmnt = $db->prepare("SELECT * FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            
            $entry->wage = $row['wage'];
            $entry->duration = $row['duration'];
            $entry->intent = $row['intent'];
            $earnings;
            if ($row["wage"] == "hour") {
                $ts1 = strtotime($row["start"]);
                $ts2 = strtotime($row["end"]);
                $seconds_diff = $ts2 - $ts1;
                $seconds_diff -= $row["paused_time"];
                $time = ($seconds_diff / 3600);
                $entry->worked_time == $time;
                $earnings = $time * $row["cost"];
                $entry->maxWithdrawl = $row["duration"] * $row["cost"] * $row["people"];
            } else {
                $earnings = $row["cost"];
                $entry->maxWithdrawl = $row["cost"] * $row["people"];
            }
            $revenue_actual = round($earnings, 2);
            $entry->revenue = $revenue_actual;
            
            if($row["prorated"] == "n" && $time < 1 && $row["wage"] == "hour"){
                $entry->customerPayment = $row["cost"] * $row["people"];
                $entry->providerPayout = $row["cost"] * 0.9;
            }else{
                $entry->customerPayment = $revenue_actual * $row["people"];
                $entry->providerPayout = $revenue_actual * 0.9;
            }
            
            if ($entry->customerPayment > $entry->maxWithdrawl){
                $entry->customerPayment = $entry->maxWithdrawl;
            }
        }
        return $entry;
    }
    
    function send_claimed_notification($order_number, $email, $type, $db, $duration) {
    
        $wage = "";
        $customer_message = "";
        $service = "";
        $schedule = "";
        $address = "";
        $price = "";
        $customer_email = "";
        $customer_phone = "";
        $client_phone = "";
        $stmnt = $db->prepare("SELECT service, schedule, address, customer_email, message, customer_phone, cost, wage FROM orders WHERE order_number = ?;");
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
            $price.= "/hr";
        }
        
        $name = "";
        $client_phone = "";
        $alerts = "";
        $stmnt = $db->prepare("SELECT firstname, phone, alerts, timezone FROM login WHERE email = ?;");
        $stmnt->execute(array($email));
        foreach($stmnt->fetchAll() as $row) {
            $name = $row['firstname'];
            $client_phone = $row['phone'];
            $alerts = $row['alerts'];
            $tz = $row['timezone'];
        }
        
        $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
        $local_date->setTimezone(new DateTimeZone($tz));
        
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
        $mail->setFrom('no-reply@helphog.com', 'HelpHog');
        $mail->addAddress($email, 'To');
        
        $mail->Subject = "HelpHog - Claimed Task";
        $mail->Body    = get_claimed_email($customer_message, $service, $local_date->format("F j, Y, g:i a"), $address, $price, $customer_email, $customer_phone, $name, $duration);
        $mail->IsHTML(true);
        
        $mail->send();
        $mail->ClearAllRecipients();
        

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
    
    function get_confirmation_email($order_number, $cost, $service, $name, $schedule, $customer_message, $address, $providers, $subtotal, $cancel_key) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
                                            <div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Service Requested</div>        																		<!--[if mso | IE]>
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
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello '. $name .',</h2>
        			<p>Your request for ' . $service . ' has been completed. Our respondent will contact you via email/text shortly.</p>
                    <p>Quality of service is our priority, so if you are not satisfied with your service or have any questions, please reply to this email (support@helphog.com) so we can resolve any issues.</p>
              <br>
        			<p><span style="color: #1c2029;">Order Number:  </span>' . $order_number . '</p>
        			<p><span style="color: #1c2029;">Message:  </span>' . $customer_message . '</p>
                    <p><span style="color: #1c2029;">Service: </span>' . $service . '</p>
                    <p><span style="color: #1c2029;">Date: </span>' . $schedule . '</p>
                    <p><span style="color: #1c2029;">Address:  </span>' . $address . '</p>
                    <p><span style="color: #1c2029;">Providers:  </span>' . $providers . '</p>
                    <p><span style="color: #1c2029;">Subtotal:  </span>' . $subtotal. '</p>
                    <p><span style="color: #1c2029;">Maximum Cost:  </span>' . $cost . '</p>
                    </div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr><td style="border:none;border-radius:3px;color:white;cursor:auto;padding:15px 19px;" align="center" valign="middle" bgcolor="#e47d68"><a href="https://www.helphog.com/cancel?ordernumber=' . $order_number . '&secret=' . $cancel_key .'" style="text-decoration:none;line-height:100%;color:white;font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:15px;font-weight:normal;text-transform:none;margin:0px;" target="_blank">
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function get_signup_email($email, $firstname, $secret_key) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Get Verified on He!pHog</div>
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
        			<a href="https://helphog.com/php/verify.php?email=' . $email . '&secret=' . $secret_key . '" style="text-decoration:none;line-height:100%;color:white;font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:15px;font-weight:normal;text-transform:none;margin:0px;" target="_blank">
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function get_reset_email($email, $random_hash, $name) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Reset Your Password</div>
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
        			<a href="https://helphog.com/reset?code=' . $random_hash . '"style="text-decoration:none;line-height:100%;color:white;font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:15px;font-weight:normal;text-transform:none;margin:0px;" target="_blank">
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a> HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function get_claimed_email($customer_message, $service, $schedule, $address, $price, $customer_email, $customer_phone, $name, $duration) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Your Service Report</div>
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
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello '. $name . ',</h2>
        			<p>Now that you\'ve confirmed your availablity to complete this task, please see the information below. Please contact the customer immediately and follow up on their order.</p><br>
        			<p><span style="color: #1c2029;">Message:  </span>' . $customer_message . '</p>
                    <p><span style="color: #1c2029;">Service: </span>' . $service . '</p>
                    <p><span style="color: #1c2029;">Date: </span>' . $schedule . '</p>
                    <p><span style="color: #1c2029;">Max Duration: </span>' . $duration . '</p>
                    <p><span style="color: #1c2029;">Location:  </span>' . $address . '</p>
                    <p><span style="color: #1c2029;">Salary:  </span>' . $price . '</p>
                    <p><span style="color: #1c2029;">Customer Phone:  </span>' . $customer_phone . '</p>
                    <p><span style="color: #1c2029;">Customer Email:  </span>' . $customer_email . '</p><br>
        			<p><span style="color: #1c2029;">To manage your order click <a href="https://helphog.com/provider">here</a> </span></p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function get_claim_email($service, $schedule, $location, $client, $order_number, $price, $customer_message, $name, $duration, $secret_key) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Service Requested</div>
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
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello '. $name .',</h2>
        			<p>There was a service request in your area. To claim this job please check over the details and then click the claim task button below.</p>
        			<p><span style="color: #1c2029;">Message:  </span>' . $customer_message . '</p>
                    <p><span style="color: #1c2029;">Service: </span>' . $service . '</p>
                    <p><span style="color: #1c2029;">Date: </span>' . $schedule . '</p>
                    <p><span style="color: #1c2029;">Max Duration: </span>' . $duration . '</p>
                    <p><span style="color: #1c2029;">Location:  </span>' . $location . '</p>
                    <p><span style="color: #1c2029;">Salary:  </span>' . $price . '</p>
               </div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:10px 25px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;" align="center" border="0"><tbody><tr><td style="border:none;border-radius:3px;color:white;cursor:auto;padding:15px 19px;" align="center" valign="middle" bgcolor="#1ecd97">
               <a href="https://www.helphog.com/php/accept.php?email=' . $client . '&ordernumber=' . $order_number . '&secret=' . $secret_key . '" style="text-decoration:none;line-height:100%;color:white;font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:15px;font-weight:normal;text-transform:none;margin:0px;" target="_blank">
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function get_address_email($to_send, $name) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Address Changed</div>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function get_cancel_email($name, $service) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
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
        			<p>Unfortunately, your order of ' . $service .' has been cancelled. Your provider has encountered extenuating circumstances, and will not be able to complete the service. You will be notified shortly if another provider picks up your order.</p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function noProviderFound($service, $order, $schedule) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
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
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello, </h2>
        			<p>Unfortunately, your order of ' . $service .' ('. $order . ') on ' .  $schedule . ' has been cancelled. The provider designated for your task has not been located. We apologize for the inconvenience this may have caused you and you will not be charged for this order. You can place another order if you\'re still seeking our services.</p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function noPartnersFound($service, $order, $schedule) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
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
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello, </h2>
        			<p>Other providers intended to work with you on ' . $service .' ('. $order . ') on ' . $schedule . ' have not been found. The task has been terminated.</p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
    }
    
    function customer_cancel($message, $name) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Cancelled</div>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function get_refund_email($name, $service) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Refunded</div>
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
        			<p>The refund for your order of ' . $service .' has been issued. The refund will appear in your bank statement within 5-10 business days.</p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
    }
    
    function sendNoChargeEmail($service, $order_number, $schedule) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Refunded</div>
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
        			<h2 style="font-family: Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-weight: 500;font-size: 20px;color: #4F545C;letter-spacing: 0.27px;">Hello, </h2>
        			<p> Due to the short duration of ' . $service .' (' . $order_number . ') on ' . $schedule . ', you will receive a full refund. The refund will appear in your bank statement within 5-10 business days.</p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
    }
    
    function get_notice_email($name, $message) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Notice</div>
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
        			<p>' . $message .' </p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
    
    function get_completed_email($service, $name) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Completed</div>
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
        			<p>Your completion of ' . $service .' has been verified by the customer. You should receive payment shortly.</p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
    }
    
    function get_dispute_email($name, $service) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Disputed</div>
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
        			<p>Your completion ' . $service .' has been disputed by the customer. The customer either felt that the work was unsatisfactory, or that the additional expenditures added were unfair. Please contact the customer to resolve the issue, or us directly if you are not able to come to a resolution.</p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
        			</div></td></tr><tr><td style="word-break:break-word;font-size:0px;padding:0px;" align="center"><div style="cursor:auto;color:#99AAB5;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:12px;line-height:24px;text-align:center;">
        			</div></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]--></td></tr></tbody></table></div><!--[if mso | IE]>
        			</td></tr></table>
        			<![endif]-->
        	</div>
        </body>';
    }
    
    function get_marked_completed_email($name, $service) {
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
        																<tr>
        																	<td style="vertical-align:undefined;width:640px;">
        																		<![endif]-->
        																		<div style="cursor:auto;color:transparent;font-family:Whitney, Helvetica Neue, Helvetica, Arial, Lucida Grande, sans-serif;font-size:36px;font-weight:600;line-height:36px;text-align:center;">Task Completed</div>
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
        			<p>Your order of ' . $service .' has been completed by the provider. Please visit the orders page on our website/app to verify the correct price and rate the service.</p>
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
        			<a href="https://www.helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank">Website </a>HelpHog LLC<a href="https://helphog.com/" style="color:#1EB0F4;text-decoration:none;" target="_blank"></a>
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
?>