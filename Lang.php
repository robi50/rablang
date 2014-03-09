<?php

namespace Rab;

require 'tokens.php';
require 'Tokenizer.php';
require 'Parser.php';
require 'Compiler.php';

class Lang{

	public static function compile($source){
		$compiler = new Compiler(new Parser(new Tokenizer(is_file($source) ? file_get_contents($source) : $source)));
		eval($compiler->compile());
	}

}
