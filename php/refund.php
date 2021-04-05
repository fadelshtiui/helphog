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
        $schedule = "";
        $tz = "";
        $stmnt = $db->prepare("SELECT intent, service, customer_email, schedule, timezone FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $customer_email = $row['customer_email'];
            $intent = $row['intent'];
            $schedule = $row['schedule'];
            $tz = $row['timezone'];
        }

        $name = "";
        $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
        $stmnt->execute(array($customer_email));
        foreach($stmnt->fetchAll() as $row) {
            $name = ' ' . $row['firstname'];
        }

        $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
        $local_date->setTimezone(new DateTimeZone($tz));

        send_email($customer_email, "no-reply@helphog.com", "Task Refunded", get_refund_email($name, $service, $order, $local_date->format('m\-d\-y \a\t g:ia')));

        $sql = "UPDATE orders SET status = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array('re', $order);
        $stmt->execute($params);

    }

}
