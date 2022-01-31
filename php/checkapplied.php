<?php
include 'common.php';

$db = establish_database();
$response = "not applied";
if (isset($_POST["session"])) {

     $session = trim($_POST['session']);
     $user = get_user_info($session)
     if ($user['type'] == "Business") {
          $response = "accepted";
     } else if ($user["stripe_acc"] != "") {
          $response = "applied";
     }
}

echo $response;
