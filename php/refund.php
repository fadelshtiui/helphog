<?php
include 'common.php';

if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    $db = establish_database();
    $order = trim($_POST["ordernumber"]);
    $session = trim($_POST['session']);
    
    if (validate_provider($order, $session)) {
    
        $service = "";
        $customer_email = "";
        $intent = "";
        $stmnt = $db->prepare("SELECT intent, service, customer_email FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $customer_email = $row['customer_email'];
            $intent = $row['intent'];
        }
        
        $name = "";
        $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
        $stmnt->execute(array($customer_email));
        foreach($stmnt->fetchAll() as $row) {
            $name = $row['firstname'];
        }

        send_email($customer_email, "no-reply@helphog.com", "HelpHog - Task Refunded", get_refund_email($name, $service));
        
        $sql = "UPDATE orders SET status = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array('re', $order);
        $stmt->execute($params);
        
    }
    
}
