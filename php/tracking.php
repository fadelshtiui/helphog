<?php
include 'common.php';
if (isset($_POST["order"]) && isset($_POST['email'])) {
    
    $db = establish_database();
    
    $order = trim($_POST["order"]);
    $email = trim($_POST['email']);
    
    $response = new \stdClass();
    
    $found = false;
    $stmnt = $db->prepare("SELECT customer_email FROM orders WHERE order_number = ?;");
    $stmnt->execute(array($order));
    foreach($stmnt->fetchAll() as $row) {
        if ($email == $row['customer_email']) {
            $found = true;
        }
    }
    
    if (!$found) {
        
        $response->emailerror = "true";
        
    } else {
    
        $service = "";
        $status = "";
        $cancelled = "false";
        $stmnt = $db->prepare("SELECT service, status FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $status = $row['status'];
            if ($status == 'pc' || $status == 'cc' || $status == 'ac') {
                $cancelled = "true";
            }
        }
        
        $response->status = $status;
        $response->service = $service;
        $response->cancelled = $cancelled;
        
    }
    
    header('Content-type: application/json');
    print json_encode($response);
}
?>
