"use strict";

(function () {

     window.addEventListener('load', init)

     function init() {

          populateServices()
          populateCategories()
          populateNavigationBar()

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

     async function populateNavigationBar() {
          let params = new FormData()
          params.append('session', getSession())

          let res = await fetch('php/session.php', { method: 'POST', body: params })
          await checkStatus(res)
          res = await res.json()

          let actions = []
          if (res.validated == "true") {
               actions = ['orders', 'settings', 'signout']

               if (res.account.type == "Business") {
                    actions.push('provider')
               }
          } else {
               actions = ['tracking', 'registration', 'signin']
          }

          let links = qsa('#actions li a')
          links.forEach(link => {
               handleLink(link, actions, link.dataset.path, link.parentElement)
          })

          for (let i = 0; i < actions.length; i++) {
               actions[i] = window.location.origin + '/' + actions[i];
          }

          let mobileLinks = qsa('#navPanel a')
          mobileLinks.forEach(link => {

               handleLink(link, actions, link.href, link)
          })

     }

     function handleLink(link, actions, identifier, elementToHide) {

          console.log(actions)

          if (!actions.includes(identifier)) {
               elementToHide.classList.add('hidden')
          }

          if (identifier == 'signout') {
               link.addEventListener('click', function (e) {
                    e.preventDefault();
                    signOut();
               })
          }
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

