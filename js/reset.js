"use strict";

(function () {

     window.onload = function () {

          id("provider").style.display = "none";

          let data = new FormData();
          data.append("session", getSession())
          let url = "php/session.php"
          fetch(url, { method: "POST", body: data })
               .then(checkStatus)
               .then(res => res.json())
               .then(updateNav)
               .catch(console.log);

          id("reset-btn").onclick = reset;
          id("confirm").addEventListener("keyup", function (event) {
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

     async function reset() {
          qs('.buttonloadicon').classList.remove('hidden')
          id('reset-btn').disabled = true
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
          try {
               let res = await fetch(url, { method: "POST", body: data })
               await checkStatus(res)
               res = await res.json()
               handleResponse(res)
          } catch (err) {
               console.error(err)
          }
          qs('.buttonloadicon').classList.add('hidden')
          id('reset-btn').disabled = false
     }

     function handleResponse(response) {
          if (response.emailerror == "true") {
               id("email-error").innerText = "*Email not found.";
          } else if (response.emailerror == "empty") {
               id("email-error").innerText = "*Required field";
          } else {
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

          if (response.numbererror == "true" || response.numbererror == "empty") {
               id('warning-message').innerText = "Error occured. Please retry clicking on the reset link you received."
               let warningIcon = ce('i')
               warningIcon.classList.add('fas', 'fa-exclamation-circle', 'warning')
               id('first').innerHTML = ''
               id('first').appendChild(warningIcon)
               qs('.modal-wrapper').classList.remove('hidden')

          } else {
               id("confirm-number-error").innerText = "";
          }

          if (response.emailerror == "" && response.passworderror == "" && response.confirmerror == "" && response.numbererror == "") {
               id('warning-message').innerText = "Error occured. Please retry clicking on the reset link you received."
               let warningIcon = ce('i')
               warningIcon.classList.add('fas', 'fa-check-circle', 'success')
               id('first').innerHTML = ''
               id('first').appendChild(warningIcon)
               qs('.modal-wrapper').classList.remove('hidden')

               qs('.modal button').innerText = "OK, Login"
               qs('.modal button').onclick = function () {
                    window.location = "signin";
               }

          }

     }

})();
