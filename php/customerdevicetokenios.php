<?php

include 'common.php';
if (isset($_POST["token"]) && isset($_POST["session"])) {

    $token = $_POST["token"];

    $errors = new \stdClass();

    $db = establish_database();

    $session = trim($_POST['session']);

    if (check_session($session)) {

        $user = get_user_info($session);
        $tokens = $user['iostokens'];

        if (strpos($tokens, $token) == false) {
            $tokens = $tokens . "," . $token;
            $sql2 = "UPDATE {$DB_PREFIX}login SET iostokens = ? WHERE session = ?";
            $stmt2 = $db->prepare($sql2);
            $params = array($tokens, $session);
            $stmt2->execute($params);
        }

        $errors->sessionerror = "false";

    } else {

        $errors->sessionerror = "true";
        header('Content-type: application/json');
        print json_encode($errors);

    }
}

?>
