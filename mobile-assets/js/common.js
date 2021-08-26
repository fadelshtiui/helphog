async function populateNavigationBar() {
     let params = new FormData()
     params.append('session', getSession())

     let res = await fetch('php/session.php', { method: 'POST', body: params })
     await checkStatus(res)
     res = await res.json()

     let actions = []
     if (res.validated == "true") {
          actions = ['home', 'contact', 'signout']
     } else {
          actions = ['home', 'contact', 'signin']
     }

     for (let i = 0; i < actions.length; i++) {
          actions[i] = window.location.origin + '/' + actions[i];
     }

     let mobileLinks = qsa('#navPanel a')
     
     mobileLinks.forEach(link => {
         
         if (link.innerText != "ACTIONS") {
             if (link.href == "") { // sign out link doesn't have href
                  if (res.validated == "true") {
                      link.addEventListener('click', function (e) {
                           e.stopPropagation();
                           e.preventDefault();
                           signOut();
                           console.log('signed out')
                      })
                  } else {
                      link.classList.add('hidden')
                  }
             } else if (!actions.includes(link.href) && link.href != window.location.origin + '/') {
                  link.classList.add('hidden')
             }
         }

          
     })

}