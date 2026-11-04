$(document).ready(function() {
	init_notification_filter();
});

function init_notification_filter() {
	$("#notification_filter").on("input", function() {
		let input = $(this);
		let text  = input.val();
		let rows  = $("#notification_table tbody tr");

		let regex;
		try {
			regex = new RegExp(text, "i");
			input.removeClass("is-invalid");
		} catch (e) {
			input.addClass("is-invalid");
			return;
		}

		let shown = 0;
		rows.each(function() {
			let match = !text || regex.test($(this).text());
			$(this).toggleClass("d-none", !match);
			if (match) {
				shown++;
			}
		});

		$("#notification_shown").text(shown);
	});
}
