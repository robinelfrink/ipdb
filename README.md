# IPDB

## An IP address management database

### Status

Work in progress:
* Layout improvements.
* Extra table/field configuration dialogs.

In general: Works fine, but has an 'unfinished' feel.

### Requirements

* A web server.
* PHP >= 5.4.
* A database (only mysql has been tested).

### Installation

* Copy the file `config.dist.php` to `config.php`.
* Edit `config.php`.
* Create database.
* Point your browser to `<...>/ipdb/`.

### Notes

* When you delete all networks from the default installation, there is no way
  you can add a new network without adding one manually to the database. The
  reason for this is that I haven't found a nice place on the 'The World' screen
  to place an 'add'-button.
