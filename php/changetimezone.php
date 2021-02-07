<?php
include 'common.php';
if (isset($_POST["timezone"]) && isset($_POST["session"])) {
    $timezone = trim($_POST["timezone"]);
    $session = trim($_POST["session"]);
    
    $db = establish_database();
    
    if (check_session($session)) {
        $sql = "UPDATE login SET timezone = ? WHERE session = ?";
        $stmt = $db->prepare($sql);
        $params = array(tzOffsetToName($timezone), $session);
        $stmt->execute($params);
    }else{
        error_log($timezone);
    }
    

}

function tzOffsetToName($offset, $isDst = null) {
    if ($isDst === null) {
        $isDst = date('I');
    }

    $offset *= 3600;
    $zone    = timezone_name_from_abbr('', $offset, $isDst);

    if ($zone === false) {
        foreach (timezone_abbreviations_list() as $abbr) {
            foreach ($abbr as $city) {
                if ((bool)$city['dst'] === (bool)$isDst && strlen($city['timezone_id']) > 0 && $city['offset'] == $offset) {
                    $zone = $city['timezone_id'];
                    break;
                }
            }

            if ($zone !== false) {
                break;
            }
        }
    }
   
    return $zone;
}
?>
