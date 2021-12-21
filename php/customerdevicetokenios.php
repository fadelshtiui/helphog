<?php

include 'common.php';
if (isset($_POST["token"]) && isset($_POST["session"])) {

    $token = $_POST["token"];

    $errors = new \stdClass();

    $db = establish_database();

    $session = trim($_POST['session']);

    if (check_session($session)) {


        $sql = "UPDATE {$DB_PREFIX}login SET iostokens = ? WHERE session = ?";
        $stmt = $db->prepare($sql);
        $params = array($token, $session);
        $stmt->execute($params);

        $errors->sessionerror = "false";




          $keyfile = 'AuthKey_K232TR3M53.p8';               # <- Your AuthKey file
          $keyid = 'K232TR3M53';                            # <- Your Key ID
          $teamid = '9R7PSB424V';                           # <- Your Team ID (see Developer Portal)
          $bundleid = 'org.regionalhelp.help';                # <- Your Bundle ID
          $url = 'https://api.development.push.apple.com';  # <- development url, or use http://api.push.apple.com for production environment
          $token = $token;              # <- Device Token

          $message = '{"aps":{"alert":{"title": "HelpHog Order Update", "subtitle": "benito", "body": "chacko"},"sound":"default", "thread-id": "12321"}}';

          $key = openssl_pkey_get_private('file://'.$keyfile);

          $header = ['alg'=>'ES256','kid'=>$keyid];
          $claims = ['iss'=>$teamid,'iat'=>time()];

          $header_encoded = base64($header);
          $claims_encoded = base64($claims);

          $signature = '';
          openssl_sign($header_encoded . '.' . $claims_encoded, $signature, $key, 'sha256');
          $jwt = $header_encoded . '.' . $claims_encoded . '.' . base64_encode($signature);

          // only needed for PHP prior to 5.5.24
          if (!defined('CURL_HTTP_VERSION_2_0')) {
              define('CURL_HTTP_VERSION_2_0', 3);
          }

          $http2ch = curl_init();
          curl_setopt_array($http2ch, array(
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_URL => "$url/3/device/$token",
            CURLOPT_PORT => 443,
            CURLOPT_HTTPHEADER => array(
              "apns-topic: {$bundleid}",
              "authorization: bearer $jwt"
            ),
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => $message,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => 1
          ));

          $result = curl_exec($http2ch);
          if ($result === FALSE) {
            throw new Exception("Curl failed: ".curl_error($http2ch));
          }

          $status = curl_getinfo($http2ch, CURLINFO_HTTP_CODE);
          echo $status;



        $errors->errors = "it worked";



        header('Content-type: application/json');
        print json_encode($errors);

    } else {

        $errors->sessionerror = "true";
        header('Content-type: application/json');
        print json_encode($errors);

    }
}
function base64($data) {
            return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
          }
?>
