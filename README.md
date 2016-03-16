# DA-Integration-module

## Description

Integration program was created to let users of other atuomotive 
portals send many ads in one time. Dictionaries allow to translate 
'language' of other portals to language of our portal database.

## How it works?

Container is enviroment for pipeline. Firstly Crawler looking for
files in given directory. Given directory is retrieving from dictionaries.
Next step is parsing found files by Parser and send data to dictionaries.
Every dictionary is creating translation of given data. Next step
is to save every translated ads to database. It's Storage job.

Program is launching by crone.

### Used
+ Dependency Injection
+ Fluent Interface
+ Method Chaining
+ PSR2

### Other information

Part of bigger program.  
  
Authors: Marcin Wachulec (mainly Dictionaries), Kamil Cioś (mainly Crawler, Parser, Storage)  
ClickMaster Polska