# Nagios V-Shell 3.x

Target Audience
---------------

This document is intended for use by Nagios XI and Nagios Core administrators,
and assumes that Nagios Core is already installed and functional on the target
system.


Overview
--------

V-Shell is an updated web interface written in PHP and HTML5. It is styled
and formatted using CSS, to have a modern appearance. Special attention has
been paid to make sure V-Shell renders well on modern mobile devices. To put
it simply: V-Shell is a modern, mobile friendly, frontend for the Nagios Core
backend.

Installation
------------

Prerequisites:
* Apache
* PHP 7.4+
* Nagios Core

To Install V-Shell:
-------------------

Clone this repo to your webroot:

```
git clone https://github.com/scottchiefbaker/nagiosvshell /usr/share/nagios/vshell
```

Verify the values in `config/vshell.conf` are correct. You *may* need to
update your Apache config with `config/vshell_apache.conf` to route web traffic
to your V-Shell installation.

Configuration
--------------

You may need to update the location of `htpasswd.users` file in the Apache
configuration file. For RedHat users this file will be `/etc/nagios/passwd`.
For Ubuntu/Debian users on a `nagios3` install this file will be at
`/etc/nagios3/htpasswd.users`.

Getting Started
---------------

Nagios V-Shell gets authorization information from the existing Nagios / Apache
access control mechanisms (usually an `htpasswd.users` file), as well as the
`cgi.cfg` file for Nagios Core.  Most permissions for Nagios Core should be
reflected in the Nagios V-Shell as well.  To get started, log into your Nagios
webserver at `http://mydomain.com/vshell`, and enter your Nagios Core
authentication information. Nagios V-Shell requires a valid user defined in
the `/path/to/nagios/etc/cgi.cfg` file in order to display information for
hosts and services.

**Note:** V-Shell needs a username from the Apache authentication method in
your Apache configuration.

V-Shell maintains most of the same features of Nagios Core, while utilizing
a top menu bar for site navigation.  This is done to maximize space for table
viewing for hosts and services.

Nagios Core system commands, reports, and the Core interface can all be
accessed by direct links from V-Shell.
