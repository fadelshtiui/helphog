"use strict";

(function () {

    window.addEventListener('load', function () {
        id('extra-prompt').classList.add('hidden')
        let queryString = window.location.search
        const urlParams = new URLSearchParams(queryString)
        if (urlParams.get('redirect') && urlParams.get('redirect') == 'quickapply') {
            id('extra-prompt').classList.remove('hidden')
        }

        id("login-btn").onclick = login;
        id("password").addEventListener("keyup", function (event) {
            event.preventDefault();
            if (event.keyCode === 13) {
                id("login-btn").click();
            }
        });
    });

    function login() {
        let data = new FormData();
        let email = id("email").value.toLowerCase();
        let password = id("password").value;
        data.append("email", email);
        data.append("password", password);
        let url = "php/signin.php"
        fetch(url, { method: "POST", body: data })
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
        } else {
            id("email-error").innerText = "";
        }

        if (response.passworderror == "true") {
            id("password-error").innerText = "*Incorrect Password.";
        } else if (response.passworderror == "empty") {
            id("password-error").innerText = "*Required field";
        } else {
            id("password-error").innerText = "";
        }

        if (response.verified == "n") {
            id('warning-message').innerText = "Your account has not yet been verified. Please check your email for a verification email."
            document.querySelector('.modal-wrapper').classList.remove('hidden')
        } else if (response.emailerror == "" && response.passworderror == "") {
            document.cookie = "session=" + response.session + ";";
            let queryString = window.location.search
            const urlParams = new URLSearchParams(queryString)
            if (urlParams.get('redirect')) {
                window.location = urlParams.get('redirect')
            } else {
                window.location = '/'
            }
        }
    }

})();
