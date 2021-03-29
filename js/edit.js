let guestOpen = false;
let loginOpen = false;
let providerId = 'none';
let selected = false;

window.addEventListener('load', function () {

     $('.modal-content').slideToggle();
     id('city-state-comma').classList.add('hidden')
     document.querySelector(".loader").classList.add("hidden");

     $('#guest').on('click', function () {

          id("username").value = "";
          id("password").value = "";

          if (!loginOpen) {
               $('.modal-content').slideToggle();
          }

          guestOpen = true;
          loginOpen = false;

          id('guest').disabled = true
          id('login').disabled = false

     });

     $('.modal-content').slideToggle();


     $('#login').on('click', function () {

          id("username").value = "";
          id("password").value = "";

          if (!guestOpen) {
               $('.modal-content').slideToggle();
          }

          loginOpen = true;
          guestOpen = false;

          id('guest').disabled = false
          id('login').disabled = true
     });

     var x = document.getElementById("options-tab");
     x.style.display = "none";

     $('#advanced-options').on('click', function () {
          if (x.style.display === "none") {
               x.style.display = "block";
          } else {
               x.style.display = "none";
          }
     });

     $('.popupCloseButton').click(function () {
          $('.hover_bkgr_fricc').hide();
          if (loginOpen || guestOpen) {
               $('.modal-content').slideToggle();
          }
     });

     id("close").onclick = function () {
          document.documentElement.style.overflow = "overlay";
          document.querySelector("#button").disabled = false;
          //   var tdtag = document.querySelectorAll('.trtag')
          //       tdtag.forEach(el => {
          //           el.classList.remove('visibility');
          //           el.classList.add('notvisible');
          //   })

     }

     id("checkprovider").onclick = function () {
          const urlParams = new URLSearchParams(window.location.search)
          id("errorlabel").classList.add("hidden")
          id("providerSelected").classList.add("hidden")
          if (urlParams.get('remote') == 'y' || id('current-address').innerText != "" && id('current-city').innerText != "" && id('current-zip').innerText != "" && id('current-state').innerText != "") {
               if (id("taskerinput").value !== "") {
                    providerId = id("taskerinput").value;
                    checkAvailability('false', updateTimePicker, true);
               } else {
                    id("errorlabel").classList.remove("hidden");
                    id("errorlabel").innerText = "No provider ID entered.";
               }

          } else {
               id("errorlabel").classList.remove("hidden");
               id("errorlabel").innerText = "Please enter your full address below first.";
          }
     }

     id("removeprovider").onclick = function () {
          toggleSelected();
     }

     id('locationField').classList.add('hidden')

     id('edit').addEventListener('click', editAddress)
     id('addressDisplay').addEventListener('click', editAddress)

     const urlParams = new URLSearchParams(window.location.search)

     let today = new Date();
     let now = today.getHours() + ":" + today.getMinutes()

     id('arrow').addEventListener('click', navigateBack)

     let dom = today.toUTCString().substring(4, 7)
     let month = today.toUTCString().substring(8, 11)
     let year = today.toUTCString().substring(12, 16)

     let months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

     id('dow').value = today.toDateString().substring(0, 3)
     id('date').value = today.toDateString().substring(4, 15)

     let timeslots = document.querySelectorAll('.timeslot')
     for (let i = 0; i < timeslots.length; i++) {
          let slot = timeslots[i];
          slot.onclick = updateTime;
     }

     initTextFields();

     id('duration').onchange = function () {
          checkAvailability('false', updateTimePicker, false);
     }
     id('numpeople').onchange = function () {
          if (id('numpeople').value == 1) {
               id('taskerinputField').classList.remove("hidden");
               id('taskerlabel').classList.remove("hidden");
               id('taskerinput').classList.remove("hidden");
               id('errorlabel').classList.remove("hidden");
               id('checkprovider').classList.remove("hidden");

          }
          else {
               providerId = "none";
               id('taskerlabel').classList.add("hidden");
               id('removeprovider').classList.add("hidden");
               id('taskerinput').classList.add("hidden");
               id('errorlabel').classList.add("hidden");
               id('providerSelected').classList.add("hidden");
               id('checkprovider').classList.add("hidden");
          }
          checkAvailability('false', updateTimePicker, false);
     }

     checkAvailability('false', updateTimePicker, false);

     id("submit-login").onclick = submitLogin;

     id("guest").onclick = function () {
          id("email-error").innerText = "";
          id("password-error").innerText = "";

          const inputElement = id('username');

          inputElement.addEventListener('keydown', enforceFormat);
          inputElement.addEventListener('keyup', formatToPhone);

          id("username").value = "";
          id("password").value = "";

          id("username").placeholder = "Phone Number";
          id("password").placeholder = "Email";
          id("password").setAttribute("type", "text");
          id("password").innerText = "";
          id("password").innerText = "";
          id("noaccount").classList.add("hidden");
          id("submit-login").onclick = guestLogin;
     };

     id("login").onclick = function () {
          id("email-error").innerText = "";
          id("password-error").innerText = "";

          const inputElement = id('username');

          id("username").value = "";
          id("password").value = "";

          inputElement.removeEventListener('keydown', enforceFormat);
          inputElement.removeEventListener('keyup', formatToPhone);

          id("username").placeholder = "Email";
          id("password").placeholder = "Password";
          id("password").setAttribute("type", "password");
          guest = false;
          id("noaccount").classList.remove("hidden");
          id("submit-login").onclick = submitLogin;
     };

     // ("guest").click();

     let wage = urlParams.get('wage')

     if (wage == "per") {
          id("hourlabel").style.display = "none";
          id("quantity2").style.display = "none";
     }

     id('button').addEventListener('click', validateInput);


     if (id('current-address').innerText == '' || id('current-city').innerText == '' || id('current-state').innerText == '' || id('current-zip').innerText == '') {
          editAddress();
     }
     checkProviders();

});

