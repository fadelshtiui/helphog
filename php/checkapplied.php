<?php
include 'common.php';

$db = establish_database();
$response = "not applied";
if (isset($_POST["session"])) {

     $session = trim($_POST['session']);
     $stmnt = $db->prepare("SELECT stripe_acc, type FROM {$DB_PREFIX}login WHERE session = ?;");
     $stmnt->execute(array($session));
     foreach ($stmnt->fetchAll() as $row) {
          if ($row['type'] == "Business") {
               $response = "accepted";
          } else if ($row["stripe_acc"] != "") {
               $response = "applied";
          }
     }
}

echo $response;
