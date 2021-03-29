<?php
include 'common.php';

$response = new stdClass();

if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    
    $session = trim($_POST['session']);
    $order = trim($_POST["ordernumber"]);
    
    if (validate_provider($order, $session)) {
        
        $response->error = start_stop_order($order);
        
    }
    
} else {
    $response->error = 'missing parameters';
}

header('Content-type: application/json');
print json_encode($response);
