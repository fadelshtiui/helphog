<?php
include 'common.php';
if (isset($_POST["ordernumber"]) && isset($_POST["session"])) {

    $order = trim($_POST["ordernumber"]);
    $session = trim($_POST["session"]);

    if (validate_provider($order, $session)) {
        resume_order($order);

        $db = establish_database();


        $service = '';
        $customer_email = '';
        $stmnt = $db->prepare("SELECT service, customer_email FROM {$DB_PREFIX}orders WHERE order_number = ?;");
    	$stmnt->execute(array($order));
    	foreach ($stmnt->fetchAll() as $row) {
    		$service = $row['service'];
    		$customer_email = $row['customer_email'];
    	}

        ios_customer_notification($customer_email, "Work Resumed By Provider", $service . " (" . $order . ")", $order, "#1ecd97");
    }

}
?>
