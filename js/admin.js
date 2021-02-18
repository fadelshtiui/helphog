window.addEventListener('load', function() {
  populateAllServices();
  id('add').addEventListener('click', function() {
      toggleAccountType('add')
  });
  id('remove').addEventListener('click', function() {
      toggleAccountType('remove')
  });
  id('update').addEventListener('click', update)
  id('load').addEventListener('click', load)
})

function toggleAccountType(action) {
  let data = new FormData();
  data.append("password", id('password').value);
  data.append('email', id('email').value)
  data.append('action', action)
  
  let url = "php/toggleaccount.php";
  fetch(url, { method: "POST", body: data, mode: 'cors', credentials: 'include' })
      .then(checkStatus)
      .then(res => res.text())
      .then(alert)
      .catch(console.log);
}

async function populateServices() {
  let url = "php/info.php?type=services";
  let response = await fetch(url)
  await checkStatus(response)
  response = await response.json()
}

async function load() {
  let data = new FormData();
  data.append("password", id('password').value);
  data.append('email', id('email').value)

  let url = "php/loadproviderservices.php";
  let response = await fetch(url, { method: "POST", body: data })
  await checkStatus(response)
  response = await response.json()
}

async function update() {
  
  let data = new FormData();
  data.append("password", id('password').value);
  data.append('email', id('email').value)
  data.append('services', [])
  
  let url = "php/updateservices.php";
  fetch(url, { method: "POST", body: data })
      .then(checkStatus)
      .then(res => res.text())
      .then(alert)
      .catch(console.log);
  
}



