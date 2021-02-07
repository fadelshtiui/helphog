<?php

include 'common.php';

if (isset($_POST["availability"]) && isset($_POST["session"]) && isset($_POST['tz'])) {
    
    $session = trim($_POST['session']);
    
    if (check_session($session)) {
        
        $tz = trim($_POST["tz"]);
        $availability = trim($_POST["availability"]);
        
        $utc_time_zone = new DateTimeZone('UTC');
        $local_time_zone = new DateTimeZone($tz);
        $utc = new DateTime("now", $utc_time_zone);
        $local = new DateTime("now", $local_time_zone);
        
        // ex. -8 for P.S.T
        $offset = $local_time_zone->getOffset($utc) / 3600;
        
        $availability = substr($availability, $offset) . substr($availability, 0, $offset);
        
        $db = establish_database();
        
        $stmnt = $db->prepare("SELECT alerts FROM login WHERE email = ?;");
        $stmnt->execute(array($email));
        foreach($stmnt->fetchAll() as $row) {
            $alerts = $row['alerts']; 
        }
        if ($alerts == "none"){
            $availability = "";
            for ($x = 0; $x <= 168; $x++) {
                $availability = $availability . '0';
            }
        }
        
        $sql = "UPDATE login SET availability = ? WHERE session = ?";
        $stmt = $db->prepare($sql);
        $params = array($availability, $session);
        $stmt->execute($params);

    }
    
}
?>