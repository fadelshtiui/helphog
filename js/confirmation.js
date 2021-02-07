"use strict";

 $(window).on('load', function () {
      
      let url = "php/confirmation.php";
      fetch(url, {method: "POST", body: null})
           .then(checkStatus)
           .then(res => res.json())
           .then(handle)
           .catch(console.log);
 })
     
    function handle(response) {
    
        id("order-number").innerText = response.ordernumber;
        
        if (response.firstname) {
            id("name").innerText = "Hi " + response.firstname + ","; 
        } else {
            id("name").innerText = "Hello,"; 
        }
        
        let services = document.querySelectorAll(".service");
        for (let i = 0; i < services.length; i++) {
            services[i].innerText = response.service;
        }
        
        id("address").innerText = response.address;
        if (response.address == "Remote (online)") {
            id("city").innerText = response.city + " ";
        } else {
            id("city").innerText = response.city + ", ";
        }
        id("state").innerText = response.state + " ";
        id("zip").innerText = response.zip;
        
        id("date").innerText = 'Date: ' + response.schedule;
        
        if (response.wage == "per"){
            per(response.people, response.cost);
        } else{
            hour(response.cost, response.people, response.duration);
        }
        
        $('#load').fadeOut(500);
        $('#notLoading').delay(500).fadeIn(500);
    
    }
 
function per(people, cost){
    let peoples = "";
    
    peoples = "provider"
    if (people > 1) {
        peoples += "s"
    }
    
    let estimate = people * cost;
    
    id("popupProviders").innerText = people;
    id("popupSubtotal").innerText = people + " " + peoples + " at $" + cost;
    id("popupTotal").innerText = estimate;
}

function hour(cost, people, duration){

    let estimate = cost + "/hr"
    
    let peoples = "provider"
    if (people > 1) {
        peoples += "s"
    }
    
    estimate = people * cost * (duration - 1) + "-$" + people * cost * duration;
    
    id("popupProviders").innerText = people
    id("popupSubtotal").innerText = people + " " + peoples + " at $" + cost + "/hr ";
    id("popupTotal").innerText = estimate;
}