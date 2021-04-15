<?php
include 'common.php';

$response = new stdClass();

if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    
    $order_number = trim($_POST['ordernumber']);
    $session = trim($_POST['session']);
    
    if (validate_customer($order_number, $session)) {
        $success = dispute_order($order_number);
        
        if ($success) {
            $response->result = 'successful';
        } else {
            $response->result = 'error';
        }
    } else {
        $response->result = 'invalid session';
    }
    
} else {
    $response->result = 'missing parameters';
}

header('Content-Type: application/json');
echo json_encode($response);
