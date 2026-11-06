$(document).ready(function() {
	init_auto_refresh();
});

function init_auto_refresh() {
	var seconds = parseInt($("body").attr("data-refresh-seconds"), 10) || 0;

	if (seconds <= 0) {
		return;
	}

	var remaining = seconds;
	var display   = $("#refresh_countdown");

	display.text("Refresh in " + remaining + "s");

	// Skip the reload while a modal is open so form input is not lost
	setInterval(function() {
		if ($(".modal.show").length) {
			display.text("Refresh paused");
			return;
		}

		remaining--;

		if (remaining <= 0) {
			location.reload();
			return;
		}

		display.text("Refresh in " + remaining + "s");
	}, 1000);
}
