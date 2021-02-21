<?php
include 'common.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$stripe = new \Stripe\StripeClient(
     'sk_test_51H77jdJsNEOoWwBJR4lupAfmJ6ZLABBPCWvwiNqv99a9rr0mfhyNZ1L823ae56gIxJLUEZKDvXKepbCN1lIwPXp200KKA5Ni5p'
);

if (isset($_POST["ordernumber"]) && isset($_POST["rating"]) && isset($_POST['session'])) {
     $db = establish_database();
     $order = trim($_POST["ordernumber"]);
     $rating = trim($_POST["rating"]);
     $session = trim($_POST['session']);

     if (validate_customer($order, $session)) {

          pay_provider($order);

          $service = "";
          $client_email = "";
          $stmnt = $db->prepare("SELECT service, client_email FROM orders WHERE order_number = ?;");
          $stmnt->execute(array($order));
          foreach ($stmnt->fetchAll() as $row) {
               $service = $row['service'];
               $client_email = $row['client_email'];
          }

          $name = "";
          $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
          $stmnt->execute(array($client_email));
          foreach ($stmnt->fetchAll() as $row) {
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
          $mail->addAddress($client_email, 'To');

          $mail->Subject = "HelpHog - Order Verified";
          $mail->Body    = get_completed_email($service, $name);
          $mail->IsHTML(true);

          $mail->send();

          $mail->ClearAllRecipients();

          $sql = "UPDATE orders SET rating = ?, status = ? WHERE order_number = ?";
          $stmt = $db->prepare($sql);
          $params = array($rating, 'pd', $order);
          $stmt->execute($params);

          echo 'success';
     } else {

          echo 'invalid session';
     }
} else {

     echo 'missing parameters';
}
