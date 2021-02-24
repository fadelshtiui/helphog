"use strict";

(function () {

    window.addEventListener('load', function () {

        const urlParams = new URLSearchParams(window.location.search)
        if (urlParams.get('search')) {
            id('text').innerText = "Sorry, we have not found your request for: " + urlParams.get('search');
            id('message').innerText = "As a newborn organization we are working hard on adding more services to our site.\
                                        We value your patience and the opportunity to serve you.\
                                        To make it up to you, we have added your search to our database and will implement your request as soon as possible. Thank You."
            id("keyword").innerText = urlParams.get('search')
            let data = new FormData();
            data.append('searchterm', urlParams.get('search'))
            data.append('session', getSession())
            let url = "php/notfound.php"
            fetch(url, { method: "POST", body: data })
        }


    });

})();
