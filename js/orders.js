let selectedStars = 0;

$(window).on('load', function () {
	$('#loading-animation').css('display', 'none');
	$('#loading-animation').fadeIn(900);
	$('a').css('display', 'none');
	$('a').fadeIn(900);
	$('.title').css('display', 'none');
	$('.title').delay(200).fadeIn(900);
	$('h1').css('display', 'none');
	$('h1').delay(400).fadeIn(900);
	$('h2').css('display', 'none');
	$('h2').delay(500).fadeIn(900);
	$('#app').css('display', 'none');
	$('#app').delay(600).fadeIn(900);

	qs('.modal-wrapper').addEventListener('click', function () {
		this.classList.add('hidden')
	})
	qs('.modal').addEventListener('click', function (e) {
		e.stopPropagation();
	})

	let tz = jstz.determine();
	let timezone = tz.name();

	data = new FormData();
	data.append("session", getSession());
	data.append("tz", timezone);
	url = "php/orders.php";
	fetch(url, { method: "POST", body: data })
		.then(checkStatus)
		.then(res => res.json())
		.then(updateOrders)
		.catch(console.log);
});

function openCancelPopup(orderNumber) {
	let data = new FormData();

	data.append("ordernumber", orderNumber)
	data.append('session', getSession())

	let url = "php/checkifcancel.php";
	fetch(url, { method: "POST", body: data })
		.then(checkStatus)
		.then(res => res.json())
		.then(handleResponse)
		.catch(console.log);
}

function handleResponse(response) {
	let date = new Date(response.schedule + " GMT");

	id('yes').innerText = "Yes, cancel it"
	id('no').innerText = "No, keep it"
	id('yes').classList.remove('hidden')
	id('first').innerHTML = ""
	id("first").textContent = "Are you sure you want to cancel " + response.service + " (" + response.order + ") on " + date.toLocaleString() + "?";
	if (response.within == "true") {
		id("second").textContent = "Since your order is within 24 hours of now, a non-refundable fee of $15 will be charged to your account."
	} else {
		id("second").textContent = "You may cancel your order now free of charge."
	}

	id('stars-container').classList.add('hidden')

	qs('.modal-wrapper').classList.remove('hidden')

	qs('.actions').classList.remove('extra-button-margin')

	id('no').onclick = closePopup

	id('yes').onclick = async function () {
		id('loading').classList.remove('hidden')
		let data = new FormData();
		data.append("ordernumber", response.order);
		data.append('session', getSession());
		let url = "php/customercancel.php";
		let response2 = await fetch(url, { method: "POST", body: data })
		await checkStatus(response2)
		response2 = await response2.text();
		id('loading').classList.add('hidden')

		if (response2 == 'ordererror') {
			id('first').innerHTML = "";
			let warningIcon = ce('i')
			warningIcon.classList.add('fas', "fa-exclamation-circle", "warning-orders")
			id('first').appendChild(warningIcon)
			id('second').innerText = 'You cannot cancel an order that is already in progress.'
			id('yes').classList.add('hidden')
			id('no').innerText = "OK"
			qs('.modal-wrapper').classList.remove('hidden')
		} else {
			// qs('.modal-wrapper').classList.add('hidden')
			location.reload()
		}
	}
}

function closePopup() {
	qs('.modal-wrapper').classList.add('hidden')
}

function openReviewPopup(orderNumber, name) {
	id('yes').classList.remove('hidden')
	id('first').innerHTML = "";
	id('first').innerText = "If your order has been successfully completed please review " + name + "'s performance as your provider and mark the order completed."
	id('second').innerText = " If you have any questions or concerns please don't hesitate to contact us."

	id('yes').classList.remove('primary-red');
	id('yes').classList.add('primary-green');

	id('yes').innerText = "Mark Completed"
	id('no').innerText = "Dispute Order"

	id('no').classList.remove('secondary-orders')
	id('no').classList.add('primary-red')

	id('stars-container').classList.remove('hidden')

	qs('.actions').classList.add('extra-button-margin')

	qs('.modal-wrapper').classList.remove('hidden')

	id('yes').onclick = async function () {
		id('loading').classList.remove('hidden')
		let data = new FormData();
		data.append("ordernumber", orderNumber);
		data.append("rating", selectedStars);
		data.append('session', getSession())
		let url = "php/rating.php";
		let res = await fetch(url, { method: "POST", body: data })
		await checkStatus(res)
		location.reload();
	}

	id('no').onclick = async function () {
		id('loading').classList.remove('hidden')
		let data = new FormData();
		data.append("ordernumber", orderNumber);
		data.append("session", getSession())
		let url = "php/dispute.php";
		let response = await fetch(url, { method: "POST", body: data })
		await checkStatus(response)
		response = await response.json()
		id('loading').classList.add('hidden')
		if (response.result == 'successful') {
			location.reload()
		} else {
		    id('stars-container').classList.add('hidden')
			id('first').innerHTML = "";
			let warningIcon = ce('i')
			warningIcon.classList.add('fas', "fa-exclamation-circle", "warning-orders")
			id('first').appendChild(warningIcon)
			id('second').innerText = 'Sorry, orders may only be disputed within 24 hours of completion.'
			id('yes').classList.add('hidden')
			id('no').innerText = "OK"
			qs('.modal-wrapper').classList.remove('hidden')
			id('no').classList.remove('primary-red');
			id('no').classList.add('secondary-orders');
			id('no').onclick = function(){
			    qs('.modal-wrapper').classList.add('hidden')
			}

		}
	}

}


