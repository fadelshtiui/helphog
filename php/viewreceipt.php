<?php
include 'common.php';
if (isset($_GET["ordernumber"]) && isset($_GET['image_key'])) {
    
    $order = trim($_GET["ordernumber"]);
    $image_key = trim($_GET['image_key']);

    $validated = false;
    
    $db = establish_database();

    $stmnt = $db->prepare("SELECT image_key FROM orders WHERE order_number = ?;");
    $stmnt->execute(array($order));
    foreach($stmnt->fetchAll() as $row) {
        if (hash_equals($image_key, $row['image_key'])) {
            $validated = true;
        }
    }
    
    if ($validated) {

        

        $uploaded = "";
        $stmnt = $db->prepare("SELECT uploaded FROM orders WHERE order_number = ?;");
        $stmnt->execute(array($order));
        foreach($stmnt->fetchAll() as $row) {
            $uploaded = $row['uploaded'];
        }
        
        if ($uploaded == 'n') {
            echo "false";
        } else if ($uploaded == 'pdf' || $uploaded == 'jpg' || $uploaded == 'jpeg' || $uploaded == 'png') {
            
            $source = '../../uploads/receipts/' . $order . '.' . $uploaded;

            if (file_exists($source)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename='.basename($source));
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($source));
                ob_clean();
                flush();
                readfile($source);
                exit;
            }
            
            echo $uploaded;

        } else {
            echo 'invalid order number';
        }
    
    } else {
        
        echo 'access denied';
        
    }
    
}
?>
