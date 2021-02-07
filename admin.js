var $TABLE = $('#table');
var $BTN = $('#export-btn');
var $EXPORT = $('#export');
var $LOAD = $('#load')
var $ADD = $('#add')
var $REMOVE = $('#remove')

$('.table-add').click(function() {
  var $clone = $TABLE.find('tr.hide').clone(true).removeClass('hide table-line');
  $TABLE.find('table').append($clone);
});

$('.table-remove').click(function() {
  $(this).parents('tr').detach();
});

$('.table-up').click(function() {
  var $row = $(this).parents('tr');
  if ($row.index() === 1) return; // Don't go above the header
  $row.prev().before($row.get(0));
});

$('.table-down').click(function() {
  var $row = $(this).parents('tr');
  $row.next().after($row.get(0));
});

// A few jQuery helpers for exporting only
jQuery.fn.pop = [].pop;
jQuery.fn.shift = [].shift;

$BTN.click(function() {
  var $rows = $TABLE.find('tr:not(:hidden)');
  var headers = [];
  var services = [];

  // Get the headers (add special header logic here)
  $($rows.shift()).find('th:not(:empty):not([data-attr-ignore])').each(function() {
    headers.push($(this).text().toLowerCase());
  });

  // Turn all existing rows into a loopable array
  $rows.each(function() {
    var $td = $(this).find('td');

    // Use the headers from earlier to name our hash keys
    headers.forEach(function(header, i) {
      services.push($td.eq(i).text()); // will adapt for inputs if text is empty
    });

  });

    let data = new FormData();
    data.append("password", document.getElementById('password').value);
    data.append('email', document.getElementById('email').value)
    data.append('services', JSON.stringify(services))
    
    let url = "php/updateservices.php";
    fetch(url, {method: "POST", body: data, mode:'cors', credentials:'include'})
    .then(checkStatus)
    .then(alert)
    .catch(console.log);
});

$LOAD.click(function() {
    
    let data = new FormData();
    data.append("password", document.getElementById('password').value);
    data.append('email', document.getElementById('email').value)

    let url = "php/loadproviderservices.php";
    fetch(url, {method: "POST", body: data, mode:'cors', credentials:'include'})
    .then(checkStatus)
    .then(updateList)
    .catch(console.log);
    
})

$ADD.click(function() {
    toggleAccountType('add')
})
$REMOVE.click(function() {
    toggleAccountType('remove')
})

function toggleAccountType(action) {
    let data = new FormData();
    data.append("password", document.getElementById('password').value);
    data.append('email', document.getElementById('email').value)
    data.append('action', action)
    
    let url = "php/toggleaccount.php";
    fetch(url, {method: "POST", body: data, mode:'cors', credentials:'include'})
    .then(checkStatus)
    .then(alert)
    .catch(console.log);
}

function updateList(response) {
    
    let services = response.split(',');
    console.log(services)
    for (let i = 0; i < services.length; i++) {
        var $clone = $TABLE.find('tr.hide').clone(true).removeClass('hide table-line');
        $clone.find('td').eq(0).text(services[i])
        $TABLE.find('table').append($clone);
    }
    
}

