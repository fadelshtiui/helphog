<?php

include 'common.php';

if (isset($_POST["distance"]) && isset($_POST["city"]) && isset($_POST["state"]) && isset($_POST["zip"]) && isset($_POST["session"]) && isset($_POST["address"])) {
    
    $db = establish_database();
    
    $email = "";
    $newEmail= $_POST["work_email"];
    $stmnt = $db->prepare("SELECT email FROM login WHERE session = ?;");
    $stmnt->execute(array($_POST['session']));
    foreach($stmnt->fetchAll() as $row) {
        $email = $row['email'];
    }
    
    if ($email !== '') {
        
        $zip_error = "";
        $zip = $_POST["zip"];
        $distance = $_POST["distance"];
        if ($distance > 100){
            $distance == 100;
        }

        if ($_POST["address"] == ""){
            echo "Full address required";
            return;
        }
        
        else if ($zip_error == "") {
            $sql = "UPDATE login SET radius = ?, work_address = ?, work_city = ?, work_state = ?, work_zip = ? WHERE session = ?";
            $stmt = $db->prepare($sql);
            $params = array($distance, $_POST["address"], $_POST["city"], $_POST["state"], $_POST["zip"], $_POST["session"]);
            $stmt->execute($params); 
        }
        
    }
   
}
?>