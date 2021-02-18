/* global fetch */

"use strict";

var uploading = false;

let preloaded = false;

(function () {

	window.addEventListener('beforeunload', (event) => {
		if (uploading) {
			event.preventDefault();
			event.returnValue = 'Are you sure you want to exit?';
		}
	});

	$(document).ready(function () {

		id('locationField').classList.add('hidden')

		id('edit').addEventListener('click', toggleAddressDisplay)

		var checkbox1 = document.querySelector("input[name=email-notification]");

		checkbox1.addEventListener('change', function () {
			checkbox();
		});

		var checkbox2 = document.querySelector("input[name=sms-notification]");

		checkbox2.addEventListener('change', function () {
			checkbox();
		});

		id('clear-availability').addEventListener('click', clearAvailability)

		id('addressDisplay').addEventListener('click', toggleAddressDisplay)

		id('sliderUpdate').addEventListener("mouseup", updateDistance);

		var timezoneSelector = document.querySelector("#timezone")

		timezoneSelector.addEventListener("change", updateTimezone);

		id("return").onclick = function () {
			window.location.href = "/";
		};

		id("previewer").classList.add("hidden");

		id("logo").onclick = function () {
			window.location.href = "/";
		};

		let tz = jstz.determine();
		let timezone = tz.name();

		let data = new FormData();
		data.append("session", getSession());
		data.append('tz', timezone);
		let url = "php/provider.php";
		fetch(url, { method: "POST", body: data })
			.then(checkStatus)
			.then(res => res.json())
			.then(checkSignedIn)
			.catch(console.log);
		let input = id("pass");
		id("submit").onclick = submitter;
		id("stripeportal").onclick = enterstripe;
		input.addEventListener("keyup", function (event) {
			event.preventDefault();
			if (event.keyCode === 13) {
				id("submit").click();
			}
		});

		const inputElement = id('work-phone');

	});

	function clearAvailability() {
		let rows = document.querySelectorAll(".time-slot")
		rows.forEach(row => {
			row.removeAttribute('data-selected')
		})
		document.querySelector('.import').style.color = "red";
		submit();
	}

	function toggleAddressDisplay() {
		// if (id('current-city').innerText != "Please enter your address here"){
		//     id('current-city').style.color = 'black'
		// }
		id('addressDisplay').classList.add('hidden')
		id('edit').classList.add('hidden')
		id('locationField').classList.remove('hidden')
		id('autocomplete').classList.remove('hidden')
		window.addEventListener('click', function (e) {
			if (!id('autocomplete').contains(e.target) && !id('addressDisplay').contains(e.target) && !id('edit').contains(e.target) && id('current-city').innerText != "Please enter your address here") {
				id('addressDisplay').classList.remove('hidden')
				id('locationField').classList.add('hidden')
				id('edit').classList.remove('hidden')
				id('current-city').style.color = 'black'
				document.querySelector('.noaddress').style.color = '#5f6876';
				updateDistance();
			}
		});
	}

	function enterstripe() {
		let session = "";
		let cookies = document.cookie.split(";");
		for (let i = 0; i < cookies.length; i++) {
			let key = cookies[i].split("=");
			if (key[0].trim() == "session") {
				session = key[1];
			}
		}
		let data = new FormData();
		data.append("session", session);
		let url = "php/expresslogin.php";
		fetch(url, {
			method: "POST",
			body: data,
			mode: 'cors',
			credentials: 'include'
		})
			.then(checkStatus)
			.then(res => res.text())
			.then(redirect);
	}

	function redirect(response) {
		window.location.href = response;
	}

	async function upload() {
		this.parentElement.parentElement.previousElementSibling.classList.remove('done-loading')
		let session = "";
		let cookies = document.cookie.split(";");
		for (let i = 0; i < cookies.length; i++) {
			let key = cookies[i].split("=");
			if (key[0].trim() == "session") {
				session = key[1];
			}
		}


		let data = new FormData();
		let ordernumber = this.dataset.ordernumber;
		let upload = this.parentElement.previousElementSibling.firstElementChild.files[0];

		if (upload) {
			this.innerText = "RE-UPLOAD";
			this.parentElement.parentElement.previousElementSibling.innerText = "Your receipt is currently being uploaded. Please do not leave the page.";
			this.parentElement.parentElement.previousElementSibling.classList.add('upload-loading')

			data.append("ordernumber", ordernumber);
			data.append('image', upload, upload.name);
			data.append('session', session);
			uploading = true;
			let url = "php/imageupload.php";
			let response = await fetch(url, { method: "POST", body: data })
			await checkStatus(response)
			response = await response.text();
			this.parentElement.parentElement.previousElementSibling.classList.remove('upload-loading')
			this.parentElement.parentElement.previousElementSibling.classList.add('done-loading')
			uploading = false;

			let messageBox = document.querySelector(".attach-message");
			if (response.startsWith("amount")) {
				this.parentElement.parentElement.previousElementSibling.innerText = "We have processed your extra expenditures for a total of $" + response.substring(6)
			} else {
				this.parentElement.parentElement.previousElementSibling.innerText = response;
			}
		} else {
			this.parentElement.previousElementSibling.firstElementChild.style.color = 'red';
		}

	}

	function checkSignedIn(response) {
		if (response.sessionerror == "") {
			$(".submit").addClass("hide-loading");
			$(".done").addClass("finish");
			$(".submit").removeClass("loading");
			$(".submit").removeClass("hide-loading");
			$(".done").removeClass("finish");
			$(".failed").removeClass("finish");
			$(".form").hide();
			$(".pass").hide();
			$(".row").hide();
			$(".panel").show();
			tl.to($('.panel'), 1, {
				opacity: 1,
				scale: 1
			})
				.staggerFrom($('.panel > .title div'), 0.5, {
					opacity: 0,
					x: -50
				}, 0.4)
				.staggerFrom($('.items .item'), 0.5, {
					opacity: 0,
					x: -15
				}, 0.4);

			displayResults(response);

		} else {
			document.querySelector(".container").classList.remove("hidden");
		}
	}

	function updateDistance() {
		if (id('current-address').innerText == '' || id('current-city').innerText == '' || id('current-state').innerText == '' || id('current-zip').innerText == '') {
			alert('Please enter your full address and select it from the dropdown below');
			return;
		}

		let data = new FormData();
		// 		let phone = id("work-phone").placeholder;
		// 		if (id("work-phone").value) {
		// 			phone = id("work-phone").value;
		// 		}
		// 		let workemail = id("work-email").placeholder;
		// 		if (id("work-email").value) {
		// 			workemail = id("work-email").value;
		// 		}

		let distance = parseInt(id("distance").value);
		// 		data.append("phone", phone.replace(/\D/g,''));
		// 		data.append("work_email", workemail);
		data.append("address", id('current-address').innerText);
		data.append("city", id('current-city').innerText);
		data.append("zip", id('current-zip').innerText);
		data.append("state", id('current-state').innerText);
		data.append("session", getSession());
		data.append("distance", distance);

		let url = "php/updateproviderinfo.php";
		fetch(url, { method: "POST", body: data })
			.then(checkStatus)
			.then(handleResponseContact)
			.catch(console.log);
	}

	async function handleResponseContact(response) {
		if (await response.text() != '') {
			alert(await response.text());
		}
	}

	function reset() {
		let data = new FormData();
		let email = document.getElementById("first").value.toLowerCase();
		let password = document.getElementById("pass").value;
		data.append("email", email);
		data.append("password", password);

		let tz = jstz.determine();
		let timezone = tz.name();

		data.append('tz', timezone)
		let url = "php/provider.php";
		fetch(url, { method: "POST", body: data })
			.then(checkStatus)
			.then(res => res.json())
			.then(handleResponse)
			.catch(console.log);
	}

	function submitter() {
		id("submit").classList.add("loading");
		$(".panel").hide();
		reset();
	}

	function handleResponse(response) {
		if (response.emailerror != "" || response.passworderror != "") {
			setTimeout(function () {
				$(".submit").addClass("hide-loading");
				// For failed icon just replace ".done" with ".failed"
				$(".failed").addClass("finish");
			}, 3000);

			setTimeout(function () {
				$(".submit").removeClass("loading");
				$(".submit").removeClass("hide-loading");
				$(".done").removeClass("finish");
				$(".failed").removeClass("finish");
			}, 5000);

		} else {

			document.cookie = "session=" + response.session + ";";

			setTimeout(function () {
				$(".submit").addClass("hide-loading");
				$(".done").addClass("finish");
			}, 1500);
			setTimeout(function () {
				$(".submit").removeClass("loading");
				$(".submit").removeClass("hide-loading");
				$(".done").removeClass("finish");
				$(".failed").removeClass("finish");
			}, 2500);
			setTimeout(function () {
				$(".form").hide();
				$(".pass").hide();
				$(".row").hide();
				$(".panel").show();
				tl.to($('.panel'), 1, {
					opacity: 1,
					scale: 1
				})
					.staggerFrom($('.panel > .title div'), 0.5, {
						opacity: 0,
						x: -50
					}, 0.4)
					.staggerFrom($('.items .item'), 0.5, {
						opacity: 0,
						x: -15
					}, 0.4);
			}, 2500);

			displayResults(response);

		}
	}

	function displayResults(response) {
		var timezones = document.getElementById('timezone');
		for (var i = 0; i < timezones.length; i++) {
			if (timezones.options[i].value == response.offset) {
				timezones.options[i].selected = true;
				break;
			}
		}

		let size = 24;
		if (!response.availability.includes("1")) {
			var alls = document.getElementsByClassName('import');
			for (var i = 0; i < alls.length; i++) {
				alls[i].style.color = 'red';
			}
		}

		const numChunks = response.availability.length / size;
		const chunks = new Array(numChunks)

		for (let i = 0, o = 0; i < numChunks; ++i, o += size) {
			chunks[i] = response.availability.substr(o, size)
		}

		for (let i = 0; i < 7; i++) {
			let rows = document.querySelectorAll(".time-slot")
			for (let j = 0; j < rows.length; j++) {
				let entry = rows[j];
				let time = parseInt(entry.getAttribute('data-time'))
				let day = entry.getAttribute('data-day')

				if (i == day) {
					if (chunks[i].charAt(time) == '1') {
						entry.setAttribute('data-selected', 'selected')
					}
				}
			}
		}

		if (response.alerts == "both") {
			document.getElementById("sms-notification").checked = true;
			document.getElementById("email-notification").checked = true;
		} else if (response.alerts == "sms") {
			document.getElementById("sms-notification").checked = true;
		} else if (response.alerts == "email") {
			document.getElementById("email-notification").checked = true;
		} else {
			var all = document.getElementsByClassName('important');
			for (var i = 0; i < all.length; i++) {
				all[i].style.color = 'red';
			}
		}

		let percentage = response.radius * 3 - 12 + 'px'
		document.querySelector('.rangeslider-fill-lower').style.width = percentage;
		document.querySelector('.rangeslider-thumb').style.left = percentage;
		id('slider-input').value = "" + response.radius * 1000;
		id("distance").value = response.radius + ' miles';
		id("name").innerText = response.firstname;

		// 		id("work-phone").value = response.workphone;
		// 		id("work-email").value = response.workemail;

		if (response.workaddress == '' || response.workcity == '') {
			id('city-state-comma').classList.add('hidden')
			id('current-city').innerText = 'Please enter your address here'
			id('current-city').style.color = 'red'
			document.querySelector('.noaddress').style.color = 'red';
		} else {
			document.querySelector('.noaddress').style.color = '#5f6876';
			id("current-address").innerText = response.workaddress;
			id("current-city").innerText = response.workcity;
			id("current-zip").innerText = response.workzip;
			id("current-state").innerText = response.workstate;
		}

		if (!preloaded) {
			preloaded = true;
			let orders = response.orders;
			let counter = 0;
			if (orders && orders.length > 0) {
				for (let i = 0; i < orders.length; i++) {


					let order = orders[i];

					if (order.status == 'mc' || order.status == 're' || order.status == 'ac' || order.status == 'pc' || order.status == 'cc') {
						counter++;
						id("no-order-history").classList.add("hidden");
						$('.dashboard-preview').show();
						let block = document.createElement("div");
						block.classList.add("dashboard-list");
						let element = document.createElement("div");
						element.classList.add("dashboard-list__item");
						block.appendChild(element);
						let service = document.createElement("h2");
						service.innerText = "#" + order.order_number + ": " + order.service;

						let customer = document.createElement("h2");
						customer.innerText = order.customer_email;
						let span = document.createElement("span");
						span.innerText = "Date: " + order.schedule;
						element.appendChild(service);
						element.appendChild(customer);
						element.appendChild(span);
						block.addEventListener("click", update);

						block.dataset.order_number = order.order_number;
						block.dataset.customer_email = "Phone Contact: " + order.customer_phone;
						block.dataset.message = order.message;
						block.dataset.timestamp = "Date: " + order.timestamp;
						block.dataset.schedule = order.schedule;
						block.dataset.address = order.address;
						block.dataset.service = order.service;
						block.dataset.price = order.price;
						block.dataset.satisfied = order.satisfied;
						block.dataset.rating = order.rating;
						block.dataset.comment = order.comment;
						block.dataset.start = order.start;
						block.dataset.end = order.end;
						if (order.status == 're') {
							block.dataset.revenue = "0 - Refunded"
						} else if (order.status == 'ac' || order.status == 'pc' || order.status == 'cc') {
							block.dataset.revenue = "0 - Cancelled"
						} else {
							block.dataset.revenue = order.revenue;
						}

						block.dataset.wage = order.wage;

						id("my_trip").appendChild(block);
						if (counter == 1) {
							block.click();
						}
					} else {
						id("no-ongoing-orders").classList.add("hidden");
						let container = document.createElement("div");
						container.classList.add("slider-card");

						let title = document.createElement("div");
						title.classList.add("title");
						title.textContent = "#" + order.order_number + ": " + order.service;

						if (order.status == 'di') {
							title.style.color = 'red';
							title.textContent = title.textContent + " (Disputed)"
						}

						let date = document.createElement("div");
						date.classList.add("date");
						date.textContent = order.schedule;

						let section1 = document.createElement("div");
						section1.classList.add("section");

						let image = document.createElement("img");
						image.src = "assets/images/home.png";

						let h1 = document.createElement("h1");
						h1.textContent = "Address";

						let h51 = document.createElement("h5");
						h51.style.color = 'black';
						h51.style.fontWeight = "400";
						h51.textContent = order.customer_email;

						let h52 = document.createElement("h5");
						h52.style.color = 'black';
						h52.style.fontWeight = "400";
						h52.textContent = order.customer_phone;

						let h53 = document.createElement("h5");
						h53.style.color = 'black';
						h53.style.fontWeight = "400";
						h53.textContent = order.address;

						let br1 = document.createElement("br");
						let br2 = document.createElement("br");

						section1.appendChild(image);
						section1.appendChild(h1);
						section1.appendChild(h51);
						section1.appendChild(br1);
						section1.appendChild(h52);
						section1.appendChild(br2);
						section1.appendChild(h53);

						let section2 = document.createElement("div");
						section2.classList.add("section");

						let image2 = document.createElement("img");
						image2.src = "assets/images/cash.png";

						let salary = document.createElement("h1");
						salary.textContent = "Salary";

						let price = document.createElement("h3");
						let displayPrice = "$" + order.price;

						price.textContent = displayPrice;

						let wageType = document.createElement("h4");
						if (order.wage == "hour") {
							wageType.textContent = "per hour";
						} else {
							wageType.textContent = "flat rate";
						}

						section2.appendChild(image2);
						section2.appendChild(salary);
						section2.appendChild(price);
						section2.appendChild(wageType);

						if (order.secondary_providers_string.length != 0) {
							let coworkers = document.createElement("h1");
							coworkers.textContent = "Co-workers";
							section2.appendChild(coworkers);

							for (let i = 0; i < order.secondary_providers_string.length; i++) {
								let coworkersContact = document.createElement("h5");
								coworkersContact.style.color = 'black';
								coworkersContact.style.fontWeight = "400";
								coworkersContact.textContent = order.secondary_providers_string[i];
								section2.appendChild(coworkersContact);
							}
						}

						let section3 = document.createElement("div");
						section3.classList.add("section");

						let image3 = document.createElement("img");
						image3.src = "assets/images/download.png";

						let documents = document.createElement("h1");
						documents.textContent = "Upload Documents";

						let attach = document.createElement("h5");
						attach.classList.add("attach-message")

						attach.textContent = "Attach receipt(s) of any additional expenditures spent on the task:";

						if (order.expenditure > 0) { // have already uploaded***

						}

						let form = document.createElement("div");
						form.classList.add("expenditure");

						let choosefilediv = document.createElement("div");
						choosefilediv.id = "choose-file-div";

						let choosefileinput = document.createElement("input");
						choosefileinput.addEventListener("click", function () {
							this.style.color = 'black'
						});

						// if (order.uploaded == "n" || order.expenditure > 0) {
						choosefileinput.classList.add("choose-file");
						choosefileinput.type = "file";
						// 	} else {
						// 		choosefileinput.type = "number";
						// 		choosefileinput.style.backgroundColor = "transparent";
						// 		choosefileinput.style.color = "black";
						// 		choosefileinput.style.width = "80%";
						// 		choosefileinput.style.margin = "auto";
						// 		choosefileinput.style.textAlign = "center";
						// 		choosefileinput.style.fontSize = "24px";
						// 		choosefileinput.style.border = "0";
						// 		choosefileinput.style.borderBottom = "1px solid #ccc";
						// 		choosefileinput.style.borderRadius = "0";
						// 		choosefileinput.style.padding = "5px 0";
						// 	}

						choosefilediv.appendChild(choosefileinput);

						let expenditurediv = document.createElement("div");

						let expenditurebutton = document.createElement("button");
						expenditurebutton.dataset.ordernumber = order.order_number;
						expenditurebutton.classList.add("expenditure-button");
						// expenditurebutton.type = "submit";
						expenditurebutton.name = "upload";
						expenditurebutton.innerText = "UPLOAD";

						// 	// if (order.uploaded == 'y') {
						// 		expenditurebutton.innerText = "SUBMIT";
						// 	}

						if (order.expenditure > 0) {
							attach.textContent = "We have processed your extra expenditures for a total of $" + order.expenditure
							attach.classList.add('done-loading')
							expenditurebutton.innerText = "RE-UPLOAD";

						}
						expenditurebutton.onclick = upload;

						expenditurediv.appendChild(expenditurebutton);

						if (order.role == "primary") {
							form.appendChild(choosefilediv);
							form.appendChild(expenditurediv);
						}


						section3.appendChild(image3);
						section3.appendChild(documents);
						if (order.role == "primary") {
							section3.appendChild(attach);
							section3.appendChild(form);
						} else {
							let notifyMessage = document.createElement("h3");
							notifyMessage.style.fontSize = "20px";
							notifyMessage.style.padding = "22px"
							notifyMessage.style.color = "blue";
							notifyMessage.innerText = "See the primary provider of your group to upload task expenses/receipts.";
							section3.appendChild(notifyMessage);
						}



						let message = document.createElement("div");
						message.classList.add("message");

						let note = document.createElement("h1");
						note.textContent = "Customer Note: ";

						message.appendChild(note);
						message.textContent += order.message;

						let action = document.createElement("div");
						action.classList.add("action");

						let button = document.createElement("button");
						button.innerText = "START";
						if (order.status == "st") {
							button.classList.toggle("started");
							button.innerText = "STOP";
						}

						button.dataset.ordernumber = order.order_number;

						if (order.status == "en" || order.status == 'di') {
							button.innerText = "MARK COMPLETED";
							button.onclick = markCompleted;
						} else {
							button.addEventListener("click", toggle);
						}

						let button2 = document.createElement("button");
						button2.innerText = "CANCEL";

						button2.dataset.ordernumber = order.order_number;

						if (order.currently_paused == 'y') {
							button2.innerText = "RESUME";
						} else if (order.status == "st") {
							button2.innerText = "PAUSE";
							button2.classList.toggle("paused");
						}

						if (order.status == "en" || order.status == "di") {
							button2.innerText = "REFUND CUSTOMER";
							button2.classList.toggle('started');
							button2.onclick = refund;
						} else {
							button2.addEventListener("click", cancel);
						}

						button.style.marginTop = "14px";
						button2.style.marginTop = "14px";

						if (order.role == "primary") {
							button2.dataset.role = 'primary';
							action.appendChild(button);
							action.appendChild(button2);
						} else {
							if (button2.innerText == 'CANCEL') {
								button2.dataset.role = 'secondary';
								action.appendChild(button2);
							} else {
								let header3 = document.createElement("h3");
								header3.style.fontSize = "20px";
								header3.style.padding = "15px";
								header3.innerText = "Only the primary provider has the ability to alter the order status.";
								action.appendChild(header3);
							}

						}


						container.appendChild(title);
						container.appendChild(date);
						container.appendChild(section1);
						container.appendChild(section2);
						container.appendChild(section3);
						container.appendChild(message);
						container.appendChild(action);

						let outer = document.querySelector(".content-card");
						outer.appendChild(container);
					}
				}
			}
		}
		document.querySelector(".container").classList.remove("hidden");

	}

	function markCompleted() {
		if (confirm("Please verify that all necessary receipts are attached of any additional expenditures.")) {

            id('loading').classList.remove('hidden')
			let data = new FormData();
			let ordernumber = this.dataset.ordernumber;
			data.append("ordernumber", ordernumber);
			data.append("session", getSession());
			let url = "php/markcompleted.php";
			fetch(url, { method: "POST", body: data })
				.then(checkStatus)
				.then(res => res.json())
				.then(function (response) {
					if (response.error == "") {
						location.reload();
					} else {
						alert(response.error);
					}

				})
				.catch(console.log);
		}
	}

	function refund() {

		if (confirm("Are you sure that you would like to refund the customer?")) {

			let data = new FormData();
			let ordernumber = this.dataset.ordernumber;
			data.append("ordernumber", ordernumber);
			data.append('session', getSession());
			let url = "php/refund.php";
			fetch(url, { method: "POST", body: data })
				.then(checkStatus)
				.then(function (response) {
					location.reload();
				})
				.catch(console.log);
		}
	}

	function cancel() {

		if (this.innerText == "CANCEL") {
			if (confirm("Are you sure that you would like to cancel the task? Please only cancel under extenuating circumstances.")) {
				let data = new FormData();
				let ordernumber = this.dataset.ordernumber;
				let role = this.dataset.role
				let tz = jstz.determine();
				let timezone = tz.name();
				data.append("ordernumber", ordernumber);
				data.append("session", getSession());
				data.append("tzoffset", timezone);
				data.append('role', role)
				let url = "php/providercancel.php";
				fetch(url, { method: "POST", body: data })
					.then(checkStatus)
					.then(res => res.text())
					.then(function (response) {
						location.reload();
					})
					.catch(console.log);
			}
		} else {
			let type = this.innerText;
			let url = "php/pause.php";
			if (type == "RESUME") {
				url = "php/resume.php"
			}

			let data = new FormData();
			let ordernumber = this.dataset.ordernumber;
			data.append("ordernumber", ordernumber);
			data.append("session", getSession());

			this.classList.toggle("paused");

			if (type == "PAUSE") {
				this.innerText = "RESUME";
			} else {
				this.innerText = "PAUSE";
			}

			fetch(url, { method: "POST", body: data })
				.then(checkStatus)
				.catch(console.log);
		}
	}

	function toggle() {

		let data = new FormData();
		let ordernumber = this.dataset.ordernumber;
		data.append("ordernumber", ordernumber);
		data.append("session", getSession())

		if (this.innerText == "START") {
			if (confirm("Are you sure you would like to start?")) {

				this.classList.add("started");
				this.innerText = "STOP";
				this.nextElementSibling.innerText = "PAUSE";
				this.nextElementSibling.classList.add("paused");
			}
		} else {
			if (confirm("Are you sure you would like to stop?")) {

				this.classList.remove("started");
				this.innerText = "MARK COMPLETED";
				this.removeEventListener("click", toggle);
				this.addEventListener("click", markCompleted);

				this.nextElementSibling.innerText = "REFUND CUSTOMER";
				this.nextElementSibling.classList.remove("paused");
				this.nextElementSibling.classList.add('started')
				this.nextElementSibling.removeEventListener("click", cancel);
				this.nextElementSibling.addEventListener("click", refund);

			}
		}

		let url = "php/startstop.php";
		fetch(url, { method: "POST", body: data })
			.then(checkStatus)
			.then(res => res.json())
			.then((response) => {
				if (response.error != "") {
					alert(response.error)
					this.classList.remove("started");
					this.innerText = "START";
					this.nextElementSibling.innerText = "CANCEL";
					this.nextElementSibling.classList.remove("paused");
				}
			})

	}

	function update() {

		id("price_display").innerText = "$" + this.dataset.price;
		id("start_display").innerText = this.dataset.start;
		id("end_display").innerText = this.dataset.end;
		if (this.dataset.revenue == 'Refunded') {
			id("revenue_display").innerText = this.dataset.revenue;
		} else {
			id("revenue_display").innerText = "$" + this.dataset.revenue;
		}


		let revenue = '' + this.dataset.revenue

		if (revenue.indexOf(".") != -1) {
			if (revenue.substring(revenue.indexOf(".") + 1).length < 2) {
				id("revenue_display").innerText += '0';
			}
		}

		if (this.dataset.wage == "hour") {
			id("price_display").innerText += "/hr";
		}

		id("customer_email_display").innerText = this.dataset.customer_email;
		id("service_display").innerText = this.dataset.service;
		id("address_display").innerText = this.dataset.address;
		let others = document.querySelectorAll(".dashboard-list__item");
		for (let i = 0; i < others.length; i++) {
			others[i].classList.remove("dashboard-list__item--active");
		}
		this.firstElementChild.classList.add("dashboard-list__item--active");
	}


	function checkbox() {

		let alerts;;

		var x = $("#email-notification").is(":checked");
		var y = $("#sms-notification").is(":checked");

		if (x && y) {
			alerts = "both";
		} else if (x === true && y === false) {
			alerts = "email"
		} else if (x === false && y === true) {
			alerts = "sms"
		} else {
			alerts = "none"
			clearAvailability();
		}

		if (alerts == "none") {
			var all = document.getElementsByClassName('important');
			for (var i = 0; i < all.length; i++) {
				all[i].style.color = '#000000b3';
			}
		} else {
			var all = document.getElementsByClassName('important');
			for (var i = 0; i < all.length; i++) {
				all[i].style.color = '#5f6876';
			}
		}

		let data = new FormData();
		data.append("session", getSession());
		data.append("alerts", alerts);
		let url = "php/alerts.php";
		fetch(url, { method: "POST", body: data })
			.then(checkStatus)
			.then(reload)
			.catch(console.log);
		;
	}

	function updateTimezone() {

		if (id("timezone").value) {
			let data = new FormData();
			let timezone = id("timezone").value;
			data.append("timezone", timezone);
			data.append("session", getSession());
			let url = "php/changetimezone.php";
			fetch(url, { method: "POST", body: data })
		}
	}

	function reload() {

		let data = new FormData();
		let tz = jstz.determine();
		let timezone = tz.name();
		data.append("session", getSession());
		data.append('tz', timezone)
		let url2 = "php/provider.php";
		fetch(url2, { method: "POST", body: data })
			.then(checkStatus)
			.then(res => res.json())
			.then(checkSignedIn)
			.catch(console.log);
	}


	var tl = new TimelineMax({
		delay: 1
	});

})();
