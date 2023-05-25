# Nagios VShell 1.x

This document describes how to install and setup the Nagios V-Shell or **Visual**
Shell for Nagios Core and Nagios XI installations.


Target Audience
---------------

This document is intended for use by Nagios XI and Nagios Core Administrators,
and assumes that Nagios Core is installed on the target system.


Overview
--------

The Nagios V-Shell is an updated web interface written in PHP that is 
designed to render as valid HTML5 and be fully styled and formatted using CSS
classes, while maintaining the power of Nagios Core for issuing system
and node commands. Special attention has been paid to make sure VShell renders
well on modern mobile devices.

Installation
------------

Prerequisites:
* Apache
* PHP 5.6+
* Nagios Core

To Install V-Shell:
-------------------

Clone this repo to your webroot:

```
git clone https://github.com/scottchiefbaker/nagiosvshell /usr/share/nagios/vshell
```

Next, verify the values in `config/vshell.conf` are correct. You *may* need to
update your Apache config with `config/vshell_apache.conf` to route web traffic
appropriately.

Configuration
--------------

You may need to update the location of `htpasswd.users` file in the apache
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
hosts and services.  NOTE: V-Shell needs a username from either an apache
authentication method or the name need to be hard-coded into the `index.php`
file.  See the code comments for the hard-coded option.

V-Shell maintains most of the same features of Nagios Core, while utilizing
a top menu bar for site navigation.  This is done to maximize space for table
viewing for hosts and services.

Nagios Core system commands, reports, and the Core interface can all be
accessed by direct links from V-Shell.
