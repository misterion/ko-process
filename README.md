ko-process
==========

Ko-Process allows for easy callable forking. It is object-oriented wrapper arount fork part of
[`PCNTL`](http://php.net/manual/ru/book.pcntl.php) PHP's extension. Background process, detaching process from the
controlling terminal, signals and exit codes and simple IPC.

[![Build Status](https://travis-ci.org/misterion/ko-process.png?branch=master)]

Installation
------------

The recommended way to install library is [composer](http://getcomposer.org).
You can see [package information on Packagist][ComposerPackage].

```JSON
{
	"require": {
		"misterion/ko-process": "*"
	}
}
```