function updateOrders(response) {
	v.orders = response.orders;
}

var v = new Vue({
	el: '#app',

	data() {
		return {
			expand: false,
			headers: [{
				text: 'Order Number',
				align: 'left',
				sortable: false,
				value: 'number',
				width: '10%'
			},

			{
				text: 'Service',
				value: 'name',
				align: 'left',
				width: '15%'
			},
			{
				text: 'Date',
				value: 'date',
				align: 'left',
				width: '10%'
			},
			{
				text: 'Time',
				value: 'time',
				align: 'left',
				width: '10%'
			},
			{
				text: 'Provider',
				value: 'provider',
				align: 'left',
				width: '10%'
			},
			{
				text: 'Cost',
				value: 'amount',
				align: 'left',
				width: '15%'
			},
			{
				text: 'Status',
				value: 'status',
				align: 'left',
				width: '15%'
			},
			{
				text: 'Actions',
				value: 'review',
				align: 'left',
				width: '15%'
			}
			],

			orders: [],
			rating: 0,
			showModal: false,
			showCancel: false,
			selectedItem: null,

		};
	},

	methods: {
		markCompleted(itemSelected) {

			let data = new FormData();
			data.append("ordernumber", itemSelected.number);
			data.append("rating", this.rating);
			data.append('session', getSession())
			let url = "php/rating.php";
			fetch(url, { method: "POST", body: data })
			itemSelected.status = "pd";
			this.showModal = false;

		},
		setRating(rating) {
			this.rating = rating
		},

		async markIncompleted(itemSelected) {

		},

		async viewImage(props) {

			window.open("php/viewreceipt.php?ordernumber=" + props.number + "&image_key=" + props.imagekey);

		},
		status(review) {
			this.showModal = true;

		},

		status2(review) {
			this.showCancel = true;

		},
		async cancel(itemSelected) {

		},
		undo() {
			this.showModal = false;
		},
		undo2() {
			this.showCancel = false;
		}

	}
});

Vue.component('star-rating', {

	props: {
		'name': String,
		'value': null,
		'id': String,
		'disabled': Boolean,
		'required': Boolean
	},

	template: '<div class="star-rating">\
        <label class="star-rating__star" v-for="rating in ratings" \
        :class="{\'is-selected\': ((value >= rating) && value != null), \'is-disabled\': disabled}" \
        v-on:click="set(rating)" v-on:mouseover="star_over(rating)" v-on:mouseout="star_out">\
        <input class="star-rating star-rating__checkbox" type="radio" :value="rating" :name="name" \
        v-model="value" :disabled="disabled">★</label></div>',

	/*
	 * Initial state of the component's data.
	 */
	data: function () {
		return {
			temp_value: null,
			ratings: [1, 2, 3, 4, 5]
		};
	},

	methods: {
		/*
		 * Behaviour of the stars on mouseover.
		 */
		star_over: function (index) {
			var self = this;

			if (!this.disabled) {
				this.temp_value = this.value;
				return this.value = index;
			}

		},

		/*
		 * Behaviour of the stars on mouseout.
		 */
		star_out: function () {
			var self = this;

			if (!this.disabled) {
				return this.value = this.temp_value;
			}
		},

		/*
		 * Set the rating.
		 */
		set: function (value) {
			var self = this;

			if (!this.disabled) {
				this.temp_value = value;
				return this.value = value;
			}
		}
	}
});
