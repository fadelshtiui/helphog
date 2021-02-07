<?php
include 'common.php';

if (isset($_GET["email"]) && isset($_GET["secret"])) {
    
    $db = establish_database();
    
    $email = trim($_GET["email"]);
    $verify_key = trim($_GET["secret"]);
    
    $found = false;
    
    $stmnt = $db->prepare("SELECT verify_key FROM login WHERE email = ?;");
    $stmnt->execute(array($email));
    foreach($stmnt->fetchAll() as $row) {
        if ($row['verify_key'] === $verify_key) {
            $found = true;
        }
    }
    
    if ($found) {
        $db = establish_database();
        $sql = "UPDATE login SET verified = ?  WHERE email = ?";
        $stmt = $db->prepare($sql);
        $params = array("y", $email);
        $stmt->execute($params);
        echo '<script>window.location.href = "https://helphog.com/success";</script>';
    } else {
        echo '<script>window.location.href = "https://helphog.com/404";</script>';
    }
    
    
}

?>