window.addEventListener('load', function() {
     document.querySelector('.modal-wrapper').addEventListener('click', function(e) {
          this.classList.add('hidden')
     })
     document.querySelector('.modal').addEventListener('click', function (e) {
          e.stopPropagation();
     })
})