function editAddress() {
     id('locationField').classList.remove('hidden')
     id('addressDisplay').classList.add('hidden')
     id('edit').classList.add('hidden')
     id('autocomplete').classList.remove('hidden')
}

function initTextFields() {
     let service = document.querySelector(".service");
     let description = document.querySelector(".description");

     let queryString = window.location.search
     const urlParams = new URLSearchParams(queryString)

     service.innerText = service.innerText + "" + urlParams.get('service');
     description.innerText = description.innerText + "" + urlParams.get('description');

     id('duration').value = 1
     id('numpeople').value = 1

     if (urlParams.get('address')) {
          id('current-address').innerText = urlParams.get('address')
     }

     if (urlParams.get('city')) {
          id('current-city').innerText = urlParams.get('city')
     }

     if (urlParams.get('state')) {
          id('current-state').innerText = urlParams.get('state')
     }

     if (urlParams.get('zip')) {
          id('current-zip').innerText = urlParams.get('zip')
     }

     if (urlParams.get('city') && urlParams.get('state')) {
          id('city-state-comma').classList.remove('hidden')
     }

     if (urlParams.get('remote') == "y") {
          id("location").classList.add('hidden')
          id("current-address").value = "Online (remote)";
          id("current-city").value = " ";
          id("current-state").value = " ";
          id("current-zip").value = " ";
     }

}

function toggleSelected() {
     if (selected) {
          id("taskerinput").classList.remove("hidden")
          id("checkprovider").classList.remove("hidden")
          id("removeprovider").classList.add("hidden")
          id("providerSelected").classList.add("hidden")
          id("taskerinput").innerText = "";
          providerId = 'none';
          checkAvailability('false', updateTimePicker, false);
          selected = false
     } else {
          id("taskerinput").classList.add("hidden")
          id("checkprovider").classList.add("hidden")
          id("removeprovider").classList.remove("hidden")
          selected = true
     }
}

function navigateBack() {

     const urlParams = new URLSearchParams(window.location.search)

     let back = urlParams.get('back')
     let url = back + '?';
     if (urlParams.get('zip')) {
          url += 'zip=' + urlParams.get('zip');
     }
     if (back == 'details') {
          url += '&service=' + urlParams.get('service')
     }

     if (urlParams.get('search')) {
          url += '&search=' + urlParams.get('search')
     } else if (urlParams.get('category')) {
          url += '&category=' + urlParams.get('category')
     }

     if (urlParams.get('address')) {
          url += '&address=' + urlParams.get('address')
     }

     if (urlParams.get('city')) {
          url += '&city=' + urlParams.get('city')
     }

     if (urlParams.get('state')) {
          url += '&state=' + urlParams.get('state')
     }

     url += '&origin=' + urlParams.get('origin')

     window.location = url
}

