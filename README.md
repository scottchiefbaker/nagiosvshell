# Nagios V-Shell 3.x

Target Audience
---------------

This document is intended for use by Nagios XI and Nagios Core administrators,
and assumes that Nagios Core is already installed and functional on the target
system.


Overview
--------

V-Shell is an alternate web interface written in PHP and HTML5. It is styled
using CSS, and special attention has been paid to make sure V-Shell renders
well on mobile devices.

To put it simply: V-Shell is a modern, mobile friendly, alternate frontend
for the Nagios Core backend.

Installation
------------

Prerequisites:
* Apache
* PHP 7.4+
* Nagios Core

To Install V-Shell:
-------------------

Extract the release file or clone this repo to your Nagios HTML directory:

```
git clone https://github.com/scottchiefbaker/nagiosvshell /usr/share/nagios/html/vshell
```

Configuration
-------------

Copy the `config/vshell-sample.conf` to `config/vshell.conf` and edit
appropriately for your system.

If you are using V-Shell in a location *other* than your Nagios HTML directory
you *may* need to update your Apache configuration. You can use
`config/vshell_apache.conf` to route web traffic to your V-Shell installation.

Getting Started
---------------

Nagios V-Shell gets authentication information from the existing Nagios / Apache
access control mechanisms (usually an `htpasswd.users` file), as well as the
`cgi.cfg` file for Nagios Core.  Most permissions for Nagios Core should be
reflected in V-Shell as well.

To get started, log into your Nagios V-Shell interface at
`http://www.mydomain.com/nagios/vshell`, and enter your Nagios Core
authentication information.

V-Shell maintains most of the same features of Nagios Core, while simplying
and modernizing the most commonly used Nagios tasks. Nagios Core system commands,
reports, and interface can all be accessed by direct links from V-Shell.
