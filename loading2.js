$(window).on('load', function () {
	$('#loading-animation').css('display', 'none');
    $('#loading-animation').fadeIn(900);
    $('a').css('display', 'none');
    $('a').fadeIn(900);
	$('#center').css('display', 'none');
    $('#center').delay( 400 ).fadeIn(900);
});