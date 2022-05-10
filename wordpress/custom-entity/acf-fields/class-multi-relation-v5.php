<?php

namespace Vnet\Entity;

use Vnet\Core\Helper;

class Press_acf_field_Multi_Relation extends \acf_field
{


	function initialize()
	{
		$this->name = 'multi_relation';
		$this->label = 'Множественное соотношение';
		$this->category = 'Прессбол';

		$this->defaults = [
			'ajax_action' => 'press_field_multi_relation',
			'entity' => 'category',
			'field_type' => 'checkbox',
			'multiple' => 0,
			'allow_null' => 0,
			'return_format' => 'id',
			'add_term' => 0, // 5.2.3
			'load_terms' => 0, // 5.2.7	
			'save_terms' => 0 // 5.2.7
		];

		add_action('wp_ajax_press_field_multi_relation', [$this, 'ajax_query']);
		add_action('wp_ajax_nopriv_press_field_multi_relation', [$this, 'ajax_query']);
	}


	/*
	*  ajax_query
	*
	*  description
	*
	*  @type	function
	*  @date	24/10/13
	*  @since	5.0.0
	*
	*  @param	$post_id (int)
	*  @return	$post_id (int)
	*/

	function ajax_query()
	{
		if (!acf_verify_ajax()) die();

		$options = acf_parse_args($_POST, [
			'post_id' => 0,
			's' => '',
			'field_key' => '',
			'paged' => 0
		]);

		$field = acf_get_field($options['field_key']);

		if (!$field) {
			exit;
		}

		$query = Main::get($field['entity'])->newQuery();

		$queryArgs = [];

		if (!empty($options['s'])) {
			$queryArgs['search'] = $options['s'];
		}

		if (!empty($options['paged'])) {
			$queryArgs['page'] = $options['paged'];
		}

		$query->filter($queryArgs);

		$res = [
			'limit' => $query->perpage,
			'more' => $query->totalPages > $query->page,
			'results' => []
		];

		while ($row = $query->fetch()) {
			$res['results'][] = [
				'id' => $row->getId(),
				'text' => $row->getName()
			];
		}

		acf_send_ajax_results($res);
	}


	/*
	*  load_value()
	*
	*  This filter is appied to the $value after it is loaded from the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value - the value found in the database
	*  @param	$post_id - the $post_id from which the value was loaded from
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$value - the value to be saved in te database
	*/

	function load_value($value, $post_id, $field)
	{
		return $value;
	}


	/*
	*  update_value()
	*
	*  This filter is appied to the $value before it is updated in the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value - the value which will be saved in the database
	*  @param	$field - the field array holding all the field options
	*  @param	$post_id - the $post_id of which the value will be saved
	*
	*  @return	$value - the modified value
	*/
	function update_value($value, $post_id, $field)
	{
		return $value;
	}


	/*
	*  format_value()
	*
	*  This filter is appied to the $value after it is loaded from the db and before it is returned to the template
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value (mixed) the value which was loaded from the database
	*  @param	$post_id (mixed) the $post_id from which the value was loaded
	*  @param	$field (array) the field array holding all the field options
	*
	*  @return	$value (mixed) the modified value
	*/
	function format_value($value, $post_id, $field)
	{
		return $value;
	}


	/*
	*  render_field()
	*
	*  Create the HTML interface for your field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - an array holding all the field's data
	*/
	function render_field($field)
	{
		// force value to array
		$field['value'] = acf_get_array($field['value']);


		// vars
		$div = [
			'class' => 'acf-taxonomy-field',
			'data-save' => $field['save_terms'],
			'data-ftype' => $field['field_type'],
			'data-entity' => $field['entity'],
			'data-allow_null' => $field['allow_null']
		];
?>
		<div <?php acf_esc_attr_e($div); ?>>
			<?php

			if ($field['field_type'] == 'select') {

				$field['multiple'] = 0;

				$this->render_field_select($field);
			} elseif ($field['field_type'] == 'multi_select') {

				$field['multiple'] = 1;

				$this->render_field_select($field);
			} elseif ($field['field_type'] == 'radio') {

				$this->render_field_checkbox($field);
			} elseif ($field['field_type'] == 'checkbox') {

				$this->render_field_checkbox($field);
			}

			?>
			<style>

			</style>
		</div>
	<?php

	}


