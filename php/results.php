<?php

include_once 'common.php';
    
$response = new \stdClass();

$category_count = array();
$services = array();
$categories_array = array();

$db = establish_database();
$result = $db->query("SELECT category FROM categories;");
foreach ($result as $row) {
    array_push($categories_array, $row['category']);
}

$response->categories = $categories_array;

$sql = "";
$params = array();

if (isset($_POST["search"])) {
    
    $search = trim($_POST["search"]);
    $metaphone = metaphone($search);
    $sql = "SELECT *, (MATCH(keyword, service, description) AGAINST (? IN NATURAL LANGUAGE MODE)) AS score FROM services WHERE metaphonetext = ? OR MATCH(keyword, service, description) AGAINST (? IN NATURAL LANGUAGE MODE) ORDER BY score DESC";
    $params = array($search, $metaphone, $search);
    
} else if (isset($_POST['category'])) {
    
    $category = trim($_POST['category']);
    $sql = "SELECT * FROM services WHERE category = ?";
    $params = array($category);
    
} else if (isset($_POST['service'])) {
    
    $service = trim($_POST['service']);
    $sql = "SELECT * FROM services WHERE service = ?";
    $params = array($service);
    
}

$num_available = 0;

$stmnt = $db->prepare($sql);
$stmnt->execute($params);
foreach($stmnt->fetchAll() as $row) {
    
    $entry = new \stdClass();
    
    $service = $row["service"];
    
    $remote = $row['remote'];
    
    $entry->service = $service;
    $entry->description = $row["description"];
    $entry->cost = $row["cost"];
    $entry->img = $row["img"];
    $entry->wage = $row["wage"];
    $entry->remote = $row["remote"];
    
    $category = $row["category"];
    
    $entry->category = $category;
    
    if (!array_key_exists($category, $category_count)) {
        $category_count[$category] = 0;
    }
    $category_count[$category]++;
    
    $available = 0.5;
    
    $all_emails = array();
    $stmnt = $db->prepare("SELECT email FROM login WHERE services LIKE ?;");
    $stmnt->execute(array('%' . $service . '%'));
    foreach($stmnt->fetchAll() as $row) {
        array_push($all_emails, $row["email"]);
    }
        
    if (isset($_POST['city']) || isset($_POST['state']) || isset($_POST['address']) || isset($_POST['zip'])) { // manual address
    
        $address_array = array();
        
        $city = trim($_POST['city']);
        $address = trim($_POST['address']);
        $state = trim($_POST['state']);
        $zip = trim($_POST['zip']);
        
        $full_address = str_replace(' ', '+', $address . '+' . $city . '+' . $state . '+' . $zip);
        
        if ($address != "") {
            $response->address = $address;
        }
        
        if ($state != "") {
            $response->state = $state;
        }
        
        if ($city != "") {
            $response->city = $city;
        }
        
        if ($zip != "") {
            $response->zip = $zip;
        }
        
        if ($remote == 'y') {
            $available = 1;
            $num_available++;
        } else {
            $available = 0;
            foreach ($all_emails as $email) {
                $google_response = address_works_for_provider($full_address, $email, time());
                if ($google_response ->within) {
                    $available = 1;
                    $num_available++;
                    break;
                }
            }
        }
        
    } else if (isset($_POST['session'])) { // logged in
        
        $session = $_POST['session'];
        
        $stmnt = $db->prepare("SELECT address, state, city, zip, radius FROM login WHERE session = ?;");
        $stmnt->execute(array($session));
        $radius = 0;
        $address = "";
        $state = "";
        $city = "";
        $zip = "";
        foreach ($stmnt->fetchAll() as $row) {
            $radius = intval($row["radius"]);
            
            $address = $row["address"];
            if ($address != "") {
                $response->address = $address;
            }
            
            $state = $row["state"];
            if ($state != "") {
                $response->state = $state;
            }
            
            $city = $row["city"];
            if ($city != "") {
                $response->city = $city;
            }
            
            $zip = $row["zip"];
            $response->zip = $zip;
        }
        
        $full_address = str_replace(' ', '+', $address . '+' . $city . '+' . $state . '+' . $zip);
        
        if ($remote == 'y') {
            $available = 1;
            $num_available++;
        } else {
            $available = 0;
            foreach ($all_emails as $email) {
                $google_response = address_works_for_provider($full_address, $email, time());
                if ($google_response ->within) {
                    $available = 1;
                    $num_available++;
                    break;
                }
            }
        }
        
    
    } else if (isset($_POST['zip'])) { // guest
        
        $zip = trim($_POST['zip']);
        
        
        if ($remote == 'y') {
            $available = 1;
            $num_available++;
        } else {
            $available = 0;
            foreach ($all_emails as $email) {
                $google_response = address_works_for_provider($full_address, $email, time());
                if ($google_response ->within) {
                    $available = 1;
                    $num_available++;
                    break;
                }
            }
        }
        
        
        $response->zip = $zip;
        
    } else {
        if (count($all_emails) > 0 && $remote == 'y') {
            $available = 1;
            $num_available++;
        }
    }
    
    $entry->available = $available;
    
    array_push($services, $entry);

}

$response->available = $num_available;
$response->services = $services;
$response->counts = $category_count;

header('Content-type: application/json');
print json_encode($response);

?>