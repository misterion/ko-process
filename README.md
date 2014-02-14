ko-process
==========

[![Build Status](https://travis-ci.org/misterion/ko-process.png?branch=master)](https://travis-ci.org/misterion/ko-process)
[![Latest Stable Version](https://poser.pugx.org/misterion/ko-process/v/stable.png)](https://packagist.org/packages/misterion/ko-process)
[![License](https://poser.pugx.org/misterion/ko-process/license.png)](https://packagist.org/packages/misterion/ko-process)

Ko-Process allows for easy callable forking. It is object-oriented wrapper arount fork part of
[`PCNTL`](http://php.net/manual/ru/book.pcntl.php) PHP's extension. Background process, detaching process from the
controlling terminal, signals and exit codes and simple IPC.

Requirements
------------

    PHP >= 5.4
    pcntl extension installed
    posix extension installed


Installation
------------

The recommended way to install library is [composer](http://getcomposer.org).
You can see [package information on Packagist](https://packagist.org/packages/misterion/ko-process).

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

The `Ko\SharedMemory` used `Semaphore` for internal locks so can be safely used for inter process communications.
SharedMemory implements `\ArrayAccess` and `\Countable` interface so accessible like an array:

```php
$sm = new SharedMemory(5000); //allocate 5000 bytes
$sm['key1'] = 'value';

echo 'Total keys is' . count($sm) . PHP_EOL;
echo 'The key with name `key1` exists: ' . isset($sm['key1'] . PHP_EOL;
echo 'The value of key1 is ' . $sm['key1'] . PHP_EOL;

unset($sm['key1']);
echo 'The key with name `key1` after unset exists: ' . isset($sm['key1'] . PHP_EOL;
```

You can use `Semaphore` for inter process locking:
```php

$s = new Semaphore();
$s->acquire();
//do some job
$s->release();

//or
$s->tryExecute(function() {
    //do some job
});
```
