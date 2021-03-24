"use strict";

(function () {

     window.addEventListener('load', function () {
          const urlParams = new URLSearchParams(window.location.search)
          if (urlParams.get('origin') == "search") {
               id('back').innerText = '< back to search'
          }


          id('exit-address').addEventListener('click', close)
          id('back').addEventListener('click', navigateBack)
          id('exit-zip').addEventListener('click', close)
          id('zip-updater').classList.add('hidden')
          id('locationField').classList.add('hidden')
          id('addressDisplay').classList.add('hidden')
          id('edit').addEventListener('click', toggleAddressDisplay)
          id('update').addEventListener('click', updateZip)


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

               window.location = url;

          } else {
               alert('Please enter a valid US zip code.')
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

          console.log(usingDBAddress)

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
                         id('button').addEventListener('click', openZipcode)
                    } else {
                         id('button').classList.add('disabled')
                         id('button').innerText = 'Unavailable';
                    }


               } else {
                    id('button').innerText = 'Unavailable in ' + fullResponse.city.charAt(0).toUpperCase() + fullResponse.city.slice(1) + ', ' + fullResponse.state + '. Click here to try a different address.';
                    id('button').addEventListener('click', openAddress)
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

               id('button').onclick = function () {

                    id('zip-updater').classList.remove('hidden');

               }
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

     let updateAvailability = async (url, newZip) => {
          let response = await fetch(url)
          await checkStatus(response)
          response = await response.json()

          if (response.available == 0) {
               id('button').classList.add('disabled')
               id('availability').innerText = 'Unavailable'
               id('button').innerText = 'Unavailable in ' + newZip
          } else if (response.available == 1) {
               const urlParams = new URLSearchParams(window.location.search)
               let url = "edit?service=" + response.service + "&description=" + response.description + "&price=" + response.cost + "&wage=" + response.wage + "&zip=" + newZip + "&remote=" + response.remote + '&back=details';
               url += '&origin=' + urlParams.get('origin')
               if (urlParams.get('search')) {
                    url += "&search=" + urlParams.get('search')
               } else if (urlParams.get('category')) {
                    url += "&category=" + urlParams.get('category')
               }
          } else {
               id('availability').innerText = 'Uncertain'
               id('button').innerText = 'Click to check availability'
               id('button').onclick = function () {
                    let zip = prompt('Please enter a valid zip code: ')
                    updateAvailability('php/details.php?service=' + response.service + '&zip=' + zip, zip)
               }
          }

     }

     function close() {
          console.log("works")
          id('address-updater').classList.add('hidden')
          id('zip-updater').classList.add('hidden')
     }

     function openAddress() {
          id('address-updater').classList.remove('hidden')
     }

     function openZipcode() {
          id('zip-updater').classList.remove('hidden')

     }


})();
