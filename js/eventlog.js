$(document).ready(function() {
	init_eventlog_filter();
});

function init_eventlog_filter() {
	$("#eventlog_filter").on("input", function() {
		let input  = $(this);
		let text   = input.val();
		let rows   = $("#eventlog_table tbody tr.event-row");
		let groups = $("#eventlog_table tbody tr.event-group");

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

		// Hide an hour heading when none of its entries match
		groups.each(function() {
			let any = $(this).nextUntil("tr.event-group").not(".d-none").length > 0;
			$(this).toggleClass("d-none", !any);
		});

		$("#eventlog_shown").text(shown);
	});
}
