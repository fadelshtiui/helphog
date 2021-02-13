<?php
include 'common.php';
if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    $db = establish_database();
    $order = trim($_POST["ordernumber"]);
    $session = trim($_POST['session']);
    
    if (validate_customer($order, $session)) {
    
        $uploaded = "";
        $stmnt = $db->prepare("SELECT uploaded FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $uploaded = $row['uploaded'];
        }
        
        if ($uploaded == 'n') {
            echo "false";
        } else if ($uploaded == 'pdf' || $uploaded == 'jpg' || $uploaded == 'jpeg' || $uploaded == 'png') {
            $destination = '../tmp/' . $order . '.' . $uploaded;
            if (!is_file($destination)) {
                $source = '../../uploads/receipts/' . $order . '.' . $uploaded;
                copy($source, $destination);
            }
            echo $uploaded;

        } else {
            echo 'invalid order number';
        }
    
    }
    
}
?>
