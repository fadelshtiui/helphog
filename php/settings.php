<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include 'common.php';
if (isset($_POST["address"]) && isset($_POST["city"]) && isset($_POST["zip"]) && isset($_POST["state"]) && isset($_POST["session"])) {
    
    $errors = new \stdClass();
    
    $db = establish_database();
    
    if (check_session($_POST["session"])) {
        $zip_error = "";
        $zip = $_POST["zip"];
        if (!preg_match("/^[0-9]{5}$/", $zip)) {
            $zip_error = "true";
        }
        $address = trim($_POST["address"]);
        $city = trim($_POST["city"]);
        $state = trim($_POST["state"]);
        $session = trim($_POST['session']);
        $name;
        if ($zip_error == "") {
            
            $no_address = false;
            $no_city = false;
            $no_state = false;
            $no_zip = false;
            
            $name = "";
            $to_send = "";
            $stmnt = $db->prepare("SELECT * FROM login WHERE session = ?;");
            $stmnt->execute(array($session));
            foreach($stmnt->fetchAll() as $row) {
                
                if (strlen($row["work_address"]) < 1) {
                    $no_address = true;
                }
                if (strlen($row["work_city"]) < 1) {
                    $no_city = true;
                }
                if (strlen($row["work_state"]) < 1) {
                    $no_state = true;
                }
                if (strlen($row["work_zip"]) < 1) {
                    $no_zip = true;
                }
                
                $name = $row["firstname"];
                $errors->zip = $row["zip"];
                $to_send = $row["email"];
                
            }
            
            $sql = "UPDATE login SET address = :address, city = :city, zip = :zip, state = :state  WHERE session = :session";
            if ($no_zip) {
                $sql = "UPDATE login SET address = :address, city = :city, zip = :zip, state = :state, work_zip = :work_zip  WHERE session = :session";
            }
            $stmt = $db->prepare($sql);
            
            $params = array("address" => $address, "city" => $city, "zip" => $zip, "state" => $state, "session" => $session);
            if ($no_zip) {
                $params = array("address" => $address, "city" => $city, "zip" => $zip, "state" => $state, "session" => $session, "work_zip" => $zip);
            }
            $stmt->execute($params);
            
            if ($no_address) {
                $sql = "UPDATE login SET work_address = ? WHERE session = ?";
                $stmt = $db->prepare($sql);
                $params = array($address, $session);
                $stmt->execute($params);
            }
            if ($no_city) {
                $sql = "UPDATE login SET work_city = ? WHERE session = ?";
                $stmt = $db->prepare($sql);
                $params = array($city, $session);
                $stmt->execute($params);
            }
            if ($no_state) {
                $sql = "UPDATE login SET work_state = ? WHERE session = ?";
                $stmt = $db->prepare($sql);
                $params = array($state, $session);
                $stmt->execute($params);
            }
        }
        
        $errors->sessionerror = "false";
        
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
        $mail->addAddress($to_send, 'To');
        
        $mail->Subject = "HelpHog - Address Changed";
        $mail->Body    = get_address_email($to_send, $name);
        $mail->IsHTML(true);
        
        $mail->send();
        
        $mail->ClearAllRecipients();
       
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
