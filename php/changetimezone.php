<?php
include 'common.php';
if (isset($_POST["timezone"]) && isset($_POST["session"])) {
    $timezone = trim($_POST["timezone"]);
    $session = trim($_POST["session"]);
    
    $db = establish_database();
    
    if (check_session($session)) {
        $user = get_user_info($session);
        $session_name = $user['session_name'];
        $sql = "UPDATE {$DB_PREFIX}login SET timezone = ? WHERE {$session_name} = ?";
        $stmt = $db->prepare($sql);
        $params = array($timezone, $user['match_session']);
        $stmt->execute($params);
    }

}
