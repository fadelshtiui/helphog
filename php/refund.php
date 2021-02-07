<?php
include 'common.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    $db = establish_database();
    $order = trim($_POST["ordernumber"]);
    $session = trim($_POST['session']);
    
    if (validate_provider($order, $session)) {
    
        $service = "";
        $customer_email = "";
        $intent = "";
        $stmnt = $db->prepare("SELECT intent, service, customer_email FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $customer_email = $row['customer_email'];
            $intent = $row['intent'];
        }
        
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
        
        $mail->Subject = "HelpHog - Task Refunded";
        $mail->Body    = get_refund_email($name, $service);
        $mail->IsHTML(true);
        
        $mail->send();
        
        $mail->ClearAllRecipients();
        
        $sql = "UPDATE orders SET status = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array('re', $order);
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
        $mail->addAddress("admin@helphog.com", 'To');
        
        $mail->Subject = "REFUND ORDER";
        $mail->Body    = "Full refund on order:" . $order . " with payment intent: " . $intent;
        $mail->IsHTML(true);
        
        $mail->send();
        
        $mail->ClearAllRecipients();
        
    }
    
}
?>
