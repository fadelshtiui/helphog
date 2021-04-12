<?php
include 'common.php';

$stripe = new \Stripe\StripeClient(
    $STRIPE_API_KEY
);

if (isset($_POST["session"])) {
    $db = establish_database();
    $session = trim($_POST["session"]);
    $stripe_acc = "";
    $stmnt = $db->prepare("SELECT stripe_acc FROM {$DB_PREFIX}login WHERE session = ?;");
    $stmnt->execute(array($session));
    foreach($stmnt->fetchAll() as $row) {
        $stripe_acc = $row['stripe_acc'];
    }
    
    $loginlink = $stripe->accounts->createloginLink(
        $stripe_acc,
        ['redirect_url' => 'https://' . $SUBDOMAIN . 'helphog.com/provider']
    );
    
    echo $loginlink->url;
}
?>