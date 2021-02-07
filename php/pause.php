<?php
include 'common.php';
if (isset($_POST["ordernumber"]) && isset($_POST["session"])) {
    $order = trim($_POST["ordernumber"]);
    $session = trim($_POST["session"]);
    
    if (validate_provider($order, $session)) {
        pause_order($order);
    }
    

}
?>

