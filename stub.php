#!/usr/bin/env php
<?php
Phar::mapPhar('codemod.phar');
require 'phar://codemod.phar/bin/codemod';
__HALT_COMPILER(); 