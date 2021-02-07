<?php

include 'common.php';
if (isset($_POST["session"])) {
    
    $db = establish_database();
    
    $errors = new \stdClass();
    $errors->stripeerror = "false";
    
    if (check_session($_POST["session"])) {

        $session = $_POST['session'];
        
        $type = "";
        $stmnt = $db->prepare("SELECT type FROM login WHERE session = ?;");
        $stmnt->execute(array($session));
        foreach($stmnt->fetchAll() as $row) {
            $type = $row["type"];
        }
        
        if ($type == "Business") {
            
            $stripe_acc = "";
            $stmnt = $db->prepare("SELECT stripe_acc FROM login WHERE session = ?;");
            $stmnt->execute(array($session));
            foreach($stmnt->fetchAll() as $row) {
                $stripe_acc = $row["stripe_acc"];
            }
            
            error_log($stripe_acc);
            
            $stripe = new \Stripe\StripeClient('sk_test_51H77jdJsNEOoWwBJR4lupAfmJ6ZLABBPCWvwiNqv99a9rr0mfhyNZ1L823ae56gIxJLUEZKDvXKepbCN1lIwPXp200KKA5Ni5p');
            $response = $stripe->accounts->delete(
                $stripe_acc,
                []
            );
            error_log($stripe_acc);
            error_log($response);
            
            
            if ($response->deleted == true) {
                
                $sql = "DELETE FROM login WHERE session = ?";
                $stmt = $db->prepare($sql);
                $params = array($session);
                $stmt->execute($params);
                
            } else {
                
                $errors->stripeerror = "true";
                
            }
            
        } else {
            
            $sql = "DELETE FROM login WHERE session = ?";
            $stmt = $db->prepare($sql);
            $params = array($session);
            $stmt->execute($params);
            
        }
        
        $errors->sessionerror = "false";
        
    } else {
        
        $errors->sessionerror = "true";
        
    }
    
    header('Content-type: application/json');
    print json_encode($errors);
} 
?>
