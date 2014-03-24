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
	public $new_code;
	public $delete;
	private $new_node_set = false;

	public function __construct($field_key = '', $new_code = array(), $delete = false ) {
		$this->function_name = 'register_field_group';
		$this->key = 'id';
		$this->value = $field_key;
		$this->new_code = $new_code;
		$this->delete = $delete;
	}

	public function leaveNode(PhpParser\Node $node) {
		if ( $node instanceof PhpParser\Node\Expr\FuncCall ) {
			// looking only for the register_field_group function
			if ( $node->name->parts[0] === $this->function_name ) {
				//loop through the argument subnodes of that function
				foreach ( $node->args[0]->value->items as $item ) {
					// return the value of the 'id' array key
					if( $item->key->value === $this->key && $item->value->value === $this->value ) {
						// Delete the field, if delete is set to true
						if ( $this->delete ) return false;
						// Otherwise, update the code with the new settings
						return $this->new_code;
					} // endif
				} // endforeach
				// if this is a new field, return an array to add the `register_field_group` function for the new field
				if ( 'new' === $this->value && false === $this->new_node_set ) {
					$this->new_node_set = true;
					// Return within an array, with the existing node, to add the new field group
					return array( $node, $this->new_code[0] );
				} //endif
			} // endif
		}
	}
}