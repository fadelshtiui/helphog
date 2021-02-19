window.addEventListener('load', function () {
     populateAllServices();
     id('grant').addEventListener('click', function () {
          toggleAccountType('add')
     });
     id('revoke').addEventListener('click', function () {
          toggleAccountType('remove')
     });
     id('update').addEventListener('click', update)
     id('load').addEventListener('click', load)
     id('add').addEventListener('click', add)
})

function toggleAccountType(action) {
     let data = new FormData();
     data.append("password", id('password').value);
     data.append('email', id('email').value)
     data.append('action', action)

     let url = "php/toggleaccount.php";
     fetch(url, { method: "POST", body: data, mode: 'cors', credentials: 'include' })
          .then(checkStatus)
          .then(res => res.json())
          .then(function (res) {
               if (res.error == '') {
                    alert('success')
               } else {
                    alert(res.error)
               }
          })
          .catch(console.log);
}

async function populateAllServices() {
     id('all-services').innerHTML = '';
     let url = "php/info.php?type=services";
     let response = await fetch(url)
     await checkStatus(response)
     response = await response.json()
     response.forEach(service => {
          let entry = document.createElement('option');
          entry.innerText = service;
          entry.value = service;
          id('all-services').appendChild(entry)
     })
}

async function load() {
     let data = new FormData();
     data.append("password", id('password').value);
     data.append('email', id('email').value)

     let url = "php/loadproviderservices.php";
     let response = await fetch(url, { method: "POST", body: data })
     await checkStatus(response)
     response = await response.json()

     response.services.forEach(service => {
          let entry = document.createElement('li');
          entry.addEventListener('dblclick', function () {
               this.remove();
          })
          entry.innerText = service;
          entry.value = service;
          id('provider-services').appendChild(entry)
     })

}

async function update() {

     let servicesArray = [];

     let services = document.querySelectorAll('#provider-services > li');

     services.forEach(service => {
          servicesArray.push(service.innerText);
     })

     let data = new FormData();
     data.append("password", id('password').value);
     data.append('email', id('email').value)
     data.append('services', JSON.stringify(servicesArray))

     let url = "php/updateservices.php";
     fetch(url, { method: "POST", body: data })
          .then(checkStatus)
          .then(res => res.json())
          .then(function (res) {
               if (res.error != "") {
                    alert(res.error)
               } else {
                    alert('success')
               }
          })
          .catch(console.log);

}

function add() {
     let services = document.querySelectorAll('#provider-services > li');
     let found = false;

     services.forEach(service => {
          if (service.innerText == id('all-services').value) {
               found = true
          }
     })

     if (!found) {
          let entry = document.createElement('li')
          entry.addEventListener('dblclick', function () {
               this.remove();
          })
          entry.innerText = id('all-services').value
          id('provider-services').appendChild(entry);
     }

}



