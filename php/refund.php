<?php
include 'common.php';

$stripe = new \Stripe\StripeClient(
    $STRIPE_API_KEY
);

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
        $stmnt = $db->prepare("SELECT intent, service, customer_email, schedule, timezone FROM {$DB_PREFIX}orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $service = $row['service'];
            $customer_email = $row['customer_email'];
            $intent = $row['intent'];
            $schedule = $row['schedule'];
            $tz = $row['timezone'];
        }

        $name = "";
        $stmnt = $db->prepare("SELECT firstname FROM {$DB_PREFIX}login WHERE email = ?;");
        $stmnt->execute(array($customer_email));
        foreach($stmnt->fetchAll() as $row) {
            $name = ' ' . $row['firstname'];
        }

        $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone('UTC'));
        $local_date->setTimezone(new DateTimeZone($tz));

        $stripe->refunds->create(['payment_intent' => $intent]);

        send_email($customer_email, "no-reply@helphog.com", "Task Refunded", get_refund_email($name, $service, $order, $local_date->format('m\-d\-y \a\t g:ia')));
        ios_customer_notification($customer_email, "Order Refunded", $service . " (" . $order . ")", $order, "#1ecd97");

        $sql = "UPDATE {$DB_PREFIX}orders SET status = ? WHERE order_number = ?";
        $stmt = $db->prepare($sql);
        $params = array('re', $order);
        $stmt->execute($params);

    }

}
