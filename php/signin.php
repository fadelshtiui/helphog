<?php
include 'common.php';
if (isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST['request_source'])) {
    
    $db = establish_database();
    $response = new \stdClass();
    $email_error = "";
    $password_error = "";
    
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $request_source = trim($_POST['request_source']);
    
    $found = false;
    $result = $db->query("SELECT email FROM {$DB_PREFIX}login;");
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
        $stmnt = $db->prepare("SELECT password FROM {$DB_PREFIX}login WHERE email = ?;");
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
            
            $stmnt = $db->prepare("SELECT verified FROM {$DB_PREFIX}login WHERE email = ?;");
            $stmnt->execute(array($email));
            foreach($stmnt->fetchAll() as $row) {
                $response->verified = $row["verified"];
            }
            
            $response->session = update_session($request_source, $email);
        }
        
    }
    
    $response->emailerror = $email_error;
    $response->passworderror = $password_error;
    header('Content-type: application/json');
    print json_encode($response);
}
?>
