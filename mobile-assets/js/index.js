"use strict";

(function () {

     window.addEventListener('load', init)

     function init() {

          populateServices()
          populateCategories()
          populateNavigationBar()
          // populateFooter()

          id('search-icon').addEventListener('click', search)
          id('search-input').addEventListener('keyup', function (e) {
               e.preventDefault()
               if (e.keyCode === 13) {
                    search()
               }
          })

     }

     async function populateServices() {
          let res = await fetch('/php/info.php?type=services')
          await checkStatus(res)
          res = await res.json()

          res.forEach(service => {
               let option = ce('option')
               option.textContent = service
               option.addEventListener('click', function () {
                    let url = 'details?service=' + this.textContent
                    if (getSession() == '' && getZip() != '') {
                         url += '&zip=' + getZip()
                    }
                    window.location.href = url
               })
               id('services').appendChild(option)
          })
     }

     async function populateCategories() {
          let res = await fetch('/php/info.php?type=categories')
          await checkStatus(res)
          res = await res.json()

          res.forEach(category => {
               let button = ce('button')
               button.classList.add('secondary')
               button.textContent = capitalize(category)
               button.addEventListener('click', function () {
                    let url = 'results?category=' + this.textContent
                    if (getSession() == '' && getZip() != '') {
                         url += '&zip=' + getZip()
                    }
                    window.location.href = url
               })
               id('categories').appendChild(button)
          })
     }

     function search() {
          let input = id('search-input').value
          if (input != '') {
               let url = 'results?search=' + id('search-input').value
               if (getSession() == '' && getZip() != '') {
                    url += '&zip=' + getZip()
               }
               window.location.href = url
          }
     }

     function capitalize(s) {
          if (typeof s !== 'string') {
               return ''
          }
          return s.charAt(0).toUpperCase() + s.slice(1)
     }

})()

