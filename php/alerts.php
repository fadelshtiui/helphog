<?php

include 'common.php';

if (isset($_POST["alerts"]) && isset($_POST["session"])) {
    
    $session = trim($_POST['session']);
    
    if (check_session($session)) {
        
        $alerts= trim($_POST["alerts"]);
        
        $db = establish_database();
        
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
?>