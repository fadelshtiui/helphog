"use strict";

(function () {

    window.addEventListener('load', function () {

        id("reset-password").onclick = reset;
        id("email").addEventListener("keyup", function (event) {
            event.preventDefault();
            if (event.keyCode === 13) {
                button.click();
            }
        });

    });


    function reset() {
        if (isCaptchaChecked()) {
            let data = new FormData();
            let email = id("email").value.toLowerCase();
            data.append("email", email);
            let url = "php/forgot.php";
            fetch(url, { method: "POST", body: data, mode: 'cors', credentials: 'include' })
                .then(checkStatus)
                .then(res => res.json())
                .then(handleResponse)
                .catch(console.log);
        } else {
            id('warning-message').innerText = "Please check the reCAPTCHA box."
            document.querySelector('.modal-wrapper').classList.remove('hidden')

            id('first').innerHTML = ''
            let warningIcon = document.createElement('i')
            warningIcon.classList.add('fas', 'fa-exclamation-circle', 'warning');
            id('first').appendChild(warningIcon)
        }

    }

    function handleResponse(response) {
        if (response.emailerror == "notfound") {
            id("email-error").innerText = "*Email not found.";
        } else if (response.emailerror == "empty") {
            id("email-error").innerText = "*Required field";
        } else {
            id("email-error").innerText = "";
            id("reset-password").innerText = "Re-send Email";
            id('warning-message').innerText = "Thank you! Please check your email for a reset link."


            id('first').innerHTML = ''
            let warningIcon = document.createElement('i')
            warningIcon.classList.add('fas', 'fa-check-circle', 'success');
            id('first').appendChild(warningIcon)


            document.querySelector('.modal-wrapper').classList.remove('hidden')

        }
    }

    function isCaptchaChecked() {
        return grecaptcha && grecaptcha.getResponse().length !== 0;
    }

})();
