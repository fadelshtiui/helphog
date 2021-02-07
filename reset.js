"use strict";

(function() {

     window.onload = function() {
         
        id("provider").style.display = "none";
        
        let data = new FormData();
        data.append("session", getSession())
        let url = "php/session.php"
        fetch(url, {method: "POST", body: data })
            .then(checkStatus)
            .then(res => res.json())
            .then(updateNav)
            .catch(console.log);
         
        id("reset-btn").onclick = reset;
        id("confirm").addEventListener("keyup", function(event) {
           event.preventDefault();
           if (event.keyCode === 13) {
                button.click();
           }
        });
        
        const urlParams = new URLSearchParams(window.location.search);
        id("number").value = urlParams.get('code');
        
     };
     
     function updateNav(response) {
         if (response.validated == "true" && response.account.type == "Business") {
            id("provider").style.display = "inline";
         }
     }

     function reset() {
          let data = new FormData();
          let email = id("email").value.toLowerCase();
          let password = id("password").value;
          let confirm = id("confirm").value;
          let number = id("number").value;
          data.append("email", email);
          data.append("password", password);
          data.append("confirm", confirm);
          data.append("number", number);
          let url = "php/reset.php"
          fetch(url, {method: "POST", body: data })
               .then(checkStatus)
               .then(res => res.json())
               .then(handleResponse)
               .catch(console.log);
     }

     function handleResponse(response) {
          if (response.emailerror == "true") {
               id("email-error").innerText = "*Email not found.";
          } else if (response.emailerror == "empty") {
               id("email-error").innerText = "*Required field";
          }  else {
               id("email-error").innerText = "";
          }

          if (response.passworderror == "true") {
               id("password-error").innerText = "*Your new password must be at least 6 characters long and contain no white space.";
          } else if (response.passworderror == "empty") {
               id("password-error").innerText = "*Required field";
          } else if (response.passworderror == "found") {
              id("password-error").innerText = "*Please enter a new password.";
          } else {
               id("password-error").innerText = "";
          }
          
          if (response.confirmerror == "true") {
              id("confirm-password-error").innerText = "*Passwords do not match";
          } else if (response.confirmerror == "empty") {
              id("confirm-password-error").innerText = "*Required Field";
          } else {
              id("confirm-password-error").innerText = "";
          }
          
          if (response.numbererror == "true") {
              alert("Error occured. Please retry clicking on the reset link you received.");
          } else {
              id("confirm-number-error").innerText = "";
          }
          
          if (response.emailerror == "" && response.passworderror == "" && response.confirmerror == "" && response.numbererror == "") {
              alert("Your password has been successfully changed. Click okay to sign in now.");
              window.location = "signin";
          }

     }

})();
