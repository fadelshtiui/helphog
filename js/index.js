(function () {
     //  var iOS = /iPad|iPhone|iPod/.test(navigator.platform || "");
     //  if (iOS === true) {
     //       window.location.href = "helphog://"
     //  }

     var displayResults, findAll, maxResults, resultsOutput, searchInput;
     let services = [];

     let url = "php/info.php?type=services";
     fetch(url)
          .then(checkStatus)
          .then(res => res.json())
          .then(handleResponse)
          .catch(console.log);

     function handleResponse(response) {
          services = response;
     }

     findAll = (wordList, collection) => {
          return collection.filter(function (word) {
               word = word.toLowerCase();
               return wordList.some(function (w) {
                    return ~word.indexOf(w);
               });
          });
     };

     displayResults = function (resultsEl, wordList) {
          resultsEl.innerHTML = '';
          wordList.forEach(word => {
               let entry = ce('li');
               entry.textContent = word;
               entry.addEventListener('click', function () {
                    let url = 'details?service=' + word + "&origin=search"
                    if (getSession() == '' && getZip() != "") {
                         url += '&zip=' + getZip()
                    }
                    window.location = url;
               })
               resultsEl.appendChild(entry);
          })
     };

     searchInput = document.getElementById('search');
     resultsOutput = document.getElementById('results');


     searchInput.addEventListener('keyup', (e) => {
          var suggested, value;
          value = searchInput.value.toLowerCase().split(' ');
          suggested = (value[0].length ? findAll(value, services) : []);
          return displayResults(resultsOutput, suggested);
     });

})();
