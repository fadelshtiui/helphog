/* global fetch */

"use strict";

(function () {
	$(document).ready(function () {
		$("#logo").click(function () {
			window.location.href = "/";
		})
		$("#return").click(function () {
			window.location.href = "/";
		})
		$(".panel").hide();
		$(".submit").click(function () {
			$(".submit").addClass("loading");
			reset();
		})
	});

	function reset() {
		let data = new FormData();
		let order = id('first').value;
		let email = id('second').value;
		data.append("order", order);
		data.append('email', email)
		let url = "php/tracking.php"
		fetch(url, { method: "POST", body: data })
			.then(checkStatus)
			.then(res => res.json())
			.then(handleResponse)
			.catch(console.log);
	}

	function handleResponse(response) {



		if (response.emailerror == "true" || response['status'] == "nf") {
			setTimeout(function () {
				$(".submit").addClass("hide-loading");
				// For failed icon just replace ".done" with ".failed"
				$(".failed").addClass("finish");
			}, 1000);
			setTimeout(function () {
				$(".submit").removeClass("loading");
				$(".submit").removeClass("hide-loading");
				$(".done").removeClass("finish");
				$(".failed").removeClass("finish");
				document.getElementById('message').innerText = "Incorrect order number."

				if (response.emailerror == "true") {
					document.getElementById('message').innerText = "Email not found."
				}
			}, 1000);
		} else {
			setTimeout(function () {
				$(".submit").addClass("hide-loading");
				// For failed icon just replace ".done" with ".failed"
				$(".done").addClass("finish");
			}, 1000);
			setTimeout(function () {
				$(".submit").removeClass("loading");
				$(".submit").removeClass("hide-loading");
				$(".done").removeClass("finish");
				$(".failed").removeClass("finish");
			}, 1000);
			setTimeout(function () {
				$(".form").hide();
				$(".row").hide();
				$(".panel").show();
			}, 1000);

		}
		console.log(response.service)
		id('service').innerText = response.service
		if (response.cancelled == "true") {
			id("canceled").style.display = "block";
		} else if (response['status'] == "pe") {
			$('*[data-state="picked-up"]').hide();
			$('*[data-state="transit"]').hide();
			$('*[data-state="delivered"]').hide();
		}
		if (response['status'] == "cl") {
			$('*[data-state="transit"]').hide();
			$('*[data-state="delivered"]').hide();
		}
		if (response['status'] == "st") {
			$('*[data-state="delivered"]').hide();
		}

	}

})();