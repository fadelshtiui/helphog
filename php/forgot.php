<?php
include 'common.php';

if (isset($_POST["email"])) {
    $db = establish_database();
    $email_error = "";
    $email = trim($_POST["email"]);
    $name = "";
    $result = $db->query("SELECT firstname, email FROM login;");
    $found = false;
    foreach ($result as $row) {
        if ($email === $row['email']) {
            $found = true;
            $name = $row['firstname'];
        }
    }
    if (!$found) {
        $email_error = "notfound";
        if ($email == "") {
            $email_error = "empty";
        }
    } else {
        $random_hash = "" . bin2hex(openssl_random_pseudo_bytes(128));
                
        $sql = "UPDATE login SET forgot = ? WHERE email = ?";
        $stmt = $db->prepare($sql);
        $params = array($random_hash, $email);
        $stmt->execute($params);
        
        send_email($email, "no-reply@helphog.com", "Password Reset", get_reset_email($email, $random_hash, $name));
        
    }
    $errors = new \stdClass();
    $errors->emailerror = $email_error;
    header('Content-type: application/json');
    print json_encode($errors);
}
?>
