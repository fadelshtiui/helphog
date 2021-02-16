$(window).on('load', function () {
	id('loading').classList.add('hidden')
	$('#loading-animation').css('display', 'none');
    $('#loading-animation').fadeIn(900);
    $('a').css('display', 'none');
    $('a').fadeIn(900);
    $('.title').css('display', 'none');
    $('.title').delay( 200 ).fadeIn(900);
    $('h1').css('display', 'none');
    $('h1').delay( 400 ).fadeIn(900);
    $('h2').css('display', 'none');
    $('h2').delay( 500 ).fadeIn(900);
    $('#app').css('display', 'none');
    $('#app').delay( 600 ).fadeIn(900);

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

		  id('loading').classList.remove('hidden')
	       let data = new FormData();
	       data.append("ordernumber", itemSelected.number);
	       data.append("session", getSession())
	       let url = "php/dispute.php";
    	   let response = await fetch(url, { method: "POST", body: data })
    	   await checkStatus(response)
    	   response = await response.json()
    	   id('loading').classList.add('hidden')
    	   this.showModal = false;
    	   if (response.result == 'successful') {
	           itemSelected.status = "di";
	       } else {
	           alert('Sorry, orders may only be disputed within 24 hours of completion.')
	       }


	    },

	    viewImage(props) {

	        let data = new FormData();
            data.append("ordernumber", props.number);
            data.append("session", getSession());
            let url = "php/checkimage.php";
            fetch(url, { method: "POST", body: data })
            	.then(checkStatus)
            	.then(res => res.text())
            	.then(function(response) {
            	    if (response == "jpg" || response == "jpeg" || response == "pdf" || response == "png") {
            	        window.open("../tmp/receipts/" + props.number + "." + response);
            	    }
            	})
            	.catch(console.log);
	    },
	    status(review) {
            this.showModal = true;

	    },

	    status2(review) {
            this.showCancel = true;

	    },
	    async cancel(itemSelected) {
           id('loading').classList.remove('hidden')
	       let data = new FormData();
	       data.append("ordernumber", itemSelected.number);
	       data.append('session', getSession());
	       let url = "php/customercancel.php";
    	   let response = await fetch(url, { method: "POST", body: data })
    	   await checkStatus(response)
    	   response = await response.text();
    	   id('loading').classList.add('hidden')

    	   if (response == 'ordererror') {
    	       alert('You cannot cancel an order that is already in progress.');
    	   } else {
    	       this.showCancel = false;
    	       itemSelected.status = "cc";
    	   }


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
  data: function() {
    return {
      temp_value: null,
      ratings: [1, 2, 3, 4, 5]
    };
  },

  methods: {
    /*
     * Behaviour of the stars on mouseover.
     */
    star_over: function(index) {
      var self = this;

      if (!this.disabled) {
        this.temp_value = this.value;
        return this.value = index;
      }

    },

    /*
     * Behaviour of the stars on mouseout.
     */
    star_out: function() {
      var self = this;

      if (!this.disabled) {
        return this.value = this.temp_value;
      }
    },

    /*
     * Set the rating.
     */
    set: function(value) {
      var self = this;

      if (!this.disabled) {
        this.temp_value = value;
        return this.value = value;
      }
    }
  }
});
