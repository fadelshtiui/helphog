<?php
include 'common.php';
if (isset($_POST["timezone"]) && isset($_POST["session"])) {
    $timezone = trim($_POST["timezone"]);
    $session = trim($_POST["session"]);
    
    $db = establish_database();
    
    if (check_session($session)) {
        $sql = "UPDATE {$DB_PREFIX}login SET timezone = ? WHERE session = ?";
        $stmt = $db->prepare($sql);
        $params = array($timezone, $session);
        $stmt->execute($params);
    }

}
