<?php
include 'common.php';

$stripe = new \Stripe\StripeClient(
    $STRIPE_API_KEY
);

if (isset($_POST["session"])) {
    $db = establish_database();
    $session = trim($_POST["session"]);
    $stripe_acc = "";
    $stmnt = $db->prepare("SELECT stripe_acc FROM login WHERE session = ?;");
    $stmnt->execute(array($session));
    foreach($stmnt->fetchAll() as $row) {
        $stripe_acc = $row['stripe_acc'];
    }
    
    $loginlink = $stripe->accounts->createLoginLink(
        $stripe_acc,
        ['redirect_url' => 'https://helphog.com/provider']
    );
    
    echo $loginlink->url;
}
?>