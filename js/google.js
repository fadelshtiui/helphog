let placeSearch;
let autocomplete;
const componentForm = {
  street_number: "short_name",
  route: "long_name",
  locality: "long_name",
  administrative_area_level_1: "short_name",
  country: "long_name",
  postal_code: "short_name",
};



function initAutocomplete() {
  var options = {
    types: ['address'],
    componentRestrictions: { country: "us" }
  };

  // Create the autocomplete object, restricting the search predictions to
  // geographical location types.
  autocomplete = new google.maps.places.Autocomplete(
    document.getElementById("autocomplete"), options
  );
  // Avoid paying for data that you don't need by restricting the set of
  // place fields that are returned to just the address components.
  autocomplete.setFields(["address_component"]);
  // When the user selects an address from the drop-down, populate the
  // address fields in the form.
  autocomplete.addListener("place_changed", fillInAddress);
}

function fillInAddress() {
  // Get the place details from the autocomplete object.
  const place = autocomplete.getPlace();

  for (const component in componentForm) {
    document.getElementById(component).value = "";
    document.getElementById(component).disabled = false;
  }

  // Get each component of the address from the place details,
  // and then fill-in the corresponding field on the form.
  for (const component of place.address_components) {
    const addressType = component.types[0];

    if (componentForm[addressType]) {
      const val = component[componentForm[addressType]];
      document.getElementById(addressType).value = val;
    }
  }

  id('addressDisplay').classList.remove('hidden')
  id('autocomplete').classList.add('hidden')
  id('edit').classList.remove('hidden')

  id('current-address').innerText = id('street_number').value + ' ' + id('route').value;
  id('current-city').innerText = id('locality').value
  id('current-state').innerText = id('administrative_area_level_1').value
  id('current-zip').innerText = id('postal_code').value
  id('city-state-comma').classList.remove('hidden')

  if (window.location.pathname == '/results' || window.location.pathname == '/details') {
    reloadResults();
  }
  if (window.location.pathname == '/edit') {
    checkAvailability(false, updateTimePicker);
  }
  if (window.location.pathname == '/settings') {
    id('city-state-comma').classList.remove('hidden')
    addressUpdate();
  }
  if (window.location.pathname == '/provider') {
    id('addressDisplay').classList.remove('hidden')
    id('locationField').classList.add('hidden')
    id('edit').classList.remove('hidden')
    id('current-city').style.color = 'black'
    document.querySelector('.noaddress').style.color = '#5f6876';
  }
}

function reloadResults() {
  let url = window.location.pathname + '?';

  let queryString = window.location.search
  const urlParams = new URLSearchParams(queryString)

  if (window.location.pathname == '/results') {
    if (urlParams.get('category')) {
      url += 'category=' + urlParams.get('category')
    } else {
      url += 'search=' + urlParams.get('search')
    }
  } else {
    url += 'service=' + urlParams.get('service')
    if (urlParams.get('search')) {
      url += '&search=' + urlParams.get('search')
    } else {
      url += '&category=' + urlParams.get('category')
    }
  }

  if (urlParams.get('origin')) {
    url += '&origin=' + urlParams.get('origin')
  }


  url += '&address=' + id('street_number').value + ' ' + id('route').value;
  url += '&city=' + id('locality').value
  url += '&state=' + id('administrative_area_level_1').value
  url += '&zip=' + id('postal_code').value

  window.location = url;
}


// Bias the autocomplete object to the user's geographical location,
// as supplied by the browser's 'navigator.geolocation' object.
function geolocate() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((position) => {
      const geolocation = {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      };
      const circle = new google.maps.Circle({
        center: geolocation,
        radius: position.coords.accuracy,
      });
      autocomplete.setBounds(circle.getBounds());
    });
  }
}