$(window).on('load', function () {
         	$('#loading-animation').css('display', 'none');
            $('#loading-animation').fadeIn(700);
            $('a').css('display', 'none');
            $('a').fadeIn(700);
            $('#intro').css('display', 'none');
            $('#intro').delay( 200 ).fadeIn(900);
            $('#search-results').css('display', 'none');
            $('#search-results').delay( 400 ).fadeIn(900);
});