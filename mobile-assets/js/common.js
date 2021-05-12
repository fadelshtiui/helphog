async function populateNavigationBar() {
     let params = new FormData()
     params.append('session', getSession())

     let res = await fetch('php/session.php', { method: 'POST', body: params })
     await checkStatus(res)
     res = await res.json()

     let actions = []
     if (res.validated == "true") {
          actions = ['home', 'contact', 'orders', 'settings', 'signout']

          if (res.account.type == "Business") {
               actions.push('provider')
          }
     } else {
          actions = ['home', 'contact', 'tracking', 'registration', 'signin']
     }

     let links = qsa('#actions li a')
     links.forEach(link => {
          handleLink(link, actions, link.dataset.path, link.parentElement, res.validated)
     })

     for (let i = 0; i < actions.length; i++) {
          actions[i] = window.location.origin + '/' + actions[i];
     }

     let mobileLinks = qsa('#navPanel a')
     mobileLinks.forEach(link => {

          handleLink(link, actions, link.href, link, res.validated)
     })

}

function handleLink(link, actions, identifier, elementToHide, validated) {
    
    
     if (identifier == 'signout' || identifier == "") {
         
         console.log('sign out link')
         
          link.addEventListener('click', function (e) {
               e.stopPropagation();
               e.preventDefault();
               signOut();
               console.log('signed out')
          })
          if (validated == "false") {
              elementToHide.classList.add('hidden')
          }
     } else if (!actions.includes(identifier) && identifier != window.location.origin + '/') {
          elementToHide.classList.add('hidden')
     }

     
}