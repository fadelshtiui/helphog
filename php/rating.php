<?php
include 'common.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST["ordernumber"]) && isset($_POST["rating"]) && isset($_POST['session'])) {
    $db = establish_database();
    $order = trim($_POST["ordernumber"]);
    $rating = trim($_POST["rating"]);
    $session = trim($_POST['session']);
    
    if (validate_customer($order, $session)) {
    
        $service = "";
        $client_email = "";
        $stmnt = $db->prepare("SELECT service, client_email FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $client_email = $column['client_email'];
        }
        
        $name = "";
        $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
        $stmnt->execute(array($client_email));
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
        $mail->Password = "FMB123456789!";
        $mail->setFrom('no-reply@helphog.com', 'HelpHog');
        $mail->addAddress($client_email, 'To');
        
        $mail->Subject = "HelpHog - Order Verified";
        $mail->Body    = get_completed_email($service, $name);
        $mail->IsHTML(true);
        
        $mail->send();
        
        $mail->ClearAllRecipients();
        
        $sql = "UPDATE orders SET rating = ?, status = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array($rating, 'mc', $order);
        $stmt->execute($params);
    }
    
}
?>
