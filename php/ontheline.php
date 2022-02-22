<?php
require __DIR__ . '/twilio-php-master/src/Twilio/autoload.php';
use Twilio\TwiML\VoiceResponse;
if(isset($_POST['From'])){

    $response = new VoiceResponse();

    $hookObject = json_encode([
        /*
         * The general "message" shown above your embeds
         */
        /*
         * The username shown in the message
         */
        "username" => "HelpHog",
        /*
         * The image location for the senders image
         */
        "avatar_url" => "https://pbs.twimg.com/profile_images/972154872261853184/RnOg6UyU_400x400.jpg",
        /*
         * Whether or not to read the message in Text-to-speech
         */
        "tts" => false,
        /*
         * File contents to send to upload a file
         */
        // "file" => "",
        /*
         * An array of Embeds
         */
        "embeds" => [
            /*
             * Our first embed
             */
            [

                // The type of your embed, will ALWAYS be "rich"
                "type" => "rich",

                // A description for your embed
                "description" => 'Caller is on the line',

                // The integer color to be used on the left side of the embed
                "color" => hexdec( "FFFFFF" ),

                // Field array of objects
                "fields" => [
                    // Field 1
                    [
                        "name" => 'Phone Number',
                        "value" => $_POST['From'],
                        "inline" => false
                    ]
                ]
            ]
        ]

    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

    $ch = curl_init();

    curl_setopt_array( $ch, [
        CURLOPT_URL => 'https://discord.com/api/webhooks/940028874706788352/10YNDZGhxnKJOGtaPseWeh8MbO4nQiemnp0fQ9XVdbuXjI_wZcK0od65l4QOrTn93USJ',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $hookObject,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);

    $response2 = curl_exec( $ch );
    curl_close( $ch );

    $response->say('Great! Please wait until the next available agent can assist you.');
    $response->enqueue('support');

    echo $response;
}
