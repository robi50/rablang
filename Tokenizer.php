<?php

namespace Rab;

class Tokenizer{

	private $source = '';

	private $buffer = '';

	private $i = 0;

	private $len = 0;

	private $process = null;

	public function __construct($source){
		$this->source = str_replace(["\n", "\r", "\t"], '', $source);
		$this->len = strlen($this->source);
	}

	public function tokenize(){
		while($this->i < $this->len){
			$c = $this->source[$this->i];

			if($c == ' '){
				$this->i++;
				
				while($this->i < $this->len){
					$c = $this->source[$this->i];

					if($c == ' '){
						$this->i++;
					}else{
						break;
					}
				}
			}elseif($c == ';'){
				$this->i++;

				return [R_SEMIC];
			}elseif($c == ','){
				$this->i++;

				return [R_COMA];
			}elseif($c == '.'){
				$this->i++;

				return [R_DOT];
			}elseif($c == '='){
				$this->i++;

				return [R_EQUAL];
			}elseif($c == '('){
				$this->i++;

				return [R_LBRACKET];
			}elseif($c == ')'){
				$this->i++;

				return [R_RBRACKET];
			}elseif($c == '{'){
				$this->i++;

				return [R_LCBRACKET];
			}elseif($c == '}'){
				$this->i++;

				return [R_RCBRACKET];
			}elseif($c == "'" || $c == '"'){
				$this->i++;

				return $this->buildString($c);
			}elseif(is_numeric($c)){
				$this->i++;
				$this->buffer = $c;

				return $this->buildInteger();
			}elseif(ctype_alpha($c)){
				$this->i++;
				$this->buffer = $c;

				return $this->buildIdentifier();
			}else{
				$this->abort('Unexpected character `%s`.', $c);
			}
		}

		if($this->process) $this->abortForUnfinishedProcess();

		return [R_EOF];
	}

	private function abortForUnfinishedProcess(){
		if($this->process[0] == 'buildString'){
			$this->abort('Unfinished string build process. Expected character `%s`.', $this->process[1]);
		}
	}

	private function abort(){
		$args = func_get_args();
		$args[0] = 'Rab Tokenizer Error: ' . $args[0];

		call_user_func_array('printf', $args);
		exit;
	}

	private function buildString($mark){
		$this->buffer = '';
		$this->process = ['buildString', $mark];

		while($this->i < $this->len){
			$c = $this->source[$this->i];

			if($c == $mark){
				$this->i++;
				$this->process = null;
				break;
			}else{
				$this->i++;
				$this->buffer .= $c;
			}
		}

		return [R_STRING, $this->buffer];
	}

	private function buildInteger(){
		while($this->i < $this->len){
			$c = $this->source[$this->i];

			if(is_numeric($c)){
				$this->i++;
				$this->buffer .= $c;
			}else{
				break;
			}
		}

		return [R_INTEGER, $this->buffer];
	}

	private function buildIdentifier(){
		while($this->i < $this->len){
			$c = $this->source[$this->i];

			if(ctype_alnum($c)){
				$this->i++;
				$this->buffer .= $c;
			}else{
				break;
			}
		}

		if($this->buffer == 'var') return [R_VAR];
		if($this->buffer == 'print') return [R_PRINT];
		if($this->buffer == 'const') return [R_CONST];
		if($this->buffer == 'function') return [R_FUNCTION];
		if($this->buffer == 'enum') return [R_ENUM];
		if($this->buffer == 'return') return [R_RETURN];

		return [R_IDENTIFIER, $this->buffer];
	}

	public function match(){
		$o = $this->i;
		$d = [];

		foreach(func_get_args() as $arg){
			$t = $this->tokenize();

			if(is_array($arg)){
				if(!in_array($t[0], $arg)){
					$this->i = $o;
					return false;
				}
			}else{
				if($arg != $t[0]){
					$this->i = $o;
					return false;
				}
			}

			$d[] = $t;
		}

		return $d;
	}

}