async function checkAvailability(updatecontactlist, callback, updateprovider) {

     id('button').disabled = true;

     const urlParams = new URLSearchParams(window.location.search)

     let service = urlParams.get('service')
     let schedule = "";
     if (updatecontactlist == 'true') {
          schedule = id('date').value + ' ' + id('time').value;
     } else {
          schedule = id('date').value
     }

     let numpeople = id('numpeople').value
     let duration = id('duration').value
     let remote = urlParams.get('remote')

     let tz = jstz.determine();
     let timezone = tz.name();

     let fullAddress = (id('current-address').innerText + '+' + id('current-city').innerText + '+' + id('current-state').innerText + '+' + id('current-zip').innerText).replace(/ /gi, '+')

     let data = new FormData();
     data.append('address', fullAddress)
     data.append('tz', timezone);
     data.append("service", service);
     data.append("numpeople", numpeople);
     data.append("schedule", schedule);
     data.append("duration", duration);
     data.append("remote", remote);
     data.append('updatecontactlist', updatecontactlist);
     data.append('id', providerId);
     let url = "php/checkavailability.php";
     addLoader();
     let response = await fetch(url, { method: "POST", body: data })
     await checkStatus(response);
     response = await response.text();
     if (response == 'Provider with the inputed ID does not exist or does not provide this service' || response == 'The selected provider is unavailable for this order') {
          id("errorlabel").classList.remove("hidden");
          if (id('numpeople').value != 1) {
               id("errorlabel").classList.add("hidden");
          }
          providerId = "none"
          id("errorlabel").innerText = response;
          removeLoader();
     } else {
          checkProviders();
          callback(response);
          removeLoader();
     }
}

function addLoader() {
     document.querySelector(".loader").classList.remove("hidden");
     document.querySelector(".timePicker").classList.add("darked");
}

function removeLoader() {
     document.querySelector(".loader").classList.add("hidden");
     document.querySelector(".timePicker").classList.remove("darked");
}

function updateTimePicker(response) {

     let slots = document.querySelectorAll(".timeslot")

     for (let i = 0; i < slots.length; i++) {

          let slot = slots[i]
          let slotVal = Math.floor(slot.getAttribute('data-availabilityValue'));

          let upperBound = slotVal;
          if (slot.getAttribute('data-availabilityValue') % 1 != 0) { // decimal number
               upperBound = Math.ceil(slot.getAttribute('data-availabilityValue'))
          }

          if (response[slotVal] == '0' || response[upperBound] == '0') {
               slot.classList.remove('available-column');
               slot.classList.add('unavailable-column');
               slot.classList.remove('time-selected')
               // slot.setAttribute('title', 'Unavailable at this time')
          } else {
               slot.classList.remove('unavailable-column');
               slot.classList.add('available-column');
          }
     }

     let today = new Date();
     let newdate = new Date(id('date').value)

     if (newdate.getFullYear() == today.getFullYear() && newdate.getMonth() == today.getMonth() && newdate.getDate() == today.getDate()) {
          let currentHour = today.getHours();
          let slots = document.querySelectorAll(".timeslot")
          for (let i = 0; i < slots.length; i++) {

               let slot = slots[i]
               let slotVal = Math.floor(slot.getAttribute('data-availabilityValue'));

               if (slotVal <= currentHour) {
                    slot.classList.remove('available-column');
                    slot.classList.add('unavailable-column');
                    slot.classList.remove('time-selected')

               }
          }
     }

     id('button').disabled = false;
}


function updateTime() {
     const urlParams = new URLSearchParams(window.location.search)

     if (urlParams.get('remote') == 'y' || id('current-address').innerText != "" && id('current-city').innerText != "" && id('current-zip').innerText != "" && id('current-state').innerText != "") {
          if (this.classList.contains("available-column")) {

               let timeslots = document.querySelectorAll('.timeslot')
               for (let i = 0; i < timeslots.length; i++) {
                    let slot = timeslots[i];
                    slot.classList.remove("time-selected");
               }

               this.classList.add("time-selected")

               id('time').value = this.getAttribute('data-militaryTime')
          }
     } else {
          id('warning-message').innerText = "Please select a full address."
          document.querySelector('.modal-wrapper').classList.remove('hidden')
     }

}

