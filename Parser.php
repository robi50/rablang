<?php

namespace Rab;

class Parser{

	private $tokenizer = null;

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

	private function parseExp($d){
		switch($d[0]){
			case R_IDENTIFIER:
				// name = "robin";
				if($k = $this->tokenizer->match(R_EQUAL, [R_STRING, R_INTEGER, R_IDENTIFIER])){
					$tree = ['type' => 'defineVar', 'variables' => []];

					if($k[1][0] == R_IDENTIFIER && $this->tokenizer->match(R_LBRACKET)){
						$tree['variables'][$d[1]] = [$this->parseCallFunction($k[1])];
					}else{
						$tree['variables'][$d[1]] = [$k[1]];
					}

					while($j = $this->tokenizer->match(R_DOT, [R_STRING, R_INTEGER, R_IDENTIFIER])){
						if($j[1][0] == R_IDENTIFIER && $this->tokenizer->match(R_LBRACKET)){
							$tree['variables'][$d[1]][] = [$this->parseCallFunction($j[1])];
						}else{
							$tree['variables'][$d[1]][] = $j[1];
						}
					}

					while($this->tokenizer->match(R_COMA)){
						$d = $this->tokenizer->match(R_IDENTIFIER, R_EQUAL, [R_STRING, R_INTEGER, R_IDENTIFIER]);
						$tree['variables'][$d[0][1]] = [$d[2]];

						while($j = $this->tokenizer->match(R_DOT, [R_STRING, R_INTEGER, R_IDENTIFIER])){
							$tree['variables'][$d[0][1]][] = $j[1];
						}
					}

					return $tree;
				}elseif($this->tokenizer->match(R_EQUAL, R_FUNCTION, R_LBRACKET)){
					$tree = ['type' => 'defineFunction', 'name' => $d[1], 'args' => []];

					if($d = $this->tokenizer->match(R_IDENTIFIER)){
						$tree['args'][] = $d[0];

						while($d = $this->tokenizer->match(R_COMA, R_IDENTIFIER)){
							$tree['args'][] = $d[1];
						}
					}

					if($this->tokenizer->match(R_RBRACKET, R_LCBRACKET)){
						$tree['inner'] = $this->parse(R_RCBRACKET);

						return $tree;
					}
				}elseif($this->tokenizer->match(R_LBRACKET)){
					return $this->parseCallFunction($d);
				}
				// name, surname = "robin", "yildiz";
				elseif($j = $this->tokenizer->match(R_COMA, R_IDENTIFIER)){
					$tree = ['type' => 'defineVar', 'variables' => []];
					$names = [$d[1], $j[1][1]];
					$values = [];
					$a = 0;

					while($j = $this->tokenizer->match(R_COMA, R_IDENTIFIER)){
						$names[] = $j[1][1];
					}

					if($j = $this->tokenizer->match(R_EQUAL, [R_STRING, R_INTEGER, R_IDENTIFIER])){
						$values[$a] = [$j[1]];

						while($z = $this->tokenizer->match(R_DOT, [R_STRING, R_INTEGER, R_IDENTIFIER])){
							$values[$a][] = $z[1];
						}

						// if: a, b, c = 3;
						if($this->tokenizer->match(R_SEMIC)){
							for($i = 0; $i < count($names); $i++){
								$tree['variables'][$names[$i]] = $values[0];
							}

							return $tree;
						}
						// else: a, b, c = 1, 2, 3;
						else{
							while($j = $this->tokenizer->match(R_COMA, [R_STRING, R_INTEGER, R_IDENTIFIER])){
								$values[++$a] = [$j[1]];

								while($z = $this->tokenizer->match(R_DOT, [R_STRING, R_INTEGER, R_IDENTIFIER])){
									$values[$a][] = $z[1];
								}
							}

							for($i = 0; $i < count($names); $i++){
								$tree['variables'][$names[$i]] = $values[$i];
							} 

							if($this->tokenizer->match(R_SEMIC)) return $tree;
						}
					}
				}
				break;

			case R_CONST:
				$tree = ['type' => 'defineConst', 'variables' => []];

				if($d = $this->tokenizer->match(R_IDENTIFIER, R_EQUAL, [R_STRING, R_INTEGER])){
					$tree['variables'][strtoupper($d[0][1])] = $d[2][1];

					while($this->tokenizer->match(R_COMA)){
						$d = $this->tokenizer->match(R_IDENTIFIER, R_EQUAL, [R_STRING, R_INTEGER]);
						$tree['variables'][strtoupper($d[0][1])] = $d[2][1];
					}
				}
				
				return $tree;
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
				if($d = $this->tokenizer->match([R_INTEGER, R_STRING, R_IDENTIFIER])){
					$tree = ['type' => 'return', 'value' => []];

					if($d[0][0] == R_IDENTIFIER && $this->tokenizer->match(R_LBRACKET)){
						$tree['value'][] = [$this->parseCallFunction($d[0])];
					}else{
						$tree['value'][] = $d[0];
					}

					while($j = $this->tokenizer->match(R_DOT, [R_STRING, R_INTEGER, R_IDENTIFIER])){
						if($j[1][0] == R_IDENTIFIER && $this->tokenizer->match(R_LBRACKET)){
							$tree['value'][] = [$this->parseCallFunction($j[1])];
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

		}
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