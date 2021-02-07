<?php

include 'common.php';

$db = establish_database();
$response = new \stdClass();
$session = "";
$validated = false;
$params = array();

if (isset($_POST['tz'])) {
    
    $tz = trim($_POST['tz']);
    
    if (isset($_POST["email"]) && isset($_POST["password"])) {
        
        $email_error = "";
        $password_error = "";
        
        
        $email = trim($_POST["email"]);
        $found = false;
        $result = $db->query("SELECT email FROM login WHERE type='Business';");
        foreach ($result as $row) {
            if ($email === $row['email']) {
                $found = true;
            }
        }
        
        if ($email == "") {
            $email_error = "empty";
        } else if (!$found) {
            $email_error = "true";
        } else {
            
            $password = trim($_POST["password"]);
            
            $found = false;
            $stmnt = $db->prepare("SELECT password FROM login WHERE email = ?;");
            $stmnt->execute(array($email));
            foreach($stmnt->fetchAll() as $row) {
                if (password_verify($password, $row['password'])) {
                    $found = true;
                }
            }
            
            if ($password == "") {
                $password_error = "empty";
            } else if (!$found) {
                $password_error = "true";
            } else {
                $session = "" . bin2hex(openssl_random_pseudo_bytes(256));
                $sql = "UPDATE login SET session = ? WHERE email = ?";
                $stmt = $db->prepare($sql);
                $params = array($session, $_POST["email"]);
                $stmt->execute($params);
                
                $account_sql = "SELECT * FROM login WHERE email = ?;";
                $params = array($email);
                $validated = true;
            }
        }
        
        $response->emailerror = $email_error;
        $response->passworderror = $password_error;
    }
    
    if (isset($_POST['session'])) {
        $session = trim($_POST["session"]);
        $session_error = "";
        if (check_session($session)) {
            
            $account_sql = "SELECT * FROM login WHERE session = ?;";
            $validated = true;
            $params = array($session);
    
        } else {
            $session_error = "true";
        }
        
        $response->sessionerror = $session_error;
    }
    
}
            
if ($validated) {
    
    $orders_array = array();
    $email = "";
    
    $stmnt = $db->prepare($account_sql);
    $stmnt->execute($params);
    foreach($stmnt->fetchAll() as $row) {
        
        $response->firstname = $row["firstname"];
        $response->lastname = $row["lastname"];
        $response->type = $row["type"];
        $response->email = $row["email"];
        $response->phone = $row["phone"];
        $response->zip = $row["zip"];
        $response->workfield = $row["workfield"];
        $response->address = $row["address"];
        $response->city = $row["city"];
        $response->state = $row["state"];
        $response->verified = $row["verified"];
        $response->radius = $row["radius"];
        $response->workaddress = $row["work_address"];
        $response->workcity = $row["work_city"];
        $response->workstate = $row["work_state"];
        $response->workzip = $row["work_zip"];
        $response->workphone = $row["work_phone"];
        $response->workemail = $row["work_email"];
        $response->alerts = $row["alerts"];
        
        $utc_time_zone = new DateTimeZone('UTC');
        $local_time_zone = new DateTimeZone($tz);
        $utc = new DateTime("now", $utc_time_zone);
        $local = new DateTime("now", $local_time_zone);
        $offset = $local_time_zone->getOffset($utc) / 3600;
        $offset = $offset * -1;
        $response->availability = substr($row['availability'], $offset) . substr($row['availability'], 0, $offset);
        
        $time = new DateTimeZone($row['timezone']);
        $response->offset = $time->getOffset($utc) / 3600;
        
        $response->session = $session;

        $email = $row["email"];
    }
    
    $stmnt = $db->prepare("SELECT * FROM orders WHERE client_email = ? OR secondary_providers LIKE ?;");
    
    $stmnt->execute(array($email, '%' . $email . '%'));
    foreach($stmnt->fetchAll() as $row) {
        
        $entry = new \stdClass();
        
        if ($email == $row['client_email']) {
            $entry->role = "primary";
        } else {
            $entry->role = "secondary";
        }
        
        $entry->order_number = $row["order_number"];
        $entry->customer_email = $row["customer_email"];
        $entry->message = $row["message"];
        $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($row["schedule"])), new DateTimeZone('UTC'));
        $local_date->setTimezone(new DateTimeZone($tz));
        $entry->timestamp = date('F d, Y h:i a', strtotime($row["timestamp"]));
        $entry->schedule = $local_date->format("F j, Y, g:i a");
        $entry->address = $row["address"];
        $entry->service = $row["service"];
        $entry->wage = $row["wage"];
        $entry->price = $row["cost"];
        $entry->customer_phone = $row["customer_phone"];
        $entry->satisfied = $row["satisfied"];
        $entry->rating = $row["rating"];
        
        $local_start = new DateTime(date('Y-m-d H:i:s', strtotime($row["start"])), new DateTimeZone('UTC'));
        $local_start->setTimezone(new DateTimeZone($tz));
        $entry->start = $local_start->format("g:i a");
        
        $local_end = new DateTime(date('Y-m-d H:i:s', strtotime($row["end"])), new DateTimeZone('UTC'));
        $local_end->setTimezone(new DateTimeZone($tz));
        $entry->end = $local_end->format("g:i a");
        
        $entry->uploaded = $row["uploaded"];
        $entry->expenditure = $row["expenditure"];
        $entry->currently_paused = $row["currently_paused"];
        $entry->status = get_order_status($row["order_number"]);
        
        $payment_info = payment($row["order_number"]);
        
        $entry->revenue = $payment_info->revenue;
        
        array_push($orders_array, $entry);
    }
    
    $response->orders = $orders_array;
    
}

header('Content-type: application/json');
print json_encode($response);

?>