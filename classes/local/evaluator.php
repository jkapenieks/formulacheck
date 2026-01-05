<?php
namespace assignsubmission_formulacheck\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Evaluates a mathematical formula given a set of variables.
 * It uses the Shunting-yard algorithm (to_rpn) and RPN evaluation (eval_rpn).
 */
class evaluator {
    /**
     * Supported operators: precision, associativity, and arity.
     */
    private static $ops = [
        '+' => ['prec'=>2,'assoc'=>'L','arity'=>2],
        '-' => ['prec'=>2,'assoc'=>'L','arity'=>2],
        '*' => ['prec'=>3,'assoc'=>'L','arity'=>2],
        '/' => ['prec'=>3,'assoc'=>'L','arity'=>2],
        '^' => ['prec'=>4,'assoc'=>'R','arity'=>2],
        'u-' => ['prec'=>5,'assoc'=>'R','arity'=>1] // Unary minus
    ];

    /**
     * Supported functions and their arity (number of arguments).
     */
    private static $funcs = [
        'sqrt'=>1,'abs'=>1,'log'=>1,'log10'=>1,'sin'=>1,'cos'=>1,'tan'=>1,'min'=>2,'max'=>2
    ];

    /**
     * Evaluates the formula using the provided variables.
     *
     * @param string $formula The formula string (e.g., "({p1}+{p2})*sin({p3})").
     * @param array $vars An associative array of variables (e.g., ['p1'=>10.0, 'p2'=>20.0, ...]).
     * @return float|null The result of the evaluation, or null on error (e.g., syntax, division by zero, invalid input).
     */
    public static function evaluate(string $formula, array $vars): ?float {
        // --- START: Dynamic check for required parameters based on formula usage ---
        $usedparams = [];
        
        // 1. Extract all {pX} variables used in the formula. Pattern matches {p1} through {p10}.
        // The pattern now matches p10 or p1 through p9: (p(10|[1-9]))
        if (preg_match_all('/\\{(p(10|[1-9]))\\}/i', $formula, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $m) {
                // $m[1] holds the variable name without braces, e.g., 'p1', 'p10'.
                $usedparams[] = strtolower($m[1]);
            }
        }
        $usedparams = array_unique($usedparams);

        // 2. Check ONLY if parameters REQUIRED by the formula are present in $vars and are numeric.
        foreach ($usedparams as $k) {
            if (!array_key_exists($k, $vars) || !is_numeric($vars[$k])) {
                // A required parameter is missing or non-numeric.
                return null;
            }
        }
        // --- END: Dynamic check ---

        // Replace {pX} placeholders in the formula with just pX (variable token).
        // The pattern p(10|[1-9]) matches p1 through p10.
        $expr = preg_replace('/\\{(p(10|[1-9]))\\}/i', '$1', $formula);
        
        if ($expr === null) { return null; }
        $tokens = self::tokenize($expr);
        if ($tokens === null) { return null; }
        $rpn = self::to_rpn($tokens);
        if ($rpn === null) { return null; }
        $val = self::eval_rpn($rpn, $vars);
        return $val;
    }

    /**
     * Tokenizes the mathematical expression string.
     * @param string $expr The expression string after variable substitution.
     * @return array|null An array of tokens, or null on error.
     */
    private static function tokenize(string $expr): ?array {
        $tokens = [];
        $expr = str_replace([' ', ','], ['', '.'], trim($expr));
        if ($expr === '') { return null; }

        // Variable pattern extended to p1-p10.
        $vars_pattern = '(p(10|[1-9]))';
        
        $funcs_pattern = implode('|', array_keys(self::$funcs));

        // Pattern matches: variables (pX), functions, numbers, or single operators/parentheses.
        // Note: The number pattern \d+\.?\d* allows integers and decimals.
        $pattern = '/('.$vars_pattern.'|'.$funcs_pattern.'|\d+\.?\d*|[-+*\/^()])/i';

        if (preg_match_all($pattern, $expr, $matches, PREG_SET_ORDER) === 0) { return null; }

        $expect_operand = true;
        foreach ($matches as $m) {
            $token = $m[0];

            if (is_numeric($token)) {
                if (!$expect_operand) { return null; } // Operator expected.
                $tokens[] = ['type'=>'num', 'value'=>$token];
                $expect_operand = false;
            } else if (preg_match("/^p(10|[1-9])$/i", $token)) {
                if (!$expect_operand) { return null; } // Operator expected.
                $tokens[] = ['type'=>'var', 'value'=>$token];
                $expect_operand = false;
            } else if (array_key_exists(strtolower($token), self::$funcs)) {
                if (!$expect_operand) { return null; } // Operator expected.
                $tokens[] = ['type'=>'func', 'value'=>strtolower($token)];
                // Function is followed by '(', still expecting operand after it.
            } else if ($token === '(') {
                $tokens[] = ['type'=>'lparen', 'value'=>'('];
                $expect_operand = true;
            } else if ($token === ')') {
                if ($expect_operand) { return null; } // Operand expected before ')'
                $tokens[] = ['type'=>'rparen', 'value'=>')'];
                $expect_operand = false;
            } else if (array_key_exists($token, self::$ops)) {
                if ($token === '-') {
                    // Check for unary minus 'u-'
                    if ($expect_operand) {
                        $tokens[] = ['type'=>'op', 'value'=>'u-'];
                        $expect_operand = true;
                    } else {
                        // Binary minus
                        $tokens[] = ['type'=>'op', 'value'=>'-'];
                        $expect_operand = true;
                    }
                } else if ($token === '+') {
                    // Ignore unary plus, otherwise treat as binary.
                    if ($expect_operand) {
                        $expect_operand = true;
                        continue;
                    } else {
                        // Binary plus
                        $tokens[] = ['type'=>'op', 'value'=>'+'];
                        $expect_operand = true;
                    }
                } else {
                    // Binary operators: *, /, ^
                    if ($expect_operand) { return null; } // Operand expected before binary op.
                    $tokens[] = ['type'=>'op', 'value'=>$token];
                    $expect_operand = true;
                }
            } else {
                // Unrecognized token (e.g., invalid function name or operator)
                return null;
            }
        }
        if ($expect_operand) { return null; }
        return $tokens;
    }