	/*
	*  render_field_select()
	*
	*  Create the HTML interface for your field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - an array holding all the field's data
	*/

	function render_field_select($field)
	{
		$field['type'] = 'select';
		$field['ui'] = 1;
		$field['ajax'] = 1;
		$field['choices'] = [];

		// value
		if (!empty($field['value'])) {
			$query = Main::get($field['entity'])->newQuery()->filter(['idin' => $field['value']]);

			while ($row = $query->fetch()) {
				$field['choices'][$row->getId()] = $row->getName();
			}
		}

		// acf_render_field_wrap($field, 'div', 'field');
		$this->acf_render_field_wrap($field);
	}


	function acf_render_field_wrap($field, $element = 'div', $instruction = 'label')
	{

		// Ensure field is complete (adds all settings).
		$field = acf_validate_field($field);

		// Prepare field for input (modifies settings).
		$field = acf_prepare_field($field);

		// Allow filters to cancel render.
		if (!$field) {
			return;
		}

		// Determine wrapping element.
		$elements = array(
			'div'	=> 'div',
			'tr'	=> 'td',
			'td'	=> 'div',
			'ul'	=> 'li',
			'ol'	=> 'li',
			'dl'	=> 'dt',
		);

		if (isset($elements[$element])) {
			$inner_element = $elements[$element];
		} else {
			$element = $inner_element = 'div';
		}

		// Generate wrapper attributes.
		$wrapper = array(
			'id'		=> '',
			'class'		=> 'acf-field',
			'width'		=> '',
			'style'		=> '',
			'data-name'	=> $field['_name'],
			'data-type'	=> $field['type'],
			'data-key'	=> $field['key'],
		);

		// Add field type attributes.
		$wrapper['class'] .= " acf-field-{$field['type']}";

		// add field key attributes
		if ($field['key']) {
			$wrapper['class'] .= " acf-field-{$field['key']}";
		}

		// Add required attributes.
		// Todo: Remove data-required
		if ($field['required']) {
			$wrapper['class'] .= ' is-required';
			$wrapper['data-required'] = 1;
		}

		// Clean up class attribute.
		$wrapper['class'] = str_replace('_', '-', $wrapper['class']);
		$wrapper['class'] = str_replace('field-field-', 'field-', $wrapper['class']);

		// Merge in field 'wrapper' setting without destroying class and style.
		if ($field['wrapper']) {
			$wrapper = acf_merge_attributes($wrapper, $field['wrapper']);
		}

		// Extract wrapper width and generate style.
		// Todo: Move from $wrapper out into $field.
		$width = acf_extract_var($wrapper, 'width');
		if ($width) {
			$width = acf_numval($width);
			if ($element !== 'tr' && $element !== 'td') {
				$wrapper['data-width'] = $width;
				$wrapper['style'] .= " width:{$width}%;";
			}
		}

		// Clean up all attributes.
		$wrapper = array_map('trim', $wrapper);
		$wrapper = array_filter($wrapper);

		/**
		 * Filters the $wrapper array before rendering.
		 *
		 * @date	21/1/19
		 * @since	5.7.10
		 *
		 * @param	array $wrapper The wrapper attributes array.
		 * @param	array $field The field array.
		 */
		$wrapper = apply_filters('acf/field_wrapper_attributes', $wrapper, $field);

		// Append conditional logic attributes.
		if (!empty($field['conditional_logic'])) {
			$wrapper['data-conditions'] = $field['conditional_logic'];
		}
		if (!empty($field['conditions'])) {
			$wrapper['data-conditions'] = $field['conditions'];
		}

		file_put_contents(__DIR__ . '/_DEBUG_', print_r($wrapper, true));

		if (isset($wrapper['data-width'])) {
			unset($wrapper['data-width']);
		}

		if (isset($wrapper['style'])) {
			unset($wrapper['style']);
		}

		$wrapper['style'] = 'margin: 0px;';

		// Vars for render.
		$attributes_html = acf_esc_attr($wrapper);

		// Render HTML
		echo "<$element $attributes_html>" . "\n";
		// if ($element !== 'td') {
		// 	echo "<$inner_element class=\"acf-label\">" . "\n";
		// 	acf_render_field_label($field);
		// 	if ($instruction == 'label') {
		// 		acf_render_field_instructions($field);
		// 	}
		// 	echo "</$inner_element>" . "\n";
		// }
		// echo "<$inner_element class=\"acf-input\">" . "\n";
		acf_render_field($field);
		// if ($instruction == 'field') {
		// 	acf_render_field_instructions($field);
		// }
		// echo "</$inner_element>" . "\n";
		echo "</$element>" . "\n";
	}


