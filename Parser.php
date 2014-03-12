<?php

namespace Rab;

class Parser{

	private $tokenizer = null;

	private $variablePrefixs = [];

	public function __construct($tokenizer){
		$this->tokenizer = $tokenizer;
	}

	public function parse($ender = false){
		$tree = [];

		while(true){
			$d = $this->getToken();
			if($d[0] == R_EOF) break;
			if($ender !== false) if($d[0] == $ender) break;
			$tree[] = $this->parseExp($d);
		}

		return $tree;
	}

	// foo = {value} | const foo = {value}
	private function parseValue($conf){
		if($d = $this->tokenizer->match([R_STRING, R_INTEGER, R_BOOLEAN])){
			return ['type' => 'native', 'value' => $d[0]];
		}elseif($d = $this->tokenizer->match(R_IDENTIFIER, R_LBRACKET)){
			return $this->parseCallFunction($d[0]);
		}elseif($d = $this->tokenizer->match(R_IDENTIFIER)){
			return ['type' => 'native', 'value' => $d[0]];
		}
	}

	private function parseExp($d){
		switch($d[0]){

			case R_CONST:
			case R_VISIBILITY:
			case R_IDENTIFIER:
				// visibility init
				$v = null;

				if($d[0] == R_VISIBILITY){
					if($j = $this->tokenizer->match(R_IDENTIFIER)){
						$this->variablePrefixs[] = $d[1];
						$v = $d[1];
						$d = $j[0];
					}elseif($j = $this->tokenizer->match(R_CONST, R_IDENTIFIER)){
						$this->variablePrefixs[] = $d[1];
						$this->variablePrefixs[] = 'const';
						$d = $j[1];	
					}else{
						$this->abort('Expected: `R_IDENTIFIER`.');
					}
				}elseif($d[0] == R_CONST){
					$this->variablePrefixs[] = 'const';
				}

				if($this->tokenizer->match(R_EQUAL, R_FUNCTION, R_LBRACKET)){
					$tree = ['type' => 'defineFunction', 'name' => $d[1], 'args' => [], 'visibility' => $v];
					$i = 0;

					if($d = $this->tokenizer->match(R_IDENTIFIER)){
						$tree['args'][$i] = [$d[0]];

						if($j = $this->tokenizer->match(R_EQUAL)){
							$tree['args'][$i][] = $this->parseValue(['name' => $d[0][1]]);
						}

						$i++;

						while($d = $this->tokenizer->match(R_COMA, R_IDENTIFIER)){
							$tree['args'][$i] = [$d[1]];

							if($j = $this->tokenizer->match(R_EQUAL)){
								$tree['args'][$i++][] = $this->parseValue(['name' => $d[1][1]]);
							}
						}
					}

					if($this->tokenizer->match(R_RBRACKET, R_LCBRACKET)){
						$tree['inner'] = $this->parse(R_RCBRACKET);

						return $tree;
					}	
				}
				// name = "robin";
				elseif($k = $this->tokenizer->match(R_EQUAL)){
					$tree = ['type' => 'defineVar', 'variables' => [], 'prefixs' => $this->variablePrefixs];

					$tree['variables'][$d[1]] = [$this->parseValue(['name' => $d[1], 'prefixs' => $this->variablePrefixs])];

					while($j = $this->tokenizer->match(R_DOT)){
						$tree['variables'][$d[1]][] = $this->parseValue(['name' => $d[1], 'prefixs' => $this->variablePrefixs]);
					}

					while($this->tokenizer->match(R_COMA)){
						$d = $this->tokenizer->match(R_IDENTIFIER, R_EQUAL);
						$tree['variables'][$d[0][1]] = $this->parseValue(['name' => $d[0][1], 'prefixs' => $this->variablePrefixs]);

						while($j = $this->tokenizer->match(R_DOT)){
							$tree['variables'][$d[0][1]][] = $this->parseValue(['name' => $d[0][1], 'prefixs' => $this->variablePrefixs]);
						}
					}

					// convert variables names to uppercase if const
					if(in_array('const', $this->variablePrefixs)){
						foreach($tree['variables'] as $n => $v){
							unset($tree['variables'][$n]);
							$tree['variables'][strtoupper($n)] = $v;
						}
					}

					// flush variables prefixs
					$this->variablePrefixs = [];

					return $tree;
				}elseif($this->tokenizer->match(R_LBRACKET)){
					return $this->parseCallFunction($d);
				}
				// name, surname = "robin", "yildiz";
				elseif($j = $this->tokenizer->match(R_COMA, R_IDENTIFIER)){
					$tree = ['type' => 'defineVar', 'variables' => [], 'prefixs' => $this->variablePrefixs];
					$names = [$d[1], $j[1][1]];
					$values = [];
					$a = 0;

					while($j = $this->tokenizer->match(R_COMA, R_IDENTIFIER)){
						$names[] = $j[1][1];
					}

					if($j = $this->tokenizer->match(R_EQUAL)){
						$values[$a] = [$this->parseValue(['name' => $names[$a], 'prefixs' => $this->variablePrefixs])];

						while($z = $this->tokenizer->match(R_DOT)){
							$values[$a][] = $this->parseValue(['name' => $names[$a], 'prefixs' => $this->variablePrefixs]);
						}

						// if: a, b, c = 1, 2, 3;
						if($this->tokenizer->match(R_COMA)){
														while($j = $this->tokenizer->match(R_COMA)){
								$values[++$a] = [$this->parseValue(['name' => $names[$a], 'prefixs' => $this->variablePrefixs])];

								while($z = $this->tokenizer->match(R_DOT)){
									$values[$a][] = $this->parseValue(['name' => $names[$a], 'prefixs' => $this->variablePrefixs]);
								}
							}

							for($i = 0; $i < count($names); $i++){
								$tree['variables'][$names[$i]] = $values[$i];
							} 

							// convert variables names to uppercase if const
							if(in_array('const', $this->variablePrefixs)){
								foreach($tree['variables'] as $n => $v){
									unset($tree['variables'][$n]);
									$tree['variables'][strtoupper($n)] = $v;
								}
							}

							if($this->tokenizer->match(R_SEMIC)) return $tree;
						}
						// else: a, b, c = 3;
						else{
							for($i = 0; $i < count($names); $i++){
								$tree['variables'][$names[$i]] = $values[0];
							}

							// convert variables names to uppercase if const
							if(in_array('const', $this->variablePrefixs)){
								foreach($tree['variables'] as $n => $v){
									unset($tree['variables'][$n]);
									$tree['variables'][strtoupper($n)] = $v;
								}
							}

							return $tree;
						}
					}

					$this->variablePrefixs = [];
				}
				break;

			case R_PRINT:
				if($d = $this->tokenizer->match([R_IDENTIFIER, R_STRING, R_INTEGER])){
					$tree = ['type' => 'print', 'values' => []];

					if($d[0][0] == R_IDENTIFIER && $this->tokenizer->match(R_LBRACKET)){
						$tree['values'][] = $this->parseCallFunction($d[0]);
						$this->tokenizer->match(R_RBRACKET);
					}else{
						$tree['values'][] = $d[0];
					}

					while($d = $this->tokenizer->match(R_DOT, [R_IDENTIFIER, R_STRING])){
						if($d[1][0] == R_IDENTIFIER && $this->tokenizer->match(R_LBRACKET)){
							$tree['values'][] = $this->parseCallFunction($d[1]);
						}else{
							$tree['values'][] = $d[1];
						}

					}

					return $tree;
				}
				break;

			case R_ENUM:
				if($d = $this->tokenizer->match(R_LCBRACKET, R_IDENTIFIER)){
					$tree = ['type' => 'enum', 'values' => []];
					$tree['values'][] = strtoupper($d[1][1]);

					while($d = $this->tokenizer->match(R_COMA, R_IDENTIFIER)){
						$tree['values'][] = strtoupper($d[1][1]);
					}

					if($this->tokenizer->match(R_RCBRACKET)) return $tree;
				}
				break;

			case R_RETURN:
				if($d = $this->tokenizer->match([R_INTEGER, R_STRING, R_IDENTIFIER, R_BOOLEAN])){
					$tree = ['type' => 'return', 'value' => []];

					if($d[0][0] == R_IDENTIFIER && $this->tokenizer->match(R_LBRACKET)){
						$tree['value'][] = $this->parseCallFunction($d[0]);
					}else{
						$tree['value'][] = $d[0];
					}

					while($j = $this->tokenizer->match(R_DOT, [R_STRING, R_INTEGER, R_IDENTIFIER, R_BOOLEAN])){
						if($j[1][0] == R_IDENTIFIER && $this->tokenizer->match(R_LBRACKET)){
							$tree['value'][] = $this->parseCallFunction($j[1]);
						}else{
							$tree['value'][] = $j[1];
						}
					}

					return $tree;
				}

				break;

			case R_NAMESPACE:
				if($d = $this->tokenizer->match(R_IDENTIFIER, R_LCBRACKET)){
					$tree = ['type' => 'namespace', 'name' => $d[0][1]];
					$tree['inner'] = $this->parse(R_RCBRACKET);

					return $tree;
				}	

				break;

			case R_IMPORT:
				if($d = $this->tokenizer->match(R_STRING)){
					$tree = ['type' => 'import', 'path' => $d[0][1]];

					return $tree;
				}

				break;

			case R_FROM:
				if($d = $this->tokenizer->match(R_STRING, R_IMPORT, R_STRING)){
					$tree = ['type' => 'importFrom', 'path' => $d[0][1], 'files'];
					$tree['files'][] = $d[2][1];

					while($d = $this->tokenizer->match(R_COMA, R_STRING)){
						$tree['files'][] = $d[1][1];
					}

					return $tree;
				}
				break;

			case R_LOOP:
				if($d = $this->tokenizer->match(R_LBRACKET, R_INTEGER, R_RBRACKET, R_LCBRACKET)){
					$tree = ['type' => 'loop', 'limit' => $d[1][1]];
					$tree['inner'] = $this->parse(R_RCBRACKET);

					return $tree;
				}
				break;

			case R_CLASS:
				if($d = $this->tokenizer->match(R_IDENTIFIER, R_LCBRACKET)){
					$tree = ['type' => 'defineClass', 'name' => $d[0][1]];
					$tree['inner'] = $this->parse(R_RCBRACKET);

					return $tree;
				}

				break;

		}
	}

	private function parseCallFunction($d){
		$tree = ['type' => 'callFunction', 'name' => $d[1], 'args' => []];

		if($d = $this->tokenizer->match([R_STRING, R_INTEGER, R_IDENTIFIER])){
			$tree['args'][] = $d[0];

			while($d = $this->tokenizer->match(R_COMA, [R_STRING, R_INTEGER, R_IDENTIFIER])){
				$tree['args'][] = $d[1];
			}
		}elseif($this->tokenizer->match(R_RBRACKET)){
			return $tree;
		}

		return $tree;
	}

	private function getToken(){
		return $this->tokenizer->tokenize();
	}

	private function abort(){
		$args = func_get_args();
		$args[0] = 'Rab Parser Error: ' . $args[0];

		call_user_func_array('printf', $args);
		exit;
	}

}