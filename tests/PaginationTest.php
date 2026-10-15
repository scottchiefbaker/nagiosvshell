<?php

// Tests for vshell::record_limit() pagination

// Two hosts, five services total: h1 has a,b,c and h2 has d,e
function pagination_fixture(): array {
	return [
		'h1' => [
			['service_description' => 'a'],
			['service_description' => 'b'],
			['service_description' => 'c'],
		],
		'h2' => [
			['service_description' => 'd'],
			['service_description' => 'e'],
		],
	];
}

function names_in(array $rows): array {
	$names = [];
	foreach ($rows as $host => $svcs) {
		foreach ($svcs as $name => $_) {
			$names[] = "$host/$name";
		}
	}
	return $names;
}

test('record_limit: first page returns the first N services', function () {
	$v = make_vshell();
	[$rows, $page] = $v->record_limit(pagination_fixture(), 2, 0);

	assert_same(['h1/a', 'h1/b'], names_in($rows));
	assert_same(5, $page['total'], "total count");
	assert_same(2, $page['shown'], "shown count");
	assert_same(3, $page['num_pages'], "5 items at 2 per page");
	assert_same(0, $page['current_page'], "offset 0 is the first page");
});

test('record_limit: middle page returns the next slice across hosts', function () {
	$v = make_vshell();
	[$rows, $page] = $v->record_limit(pagination_fixture(), 2, 2);

	assert_same(['h1/c', 'h2/d'], names_in($rows));
	assert_same(1, $page['current_page']);
	assert_same(0, $page['prev_offset']);
	assert_same(4, $page['next_offset']);
});

test('record_limit: last partial page returns only the remainder', function () {
	$v = make_vshell();
	[$rows, $page] = $v->record_limit(pagination_fixture(), 2, 4);

	assert_same(['h2/e'], names_in($rows));
	assert_same(1, $page['shown']);
	assert_same(2, $page['current_page']);
});

test('record_limit: limit 0 shows nothing and reports a single page', function () {
	// Callers only use the sliced rows when RESULT_LIMIT > 0 (see services.php),
	// so limit 0 means "paging off": the page count is 1 and the total is intact.
	$v = make_vshell();
	[$rows, $page] = $v->record_limit(pagination_fixture(), 0, 0);

	assert_same([], $rows);
	assert_same(1, $page['num_pages']);
	assert_same(5, $page['total']);
});

test('record_limit: page list only includes pages within 4 of the current one', function () {
	// 20 services, 1 per page: 20 pages. At offset 10 (page 10) the window
	// is pages 6..14 inclusive, which is 9 entries.
	$svcs = [];
	for ($i = 0; $i < 20; $i++) {
		$svcs['h'][] = ['service_description' => "s$i"];
	}

	$v = make_vshell();
	[, $page] = $v->record_limit($svcs, 1, 10);

	assert_same(20, $page['num_pages']);
	assert_same(9, count($page['page_list']), "window size");
	assert_same(range(6, 14), array_keys($page['page_list']), "window keys");
	assert_same(6, $page['page_list'][6], "offset for page 6");
});

test('record_limit: empty data gives no rows and no pages', function () {
	$v = make_vshell();
	[$rows, $page] = $v->record_limit([], 2, 0);

	assert_same([], $rows);
	assert_same(0, $page['total']);
	assert_same(0, $page['num_pages']);
});
