$(document).ready(function() {
	init_auto_refresh();
});

function init_auto_refresh() {
	var seconds = parseInt($("body").attr("data-refresh-seconds"), 10) || 0;

	if (seconds <= 0) {
		return;
	}

	// Skip the reload while a modal is open so form input is not lost
	setInterval(function() {
		if ($(".modal.show").length) {
			return;
		}

		location.reload();
	}, seconds * 1000);
}
