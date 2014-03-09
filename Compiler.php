<?php

namespace Rab;

class Compiler{

	private $parser = null;

	private $output = '';

	public function __construct($parser){
		$this->parser = $parser;
	}

	public function compile(){
		$tree = $this->parser->parse();

		foreach($tree as $element){
			$this->output .= $this->translate($element);
		}

		return $this->output;
	}

	private function translate($element){
		$output = '';

		// var foo = "robin";
		if($element['type'] == 'defineVar'){
			foreach($element['variables'] as $n => $v){
				$output .= sprintf('$%s = %s;', $n, $this->tokenToValue($v));
			}
		}
		// const FOO = 2;
		elseif($element['type'] == 'defineConst'){
			foreach($element['variables'] as $n => $v){
				$output .= sprintf('const %s = "%s";', $n, $v);
			}
		}
		// print foo;
		elseif($element['type'] == 'print'){
			$output .= sprintf('echo %s;', implode('.', array_map(function($v){
				return $this->tokenToValue($v);
			}, $element['values'])));
		}
		// enum{F, O, o}
		elseif($element['type'] == 'enum'){
			$i = 0;

			foreach($element['values'] as $v){
				$output .= sprintf('const %s = %d;', $v, $i++);
			}
		}
		// var foo = function(){}
		elseif($element['type'] == 'defineFunction'){
			$output .= sprintf('function %s(%s){%s}', $element['name'], implode(',', array_map(function($v){
				return sprintf('%s', $this->tokenToValue($v));
			}, $element['args'])),implode('', array_map(function($e){
				return $this->translate($e);
			}, $element['inner'])));
		}
		// foo();
		elseif($element['type'] == 'callFunction'){
			$output .= sprintf('%s(%s);', $element['name'], implode(',', array_map(function($v){
				return sprintf('%s', $this->tokenToValue($v));
			}, $element['args'])));
		}
		// return foo;
		elseif($element['type'] == 'return'){
			$output .= sprintf('return %s;', $this->tokenToValue($element['value']));
		}
		// namespace foo{}
		elseif($element['type'] == 'namespace'){
			$output .= sprintf('namespace %s; %s', $element['name'], implode('', array_map(function($e){
				return $this->translate($e);
			}, $element['inner'])));
		}

		return $output;
	}

	/* 
		2 => 2
		foo fo ao => "foo fo ao"
		name => $name
		NAME => NAME
	*/
	private function tokenToValue($t){	
		if($t[0] == R_INTEGER) return $t[1];
		if($t[0] == R_STRING) return '"'. $t[1] .'"';
		if($t[0] == R_IDENTIFIER) return (preg_match('/^[A-Z_]+$/', $t[1])) ? $t[1] : '$'. $t[1];
	}

}