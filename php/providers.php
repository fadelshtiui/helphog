<?php

include 'common.php';

if (isset($_POST["service"]) && isset($_POST["id"])) {

    $service = $_POST["service"];
    $id = $_POST["id"];

    $db = establish_database();

    $providers = "";
    $stmnt = $db->prepare("SELECT providers FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach($stmnt->fetchAll() as $row) {
        $providers = $row['providers'];
    }

    $available = 0;
    $stmnt2 = $db->prepare("SELECT availability FROM {$DB_PREFIX}login WHERE type='Business' AND services LIKE ?;");
    $stmnt2->execute(array('%' . $service . '%'));
    foreach($stmnt2->fetchAll() as $row2) {
        if (strpos($row2['availability'], '1') !== false) {
            $available++;
        }
    }

    $result = new \stdClass();
    $result->providers = $providers;
    $result->available = $available;

    header('Content-type: application/json');
    print json_encode($result);
}

?>
