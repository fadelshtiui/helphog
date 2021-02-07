"use strict";

(function() {

    window.addEventListener('load', function() {
        
        const urlParams = new URLSearchParams(window.location.search)
        
        id("keyword").innerText = urlParams.get('search')
        
        let data = new FormData();
        
        data.append('searchterm', urlParams.get('search'))
        data.append('session', getSession())
        let url = "php/notfound.php"
        fetch(url, { method: "POST", body: data })
        
     });

})();
