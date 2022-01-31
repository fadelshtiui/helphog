<?php
include 'common.php';
if (isset($_POST["timezone"]) && isset($_POST["session"])) {
    $timezone = trim($_POST["timezone"]);
    $session = trim($_POST["session"]);
    
    $db = establish_database();
    
    if (check_session($session)) {
        $user = get_user_info($session);
        $sql = "UPDATE {$DB_PREFIX}login SET timezone = ? WHERE session = ?";
        $stmt = $db->prepare($sql);
        $params = array($timezone, $user['session']);
        $stmt->execute($params);
    }

}
