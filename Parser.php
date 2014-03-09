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

	private function parseExp($d){
		switch($d[0]){
			case R_VAR:
				if($d = $this->tokenizer->match(R_IDENTIFIER, R_EQUAL, [R_STRING, R_INTEGER, R_IDENTIFIER])){
					$tree = ['type' => 'defineVar', 'variables' => []];
					$tree['variables'][$d[0][1]] = [$d[2]];

					while($j = $this->tokenizer->match(R_DOT, [R_STRING, R_INTEGER, R_IDENTIFIER])){
						$tree['variables'][$d[0][1]][] = $j[1];
					}

					while($this->tokenizer->match(R_COMA)){
						$d = $this->tokenizer->match(R_IDENTIFIER, R_EQUAL, [R_STRING, R_INTEGER, R_IDENTIFIER]);
						$tree['variables'][$d[0][1]] = [$d[2]];

						while($j = $this->tokenizer->match(R_DOT, [R_STRING, R_INTEGER, R_IDENTIFIER])){
							$tree['variables'][$d[0][1]][] = $j[1];
						}
					}

					return $tree;
				}elseif($d = $this->tokenizer->match(R_IDENTIFIER, R_EQUAL, R_FUNCTION, R_LBRACKET)){
					$tree = ['type' => 'defineFunction', 'name' => $d[0][1], 'args' => []];

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
				}
				
				break;

			case R_IDENTIFIER:
				if($this->tokenizer->match(R_LBRACKET)){
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
				$tree = ['type' => 'print', 'values' => []];

				if($d = $this->tokenizer->match([R_IDENTIFIER, R_STRING, R_INTEGER])){
					$tree['values'][] = $d[0];	

					while($d = $this->tokenizer->match(R_DOT, [R_IDENTIFIER, R_STRING])){
						$tree['values'][] = $d[1];	
					}
				}

				return $tree;
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
					$tree = ['type' => 'return', 'value' => $d[0]];

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