<?php
include 'common.php';

$db = establish_database();
if (isset($_POST["email"]) && isset($_POST["phone"])){
    $phone = trim($_POST["phone"]);
    $email = trim($_POST["email"]);
    
    $email_error = "";
    $phone_error = "";
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_error = "Invalid email";
    }
    if ($email == "") {
        $email_error = "Email is empty";
    }
    
    if (!preg_match("/^[0-9]{10}$/", $phone)) {
        $phone_error = "Invalid phone number";
    }
    if ($phone == "") {
        $phone_error = "Phone number is empty";
    }
        
    $login = $db->query("SELECT email, phone FROM {$DB_PREFIX}login;");
    foreach ($login as $row) {
        if ($row["email"] == $email) {
            $email_error = "Email found, please login";
        }
        if ($row["phone"] == $phone) {
            $phone_error = "Phone found, please login";
        }
    }
    
    $result = new \stdClass();
    $result->emailerror = $email_error;
    $result->phoneerror = $phone_error;
    
    header('Content-type: application/json');
    print json_encode($result);
}


