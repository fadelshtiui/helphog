"use strict";

(function () {

    window.addEventListener('load', function () {
        id('register').onclick = form;
        id('apply2').onclick = application;

    });

    function form() {
        window.location = "signup";
    }

    function application() {
        window.location = "signin?redirect=quickapply";
    }

})();
