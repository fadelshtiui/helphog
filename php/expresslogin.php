<?php
include 'common.php';

$stripe = new \Stripe\StripeClient(
    $STRIPE_API_KEY
);

if (isset($_POST["session"])) {
    $db = establish_database();
    $session = trim($_POST["session"]);
    $user = get_user_info($session);
    $stripe_acc = $user['stripe_acc'];
    
    $loginlink = $stripe->accounts->createloginLink(
        $stripe_acc,
        ['redirect_url' => 'https://' . $SUBDOMAIN . 'helphog.com/provider']
    );
    
    echo $loginlink->url;
}
?>