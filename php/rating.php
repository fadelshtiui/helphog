<?php
include 'common.php';

$stripe = new \Stripe\StripeClient(
     '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'
);

if (isset($_POST["ordernumber"]) && isset($_POST["rating"]) && isset($_POST['session'])) {
     $db = establish_database();
     $order = trim($_POST["ordernumber"]);
     $rating = trim($_POST["rating"]);
     $session = trim($_POST['session']);

     if (validate_customer($order, $session)) {

          pay_provider($order);
          
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
