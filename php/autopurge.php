<?php
include 'common.php';

$db = establish_database();
$sql = "SELECT * FROM {$DB_PREFIX}guests;";
$result = $db->query($sql);

foreach ($result as $row) {
    if (minutes_since($row['timestamp']) > 1440 * 29) {
        if ($row['banned'] == "y"){
            $sql = "INSERT INTO {$DB_PREFIX}guestbanned VALUES (?)";
            $stmt = $db->prepare($sql);
            $params = array(password_hash($row["phone"], PASSWORD_DEFAULT));
            $stmt->execute($params);
        }
        $sql = "DELETE FROM {$DB_PREFIX}guests WHERE phone = ?";
        $stmt = $db->prepare($sql);
        $params = array($row["phone"]);
        $stmt->execute($params);
    }
}
