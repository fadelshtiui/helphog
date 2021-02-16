/* global fetch */

"use strict";

var index = 0;

(function () {

	window.addEventListener('load', function () {

		suggestionLoop();

		var suggestion = ["I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "What do you need help with today?"];

		let button = id("search-btn")
		button.onclick = search;
		let input = id("search");
		input.focus();

		input.addEventListener("keyup", function (event) {
			event.preventDefault();
			if (event.keyCode === 13) {
				button.click();
			}
			let suggestions = document.querySelectorAll("#results li");
			for (let i = 0; i < suggestions.length; i++) {
				suggestions[i].onclick = suggestion;
			}
		});

		let subcategories = document.querySelectorAll(".toggles button");
		for (let i = 0; i < subcategories.length; i++) {
			subcategories[i].onclick = searchCategory;
		}
	});

	function searchCategory() {
		if (id("zipcode").value != "") {
			let searchTerm = this.innerText;
			window.location.href = "results?zip=" + id('zipcode').value + "&category=" + this.innerText.toLowerCase()
		} else {
			window.location.href = "results?category=" + this.innerText.toLowerCase()
		}
	}

	function search() {
		let searchBar = id("search");
		let searchTerm = searchBar.value;
		searchTerm = searchTerm.replace("\"", "");
		searchTerm = searchTerm.replace("\'", "");
		if (searchTerm != '') {
			let url = 'results?search=' + searchTerm
			if (id('zipcode').value) {
				url += '&zip=' + id('zipcode').value
			}
			window.location = url
		}
	}

	function suggestion() {
		let service = this.innerText;
		let url = 'details?service=' + service
		if (id('zipcode').value) {
			url += '&zip=' + id('zipcode').value
		}
		window.location = url
	}

	function suggestionLoop() {
		setTimeout(function () {
			$(".search-input").addClass("fade");
			setTimeout(function () {
				$(".search-input").attr("placeholder", suggestion[index]).removeClass("fade");
			}, 750);
			index++;
			if (index < suggestion.length) {
				suggestionLoop();
			}
		}, 3000);
	}

})();
