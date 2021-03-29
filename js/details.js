"use strict";

(function () {

     window.addEventListener('load', function () {
          const urlParams = new URLSearchParams(window.location.search)
          if (urlParams.get('origin') == "search") {
               id('back').innerText = '< back to search'
          }

          document.querySelector('.modal').onclick = function (e) {
               e.stopPropagation();
          }

          document.querySelector('.modal button').onclick = function () {
               this.parentElement.parentElement.parentElement.classList.add('hidden');
          }

          id('exit-address').addEventListener('click', close)
          id('back').addEventListener('click', navigateBack)
          id('exit-zip').addEventListener('click', close)
          id('zip-updater').classList.add('hidden')
          id('locationField').classList.add('hidden')
          id('addressDisplay').classList.add('hidden')
          id('edit').addEventListener('click', toggleAddressDisplay)

          let data = new FormData();
          data.append("session", getSession());
          let url = "php/session.php";
          fetch(url, { method: "POST", body: data, })
               .then(checkStatus)
               .then(generateRequest)
               .catch(console.log);

     });

     function toggleAddressDisplay() {
          id('autocomplete').classList.remove('hidden')
          id('addressDisplay').classList.add('hidden')
          id('locationField').classList.remove('hidden')
     }

     function generateRequest(response) {

          const urlParams = new URLSearchParams(window.location.search);

          let session = getSession();

          let data = new FormData();

          if (urlParams.get('address')) {
               data.append('address', urlParams.get('address'))
          }

          if (urlParams.get('city')) {
               data.append('city', urlParams.get('city'))
          }

          if (urlParams.get('state')) {
               data.append('state', urlParams.get('state'))
          }

          if (urlParams.get('zip')) {
               data.append('zip', urlParams.get('zip'))
          }

          if (session != '') {
               data.append('session', session);
          }

          data.append('service', urlParams.get('service'));

          fetchDetails(data)

     }

     function updateZip() {
          if (/(^\d{5}$)|(^\d{5}-\d{4}$)/.test(id('zip-input').value)) {

               const urlParams = new URLSearchParams(window.location.search)

               let url = '/details?service=' + urlParams.get('service');
               url += '&zip=' + id('zip-input').value
               url += '&origin=' + urlParams.get('origin')
               
               document.cookie = "zip=" + id('zip-input').value + ";";

               window.location = url;

          } else {
               let warningIcon = document.createElement('i')
               warningIcon.classList.add('fas', 'fa-exclamation-circle', 'warning')
               id('first').innerHTML = "";
               id('first').appendChild(warningIcon)

               document.querySelector('.modal-wrapper button').classList.remove('primary-green')
               document.querySelector('.modal-wrapper button').classList.add('secondary')
               document.querySelector('.modal-wrapper button').innerText = "OK, Retry"


                id('autocomplete').classList.add('hidden')
               id('warning-message').innerText = 'Please enter a valid US zip code.'
               document.querySelector('.modal-wrapper').classList.remove('hidden')

               document.querySelector('.modal-wrapper button').onclick = openZipcode

          }
     }

     async function fetchDetails(data) {

          let response = await fetch('php/results.php', { method: "POST", body: data });
          await checkStatus(response);
          let fullResponse = await response.json();

          let usingDBAddress = false;



          if (fullResponse.address && fullResponse.city && fullResponse.state && fullResponse.zip) {

               usingDBAddress = true;
               id('current-address').innerText = fullResponse.address;
               id('current-state').innerText = fullResponse.state;
               id('current-city').innerText = fullResponse.city;
               id('current-zip').innerText = fullResponse.zip

          }

          response = fullResponse.services[0];

          id('service').innerText = response.service
          id('image').src = response.src;
          id('description').innerText = response.description
          id('price').innerText = '$' + response.cost
          if (response.wage == 'per') {
               id('rate').innerText = 'Flat'
          } else {
               id('rate').innerText = 'Hourly'
          }

          if (response.available == 0) {

               id('availability').innerText = 'Unavailable'
               if (!fullResponse.city) {
                    if (fullResponse.zip) {
                         id('button').innerText = 'Unavailable in ' + fullResponse.zip + '. Click here to try a different zipcode.';
                         id('button').onclick = openZipcode
                    } else {
                         id('button').classList.add('disabled')
                         id('button').innerText = 'Unavailable';
                    }


               } else {
                    id('button').innerText = 'Unavailable in ' + fullResponse.city.charAt(0).toUpperCase() + fullResponse.city.slice(1) + ', ' + fullResponse.state + '. Click here to try a different address.';
                    id('button').onclick = openAddress
               }
               if (usingDBAddress) {
                    id('addressDisplay').classList.remove('hidden')
               }
          } else if (response.available == 1) {
               id('availability').innerText = 'Available'
               id('button').innerText = 'Book'
               id('button').onclick = function () {
                    let queryString = window.location.search
                    const urlParams = new URLSearchParams(queryString)
                    let zip = urlParams.get('zip')
                    let url = "edit?"
                    url += 'service=' + response.service
                    url += '&description=' + response.description
                    url += "&price=" + response.cost
                    url += "&wage=" + response.wage


                    url += "&origin=" + urlParams.get('origin')

                    if (zip) {
                         url += "&zip=" + zip
                    } else if (id('current-zip').innerText != "") {
                         url += '&zip=' + id('current-zip').innerText
                    }

                    url += "&remote=" + response.remote

                    if (id('current-address').innerText != "") {
                         url += "&address=" + id('current-address').innerText
                    }
                    if (id('current-city').innerText != "") {
                         url += "&city=" + id('current-city').innerText
                    }
                    if (id('current-state').innerText != "") {
                         url += "&state=" + id('current-state').innerText
                    }

                    if (urlParams.get('search')) {
                         url += "&search=" + urlParams.get('search')
                    } else if (urlParams.get('category')) {
                         url += "&category=" + urlParams.get('category')
                    }

                    url += '&back=details'
                    window.location = url
               }

          } else {
               id('availability').innerText = 'Uncertain'
               id('button').innerText = 'Click to check availability'

               id('button').onclick = openZipcode
          }


     }

     function navigateBack(e) {

          const urlParams = new URLSearchParams(window.location.search)

          let url = '';
          let back = urlParams.get('origin')
          if (back == 'search') {
               window.location = '/'
               return;
          } else {
               url += back + "?"
               if (urlParams.get('search')) {
                    url += '&search=' + urlParams.get('search')
               } else {
                    url += '&category=' + urlParams.get('category')
               }
          }

          if (urlParams.get('address')) {
               url += '&address=' + urlParams.get('address')
          }

          if (urlParams.get('city')) {
               url += '&city=' + urlParams.get('city')
          }

          if (urlParams.get('state')) {
               url += '&state=' + urlParams.get('state')
          }
          if (urlParams.get('zip')) {
               url += '&zip=' + urlParams.get('zip')
          }

          window.location = url
     }

     function openAddress() {
         id('first').innerHTML = ""
         id('first').innerText = "Enter your address to check availability in your area"
         
          id('autocomplete').classList.remove('hidden')
          
          document.querySelector('.modal-wrapper button').classList.add('hidden')
          
          document.querySelector('.modal-wrapper').classList.remove('hidden')
     }

     function openZipcode() {
          id('first').innerHTML = ""
          id('first').innerText = "Enter your zipcode to check availability in your area"

          id('warning-message').innerText = ""
          id('autocomplete').classList.add('hidden')
          let input = document.createElement('input')
          input.id = 'zip-input'
          input.type = 'number'
          input.placeholder = 'Enter Zipcode'
          id('update').onclick = updateZip
          id('warning-message').appendChild(input)

          document.querySelector('.modal-wrapper button').classList.remove('hidden')
          document.querySelector('.modal-wrapper button').innerText = "Update"
          document.querySelector('.modal-wrapper button').classList.remove('secondary')
          document.querySelector('.modal-wrapper button').classList.add('primary-green')
          document.querySelector('.modal-wrapper button').onclick = updateZip
          document.querySelector('.modal-wrapper').classList.remove('hidden')

     }


})();
