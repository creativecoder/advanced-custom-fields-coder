<?php
/**
 * ACF Field Code
 *
 * @package   advanced-custom-fields-coder
 * @author    Grant Kinney <grant@verismo.io>
 * @license   MIT
 * @link      http://wordpress.org
 * @copyright 2014 Grant Kinney
 */

class ACF_Node_Visitor extends PhpParser\NodeVisitorAbstract {
	
	public $function_name;
	public $key;
	public $value;
	public $replacement_code;

	public function __construct($value, $replacement_code) {
		$this->function_name = 'register_field_group';
		$this->key = 'id';
		$this->value = $value;
		$this->replacement_code = $replacement_code;
	}

	public function leaveNode(PhpParser\Node $node) {
		// looking only for the register_field_group function
		if ( $node instanceof PhpParser\Node\Expr\FuncCall &&
				 $node->name->parts[0] === $this->function_name ) {

			//loop through the argument subnodes of that function
			foreach ( $node->args[0]->value->items as $item ) {
				// return the value of the 'id' array key
				if( $item->key->value === $this->key && $item->value->value === $this->value ) {
					return $this->replacement_code;
				}
			}

		}
	}
}