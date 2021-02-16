window.onload = function () {
    let data = new FormData();
    const queryString = window.location.search
    const urlParams = new URLSearchParams(queryString)
    const orderNumber = urlParams.get('ordernumber')
    const secret = urlParams.get('secret')

    data.append("ordernumber", orderNumber)
    data.append('secret', secret)

    let url = "php/checkifcancel.php";
    fetch(url, { method: "POST", body: data })
        .then(checkStatus)
        .then(res => res.json())
        .then(handleResponse)
        .catch(console.log);


    id('notcancel').onclick = function () {
        window.location = "https://helphog.com"
    }
    id('cancel').onclick = cancel
}

function handleResponse(response) {
    let date = new Date(response.schedule + " GMT");

    id("first").textContent = "Are you sure you want to cancel " + response.service + " (" + response.order + ") on " + date.toLocaleString() + "?";
    if (response.within == "true") {
        id("second").textContent = "Since your order is within 24 hours of now, a non-refundable fee of $15 will be charged to your account."
    } else {
        id("second").textContent = "You may cancel your order now free of charge."
    }
}

function cancel() {
    const queryString = window.location.search
    const urlParams = new URLSearchParams(queryString)
    const orderNumber = urlParams.get('ordernumber')
    const secret = urlParams.get('secret')
    window.location = "https://helphog.com/php/customercancel.php?ordernumber=" + orderNumber + "&secret=" + secret
}