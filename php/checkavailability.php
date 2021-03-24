<?php

include 'common.php';

if (isset($_POST["service"]) && isset($_POST["address"]) && isset($_POST["schedule"]) && isset($_POST["duration"]) && isset($_POST["numpeople"]) && isset($_POST['remote']) && isset($_POST['updatecontactlist']) && isset($_POST['tz']) && isset($_POST['id'])) {

    $service = trim($_POST["service"]);
    $schedule = trim($_POST["schedule"]);
    $address = trim($_POST["address"]);
    $post_duration = trim($_POST["duration"]);
    $numpeople = trim($_POST["numpeople"]);
    $tz = trim($_POST['tz']);
    $remote = trim($_POST['remote']);
    $updatecontactlist = trim($_POST['updatecontactlist']);
    $providerId = trim($_POST["id"]);

    echo check_availability($service, $schedule, $address, $post_duration, $numpeople, $tz, $remote, $updatecontactlist, $providerId);

}

function check_availability($service, $schedule, $address, $post_duration, $numpeople, $tz, $remote, $updatecontactlist, $providerId) {

    if ($updatecontactlist == 'true') {
        $utc = new DateTime(date('Y-m-d H:i:s', strtotime($schedule)), new DateTimeZone($tz));
    } else {
        $utc = new DateTime(date('Y-m-d', strtotime($schedule)), new DateTimeZone($tz));
    }

    $utc->setTimezone(new DateTimeZone('UTC'));
    $t = time();

    // check if day is greater than or equal to 6 days in the future
    if (minutes_until($utc->format('Y-m-d')) >= (1440 * 6)) {
        $result = "";
        for ($i = 0; $i < 24; $i++) {
            $result .= '0';
        }
        echo $result;
        return;
    }

    $db = establish_database();

    // if remote, check all providers, otherwise check by zip code
    $all_emails = array();

    if($providerId != 'none'){
        $providerexists = false;
        $stmnt = $db->prepare("SELECT email FROM login WHERE type='Business' AND services LIKE ? AND id=?;");
        $stmnt->execute(array('%' . $service . '%', $providerId));
        foreach($stmnt->fetchAll() as $row) {
            array_push($all_emails, $row['email']);
            error_log($row['email']);
            $providerexists = true;
        }

        if (!$providerexists){
            echo 'Provider with the inputed ID does not exist or does not provide this service';
            return;
        }

    }else{

        $stmnt = $db->prepare("SELECT email FROM login WHERE type='Business' AND services LIKE ?;");
        $stmnt->execute(array('%' . $service . '%'));
        foreach($stmnt->fetchAll() as $row) {
            array_push($all_emails, $row['email']);
        }
    }

    if ($remote == 'y') {

        $available_emails = $all_emails;

    }else{

        // only consider providers who are available in the address and can travel within order timeframe

        $available_emails = array();
        $durations = array();

        foreach ($all_emails as $email) {

            // if (address_works_for_provider($address, $email)) {
            //     array_push($available_emails, $email);
            // }
            $distanceMatrix = address_works_for_provider($address, $email, $t);
            if ($distanceMatrix->within){
                array_push($available_emails, $email);
                array_push($durations, $distanceMatrix->traffic);
            }
        }
    }


    $combined_availability = array();
    for ($i = 0; $i < 168; $i++) {
        array_push($combined_availability, 0);
    }
    $available_providers = array();

    // calculate time zone offset
    $utc_time_zone = new DateTimeZone('UTC');
    $local_time_zone = new DateTimeZone($tz);
    $curr_utc_time = new DateTime("now", $utc_time_zone);
    $curr_local_time = new DateTime("now", $local_time_zone);
    $offset = $local_time_zone->getOffset($curr_utc_time) / 3600;
    $offset = $offset * -1;

    for ($i = 0; $i < count($available_emails); $i++) {
        $curr_email = $available_emails[$i];

        $phone = "";
        $full_availability = "";
        $provider_tz = "";
        for ($j = 0; $j < 168; $j++) {
            $full_availability .= '0';
        }
        $stmnt = $db->prepare("SELECT availability, phone, timezone FROM login WHERE email = ?;");
        $stmnt->execute(array($curr_email));
        foreach($stmnt->fetchAll() as $row) {
            $provider_tz = $row['timezone'];
            $phone = $row['phone'];
            $full_availability = $row['availability'];

            // account for travel time
            $travelDurationIndexes = ceil($durations[$i] / 3600) + 1;
            $duration_index = 24 * intval($curr_utc_time->format('w')) + intval($curr_utc_time->format('G'));
            for ($k = $duration_index; $k < $duration_index + $travelDurationIndexes; $k++){
                $full_availability[$k] = '0';
            }
        }

        $sql_schedule = $utc->format('Y-m-d');

        $start_index = 24 * intval($utc->format('w')) + $offset;


        // check for overlapping orders
        $stmnt = $db->prepare("SELECT schedule, duration FROM orders WHERE client_email = ? OR secondary_providers LIKE ? AND schedule LIKE ?;");
        $stmnt->execute(array($curr_email, '%' . $curr_email . '%', '%' . $sql_schedule . '%'));
        foreach($stmnt->fetchAll() as $row) {

            $order_time = new DateTime(date('Y-m-d H:i:s', strtotime($row["schedule"])), new DateTimeZone('UTC'));

            $duration = $row['duration'];

            $time = intval($order_time->format('G'));
            $dow = 24 * $order_time->format('w');

            for ($j = $dow + $time; $j < $dow + $time + $duration; $j++) {
                $full_availability[$j] = '0';
            }
        }

        // check if the order is too long
        for ($j = $start_index; $j < $start_index + 24; $j++) {
            for ($k = 0; $k < $post_duration; $k++) {
                $curr = $j + $k;
                if ($curr >= strlen($full_availability)) {
                    $curr = $curr % strlen($full_availability);
                }
                if ($full_availability[$curr] == '0') {
                    $full_availability[$j] = '0';
                }
            }
        }

        if ($updatecontactlist == 'true') {

            $unadjusted_index = 24 * intval($utc->format('w')) + intval($utc->format('G'));
            $adjusted_index = $unadjusted_index % 168;

            if ($full_availability[$adjusted_index]) {

                $contact = new \stdClass();
                $contact->email = $curr_email;
                $contact->phone = $phone;
                $contact->tz = $tz;
                array_push($available_providers, $contact);
            }

            header('Content-type: application/json');
            print json_encode($available_providers);
        }


        // update total availability
        $j = $start_index;
        for ($index = 0; $index < 24; $index++) {
            if ($j >= strlen($full_availability)) {
                $j = $j % strlen($full_availability);
            }
            if ($full_availability[$j] == '1') {
                $combined_availability[$j] += 1;
            }
            $j++;
        }

    }

    if ($updatecontactlist == 'true') {
        session_start();
        $_SESSION = array();
        $_SESSION['available_providers'] = $available_providers;
        return '';
    }

    $final_result = "";
    for ($i = $start_index; $i < $start_index + 24; $i++) {
        if ($combined_availability[$i] >= $numpeople) {
            $final_result .= '1';
        } else {
            $final_result .= '0';
        }
    }

    if ($providerId !== '0'){
        if (strpos($final_result, '1') === false) {
          echo 'The selected provider is unavailable for this order';
            return;
        }
    }

    return $final_result;

}
