<?php
include 'common.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
        }
        $name = $row['firstname'];
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
        
        $mail->Subject = "HelpHog - Password Reset";
        $mail->Body    = get_reset_email($email, $random_hash, $name);
        $mail->IsHTML(true);
        
        $mail->send();
        
        $mail->ClearAllRecipients();
        
    }
    $errors = new \stdClass();
    $errors->emailerror = $email_error;
    header('Content-type: application/json');
    print json_encode($errors);
}
?>
