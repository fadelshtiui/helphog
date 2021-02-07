<?php
include 'common.php';

$response = new stdClass();

if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    
    $session = trim($_POST['session']);
    $order = trim($_POST["ordernumber"]);
    
    if (validate_provider($order, $session)) {
        
        if (!start_stop_order($order)) {
            $response->error = 'This order has not been fully claimed. Secondary providers must first claim this order.';
        }
        
    }
    
} else {
    $response->error = 'missing parameters';
}

header('Content-type: application/json');
print json_encode($response);
?>
