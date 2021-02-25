<?php

include 'common.php';

if (isset($_POST["service"])) {

    $service = $_POST["service"];

    $db = establish_database();

    $providers = "";
    $stmnt = $db->prepare("SELECT providers FROM services WHERE service = ?;");
    $stmnt->execute(array($service));
    foreach($stmnt->fetchAll() as $row) {
        $providers = $row['providers'];
    }

    $result = new \stdClass();
    $result->providers = $providers;

    header('Content-type: application/json');
    print json_encode($result);
}

?>
