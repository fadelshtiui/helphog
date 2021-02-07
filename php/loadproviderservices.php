<?php

include 'common.php';

if (isset($_POST["email"]) && isset($_POST["password"])) {
    
	$email = trim($_POST["email"]);
	$password = trim($_POST["password"]);

	if (hash_equals($password, 'HrgYlM&gkHqu&5QdFV_lT!_WcKF|jNBRsM=xcm?4df!r*keHJF$YAhm$T#k4?lhdV_Ht&b3=ed+5v=jg3p@GLNAC4nND^rt@Ew*vPM6oDHa?GWm#E6wIVjXJlvj99?s-?WeOZuByVbP=Yw^cmTJ0Xv^-KJYLXy?q6HAWA=p=oxH4gQP@qKF1R%=EIwQ3Zt0vSMzct?oF1jn|J1njw73g78jE#TVYS813I=a7jvdLjn0+VK$WqIM5G+62%zuuPBKS')) {
		$db = establish_database();

        $result = "";
        $stmnt = $db->prepare("SELECT services FROM login WHERE email = ?;");
        $stmnt->execute(array($email));
        foreach($stmnt->fetchAll() as $row) {
            $result = $row['services'];
        }
        
        echo $result;
	} else {
	    echo 'Access Denied';
	}
}

?>