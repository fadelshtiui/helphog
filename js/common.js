"use strict";

window.addEventListener('load', checkLoggedIn);
window.addEventListener('load', populateFooter)

function getSession() {
    let cookies = document.cookie.split(";");
    let session = "";
    for (let i = 0; i < cookies.length; i++) {
        let key = cookies[i].split("=");
        if (key[0].trim() == "session") {
            session = key[1];
        }
    }
    return session;
}

function getZip() {
    let cookies = document.cookie.split(";");
    let zip = "";
    for (let i = 0; i < cookies.length; i++) {
        let key = cookies[i].split("=");
        if (key[0].trim() == "zip") {
            zip = key[1];
        }
    }
    return zip;
}

function signOut() {
    let cookies = document.cookie.split(";");
    for (let i = 0; i < cookies.length; i++) {
        let cookie = cookies[i];
        let eqPos = cookie.indexOf("=");
        let name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
        document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT";
    }
    window.location = "/";
}

async function checkStatus(res) {
    if (!res.ok) {
        throw new Error(await res.text());
    }
    return res;
}

function checkLoggedIn() {
    if (id('navigation')) {

        let data = new FormData();
        data.append("session", getSession());
        let url = "php/session.php";
        fetch(url, { method: "POST", body: data })
            .then(checkStatus)
            .then(res => res.json())
            .then(updateNav)
            .catch(console.log);
    }

}

function updateNav(response) {

    if (response.validated == "true") {
        // id("sign-out").onclick = signOut;
        if (response.account.type == "Business") {
            id("provider").classList.remove("hidden");
            id("apply").classList.add('hidden')
        }

        id('signed-in-nav').classList.remove('hidden')
    } else {
        id('signed-out-nav').classList.remove('hidden')
    }

}

/**
 * returns the DOM element associated with the given id
 *
 * @param {string} id - id of html element
 * @returns {Object} DOM element associated with the given id
 */
function id(id) {
    return document.getElementById(id);
}

function qs(selector) {
    return document.querySelector(selector);
}

function qsa(selector) {
    return document.querySelectorAll(selector);
}

function ce(tag) {
    return document.createElement(tag);
}

const isNumericInput = (event) => {
    const key = event.keyCode;
    return ((key >= 48 && key <= 57) || // Allow number line
        (key >= 96 && key <= 105) // Allow number pad
    );
};

const isModifierKey = (event) => {
    const key = event.keyCode;
    return (event.shiftKey === true || key === 35 || key === 36) || // Allow Shift, Home, End
        (key === 8 || key === 9 || key === 13 || key === 46) || // Allow Backspace, Tab, Enter, Delete
        (key > 36 && key < 41) || // Allow left, up, right, down
        (
            // Allow Ctrl/Command + A,C,V,X,Z
            (event.ctrlKey === true || event.metaKey === true) &&
            (key === 65 || key === 67 || key === 86 || key === 88 || key === 90)
        )
};

const enforceFormat = (event) => {
    // Input must be of a valid number format or a modifier key, and not longer than ten digits
    if (!isNumericInput(event) && !isModifierKey(event)) {
        event.preventDefault();
    }
};

const formatToPhone = (event) => {
    if (isModifierKey(event)) { return; }

    // I am lazy and don't like to type things more than once
    const target = event.target;
    const input = target.value.replace(/\D/g, '').substring(0, 10); // First ten digits of input only
    const zip = input.substring(0, 3);
    const middle = input.substring(3, 6);
    const last = input.substring(6, 10);

    if (input.length > 6) { target.value = `(${zip}) ${middle} - ${last}`; }
    else if (input.length > 3) { target.value = `(${zip}) ${middle}`; }
    else if (input.length > 0) { target.value = `(${zip}`; }
};

async function populateFooter() {

    let response = await fetch("php/info.php?type=categories")
    await checkStatus(response)
    response = await response.json()

    let list = qs('.nav__ul--extra')

    if (list) {
        for (let i = 0; i < response.length; i++) {

            let category = response[i];

            let entry = ce('li')

            let link = ce('a')
            link.innerText = category.charAt(0).toUpperCase() + category.slice(1)
            link.href = 'results?category=' + category

            entry.appendChild(link)

            list.appendChild(entry)

        }
    }

}
