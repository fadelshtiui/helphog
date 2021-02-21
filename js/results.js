/* global fetch */

"use strict";

(function () {

     window.onload = function () {

        id('loading').classList.remove('hidden');
          let availabilityFilters = document.querySelectorAll('#availability-filters input')
          for (let i = 0; i < availabilityFilters.length; i++) {
               availabilityFilters[i].onchange = filterAvailability;
          }

          id('address-updater').classList.add('hidden')
          id('locationField').classList.add('hidden')
          // id('locationFieldGuest').classList.add('hidden')
          id('zip-updater').classList.add('hidden')
          id('city-state-comma').classList.add('hidden')

          fetchCategories("php/info.php?type=categories")

          id('edit').addEventListener('click', toggleAddressInput)

          id('update').addEventListener('click', removeSpotlight)

          $('input').attr('autocomplete', 'off')


     };

     function toggleAddressInput() {
          id('addressDisplay').classList.add('hidden');
          id('locationField').classList.remove('hidden');
          id('autocomplete').classList.remove('hidden');
     }

     let fetchCategories = async (url) => {
          let response = await fetch(url)
          let result = await response.json()

          let queryString = window.location.search
          const urlParams = new URLSearchParams(queryString)

          let searchURL = "php/results.php"

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

          if (urlParams.get('search')) {
               let search = urlParams.get('search')
               data.append("search", search)
          } else if (urlParams.get('category')) {
               let category = urlParams.get('category')
               data.append("category", category)
          }

          let session = getSession()

          if (session != '') {
               data.append("session", session)
          }

          if (urlParams.get('zip')) {
               data.append("zip", urlParams.get('zip'))
          }

          fetchResults(searchURL, data)
     }

     let fetchResults = async (url, data) => {

          let response = await fetch(url, { method: "POST", body: data })
          await checkStatus(response)
          let result = await response.json()
          handleResponse(result)
     }

     function populateCategories(response) {

          // footer
          let list = document.querySelector('.nav__ul--extra')

          for (let i = 0; i < response.length; i++) {

               let category = response[i];

               let entry = document.createElement('li')

               let link = document.createElement('a')
               link.innerText = category.charAt(0).toUpperCase() + category.slice(1)
               link.href = 'results?category=' + category

               entry.appendChild(link)

               list.appendChild(entry)

          }

          for (let i = 0; i < response.length; i++) {
               let category = response[i];

               let filterContainer = document.createElement('div')
               filterContainer.classList.add('filters__item')

               let checkboxContainer = document.createElement('div')
               checkboxContainer.classList.add('checkbox')

               let checkbox = document.createElement('input')
               checkbox.type = 'checkbox'
               checkbox.dataset.category = category
               checkbox.id = 'checkbox-' + (i + 1)

               let label = document.createElement('label')
               label.textContent = category.charAt(0).toUpperCase() + category.slice(1)
               label.htmlFor = checkbox.id

               let box = document.createElement('span')
               box.classList.add('box')

               label.appendChild(box)

               checkboxContainer.appendChild(checkbox)
               checkboxContainer.appendChild(label)

               let numResults = document.createElement('span')
               numResults.classList.add('badge')
               numResults.classList.add('status-primary')
               numResults.dataset.category = category

               filterContainer.appendChild(checkboxContainer)
               filterContainer.appendChild(numResults)

               id('category-filters').appendChild(filterContainer)
          }

          let categoryFilters = document.querySelectorAll('#category-filters input')
          for (let i = 0; i < categoryFilters.length; i++) {
               categoryFilters[i].onchange = filterCategory;
          }

     }

     function filterCategory() {
          let filters = document.querySelectorAll('#category-filters input')
          for (let i = 0; i < filters.length; i++) {
               if (filters[i] != this) {
                    filters[i].checked = false;
               }
          }

          if (this.checked) {
               let currAvailability
               filters = document.querySelectorAll('#availability-filters input')
               for (let i = 0; i < filters.length; i++) {
                    if (filters[i].checked) {
                         currAvailability = filters[i].dataset.availability
                    }
               }

               filter(this.dataset.category, currAvailability)
          }
     }

     function filterAvailability() {
          let filters = document.querySelectorAll('#availability-filters input')
          for (let i = 0; i < filters.length; i++) {
               if (filters[i] != this) {
                    filters[i].checked = false;
               }
          }

          if (this.checked) {
               let currCategory
               filters = document.querySelectorAll('#category-filters input')
               for (let i = 0; i < filters.length; i++) {
                    if (filters[i].checked) {
                         currCategory = filters[i].dataset.category
                    }
               }

               filter(currCategory, this.dataset.availability)
          }


     }

     function filter(category, availability) {
          let results = document.querySelectorAll('.profile')
          for (let i = 0; i < results.length; i++) {
               let categoryMatch = (category == 'all' || results[i].dataset.category == category)
               let availabilityMatch = (availability == 'all' || results[i].dataset.available == 1)

               if (categoryMatch && availabilityMatch) {
                    results[i].classList.remove('hidden')
               } else {
                    results[i].classList.add('hidden')

               }
          }

     }

     function handleResponse(response) {
          if (response.services.length == 0) {
               let queryString = window.location.search
               const urlParams = new URLSearchParams(queryString)
               window.location = "notfound?search=" + urlParams.get('search')
          } else {

               if (response.address && response.state && response.city) {
                    id('current-address').innerText = response.address;
                    id('current-state').innerText = response.state;
                    id('current-city').innerText = response.city;
                    id('current-zip').innerText = response.zip

                    id('edit').classList.remove('hidden')
                    id('address-updater').classList.remove('hidden')
                    id('city-state-comma').classList.remove('hidden')
               } else {

                    id('zip-updater').classList.remove('hidden')
                    if (response.zip) {
                         id('zip-input').value = response.zip
                         // id('current-zip-guest').innerText =
                    }
               }



               let counters = document.querySelectorAll('.badge')
               for (let i = 0; i < counters.length; i++) {
                    let counter = counters[i]
                    counter.innerText = '0'
                    if (response.counts[counter.dataset.category]) {
                         counter.innerText = response.counts[counter.dataset.category];
                    }

               }

               id('total').innerText = response.services.length;
               id('show-all').innerText = response.services.length;
               id('only-available').innerText = response.available;

               for (let i = 0; i < response.services.length; i++) {
                    let service = response.services[i]

                    let results = document.querySelector('.results-section')

                    let result = document.createElement('div')
                    result.classList.add('profile')
                    result.dataset.category = service.category
                    result.dataset.available = service.available

                    let imageContainer = document.createElement('div');
                    imageContainer.classList.add('profile__image')

                    let image = document.createElement('img')
                    image.src = service.src

                    imageContainer.appendChild(image)

                    result.appendChild(imageContainer)

                    let infoContainer = document.createElement('div')
                    infoContainer.classList.add('profile__info')

                    let title = document.createElement('h3')
                    title.innerText = service.service

                    let x = 70
                    // while (service.description.charAt(x) != " " || service.description.charAt(x) != "," || service.description.charAt(x) != "." || service.description.charAt(x) != "-") {
                    //     x--
                    // }

                    let description = document.createElement('p')
                    description.classList.add('profile__info__extra')
                    description.innerText = service.description.substring(0, x)

                    let queryString = window.location.search
                    const urlParams = new URLSearchParams(queryString)
                    let zip = urlParams.get('zip')

                    let more = document.createElement('span')
                    more.innerText = " ...(view more)"
                    more.classList.add('more')
                    description.appendChild(more)
                    more.onclick = function () {
                         let url = 'details?service=' + service.service

                         if (id('current-address').innerText != '') {
                              url += '&address=' + id('current-address').innerText
                         }

                         if (id('current-city').innerText != '') {
                              url += '&city=' + id('current-city').innerText
                         }

                         if (id('current-state').innerText != '') {
                              url += '&state=' + id('current-state').innerText
                         }

                         if (id('current-zip').innerText != '') {
                              url += '&zip=' + id('current-zip').innerText
                         } else if (id('zip-input').value) {
                              url += '&zip=' + id('zip-input').value;
                         } else if (zip) {
                              url += '&zip=' + zip
                         }
                         url += "&origin=results";
                         url += '&search=' + urlParams.get('search')

                         window.location = url;
                    }

                    infoContainer.appendChild(title)
                    infoContainer.appendChild(description)

                    result.appendChild(infoContainer)

                    let availabilityContainer = document.createElement('div')
                    availabilityContainer.classList.add('profile__stats')

                    let availabilityTitle = document.createElement('p')
                    availabilityTitle.classList.add('profile__stats__title')
                    availabilityTitle.innerText = 'Availability'

                    let availabilityValue = document.createElement('h5')
                    availabilityValue.classList.add('profile__stats__info')
                    availabilityValue.innerText = 'Unavailable'
                    if (service.available) {
                         availabilityValue.innerText = 'Available'
                    }

                    availabilityContainer.appendChild(availabilityTitle)
                    availabilityContainer.appendChild(availabilityValue)

                    result.appendChild(availabilityContainer)

                    let priceContainer = document.createElement('div')
                    priceContainer.classList.add('profile__stats')

                    let priceTitle = document.createElement('p')
                    priceTitle.classList.add('profile__stats__title')
                    priceTitle.style.marginLeft = '22px';
                    priceTitle.innerText = 'Price'

                    let priceValue = document.createElement('h5')
                    priceValue.innerText = '$' + service.cost
                    priceValue.style.marginLeft = '22px';

                    priceContainer.appendChild(priceTitle)
                    priceContainer.appendChild(priceValue)

                    result.appendChild(priceContainer)

                    let rateContainer = document.createElement('div')
                    rateContainer.classList.add('profile__stats')

                    let rateTitle = document.createElement('p')
                    rateTitle.classList.add('profile__stats__title')
                    rateTitle.innerText = 'Rate'

                    let rateValue = document.createElement('h5')
                    rateValue.classList.add('profile__stats__info')


                    if (service.wage == 'per') {
                         rateValue.innerText = 'Flat'
                    } else { // service.wage == 'hour'
                         rateValue.innerText = 'Hourly'
                    }


                    rateContainer.appendChild(rateTitle)
                    rateContainer.appendChild(rateValue)

                    result.appendChild(rateContainer)

                    let buttonContainer = document.createElement('profile__cta')
                    buttonContainer.classList.add('profile__cta')

                    let link = document.createElement('a')
                    link.classList.add('button')

                    if (service.available == 1) {
                         link.innerText = 'Book'
                         buttonContainer.onclick = function () {
                              const urlParams = new URLSearchParams(window.location.search)
                              let url = 'edit?'
                              url += "service=" + service.service
                              url += "&description=" + service.description
                              url += "&price=" + service.cost
                              url += "&wage=" + service.wage

                              if (id('current-address').innerText != '') {
                                   url += '&address=' + id('current-address').innerText
                              }
                              if (id('current-city').innerText != '') {
                                   url += '&city=' + id('current-city').innerText
                              }
                              if (id('current-state').innerText != '') {
                                   url += '&state=' + id('current-state').innerText
                              }

                              if (id('current-zip').innerText != '') {
                                   url += '&zip=' + id('current-zip').innerText
                              } else if (id('zip-input').value) {
                                   url += '&zip=' + id('zip-input').value;
                              } else if (urlParams.get('zip')) {
                                   url += '&zip=' + urlParams.get('zip')
                              }
                              url += "&remote=" + service.remote
                              url += "&back=results";
                              if (urlParams.get('category')) {
                                   url += '&category=' + urlParams.get('category')
                              } else {
                                   url += '&search=' + urlParams.get('search')
                              }

                              window.location = url;
                         };
                    } else if (service.available == 0) {
                         link.classList.add('disabled')
                         link.innerText = 'Unavailable'

                         buttonContainer.onclick = function () {
                              alert('Sorry, this service is unavailable in your zip code.')
                         }
                    } else { // service.available == 0.5
                         link.innerText = 'Click to check availability'

                         buttonContainer.onclick = function () {
                              id('scroll-to-zip').click();
                              var element = id("highlight");
                              element.classList.add("highlightEffect");
                              var profile = document.querySelectorAll('.profile')
                              for (let i = 0; i < profile.length; i++) {
                                   profile[i].classList.add("dim")
                              }

                              let checkBoxes = document.querySelectorAll('.checkbox > input')
                              checkBoxes.forEach(checkbox => {
                                   checkbox.disabled = 'true';
                              })
                              // var catFilters = id("category-filters");
                              // catFilters.classList.add("dim");
                              // var avaFilters = id("availability-filters");
                              // catFilters.classList.add("dim");

                         }
                    }
                    buttonContainer.appendChild(link)

                    result.appendChild(buttonContainer)

                    results.appendChild(result)

               }

                id('loading').classList.add('hidden');
                id('main').classList.remove('hidden');

          }
     }

     function removeSpotlight() {
          if (/(^\d{5}$)|(^\d{5}-\d{4}$)/.test(id('zip-input').value)) {
               var element = id("highlight");
               element.classList.remove("highlightEffect");
               var profile = document.querySelectorAll('.profile')
               for (let i = 0; i < profile.length; i++) {
                    profile[i].classList.remove("dim")
               }
               let checkBoxes = document.querySelectorAll('.checkbox > input')
               checkBoxes.forEach(checkbox => {
                    checkbox.disabled = 'false';
               })

               let url = '/results?';
               const urlParams = new URLSearchParams(window.location.search)

               if (urlParams.get('category')) {
                    url += 'category=' + urlParams.get('category')
               } else {
                    url += 'search=' + urlParams.get('search')
               }

               url += '&zip=' + id('zip-input').value

               window.location = url;

          } else {
               alert('Please enter a valid US zip code.')
          }

     }

     function isEmpty(obj) {
          for (var key in obj) {
               if (obj.hasOwnProperty(key))
                    return false;
          }
          return true;
     }

})();
