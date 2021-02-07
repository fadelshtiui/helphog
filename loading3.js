$(window).on('load', function () {
         	$('#loading-animation').css('display', 'none');
            $('#loading-animation').fadeIn(900);
            $('#logo').css('display', 'none');
            $('#logo').fadeIn(900);
            $('main').css('display', 'none');
            $('main').delay( 400 ).fadeIn(900);
});