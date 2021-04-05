<?php
include 'common.php';

session_start();
$db = establish_database();

if (isset($_SESSION['firstname']) && isset($_SESSION['lastname']) && isset($_SESSION['email']) && isset($_SESSION['password']) && isset($_SESSION['zip']) && isset($_SESSION['phone']) && isset($_SESSION['radius']) && isset($_SESSION['workfield']) && isset($_SESSION['experience']) && isset($_SESSION['stripe']) && isset($_SESSION['tz'])) { // regular apply

    $firstname = $_SESSION['firstname'];
    $lastname = $_SESSION['lastname'];
    $email = $_SESSION['email'];
    $password = $_SESSION['password'];
    $zip = $_SESSION['zip'];
    $phone = $_SESSION['phone'];

    $radius = $_SESSION["radius"];
    $workfield = $_SESSION["workfield"];
    $experience = $_SESSION["experience"];
    $stripe = $_SESSION['stripe'];
    $tz = $_SESSION['tz'];

    $secret_key = "" . bin2hex(openssl_random_pseudo_bytes(256));

    $id;
    $unique = false;
    while (!$unique) {
        $id = (time() + mt_rand()) % 100000;
        if ($id >= 10000) {
            $unique = true;
            $result = $db->query("SELECT id FROM login");
            foreach ($result as $row) {
                if ($id == $row["id"]) {
                    $unique = false;
                    break;
                }
            }
        }
    }

    $sql = "INSERT INTO login (firstname, lastname, email, password, phone, type, verified, zip, work_zip, work_phone, work_email, stripe_acc, verify_key, timezone, id) VALUES (:firstname, :lastname, :email, :password, :phone, :type, :verified, :zip, :work_zip, :work_phone, :work_email, :stripe_acc, :verify_key, :timezone, :id);";
    $stmt = $db->prepare($sql);
    $params = array("firstname" => $firstname, "lastname" => $lastname, "email" => $email, "password" => $password, "phone" => $phone, "type" => "Personal", "verified" => "n", "zip" => $zip, "work_zip" => $zip, "work_phone" => $phone, "work_email" => $email, "stripe_acc" => $stripe, "verify_key" => $secret_key, "timezone" => $tz, "id" => $id);
    $stmt->execute($params);

    update_radius($radius, $email, $workfield, $db);

    send_email("admin@helphog.com", "admin@helphog.com", "HelpHog - New Applicant", $firstname . "\n\n" . $email . "\n\n" . $workfield . "\n\n" . $experience . "\n\n" . $phone);

    send_email($email, "no-reply@helphog.com", "Account Verification", get_signup_email($email, $firstname, $secret_key));

    echo '<script>window.location.href = "https://www.helphog.com/verify?message=Before+you+can+use+your+account%2C+please+verify+your+account+through+the+link+we+sent+to+your+email+address.+Be+on+the+lookout+for+an+email+from+our+hiring+team.+Check+your+junk+folder+if+you+don%27t+see+our+email+within+24+hours.";</script>';

} else if (isset($_SESSION["email"]) && isset($_SESSION["radius"]) && isset($_SESSION["workfield"]) && isset($_SESSION["experience"]) && isset($_SESSION["zip"]) && isset($_SESSION["stripe"]) && isset($_SESSION['tz'])) { // quick apply

    $email = $_SESSION["email"];
    $radius = $_SESSION["radius"];
    $workfield = $_SESSION["workfield"];
    $experience = $_SESSION["experience"];
    $stripe = $_SESSION["stripe"];
    $zip = $_SESSION["zip"];
    $tz = $_SESSION['tz'];

    $sql = "UPDATE login SET stripe_acc = :stripe_acc, timezone = :timezone WHERE email = :email";
    $stmt = $db->prepare($sql);
    $params = array("email" => $email, "stripe_acc" => $stripe, "timezone" => $tz);
    $stmt->execute($params);

    update_radius($radius, $email, $workfield, $db);

    send_email("admin@helphog.com", "admin@helphog.com", "HelpHog - New Applicant", $firstname . "\n\n" . $email . "\n\n" . $workfield . "\n\n" . $experience . "\n\n" . $phone);


    echo '<script>window.location.href = "https://www.helphog.com/verify?message=Your+application+has+been+submitted.+Please+be+on+the+lookout+for+an+email+from+our+hiring+team.+Check+your+junk+folder+if+you+don%27t+see+our+email+within+24+hours.";</script>';

} else {

    echo 'missing parameters';

}

function update_radius($radius, $email, $workfield, $db) {
    if ($radius + 0 > 100) {
        $radius = "100";
    }

    $sql = "UPDATE login SET workfield = ?, radius = ? WHERE email = ?";
    $stmt = $db->prepare($sql);
    $params = array($workfield, $radius, $email);
    $stmt->execute($params);
}

?>
