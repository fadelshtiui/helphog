/* global fetch */

"use strict";

(function () {

    window.addEventListener('load', function () {

        id('submit').onclick = submit;

        fetch("php/info.php?type=categories")
            .then(checkStatus)
            .then(res => res.json())
            .then(populateCategories)
            .catch(console.log);

    });

    function populateCategories(response) {
        let dropdown = id("dropdown");
        for (let i = 0; i < response.length; i++) {
            let option = document.createElement("option");
            option.value = response[i];
            option.innerText = response[i].charAt(0).toUpperCase() + response[i].slice(1);
            dropdown.appendChild(option);
        }
    }

    function submit() {
        // $('.btn').button('loading');

        let session = "";
        let cookies = document.cookie.split(";");
        for (let i = 0; i < cookies.length; i++) {
            let key = cookies[i].split("=");
            if (key[0].trim() == "session") {
                session = key[1];
            }
        }

        let workfield = id("dropdown").value;
        let experience = id("aratext").value;
        let radius = '0';

        if (id("aratext").value === "") {
            id('warning-message').innerText = "Please type a brief description of your experience in this workfield";
            document.querySelector('.modal-wrapper').classList.remove('hidden')
            // $('.btn').button('reset');
        } else {

            let data = new FormData();

            let attachedResume = id('resume').files[0];

            if (attachedResume) {
                let resume = id('resume').files[0];
                data.append('resume', resume, resume.name);
            }

            let tz = jstz.determine();
            let timezone = tz.name();

            data.append("session", session);
            data.append("experience", experience);
            data.append("workfield", workfield);
            data.append('radius', radius)
            data.append('tz', timezone);

            let url = "php/apply.php";
            fetch(url, { method: "POST", body: data, mode: 'cors', credentials: 'include' })
                .then(checkStatus)
                .then(res => res.text())
                .then(handleResponse)
                .catch(console.log);
        }
    }

    function handleResponse(response) {
        if (response == "Please upload PNG, JPG, PDF, DOCX files only") {
            id('warning-message').innerText = response
            document.querySelector('.modal-wrapper').classList.remove('hidden')
            // $('.btn').button('reset');

        } else {
            // $('.btn').button('loading');
            window.location.href = response;
        }
    }

})();