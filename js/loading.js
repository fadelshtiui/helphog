$(window).on('load', function () {
	$('#loading-animation').css('display', 'none');
    $('#loading-animation').delay( 400 ).fadeIn(900);
    $('a').css('display', 'none');
    $('a').fadeIn(900);
    $('#slogan').css('display', 'none');
    $('#slogan').delay( 600 ).fadeIn(900);
    $('.search-container').css('display', 'none');
    $('.search-container').delay( 800 ).fadeIn(900);
    $('#category').css('display', 'none');
    $('#category').delay( 1000 ).fadeIn(900);
    $('.toggles').css('display', 'none');
    $('.toggles').delay( 1000 ).fadeIn(900);
});