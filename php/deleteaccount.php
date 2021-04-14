<?php

include 'common.php';

if (isset($_POST["session"])) {
    
    $db = establish_database();
    
    $errors = new \stdClass();
    $errors->stripeerror = "false";
    $errors->ordererror = "false";
    $errors->sessionerror = "true";

    $session = trim($_POST['session']);
            
    if (check_session($session)) {
        
        $errors->sessionerror = "false";
        
        $email = "";
        $type = "";
        $stmnt = $db->prepare("SELECT type, email FROM {$DB_PREFIX}login WHERE session = ?;");
        $stmnt->execute(array($session));
        foreach($stmnt->fetchAll() as $row) {
            $type = $row["type"];
            $email = $row["email"];
        }
        
        $has_active_orders = false;
        
        if ($type == "Business") {
            
            $stmnt = $db->prepare("SELECT status FROM {$DB_PREFIX}orders WHERE client_email = ? OR secondary_providers LIKE ? OR customer_email = ?;");
            $stmnt->execute(array($email, '%' . $email . '%', $email));
            foreach($stmnt->fetchAll() as $row) {
                if ($row['status'] != 'pd' && $row["status"] != 'ac' && $row["status"] != 'cc' && $row["status"] != 'pc' && row["status"] != "re") {
                    $has_active_orders = true;
                }
            }
            
            if ($has_active_orders) {
                
                $errors->ordererror = "true";
                
            } else {
                
                $stripe_acc = "";
                $stmnt = $db->prepare("SELECT stripe_acc FROM {$DB_PREFIX}login WHERE session = ?;");
                $stmnt->execute(array($session));
                foreach($stmnt->fetchAll() as $row) {
                    $stripe_acc = $row["stripe_acc"];
                }
                            
                $stripe = new \Stripe\StripeClient($STRIPE_API_KEY);
                $response = $stripe->accounts->delete(
                    $stripe_acc,
                    []
                );            
                
                if ($response->deleted == true) {
                    
                    // clear all provider info from primary orders
                    $sql = "UPDATE {$DB_PREFIX}orders SET client_email = ?, clicked = '' WHERE client_email = ?;";
                    $placeholder = "Provider Account Deleted";
                    $stmt = $db->prepare($sql);
                    $params = array($placeholder, $email);
                    $stmt->execute($params);
                    
                    // clear all provider info from secondary orders
                    $stmnt = $db->prepare("SELECT order_number, secondary_providers FROM {$DB_PREFIX}orders WHERE secondary_providers LIKE ?;");
                    $stmnt->execute(array('%' . $emai . '%'));
                    foreach($stmnt->fetchAll() as $row) {
                        $secondary_providers_array = explode(",", $row["secondary_providers"]);
            
                        $array_without_cancelling_provider = array_diff($secondary_providers_array, array($cancelling_provider));
                        
                        $updated_string = "";
                        if (count($array_without_cancelling_provider) > 0) {
                            $updated_string = $array_without_cancelling_provider[1];
                        }
                        for ($i = 1; $i < count($array_without_cancelling_provider); $i++) {
                            $updated_string.= ",";
                            $updated_string.= $array_without_cancelling_provider[$i];
                        }
                        if ($updated_string == ",") {
                            $updated_string = "";
                        }
                        
                        $sql = "UPDATE {$DB_PREFIX}orders SET secondary_providers = ?, clicked = '' WHERE order_number = ?";
                        $stmt = $db->prepare($sql);
                        $params = array($updated_string, $row["order_number"]);
                        $stmt->execute($params);
                    }
                    
                    // clear all orders provider made as customer
                    $sql = "UPDATE {$DB_PREFIX}orders SET customer_email = ?, address = ?, customer_phone = ?, city = '', state = '', zip = '', street_address = '' WHERE customer_email = ?";
                    $placeholder = "Customer Account Deleted";
                    $stmt = $db->prepare($sql);
                    $params = array($placeholder, $placeholder, $placeholder, $email);
                    $stmt->execute($params);
                        
                    // clear login table of provider info
                    $sql = "DELETE FROM {$DB_PREFIX}login WHERE session = ?";
                    $stmt = $db->prepare($sql);
                    $params = array($session);
                    $stmt->execute($params);
                    
                } else {
                    
                    $errors->stripeerror = "true";
                    
                }
            
            }
            
        } else {
            
            $stmnt = $db->prepare("SELECT status FROM {$DB_PREFIX}orders WHERE customer_email = ?;");
            $stmnt->execute(array($email));
            foreach($stmnt->fetchAll() as $row) {
                if ($row['status'] != 'pd' && $row['status'] != 'mc' && $row['status'] != 'ac' && $row['status'] != 'pc' && $row['status'] != 'cc' && row['status'] != 're') {
                    $has_active_orders = true;
                }
            }
            
            if ($has_active_orders) {
                
                $errors->ordererror = "true";
                
            } else {
                
                // clear bookmarks
                $sql = "DELETE FROM bookmarks WHERE email = ?";
                $stmt = $db->prepare($sql);
                $params = array($email);
                $stmt->execute($params);
                
                // clear all customer info from orders
                $sql = "UPDATE {$DB_PREFIX}orders SET customer_email = ?, address = ?, customer_phone = ?, city = '', state = '', zip = '', street_address = '' WHERE customer_email = ?";
                $placeholder = "Customer Account Deleted";
                $stmt = $db->prepare($sql);
                $params = array($placeholder, $placeholder, $placeholder, $email);
                $stmt->execute($params);
                
                // clear login table
                $sql = "DELETE FROM {$DB_PREFIX}login WHERE session = ?";
                $stmt = $db->prepare($sql);
                $params = array($session);
                $stmt->execute($params);
                
            }

        }
    
    }

    header('Content-type: application/json');
    print json_encode($errors);
} 
?>
