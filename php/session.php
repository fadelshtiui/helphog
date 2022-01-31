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
        
        $user = get_user_info($post_session);
            
        $account->firstname = $user['firstname'];
        $account->email = $user['email'];
        $account->phone = $user['phone'];
        $account->address = $user['address'];
        $account->city = $user['city'];
        $account->state = $user['state'];
        $account->zip = $user['zip'];
        $account->type = $user['type'];
        
        if ($user['type'] == 'Business') {
            $account->work_address = $user['work_address'];
            $account->work_city = $user['work_city'];
            $account->work_state = $user['work_state'];
            $account->work_zip = $user['work_zip'];
            $account->work_phone = $user['work_phone'];
            $account->work_email = $user['work_email'];
            $account->radius = $user['radius'];
            $account->availability = $user['availability'];
            $account->workfield = $user['workfield'];
        }
            
    }
    
    $response->account = $account;
    
    header('Content-type: application/json');
    print json_encode($response);
}
?>