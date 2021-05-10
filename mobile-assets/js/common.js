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