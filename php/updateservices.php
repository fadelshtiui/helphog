<?php

include 'common.php';

if (isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST["services"])) {
	$email = trim($_POST["email"]);
	$password = trim($_POST["password"]);
	$services = json_decode($_POST["services"]);
	
	if (hash_equals($password, 'HrgYlM&gkHqu&5QdFV_lT!_WcKF|jNBRsM=xcm?4df!r*keHJF$YAhm$T#k4?lhdV_Ht&b3=ed+5v=jg3p@GLNAC4nND^rt@Ew*vPM6oDHa?GWm#E6wIVjXJlvj99?s-?WeOZuByVbP=Yw^cmTJ0Xv^-KJYLXy?q6HAWA=p=oxH4gQP@qKF1R%=EIwQ3Zt0vSMzct?oF1jn|J1njw73g78jE#TVYS813I=a7jvdLjn0+VK$WqIM5G+62%zuuPBKS')) {
		if (count($services) == 0) {
			return;
		}
		
		$db = establish_database();
		
		$services_string = $services[0];
		for ($i = 1; $i < count($services); $i++) {
			$services_string .= ',' . $services[$i];
		}
		
		$sql = "UPDATE login SET services = ? WHERE email = ?";
        $stmt = $db->prepare($sql);
        $params = array($services_string, $email);
        $stmt->execute($params);
        
        echo 'Successfully updated';
	} else {
	    echo 'Access Denied';
	}
}

?>