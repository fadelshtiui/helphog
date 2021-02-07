<?php

echo "checking bitcoin price..." . '<br>';

$LOWER_BOUND = 34800;
$UPPER_BOUND = 36100;

$get_data = fetch('GET', 'https://api.coindesk.com/v1/bpi/currentprice.json', false);
$response = json_decode($get_data, true);

$btc_price = str_replace(',', '', $response['bpi']['USD']['rate']);
$alert = false;
$message = '';

echo "current price is " . $btc_price . "...";

if ($btc_price > $UPPER_BOUND || $btc_price < $LOWER_BOUND) {
    $alert = true;
    if ($btc_price > $UPPER_BOUND) {
        $message = 'BTC price has risen above ' . $UPPER_BOUND;
    } else {
        $message = 'BTC price has fallen below ' . $THRESHOLD;
    }
    $message .= '\nCurrent price is: ' . $btc_price . '<br>';
}

if ($alert) {
    echo 'sending email...';
    $to = 'fadelshtiui@gmail.com';
    $subject = 'BTC PRICE ALERT';
    mail($to, $subject, $message);
}

function fetch($method, $url, $data){
  $curl = curl_init();
  switch ($method){
      case "POST":
         curl_setopt($curl, CURLOPT_POST, 1);
         if ($data)
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
         break;
      case "PUT":
         curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
         if ($data)
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
         break;
      default:
         if ($data)
            $url = sprintf("%s?%s", $url, http_build_query($data));
  }
  // OPTIONS:
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'APIKEY: 111111111111111111111',
      'Content-Type: application/json',
  ));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  // EXECUTE:
  $result = curl_exec($curl);
  if(!$result){die("Connection Failure");}
  curl_close($curl);
  return $result;
}

?>