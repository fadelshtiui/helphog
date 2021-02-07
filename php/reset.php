<?php
include 'common.php';

if (isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST["confirm"]) && isset($_POST["number"])) {
    
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm = trim($_POST["confirm"]);
    $number = trim($_POST["number"]);
    
    $db = establish_database();
    
    $email_error = "";
    $password_error = "";
    $confirm_error = "";
    $number_error = "";
    
    $name = "";
    $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
    $stmnt->execute(array($email));
    foreach($stmnt->fetchAll() as $row) {
        $name = $row['firstname'];
    }
    
    $found = false;
    $result = $db->query("SELECT email FROM login;");
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
        
        $found = false;
        $stmnt = $db->prepare("SELECT password FROM login WHERE email = ?;");
        $stmnt->execute(array($email));
        foreach($stmnt->fetchAll() as $row) {
            if (password_verify($password, $row['password'])) {
                $found = true;
            }
        }
        
        if ($found) {
            $password_error = "found";
        }
        if (!preg_match("/^\S{6,}$/", $password)) {
            $password_error = "true";
        }
        
    }
    
    if ($password == "") {
        $password_error = "empty";
    }
    if ($confirm == "") {
        $confirm_error = "empty";
    } else if ($confirm !== $password) {
        $confirm_error = "true";
    }
    
    $found = false;
    $stmnt = $db->prepare("SELECT forgot FROM login WHERE email = ?;");
    $stmnt->execute(array($email));
    foreach($stmnt->fetchAll() as $row) {
        if ($number = $row['forgot']) {
            $found = true;
        }
    }
    
    if ($number == "") {
        $number_error = "empty";
    } else if (!$found) {
        $number_error = "true";
    }
    if ($email_error == "" && $password_error == "" && $confirm_error == "" && $number_error == "") {
        $sql = "UPDATE login SET password = ?, forgot = '' WHERE email = ?";
        $stmt = $db->prepare($sql);
        $params = array(password_hash($password, PASSWORD_DEFAULT), $email);
        $stmt->execute($params);
    }
    
    $response = new \stdClass();
    $response->emailerror = $email_error;
    $response->passworderror = $password_error;
    $response->confirmerror = $confirm_error;
    $response->numbererror = $number_error;
    $response->number = $number;
    
    header('Content-type: application/json');
    print json_encode($response);
}
?>
