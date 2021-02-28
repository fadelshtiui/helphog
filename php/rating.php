<?php
include 'common.php';

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

          send_email($client_email, "no-reply@helphog.com", "HelpHog - Order Verified", get_completed_email($service, $name));

          $sql = "UPDATE orders SET rating = ? WHERE order_number = ?";
          $stmt = $db->prepare($sql);
          $params = array($rating, $order);
          $stmt->execute($params);

          echo 'success';
     } else {

          echo 'invalid session';
     }
} else {

     echo 'missing parameters';
}
