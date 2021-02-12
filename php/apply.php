<?php
include 'common.php';

session_start();

if (isset($_POST["session"]) && isset($_POST["workfield"]) && isset($_POST["experience"]) && isset($_POST['radius']) && isset($_POST['tz'])) { // quick apply

    $_SESSION = array();

    $session = trim($_POST["session"]);
    $workfield = trim($_POST["workfield"]);
    $experience = trim($_POST["experience"]);
    $radius = trim($_POST['radius']);
    $tz = trim($_POST['tz']);
    
    if (check_session($session)) {
        
        $db = establish_database();
    
        if (isset($_FILES['resume'])) {
            
            upload_resume();
        
        }
        
        $email = "";
        $zip = "";
        $stmnt = $db->prepare("SELECT * FROM login WHERE session = ?;");
        $stmnt->execute(array($session));
        foreach($stmnt->fetchAll() as $row) {
            $email = $row["email"];
            $zip = $row['zip'];
        }
        
        error_log('email ' . $email);
        error_log('zip' . $zip);
        
        $_SESSION['email'] = $email;
        $_SESSION['radius'] = $radius;
        $_SESSION['workfield'] = $workfield;
        $_SESSION['experience'] = $experience;
        $_SESSION['zip'] = $zip;
        $_SESSION['tz'] = $tz;
        
        $url = create_stripe_account($email, $db);
        echo $url;
        
    }
    
} else if (isset($_POST['radius']) && isset($_POST['workfield']) && isset($_POST['experience']) && isset($_POST['tz'])) { // regular apply

    $db = establish_database();
    
    $radius = trim($_POST['radius']);
    $workfield = trim($_POST['workfield']);
    $experience = trim($_POST['experience']);
    $tz = trim($_POST['tz']);
    
    $zip = $_SESSION['zip'];
    $email = $_SESSION['email'];
    
    $_SESSION['radius'] = $radius;
    $_SESSION['workfield'] = $workfield;
    $_SESSION['experience'] = $experience;
    $_SESSION['tz'] = $tz;
    
    $found = false;
    $sql = "SELECT email FROM login";
    $result = $db->query($sql);
    foreach ($result as $row) {
        if ($email == $row['email']) {
            $found = true;
        }
    }
    
    if (!$found) {
        
        if (isset($_FILES['resume'])) {
        
            upload_resume();
            
        }
        
        $url = create_stripe_account($email, $db);
        echo $url;
        
    } else {
        
        echo "Error: an account with this email already exists";
        return;
        
    }
    
} else {
    echo 'missing parameters';
}

function upload_resume() {
    $info = strtolower(pathinfo($_FILES['resume']['name'])["extension"]);
    if ($info == "jpg" || $info == "png" || $info == "pdf" || $info == "docx" || $info == "doc") {
      	$target = "../../uploads/resumes/". $email;
        move_uploaded_file($_FILES['resume']['tmp_name'], $target . '.' . $info);
    } else {
        echo "Please upload PNG, JPG, PDF, DOCX files only";
        return;
    }
}

function create_stripe_account($email, $db) {
    $account = \Stripe\Account::create([
      'country' => 'US',
      'type' => 'express',
      'email' => $email,
    ]);
    
    $account_id = $account->id;
    
    $_SESSION['stripe'] = $account_id;
    
    $account_links = \Stripe\AccountLink::create([
      'account' => $account_id,
      'refresh_url' => 'https://helphog.com/quickapply',
      'return_url' => 'https://helphog.com/php/completeapp.php',
      'type' => 'account_onboarding'
    ]);
    
    return $account_links->url;
}
			
?>
