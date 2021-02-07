<?php

include 'common.php';

if (isset($_GET["email"]) && isset($_GET["ordernumber"]) && isset($_GET['secret'])) {
    
    $email = trim($_GET["email"]);
    $order_number = trim($_GET["ordernumber"]);
    $accept_key = trim($_GET['secret']);
    
    echo claim_order($email, $order_number, $accept_key);
    
}


?>