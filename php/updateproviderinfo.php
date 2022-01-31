<?php

include 'common.php';

if (isset($_POST["distance"]) && isset($_POST["city"]) && isset($_POST["state"]) && isset($_POST["zip"]) && isset($_POST["session"]) && isset($_POST["address"])) {
    
    $db = establish_database();

    $distance = trim($_POST['distance']);
    $zip = trim($_POST["zip"]);
    $session = trim($_POST['session']);
    $state = trim($_POST['state']);
    $city = trim($_POST['city']);
    $address = trim($_POST['address']);
    
    $user = get_user_info($session);
    $email = $user['email'];
    
    if ($email !== '') {
        
        $zip_error = "";
        
        if ($distance > 100){
            $distance = 100;
        }

        if ($_POST["address"] == ""){
            echo "Full address required";
            return;
        } else if ($zip_error == "") {
            $sql = "UPDATE {$DB_PREFIX}login SET radius = ?, work_address = ?, work_city = ?, work_state = ?, work_zip = ? WHERE session = ?";
            $stmt = $db->prepare($sql);
            $params = array($distance, $address, $city, $state, $zip, $user['session']);
            $stmt->execute($params); 
        }
        
    }
   
}
?>