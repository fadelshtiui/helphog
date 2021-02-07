$(window).on('load', function () {
         	$('#loading-animation').css('display', 'none');
            $('#loading-animation').fadeIn(700);
            $('a').css('display', 'none');
            $('a').fadeIn(700);
            $('h1').css('display', 'none');
            $('h1').delay( 200 ).fadeIn(900);
            $('.toggles').css('display', 'none');
            $('.toggles').delay( 400 ).fadeIn(900);
            $('.posts').css('display', 'none');
            $('.posts').delay( 600 ).fadeIn(900);
});