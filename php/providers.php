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

    $result = new \stdClass();
    $result->providers = $providers;


    if ($id != none){
        $stmnt2 = $db->prepare("SELECT firstname FROM login WHERE id = ?;");
        $stmnt2->execute(array($id));
        foreach($stmnt2->fetchAll() as $row2) {
            $name = $row2['firstname'];
        }
        $result->name = $name;
    }
    header('Content-type: application/json');
    print json_encode($result);
}

?>
