$(window).on('load', function () {
         	$('#loading-animation').css('display', 'none');
            $('#loading-animation').fadeIn(900);
            $('a').css('display', 'none');
            $('a').fadeIn(900);
            $('.form').css('display', 'none');
            $('.form').delay( 400 ).fadeIn(900);
});