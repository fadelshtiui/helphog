<?php

include 'common.php';

if (isset($_POST["token"]) && isset($_POST["session"]) && isset($_POST["action"])) {


    $token = $_POST["token"];
    $action = $_POST["action"];

    $errors = new \stdClass();

    $db = establish_database();

    $session = trim($_POST['session']);

    $alerts = "";
	$stmnt = $db->prepare("SELECT alerts FROM {$DB_PREFIX}login WHERE ios_provider_session = ?;");
	$stmnt->execute(array($session));
	foreach ($stmnt->fetchAll() as $row) {
		$alerts = $row['alerts'];
	}

    if (check_session($session)) {

        $tokens = "";

        if ($action == 'in'){
            if ($alerts == 'both'){
	            $alerts = 'email';
	        }
	        //can't have alerts be none
	        if ($alerts == 'sms'){
	            $alerts = 'email';
	        }

            $tokens = $token;

        }else{
	        if ($alerts == 'email'){
	            $alerts = 'both';
	        }
        }

        $sql = "UPDATE {$DB_PREFIX}login SET iostokenprovider = ?, alerts = ? WHERE ios_provider_session = ?";
        $stmt = $db->prepare($sql);
        $params = array($tokens, $alerts, $session);
        $stmt->execute($params);

        $errors->sessionerror = "false";


    } else {

        $errors->sessionerror = "true";
        header('Content-type: application/json');
        print json_encode($errors);

    }
}

?>
