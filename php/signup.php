<?php

include 'common.php';

if (isset($_POST["firstname"]) && isset($_POST["lastname"]) && isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST["confirm"]) && isset($_POST["phone"]) && isset($_POST["zip"]) && isset($_POST['sendemail']) && isset($_POST['createaccount'])) {
    $db = establish_database();
    
    $firstname = ucfirst(strtolower(trim($_POST["firstname"])));
    $lastname = ucfirst(strtolower(trim($_POST["lastname"])));
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $zip = trim($_POST["zip"]);
    $confirm = trim($_POST["confirm"]);
    $phone = trim($_POST["phone"]);
    $send_email = trim($_POST['sendemail']);
    $create_account = trim($_POST['createaccount']);

    $errors = validate_form($firstname, $lastname, $email, $password, $zip, $confirm, $phone);
    
    if ($errors->firstnameerror == "" && $errors->lastnameerror == "" && $errors->emailerror == "" && $errors->passworderror == "" && $errors->confirmerror == "" && $errors->phoneerror == "" && $errors->ziperror == "") {
        
        $db = establish_database();
        
        $secret_key = "" . bin2hex(openssl_random_pseudo_bytes(256));
        
        if ($send_email == 'true') {
            
            send_verification_email($firstname, $email, $secret_key);
            
        }
        
        if ($create_account == 'true') {
            
            $sql = "INSERT INTO login (firstname, lastname, email, password, phone, type, verified, zip, verify_key) VALUES (:firstname, :lastname, :email, :password, :phone, :type, :verified, :zip, :verify_key);";
            $stmt = $db->prepare($sql);
            $params = array("firstname" => $firstname, "lastname" => $lastname, "email" => $email, "password" => password_hash($password, PASSWORD_DEFAULT), "phone" => $phone, "type" => "Personal", "verified" => "n", "zip" => $zip, "verify_key" => $secret_key);
            $stmt->execute($params);
            
        } else {
            
            session_start();
            $_SESSION = array();
            $_SESSION['firstname'] = $firstname;
            $_SESSION['lastname'] = $lastname;
            $_SESSION['email'] = $email;
            $_SESSION['password'] = password_hash($password, PASSWORD_DEFAULT);
            $_SESSION['zip'] = $zip;
            $_SESSION['phone'] = $phone;
            
        }
            
    }
    
    header('Content-type: application/json');
    print json_encode($errors);
    
} else {
    
    echo "Please enter all required fields.";
    
}

?>
