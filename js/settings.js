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
            window.location.href = "signin?redirect=settings";
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
        resetModal()
        id('yes').innerText = 'Yes, delete'
        id('yes').classList.add('primary-red')
        id('first').innerText = 'Are you sure you want to delete your account?'
        id('warning-message').innerText = 'All your data will be removed and your account will be closed. If you are a provider, this action will delete your Stripe account.'
        id('no').classList.add('secondary')
        id('no').innerText = "No, close modal"
        id('no').onclick = function () {
            document.querySelector('.modal-wrapper').classList.add('hidden')
        }
        document.querySelector('.modal-wrapper').classList.remove('hidden')
        id('yes').onclick = function () {

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
        let warningIcon = document.createElement('i')
        warningIcon.classList.add('fas')
        if (response.sessionerror == "true") {
            resetModal()
            id('no').classList.add('hidden')
            id('warning-message').innerText = "Please log out and try again.";
            warningIcon.classList.add('fa-exclamation-circle', 'warning')
            id('first').appendChild(warningIcon)
            document.querySelector('.modal-wrapper').classList.remove('hidden')
        } else if (response.stripeerror == "true") {
            resetModal()
            id('no').classList.add('hidden')
            id('warning-message').innerText = "Please make sure your Stripe balance is zero before deleting your account."
            warningIcon.classList.add('fa-exclamation-circle', 'warning')
            id('first').appendChild(warningIcon)
            document.querySelector('.modal-wrapper').classList.remove('hidden')
        } else if (response.ordererror == "true") {
            resetModal()
            id('no').classList.add('hidden')
            id('warning-message').innerText = "Please make sure you have no active orders before deleting your account."
            warningIcon.classList.add('fa-exclamation-circle', 'warning')
            id('first').appendChild(warningIcon)
            document.querySelector('.modal-wrapper').classList.remove('hidden')
        } else {
            resetModal()
            id('no').classList.add('hidden')
            warningIcon.classList.add('fa-check-circle', 'warning')
            id('first').appendChild(warningIcon)
            id('yes').innerText = "OK, go home"
            id('yes').classList.add('secondary')
            id('yes').onclick = function () {
                window.location.replace("https://helphog.com");
            }
            document.querySelector(".modal-wrapper").onclick = function () {
                window.location.replace("https://helphog.com");
            }

        }
    }

    function resetModal() {
        id('first').innerHTML = ""
        id('warning-message').innerHTML = ""

        id('yes').classList.remove('primary-green')
        id('yes').classList.remove('primary-red')
        id('yes').classList.remove('secondary')
        id('yes').classList.remove('hidden')
        id('yes').onclick = function () { };
        id('yes').innerText = ""

        id('no').classList.remove('primary-green')
        id('no').classList.remove('primary-red')
        id('no').classList.remove('secondary')
        id('yes').classList.remove('hidden')
        id('no').onclick = function () { };
        id('no').innerText = ""
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
