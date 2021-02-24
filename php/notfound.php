<?php
include 'common.php';
if (isset($_POST["searchterm"]) && isset($_POST['session'])) {
    
    $search = trim($_POST["searchterm"]);
    $session = trim($_POST['session']);
    
    if (check_session($session)) {
        $db = establish_database();
        $sql = "INSERT INTO missing_services (phrase) VALUES (?);";
        $stmt = $db->prepare($sql);
        $params = array($search);
        $stmt->execute($params);
    }
    
}
?>