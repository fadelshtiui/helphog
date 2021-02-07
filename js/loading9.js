$(window).on('load', function () {
	$('#loading-animation').css('display', 'none');
    $('#loading-animation').fadeIn(1200);
    $('a').css('display', 'none');
    $('a').fadeIn(1200);
	$('#logo').css('display', 'none');
    $('#logo').delay( 400 ).fadeIn(1200);
    $('.et-hero-tabs').css('display', 'none');
    $('.et-hero-tabs').delay( 400 ).fadeIn(900);
    $('.et-main').css('display', 'none');
    $('.et-main').delay( 400 ).fadeIn(900);
    $('.overlay').css('display', 'none');
    $('.overlay').delay( 1800 ).fadeIn(900);
    AOS.init();
    setTimeout(AOS.refresh, 500);
});