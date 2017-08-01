<?php
$too_many = 8;

if (count($choices) == 0) return;

if ( ! function_exists('normalizedChoices'))
{
	function normalizedChoices($choices, $disable_headings)
	{
		$return_array = [];
		foreach ($choices as $value => $label)
		{
			if ( ! $disable_headings && is_array($label) && is_string($value))
			{
				$return_array[] = ['section' => $value];
				$return_array = array_merge($return_array, normalizedChoices($label, $disable_headings));
				continue;
			}

			$choice = [
				'value' => $value,
				'label' => $label,
				'instructions' => isset($label['instructions']) ? $label['instructions'] : ''
			];

			if (isset($label['label'])) $choice['label'] = $label['label'];
			if (isset($label['value'])) $choice['value'] = $label['value'];
			if (isset($label['name'])) $choice['label'] = $label['name'];

			if (isset($label['children']))
			{
				$choice['children'] = normalizedChoices($label['children'], $disable_headings);
			}

			$return_array[] = $choice;
		}

		return $return_array;
	}
}

if ( ! function_exists('findLabelForValue'))
{
	function findLabelForValue($value, $choices)
	{
		foreach ($choices as $choice)
		{
			if (isset($choice['value']) && $value == $choice['value'])
			{
				return $choice['label'];
			}

			if (isset($choice['children']))
			{
				$label = findLabelForValue($value, $choice['children']);
				if ($label)
				{
					return $label;
				}
			}
		}

		return FALSE;
	}
}

// Get total number of choices including children
if ( ! function_exists('countChoices'))
{
	function countChoices($choices)
	{
		$count = 0;
		foreach ($choices as $choice)
		{
			$count += 1;
			if (isset($choice['children']))
			{
				$count += countChoices($choice['children']);
			}
		}

		return $count;
	}
}

$nested = isset($nested) ? $nested : FALSE;

// Normalize choices into an array to keep order of items, order cannot be
// counted on in a JavaScript object
$normalized_choices = normalizedChoices($choices, $nested);

$has_groupings = FALSE;
foreach ($normalized_choices as $key => $choice)
{
	if (isset($choice['section']))
	{
		$has_groupings = TRUE;
	}
	if (isset($choice['children']))
	{
		$nested = TRUE;
	}
}

// If it's a small list, just render it server-side
if (countChoices($normalized_choices) <= $too_many && ! $nested && ! $has_groupings):
	// For radios with no value, set value to first choice
	if ( ! $multi && ! $value) {
		$keys = array_keys($choices);
		$value = $keys[0];
	}
	if ( ! isset($scalar) && $multi) $field_name .= '[]';
	?>
	<div class="fields-select">
		<div class="field-inputs">
			<?php foreach ($choices as $key => $choice):
				$label = isset($choice['label']) ? $choice['label'] : $choice;
				$checked = ((is_bool($value) && get_bool_from_string($key) === $value)
					OR ( is_array($value) && in_array($key, $value))
					OR ( ! is_bool($value) && $key == $value)); ?>

				<label<?php if ($checked): ?> class="act"<?php endif ?>>
					<input type="<?=($multi) ? 'checkbox' : 'radio'?>" name="<?=$field_name?>" value="<?=htmlentities($key, ENT_QUOTES, 'UTF-8')?>"<?php if ($checked):?> checked="checked"<?php endif ?><?=isset($attrs) ? $attrs : ''?>> <?=lang($label)?>
				</label>
			<?php endforeach; ?>
		</div>
	</div>
<?php
// Large list, render it using React
else:
	if ($value && ! is_array($value) && ! $multi)
	{
		$label = findLabelForValue($value, $normalized_choices);
		$value = [$value => $label];
	}
	elseif ($multi && ! is_array($value))
	{
		$value = [$value];
	}

	$component = [
		'name' => $field_name,
		'items' => $normalized_choices,
		'selected' => $value,
		'multi' => $multi,
		'nested' => $nested,
		'disabled' => isset($disabled) ? $disabled : FALSE,
		'auto_select_parents' => isset($auto_select_parents) ? $auto_select_parents : NULL,
		'too_many' => $too_many,
		'filter_url' => isset($filter_url) ? $filter_url : NULL,
		'limit' => isset($limit) ? $limit : 100,
		'toggle_all' => NULL,
		'group_toggle' => isset($group_toggle) ? $group_toggle : NULL,
		'manageable' => isset($manageable) ? $manageable : NULL,
		'manage_label' => isset($manage_label) ? $manage_label : NULL,
		'reorder_ajax_url' => isset($reorder_ajax_url) ? $reorder_ajax_url : NULL
	];
	if (isset($no_results['text']))
	{
		$component['no_results'] = lang($no_results['text']);
	}
	?>
	<div data-select-react="<?=base64_encode(json_encode($component))?>" data-input-value="<?=$field_name?>">
		<div class="fields-select">
			<div class="field-inputs">
				<label class="field-loading">
					<?=lang('loading')?><span></span>
				</label>
			</div>
		</div>
	</div>
<?php endif ?>
