<?php
include 'common.php';
if (isset($_POST["session"])) {
    
    $db = establish_database();
    $response = new \stdClass();
    $account = new \stdClass();
    
    $post_session = trim($_POST["session"]);
    $found = check_session($post_session);
    
    $response->validated = "false";
    
    if ($found) {
        $response->validated = "true";
        
        $stmnt = $db->prepare("SELECT * FROM {$DB_PREFIX}login WHERE session = ?;");
        $stmnt->execute(array($post_session));
        foreach($stmnt->fetchAll() as $row) {
            
            $account->firstname = $row['firstname'];
            $account->email = $row['email'];
            $account->phone = $row['phone'];
            $account->address = $row['address'];
            $account->city = $row['city'];
            $account->state = $row['state'];
            $account->zip = $row['zip'];
            $account->type = $row['type'];
            
            if ($row['type'] == 'Business') {
                $account->work_address = $row['work_address'];
                $account->work_city = $row['work_city'];
                $account->work_state = $row['work_state'];
                $account->work_zip = $row['work_zip'];
                $account->work_phone = $row['work_phone'];
                $account->work_email = $row['work_email'];
                $account->radius = $row['radius'];
                $account->availability = $row['availability'];
                $account->workfield = $row['workfield'];
            }
            
        }
    }
    
    $response->account = $account;
    
    header('Content-type: application/json');
    print json_encode($response);
}
?>