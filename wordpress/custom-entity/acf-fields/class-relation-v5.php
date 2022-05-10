<?php

namespace Vnet\Entity;

use Vnet\Entity_Category;
use Vnet\Entity_News;

if (!class_exists('acf_field')) {
	return;
}

class Press_acf_field_Relation extends \acf_field
{

	var $settings;
	var $defaults;


	function __construct()
	{
		$this->name = 'relation';
		$this->label = 'Соотношение';
		$this->category = 'Прессбол';
		$this->defaults = [];

		parent::__construct();
	}


	function render_field($field)
	{
		$settings = Main::get($field['entity_relation']);

		if (!$settings) {
			return;
		}

		$query = $settings->newQuery()->filter(['perpage' => -1]);

		$value = $field['value'];
?>
		<select name="<?= $field['name']; ?>">
			<option value="">--</option>
			<?php
			while ($item = $query->fetch()) {
				$selected = $item->getId() == $value;
			?>
				<option value="<?= $item->getId(); ?>" <?= $selected ? 'selected' : ''; ?>><?= $item->getName(); ?></option>
			<?php
			}
			?>
		</select>
	<?php
	}


	function render_field_settings($field)
	{
		$settings = Main::getAll();
		$options = [];

		foreach ($settings as $sets) {
			$options[$sets->getKey()] = $sets->getLabel('name');
		}

		acf_render_field_setting($field, array(
			'label' => 'Элемент соотношения',
			'instructions' => '',
			'type' => 'select',
			'name' => 'entity_relation',
			'choices' => $options,
			'multiple' => false,
			'ui' => 1,
			'allow_null' => false,
			'placeholder' => 'Все сущности',
		));
	}


	/*
	*  create_options()
	*
	*  Create extra options for your field. This is rendered when editing a field.
	*  The value of $field['name'] can be used (like below) to save extra data to the $field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field	- an array holding all the field's data
	*/

	function create_options($field)
	{
		// defaults?
		/*
		$field = array_merge($this->defaults, $field);
		*/

		// key is needed in the field names to correctly save the data
		$key = $field['name'];
		// Create Field Options HTML
	?>
		<tr class="field_option field_option_<?php echo $this->name; ?>">
			<td class="label">
				<label><?php _e("Preview Size", 'TEXTDOMAIN'); ?></label>
				<p class="description"><?php _e("Thumbnail is advised", 'TEXTDOMAIN'); ?></p>
			</td>
			<td>
				<?php
				// do_action('acf/create_field', [
				// 	'type' => 'radio',
				// 	'name' => 'fields[' . $key . '][preview_size]',
				// 	'value' => $field['preview_size'],
				// 	'layout' => 'horizontal',
				// 	'choices' => [
				// 		'thumbnail' => __('Thumbnail', 'TEXTDOMAIN'),
				// 		'something_else' => __('Something Else', 'TEXTDOMAIN'),
				// 	]
				// ]);
				?>
			</td>
		</tr>
	<?php
	}


	/*
	*  create_field()
	*
	*  Create the HTML interface for your field
	*
	*  @param	$field - an array holding all the field's data
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/

	function create_field($field)
	{
		// defaults?
		/*
		$field = array_merge($this->defaults, $field);
		*/

		// perhaps use $field['preview_size'] to alter the markup?


