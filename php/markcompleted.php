<?php

include 'common.php';

$response = new stdClass();

if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    
    $db = establish_database();
    
    $order = trim($_POST["ordernumber"]);
    $session = trim($_POST['session']);
    
    if (validate_provider($order, $session)) {
        
        $message = 'The service provider has marked your order complete. If the support provided by the provider was inadequate you can dispute the transaction by texting back DISPUTE to this number.';
        
        $success = mark_completed($order, $message);
        
        if ($success) {
            
            $response->error = "";
            
        } else {
            
            $response->error = "Since this order has been disputed several times, our staff will now reach out to both you and the customer to resolve any issues.";
            
        }
        
    } else {
        $response->error = "Please log out and try again.";
    }
    
    
} else {
    
    $response->error = "missing parameters";
    
}

header('Content-Type: application/json');
echo json_encode($response);

?>