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