    /**
     * Converts an array of tokens into Reverse Polish Notation (RPN) using the Shunting-yard algorithm.
     * @param array $tokens The tokenized expression.
     * @return array|null The RPN array, or null on error.
     */
    private static function to_rpn(array $tokens): ?array {
        $out = [];
        $opst = [];

        foreach ($tokens as $t) {
            $type = $t['type']; $value = $t['value'];

            if ($type === 'num' || $type === 'var') { $out[] = $t; continue; }
            if ($type === 'func') { $opst[] = $t; continue; }
            if ($type === 'lparen') { $opst[] = $t; continue; }

            if ($type === 'rparen') {
                $found_lparen = false;
                while (!empty($opst)) {
                    $op = array_pop($opst);
                    if ($op['type'] === 'lparen') { $found_lparen = true; break; }
                    $out[] = $op;
                }
                if (!$found_lparen) { return null; } // Mismatched parentheses.
                if (!empty($opst) && $opst[count($opst)-1]['type'] === 'func') {
                    $out[] = array_pop($opst); // Pop function after matching parenthesis.
                }
                continue;
            }

            if ($type === 'op') {
                $op1 = self::$ops[$value];
                while (!empty($opst) && ($op2 = self::$ops[$opst[count($opst)-1]['value']] ?? null)) {
                    if ($op2 && $opst[count($opst)-1]['type'] === 'op') {
                        if (($op1['assoc'] === 'L' && $op1['prec'] <= $op2['prec']) ||
                            ($op1['assoc'] === 'R' && $op1['prec'] < $op2['prec'])) {
                            $out[] = array_pop($opst);
                        } else {
                            break;
                        }
                    } else {
                        break;
                    }
                }
                $opst[] = $t;
                continue;
            }
            return null; // Should not happen.
        }

        while (!empty($opst)) {
            $op = array_pop($opst);
            if ($op['type'] === 'lparen' || $op['type'] === 'rparen') { return null; } // Mismatched parentheses.
            $out[] = $op;
        }

        return $out;
    }

    /**
     * Evaluates the RPN array.
     * @param array $rpn The RPN array.
     * @param array $vars The variable values.
     * @return float|null The result, or null on error.
     */
    private static function eval_rpn(array $rpn, array $vars): ?float {
        $st = [];

        foreach ($rpn as $t) {
            $type = $t['type']; $value = $t['value'];

            if ($type==='num') { $st[]=(float)$value; continue; }
            // Check if variable exists in $vars before casting
            if ($type==='var') { if (!array_key_exists($value,$vars)) return null; $st[]=(float)$vars[$value]; continue; }

            if ($type==='op') {
                $arity=self::$ops[$value]['arity'];
                if (count($st)<$arity) { return null; } // Stack underflow

                if ($arity===1) {
                    // Unary minus
                    $a=array_pop($st);
                    $st[]=-$a;
                } else {
                    // Binary operators
                    $b=array_pop($st);
                    $a=array_pop($st);

                    switch($value){
                        case '+': $st[]=$a+$b; break;
                        case '-': $st[]=$a-$b; break;
                        case '*': $st[]=$a*$b; break;
                        case '/': if ($b==0.0) return null; $st[]=$a/$b; break;
                        case '^': $st[]=pow($a,$b); break;
                        default: return null;
                    }
                }
                continue;
            }

            if ($type==='func') {
                $name=$value;
                if (!array_key_exists($name,self::$funcs)) { return null; } // Should have been caught in tokenize/to_rpn
                $arity=self::$funcs[$name];
                if (count($st)<$arity) { return null; } // Stack underflow

                if ($arity===1) {
                    $a=array_pop($st);
                    switch($name){
                        case 'sqrt': if ($a<0) return null; $st[]=sqrt($a); break;
                        case 'abs': $st[]=abs($a); break;
                        case 'log': if ($a<=0) return null; $st[]=log($a); break;
                        case 'log10': if ($a<=0) return null; $st[]=log10($a); break;
                        case 'sin': $st[]=sin($a); break;
                        case 'cos': $st[]=cos($a); break;
                        case 'tan': $st[]=tan($a); break;
                        default: return null;
                    }
                } else {
                    // Arity 2 functions (min, max)
                    $b=array_pop($st);
                    $a=array_pop($st);
                    switch($name){
                        case 'min': $st[]=min($a,$b); break;
                        case 'max': $st[]=max($a,$b); break;
                        default: return null;
                    }
                }
                continue;
            }
            return null; // Unknown token type.
        }

        if (count($st) !== 1) { return null; } // Result should be the only item left.
        return $st[0];
    }
}
