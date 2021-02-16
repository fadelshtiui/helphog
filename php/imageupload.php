<?php
include 'common.php';
if (isset($_POST["ordernumber"]) && isset($_POST['session'])) {
    
    if (validate_provider($_POST['ordernumber'], $_POST['session'])) {
        $db = establish_database();
        $info = strtolower(pathinfo($_FILES['image']['name']) ["extension"]);
        if ($info == "jpg" || $info == "png" || $info == "pdf" || $info == "jpeg") {
            $order_number = $_POST["ordernumber"];
            $target = "../../uploads/receipts/" . $order_number;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target . '.' . $info)) {
                $sql = "UPDATE orders SET uploaded = ? WHERE order_number = ?";
                $stmt = $db->prepare($sql);
                $params = array($info, $_POST["ordernumber"]);
                $stmt->execute($params);
                
                // Format
               
                $filename = realpath($target . '.' . $info);
                $cfile    = new CurlFile( $filename, 'image/' . $info, $filename );
                $data     = array( 'file' => $cfile );
                
                $taggun_endpoint = 'https://api.taggun.io/api/receipt/v1/simple/file';
                
                $ch      = curl_init();
                $options = array(
                	CURLOPT_URL            => $taggun_endpoint,
                	CURLOPT_RETURNTRANSFER => true,
                	CURLINFO_HEADER_OUT    => true,
                	CURLOPT_HEADER         => true,
                	CURLOPT_POST           => true,
                	CURLOPT_HTTPHEADER     => array(
                		'apikey: 4c8b4890595911ebafc7c5a18819396c',
                		'Accept: application/json',
                		'Content-Type: multipart/form-data',
                	),
                	CURLOPT_POSTFIELDS     => $data,
                );
                
                curl_setopt_array( $ch, $options );
                $result      = curl_exec( $ch );
                $header_info = curl_getinfo( $ch, CURLINFO_HEADER_OUT );
                $header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
                $header      = substr( $result, 0, $header_size );
                $body        = substr( $result, $header_size );
                curl_close( $ch );
                
                $body = json_decode($body);

                $confidence = $body->totalAmount->confidenceLevel;
                if (floatval($confidence) < 0.75){
                    echo "Receipt image quality was too poor, please upload a clearer picture of the receipt";
                }else{
                    $sql = "UPDATE orders SET expenditure = ? WHERE order_number = ?";
                    $stmt = $db->prepare($sql);
                    $params = array($body->totalAmount->data, $order_number);
                    $stmt->execute($params);
                    echo "amount" . $body->totalAmount->data;
                }
               
                
            } else {
                echo "Image upload failed. Image must be under 2 MB in size.";
            }
        } else {
            echo 'Invalid file format. Please upload a png, jpg, or pdf file.';
        }
        
    }
} else {
    echo 'Please choose a file before uploading.';
}
?>