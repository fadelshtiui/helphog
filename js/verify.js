window.addEventListener('load', function() {
    
	const urlParams = new URLSearchParams(window.location.search)
    id('message').innerText = urlParams.get('message');
    
});