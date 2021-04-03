/* global fetch */

"use strict";

(function () {

    window.addEventListener('load', async function () {

        id('submit').onclick = submit;

        let data = new FormData();
        data.append('session', getSession())
        let res = await fetch("php/checkapplied", { method: "POST", body: data })
        await checkStatus(res)
        res = await res.text()

        if (res == "accepted") {
            window.location = "verify?message=Please+reach+out+to+us+directly+if+you+would+like+to+apply+to+provide+more+services.&email=none"
        } else if (res == "applied") {
            window.location = "verify?message=Your+application+has+been+submitted.+Please+be+on+the+lookout+for+an+email+from+our+hiring+team.+Check+your+junk+folder+if+you+don%27t+see+our+email+within+24+hours+of+applying.&email=none"
        }

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