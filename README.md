ko-process
==========

[![Build Status](https://travis-ci.org/misterion/ko-process.png?branch=master)](https://travis-ci.org/misterion/ko-process)

Ko-Process allows for easy callable forking. It is object-oriented wrapper arount fork part of
[`PCNTL`](http://php.net/manual/ru/book.pcntl.php) PHP's extension. Background process, detaching process from the
controlling terminal, signals and exit codes and simple IPC.

Requirements
------------

    PHP >= 5.4
    pcntl extension installed


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

# Usage #

Basic usage looks like this:

```php
    $manager = new Ko\ProcessManager();
    $process = $manager->fork(function(Ko\Process $p) {
        echo 'Hello from ' . $p->getPid();
    })->onSuccess(function() {
        echo 'Success finish!';
    })->wait();
```

If should wait for all forked process
```php
    $manager = new Ko\ProcessManager();
    for ($i = 0; $i < 10; $i++) {
        $manager->fork(function(Ko\Process $p) {
            echo 'Hello from ' . $p->getPid();
            sleep(1);
        });
    }
    $manager->wait();
```
