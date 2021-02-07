<?php
$filename = realpath( '../images/receipts/5ea65be343d27' );
$cfile    = new CurlFile( $filename, 'image/jpeg', $filename );
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

?>

<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>File Upload results</title>
</head>
<body>
	<p>Raw Result: <?php echo $result; ?>
	<p>Header Sent: <?php echo $header_info; ?></p>
	<p>Header Received: <?php echo $header; ?></p>
	<p>Body: <?php echo $body; ?></p>
</body>
</html>