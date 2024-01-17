# Nagios V-Shell 3.x

## Overview

V-Shell is a modern, mobile friendly, alternate web frontend for the Nagios Core
backend. It is written in PHP and HTML5, with special attention being paid to
ensure V-Shell renders well on mobile devices.

**Note:** V-Shell assumes Nagios Core is already installed and functional on the
target system.

## Screenshots
![Screenshot](screenshot.png?raw=true)

## Prerequisites

* Apache
* PHP 7.4+
* Nagios Core

## Installation

Extract the release file or clone this repo to your Nagios HTML directory:

```
git clone https://github.com/scottchiefbaker/nagiosvshell /usr/share/nagios/html/vshell
```

## Configuration

Copy the `config/vshell-sample.conf` to `config/vshell.conf` and edit
appropriately for your system.

If you are using V-Shell in a location *other* than your Nagios HTML directory,
you *may* need to update your Apache configuration. You can use
`config/vshell_apache.conf` as a starting point.

## Getting Started

To get started, log into your Nagios V-Shell interface at
`http://www.mydomain.com/nagios/vshell`, and enter your Nagios Core
credentials.

Nagios V-Shell gets authentication information from the existing Nagios / Apache
access control mechanisms (usually an `htpasswd.users` file), as well as the
`cgi.cfg` file.  Most permissions for Nagios Core should be reflected in
V-Shell.

V-Shell maintains most of the same features of Nagios Core, while simplifying
and modernizing the most commonly used Nagios tasks. Nagios Core system commands,
reports, and interface can all be accessed by direct links from V-Shell.
