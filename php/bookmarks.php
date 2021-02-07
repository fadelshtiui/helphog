<?php

include 'common.php';

if (isset($_POST["username"]) && isset($_POST["action"]) && isset($_POST["bookmark"]) && isset($_POST['session'])) {
    
    $username = trim($_POST["username"]);
    $action = trim($_POST["action"]);
    $bookmark_parameter = trim($_POST["bookmark"]);
    $session = trim($_POST['session']);
    
    if (validate_user($email, $session)) {
    
        $db = establish_database();
        
        $bookmarks_string = "";
        $stmnt = $db->prepare("SELECT bookmark_list FROM bookmarks WHERE email = ?;");
        $stmnt->execute(array($username));
        foreach($stmnt->fetchAll() as $row) {
            $bookmarks_string = $row['bookmark_list'];
        }
        
        $found = false;
        $result = $db->query("SELECT email FROM bookmarks;");
        foreach ($result as $row) {
            if ($username === $row['email']) {
                $found = true;
            }
        }
        
        $time = gmdate('m-d-y H:i:s');
        
        if ($action == "retrieve") {
            
            echo $bookmarks_string;
            
        } else if ($action == "add") {
            
            $new;
            if ($bookmarks_string == "") {
                $new = $bookmark_parameter;
            } else {
                $new =  $bookmark_parameter . "," . $bookmarks_string;
            }
            
            if (!$found) {
                
                $sql = "INSERT INTO bookmarks (bookmark_list, email, timestamp) VALUES (?, ?, ?);";
                $stmt = $db->prepare($sql);
                $params = array($new, $username, $time);            
                $stmt->execute($params);
                
            } else {
                
                $sql = "UPDATE bookmarks SET bookmark_list = ?, timestamp = ? WHERE email = ?";
                $stmt = $db->prepare($sql);
                $params = array($new, $time, $username,);
                $stmt->execute($params);
                
            }
            
        } else if ($action == "delete") {
            
            $bookmarks_array = explode(",", $bookmarks_string);
            
            $array_without_bookmark = array_diff($bookmarks_array, array($bookmark_parameter));
            
            $updated_string = "";
            if (count($array_without_bookmark) > 0) {
                $updated_string = $array_without_bookmark[1];
            }
            for ($i = 1; $i < count($array_without_bookmark); $i++) {
                $updated_string.= ",";
                $updated_string.= $array_without_bookmark[$i];
            }
            if ($updated_string == ",") {
                $updated_string = "";
            }
            
            $sql = "UPDATE bookmarks SET bookmark_list = ?, timestamp = ? WHERE email = ?";
            $stmt = $db->prepare($sql);
            $params = array($updated_string, $time, $username);
            $stmt->execute($params);
            
        }
    }
}
?>