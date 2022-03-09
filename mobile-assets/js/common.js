async function populateNavigationBar() {
     let params = new FormData()
     params.append('session', getSession())

     let res = await fetch('php/session.php', { method: 'POST', body: params })
     await checkStatus(res)
     res = await res.json()

     let actions = []
     if (res.validated == "true") {
          actions = ['home', 'about', 'contact', 'provider', 'orders', 'settings']
     } else {
          actions = ['home', 'contact', 'tracking', 'registration' , 'signin']
     }

     for (let i = 0; i < actions.length; i++) {
          actions[i] = window.location.origin + '/' + actions[i];
     }

     let mobileLinks = qsa('#navPanel a')

     mobileLinks.forEach(link => {

         if (!actions.includes(link.href) && link.href != window.location.origin + '/') {
              link.classList.add('hidden')
         }

     })

}
