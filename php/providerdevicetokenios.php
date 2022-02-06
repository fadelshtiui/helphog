<?php

include 'common.php';

if (isset($_POST["token"]) && isset($_POST["session"]) && isset($_POST["action"])) {


    $token = $_POST["token"];
    $action = $_POST["action"];

    $errors = new \stdClass();

    $db = establish_database();

    $session = trim($_POST['session']);

    if (check_session($session)) {

        $tokens = "";

        if ($action == 'in'){

            $tokens = $token;

        }

        $sql = "UPDATE {$DB_PREFIX}login SET iostokenprovider = ? WHERE ios_provider_session = ?";
        $stmt = $db->prepare($sql);
        $params = array($tokens, $session);
        $stmt->execute($params);

        $errors->sessionerror = "false";


    } else {

        $errors->sessionerror = "true";
        header('Content-type: application/json');
        print json_encode($errors);

    }
}

?>