async function validateInput() {

     const urlParams = new URLSearchParams(window.location.search)

     if ((id("current-address").innerText == "" || id("current-city").innerText == "" || id("current-state").innerText == "" || id("current-zip").innerText == "") && urlParams.get('remote') == 'n') {
          id('warning-message').innerText = "Please select a full address."
          document.querySelector('.modal-wrapper').classList.remove('hidden')
          return;
     }

     let timeSelected = false;
     let timeslots = document.querySelectorAll('.timeslot')
     for (let i = 0; i < timeslots.length; i++) {
          let slot = timeslots[i];
          if (slot.classList.contains("time-selected")) {
               timeSelected = true;
          }
     }
     if (!timeSelected) {
          id('warning-message').innerText = "Please select a time for your order."
          document.querySelector('.modal-wrapper').classList.remove('hidden')
          return
     }

     let data = new FormData();
     data.append("session", getSession());
     let url = "php/session.php";
     let response = await fetch(url, { method: "POST", body: data })
     await checkStatus(response)
     response = await response.json();

     if (response.validated == "false") {

          $('.hover_bkgr_fricc').show();

     } else {

          initModal();

     }

}

function submitLogin() {
     let data = new FormData();
     let email = id("username").value.toLowerCase();
     let password = id("password").value;
     data.append("email", email);
     data.append("password", password);
     let url = "php/signin.php";
     fetch(url, { method: "POST", body: data })
          .then(checkStatus)
          .then(res => res.json())
          .then(submitLoginHelper)
          .catch(console.log);
}

function submitLoginHelper(response) {
     if (response.emailerror == "true") {
          id("email-error").innerText = "*Email not found.";
     } else if (response.emailerror == "empty") {
          id("email-error").innerText = "*Required field";
     } else {
          id("email-error").innerText = "";
     }

     if (response.passworderror == "true") {
          id("password-error").innerText = "*Incorrect Password.";
     } else if (response.passworderror == "empty") {
          id("password-error").innerText = "*Required field";
     } else {
          id("password-error").innerText = "";
     }

     if (response.verified == "n") {
          id('warning-message').innerText = "Your account has not yet been verified. Please check your email for a verification email."
          document.querySelector('.modal-wrapper').classList.remove('hidden')
     } else if (response.emailerror == "" && response.passworderror == "") {

          $('.hover_bkgr_fricc').hide();

          document.cookie = "session=" + response.session + ";";
          initModal();
     }
}

function checkProviders() {
     let data = new FormData();
     let service = document.querySelector(".service").innerText
     data.append("service", service);
     data.append("id", providerId);
     let url = "php/providers.php";
     fetch(url, { method: "POST", body: data })
          .then(checkStatus)
          .then(res => res.json())
          .then(providersHelper)
          .catch(console.log);
}

function providersHelper(response) {
     if (response.providers != 0) {
          document.querySelector("#quantity").classList.add("hidden");
          document.querySelector("#quantity-label").classList.add("hidden");
     }
     if (providerId != "none") {
          id("providerSelected").classList.remove("hidden");
          id("errorlabel").classList.add("hidden");
          id('taskerinput').classList.add("hidden");
          id("provider").innerText = providerId;
          if (selected == false) {
               toggleSelected()
          }
     } else if (id('numpeople').value != 1) {
          id('taskerlabel').classList.add("hidden");
          id('removeprovider').classList.add("hidden");
          id('taskerinput').classList.add("hidden");
          id('errorlabel').classList.add("hidden");
          id('providerSelected').classList.add("hidden");
          id('checkprovider').classList.add("hidden");
     }
     for (i = response.available + 1; i <= 5; i++) {
          $("#numpeople option[value=" + i + "]").attr('disabled', 'disabled')
     }
}

function guestLogin() {

     let data = new FormData();
     data.append("email", id("password").value);
     data.append("phone", id("username").value.replace(/\D/g, ''));
     let url = "php/checkguest.php";
     fetch(url, { method: "POST", body: data })
          .then(checkStatus)
          .then(res => res.json())
          .then(guestLoginHelper)
          .catch(console.log);

}

function guestLoginHelper(response) {
     if (response.emailerror != "") {
          id("email-error").innerText = response.emailerror;
     }
     if (response.phoneerror != "") {
          id("password-error").innerText = response.phoneerror;
     }

     if (response.emailerror == "" && response.phoneerror == "") {
          $('.hover_bkgr_fricc').hide();
          id("email-error").innerText = "";
          id("password-error").innerText = "";

          initModal();
     }

}

