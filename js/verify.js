window.addEventListener('load', function () {

    const urlParams = new URLSearchParams(window.location.search)
    id('message').innerText = urlParams.get('message');

    if (urlParams.get('email') == 'none') {
        id('resend-email').innerText = '';
    }

});