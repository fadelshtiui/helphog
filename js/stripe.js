var stripe = Stripe('pk_live_51H77jdJsNEOoWwBJ7Rhp3s4qxRaio4fFXEhm9FcJ7cazHkTbyFENYLXhLqZKdUBoCIT5QO77odFxkwKhkYqZ48La000JNX6geu');

var elements = stripe.elements();
var cardElement = elements.create('card');
cardElement.mount('#card-element');

var cardholderName = document.getElementById('cardholder-name');
var cardButton = document.getElementById('card-button');
var clientSecret = cardButton.dataset.secret;

cardButton.addEventListener('click', function (ev) {

  stripe.confirmCardSetup(
    clientSecret,
    {
      payment_method: {
        card: cardElement,
        billing_details: {
          name: cardholderName.value,
        },
      },
    }
  ).then(function (result) {
    if (result.error) {
      // Display error.message in your UI.
    } else {
      // The setup has succeeded. Display a success message.
    }
  });
});