function generateOrderNumber() {
     let dateString = Math.floor(Date.now() / 1000).toString();
     let now = dateString + Math.floor(1000 + Math.random() * 9000);
     return [now.slice(0, 4), now.slice(4, 10), now.slice(10, 14)].join('-');
}

function initModal() {

     var tdtag = document.querySelectorAll('.trtag')
     tdtag.forEach(el => {
          el.classList.remove('visibility');
          el.classList.add('notvisible');
     })




     id('order').value = generateOrderNumber()

     document.documentElement.style.overflow = "hidden";
     const urlParams = new URLSearchParams(window.location.search)

     let cost = urlParams.get('price')
     let estimate;
     if (urlParams.get('wage') == 'per') {
          estimate = id("numpeople").value * cost;
     } else {
          estimate = id("numpeople").value * cost * ((id("duration").value) - 1) + "-$" + id("numpeople").value * cost * id("duration").value;
     }

     let time = id('time').value

     let message = "(No message)"
     if (id("message").value != "") {
          message = id("message").value;
     }

     let people = "provider"
     if (id("numpeople").value > 1) {
          people = "providers"
     }

     id("terms").innerText = "*Estimated cost is calculated based on the amount of hours the tasks is expected to take. If your task takes less time or more time, the charge will decrease or increase respectively. You will not be charged until your order is completed. If you cancel your task within 24 hours of the scheduled start time, you will be billed a $15 cancellation fee. Estimated cost does not include costs of replacement parts/accessories."

     id("popupService").innerText = id("first").innerText;
     id("popupMessage").innerText = " " + message;
     id("popupDate").innerText = " " + formatDate(id('date').innerText) + " " + time;
     id("popupProviders").innerText = id("numpeople").value;
     id("popupTotal").innerText = estimate;

     if (urlParams.get('remote') == "y") {
          id("popupAddress").innerText = " Remote (Online)"
     } else {
          id("popupAddress").innerText = " " + id("current-address").innerText + " " + id("current-city").innerText.charAt(0).toUpperCase() + id("current-city").innerText.slice(1) + ", " + id("current-state").innerText.toUpperCase() + " " + id("current-zip").innerText;
     }

     window.location = window.location.href.replace('#', '') + "#popup1";

     if (urlParams.get('wage') == "per") {

          id("popupSubtotal").innerText = id("numpeople").value + " " + people + " at $" + cost;

          stripe(id("first").innerText, "Until Completion", id("numpeople").value, cost);
     } else {

          let durationText = id("duration").options[id("duration").selectedIndex].text;
          let durationCalc = id("duration").value;

          id("popupSubtotal").innerText = id("numpeople").value + " " + people + " at $" + cost + "/hr " + "(" + durationText + ")"

          stripe(id("first").innerText, durationCalc, id("numpeople").value, cost);
     }

}

