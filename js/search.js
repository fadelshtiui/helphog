/* global fetch */

"use strict";

var index = 0;
var suggestion = ["I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "I need my lawn mowed...", "Car needs an oil change...", "Need help with calculus...", "Videographer needed for event...", "What do you need help with today?"];


(function () {

     window.addEventListener('load', function () {

          suggestionLoop();


          let button = id("search-btn")
          button.onclick = search;
          let input = id("search");
          input.focus();

          input.addEventListener("keyup", function (event) {
               event.preventDefault();
               if (event.keyCode === 13) {
                    button.click();
               }
          });

          let subcategories = qsa(".toggles button");
          for (let i = 0; i < subcategories.length; i++) {
               subcategories[i].onclick = searchCategory;
          }
     });

     function searchCategory() {
         let searchTerm = this.innerText;
         let url = "results?category=" + this.innerText.toLowerCase()
          if (getSession() == "" && getZip() != "") { // not logged in and has saved zip cookie
               url += "&zip=" + getZip()
          }
          window.location.href = url;
     }

     function search() {
          let searchBar = id("search");
          let searchTerm = searchBar.value;
          searchTerm = searchTerm.replace("\"", "");
          searchTerm = searchTerm.replace("\'", "");
          if (searchTerm != '') {
               let url = 'results?search=' + searchTerm
               if (getSession() == "" && getZip() != "") { // not logged in and has saved zip cookie
                   url += "&zip=" + getZip()
              }
               window.location = url
          }
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
