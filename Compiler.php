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

		echo $this->output;

		return $this->output;
	}

	private function translate($element){
		$output = '';

		// foo = "robin";
		if($element['type'] == 'defineVar'){
			foreach($element['variables'] as $n => $v){
				$output .= sprintf('%s%s%s = %s;', implode(' ', $element['prefixs']).' ', in_array('const', $element['prefixs']) ? '' : '$',$n, implode('.', array_map(function($vv){
					return $this->tokenToValue($vv);
				}, $v)));
			}
		}
		// const FOO = 2;
		elseif($element['type'] == 'defineConst'){
			foreach($element['variables'] as $n => $v){
				$output .= sprintf('const %s = %s;', $n, $this->tokenToValue($v));
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
			$output .= sprintf('%sfunction %s(%s){%s}', $element['visibility'] ? $element['visibility'].' ' : '', $element['name'], implode(',', array_map(function($v){
				$arg = sprintf('%s', $this->tokenToValue($v[0]));

				if(isset($v[1])) $arg .= '=' . $this->tokenToValue($v[1]);

				return $arg;
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
		// loop(5){print "foo";}
		elseif($element['type'] == 'loop'){
			$output .= sprintf('for($i=0;$i<%s;$i++){%s}', $element['limit'], implode('', array_map(function($e){
				return $this->translate($e);
			}, $element['inner'])));
		}
		// class Foo{}
		elseif($element['type'] == 'defineClass'){
			$output .= sprintf('class %s{%s}', $element['name'], implode('', array_map(function($e){
				return $this->translate($e);
			}, $element['inner'])));
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
	private function tokenToValue($e){
		if(isset($e['type'])){
			if($e['type'] == 'native'){
				$v = $e['value'];

				if($v[0] == R_INTEGER) return $v[1];
				if($v[0] == R_STRING) return '"'. $v[1] .'"';
				if($v[0] == R_IDENTIFIER) return (preg_match('/^[A-Z_]+$/', $v[1])) ? $v[1] : '$'. $v[1];
				if($v[0] == R_BOOLEAN) return $v[1];
			}elseif($e['type'] == 'callFunction'){
				return $this->compileCallFunction($e['name'], $e['args']);	
			}elseif($e['type'] == 'defineFunction'){
				return $this->translate($e);
			}
		}else{
			if($e[0] == R_INTEGER) return $e[1];
			if($e[0] == R_STRING) return '"'. $e[1] .'"';
			if($e[0] == R_IDENTIFIER) return (preg_match('/^[A-Z_]+$/', $e[1])) ? $e[1] : '$'. $e[1];
			if($e[0] == R_BOOLEAN) return $e[1];
		}
	}

}