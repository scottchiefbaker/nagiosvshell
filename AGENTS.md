# AGENTS.md

V-Shell: a PHP 8.2+ web frontend for Nagios Core. No Composer, no package.json, no build step, no linter/formatter config, no CI config.

## Commands

- Run all tests: `php tests/run.php` (add `--simple` to print only failures + summary, `--no-color` for plain output). Exits 1 on any failure.
- There is no per-file or per-test filter. `run.php` loads every `tests/*Test.php`; to focus, temporarily edit the glob or write a throwaway script that requires `tests/bootstrap.php` and the test file.
- Syntax check: `php -l <file>` (no other lint/typecheck tooling exists).
- Tests need only PHP. They do not need Nagios, `/etc/vshell.conf`, or sessions.

## Architecture

- Every top-level `*.php` page is an entrypoint. The pattern is: `require('include/vshell.class.php')`, `$v = new vshell;`, query data, `$v->sluz->assign(...)`, then `print $v->fetch("tpls/<page>.stpl")`.
- Templates in `tpls/*.stpl` use the bundled sluz engine (`include/sluz/`), not Smarty, though the syntax is similar (`{if}`, `{foreach}`, `$var`).
- Per-page JS: `$v->sluz->assign('js_files', ["js/<page>.js"])`. `tpls/skin.stpl` loops over this.
- Adding a page means also adding its nav link in `tpls/top_nav.stpl`.
- Where possible, keep the UI of `host_details.php` and `service_details.php` (and their templates and JS) in parity: same layout, sections, actions, and behavior for equivalent information. If a change lands on one page, check whether the other needs it too.
- Any page supports `?json=1`, which returns the page data via `$v->send_json()`. Keep this in mind when changing data shapes.
- `include/vshell.class.php` holds nearly all logic: Nagios status/objects parsing (with caching, see the `$use_cache` params), permissions (`get_user_perms` reads `cgi.cfg`), log/event helpers, and `tac_data` aggregation.
- `ajax/ajax.php` writes Nagios external commands into `CMDFILE`. It needs the `system_commands` permission and a writable command file.
- Auth comes from Apache basic auth. The constructor errors out unless `$_SERVER['REMOTE_USER']` is set.

## Configuration

- Config is read from `/etc/vshell.conf`, falling back to `config/vshell.conf`. The fallback is gitignored and triggers a warning. Start from `config/vshell-sample.conf`.
- `NAGIOSCFG` is required. The Nagios config file supplies `status_file`, `object_cache_file`, `command_file`, and `log_file`.
- Constants `COREURL`, `CORECGI`, `CORECMD`, `CGICFG`, `STATUSFILE`, `OBJECTSFILE`, and `CMDFILE` are set with `defined() ||` guards, so they are set once per process.

## Testing conventions

- Test files live in `tests/` and must end in `Test.php`. Register tests with `test(name, fn, ?ext)`. The optional `ext` skips the test when that PHP extension is missing.
- `tests/bootstrap.php` provides `make_vshell()`, which builds a `vshell` without running its constructor. Use it only for methods that don't depend on constructor state.
- `vshell::$throw_on_error` is set to true in tests, so `error_out()` throws `vshell_exception` instead of rendering and exiting. Assert errors with `assert_throws`.
- Use `temp_file()` for scratch files. The runner deletes them afterward.

## Repo hygiene

- `include/krumo/`, `js/jquery*`, `js/bootstrap*`, `js/popper*`, `css/bootstrap*`, and `doc/` are vendored or legacy. Don't edit them unless the task is explicitly about them.
- `.ignore` (ripgrep/grep tools) excludes those vendored paths, so search results won't include them.
- Code style: tabs, and each PHP file ends with a `// vim: tabstop=4 shiftwidth=4 noexpandtab ...` modeline. Match it.
- Most recent commit subjects use an uppercase prefix such as `FEATURE:`, `BUGFIX:`, `TESTS:`, or `PERF:` (older ones don't). Follow the recent style.
- `TODO` and `doc/CHANGELOG` are not authoritative docs.
