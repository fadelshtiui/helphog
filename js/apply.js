$(window).on('load', function () {

	const inputElement = id('phone');
	inputElement.addEventListener('keydown', enforceFormat);
	inputElement.addEventListener('keyup', formatToPhone);

	let url = "php/info.php?type=categories";
	fetch(url, {
		credentials: 'include'
	})
		.then(checkStatus)
		.then(res => res.json())
		.then(populateCategories)
		.catch(console.log);

	$("#sign-up").click(function () {
		// $('.btn').button('loading');
		let data = new FormData();
		let firstName = id("first-name").value;
		let lastName = id("last-name").value;
		let email = id("email").value.toLowerCase();
		let phone = id("phone").value.replace(/\D/g, '');
		let zip = id("zip").value;
		let password = id("password").value;
		let confirm = id("confirm-password").value;
		data.append("zip", zip);
		data.append("firstname", firstName);
		data.append("lastname", lastName);
		data.append("email", email);
		data.append("password", password);
		data.append("phone", phone);
		data.append("confirm", confirm);
		data.append("createaccount", 'false');
		data.append("sendemail", 'false');
		let url = "php/signup.php";
		fetch(url, { method: "POST", body: data })
			.then(checkStatus)
			.then(res => res.json())
			.then(checkFormErrors)
			.catch(console.log);
	});

	$("#submit").click(function () {
		if (isCaptchaChecked()) {
			// $('.btn').button('loading');
			let data = new FormData();

			let workfield = id("dropdown").value;
			let experience = id("aratext").value;
			let radius = '0';
			let resume = id('resume').files[0];

			let url = "php/apply.php";

			let tz = jstz.determine();
			let timezone = tz.name();

			if (resume) {
				data.append('resume', resume, resume.name);
			}
			data.append("radius", radius);
			data.append("workfield", workfield);
			data.append("experience", experience);
			data.append('tz', timezone);

			fetch(url, { method: "POST", body: data })
				.then(checkStatus)
				.then(res => res.text())
				.then(redirectToStripe)
				.catch(console.log);

		} else {
			id('warning-message').innerText = "Please check the reCAPTCHA box."
			document.querySelector('.modal-wrapper').classList.remove('hidden')
		}

	});

	function populateCategories(response) {
		let dropdown = id("dropdown");
		for (let i = 0; i < response.length; i++) {
			let option = document.createElement("option");
			option.value = response[i];
			option.innerText = response[i].charAt(0).toUpperCase() + response[i].slice(1);
			dropdown.append(option);
		}
	}

	function checkFormErrors(response) {
		// $('.btn').button('reset');
		if (response.firstnameerror == "true") {
			id("first-name-error").innerText = "*Only letters and white space\n are allowed for first names.";
		} else if (response.firstnameerror == "empty") {
			id("first-name-error").innerText = "*Required field";
		} else {
			id("first-name-error").innerText = "";
		}

		if (response.lastnameerror == "true") {
			id("last-name-error").innerText = "*Only letters and white space are allowed for last names.";
		} else if (response.lastnameerror == "empty") {
			id("last-name-error").innerText = "*Required field";
		} else {
			id("last-name-error").innerText = "";
		}

		if (response.emailerror == "true") {
			id("email-error").innerText = "*Invalid email format.";
		} else if (response.emailerror == "empty") {
			id("email-error").innerText = "*Required field";
		} else if (response.emailerror == "found") {
			id("email-error").innerText = "*Email already registered with an account.";
		} else {
			id("email-error").innerText = "";
		}

		if (response.passworderror == "true") {
			id("password-error").innerText = "*Passwords must be at least 6\n characters and contain no whitespace.";
		} else if (response.passworderror == "empty") {
			id("password-error").innerText = "*Required field";
		} else {
			id("password-error").innerText = "";
		}

		if (response.confirmerror == "true") {
			id("confirm-password-error").innerText = "*Passwords do not match.";
		} else if (response.confirmerror == "empty") {
			id("confirm-password-error").innerText = "*Required field";
		} else {
			id("confirm-password-error").innerText = "";
		}


		if (response.phoneerror == "true") {
			id("phone-error").innerText = "*Please enter a 10 digit\n phone number.";
		} else if (response.phoneerror == "empty") {
			id("phone-error").innerText = "*Required field";
		} else if (response.phoneerror == "found") {
			id("phone-error").innerText = "*Phone number already registered with an account";
		} else {
			id("phone-error").innerText = "";
		}

		if (response.ziperror == "true") {
			id("zip-error").innerText = "*Please enter a 5 digit zip code.";
		} else if (response.ziperror == "empty") {
			id("zip-error").innerText = "*Required field";
		} else {
			id("zip-error").innerText = "";
		}

		if (response.firstnameerror == "" && response.lastnameerror == "" && response.emailerror == "" &&
			response.passworderror == "" && response.phoneerror == "" && response.ziperror == "" && response.confirmerror == "") {
			$("#container2").fadeOut();
			setTimeout(fader, 600);
		}

	}

	function redirectToStripe(response) {

		if (response == "Please upload PNG, JPG, PDF, DOCX files only" || response == 'Error: an account with this email already exists') {

			id('warning-message').innerText = response
			document.querySelector('.modal-wrapper').classList.remove('hidden')

		} else {

			window.location.href = response;

		}
	}

	function isCaptchaChecked() {
		return grecaptcha && grecaptcha.getResponse().length !== 0;
	}


	function fader() {
		$("#part-two").fadeIn();
	}

});