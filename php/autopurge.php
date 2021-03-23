<?php
include 'common.php';

$db = establish_database();
$sql = "SELECT * FROM guests;";
$result = $db->query($sql);

foreach ($result as $row) {
    if (minutes_since($row['timestamp']) > 1440 * 29) {
        $sql = "DELETE FROM guests WHERE phone = ?";
        $stmt = $db->prepare($sql);
        $params = array($row["phone"]);
        $stmt->execute($params);
    }
}
