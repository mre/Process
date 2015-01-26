Process
=======


A lightweight wrapper around PHP's subprocess handling.  
Especially useful for interactive commands.


Example
-------

```PHP
require_once __DIR__.'/../vendor/autoload.php';

use \mre\Process;

$process = new Process('cat');
$process->send('hello');
$process->send('world');

foreach ($process->receive() as $s)
{
    echo $s . PHP_EOL;
}
```


Alternatives
------------

Fabien Potencier's `Process` library is awesome for non-interactive processes,  
although it has grown quite big. 


Maintainers
-----------

This project was initially started by Christian Lück.
It has been deprecated some time ago but I thought it's useful  
and picked up development again.