		// create Field HTML
	?>
		<div>
		</div>
<?php
	}


	/*
	*  input_admin_enqueue_scripts()
	*
	*  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
	*  Use this action to add CSS + JavaScript to assist your create_field() action.
	*
	*  $info	http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/

	function input_admin_enqueue_scripts()
	{
		// Note: This function can be removed if not used

		// vars
		// $url = $this->settings['url'];
		// $version = $this->settings['version'];

		// // register & include JS
		// wp_register_script('TEXTDOMAIN', "{$url}assets/js/input.js", array('acf-input'), $version);
		// wp_enqueue_script('TEXTDOMAIN');


		// // register & include CSS
		// wp_register_style('TEXTDOMAIN', "{$url}assets/css/input.css", array('acf-input'), $version);
		// wp_enqueue_style('TEXTDOMAIN');
	}


	/*
	*  input_admin_head()
	*
	*  This action is called in the admin_head action on the edit screen where your field is created.
	*  Use this action to add CSS and JavaScript to assist your create_field() action.
	*
	*  @info	http://codex.wordpress.org/Plugin_API/Action_Reference/admin_head
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/

	function input_admin_head()
	{
		// Note: This function can be removed if not used
	}


	/*
	*  field_group_admin_enqueue_scripts()
	*
	*  This action is called in the admin_enqueue_scripts action on the edit screen where your field is edited.
	*  Use this action to add CSS + JavaScript to assist your create_field_options() action.
	*
	*  $info	http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/

	function field_group_admin_enqueue_scripts()
	{
		// Note: This function can be removed if not used
	}


	/*
	*  field_group_admin_head()
	*
	*  This action is called in the admin_head action on the edit screen where your field is edited.
	*  Use this action to add CSS and JavaScript to assist your create_field_options() action.
	*
	*  @info	http://codex.wordpress.org/Plugin_API/Action_Reference/admin_head
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/

	function field_group_admin_head()
	{
		// Note: This function can be removed if not used
	}


	/*
	*  load_value()
	*
		*  This filter is applied to the $value after it is loaded from the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value - the value found in the database
	*  @param	$post_id - the $post_id from which the value was loaded
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$value - the value to be saved in the database
	*/

	function load_value($value, $post_id, $field)
	{
		// Note: This function can be removed if not used
		return $value;
	}


	/*
	*  update_value()
	*
	*  This filter is applied to the $value before it is updated in the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value - the value which will be saved in the database
	*  @param	$post_id - the $post_id of which the value will be saved
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$value - the modified value
	*/

	function update_value($value, $post_id, $field)
	{
		// Note: This function can be removed if not used
		return $value;
	}


	/*
	*  format_value()
	*
	*  This filter is applied to the $value after it is loaded from the db and before it is passed to the create_field action
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value	- the value which was loaded from the database
	*  @param	$post_id - the $post_id from which the value was loaded
	*  @param	$field	- the field array holding all the field options
	*
	*  @return	$value	- the modified value
	*/

	function format_value($value, $post_id, $field)
	{
		// defaults?
		/*
		$field = array_merge($this->defaults, $field);
		*/

		// perhaps use $field['preview_size'] to alter the $value?


		// Note: This function can be removed if not used
		return $value;
	}


	/*
	*  format_value_for_api()
	*
	*  This filter is applied to the $value after it is loaded from the db and before it is passed back to the API functions such as the_field
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value	- the value which was loaded from the database
	*  @param	$post_id - the $post_id from which the value was loaded
	*  @param	$field	- the field array holding all the field options
	*
	*  @return	$value	- the modified value
	*/

	function format_value_for_api($value, $post_id, $field)
	{
		// defaults?
		/*
		$field = array_merge($this->defaults, $field);
		*/

		// perhaps use $field['preview_size'] to alter the $value?


		// Note: This function can be removed if not used
		return $value;
	}


	/*
	*  load_field()
	*
	*  This filter is applied to the $field after it is loaded from the database
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$field - the field array holding all the field options
	*/

	function load_field($field)
	{
		// Note: This function can be removed if not used
		return $field;
	}


	/*
	*  update_field()
	*
	*  This filter is applied to the $field before it is saved to the database
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - the field array holding all the field options
	*  @param	$post_id - the field group ID (post_type = acf)
	*
	*  @return	$field - the modified field
	*/

	function update_field($field, $post_id = 0)
	{
		// Note: This function can be removed if not used
		return $field;
	}
}


// initialize
new Press_acf_field_Relation();
