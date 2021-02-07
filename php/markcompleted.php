<?php

include 'common.php';

if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    
    $db = establish_database();
    
    $order = trim($_POST["ordernumber"]);
    $session = trim($_POST['session']);
    
    if (validate_provider($order, $session)) {
        
        $message = 'The service provider has marked your order complete. If the support provided by the provider was inadequate you can dispute the transaction by texting back DISPUTE to this number.';
        mark_completed($order, $message);
        
    }
    
    
}

?>