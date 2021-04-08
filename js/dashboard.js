(function () {
	$('.dashboard-nav__item').on('click', function (e) {
		var itemId;
		e.preventDefault();
		$('.dashboard-nav__item').removeClass('dashboard-nav__item--selected');
		$(this).addClass('dashboard-nav__item--selected');
		itemId = $(this).children().attr('href');
		$('.dashboard-content__panel').hide();
		$('.dashboard-content__panel[data-panel-id=' + itemId + ']').show();

		if (itemId === 'my_trip') {
			if (qs("#no-order-history").classList.contains("hidden")) {
				$('.dashboard-preview').show();
				qs("#previewer").classList.remove("hidden");
			}
		} else {
			qs("#previewer").classList.add("hidden");
			$('.dashboard-preview').hide();
		}


		return itemId;
	});


	qs("#previewer").classList.add("hidden");
	$('.dashboard-preview').hide();

	$('.dashboard-list__item').on('click', function (e) {
		var itemId;
		// e.preventDefault();
		$('.dashboard-list__item').removeClass('dashboard-list__item--active');
		$(this).addClass('dashboard-list__item--active');
		itemId = $(this).attr('data-item-id');
		$('.dashboard-preview__panel').hide();
		$('.dashboard-preview__panel[data-panel-id=' + itemId + ']').show();
		return itemId;
	});

}).call(this);