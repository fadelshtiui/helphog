"use strict";

(function () {

    window.addEventListener('load', function () {
        profile();

        let data = new FormData();
        data.append("session", getSession());
        let url = "php/session.php";
        fetch(url, { method: "POST", body: data })
            .then(checkStatus)
            .then(res => res.json())
            .then(redirect)
            .catch(console.log);

        id("profile").onclick = profile;
        id("payment").onclick = payment;
        id("settings").onclick = settings;
        id("delete").onclick = deleteAccount;

        id('logo').onclick = goHome;

        id('locationField').classList.add('hidden')

        id('edit').addEventListener('click', toggleAddressView)

        id('locationField').classList.add('hidden')

    });

    function toggleAddressView() {
        id('locationField').classList.remove('hidden')
        id('addressDisplay').classList.add('hidden')
        id('autocomplete').classList.remove('hidden')
    }

    function goHome() {
        window.location.href = '/'
    }

    function redirect(response) {
        if (response.validated == "false") {
            window.location.href = "/";
        } else {
            initialize(response.account.phone, response.account.email, response.account.address, response.account.city, response.account.state, response.account.zip);
        }
    }

    function profile() {
        let payment = document.querySelector(".payment");
        payment.classList.add("hidden");
        let settings = document.querySelector(".settings");
        settings.classList.add("hidden");
        let profile = document.querySelector(".profile");
        profile.classList.remove("hidden");
        id("payment").classList.remove("active");
        id("settings").classList.remove("active");
        id("profile").classList.add("active");
    }

    function initialize(phone, email, address, city, state, zip) {

        id("phone").innerText = phone;
        id("email").innerText = email;

        if (address && city && state && zip) {
            id("current-address").innerText = address;
            id("current-city").innerText = city;
            id("current-state").innerText = state;
            id("current-zip").innerText = zip;
        } else {
            id('city-state-comma').classList.add('hidden')
            id('current-address').innerText = 'No Address'
        }

    }

    function payment() {
        let profile = document.querySelector(".profile");
        profile.classList.add("hidden");
        let settings = document.querySelector(".settings");
        settings.classList.add("hidden");
        let payment = document.querySelector(".payment");
        payment.classList.remove("hidden");
        id("payment").classList.add("active");
        id("settings").classList.remove("active");
        id("profile").classList.remove("active");
    }

    function subscription() {
        let payment = document.querySelector(".payment");
        payment.classList.add("hidden");
        let profile = document.querySelector(".profile");
        profile.classList.add("hidden");
        let settings = document.querySelector(".settings");
        settings.classList.add("hidden");
        id("payment").classList.remove("active");
        id("settings").classList.remove("active");
        id("profile").classList.remove("active");
    }

    function privacy() {
        let payment = document.querySelector(".payment");
        payment.classList.add("hidden");
        let profile = document.querySelector(".profile");
        profile.classList.add("hidden");
        let settings = document.querySelector(".settings");
        settings.classList.add("hidden");
        id("payment").classList.remove("active");
        id("settings").classList.remove("active");
        id("profile").classList.remove("active");
    }

    function settings() {
        let payment = document.querySelector(".payment");
        payment.classList.add("hidden");
        let profile = document.querySelector(".profile");
        profile.classList.add("hidden");
        let settings = document.querySelector(".settings");
        settings.classList.remove("hidden");
        id("payment").classList.remove("active");
        id("settings").classList.add("active");
        id("profile").classList.remove("active");
    }

    function deleteAccount() {
        if (confirm('Are you sure you want to delete your account? All your data will be removed and your account will be closed. If you are a provider, this action will delete your Stripe account.')) {

            let data = new FormData();
            data.append("session", getSession());
            let url = "php/deleteaccount.php";
            fetch(url, { method: "POST", body: data, mode: 'cors', credentials: 'include' })
                .then(checkStatus)
                .then(res => res.json())
                .then(handleDeleteResponse)
                .catch(console.log);

        }
    }

    function handleDeleteResponse(response) {
        if (response.sessionerror == "true") {
            id('warning-message').innerText = "Please log out and try again."
            document.querySelector('.modal-wrapper').classList.remove('hidden')
        } else if (response.stripeerror == "true") {
            id('warning-message').innerText = "Please make sure your Stripe balance is zero before deleting your account."
            document.querySelector('.modal-wrapper').classList.remove('hidden')
        } else if (response.ordererror == "true") {
            id('warning-message').innerText = "Please make sure you have no active orders before deleting your account."
            document.querySelector('.modal-wrapper').classList.remove('hidden')
        } else {
            document.querySelector('.modal-wrapper i').classList.remove('warning')
            document.querySelector('.modal-wrapper i').classList.add('success')
            document.querySelector('.modal-wrapper button').onclick = function () {
                window.location.replace("https://helphog.com");
            }
            document.querySelector(".modal-wrapper").onclick = function () {
                window.location.replace("https://helphog.com");
            }

        }
    }


})();

function addressUpdate() {

    if (id("address").innerText == '' || id("current-city").innerText == '' || id("current-zip").innerText == '' || id("current-state").innerText == '') {
        id('warning-message').innerText = "Please enter a full address."
        document.querySelector('.modal-wrapper').classList.remove('hidden')
        return
    }

    let data = new FormData();

    data.append("address", id("current-address").innerText);
    data.append("city", id("current-city").innerText);
    data.append("zip", id("current-zip").innerText);
    data.append("state", id("current-state").innerText);
    data.append("session", getSession());
    let url = "php/settings.php";
    fetch(url, { method: "POST", body: data })
        .then(checkStatus)
        .then(res => res.json())
        // .then(handleAddressResponse)
        .catch(console.log);

}

// function handleAddressResponse(response) {
//       if (response.sessionerror == "false") {
//           if (response.ziperror == "true") {
//           id("zip-error").innerText = "*Invalid zip code.";
//           } else {
//               id("zip-error").innerText = "";
//           }
//           if(response.ziperror == "") {
//               alert("Your address has been successfully updated.");
//               location.reload();
//           }
//       } else {
//           alert("Please try logging out, and then logging in again.");
//           window.location.href = "index";
//       }

//      }
