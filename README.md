# phpdumper - improved PHP variable dumper with web interface
(moved from http://code.google.com/p/phpdumper/)

### What is it?
Simple variables dumper for easy debugging, instead of standard function var_dump(), analog of FirePHP.

### Advantages 
 * Response headers aren't modified, distingueshes of FirePHP
 * Browser-independent
 * All logging advantages: information available at all time and in different environments (stage, production)
 * Posibility of easy debugging of Web-services(REST, JSON, SOAP) in cases you can't do print_r not to break response
 * Convenient web-interface representing information about variables
 * Backlog/trace is implemented
 * Posibility to "stick" debug invokation not to increase number of logging entries
 * Debug panel interface representing both log and target pages
 
### How to use it 
Just download, include in your file and invoke Du::mp($your_variable); 
Dependencies: <a href="http://code.google.com/p/phpquery/">phpQuery *</a>
* - this is 'all-in-one' distribution, all 3rd party libraries are already included, so that you
can use it immediately after including in your project

<img src="http://img89.imageshack.us/img89/5729/phpdumperpanel.png" />


Author: Alexander Kaupanin <kaupanin@gmail.com>