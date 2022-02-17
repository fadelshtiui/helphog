window.addEventListener('load', init);

async function init() {
    
    let tz = jstz.determine();
     let timezone = tz.name(); 
     
     id('timezone').value = timezone;
     
  const MAX_DAYS_IN_FUTURE = 5;
  const DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

  let disabledDays = [];

  // change initital value to number of desired pre-computed days
  for (let i = 0; i >= 0; i--) {
      let date = new Date();
      let temp = new Date();
      temp.setDate(date.getDate() + i)

      id('dow').value = temp.toDateString().substring(0, 3)
      id('date').value = temp.toDateString().substring(4, 15)

      let response = await checkAvailability('false', updateTimePicker, false)
      if (!response.availability.includes("1")) {
          disabledDays.push(DOW.indexOf(id('dow').value));
      }
  }


  // do some work to figiure out which days should be disabled

  let today = new Date();
  console.log(today)
  let max = new Date();
  max.setDate(today.getDate() + MAX_DAYS_IN_FUTURE);

  let datepicker = $('#datetimepicker').datetimepicker({
    minDate: today,
    maxDate: max,
    inline: true,
    locale: "en",
    format: "DD.MM.YYYY",
    daysOfWeekDisabled: disabledDays,
  });

  document.querySelector(".picker-switch").onclick = function(e){e.stopPropagation()};

  $('.prev span').removeClass();
  $('.prev span').addClass("fa fa-chevron-left");

  $('.next span').removeClass();
  $('.next span').addClass("fa fa-chevron-right");

  datepicker.on("dp.change", (event) => {
    console.log(event.date._d);

    let selectedDate = new Date(event.date._d)

    id('dow').value = selectedDate.toDateString().substring(0, 3)
    id('date').value = selectedDate.toDateString().substring(4, 15)

    uncheckTimes();

    checkAvailability("false", updateTimePicker, false);
  });
  
  // if date gets offset because of disabled days
  let highlighted = new Date(document.querySelector('.active').dataset.day)
  if (highlighted.getFullYear() != today.getFullYear() || highlighted.getMonth() != today.getMonth() || highlighted.getDate() != today.getDate()) {
      id('dow').value = highlighted.toDateString().substring(0, 3)
      id('date').value = highlighted.toDateString().substring(4, 15)
      uncheckTimes();
      checkAvailability("false", updateTimePicker, false);
  }
  
}

function uncheckTimes() {
  let timeslots = qsa(".timeslot");
  for (let i = 0; i < timeslots.length; i++) {
    let slot = timeslots[i];
    slot.classList.remove("time-selected");
  }
}
