<?php

include 'common.php';

if (isset($_POST["session"]) && isset($_POST['tz'])) {
    $db = establish_database();
    $post_session = trim($_POST["session"]);
    $tz = trim($_POST['tz']);

    if (check_session($post_session)) {

        $email = "";

        $stmnt = $db->prepare("SELECT email FROM login WHERE session = ?;");
        $stmnt->execute(array($post_session));
        foreach($stmnt->fetchAll() as $row) {
            $email = $row['email'];
        }

        $response = new \stdClass();
        $orders_array = array();
        $stmnt = $db->prepare("SELECT * FROM orders WHERE customer_email = ? ORDER BY timestamp;");
        $stmnt->execute(array($email));
        foreach($stmnt->fetchAll() as $row) {

            $entry = new \stdClass();

            $entry->number = $row["order_number"];
            $entry->name = $row["service"];
            $entry->rating = $row['rating'];

            $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($row["schedule"])), new DateTimeZone('UTC'));
            $local_date->setTimezone(new DateTimeZone($tz));

            $entry->date = $local_date->format('F j, Y');
            $entry->time = $local_date->format('h:i a');
            $entry->message = $row["message"];
            $entry->wage = $row["cost"];

            $entry->began = "TBD";
            if ($row["start"] != '2019-02-18 01:53:14') {
                $entry->began = date('h:i a', strtotime($row["start"]));
            }
            $entry->ended = "TBD";
            if ($row["end"] != '2019-02-18 01:53:14') {
                $entry->ended = date('h:i a', strtotime($row["end"]));
            }
            $entry->provider = "TBD";
            if ($row['status'] != 'pe' ) {

                $entry->provider = "";
                $stmnt = $db->prepare("SELECT firstname FROM login WHERE email = ?;");
                $stmnt->execute(array($row["client_email"]));
                foreach($stmnt->fetchAll() as $row2) {
                    $entry->provider = $row2['firstname'];
                }

            }

            $entry-> type = $row['wage'];


            if ($row['status'] != 'st') {
                $ts1 = strtotime($row["start"]);
                $ts2 = strtotime($row["end"]);
                $seconds_diff = $ts2 - $ts1;
                $seconds_diff -= $row["paused_time"];

                $time = ($seconds_diff / 3600);
                if ($row["wage"] == "hour") {
                    $earnings = $time * $row["cost"];
                } else {
                    $earnings = $row["cost"];
                }
                $entry->amount = round($earnings + $row["expenditure"], 2) ;
                $entry->hours = round($time, 2);
            } else {
                $entry->amount = "TBD";
                $entry->hours = "TBD";
            }

            $entry->expenditure = $row["expenditure"];


            $entry->status = $row["status"];

            if ($row["uploaded"] == 'n') {
                $entry->receipt = "none";
            } else {
                $entry->receipt = "download";
            }
            
            $entry->imagekey = $row['image_key'];

            $mintotal = minutes_until($row['schedule']);

            if ($mintotal < 1440) {
                $entry->warning = 'Warning: since you are canceling within 24 hours of the start time, you will be charged $15. ';
            }

            array_push($orders_array, $entry);
        }

        $response->orders = $orders_array;
        header('Content-type: application/json');
        echo json_encode($response);
    }
}
?>