	/*
	*  render_field_checkbox()
	*
	*  Create the HTML interface for your field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - an array holding all the field's data
	*/
	function render_field_checkbox($field)
	{
		// hidden input
		acf_hidden_input([
			'type'	=> 'hidden',
			'name'	=> $field['name']
		]);


		// checkbox saves an array
		if ($field['field_type'] == 'checkbox') {
			$field['name'] .= '[]';
		}

		$query = Main::get($field['entity'])->newQuery()->filter(['perpage' => -1]);
	?>
		<div class="categorychecklist-holder">
			<ul class="acf-checkbox-list acf-bl">
				<?php
				while ($row = $query->fetch()) {
				?>
					<li data-id="<?= $row->getId(); ?>">
						<label>
							<?php
							if ($field['field_type'] == 'checkbox') {
							?>
								<input type="checkbox" name="<?= $field['name']; ?>" value="<?= $row->getId(); ?>">
							<?php
							} else {
							?>
								<input type="radio" name="<?= $field['name']; ?>" value="<?= $row->getId(); ?>">
							<?php
							}
							?>
							<span><?= $row->getName(); ?></span>
						</label>
					</li>
				<?php
				}
				?>
			</ul>
		</div>
<?php
	}


	/*
	*  render_field_settings()
	*
	*  Create extra options for your field. This is rendered when editing a field.
	*  The value of $field['name'] can be used (like bellow) to save extra data to the $field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field	- an array holding all the field's data
	*/

	function render_field_settings($field)
	{
		$sets = Main::getAll();

		$entities = [];

		foreach ($sets as $item) {
			$entities[$item->getKey()] = $item->getLabel('name') . ' (' . $item->getKey() . ')';
		}

		acf_render_field_setting($field, [
			'label'			=> 'Сущность',
			'instructions'	=> 'Выберите сущность с которой строить связь',
			'type'			=> 'select',
			'name'			=> 'entity',
			'choices'		=> $entities
		]);


		// field_type
		acf_render_field_setting($field, [
			'label'			=> __('Appearance', 'acf'),
			'instructions'	=> __('Select the appearance of this field', 'acf'),
			'type'			=> 'select',
			'name'			=> 'field_type',
			'optgroup'		=> true,
			'choices'		=> array(
				__("Multiple Values", 'acf') => array(
					'checkbox' => __('Checkbox', 'acf'),
					'multi_select' => __('Multi Select', 'acf')
				),
				__("Single Value", 'acf') => array(
					'radio' => __('Radio Buttons', 'acf'),
					'select' => _x('Select', 'noun', 'acf')
				)
			)
		]);


		// allow_null
		acf_render_field_setting($field, array(
			'label'			=> __('Allow Null?', 'acf'),
			'instructions'	=> '',
			'name'			=> 'allow_null',
			'type'			=> 'true_false',
			'ui'			=> 1,
			'conditions'	=> array(
				'field'		=> 'field_type',
				'operator'	=> '!=',
				'value'		=> 'checkbox'
			)
		));
	}
}

acf_register_field_type(__NAMESPACE__ . '\Press_acf_field_Multi_Relation');
