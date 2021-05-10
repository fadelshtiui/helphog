/* global fetch */

"use strict";

(function () {

     window.onload = function () {

          // id('loading').classList.remove('hidden');
          let availabilityFilters = qsa('#availability-filters input')
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

          id('zip-input').addEventListener("keyup", function (event) {
               event.preventDefault();
               if (event.keyCode === 13) {
                    id('update').click();
               }
          });


     };

     function toggleAddressInput() {
          this.disabled = true;
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

          if (urlParams.get('category')) {
               id('category-filters').classList.add('hidden')
          }

          fetchResults(searchURL, data)
     }

     let fetchResults = async (url, data) => {

          let response = await fetch(url, { method: "POST", body: data })
          await checkStatus(response)
          let result = await response.json()
          populateCategories(result.categories)
          handleResponse(result)

     }

     function populateCategories(response) {

          for (let i = 0; i < response.length; i++) {
               let category = response[i];

               let filterContainer = ce('div')
               filterContainer.classList.add('filters__item')

               let checkboxContainer = ce('div')
               checkboxContainer.classList.add('checkbox')

               let checkbox = ce('input')
               checkbox.type = 'checkbox'
               checkbox.dataset.category = category
               checkbox.id = 'checkbox-' + (i + 1)

               let label = ce('label')
               label.textContent = category.charAt(0).toUpperCase() + category.slice(1)
               label.htmlFor = checkbox.id

               let box = ce('span')
               box.classList.add('box')

               label.appendChild(box)

               checkboxContainer.appendChild(checkbox)
               checkboxContainer.appendChild(label)

               let numResults = ce('span')
               numResults.classList.add('badge')
               numResults.classList.add('status-primary')
               numResults.dataset.category = category

               filterContainer.appendChild(checkboxContainer)
               filterContainer.appendChild(numResults)

               id('category-filters').appendChild(filterContainer)
          }

          let categoryFilters = qsa('#category-filters input')
          for (let i = 0; i < categoryFilters.length; i++) {
               categoryFilters[i].onchange = filterCategory;
          }

     }

     function filterCategory() {
          let filters = qsa('#category-filters input')
          for (let i = 0; i < filters.length; i++) {
               if (filters[i] != this) {
                    filters[i].checked = false;
               }
          }

          if (this.checked) {
               let currAvailability
               filters = qsa('#availability-filters input')
               for (let i = 0; i < filters.length; i++) {
                    if (filters[i].checked) {
                         currAvailability = filters[i].dataset.availability
                    }
               }

               filter(this.dataset.category, currAvailability)
          }
     }

     function filterAvailability() {
          let filters = qsa('#availability-filters input')
          for (let i = 0; i < filters.length; i++) {
               if (filters[i] != this) {
                    filters[i].checked = false;
               }
          }

          if (this.checked) {
               let currCategory
               filters = qsa('#category-filters input')
               for (let i = 0; i < filters.length; i++) {
                    if (filters[i].checked) {
                         currCategory = filters[i].dataset.category
                    }
               }

               filter(currCategory, this.dataset.availability)
          }


     }

     function filter(category, availability) {
          let results = qsa('.profile')
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
               window.location = "error?search=" + urlParams.get('search')
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
               let count = 0;
               let counters = qsa('#category-filters .badge')
               for (let i = 0; i < counters.length; i++) {
                    let counter = counters[i]
                    counter.innerText = '0'
                    if (response.counts[counter.dataset.category]) {
                         counter.innerText = response.counts[counter.dataset.category];
                         count++;
                    } else {
                         counter.parentElement.classList.add('hidden')
                    }

               }
               if (count <= 1) {
                    id('category-filters').classList.add('hidden');
               }

               id('total').innerText = response.services.length;
               id('show-all').innerText = response.services.length;
               id('only-available').innerText = response.available;

               for (let i = 0; i < response.services.length; i++) {
                    let service = response.services[i]

                    let results = qs('.results-section')

                    let result = ce('div')
                    result.classList.add('profile')
                    result.dataset.category = service.category
                    result.dataset.available = service.available

                    let imageContainer = ce('div');
                    imageContainer.classList.add('profile__image')

                    let image = ce('img')
                    image.src = service.src

                    imageContainer.appendChild(image)

                    result.appendChild(imageContainer)

                    let infoContainer = ce('div')
                    infoContainer.classList.add('profile__info')

                    let title = ce('h3')
                    title.classList.add('third-level-heading')
                    title.innerText = service.service

                    let x = 70
                    // while (service.description.charAt(x) != " " || service.description.charAt(x) != "," || service.description.charAt(x) != "." || service.description.charAt(x) != "-") {
                    //     x--
                    // }

                    let description = ce('p')
                    description.classList.add('profile__info__extra')
                    description.innerText = service.description.substring(0, x)

                    let queryString = window.location.search
                    const urlParams = new URLSearchParams(queryString)
                    let zip = urlParams.get('zip')

                    let more = ce('span')
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
                         if (urlParams.get('search')) {
                              url += '&search=' + urlParams.get('search')
                         } else {
                              url += '&category=' + urlParams.get('category')
                         }


                         window.location = url;
                    }

                    infoContainer.appendChild(title)
                    infoContainer.appendChild(description)

                    result.appendChild(infoContainer)

                    let bottomRowContainer = ce('div')
                    bottomRowContainer.classList.add('bottom-row')

                    let priceContainer = ce('div')
                    priceContainer.classList.add('profile__stats')

                    let priceTitle = ce('p')
                    priceTitle.classList.add('profile__stats__title')
                    // priceTitle.style.marginLeft = '28px';
                    priceTitle.innerText = 'Price'

                    let priceValue = ce('h5')
                    priceValue.classList.add('fifth-level-heading')
                    priceValue.innerText = '$' + service.cost
                    // priceValue.style.marginLeft = '28px';

                    let rateContainer = ce('div')
                    rateContainer.classList.add('profile__stats')

                    let rateTitle = ce('p')
                    rateTitle.classList.add('profile__stats__title')
                    rateTitle.innerText = 'Rate'
                    // rateTitle.style.marginLeft = '-5px';

                    let rateValue = ce('h5')
                    rateValue.classList.add('fifth-level-heading')
                    rateValue.classList.add('profile__stats__info')
                    // rateValue.style.marginLeft = '-5px';

                    let availabilityContainer = ce('div')
                    availabilityContainer.classList.add('profile__stats')

                    let availabilityTitle = ce('p')
                    availabilityTitle.classList.add('profile__stats__title')
                    availabilityTitle.innerText = 'First hour'
                    // availabilityTitle.style.width = '89px';
                    // availabilityTitle.style.marginLeft = '-19px';

                    let toolTipContainer = ce('span')
                    toolTipContainer.classList.add('tooltip', 'top');
                    let tooltip = ce('span')
                    let iCircle = ce('span')
                    iCircle.classList.add('fa-question-circle', 'icon')
                    tooltip.appendChild(iCircle);
                    toolTipContainer.appendChild(tooltip);


                    let availabilityValue = ce('h5')
                    availabilityValue.classList.add('fifth-level-heading')
                    availabilityValue.classList.add('profile__stats__info')
                    availabilityValue.innerText = 'Unavailable'
                    // availabilityValue.style.marginLeft = '-19px';

                    availabilityValue.innerText = 'Full';

                    if (service.prorated == 'y') {
                         availabilityValue.innerText = 'Prorated';
                         toolTipContainer.dataset.tooltip = "If your order takes less than 1 hour, you will be charged for exactly that amount of time.";
                    } else {
                         toolTipContainer.dataset.tooltip = "If your order takes less than 1 hour, you will be charged for 1 hour.";

                    }

                    if (service.wage == 'per') {
                         rateValue.innerText = 'Flat'
                         availabilityValue.innerText = 'Full';

                    } else { // service.wage == 'hour'
                         rateValue.innerText = 'Hourly'
                         priceValue.innerText = priceValue.innerText + "/hr"
                    }

                    availabilityValue.appendChild(toolTipContainer)


                    priceContainer.appendChild(priceTitle)
                    priceContainer.appendChild(priceValue)

                    rateContainer.appendChild(rateTitle)
                    rateContainer.appendChild(rateValue)

                    availabilityContainer.appendChild(availabilityTitle)
                    availabilityContainer.appendChild(availabilityValue)

                    bottomRowContainer.appendChild(priceContainer)
                    bottomRowContainer.appendChild(rateContainer)
                    bottomRowContainer.appendChild(availabilityContainer)

                    result.appendChild(bottomRowContainer)

                    let buttonContainer = ce('profile__cta')
                    buttonContainer.classList.add('profile__cta')

                    let link = ce('button')
                    link.classList.add('primary-green')

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
                              url += "&origin=" + urlParams.get('origin')

                              window.location = url;
                         };
                    } else if (service.available == 0) {
                         link.disabled = true;
                         link.innerText = 'Unavailable'

                         buttonContainer.onclick = function () {
                              id('warning-message').innerText = 'Sorry, this service is unavailable in your zip code.'
                              qs(".modal-wrapper").classList.remove('hidden')
                         }
                    } else { // service.available == 0.5
                         link.innerText = 'Click to check availability'

                         buttonContainer.onclick = function () {
                              id('scroll-to-zip').click();
                              var element = id("highlight");
                              element.classList.add("highlightEffect");
                              var profile = qsa('.profile')
                              for (let i = 0; i < profile.length; i++) {
                                   profile[i].classList.add("dim")
                              }

                              let checkBoxes = qsa('.checkbox > input')
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

               // id('loading').classList.add('hidden');
               id('main').classList.remove('hidden');

          }
     }

     function removeSpotlight() {
          if (/(^\d{5}$)|(^\d{5}-\d{4}$)/.test(id('zip-input').value)) {
               var element = id("highlight");
               element.classList.remove("highlightEffect");
               var profile = qsa('.profile')
               for (let i = 0; i < profile.length; i++) {
                    profile[i].classList.remove("dim")
               }
               let checkBoxes = qsa('.checkbox > input')
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

               document.cookie = "zip=" + id('zip-input').value + ";";

               window.location = url;

          } else {
               id('warning-message').innerText = 'Please enter a valid US zip code.'
               qs(".modal-wrapper").classList.remove('hidden')
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
