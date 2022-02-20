<?php
require __DIR__ . '/twilio-php-master/src/Twilio/autoload.php';
use Twilio\TwiML\VoiceResponse;

$response = new VoiceResponse();
$dial = $response->dial('');
$dial->queue('support');

echo $response;
