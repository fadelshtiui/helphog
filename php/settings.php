<?php

include 'common.php';
if (isset($_POST["address"]) && isset($_POST["city"]) && isset($_POST["zip"]) && isset($_POST["state"]) && isset($_POST["session"])) {
    
    $errors = new \stdClass();
    
    $db = establish_database();

    $session = trim($_POST['session']);
    
    if (check_session($session)) {
        $zip_error = "";
        $zip = trim($_POST["zip"]);
        if (!preg_match("/^[0-9]{5}$/", $zip)) {
            $zip_error = "true";
        }
        $address = trim($_POST["address"]);
        $city = trim($_POST["city"]);
        $state = trim($_POST["state"]);
        
        $name;
        if ($zip_error == "") {
            
            $no_address = false;
            $no_city = false;
            $no_state = false;
            $no_zip = false;
            
            $name = "";
            $to_send = "";
            $user = get_user_info($session);            
                
            if (strlen($user["work_address"]) < 1) {
                $no_address = true;
            }
            if (strlen($user["work_city"]) < 1) {
                $no_city = true;
            }
            if (strlen($user["work_state"]) < 1) {
                $no_state = true;
            }
            if (strlen($user["work_zip"]) < 1) {
                $no_zip = true;
            }
            
            $name = $user["firstname"];
            $errors->zip = $user["zip"];
            $to_send = $user["email"];
                
            $sql = "UPDATE {$DB_PREFIX}login SET address = :address, city = :city, zip = :zip, state = :state  WHERE session = :session";
            if ($no_zip) {
                $sql = "UPDATE {$DB_PREFIX}login SET address = :address, city = :city, zip = :zip, state = :state, work_zip = :work_zip  WHERE session = :session";
            }
            $stmt = $db->prepare($sql);
            
            $params = array("address" => $address, "city" => $city, "zip" => $zip, "state" => $state, "session" => $user['match_session']);
            if ($no_zip) {
                $params = array("address" => $address, "city" => $city, "zip" => $zip, "state" => $state, "session" => $user['match_session'], "work_zip" => $zip);
            }
            $stmt->execute($params);
            
            $session_name = $user['session_name'];
            if ($no_address) {
                $sql = "UPDATE {$DB_PREFIX}login SET work_address = ? WHERE {$session_name} = ?";
                $stmt = $db->prepare($sql);
                $params = array($address, $user['match_session']);
                $stmt->execute($params);
            }
            if ($no_city) {
                $sql = "UPDATE {$DB_PREFIX}login SET work_city = ? WHERE {$session_name} = ?";
                $stmt = $db->prepare($sql);
                $params = array($city, $user['match_session']);
                $stmt->execute($params);
            }
            if ($no_state) {
                $sql = "UPDATE {$DB_PREFIX}login SET work_state = ? WHERE {$session_name} = ?";
                $stmt = $db->prepare($sql);
                $params = array($state, $user['match_session']);
                $stmt->execute($params);
            }
        }
        
        $errors->sessionerror = "false";
        
        send_email($to_send, "no-reply@helphog.com", "Address Changed", get_address_email($to_send, $name));
       
        $errors->ziperror = $zip_error;
        header('Content-type: application/json');
        print json_encode($errors);
        
    } else {
        
        $errors->sessionerror = "true";
        header('Content-type: application/json');
        print json_encode($errors);
        
    }
} 
?>
