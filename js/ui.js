window.addEventListener('load', function() {
     qs('.modal-wrapper').addEventListener('click', function(e) {
          this.classList.add('hidden')
     })
     qs('.modal').addEventListener('click', function (e) {
          e.stopPropagation();
     })
})