async function stripe(service, duration, people, cost) {
     let data = new FormData();
     data.append("session", getSession())
     let url = "php/session.php"
     let response = await fetch(url, { method: "POST", body: data })
     await checkStatus(response);
     response = await response.json()

     let email = "";
     let phone = "";

     if (response.validated == "true") {
          email = response.account.email
          phone = response.account.phone
     } else {
          email = id("password").value
          phone = id("username").value.replace(/\D/g, '')
     }

     let message = "N/A"
     if (id('message').value) {
          message = id('message').value
     }

     await checkAvailability('true', console.log, false);

     let tz = jstz.determine();
     let timezone = tz.name();

     var stripe = Stripe("pk_test_51H77jdJsNEOoWwBJ6SbYEnMmPPCnGhzuVXyWzoUE3UYIpC3jTqrSUg3XjqFoswJ9Eh4cfrrVTDcGkgsmB68Ii59800u7xkwABp");
     // The items the customer wants to buy
     var purchase = {
          items: [
               {
                    service: document.querySelector(".service").innerText,
                    duration: id('duration').value,
                    people: id('numpeople').value
               }
          ],
          creds: [
               {
                    phone: phone,
                    email: email
               }
          ],
          checkout: {
               tzoffset: timezone,
               day: id('dow').value,
               order: id('order').value,
               service: document.querySelector(".service").innerText,
               customeremail: email,
               schedule: id('date').value + ' ' + id('time').value,
               phone: phone,
               message: message,
               zip: id('current-zip').innerText,
               address: id('current-address').innerText,
               city: id('current-city').innerText,
               state: id('current-state').innerText,
               people: id('numpeople').value,
               duration: id('duration').value,
               cancelbuffer: id('cancelbuffer').value
          }
     };

     document.querySelector("button").disabled = true;
     fetch("php/payment.php", {
          method: "POST",
          headers: {
               "Content-Type": "application/json"
          },
          body: JSON.stringify(purchase)
     })
          .then(checkStatus)
          .then(res => res.json())
          .then(function (data) {
               var elements = stripe.elements();
               var style = {
                    base: {
                         color: "#32325d",
                         fontFamily: 'Roboto, sans-serif',
                         fontSmoothing: "antialiased",
                         fontSize: "16px",
                         "::placeholder": {
                              color: "#32325d"
                         }
                    },
                    invalid: {
                         fontFamily: 'Roboto, sans-serif',
                         color: "#fa755a",
                         iconColor: "#fa755a"
                    }
               };
               var card = elements.create("card", { style: style });
               card.mount("#card-element");
               card.on("change", function (event) {
                    document.querySelector("button").disabled = event.empty;
                    document.querySelector("#card-error").textContent = event.error ? event.error.message : "";
               });
               if (data.taxRate != '') {
                    id("taxRate").innerText = " + tax (" + data.taxRate + ")";
               }
               if (data.prorated == 'n' && (id("numpeople").value * cost * ((id("duration").value) - 1)) == 0) {
                    id("popupTotal").innerText = id("numpeople").value * cost * id("duration").value;
               }
               var form = id("payment-form");
               var tdtag = document.querySelectorAll('.trtag')
               tdtag.forEach(el => {
                    el.classList.add('visibility');
                    el.classList.remove('notvisible');

               })
               form.addEventListener("submit", function (event) {
                    event.preventDefault();
                    payWithCard(stripe, card, data.clientSecret);
               });
          });
     var payWithCard = function (stripe, card, clientSecret) {
          loading(true);
          stripe
               .confirmCardPayment(clientSecret, {
                    payment_method: {
                         card: card
                    }
               })
               .then(function (result) {
                    if (result.error) {
                         showError(result.error.message);
                    } else {
                         orderComplete(result.paymentIntent.id);
                         $('#hide').fadeOut(500);
                         setTimeout(function () { window.location = "confirmation"; }, 500);
                    }
               });
     };
     /* ------- UI helpers ------- */
     // Shows a success message when the payment is complete
     var orderComplete = function (paymentIntentId) {
          loading(false);
          document
               .querySelector(".result-message a")
               .setAttribute(
                    "href",
                    "https://dashboard.stripe.com/test/payments/" + paymentIntentId
               );
          document.querySelector(".result-message").classList.remove("hidden");
          document.querySelector("button").disabled = true;
     };
     // Show the customer the error from Stripe if their card fails to charge
     var showError = function (errorMsgText) {
          loading(false);
          var errorMsg = document.querySelector("#card-error");
          errorMsg.textContent = errorMsgText;
          setTimeout(function () {
               errorMsg.textContent = "";
          }, 4000);
     };
     // Show a spinner on payment submission
     var loading = function (isLoading) {
          if (isLoading) {
               // Disable the button and show a spinner
               document.querySelector("button").disabled = true;
               document.querySelector("#spinner").classList.remove("hidden");
               document.querySelector("#button-text").classList.add("hidden");
          } else {
               document.querySelector("button").disabled = false;
               document.querySelector("#spinner").classList.add("hidden");
               document.querySelector("#button-text").classList.remove("hidden");
          }
     };
}

function formatDate(date) {
     var d = new Date(date);
     var hh = d.getHours();
     var m = d.getMinutes();
     var s = d.getSeconds();
     var dd = "AM";
     var h = hh;
     if (h >= 12) {
          h = hh - 12;
          dd = "PM";
     }
     if (h == 0) {
          h = 12;
     }
     m = m < 10 ? "0" + m : m;

     s = s < 10 ? "0" + s : s;

     h = h < 10 ? "0" + h : h;

     var pattern = new RegExp("0?" + hh + ":" + m + ":" + s);

     var replacement = h + ":" + m;
     replacement += " " + dd;

     return date.replace(pattern, replacement);
}
