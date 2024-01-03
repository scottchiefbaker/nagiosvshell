# Nagios V-Shell 3.x

## Overview

V-Shell is a modern, mobile friendly, alternate web frontend for the Nagios Core
backend. It is written in PHP and HTML5, with special attention being paid to
ensure V-Shell renders well on mobile devices.

**Note:** V-Shell assumes Nagios Core is already installed and functional on the
target system.

## Screenshots
![Screenshot](https://private-user-images.githubusercontent.com/3429760/294054669-7e639f0e-ac17-4b13-940c-ca321e63b14e.png?jwt=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJnaXRodWIuY29tIiwiYXVkIjoicmF3LmdpdGh1YnVzZXJjb250ZW50LmNvbSIsImtleSI6ImtleTUiLCJleHAiOjE3MDQzMjYzMTYsIm5iZiI6MTcwNDMyNjAxNiwicGF0aCI6Ii8zNDI5NzYwLzI5NDA1NDY2OS03ZTYzOWYwZS1hYzE3LTRiMTMtOTQwYy1jYTMyMWU2M2IxNGUucG5nP1gtQW16LUFsZ29yaXRobT1BV1M0LUhNQUMtU0hBMjU2JlgtQW16LUNyZWRlbnRpYWw9QUtJQVZDT0RZTFNBNTNQUUs0WkElMkYyMDI0MDEwMyUyRnVzLWVhc3QtMSUyRnMzJTJGYXdzNF9yZXF1ZXN0JlgtQW16LURhdGU9MjAyNDAxMDNUMjM1MzM2WiZYLUFtei1FeHBpcmVzPTMwMCZYLUFtei1TaWduYXR1cmU9Zjg2ZmIwZGEyM2JmMWNlZGNhOTE5Y2U5ODlmMjc2ZmY2NWZlOTFiNTNjMThmMzE1NGIzOTEzMmFlZWUyZWNiNyZYLUFtei1TaWduZWRIZWFkZXJzPWhvc3QmYWN0b3JfaWQ9MCZrZXlfaWQ9MCZyZXBvX2lkPTAifQ.mrNpCgGLV86vxMpVw3o6ynanh4gxuMexc8L2cjsi4sk)

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
