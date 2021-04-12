<?php
include 'common.php';

if (isset($_GET["email"]) && isset($_GET["secret"])) {
    
    $db = establish_database();
    
    $email = trim($_GET["email"]);
    $verify_key = trim($_GET["secret"]);
    
    $found = false;
    
    $stmnt = $db->prepare("SELECT verify_key FROM {$DB_PREFIX}login WHERE email = ?;");
    $stmnt->execute(array($email));
    foreach($stmnt->fetchAll() as $row) {
        if ($row['verify_key'] === $verify_key) {
            $found = true;
        }
    }
    
    if ($found) {
        $db = establish_database();
        $sql = "UPDATE {$DB_PREFIX}login SET verified = ?  WHERE email = ?";
        $stmt = $db->prepare($sql);
        $params = array("y", $email);
        $stmt->execute($params);
        echo '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/success?message=You+have+successfully+registered+for+HelpHog!&link=signin&content=you+can+now+login+here";</script>';
    } else {
        echo '<script>window.location.href = "https://' . $SUBDOMAIN . 'helphog.com/404";</script>';
    }
    
    
}
