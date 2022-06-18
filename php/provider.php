<?php

include 'common.php';

$db = establish_database();
$response = new \stdClass();
$tz = trim($_POST['tz']);
$session = trim($_POST['session']);
$response->sessionerror = "";

if (check_session($session)) {

    $orders_array = array();
    $email = "";

    $user = get_user_info($session);

    $response->firstname = $user["firstname"];
    $response->lastname = $user["lastname"];
    $response->type = $user["type"];
    $response->email = $user["email"];
    $response->phone = $user["phone"];
    $response->zip = $user["zip"];
    $response->workfield = $user["workfield"];
    $response->address = $user["address"];
    $response->city = $user["city"];
    $response->state = $user["state"];
    $response->verified = $user["verified"];
    $response->radius = $user["radius"];
    $response->workaddress = $user["work_address"];
    $response->workcity = $user["work_city"];
    $response->workstate = $user["work_state"];
    $response->workzip = $user["work_zip"];
    $response->workphone = $user["work_phone"];
    $response->workemail = $user["work_email"];
    $response->alerts = $user["alerts"];
    // $response->client_email = $user["client_email"];
    $response->providerId = $user["id"];
    $response->services_offered = $user["services"];
    $response->disputes = $user["disputes"];
    $response->cancels = $user["cancels"];
    $response->banned = $user["banned"];
    
    $utc_time_zone = new DateTimeZone('UTC');
    $local_time_zone = new DateTimeZone($tz);
    $utc = new DateTime("now", $utc_time_zone);
    $local = new DateTime("now", $local_time_zone);
    $offset = $local_time_zone->getOffset($utc) / 3600;
    $offset = $offset * -1;
    $response->availability = substr($user['availability'], $offset) . substr($user['availability'], 0, $offset);

    $time = new DateTimeZone($user['timezone']);
    $response->offset = $time->getOffset($utc) / 3600;
    $response->tz = $user['timezone'];

    $response->session = $session;

    $email = $user["email"];

    $stmnt = $db->prepare("SELECT * FROM {$DB_PREFIX}orders WHERE client_email = ? OR secondary_providers LIKE ? ORDER BY start DESC;");

    $total_revenue = 0.0;
    $active_disputes = 0;
    $total_disputes = $response->disputes;
    $dispute_percentage = 0.0;
    $total_rating = 0;
    $number_of_ratings = 0;
    $active_orders = 0;
    $total_cancels = 0;
    $cancel_percentage = 0.0;
    $completed_orders = 0;

    $stmnt->execute(array($email, '%' . $email . '%'));
    foreach($stmnt->fetchAll() as $row) {

        $entry = new \stdClass();

        if ($email == $row['client_email']) {
            $entry->role = "primary";
        } else {
            $entry->role = "secondary";
        }

        $entry->secondary_providers_string = array();

        $providers = array();
        $secondary_providers_string = array();
        $initialproviders = explode("," , $row["secondary_providers"]);

        if ($row["people"] > 1 && $row["secondary_providers"] != ''){

            for ($j = 0; $j < sizeof($initialproviders); $j++){
                if ($initialproviders[$j] != $email){
                    array_push($providers, $initialproviders[$j]);
                }
            }
            if ($row["client_email"] != $email){
                    array_push($providers, $row["client_email"]);
            }
            for ($i = 0; $i < sizeof($providers); $i++){
                $stmnt = $db->prepare("SELECT firstname, phone FROM {$DB_PREFIX}login WHERE email = ?;");
                $stmnt->execute(array($providers[$i]));
                foreach($stmnt->fetchAll() as $row2) {
                    $string = $row2["firstname"] . ": " . preg_replace("/^1?(\d{3})(\d{3})(\d{4})$/", "$1-$2-$3", $row2["phone"]);
                    array_push($secondary_providers_string, $string);
                }
            }

            $entry ->secondary_providers_string = $secondary_providers_string;
        }

        $entry->order_number = $row["order_number"];
        $entry->customer_email = $row["customer_email"];
        $entry->message = $row["message"];
        $local_date = new DateTime(date('Y-m-d H:i:s', strtotime($row["schedule"])), new DateTimeZone('UTC'));
        $local_date->setTimezone(new DateTimeZone($tz));
        $entry->timestamp = date('F d, Y h:i a', strtotime($row["timestamp"]));
        $entry->schedule = $local_date->format("F j, Y, g:i a");
        $entry->address = $row["address"];
        $entry->service = $row["service"];
        $entry->wage = $row["wage"];
        $entry->price = money_format('%.2n', $row["cost"] * 0.9);
        $entry->customer_phone = $row["customer_phone"];
        $entry->satisfied = $row["satisfied"];
        $entry->rating = $row["rating"];
        $entry->duration = $row["duration"];

        $local_start = new DateTime(date('Y-m-d H:i:s', strtotime($row["start"])), new DateTimeZone('UTC'));
        $local_start->setTimezone(new DateTimeZone($tz));
        $entry->start = $local_start->format("g:i a");

        $local_end = new DateTime(date('Y-m-d H:i:s', strtotime($row["end"])), new DateTimeZone('UTC'));
        $local_end->setTimezone(new DateTimeZone($tz));
        $entry->end = $local_end->format("g:i a");

        $entry->uploaded = $row["uploaded"];
        $entry->expenditure = $row["expenditure"];
        $entry->currently_paused = $row["currently_paused"];
        $entry->status = get_order_status($row["order_number"]);

        $payment_info = payment($row["order_number"]);

        if (round($payment_info->provider_payout) < 0.50){
            $entry->revenue = "Payment waived (<\$0.50)";
        }else{
            $entry->revenue_raw = $payment_info->provider_payout;
            $entry->revenue = money_format('%.2n', $payment_info->provider_payout);
            if($row["status"] == "pd"){
                $total_revenue = $total_revenue + $entry->revenue;
            }
        }


        $total_orders++;

        if ($row["status"] == "pc" || $row["status"] == "cc" || $row["status"] == "ac"){
            $total_cancels = $total_cancels + 1;
        }

        if ($row["rating"] != 0){
            $number_of_ratings = $number_of_ratings + 1;
            $total_ratings = $total_ratings + $row["rating"];
        }

        if($row["status"] == "di"){
            $active_disputes = $active_disputes + 1;
        }

        if ($row["status"] == "cl" || $row["status"] == "st" || $row["status"] == "en"){
            $active_orders = $active_orders + 1;
        }

        if ($total_disputes != 0.0){
            $dispute_percentage = ($total_disputes/$total_orders) * 100;
        }else{
            $dispute_percentage = 0.00;
        }

        if ($row["status"] == "pd" || $row["status" == "mc"]){
            $completed_orders = $completed_orders + 1;
        }

        array_push($orders_array, $entry);
    }
    

    if($total_ratings != 0){
        $response->rating = money_format('%.2n', $total_ratings/$number_of_ratings) . " of 5";
    }else{
        $response->rating = "No reviews yet";
    }


    if($response->cancels != 0 && $total_orders != 0){
        $response->cancel_percentage = $response->cancels/$total_orders;
    }else{
        $response->cancel_percentage = 0;
    }
    
    $response->revenue = money_format('%.2n', $total_revenue);
    $response->active_disputes = $active_disputes;
    $response->dispute_percentage = money_format('%.2n', $dispute_percentage);
    $response->active_orders = $active_orders;
    $response->total_orders = $total_orders;
    $response->completed_orders = $completed_orders;

    $response->orders = $orders_array;
    
    

} else {
    $response->sessionerror = "true";
    
}

header('Content-type: application/json');
print json_encode($response);

