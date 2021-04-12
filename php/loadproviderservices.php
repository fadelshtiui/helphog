<?php

include 'common.php';

$response = new stdClass();
$response->error = "";
$response->services = array();

if (isset($_POST["email"]) && isset($_POST["password"])) {
    
	$email = trim($_POST["email"]);
	$password = trim($_POST["password"]);

    if (hash_equals($password, 'yoburger')) {
	// if (hash_equals($password, 'HrgYlM&gkHqu&5QdFV_lT!_WcKF|jNBRsM=xcm?4df!r*keHJF$YAhm$T#k4?lhdV_Ht&b3=ed+5v=jg3p@GLNAC4nND^rt@Ew*vPM6oDHa?GWm#E6wIVjXJlvj99?s-?WeOZuByVbP=Yw^cmTJ0Xv^-KJYLXy?q6HAWA=p=oxH4gQP@qKF1R%=EIwQ3Zt0vSMzct?oF1jn|J1njw73g78jE#TVYS813I=a7jvdLjn0+VK$WqIM5G+62%zuuPBKS')) {
		$db = establish_database();

        $services = "";
        $stmnt = $db->prepare("SELECT services FROM {$DB_PREFIX}login WHERE email = ?;");
        $stmnt->execute(array($email));
        foreach($stmnt->fetchAll() as $row) {
            $services = $row['services'];
        }
        
        $services_array = explode(",", $services);
        foreach ($services_array as $service) {
            if ($service != "") {
                array_push($response->services, $service);
            }
        }
        
	} else {
	    
	    $response->error = 'access denied';
	    
	}
	
} else {
    
    $response->error = 'missing parameters';
    
}

header('Content-type: application/json');
print json_encode($response);
