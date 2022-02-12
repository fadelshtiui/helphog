<?php

include 'common.php';

if (isset($_POST["alerts"]) && isset($_POST["session"])) {

    $session = trim($_POST['session']);

    if (check_session($session)) {

        $alerts= trim($_POST["alerts"]);

        if ($alerts == "both" || $alerts == "email" || $alerts == "sms" || $alerts == "none"){
            $db = establish_database();
            $response = new stdClass();
            $response->status = "none";

            $user = get_user_info($session);
            $work_phone = $user['work_phone'];
    		  $phone = $user['phone'];

            if ($alerts == "both" || $alerts == "sms"){

                $result = $db->query("SELECT number FROM {$DB_PREFIX}blacklisted;");
        		foreach ($result as $row) {
        			if (strpos($row['number'], $phone) || strpos($row['number'], $work_phone)) {
        			    $response->status = "blacklisted";
        			}
        		}
            }

            if ($response->status !==  "blacklisted"){
                $sql = "UPDATE {$DB_PREFIX}login SET alerts = ? WHERE session = ?";
                $stmt = $db->prepare($sql);
                $params = array($alerts, $session);
                $stmt->execute($params);

                if ($alerts == "none"){
                    $availability = "";
                    for ($x = 0; $x <= 168; $x++) {
                        $availability .= "0";
                    }
                    $sql = "UPDATE {$DB_PREFIX}login SET availability = ? WHERE session = ?";
                    $stmt = $db->prepare($sql);
                    $params = array($availability, $session);
                    $stmt->execute($params);
                }
            }
        }
    }
    header('Content-type: application/json');
    print json_encode($response);
}
?>
