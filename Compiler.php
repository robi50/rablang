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

		// foo = "robin";
		if($element['type'] == 'defineVar'){
			foreach($element['variables'] as $n => $v){
				$output .= sprintf('$%s = %s;', $n, implode('.', array_map(function($vv){
					return $this->tokenToValue($vv);
				}, $v)));
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
		// foo = function(){}
		elseif($element['type'] == 'defineFunction'){
			$output .= sprintf('function %s(%s){%s}', $element['name'], implode(',', array_map(function($v){
				return sprintf('%s', $this->tokenToValue($v));
			}, $element['args'])),implode('', array_map(function($e){
				return $this->translate($e);
			}, $element['inner'])));
		}
		// foo();
		elseif($element['type'] == 'callFunction'){
			$output .= $this->compileCallFunction($element['name'], $element['args']) . ';';
		}
		// return foo;
		elseif($element['type'] == 'return'){
			$output .= sprintf('return %s;', implode('.', array_map(function($v){
					return $this->tokenToValue($v);
			}, $element['value'])));
		}
		// namespace foo{}
		elseif($element['type'] == 'namespace'){
			$output .= sprintf('namespace %s; %s', $element['name'], implode('', array_map(function($e){
				return $this->translate($e);
			}, $element['inner'])));
		}
		// import "foo";
		elseif($element['type'] == 'import'){
			$output .= sprintf('require "%s.php";', implode('/', explode('.', $element['path'])));
		}
		// from "fo.foo.fooo" import "foo";
		elseif($element['type'] == 'importFrom'){
			foreach($element['files'] as $f){
				$output .= sprintf('require "%s.php";', implode('/', explode('.', $element['path'])).'/'.$f);
			}
		}

		return $output;
	}

	private function compileCallFunction($name, $args){
		return sprintf('%s(%s)', $name, implode(',', array_map(function($v){
				return sprintf('%s', $this->tokenToValue($v));
		}, $args)));
	}

	/* 
		2 => 2
		foo fo ao => "foo fo ao"
		name => $name
		NAME => NAME
	*/
	private function tokenToValue($t){
		if(isset($t['type']) && $t['type'] == 'callFunction') return $this->compileCallFunction($t['name'], $t['args']);	
		if($t[0] == R_INTEGER) return $t[1];
		if($t[0] == R_STRING) return '"'. $t[1] .'"';
		if($t[0] == R_IDENTIFIER) return (preg_match('/^[A-Z_]+$/', $t[1])) ? $t[1] : '$'. $t[1];
		if($t[0] == R_BOOLEAN) return $t[1];
	}

}