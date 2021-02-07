$(window).on('load', function () {
         	$('#loading-animation').css('display', 'none');
            $('#loading-animation').fadeIn(900);
            $('a').css('display', 'none');
            $('a').fadeIn(900);
            $('#container2').css('display', 'none');
            $('#container2').delay( 100 ).fadeIn(900);
            $('button').css('display', 'none');
            $('button').delay( 200 ).fadeIn(900);
});