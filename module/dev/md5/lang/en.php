<?php
$lang->md5 = new stdClass();
$lang->md5->help = new stdClass();
$lang->md5->help->md5 = <<<EOF
Calculates the md5 hash of the a string or given file.

Usage
  z md5 <command> [options]

Commands
  calculate  -c  <string | filepath>     Calculates the md5 hash of the a string or given file..
EOF;
$lang->md5->help->calculate = <<<EOF
Usage
  z md5 calculate [options]

Options
  -b, --binary   If use the optional, then the md5 digest is instead returned in raw binary format with a length of 16.

Example
  z md5 calculate -b
EOF;
