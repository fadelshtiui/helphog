<?php
include_once 'common.php';
if (isset($_GET["type"])) {
    $type = trim($_GET["type"]);
    $db = establish_database();
    
    $response = array();
    if ($type == "categories") {
        $result = $db->query("SELECT category FROM categories;");
        foreach ($result as $row) {
            array_push($response, $row['category']);
        }
    } else if ($type == "services") {
        $result = $db->query("SELECT service FROM services;");
        foreach ($result as $row) {
            array_push($response, $row["service"]);
        }
    } else {
        print "Usage: https://regionalhelp.org/php/info.php?type=(categories|services)";
    }
    
    header('Content-type: application/json');
    print json_encode($response);
}
?>