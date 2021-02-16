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
            alert("Please check the reCAPTCHA box.")
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
            alert("Thank you! Please check your email for a reset link. Click re-send if you don't receive the email.");
        }
    }

    function isCaptchaChecked() {
        return grecaptcha && grecaptcha.getResponse().length !== 0;
    }

})();
