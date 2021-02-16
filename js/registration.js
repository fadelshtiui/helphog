"use strict";

(function () {

    window.addEventListener('load', function () {
        id('left').onclick = form;
        id('right').onclick = application;

        id("provider").classList.add("hidden");

        let data = new FormData();
        data.append("session", getSession())
        let url = "php/session.php"
        fetch(url, { method: "POST", body: data })
            .then(checkStatus)
            .then(res => res.json())
            .then(updateNav)
            .catch(console.log);

    });

    function updateNav(response) {
        if (response.validated == "true" && response.account.type == "Business") {
            id("provider").classList.remove("hidden");
        }
    }

    function form() {
        window.location = "signup";
    }

    function application() {
        window.location = "signin?redirect=quickapply";
    }

})();
